<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/upload_helper.php';
require_once dirname(__DIR__) . '/layout.php';

$pdo      = getPDO();
$uploadDir = dirname(__DIR__, 2) . '/uploads/products/';
$msg = $err = '';

/* ==================== ACTIONS ==================== */
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// DELETE
if ($action === 'delete' && $id) {
    verifyCsrf();
    $row = $pdo->prepare("SELECT image FROM products WHERE id=?")->execute([$id]) && ($row = $pdo->prepare("SELECT image FROM products WHERE id=?")->execute([$id]));
    $stmt = $pdo->prepare("SELECT image FROM products WHERE id=?");
    $stmt->execute([$id]);
    $prod = $stmt->fetch();
    if ($prod) {
        deleteUpload($uploadDir, $prod['image']);
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
    }
    header('Location: ' . BASE_URL . '/admin/crud/products.php?msg=deleted');
    exit;
}

// SAVE (add/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    verifyCsrf();
    $name      = trim($_POST['name'] ?? '');
    $catId     = (int)($_POST['category_id'] ?? 0);
    $shortDesc = trim($_POST['short_desc'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $isNew     = isset($_POST['is_new']) ? 1 : 0;
    $isFeatured= isset($_POST['is_featured']) ? 1 : 0;
    $isActive  = isset($_POST['is_active']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    if (!$name || !$catId) {
        $err = 'Product name and category are required.';
    } else {
        $slug  = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $name), '-'));
        $image = null;

        // Image upload
        if (!empty($_FILES['image']['name'])) {
            $upload = uploadImage($_FILES['image'], $uploadDir);
            if (!$upload['success']) {
                $err = $upload['error'];
            } else {
                $image = $upload['filename'];
            }
        }

        if (!$err) {
            if ($action === 'add') {
                $s = $pdo->prepare(
                    "INSERT INTO products (category_id,name,slug,short_desc,description,image,is_new,is_featured,sort_order,is_active)
                     VALUES (?,?,?,?,?,?,?,?,?,?)"
                );
                $s->execute([$catId,$name,$slug,$shortDesc,$desc,$image,$isNew,$isFeatured,$sortOrder,$isActive]);
            } else {
                // If new image uploaded, delete old one
                if ($image) {
                    $old = $pdo->prepare("SELECT image FROM products WHERE id=?");
                    $old->execute([$id]);
                    deleteUpload($uploadDir, ($old->fetchColumn() ?: null));
                }
                $s = $pdo->prepare(
                    "UPDATE products SET category_id=?,name=?,slug=?,short_desc=?,description=?,
                     is_new=?,is_featured=?,sort_order=?,is_active=?" .
                    ($image ? ",image=?" : "") .
                    " WHERE id=?"
                );
                $params = [$catId,$name,$slug,$shortDesc,$desc,$isNew,$isFeatured,$sortOrder,$isActive];
                if ($image) $params[] = $image;
                $params[] = $id;
                $s->execute($params);
            }
            header('Location: ' . BASE_URL . '/admin/crud/products.php?msg=saved');
            exit;
        }
    }
}

/* ==================== FETCH DATA ==================== */
$cats = $pdo->query("SELECT id,name FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();
$editing = null;
if ($action === 'edit' && $id) {
    $stmtE = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmtE->execute([$id]);
    $editing = $stmtE->fetch();
    if (!$editing) { header('Location: ' . BASE_URL . '/admin/crud/products.php'); exit; }
}

if (isset($_GET['msg'])) $msg = $_GET['msg'] === 'saved' ? 'Product saved successfully.' : 'Product deleted.';

/* ==================== LAYOUT ==================== */
adminOpen('Products', 'products');
?>

<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<?php if (in_array($action, ['add','edit'])): ?>
<!-- ===== FORM ===== -->
<div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;max-width:800px;">
  <h2 style="font-family:var(--ff-heading);font-size:1.4rem;margin-bottom:1.5rem;">
    <?= $action === 'add' ? 'Add New Product' : 'Edit Product' ?>
  </h2>
  <form method="POST" enctype="multipart/form-data" novalidate>
    <?= csrfField() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
      <div class="form-group" style="grid-column:1/-1;">
        <label class="form-label">Product Name *</label>
        <input class="form-control" name="name" required value="<?= e($editing['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Category *</label>
        <select class="form-control" name="category_id" required>
          <option value="">— Select Category —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($editing['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Sort Order</label>
        <input class="form-control" type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>">
      </div>
      <div class="form-group" style="grid-column:1/-1;">
        <label class="form-label">Short Description</label>
        <input class="form-control" name="short_desc" maxlength="300" value="<?= e($editing['short_desc'] ?? '') ?>">
      </div>
      <div class="form-group" style="grid-column:1/-1;">
        <label class="form-label">Full Description</label>
        <textarea class="form-control" name="description" rows="5"><?= e($editing['description'] ?? '') ?></textarea>
      </div>
      <div class="form-group" style="grid-column:1/-1;">
        <label class="form-label">Product Image (JPG/PNG/WebP, max 2MB)</label>
        <?php if (!empty($editing['image'])): ?>
          <div style="margin-bottom:.75rem;">
            <img src="<?= BASE_URL ?>/uploads/products/<?= e($editing['image']) ?>" alt="" style="height:80px;border-radius:4px;">
          </div>
        <?php endif; ?>
        <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
          <input type="checkbox" name="is_new" value="1" <?= !empty($editing['is_new']) ? 'checked' : '' ?>>
          <span class="form-label" style="margin:0;">Mark as New</span>
        </label>
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
          <input type="checkbox" name="is_featured" value="1" <?= ($editing['is_featured'] ?? 1) ? 'checked' : '' ?>>
          <span class="form-label" style="margin:0;">Featured on Homepage</span>
        </label>
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
          <input type="checkbox" name="is_active" value="1" <?= ($editing['is_active'] ?? 1) ? 'checked' : '' ?>>
          <span class="form-label" style="margin:0;">Active / Published</span>
        </label>
      </div>
    </div>
    <div style="display:flex;gap:1rem;margin-top:1rem;">
      <button type="submit" class="btn btn-primary">Save Product</button>
      <a href="<?= BASE_URL ?>/admin/crud/products.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<!-- ===== LIST ===== -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
  <p style="font-family:var(--ff-ui);font-size:.85rem;color:var(--clr-muted);">
    <?= $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() ?> total products
  </p>
  <a href="?action=add" class="btn btn-primary">+ Add Product</a>
</div>

<table class="admin-table">
  <thead>
    <tr><th>Image</th><th>Name</th><th>Category</th><th>Featured</th><th>Status</th><th>Actions</th></tr>
  </thead>
  <tbody>
  <?php
  $prods = $pdo->query(
      "SELECT p.*,c.name AS cat FROM products p JOIN categories c ON c.id=p.category_id ORDER BY p.sort_order,p.id DESC"
  )->fetchAll();
  foreach ($prods as $p):
  ?>
  <tr>
    <td>
      <?php if ($p['image']): ?>
        <img src="<?= BASE_URL ?>/uploads/products/<?= e($p['image']) ?>" alt="" style="height:44px;border-radius:4px;">
      <?php else: ?>
        <div style="width:44px;height:44px;background:var(--clr-offwhite);border-radius:4px;display:flex;align-items:center;justify-content:center;color:var(--clr-muted);font-size:.7rem;">N/A</div>
      <?php endif; ?>
    </td>
    <td>
      <strong><?= e($p['name']) ?></strong>
      <?php if ($p['is_new']): ?><span class="badge badge-gold" style="margin-left:.4rem;">New</span><?php endif; ?>
    </td>
    <td><?= e($p['cat']) ?></td>
    <td><?= $p['is_featured'] ? '<span class="badge badge-success">Yes</span>' : '—' ?></td>
    <td><?= $p['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Hidden</span>' ?></td>
    <td>
      <a href="?action=edit&id=<?= $p['id'] ?>" style="color:var(--clr-dark);font-weight:600;font-size:.82rem;margin-right:.75rem;">Edit</a>
      <form method="POST" action="?action=delete&id=<?= $p['id'] ?>" style="display:inline;" onsubmit="return confirm('Delete this product?')">
        <?= csrfField() ?>
        <button type="submit" style="color:var(--clr-crimson);font-weight:600;font-size:.82rem;">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$prods): ?>
    <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--clr-muted);">No products yet. <a href="?action=add">Add one</a>.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
<?php endif; ?>

<?php adminClose(); ?>
