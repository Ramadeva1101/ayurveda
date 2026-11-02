<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$user = Auth::require();
$statuses = ['requested' => 'Requested', 'reserved' => 'Reserved', 'checked_in' => 'Checked in', 'checked_out' => 'Checked out', 'cancelled' => 'Cancelled'];

$from = (string) ($_GET['from'] ?? '');
$from = valid_date($from) ? $from : date('Y-m-d', strtotime('-3 days'));
$days = 21;
$until = date('Y-m-d', strtotime("$from +$days days"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete' && $id) {
        Db::run('DELETE FROM stays WHERE id = ?', [$id]);
        flash('Stay removed.');
        redirect('/admin/stays.php?from=' . $from);
    }

    $guestId = (int) ($_POST['guest_id'] ?? 0) ?: null;
    $place = trim((string) ($_POST['place'] ?? ''));
    $room = trim((string) ($_POST['room'] ?? '')) ?: null;
    $in = (string) ($_POST['check_in'] ?? '');
    $out = (string) ($_POST['check_out'] ?? '');
    $status = (string) ($_POST['status'] ?? 'reserved');
    $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;

    if ($place === '' || !valid_date($in) || !valid_date($out) || $out <= $in || !isset($statuses[$status])) {
        flash('Enter the place, and a check-out date after check-in.', 'err');
        redirect('/admin/stays.php?from=' . $from . ($id ? "&edit=$id" : '&new=1'));
    }
    if (!$guestId && !$notes) {
        flash('Pick a guest, or write who is staying in Notes.', 'err');
        redirect('/admin/stays.php?from=' . $from . ($id ? "&edit=$id" : '&new=1'));
    }
    $data = [$guestId, $place, $room, $in, $out, $status, $notes];
    if ($id) {
        Db::run('UPDATE stays SET guest_id=?, place=?, room=?, check_in=?, check_out=?, status=?, notes=? WHERE id=?', [...$data, $id]);
    } else {
        Db::run('INSERT INTO stays (guest_id, place, room, check_in, check_out, status, notes) VALUES (?,?,?,?,?,?,?)', $data);
    }
    flash('Stay saved.');
    redirect('/admin/stays.php?from=' . $from);
}

$placeFilter = trim((string) ($_GET['place'] ?? ''));
$params = [$until, $from];
$wherePlace = '';
if ($placeFilter !== '') {
    $wherePlace = ' AND st.place = ?';
    $params[] = $placeFilter;
}
$stays = Db::all(
    "SELECT st.*, g.name AS guest_name FROM stays st LEFT JOIN guests g ON g.id = st.guest_id
     WHERE st.check_in < ? AND st.check_out > ? AND st.status <> 'cancelled' $wherePlace
     ORDER BY st.place, st.room, st.check_in",
    $params
);
$guests = Db::all('SELECT id, name, whatsapp FROM guests ORDER BY name');
$places = Db::all(
    "SELECT place, COUNT(*) n FROM stays WHERE status <> 'cancelled' AND check_out > ? GROUP BY place ORDER BY place",
    [$from]
);

$edit = null;
if (isset($_GET['edit'])) {
    $edit = Db::one('SELECT * FROM stays WHERE id = ?', [(int) $_GET['edit']]);
} elseif (isset($_GET['new'])) {
    $edit = ['id' => 0, 'guest_id' => null, 'place' => $placeFilter, 'room' => '', 'check_in' => date('Y-m-d'),
             'check_out' => date('Y-m-d', strtotime('+1 day')), 'status' => 'reserved', 'notes' => ''];
}
$pq = $placeFilter !== '' ? '&place=' . rawurlencode($placeFilter) : '';

admin_header('Stays', $user);
?>
<h1>Stays <small><?= e(date('j M', strtotime($from))) ?> – <?= e(date('j M Y', strtotime("$until -1 day"))) ?></small></h1>

<div class="toolbar">
  <div class="seg">
    <a href="?from=<?= date('Y-m-d', strtotime("$from -14 days")) . $pq ?>" title="Earlier">‹</a>
    <a href="?from=<?= date('Y-m-d', strtotime('-3 days')) . $pq ?>" class="on">Today</a>
    <a href="?from=<?= date('Y-m-d', strtotime("$from +14 days")) . $pq ?>" title="Later">›</a>
  </div>
  <div class="chips grow">
    <a href="?from=<?= $from ?>"<?= $placeFilter === '' ? ' class="on"' : '' ?>>All places</a>
    <?php foreach ($places as $p): ?>
      <a href="?from=<?= $from ?>&place=<?= e(rawurlencode($p['place'])) ?>"<?= $placeFilter === $p['place'] ? ' class="on"' : '' ?>><?= e($p['place']) ?> <span class="muted"><?= (int) $p['n'] ?></span></a>
    <?php endforeach; ?>
  </div>
  <a class="btn" href="?from=<?= $from . $pq ?>&new=1"><?= icon('plus', 16) ?>Add stay</a>
</div>
<p class="muted" style="margin-top:-.4rem">Rooms are entered by hand. Nothing here is booked with the accommodation automatically.</p>

<?php if ($edit): ?>
<form method="post" class="card">
  <h2><?= $edit['id'] ? 'Edit stay' : 'New stay' ?></h2>
  <?= Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
  <div class="row">
    <label>Guest
      <select name="guest_id">
        <option value="0">– not in guest list –</option>
        <?php foreach ($guests as $g): ?>
          <option value="<?= (int) $g['id'] ?>"<?= (int) $edit['guest_id'] === (int) $g['id'] ? ' selected' : '' ?>><?= e($g['name']) ?> (<?= e($g['whatsapp']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Place<input name="place" list="places" value="<?= e($edit['place']) ?>" required placeholder="e.g. Bali Silent Retreat"></label>
    <label>Room<input name="room" value="<?= e($edit['room']) ?>"></label>
  </div>
  <datalist id="places"><?php foreach ($places as $p): ?><option value="<?= e($p['place']) ?>"><?php endforeach; ?></datalist>
  <div class="row">
    <label>Check-in<input type="date" name="check_in" value="<?= e($edit['check_in']) ?>" required></label>
    <label>Check-out<input type="date" name="check_out" value="<?= e($edit['check_out']) ?>" required></label>
    <label>Status
      <select name="status"><?php foreach ($statuses as $k => $v): ?><option value="<?= $k ?>"<?= $edit['status'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
    </label>
  </div>
  <label>Notes<input name="notes" value="<?= e($edit['notes']) ?>" placeholder="Required if the guest is not in the list"></label>
  <div class="row">
    <button>Save</button>
    <a class="btn ghost" href="/admin/stays.php?from=<?= $from . $pq ?>">Cancel</a>
  </div>
</form>
<?php if ($edit['id']): ?>
<form method="post" class="card flat" onsubmit="return confirm('Remove this stay?')">
  <?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
  <button class="danger">Remove stay</button>
</form>
<?php endif; ?>
<?php endif; ?>

<?php if (!$stays): ?>
  <div class="card"><p class="empty">No stays in these dates.</p></div>
<?php else: ?>
<div class="tl" style="grid-template-columns: 220px repeat(<?= $days ?>, minmax(44px, 1fr))">
  <div class="tl-label" style="grid-row:1;grid-column:1"><small>Guest / room</small></div>
  <?php for ($i = 0; $i < $days; $i++): $d = strtotime("$from +$i days"); ?>
    <div class="tl-head<?= date('Y-m-d', $d) === date('Y-m-d') ? ' today' : '' ?>" style="grid-row:1;grid-column:<?= $i + 2 ?>"><?= date('D', $d) ?><b><?= date('j', $d) ?></b></div>
  <?php endfor; ?>

  <?php foreach ($stays as $n => $s):
      $row = $n + 2;
      $startIdx = max(0, (int) round((strtotime($s['check_in']) - strtotime($from)) / 86400));
      $endIdx = min($days, (int) round((strtotime($s['check_out']) - strtotime($from)) / 86400));
      $nights = (int) round((strtotime($s['check_out']) - strtotime($s['check_in'])) / 86400); ?>
    <div class="tl-label" style="grid-row:<?= $row ?>;grid-column:1">
      <b><?= e($s['room'] ?: $s['place']) ?></b>
      <small><?= $s['room'] ? e($s['place']) . ' · ' : '' ?><?= e($statuses[$s['status']]) ?></small>
    </div>
    <?php for ($i = 0; $i < $days; $i++): ?>
      <div class="tl-cell<?= date('Y-m-d', strtotime("$from +$i days")) === date('Y-m-d') ? ' tl-now' : '' ?>" style="grid-row:<?= $row ?>;grid-column:<?= $i + 2 ?>"></div>
    <?php endfor; ?>
    <a class="tl-bar st-<?= e($s['status']) ?>" href="?from=<?= $from . $pq ?>&edit=<?= (int) $s['id'] ?>"
       style="grid-row:<?= $row ?>;grid-column:<?= $startIdx + 2 ?> / <?= $endIdx + 2 ?>"
       title="<?= e(date('j M', strtotime($s['check_in'])) . ' – ' . date('j M', strtotime($s['check_out'])) . ", $nights nights") ?>">
      <?= e($s['guest_name'] ?? $s['notes']) ?>
    </a>
  <?php endforeach; ?>
</div>
<p class="legend muted">
  <span class="pill ok">Reserved</span>
  <span class="pill warn">Requested</span>
  <span class="pill" style="background:#1d3140;color:var(--info)">Checked in</span>
  <span class="pill">Checked out</span>
</p>
<?php endif; ?>
<?php admin_footer();
