<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$user = Auth::require();

// Week starting Monday.
$start = (string) ($_GET['week'] ?? '');
$monday = valid_date($start) ? new DateTime($start) : new DateTime('today');
$monday->modify('-' . ((int) $monday->format('N') - 1) . ' days');
$weekStart = $monday->format('Y-m-d');
$weekEnd = (clone $monday)->modify('+7 days')->format('Y-m-d');
$pid = (int) ($_GET['p'] ?? 0);
$back = '/admin/calendar.php?week=' . $weekStart . ($pid ? "&p=$pid" : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        Db::run('DELETE FROM appointments WHERE id = ?', [$id]);
        flash('Removed from calendar.');
    } elseif ($action === 'save') {
        $date = (string) ($_POST['date'] ?? '');
        $from = (string) ($_POST['from'] ?? '');
        $to = (string) ($_POST['to'] ?? '');
        $title = trim((string) ($_POST['title'] ?? ''));
        $kind = ($_POST['kind'] ?? '') === 'block' ? 'block' : 'session';
        $practitioner = (int) ($_POST['practitioner_id'] ?? 0) ?: null;
        $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;
        $timeOk = fn ($t) => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);

        if (!valid_date($date) || !$timeOk($from) || !$timeOk($to) || $to <= $from) {
            flash('Enter a date and a start time before the end time.', 'err');
        } elseif ($title === '') {
            flash('Enter a title.', 'err');
        } else {
            $data = [$title, "$date $from:00", "$date $to:00", $kind, $practitioner, $notes];
            if ($id) {
                Db::run('UPDATE appointments SET title=?, starts_at=?, ends_at=?, kind=?, practitioner_id=?, notes=? WHERE id=?', [...$data, $id]);
            } else {
                Db::run('INSERT INTO appointments (title, starts_at, ends_at, kind, practitioner_id, notes) VALUES (?,?,?,?,?,?)', $data);
            }
            flash('Calendar saved.');
        }
    }
    redirect($back);
}

$practitioners = Db::all('SELECT id, name FROM practitioners WHERE active = 1 ORDER BY name');
$params = ["$weekStart 00:00:00", "$weekEnd 00:00:00"];
$filter = '';
if ($pid) {
    $filter = ' AND (a.practitioner_id = ? OR a.practitioner_id IS NULL)';
    $params[] = $pid;
}
$items = Db::all(
    "SELECT a.*, p.name AS practitioner, b.code, b.status AS booking_status
     FROM appointments a
     LEFT JOIN practitioners p ON p.id = a.practitioner_id
     LEFT JOIN bookings b ON b.id = a.booking_id
     WHERE a.starts_at >= ? AND a.starts_at < ? $filter
     ORDER BY a.starts_at",
    $params
);
// Requests that are not confirmed yet, so the clinic sees what is pending.
$pending = Db::all(
    "SELECT b.id, b.code, b.start_date, b.start_time, g.name, s.name AS service_name, s.duration_minutes
     FROM bookings b JOIN guests g ON g.id = b.guest_id JOIN services s ON s.id = b.service_id
     WHERE b.status = 'requested' AND b.start_time IS NOT NULL AND b.start_date >= ? AND b.start_date < ?
     ORDER BY b.start_date, b.start_time",
    [$weekStart, $weekEnd]
);
// Programs have no time: shown in a top row on every day they run.
$programs = Db::all(
    "SELECT b.id, b.code, b.start_date, b.status, g.name, s.name AS service_name, s.duration_days
     FROM bookings b JOIN guests g ON g.id = b.guest_id JOIN services s ON s.id = b.service_id
     WHERE b.start_time IS NULL AND b.status IN ('requested','confirmed','checked_in')
       AND b.start_date < ? AND DATE_ADD(b.start_date, INTERVAL s.duration_days DAY) > ?
     ORDER BY b.start_date",
    [$weekEnd, $weekStart]
);

// One list of timed blocks per day.
$events = [];
foreach ($items as $it) {
    $events[substr($it['starts_at'], 0, 10)][] = [
        'start' => strtotime($it['starts_at']), 'end' => strtotime($it['ends_at']),
        'title' => $it['title'], 'kind' => $it['kind'],
        'sub' => ($it['practitioner'] ?? ($it['kind'] === 'block' ? 'Whole clinic' : 'Unassigned')) . ($it['code'] ? ' · ' . $it['code'] : ''),
        'href' => "?week=$weekStart" . ($pid ? "&p=$pid" : '') . '&edit=' . (int) $it['id'],
    ];
}
foreach ($pending as $pb) {
    $st = strtotime($pb['start_date'] . ' ' . $pb['start_time']);
    $events[$pb['start_date']][] = [
        'start' => $st, 'end' => $st + max(30, (int) $pb['duration_minutes']) * 60,
        'title' => $pb['name'], 'kind' => 'pending',
        'sub' => 'Request · ' . $pb['service_name'],
        'href' => '/admin/booking.php?id=' . (int) $pb['id'],
    ];
}

// Visible hours: 08:00–18:00, stretched to fit anything outside.
$fromH = 8;
$toH = 18;
foreach ($events as $list) {
    foreach ($list as $ev) {
        $fromH = min($fromH, (int) date('G', $ev['start']));
        $toH = max($toH, (int) ceil(((int) date('G', $ev['end']) * 60 + (int) date('i', $ev['end'])) / 60));
    }
}
$toH = min(24, max($toH, $fromH + 1));
$px = 60; // pixels per hour

// Side-by-side lanes for overlapping blocks.
foreach ($events as $day => $list) {
    usort($list, fn ($a, $b) => $a['start'] <=> $b['start']);
    $laneEnds = [];
    foreach ($list as $k => $ev) {
        $lane = 0;
        while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $ev['start']) {
            $lane++;
        }
        $laneEnds[$lane] = $ev['end'];
        $list[$k]['lane'] = $lane;
    }
    foreach ($list as $k => $ev) {
        $list[$k]['lanes'] = max(1, count($laneEnds));
    }
    $events[$day] = $list;
}

$openDays = [];
foreach (Db::all('SELECT weekday, is_open FROM opening_hours') as $oh) {
    $openDays[(int) $oh['weekday']] = (bool) $oh['is_open'];
}
$closedDates = array_column(Db::all('SELECT date FROM closed_dates WHERE date >= ? AND date < ?', [$weekStart, $weekEnd]), 'date');

$edit = null;
if (isset($_GET['edit'])) {
    $edit = Db::one('SELECT * FROM appointments WHERE id = ?', [(int) $_GET['edit']]);
} elseif (isset($_GET['new'])) {
    $d = valid_date((string) $_GET['new']) ? (string) $_GET['new'] : date('Y-m-d');
    $edit = ['id' => 0, 'title' => '', 'starts_at' => "$d 09:00:00", 'ends_at' => "$d 10:00:00",
             'kind' => 'session', 'practitioner_id' => $pid ?: null, 'notes' => '', 'booking_id' => null];
}

$prev = (clone $monday)->modify('-7 days')->format('Y-m-d');
$nextW = (clone $monday)->modify('+7 days')->format('Y-m-d');
$pq = $pid ? "&p=$pid" : '';
$days = [];
for ($i = 0; $i < 7; $i++) {
    $day = (clone $monday)->modify("+$i days");
    $key = $day->format('Y-m-d');
    $days[] = [
        'key' => $key,
        'label' => $day->format('D j'),
        'today' => $key === date('Y-m-d'),
        'closed' => in_array($key, $closedDates, true) || empty($openDays[(int) $day->format('N')]),
    ];
}

admin_header('Calendar', $user);
?>
<h1>Calendar <small><?= e($monday->format('j M')) ?> – <?= e((clone $monday)->modify('+6 days')->format('j M Y')) ?></small></h1>

<div class="toolbar">
  <div class="seg">
    <a href="?week=<?= $prev . $pq ?>" title="Previous week">‹</a>
    <a href="?week=<?= date('Y-m-d') . $pq ?>"<?= $weekStart <= date('Y-m-d') && date('Y-m-d') < $weekEnd ? ' class="on"' : '' ?>>This week</a>
    <a href="?week=<?= $nextW . $pq ?>" title="Next week">›</a>
  </div>
  <div class="chips grow">
    <a href="?week=<?= $weekStart ?>"<?= !$pid ? ' class="on"' : '' ?>>Everyone</a>
    <?php foreach ($practitioners as $p): ?>
      <a href="?week=<?= $weekStart ?>&p=<?= (int) $p['id'] ?>"<?= $pid === (int) $p['id'] ? ' class="on"' : '' ?>><?= e($p['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <a class="btn" href="?week=<?= $weekStart . $pq ?>&new=<?= max($weekStart, date('Y-m-d')) ?>"><?= icon('plus', 16) ?>Add to calendar</a>
</div>

<?php if ($edit): ?>
<form method="post" class="card">
  <h2><?= $edit['id'] ? 'Edit entry' : 'New entry' ?></h2>
  <?php if ($edit['booking_id']): ?><p class="muted">Linked to a booking. Moving it here does not move the booking; use the booking page for that.</p><?php endif; ?>
  <?= Csrf::field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
  <label>Title<input name="title" value="<?= e($edit['title']) ?>" required placeholder="e.g. Shirodhara – guest name, or Lunch break"></label>
  <div class="row">
    <label>Date<input type="date" name="date" value="<?= e(substr($edit['starts_at'], 0, 10)) ?>" required></label>
    <label>From<input type="time" name="from" value="<?= e(substr($edit['starts_at'], 11, 5)) ?>" required></label>
    <label>To<input type="time" name="to" value="<?= e(substr($edit['ends_at'], 11, 5)) ?>" required></label>
    <label>Practitioner
      <select name="practitioner_id">
        <option value="0">– whole clinic –</option>
        <?php foreach ($practitioners as $p): ?>
          <option value="<?= (int) $p['id'] ?>"<?= (int) $edit['practitioner_id'] === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Type
      <select name="kind">
        <option value="session"<?= $edit['kind'] === 'session' ? ' selected' : '' ?>>Session</option>
        <option value="block"<?= $edit['kind'] === 'block' ? ' selected' : '' ?>>Blocked time</option>
      </select>
    </label>
  </div>
  <label>Notes<input name="notes" value="<?= e($edit['notes']) ?>"></label>
  <p class="muted">"Blocked time" with no practitioner closes those online booking slots for everyone.</p>
  <div class="row">
    <button>Save</button>
    <a class="btn ghost" href="<?= e($back) ?>">Cancel</a>
  </div>
</form>
<?php if ($edit['id']): ?>
<form method="post" class="card flat" onsubmit="return confirm('Remove this entry from the calendar?')">
  <?= Csrf::field() ?>
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
  <button class="danger">Remove entry</button>
</form>
<?php endif; ?>
<?php endif; ?>

<div class="cal">
  <div class="cal-corner"></div>
  <?php foreach ($days as $d): ?>
    <div class="cal-h<?= $d['today'] ? ' today' : '' ?><?= $d['closed'] ? ' closed' : '' ?>">
      <span><?= e($d['label']) ?><?= $d['closed'] ? ' <small>closed</small>' : '' ?></span>
      <a href="?week=<?= $weekStart . $pq ?>&new=<?= $d['key'] ?>" title="Add on this day">+</a>
    </div>
  <?php endforeach; ?>

  <?php if ($programs): ?>
  <div class="cal-allday-label">Programs</div>
  <?php foreach ($days as $d): ?>
    <div class="cal-allday-cell">
      <?php foreach ($programs as $pg):
          $end = date('Y-m-d', strtotime($pg['start_date'] . ' +' . (int) $pg['duration_days'] . ' days'));
          if ($d['key'] < $pg['start_date'] || $d['key'] >= $end) {
              continue;
          } ?>
        <a class="pill <?= $pg['status'] === 'requested' ? 'warn' : 'ok' ?>" href="/admin/booking.php?id=<?= (int) $pg['id'] ?>"
           title="<?= e($pg['service_name']) ?>"><?= e($pg['name']) ?><?= $d['key'] === $pg['start_date'] ? ' · day 1' : '' ?></a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="cal-times" style="height:<?= ($toH - $fromH) * $px ?>px">
    <?php for ($h = $fromH + 1; $h < $toH; $h++): ?><span style="top:<?= ($h - $fromH) * $px ?>px"><?= sprintf('%02d:00', $h) ?></span><?php endfor; ?>
  </div>
  <?php foreach ($days as $d): ?>
  <div class="cal-col<?= $d['today'] ? ' today' : '' ?><?= $d['closed'] ? ' closed' : '' ?>" style="height:<?= ($toH - $fromH) * $px ?>px">
    <?php foreach ($events[$d['key']] ?? [] as $ev):
        $base = strtotime($d['key'] . ' ' . sprintf('%02d:00', $fromH));
        $top = ($ev['start'] - $base) / 3600 * $px;
        $height = max(26, ($ev['end'] - $ev['start']) / 3600 * $px - 2);
        $w = 100 / $ev['lanes']; ?>
      <a class="ev <?= e($ev['kind']) ?>" href="<?= e($ev['href']) ?>"
         style="top:<?= round($top) ?>px;height:<?= round($height) ?>px;left:calc(<?= round($w * $ev['lane'], 3) ?>% + 3px);width:calc(<?= round($w, 3) ?>% - 6px)">
        <b><?= e(date('H:i', $ev['start'])) ?> <?= e($ev['title']) ?></b>
        <small><?= e($ev['sub']) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div>
<p class="legend muted">
  <span class="pill ok">Session</span> confirmed or added by hand
  <span class="pill warn">Request</span> waiting for confirmation
  <span class="pill">Blocked</span> not bookable online
</p>
<?php admin_footer();
