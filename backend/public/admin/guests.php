<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$user = Auth::require();
$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $whatsapp = Booking::normaliseWhatsapp((string) ($_POST['whatsapp'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $language = ($_POST['language'] ?? '') === 'id' ? 'id' : 'en';

    if ($name === '' || $whatsapp === null) {
        flash('Name and a valid WhatsApp number (with country code) are required.', 'err');
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('Email is not valid.', 'err');
    } else {
        Db::run(
            'UPDATE guests SET name=?, whatsapp=?, email=?, country=?, language=?, notes=? WHERE id=?',
            [$name, $whatsapp, $email ?: null, trim((string) ($_POST['country'] ?? '')) ?: null, $language,
             trim((string) ($_POST['notes'] ?? '')) ?: null, $id]
        );
        flash('Guest saved.');
    }
    redirect('/admin/guests.php?id=' . $id);
}

if ($id) {
    $g = Db::one('SELECT * FROM guests WHERE id = ?', [$id]);
    if (!$g) {
        redirect('/admin/guests.php');
    }
    $bookings = Db::all(
        'SELECT b.id, b.code, b.start_date, b.start_time, b.status, s.name AS service_name
         FROM bookings b JOIN services s ON s.id = b.service_id WHERE b.guest_id = ? ORDER BY b.start_date DESC',
        [$id]
    );
    admin_header($g['name'], $user);
    ?>
<h1><?= e($g['name']) ?> <small><a href="/admin/guests.php">All guests</a></small></h1>
<form method="post" class="card">
  <?= Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
  <div class="row">
    <label>Name<input name="name" value="<?= e($g['name']) ?>" required></label>
    <label>WhatsApp<input name="whatsapp" value="<?= e($g['whatsapp']) ?>" required></label>
    <label>Email<input type="email" name="email" value="<?= e($g['email']) ?>"></label>
  </div>
  <div class="row">
    <label>Country<input name="country" value="<?= e($g['country']) ?>"></label>
    <label>Language
      <select name="language"><option value="en"<?= $g['language'] === 'en' ? ' selected' : '' ?>>English</option><option value="id"<?= $g['language'] === 'id' ? ' selected' : '' ?>>Bahasa Indonesia</option></select>
    </label>
  </div>
  <label>Notes<input name="notes" value="<?= e($g['notes']) ?>"></label>
  <button>Save</button> <a class="btn ghost" href="<?= e(wa_link($g['whatsapp'])) ?>" target="_blank" rel="noopener">WhatsApp</a>
</form>
<section class="card">
  <h2>Bookings</h2>
  <?php if (!$bookings): ?><p class="muted">None.</p><?php else: ?>
  <table>
    <?php foreach ($bookings as $b): ?>
    <tr><td><a href="/admin/booking.php?id=<?= (int) $b['id'] ?>"><?= e($b['code']) ?></a></td><td><?= e($b['service_name']) ?></td><td><?= e(Booking::whenText($b)) ?></td><td><?= status_pill($b['status']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</section>
<?php
    admin_footer();
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$params = [];
$where = '';
if ($q !== '') {
    $where = 'WHERE g.name LIKE ? OR g.whatsapp LIKE ? OR g.email LIKE ?';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$guests = Db::all(
    "SELECT g.*, COUNT(b.id) AS n, MAX(b.start_date) AS last_date
     FROM guests g LEFT JOIN bookings b ON b.guest_id = g.id $where
     GROUP BY g.id ORDER BY last_date DESC, g.name LIMIT 300",
    $params
);

admin_header('Guests', $user);
?>
<h1>Guests</h1>
<form class="card row" method="get">
  <label>Search<input name="q" value="<?= e($q) ?>" placeholder="Name, WhatsApp or email"></label>
  <button>Search</button>
</form>
<section class="card">
<?php if (!$guests): ?><p class="muted">No guests yet. Guests are added when someone books.</p><?php else: ?>
  <table>
    <tr><th>Name</th><th>WhatsApp</th><th>Email</th><th>Bookings</th><th>Latest date</th></tr>
    <?php foreach ($guests as $g): ?>
    <tr>
      <td><a href="?id=<?= (int) $g['id'] ?>"><?= e($g['name']) ?></a></td>
      <td><?= e($g['whatsapp']) ?></td>
      <td><?= e($g['email'] ?: '–') ?></td>
      <td><?= (int) $g['n'] ?></td>
      <td><?= $g['last_date'] ? e(date('j M Y', strtotime($g['last_date']))) : '–' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
</section>
<?php admin_footer();
