<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$user = Auth::require('developer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? '') === 'developer' ? 'developer' : 'admin';
        $err = ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
            ? 'Enter a name and a valid email.'
            : Auth::validatePassword($password);
        if (!$err && Db::value('SELECT 1 FROM users WHERE email = ?', [$email])) {
            $err = 'That email already has an account.';
        }
        if ($err) {
            flash($err, 'err');
        } else {
            Db::run(
                'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
                [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]
            );
            flash('Account created. Give the password to the person directly, not by email.');
        }
    }

    if ($action === 'toggle' && $id && $id !== (int) $user['id']) {
        Db::run('UPDATE users SET active = 1 - active WHERE id = ?', [$id]);
        flash('Account updated.');
    }

    if ($action === 'password' && $id) {
        $password = (string) ($_POST['password'] ?? '');
        $err = Auth::validatePassword($password);
        if ($err) {
            flash($err, 'err');
        } else {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            flash('Password changed.');
        }
    }

    redirect('/admin/users.php');
}

$users = Db::all('SELECT id, name, email, role, active, last_login_at FROM users ORDER BY active DESC, name');

admin_header('Users', $user);
?>
<h1>Users</h1>

<form method="post" class="card">
  <h2>Add account</h2>
  <?= Csrf::field() ?>
  <input type="hidden" name="action" value="add">
  <div class="row">
    <label>Name<input name="name" required></label>
    <label>Email<input type="email" name="email" required></label>
    <label>Password (min. 12)<input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
    <label>Role
      <select name="role"><option value="admin">Admin (clinic)</option><option value="developer">Developer</option></select>
    </label>
  </div>
  <button>Add</button>
</form>

<section class="card">
  <table>
    <tr><th>Name</th><th>Email</th><th>Role</th><th>Last login</th><th></th><th>New password</th></tr>
    <?php foreach ($users as $u): ?>
    <tr class="<?= $u['active'] ? '' : 'off' ?>">
      <td><?= e($u['name']) ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e($u['role']) ?></td>
      <td><?= $u['last_login_at'] ? e(date('j M Y H:i', strtotime($u['last_login_at']))) : '–' ?></td>
      <td>
        <?php if ((int) $u['id'] !== (int) $user['id']): ?>
        <form method="post"><?= Csrf::field() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <button class="link"><?= $u['active'] ? 'Disable' : 'Enable' ?></button>
        </form>
        <?php endif; ?>
      </td>
      <td>
        <form method="post" class="inline"><?= Csrf::field() ?>
          <input type="hidden" name="action" value="password">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <input type="password" name="password" minlength="12" required autocomplete="new-password">
          <button class="link">Set</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php admin_footer();
