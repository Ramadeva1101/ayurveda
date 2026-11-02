<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$user = Auth::require();
$types = ['experience' => 'Experience', 'treatment' => 'Treatment', 'program' => 'Program'];

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'service';
}

function int_or_null($v): ?int
{
    $v = trim((string) $v);
    return $v === '' ? null : (int) $v;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $type = (string) ($_POST['type'] ?? '');
    $minutes = int_or_null($_POST['duration_minutes'] ?? '');
    $daysN = int_or_null($_POST['duration_days'] ?? '');
    $image = trim((string) ($_POST['image'] ?? '')) ?: null;
    $price = int_or_null(str_replace(['.', ',', ' '], '', (string) ($_POST['price_idr'] ?? '')));

    if ($name === '' || !isset($types[$type])) {
        flash('Name and type are required.', 'err');
    } elseif ($type === 'program' && (!$daysN || $daysN < 1)) {
        flash('A program needs its length in days.', 'err');
    } elseif ($type !== 'program' && (!$minutes || $minutes < 5)) {
        flash('A treatment or experience needs its length in minutes.', 'err');
    } elseif ($image !== null && !preg_match('#^/[^\s"<>]+$#u', str_replace(' ', '_', $image))) {
        flash('Image must be a site path starting with /, e.g. /images/shirodhara.webp', 'err');
    } elseif ($price !== null && $price < 0) {
        flash('Price cannot be negative.', 'err');
    } else {
        $data = [
            $type, $name,
            $type === 'program' ? null : $minutes,
            $type === 'program' ? $daysN : null,
            $price,
            isset($_POST['bookable_online']) ? 1 : 0,
            isset($_POST['active']) ? 1 : 0,
            (int) ($_POST['sort_order'] ?? 0),
            $image,
        ];
        if ($id) {
            Db::run(
                'UPDATE services SET type=?, name=?, duration_minutes=?, duration_days=?, price_idr=?,
                 bookable_online=?, active=?, sort_order=?, image=? WHERE id=?',
                [...$data, $id]
            );
        } else {
            $slug = slugify($name);
            $base = $slug;
            for ($i = 2; Db::value('SELECT 1 FROM services WHERE slug = ?', [$slug]); $i++) {
                $slug = "$base-$i";
            }
            Db::run(
                'INSERT INTO services (type, name, duration_minutes, duration_days, price_idr,
                 bookable_online, active, sort_order, image, slug) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [...$data, $slug]
            );
        }
        flash('Service saved.');
        redirect('/admin/services.php');
    }
    redirect('/admin/services.php' . ($id ? "?edit=$id" : '?new=1'));
}

$services = Db::all('SELECT * FROM services ORDER BY active DESC, type, sort_order, name');
$edit = null;
if (isset($_GET['edit'])) {
    $edit = Db::one('SELECT * FROM services WHERE id = ?', [(int) $_GET['edit']]);
} elseif (isset($_GET['new'])) {
    $edit = ['id' => 0, 'type' => 'treatment', 'name' => '', 'duration_minutes' => null, 'duration_days' => null,
             'price_idr' => null, 'bookable_online' => 0, 'active' => 1, 'sort_order' => 0, 'image' => null];
}

admin_header('Services', $user);
?>
<h1>Services <a class="btn" href="?new=1">+ Add service</a></h1>
<p class="muted">Only services marked "Bookable online" appear on the Book page. Leave price empty to show "to be confirmed".</p>

<?php if ($edit): ?>
<form method="post" class="card">
  <h2><?= $edit['id'] ? 'Edit service' : 'New service' ?></h2>
  <?= Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
  <label>Name<input name="name" value="<?= e($edit['name']) ?>" required></label>
  <div class="row">
    <label>Type
      <select name="type">
        <?php foreach ($types as $k => $v): ?>
          <option value="<?= $k ?>"<?= $edit['type'] === $k ? ' selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Minutes (treatment/experience)<input type="number" name="duration_minutes" min="5" value="<?= e((string) $edit['duration_minutes']) ?>"></label>
    <label>Days (program)<input type="number" name="duration_days" min="1" value="<?= e((string) $edit['duration_days']) ?>"></label>
  </div>
  <div class="row">
    <label>Price (IDR)<input inputmode="numeric" name="price_idr" value="<?= e((string) $edit['price_idr']) ?>" placeholder="to be confirmed"></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int) $edit['sort_order'] ?>"></label>
    <label>Photo (site path)<input name="image" value="<?= e($edit['image']) ?>" placeholder="/images/shirodhara.webp"></label>
  </div>
  <label class="check"><input type="checkbox" name="bookable_online" <?= $edit['bookable_online'] ? 'checked' : '' ?>> Bookable online</label>
  <label class="check"><input type="checkbox" name="active" <?= $edit['active'] ? 'checked' : '' ?>> Active</label>
  <button>Save</button> <a href="/admin/services.php">Cancel</a>
</form>
<?php endif; ?>

<section class="card">
<?php if (!$services): ?>
  <p class="muted">No services yet. Add the clinic's confirmed services and prices.</p>
<?php else: ?>
  <table>
    <tr><th>Name</th><th>Type</th><th>Length</th><th>Price</th><th>Online</th><th></th></tr>
    <?php foreach ($services as $s): ?>
    <tr class="<?= $s['active'] ? '' : 'off' ?>">
      <td><?= e($s['name']) ?></td>
      <td><?= e($types[$s['type']]) ?></td>
      <td><?= $s['type'] === 'program' ? (int) $s['duration_days'] . ' days' : (int) $s['duration_minutes'] . ' min' ?></td>
      <td><?= $s['price_idr'] === null ? '<span class="muted">to be confirmed</span>' : 'IDR ' . number_format((int) $s['price_idr'], 0, ',', '.') ?></td>
      <td><?= $s['bookable_online'] ? 'Yes' : 'No' ?></td>
      <td><a href="?edit=<?= (int) $s['id'] ?>">Edit</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
</section>
<?php admin_footer();
