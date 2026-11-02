<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

if (Auth::user()) {
    redirect('/admin/');
}

$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $email = (string) ($_POST['email'] ?? '');
    $error = Auth::attempt($email, (string) ($_POST['password'] ?? ''));
    if ($error === null) {
        redirect('/admin/');
    }
}

admin_header('Log in', null);
?>
<form method="post" class="card narrow">
  <h1>Clinic admin</h1>
  <?= Csrf::field() ?>
  <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username"></label>
  <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
  <button>Log in</button>
</form>
<?php admin_footer();
