<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/layout.php';

$pdo    = getPDO();
$msg = $err = '';
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS contact_info (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(100),
    email VARCHAR(150),
    whatsapp VARCHAR(50),
    show_header TINYINT(1) NOT NULL DEFAULT 1,
    show_footer TINYINT(1) NOT NULL DEFAULT 1,
    show_contact_page TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($action === 'delete' && $id) {
    verifyCsrf();
    $pdo->prepare("DELETE FROM contact_info WHERE id=?")->execute([$id]);
    header('Location: ' . BASE_URL . '/admin/crud/contact_info.php?msg=deleted'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    verifyCsrf();
    $label     = trim($_POST['label'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $whatsapp  = trim($_POST['whatsapp'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $showH     = isset($_POST['show_header']) ? 1 : 0;
    $showF     = isset($_POST['show_footer']) ? 1 : 0;
    $showC     = isset($_POST['show_contact_page']) ? 1 : 0;
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if (!$label) { $err = 'Label is required.'; }
    else {
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO contact_info (label,address,phone,email,whatsapp,show_header,show_footer,show_contact_page,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$label,$address,$phone,$email,$whatsapp,$showH,$showF,$showC,$sortOrder,$isActive]);
        } else {
            $pdo->prepare("UPDATE contact_info SET label=?,address=?,phone=?,email=?,whatsapp=?,show_header=?,show_footer=?,show_contact_page=?,sort_order=?,is_active=? WHERE id=?")
                ->execute([$label,$address,$phone,$email,$whatsapp,$showH,$showF,$showC,$sortOrder,$isActive,$id]);
        }
        header('Location: ' . BASE_URL . '/admin/crud/contact_info.php?msg=saved'); exit;
    }
}

$editing = null;
if ($action === 'edit' && $id) {
    $s = $pdo->prepare("SELECT * FROM contact_info WHERE id=?"); $s->execute([$id]);
    $editing = $s->fetch();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'] === 'saved' ? 'Contact info saved.' : 'Entry deleted.';

adminOpen('Contact Information', 'contact_info');
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<p style="font-family:var(--ff-ui);font-size:.82rem;color:var(--clr-muted);margin-bottom:1.5rem;">
  Add multiple office/department contact entries. Control where each one appears: Header, Footer, or Contact Page.
</p>

<?php if (in_array($action,['add','edit'])): ?>
<div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;max-width:640px;">
  <h2 style="font-family:var(--ff-heading);font-size:1.4rem;margin-bottom:1.5rem;">
    <?= $action==='add' ? 'Add Contact Entry' : 'Edit Contact Entry' ?>
  </h2>
  <form method="POST" novalidate>
    <?= csrfField() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
      <div class="form-group" style="grid-column:1/-1;">
        <label class="form-label">Label / Office Name *</label>
        <input class="form-control" name="label" required placeholder="e.g. Head Office, Sales Dept."
               value="<?= e($editing['label'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Phone</label>
        <input class="form-control" name="phone" placeholder="+880 01XXXXXXXXX"
               value="<?= e($editing['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">WhatsApp</label>
        <input class="form-control" name="whatsapp" placeholder="+880 01XXXXXXXXX"
               value="<?= e($editing['whatsapp'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email"
               value="<?= e($editing['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Sort Order</label>
        <input class="form-control" type="number" name="sort_order"
               value="<?= (int)($editing['sort_order'] ?? 0) ?>">
      </div>
      <div class="form-group" style="grid-column:1/-1;">
        <label class="form-label">Address</label>
        <textarea class="form-control" name="address" rows="2"
                  placeholder="Full address"><?= e($editing['address'] ?? '') ?></textarea>
      </div>
      <!-- Visibility toggles -->
      <div class="form-group" style="grid-column:1/-1;">
        <p class="form-label" style="margin-bottom:.75rem;">Show In</p>
        <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
          <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-family:var(--ff-ui);font-size:.85rem;">
            <input type="checkbox" name="show_header" value="1" <?= ($editing['show_header'] ?? 1) ? 'checked':'' ?>>
            Header (top bar)
          </label>
          <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-family:var(--ff-ui);font-size:.85rem;">
            <input type="checkbox" name="show_footer" value="1" <?= ($editing['show_footer'] ?? 1) ? 'checked':'' ?>>
            Footer
          </label>
          <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-family:var(--ff-ui);font-size:.85rem;">
            <input type="checkbox" name="show_contact_page" value="1" <?= ($editing['show_contact_page'] ?? 1) ? 'checked':'' ?>>
            Contact Page
          </label>
          <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-family:var(--ff-ui);font-size:.85rem;">
            <input type="checkbox" name="is_active" value="1" <?= ($editing['is_active'] ?? 1) ? 'checked':'' ?>>
            Active
          </label>
        </div>
      </div>
    </div>
    <div style="display:flex;gap:1rem;margin-top:.5rem;">
      <button type="submit" class="btn btn-primary">Save</button>
      <a href="<?= BASE_URL ?>/admin/crud/contact_info.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1.25rem;">
  <a href="?action=add" class="btn btn-primary">+ Add Entry</a>
</div>
<table class="admin-table">
  <thead>
    <tr><th>Label</th><th>Phone</th><th>Email</th><th>Header</th><th>Footer</th><th>Contact Page</th><th>Status</th><th>Actions</th></tr>
  </thead>
  <tbody>
  <?php foreach ($pdo->query("SELECT * FROM contact_info ORDER BY sort_order,id")->fetchAll() as $ci): ?>
  <tr>
    <td><strong><?= e($ci['label']) ?></strong><br>
      <span style="font-size:.75rem;color:var(--clr-muted);"><?= e($ci['address']) ?></span>
    </td>
    <td><?= e($ci['phone']) ?></td>
    <td><?= e($ci['email']) ?></td>
    <td><?= $ci['show_header'] ? '✅':'—' ?></td>
    <td><?= $ci['show_footer'] ? '✅':'—' ?></td>
    <td><?= $ci['show_contact_page'] ? '✅':'—' ?></td>
    <td><?= $ci['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Off</span>' ?></td>
    <td>
      <a href="?action=edit&id=<?= $ci['id'] ?>" style="color:var(--clr-dark);font-weight:600;font-size:.82rem;margin-right:.6rem;">Edit</a>
      <form method="POST" action="?action=delete&id=<?= $ci['id'] ?>" style="display:inline;" onsubmit="return confirm('Delete?')">
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
