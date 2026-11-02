<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$user = Auth::require();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test') {
    Csrf::check();
    // Developer tool: checks the SMTP settings in config.php end to end.
    if ($user['role'] !== 'developer') {
        http_response_code(403);
        exit('Only a developer account can send test emails.');
    }
    $to = trim((string) ($_POST['to'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('Enter a valid address for the test email.', 'err');
    } else {
        Mailer::queue(null, 'test', $to, 'Test email from Wellness House admin',
            '<p>This is a test email from the Ayurvedic Wellness House booking system.</p><p>If you can read this, sending works.</p>'
            . '<p style="color:#888">Sent ' . date('j M Y H:i') . ' (Bali time), mail driver: ' . e((string) (config('mail')['driver'] ?? '')) . '</p>');
        $r = Mailer::processQueue(5);
        $last = Db::one("SELECT status, last_error FROM email_queue WHERE template = 'test' ORDER BY id DESC LIMIT 1");
        flash($last && $last['status'] === 'sent'
            ? "Test email sent to $to. Check that inbox (and spam)."
            : 'Test email failed: ' . ($last['last_error'] ?? 'unknown error'), $last && $last['status'] === 'sent' ? 'ok' : 'err');
    }
    redirect('/admin/emails.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $id = (int) ($_POST['id'] ?? 0);
    Db::run("UPDATE email_queue SET status = 'pending', attempts = 0 WHERE id = ? AND status = 'failed'", [$id]);
    $r = Mailer::processQueue(5);
    flash($r['failed'] ? 'Still failing. Check the error and the mail settings.' : 'Sent.', $r['failed'] ? 'err' : 'ok');
    redirect('/admin/emails.php');
}

if (isset($_GET['view'])) {
    $m = Db::one('SELECT q.*, b.code FROM email_queue q LEFT JOIN bookings b ON b.id = q.booking_id WHERE q.id = ?', [(int) $_GET['view']]);
    if ($m) {
        admin_header('Email', $user);
        ?>
<h1>Email <small><a href="/admin/emails.php">All emails</a></small></h1>
<section class="card">
  <table>
    <tr><th>To</th><td><?= e($m['to_email']) ?></td></tr>
    <tr><th>Subject</th><td><?= e($m['subject']) ?></td></tr>
    <tr><th>Status</th><td><?= e($m['status']) ?><?= $m['sent_at'] ? ' · ' . e(date('j M Y H:i', strtotime($m['sent_at']))) : '' ?></td></tr>
    <?php if ($m['code']): ?><tr><th>Booking</th><td><a href="/admin/booking.php?id=<?= (int) $m['booking_id'] ?>"><?= e($m['code']) ?></a></td></tr><?php endif; ?>
    <?php if ($m['last_error']): ?><tr><th>Error</th><td class="err"><?= e($m['last_error']) ?></td></tr><?php endif; ?>
  </table>
  <iframe class="mail" sandbox srcdoc="<?= e($m['body_html']) ?>"></iframe>
</section>
<?php
        admin_footer();
        exit;
    }
}

$rows = Db::all(
    'SELECT q.id, q.template, q.to_email, q.subject, q.status, q.attempts, q.last_error, q.sent_at, q.send_after, b.code, q.booking_id
     FROM email_queue q LEFT JOIN bookings b ON b.id = q.booking_id ORDER BY q.id DESC LIMIT 200'
);

admin_header('Emails', $user);
?>
<h1>Emails</h1>
<?php if ($user['role'] === 'developer'): $mail = config('mail') ?? []; ?>
<form method="post" class="card">
  <h2>Send a test email <span class="count"><?= e((string) ($mail['driver'] ?? '')) ?><?= ($mail['driver'] ?? '') === 'smtp' ? ' · ' . e((string) ($mail['smtp_host'] ?? '')) . ':' . (int) ($mail['smtp_port'] ?? 0) : '' ?></span></h2>
  <?= Csrf::field() ?>
  <input type="hidden" name="action" value="test">
  <div class="row">
    <label>Send to<input type="email" name="to" required placeholder="you@example.com"></label>
    <button>Send test</button>
  </div>
  <p class="muted">Developer only. Uses the mail settings in awh-app/config.php.</p>
</form>
<?php endif; ?>
<section class="card">
<?php if (!$rows): ?><p class="muted">No emails yet.</p><?php else: ?>
  <table>
    <tr><th>Subject</th><th>To</th><th>Booking</th><th>Status</th><th>Time</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="?view=<?= (int) $r['id'] ?>"><?= e($r['subject']) ?></a></td>
      <td><?= e($r['to_email']) ?></td>
      <td><?= $r['code'] ? '<a href="/admin/booking.php?id=' . (int) $r['booking_id'] . '">' . e($r['code']) . '</a>' : '–' ?></td>
      <td><span class="pill<?= $r['status'] === 'sent' ? ' ok' : ($r['status'] === 'failed' ? ' bad' : '') ?>"><?= e($r['status']) ?></span>
        <?php if ($r['last_error']): ?><br><small class="err"><?= e($r['last_error']) ?></small><?php endif; ?></td>
      <td><small class="muted"><?= e(date('j M H:i', strtotime($r['sent_at'] ?? $r['send_after']))) ?></small></td>
      <td><?php if ($r['status'] === 'failed'): ?>
        <form method="post"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="link">Retry</button></form>
      <?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
</section>
<?php admin_footer();
