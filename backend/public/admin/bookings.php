<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$user = Auth::require();

$view = ($_GET['view'] ?? 'board') === 'list' ? 'list' : 'board';
$status = (string) ($_GET['status'] ?? ($view === 'list' ? 'all' : 'open'));
$q = trim((string) ($_GET['q'] ?? ''));
$from = (string) ($_GET['from'] ?? '');
$to = (string) ($_GET['to'] ?? '');

$where = [];
$params = [];
if ($view === 'board') {
    // Board: everything still in progress, plus what finished in the last 14 days.
    $where[] = "(b.status IN ('requested','confirmed','checked_in')
                 OR (b.status = 'completed' AND b.updated_at >= NOW() - INTERVAL 14 DAY))";
} elseif ($status === 'open') {
    $where[] = "b.status IN ('requested','confirmed','checked_in')";
} elseif (isset(Booking::STATUS_LABELS[$status])) {
    $where[] = 'b.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(g.name LIKE ? OR g.whatsapp LIKE ? OR g.email LIKE ? OR b.code LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
if (valid_date($from)) {
    $where[] = 'b.start_date >= ?';
    $params[] = $from;
}
if (valid_date($to)) {
    $where[] = 'b.start_date <= ?';
    $params[] = $to;
}

$rows = Db::all(
    'SELECT b.id, b.code, b.start_date, b.start_time, b.status, b.created_at, b.guests_count,
            g.name, g.whatsapp, g.country, s.name AS service_name, s.type AS service_type, hc.answers
     FROM bookings b JOIN guests g ON g.id = b.guest_id JOIN services s ON s.id = b.service_id
     LEFT JOIN health_checks hc ON hc.booking_id = b.id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY b.start_date, b.start_time LIMIT 400',
    $params
);

$columns = [
    'requested' => ['Requested', 'warn'],
    'confirmed' => ['Confirmed', 'ok'],
    'checked_in' => ['Checked in', 'info'],
    'completed' => ['Completed (last 14 days)', ''],
];
$byStatus = array_fill_keys(array_keys($columns), []);
foreach ($rows as $r) {
    if (isset($byStatus[$r['status']])) {
        $byStatus[$r['status']][] = $r;
    }
}

$qs = fn (array $extra) => '?' . http_build_query(array_filter(array_merge(['view' => $view, 'q' => $q, 'from' => $from, 'to' => $to, 'status' => $view === 'list' ? $status : null], $extra), fn ($v) => $v !== null && $v !== ''));

admin_header('Bookings', $user);
?>
<h1>Bookings</h1>

<div class="toolbar">
  <div class="seg">
    <a href="<?= e($qs(['view' => 'board', 'status' => null])) ?>"<?= $view === 'board' ? ' class="on"' : '' ?>>Board</a>
    <a href="<?= e($qs(['view' => 'list'])) ?>"<?= $view === 'list' ? ' class="on"' : '' ?>>List</a>
  </div>
  <form method="get" class="inline grow">
    <input type="hidden" name="view" value="<?= $view ?>">
    <input name="q" value="<?= e($q) ?>" placeholder="Name, WhatsApp, email or AWH-…" style="flex:1;max-width:320px">
    <input type="date" name="from" value="<?= e($from) ?>" title="From date">
    <input type="date" name="to" value="<?= e($to) ?>" title="To date">
    <?php if ($view === 'list'): ?>
    <select name="status">
      <option value="all"<?= $status === 'all' ? ' selected' : '' ?>>All statuses</option>
      <option value="open"<?= $status === 'open' ? ' selected' : '' ?>>Open</option>
      <?php foreach (Booking::STATUS_LABELS as $k => $v): ?>
        <option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button class="ghost">Filter</button>
  </form>
</div>

<?php if ($view === 'board'): ?>
<div class="board">
  <?php foreach ($columns as $st => [$label, $tone]): ?>
  <div class="col">
    <div class="col-head"><span class="dot <?= $tone ?>"></span><?= e($label) ?><span class="n"><?= count($byStatus[$st]) ?></span></div>
    <?php foreach ($byStatus[$st] as $r):
        $health = $r['answers'] ? Health::needsAttention(json_decode($r['answers'], true) ?: []) : false;
        $old = $st === 'requested' && strtotime($r['created_at']) < time() - 24 * 3600; ?>
    <a class="bcard" href="/admin/booking.php?id=<?= (int) $r['id'] ?>">
      <div class="bcard-top"><?= avatar($r['name']) ?><span><b><?= e($r['name']) ?></b><small><?= e($r['code']) ?><?= $r['country'] ? ' · ' . e($r['country']) : '' ?></small></span></div>
      <p><?= e($r['service_name']) ?></p>
      <p class="when"><?= e(Booking::whenText($r)) ?><?= (int) $r['guests_count'] > 1 ? ' · 2 guests' : '' ?></p>
      <?php if ($health || $old): ?>
      <div class="tags"><?= $old ? '<span class="pill bad">over 24 h</span>' : '' ?><?= $health ? '<span class="pill warn">health notes</span>' : '' ?></div>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php if (!$byStatus[$st]): ?><p class="empty">None</p><?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<?php else: ?>
<section class="card">
<?php if (!$rows): ?>
  <p class="empty">No bookings match.</p>
<?php else: ?>
  <table>
    <tr><th>Ref</th><th>Guest</th><th>Service</th><th>When</th><th>Status</th><th>Received</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><a class="ref" href="/admin/booking.php?id=<?= (int) $r['id'] ?>"><?= e($r['code']) ?></a></td>
      <td><?= e($r['name']) ?><?= (int) $r['guests_count'] > 1 ? ' <span class="muted">+1</span>' : '' ?><br><small class="muted"><?= e($r['whatsapp']) ?></small></td>
      <td><?= e($r['service_name']) ?></td>
      <td><?= e(Booking::whenText($r)) ?></td>
      <td><?= status_pill($r['status']) ?></td>
      <td><small class="muted"><?= e(date('j M H:i', strtotime($r['created_at']))) ?></small></td>
    </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
</section>
<?php endif; ?>
<?php admin_footer();
