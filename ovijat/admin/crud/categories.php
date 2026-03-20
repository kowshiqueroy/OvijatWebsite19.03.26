<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/layout.php';

$pdo = getPDO();
$msg = $err = '';
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// DELETE
if ($action === 'delete' && $id) {
    verifyCsrf();
    $count = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id=?")->execute([$id]) ? $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id=?")->execute([$id]) : 0;
    $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
    $chk->execute([$id]);
    if ((int)$chk->fetchColumn() > 0) {
        $msg = 'Cannot delete: category has products assigned to it.';
    } else {
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        header('Location: ' . BASE_URL . '/admin/crud/categories.php?msg=deleted'); exit;
    }
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    verifyCsrf();
    $name      = trim($_POST['name'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if (!$name) { $err = 'Category name is required.'; }
    else {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $name), '-'));
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO categories (name,slug,description,sort_order,is_active) VALUES (?,?,?,?,?)")
                ->execute([$name,$slug,$desc,$sortOrder,$isActive]);
        } else {
            $pdo->prepare("UPDATE categories SET name=?,slug=?,description=?,sort_order=?,is_active=? WHERE id=?")
                ->execute([$name,$slug,$desc,$sortOrder,$isActive,$id]);
        }
        header('Location: ' . BASE_URL . '/admin/crud/categories.php?msg=saved'); exit;
    }
}

$editing = null;
if ($action === 'edit' && $id) {
    $s = $pdo->prepare("SELECT * FROM categories WHERE id=?"); $s->execute([$id]);
    $editing = $s->fetch();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'] === 'saved' ? 'Category saved.' : 'Category deleted.';

adminOpen('Categories', 'categories');
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<?php if (in_array($action,['add','edit'])): ?>
<div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;max-width:600px;">
  <h2 style="font-family:var(--ff-heading);font-size:1.4rem;margin-bottom:1.5rem;">
    <?= $action === 'add' ? 'Add Category' : 'Edit Category' ?>
  </h2>
  <form method="POST" novalidate>
    <?= csrfField() ?>
    <div class="form-group">
      <label class="form-label">Category Name *</label>
      <input class="form-control" name="name" required value="<?= e($editing['name'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Description</label>
      <textarea class="form-control" name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Sort Order</label>
      <input class="form-control" type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>">
    </div>
    <label style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.5rem;cursor:pointer;">
      <input type="checkbox" name="is_active" value="1" <?= ($editing['is_active'] ?? 1) ? 'checked' : '' ?>>
      <span class="form-label" style="margin:0;">Active</span>
    </label>
    <div style="display:flex;gap:1rem;">
      <button type="submit" class="btn btn-primary">Save</button>
      <a href="<?= BASE_URL ?>/admin/crud/categories.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1.25rem;">
  <a href="?action=add" class="btn btn-primary">+ Add Category</a>
</div>
<table class="admin-table">
  <thead><tr><th>Name</th><th>Slug</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php
  $rows = $pdo->query(
    "SELECT c.*,(SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) AS prod_count FROM categories c ORDER BY c.sort_order"
  )->fetchAll();
  foreach ($rows as $r):
  ?>
  <tr>
    <td><strong><?= e($r['name']) ?></strong></td>
    <td style="color:var(--clr-muted);font-size:.82rem;"><?= e($r['slug']) ?></td>
    <td><?= (int)$r['prod_count'] ?></td>
    <td><?= $r['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Hidden</span>' ?></td>
    <td>
      <a href="?action=edit&id=<?= $r['id'] ?>" style="color:var(--clr-dark);font-weight:600;font-size:.82rem;margin-right:.75rem;">Edit</a>
      <form method="POST" action="?action=delete&id=<?= $r['id'] ?>" style="display:inline;" onsubmit="return confirm('Delete category?')">
        <?= csrfField() ?>
        <button type="submit" style="color:var(--clr-crimson);font-weight:600;font-size:.82rem;">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php adminClose(); ?>
