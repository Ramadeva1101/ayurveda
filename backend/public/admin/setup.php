<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

// Creates the first developer account. Works only while config 'setup_token' is set
// AND the users table is empty. Afterwards it always answers 404.

$token = (string) config('setup_token');
$hasUsers = (int) Db::value('SELECT COUNT(*) FROM users') > 0;
if ($token === '' || $hasUsers) {
    http_response_code(404);
    exit('Not found');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!hash_equals($token, (string) ($_POST['token'] ?? ''))) {
        $error = 'Setup token is wrong.';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a name and a valid email.';
    } else {
        $error = Auth::validatePassword($password);
    }

    if ($error === null) {
        Db::run(
            "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'developer')",
            [$name, $email, password_hash($password, PASSWORD_DEFAULT)]
        );
        flash("Developer account created. Now set 'setup_token' back to '' in config.php.");
        redirect('/admin/login.php');
    }
}

admin_header('Setup', null);
?>
<form method="post" class="card narrow">
  <h1>Create developer account</h1>
  <?= Csrf::field() ?>
  <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <label>Setup token<input type="password" name="token" required></label>
  <label>Name<input name="name" required></label>
  <label>Email<input type="email" name="email" required></label>
  <label>Password (min. 12 characters)<input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
  <button>Create</button>
</form>
<?php admin_footer();
