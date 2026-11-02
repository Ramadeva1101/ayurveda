<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$user = Auth::require();

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$hour = (int) date('G');
$greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));

$requests = Db::all(
    "SELECT b.id, b.code, b.start_date, b.start_time, b.created_at, g.name, s.name AS service_name,
            hc.answers
     FROM bookings b JOIN guests g ON g.id = b.guest_id JOIN services s ON s.id = b.service_id
     LEFT JOIN health_checks hc ON hc.booking_id = b.id
     WHERE b.status = 'requested' ORDER BY b.created_at"
);
$schedule = Db::all(
    "SELECT a.id, a.title, a.starts_at, a.ends_at, a.kind, a.booking_id, p.name AS practitioner
     FROM appointments a LEFT JOIN practitioners p ON p.id = a.practitioner_id
     WHERE DATE(a.starts_at) = ? ORDER BY a.starts_at",
    [$today]
);
$sessionsToday = count(array_filter($schedule, fn ($a) => $a['kind'] === 'session'));
$stayingNow = Db::all(
    "SELECT st.*, g.name AS guest_name FROM stays st LEFT JOIN guests g ON g.id = st.guest_id
     WHERE st.status IN ('reserved','checked_in') AND st.check_in <= ? AND st.check_out > ?",
    [$today, $today]
);
$arrivals = Db::all(
    "SELECT st.*, g.name AS guest_name FROM stays st LEFT JOIN guests g ON g.id = st.guest_id
     WHERE st.status IN ('requested','reserved') AND st.check_in IN (?, ?) ORDER BY st.check_in",
    [$today, $tomorrow]
);
$programsStarting = Db::all(
    "SELECT b.id, b.code, b.start_date, g.name, s.name AS service_name
     FROM bookings b JOIN guests g ON g.id = b.guest_id JOIN services s ON s.id = b.service_id
     WHERE b.status = 'confirmed' AND b.start_time IS NULL AND b.start_date IN (?, ?)",
    [$today, $tomorrow]
);
$failed = Db::all("SELECT id, subject, to_email FROM email_queue WHERE status = 'failed' ORDER BY id DESC LIMIT 5");

$checks = [
    'Notification email set' => (bool) Settings::get('notify_email'),
    'At least one open day with start times' => (int) Db::value(
        "SELECT COUNT(*) FROM opening_hours WHERE is_open = 1 AND slot_times IS NOT NULL AND slot_times <> ''"
    ) > 0,
    'At least one service bookable online' => (int) Db::value(
        'SELECT COUNT(*) FROM services WHERE active = 1 AND bookable_online = 1'
    ) > 0,
];
$bookingOn = Settings::get('booking_enabled') === '1';
$closedToday = (bool) Db::value('SELECT 1 FROM closed_dates WHERE date = ?', [$today])
    || !(int) Db::value('SELECT is_open FROM opening_hours WHERE weekday = ?', [(int) date('N')]);

$attention = count($requests) + count($arrivals) + count($programsStarting) + count($failed) + ($bookingOn ? 0 : 1);

admin_header('Today', $user);
?>
<p class="eyebrow"><?= e(date('l · j M Y')) ?></p>
<div class="greet-row">
  <h1 class="greet" style="padding-right:0;margin:0"><?= e($greeting) ?></h1>
  <span class="summary-line">
    <?= count($stayingNow) ?> staying · <?= $sessionsToday ?> session<?= $sessionsToday === 1 ? '' : 's' ?> today
    <?= $closedToday ? ' · clinic closed today' : '' ?>
  </span>
</div>

<div class="stats">
  <a class="stat amber" href="/admin/bookings.php?view=list&status=requested">
    <span>To confirm</span><b><?= count($requests) ?></b><small><?= count($requests) === 1 ? 'request waiting' : 'requests waiting' ?></small>
  </a>
  <a class="stat green" href="/admin/calendar.php">
    <span>Sessions today</span><b><?= $sessionsToday ?></b><small>in the calendar</small>
  </a>
  <a class="stat blue" href="/admin/stays.php">
    <span>Staying now</span><b><?= count($stayingNow) ?></b><small><?= count($arrivals) ?> arriving today or tomorrow</small>
  </a>
  <a class="stat rust" href="/admin/emails.php">
    <span>Emails not sent</span><b><?= count($failed) ?></b><small><?= count($failed) ? 'check and retry' : 'all delivered' ?></small>
  </a>
</div>

<div class="grid2">
  <section class="card">
    <h2>Needs attention <span class="count"><?= $attention ?></span></h2>
    <?php if (!$attention): ?>
      <p class="empty">Nothing needs attention right now.</p>
    <?php else: ?>
    <ul class="list">
      <?php if (!$bookingOn): ?>
      <li><span class="dot warn"></span><span class="grow"><b>Online booking is off</b><small>Guests see "not open yet" on the Book page.</small></span>
        <a class="btn sm ghost" href="/admin/settings.php">Settings</a></li>
      <?php endif; ?>
      <?php foreach ($requests as $r):
          $old = strtotime($r['created_at']) < time() - 24 * 3600;
          $health = $r['answers'] ? Health::needsAttention(json_decode($r['answers'], true) ?: []) : false; ?>
      <li>
        <?= avatar($r['name']) ?>
        <span class="grow">
          <b><?= e($r['name']) ?> <a class="ref" href="/admin/booking.php?id=<?= (int) $r['id'] ?>"><?= e($r['code']) ?></a></b>
          <small><?= e($r['service_name']) ?> · <?= e(Booking::whenText($r)) ?></small>
          <?php if ($old || $health): ?><br><?= $old ? '<span class="pill bad">waiting over 24 h</span> ' : '' ?><?= $health ? '<span class="pill warn">health notes</span>' : '' ?><?php endif; ?>
        </span>
        <a class="btn sm" href="/admin/booking.php?id=<?= (int) $r['id'] ?>">Review</a>
      </li>
      <?php endforeach; ?>
      <?php foreach ($programsStarting as $p): ?>
      <li><span class="dot info"></span><span class="grow"><b><?= e($p['name']) ?> starts a program <?= $p['start_date'] === $today ? 'today' : 'tomorrow' ?></b>
        <small><?= e($p['service_name']) ?> · plan the sessions in the calendar</small></span>
        <a class="btn sm ghost" href="/admin/calendar.php?week=<?= e($p['start_date']) ?>">Calendar</a></li>
      <?php endforeach; ?>
      <?php foreach ($arrivals as $a): ?>
      <li><span class="dot ok"></span><span class="grow"><b><?= e($a['guest_name'] ?? $a['notes']) ?> checks in <?= $a['check_in'] === $today ? 'today' : 'tomorrow' ?></b>
        <small><?= e($a['place']) ?><?= $a['room'] ? ' · ' . e($a['room']) : '' ?><?= $a['status'] === 'requested' ? ' · room not confirmed yet' : '' ?></small></span>
        <a class="btn sm ghost" href="/admin/stays.php?edit=<?= (int) $a['id'] ?>">Open</a></li>
      <?php endforeach; ?>
      <?php foreach ($failed as $m): ?>
      <li><span class="dot bad"></span><span class="grow"><b>Email not sent</b><small><?= e($m['subject']) ?> → <?= e($m['to_email']) ?></small></span>
        <a class="btn sm ghost" href="/admin/emails.php">Emails</a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Today's calendar <span class="count"><a href="/admin/calendar.php">Open calendar</a></span></h2>
    <?php if (!$schedule): ?>
      <p class="empty">Nothing in the calendar today.</p>
    <?php else: ?>
    <ul class="timeline-day">
      <?php foreach ($schedule as $a): ?>
      <li>
        <time><?= e(substr($a['starts_at'], 11, 5)) ?></time>
        <div class="slot <?= e($a['kind']) ?>">
          <b><?= e($a['title']) ?></b><br>
          <small class="muted"><?= e(substr($a['starts_at'], 11, 5)) ?>–<?= e(substr($a['ends_at'], 11, 5)) ?><?= $a['practitioner'] ? ' · ' . e($a['practitioner']) : '' ?></small>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>

<?php if (!$bookingOn || in_array(false, $checks, true)): ?>
<section class="card">
  <h2>Online booking setup <?= $bookingOn ? '<span class="pill ok">ON</span>' : '<span class="pill">OFF</span>' ?></h2>
  <ul class="checks">
    <?php foreach ($checks as $label => $done): ?>
      <li class="<?= $done ? 'done' : '' ?>"><?= e($label) ?></li>
    <?php endforeach; ?>
  </ul>
  <a class="btn sm ghost" href="/admin/settings.php">Go to Settings</a>
</section>
<?php endif; ?>
<?php admin_footer();
