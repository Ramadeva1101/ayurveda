<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$user = Auth::require();
$id = (int) ($_GET['id'] ?? 0);
$b = Booking::find($id);
if (!$b) {
    http_response_code(404);
    admin_header('Not found', $user);
    echo '<p>Booking not found.</p>';
    admin_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = (string) ($_POST['action'] ?? '');
    $notify = isset($_POST['notify']);
    $err = null;

    if ($action === 'status') {
        $err = Booking::changeStatus($id, (string) ($_POST['to'] ?? ''), (int) $user['id'], $notify);
        $ok = 'Status updated.';
    } elseif ($action === 'reschedule') {
        $err = Booking::reschedule($id, (string) ($_POST['date'] ?? ''), (string) ($_POST['time'] ?? '') ?: null,
            (int) $user['id'], $notify, isset($_POST['force']));
        $ok = 'Booking moved.';
    } elseif ($action === 'email') {
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        if (!$b['email']) {
            $err = 'This guest has no email address. Use WhatsApp.';
        } elseif ($subject === '' || $message === '') {
            $err = 'Write a subject and a message.';
        } else {
            Emails::customReply($b, mb_substr($subject, 0, 200), mb_substr($message, 0, 10000));
            Booking::log($id, (int) $user['id'], 'email', 'Sent email: ' . mb_substr($subject, 0, 200));
            $ok = 'Email sent.';
        }
    } elseif ($action === 'note') {
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note === '') {
            $err = 'Write a note first.';
        } else {
            Booking::log($id, (int) $user['id'], 'note', mb_substr($note, 0, 2000));
            $ok = 'Note added.';
        }
    } else {
        $err = 'Unknown action.';
    }

    if ($err === null) {
        Mailer::processQueue(5);
    }
    flash($err ?? $ok, $err ? 'err' : 'ok');
    redirect('/admin/booking.php?id=' . $id);
}

$health = json_decode((string) Db::value('SELECT answers FROM health_checks WHERE booking_id = ?', [$id]), true) ?: null;
$events = Db::all(
    'SELECT e.*, u.name AS user_name FROM booking_events e LEFT JOIN users u ON u.id = e.user_id
     WHERE e.booking_id = ? ORDER BY e.id DESC',
    [$id]
);
$emails = Db::all('SELECT id, template, to_email, subject, status, sent_at, last_error FROM email_queue WHERE booking_id = ? ORDER BY id DESC', [$id]);
$others = Db::all(
    'SELECT id, code, start_date, start_time, status FROM bookings WHERE guest_id = ? AND id <> ? ORDER BY start_date DESC',
    [$b['guest_id'], $id]
);
$next = Booking::TRANSITIONS[$b['status']];
$actionLabels = ['confirmed' => 'Confirm', 'cancelled' => 'Cancel', 'checked_in' => 'Check in', 'completed' => 'Mark completed', 'no_show' => 'No-show'];
$emailsGuest = ['confirmed', 'cancelled'];

$waText = $b['language'] === 'id'
    ? "Halo {$b['name']}, ini Ayurvedic Wellness House tentang booking {$b['code']} ({$b['service_name']})."
    : "Hello {$b['name']}, this is Ayurvedic Wellness House about your booking {$b['code']} ({$b['service_name']}).";

admin_header($b['code'], $user);
?>
<p class="eyebrow"><a href="/admin/bookings.php" style="color:inherit;text-decoration:none">‹ Bookings</a></p>
<h1 style="margin-top:.3rem"><?= avatar($b['name']) ?><?= e($b['name']) ?> <span class="ref" style="font-size:1rem"><?= e($b['code']) ?></span> <?= status_pill($b['status']) ?></h1>

<div class="grid2">
  <section class="card">
    <h2><?= e($b['service_name']) ?></h2>
    <table class="kv">
      <tr><th>When</th><td><?= e(Booking::whenText($b)) ?><?= $b['service_type'] === 'program' ? ' · ' . (int) $b['duration_days'] . ' days' : ' · ' . (int) $b['duration_minutes'] . ' min' ?></td></tr>
      <tr><th>Guests</th><td><?= (int) $b['guests_count'] ?></td></tr>
      <tr><th>Price at booking</th><td><?= e(idr($b['price_idr_snapshot'] === null ? null : (int) $b['price_idr_snapshot'])) ?><?= $b['offer_percent'] ? ' <span class="pill warn">' . (int) $b['offer_percent'] . '% offer</span>' : '' ?></td></tr>
      <tr><th>Staying at</th><td><?= e($b['staying_at'] ?: '–') ?></td></tr>
      <tr><th>Source</th><td><?= e($b['source']) ?>, <?= e(date('j M Y H:i', strtotime($b['created_at']))) ?></td></tr>
    </table>
    <?php if ($b['message']): ?><p><b>Message</b><br><?= nl2br(e($b['message'])) ?></p><?php endif; ?>
  </section>

  <section class="card">
    <h2><?= e($b['name']) ?></h2>
    <table class="kv">
      <tr><th>WhatsApp</th><td><?= e($b['whatsapp']) ?></td></tr>
      <tr><th>Email</th><td><?= e($b['email'] ?: '–') ?></td></tr>
      <tr><th>Language</th><td><?= $b['language'] === 'id' ? 'Bahasa Indonesia' : 'English' ?></td></tr>
    </table>
    <p><a class="btn" href="<?= e(wa_link($b['whatsapp'], $waText)) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 16) ?>Open WhatsApp chat</a></p>
    <?php if ($others): ?>
      <p class="muted">Other bookings:
        <?php foreach ($others as $o): ?>
          <a href="/admin/booking.php?id=<?= (int) $o['id'] ?>"><?= e($o['code']) ?></a> (<?= e(Booking::STATUS_LABELS[$o['status']]) ?>)
        <?php endforeach; ?>
      </p>
    <?php endif; ?>
  </section>
</div>

<?php if ($next): ?>
<section class="card">
  <h2>Actions</h2>
  <div class="actions">
    <?php foreach ($next as $to): ?>
    <form method="post" class="action"<?= $to === 'cancelled' ? ' onsubmit="return confirm(\'Cancel this booking?\')"' : '' ?>>
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="to" value="<?= $to ?>">
      <?php if (in_array($to, $emailsGuest, true)): ?>
        <label class="check"><input type="checkbox" name="notify" <?= $b['email'] ? '' : 'disabled' ?>> Send standard email<?= $b['email'] ? '' : ' (no email)' ?></label>
      <?php endif; ?>
      <button class="<?= $to === 'cancelled' || $to === 'no_show' ? 'danger' : ($to === 'confirmed' ? '' : 'ghost') ?>"><?= $actionLabels[$to] ?></button>
    </form>
    <?php endforeach; ?>
  </div>

  <?php if (in_array($b['status'], ['requested', 'confirmed'], true)): ?>
  <form method="post" class="row" style="margin-top:1rem">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="reschedule">
    <label>New date<input type="date" name="date" value="<?= e($b['start_date']) ?>" required></label>
    <?php if ($b['service_type'] !== 'program'): ?>
      <label>New time<input type="time" name="time" value="<?= e(substr((string) $b['start_time'], 0, 5)) ?>" required></label>
    <?php endif; ?>
    <label class="check"><input type="checkbox" name="notify" <?= $b['email'] ? '' : 'disabled' ?>> Send standard email</label>
    <label class="check"><input type="checkbox" name="force"> Move anyway if full</label>
    <button>Move booking</button>
  </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<div class="grid2">
  <section class="card">
    <h2>Health check</h2>
    <?php if (!$health): ?>
      <p class="muted">No answers stored.</p>
    <?php else: ?>
      <p>Pregnant: <b class="<?= in_array($health['pregnant'], ['yes', 'possibly'], true) ? 'err' : '' ?>"><?= e(Health::PREGNANT_OPTIONS[$health['pregnant']] ?? $health['pregnant']) ?></b></p>
      <?php if ($health['conditions']): ?>
        <ul class="flags"><?php foreach ($health['conditions'] as $c): ?><li><?= e(Health::CONDITIONS[$c] ?? $c) ?></li><?php endforeach; ?></ul>
      <?php else: ?>
        <p class="muted">No conditions ticked.</p>
      <?php endif; ?>
      <?php if ($health['other'] !== ''): ?><p><b>Notes</b><br><?= nl2br(e($health['other'])) ?></p><?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>History</h2>
    <form method="post" class="inline" style="margin-bottom:1rem">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="note">
      <input name="note" placeholder="Internal note (guest never sees this)" style="flex:1">
      <button>Add</button>
    </form>
    <ul class="log">
      <?php foreach ($events as $ev): ?>
        <li><small class="muted"><?= e(date('j M H:i', strtotime($ev['created_at']))) ?> · <?= e($ev['user_name'] ?? 'Website') ?></small><br>
          <b><?= e($ev['action']) ?></b> <?= e($ev['detail']) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<section class="card">
  <h2>Reply to guest by email</h2>
  <?php if (!$b['email']): ?>
    <p class="muted">This guest did not give an email address. Use the WhatsApp button above.</p>
  <?php else: ?>
  <form method="post">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="email">
    <p class="muted">To: <?= e($b['email']) ?>. Write anything you like. The booking reference is added at the bottom.</p>
    <label>Subject<input name="subject" maxlength="200" required value="<?= e(($b['language'] === 'id' ? 'Booking Anda ' : 'Your booking ') . $b['code'] . ' – Ayurvedic Wellness House') ?>"></label>
    <label>Message<textarea name="message" rows="8" required placeholder="<?= e(($b['language'] === 'id' ? 'Halo ' : 'Hello ') . $b['name'] . ',') ?>"></textarea></label>
    <button>Send email</button>
  </form>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Emails</h2>
  <?php if (!$emails): ?><p class="muted">None.</p><?php else: ?>
  <table>
    <tr><th>Subject</th><th>To</th><th>Status</th></tr>
    <?php foreach ($emails as $m): ?>
    <tr>
      <td><a href="/admin/emails.php?view=<?= (int) $m['id'] ?>"><?= e($m['subject']) ?></a></td>
      <td><?= e($m['to_email']) ?></td>
      <td><?= e($m['status']) ?><?= $m['last_error'] ? ' <small class="err">' . e($m['last_error']) . '</small>' : '' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</section>
<?php admin_footer();
