<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$user = Auth::require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $role = trim((string) ($_POST['role'] ?? '')) ?: null;
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '') {
        flash('Name is required.', 'err');
    } elseif ($id) {
        Db::run('UPDATE practitioners SET name = ?, role = ?, active = ? WHERE id = ?', [$name, $role, $active, $id]);
        flash('Saved.');
    } else {
        Db::run('INSERT INTO practitioners (name, role, active) VALUES (?, ?, 1)', [$name, $role]);
        flash('Practitioner added.');
    }
    redirect('/admin/practitioners.php');
}

$list = Db::all('SELECT * FROM practitioners ORDER BY active DESC, name');

admin_header('Practitioners', $user);
?>
<h1>Practitioners</h1>

<form method="post" class="card row">
  <?= Csrf::field() ?>
  <label>Name<input name="name" required></label>
  <label>Role<input name="role" placeholder="e.g. Ayurvedic doctor"></label>
  <button>Add</button>
</form>

<section class="card">
<?php if (!$list): ?>
  <p class="muted">No practitioners yet.</p>
<?php else: ?>
  <table>
    <tr><th>Name</th><th>Role</th><th>Active</th><th></th></tr>
    <?php foreach ($list as $p): ?>
    <?php $f = 'p' . (int) $p['id']; ?>
    <tr>
      <td><input form="<?= $f ?>" name="name" value="<?= e($p['name']) ?>" required></td>
      <td><input form="<?= $f ?>" name="role" value="<?= e($p['role']) ?>"></td>
      <td><input form="<?= $f ?>" type="checkbox" name="active" <?= $p['active'] ? 'checked' : '' ?>></td>
      <td>
        <form method="post" id="<?= $f ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="link">Save</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
</section>
<?php admin_footer();
