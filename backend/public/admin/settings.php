<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$user = Auth::require();
$days = [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// "9:00, 10:30" -> "09:00,10:30"; returns null when any entry is invalid.
function normalise_slots(string $raw): ?string
{
    $out = [];
    foreach (preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) as $t) {
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $t, $m)) {
            return null;
        }
        $out[] = sprintf('%02d:%s', (int) $m[1], $m[2]);
    }
    $out = array_unique($out);
    sort($out);
    return implode(',', $out);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $section = $_POST['section'] ?? '';

    if ($section === 'general') {
        $email = trim((string) ($_POST['notify_email'] ?? ''));
        $capacity = (int) ($_POST['slot_capacity'] ?? 0);
        $notice = (int) ($_POST['min_notice_hours'] ?? -1);
        $ahead = (int) ($_POST['max_days_ahead'] ?? 0);

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Notification email is not valid.', 'err');
        } elseif ($capacity < 1 || $capacity > 20 || $notice < 0 || $notice > 336 || $ahead < 1 || $ahead > 730) {
            flash('Check the numbers: capacity 1–20, notice 0–336 hours, days ahead 1–730.', 'err');
        } else {
            Settings::set('notify_email', $email === '' ? null : $email);
            Settings::set('slot_capacity', (string) $capacity);
            Settings::set('min_notice_hours', (string) $notice);
            Settings::set('max_days_ahead', (string) $ahead);
            flash('Saved.');
        }
    }

    if ($section === 'hours') {
        $bad = [];
        $rows = [];
        foreach ($days as $n => $name) {
            $open = isset($_POST['open'][$n]);
            $slots = normalise_slots((string) ($_POST['slots'][$n] ?? ''));
            if ($slots === null) {
                $bad[] = $name;
                continue;
            }
            if ($open && $slots === '') {
                $bad[] = "$name (open but no times)";
                continue;
            }
            $rows[$n] = [$open ? 1 : 0, $slots === '' ? null : $slots];
        }
        if ($bad) {
            flash('Not saved. Fix times for: ' . implode(', ', $bad) . '. Use HH:MM separated by commas.', 'err');
        } else {
            foreach ($rows as $n => [$open, $slots]) {
                Db::run('UPDATE opening_hours SET is_open = ?, slot_times = ? WHERE weekday = ?', [$open, $slots, $n]);
            }
            flash('Opening hours saved.');
        }
    }

    if ($section === 'offer') {
        $pct = (int) ($_POST['offer_percent'] ?? 0);
        $from = (string) ($_POST['offer_from'] ?? '');
        $until = (string) ($_POST['offer_until'] ?? '');
        if ($pct === 0) {
            Settings::set('offer_percent', '0');
            flash('Package offer turned off.');
        } elseif ($pct < 1 || $pct > 90 || !valid_date($from) || !valid_date($until) || $until < $from) {
            flash('Offer needs a percent from 1 to 90 and an end date on or after the start date.', 'err');
        } else {
            Settings::set('offer_percent', (string) $pct);
            Settings::set('offer_from', $from);
            Settings::set('offer_until', $until);
            flash("Package offer saved: $pct% off, " . date('j M', strtotime($from)) . ' – ' . date('j M Y', strtotime($until)) . '.');
        }
    }

    if ($section === 'closed_add') {
        $date = (string) ($_POST['date'] ?? '');
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            flash('Pick a valid date.', 'err');
        } else {
            Db::run(
                'INSERT INTO closed_dates (date, reason) VALUES (?, ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason)',
                [$date, trim((string) ($_POST['reason'] ?? '')) ?: null]
            );
            flash('Closed date added.');
        }
    }

    if ($section === 'closed_remove') {
        Db::run('DELETE FROM closed_dates WHERE date = ?', [(string) ($_POST['date'] ?? '')]);
        flash('Closed date removed.');
    }

    if ($section === 'booking') {
        $want = ($_POST['booking_enabled'] ?? '') === '1';
        $ready = Settings::get('notify_email')
            && (int) Db::value("SELECT COUNT(*) FROM opening_hours WHERE is_open = 1 AND slot_times IS NOT NULL AND slot_times <> ''") > 0
            && (int) Db::value('SELECT COUNT(*) FROM services WHERE active = 1 AND bookable_online = 1') > 0;
        if ($want && !$ready) {
            flash('Online booking needs a notification email, at least one open day and one bookable service first.', 'err');
        } else {
            Settings::set('booking_enabled', $want ? '1' : '0');
            flash($want ? 'Online booking is ON.' : 'Online booking is OFF.');
        }
    }

    redirect('/admin/settings.php');
}

$hours = [];
foreach (Db::all('SELECT weekday, is_open, slot_times FROM opening_hours') as $row) {
    $hours[(int) $row['weekday']] = $row;
}
$closed = Db::all('SELECT date, reason FROM closed_dates WHERE date >= CURDATE() ORDER BY date');
$bookingOn = Settings::get('booking_enabled') === '1';

admin_header('Settings', $user);
?>
<h1>Settings</h1>

<form method="post" class="card">
  <h2>Online booking</h2>
  <?= Csrf::field() ?>
  <input type="hidden" name="section" value="booking">
  <p>Status: <?= $bookingOn ? '<span class="pill ok">ON</span>' : '<span class="pill">OFF</span>' ?></p>
  <input type="hidden" name="booking_enabled" value="<?= $bookingOn ? '0' : '1' ?>">
  <button><?= $bookingOn ? 'Turn off' : 'Turn on' ?></button>
</form>

<?php $offerNow = Pricing::offerPercent(); ?>
<form method="post" class="card">
  <h2>Package offer <?= $offerNow ? '<span class="pill ok">Active now: ' . $offerNow . '% off</span>' : '<span class="pill">Not active today</span>' ?></h2>
  <p class="muted">Discount on packages (the signature experience and residential programs) for requests sent between these dates. Single treatments keep their price. Set 0 to turn it off.</p>
  <?= Csrf::field() ?>
  <input type="hidden" name="section" value="offer">
  <div class="row">
    <label>Percent off<input type="number" name="offer_percent" min="0" max="90" value="<?= e(Settings::get('offer_percent', '0')) ?>"></label>
    <label>From<input type="date" name="offer_from" value="<?= e(Settings::get('offer_from')) ?>"></label>
    <label>Until (last day)<input type="date" name="offer_until" value="<?= e(Settings::get('offer_until')) ?>"></label>
  </div>
  <button>Save offer</button>
</form>

<form method="post" class="card">
  <h2>General</h2>
  <?= Csrf::field() ?>
  <input type="hidden" name="section" value="general">
  <label>Send new booking alerts to
    <input type="email" name="notify_email" value="<?= e(Settings::get('notify_email')) ?>" placeholder="booking@ayurvedicwellnesshouse.com">
  </label>
  <div class="row">
    <label>Bookings per time slot<input type="number" name="slot_capacity" min="1" max="20" value="<?= e(Settings::get('slot_capacity', '1')) ?>"></label>
    <label>Minimum notice (hours)<input type="number" name="min_notice_hours" min="0" max="336" value="<?= e(Settings::get('min_notice_hours', '24')) ?>"></label>
    <label>Book up to (days ahead)<input type="number" name="max_days_ahead" min="1" max="730" value="<?= e(Settings::get('max_days_ahead', '180')) ?>"></label>
  </div>
  <button>Save</button>
</form>

<form method="post" class="card">
  <h2>Opening days &amp; start times</h2>
  <p class="muted">Start times guests can pick, e.g. <code>09:00, 10:30, 13:00</code>.</p>
  <?= Csrf::field() ?>
  <input type="hidden" name="section" value="hours">
  <table>
    <tr><th>Day</th><th>Open</th><th>Start times</th></tr>
    <?php foreach ($days as $n => $name): $h = $hours[$n] ?? ['is_open' => 0, 'slot_times' => null]; ?>
    <tr>
      <td><?= $name ?></td>
      <td><input type="checkbox" name="open[<?= $n ?>]" <?= $h['is_open'] ? 'checked' : '' ?>></td>
      <td><input name="slots[<?= $n ?>]" value="<?= e(str_replace(',', ', ', (string) $h['slot_times'])) ?>"></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <button>Save hours</button>
</form>

<section class="card">
  <h2>Closed dates</h2>
  <form method="post" class="row">
    <?= Csrf::field() ?>
    <input type="hidden" name="section" value="closed_add">
    <label>Date<input type="date" name="date" required></label>
    <label>Reason (optional)<input name="reason" placeholder="e.g. Nyepi"></label>
    <button>Add</button>
  </form>
  <?php if (!$closed): ?>
    <p class="muted">No upcoming closed dates.</p>
  <?php else: ?>
  <table>
    <?php foreach ($closed as $c): ?>
    <tr>
      <td><?= e(date('D j M Y', strtotime($c['date']))) ?></td>
      <td><?= e($c['reason']) ?></td>
      <td>
        <form method="post"><?= Csrf::field() ?>
          <input type="hidden" name="section" value="closed_remove">
          <input type="hidden" name="date" value="<?= e($c['date']) ?>">
          <button class="link">Remove</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</section>
<?php admin_footer();
