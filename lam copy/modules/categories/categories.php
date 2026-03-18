<?php
// ============================================================
// modules/categories/categories.php
// ============================================================

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['cat_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    if (!$name) { flash('error', 'Name is required.'); redirect('categories'); }
    if ($id) {
        dbUpdate('categories', ['name' => $name], 'id = ?', [$id]);
        logAction('UPDATE', 'categories', $id, "Renamed category from '{$_POST['old_name']}' to '$name'");
        flash('success', 'Category updated.');
    } else {
        $newId = dbInsert('categories', ['name' => $name, 'created_at' => now()]);
        logAction('CREATE', 'categories', $newId, "Created category: $name");
        flash('success', 'Category added.');
    }
    redirect('categories');
}

if ($action === 'delete' && canDelete()) {
    $id = (int)$_GET['id'];
    dbDelete('categories', 'id = ?', [$id]);
    logAction('DELETE', 'categories', $id, "Deleted category: '{$_GET['name']}'");
    flash('success', 'Category deleted.');
    redirect('categories');
}

$categories = dbFetchAll(
    "SELECT c.*, COUNT(p.id) AS product_count FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id ORDER BY c.name"
);
$editing = !empty($_GET['edit']) ? dbFetch('SELECT * FROM categories WHERE id = ?', [(int)$_GET['edit']]) : null;

$pageTitle = 'Categories';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-2">
  <h1>🏷️ Categories</h1>
  <button class="btn btn-primary" onclick="openModal('catModal')">+ Add Category</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th class="text-right">Products</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($categories as $c): ?>
        <tr>
          <td><strong><?= e($c['name']) ?></strong></td>
          <td class="text-right"><?= $c['product_count'] ?></td>
          <td>
            <a href="index.php?page=categories&edit=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">✏️</a>
            <?php if (canDelete()): ?>
            <a href="index.php?page=categories&action=delete&id=<?= $c['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete category?">🗑️</a>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-backdrop <?= $editing ? 'open' : '' ?>" id="catModal">
  <div class="modal" style="max-width:380px">
    <div class="modal-header">
      <span class="modal-title"><?= $editing ? 'Edit Category' : 'Add Category' ?></span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="catForm">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="cat_id" value="<?= $editing['id'] ?? '' ?>">
        <div class="form-group">
          <label class="form-label">Category Name *</label>
          <input type="text" name="name" class="form-control" required autofocus value="<?= e($editing['name'] ?? '') ?>">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button type="submit" form="catForm" class="btn btn-primary">Save</button>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
