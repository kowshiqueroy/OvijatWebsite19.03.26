<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/layout.php';

$pdo    = getPDO();
$msg = $err = '';
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    verifyCsrf();
    $pdo->prepare("DELETE FROM taglines WHERE id=?")->execute([$id]);
    header('Location: ' . BASE_URL . '/admin/crud/taglines.php?msg=deleted'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action,['add','edit'])) {
    verifyCsrf();
    $tagline   = trim($_POST['tagline'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if (!$tagline) { $err = 'Tagline text is required.'; }
    else {
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO taglines (tagline,sort_order,is_active) VALUES (?,?,?)")
                ->execute([$tagline,$sortOrder,$isActive]);
        } else {
            $pdo->prepare("UPDATE taglines SET tagline=?,sort_order=?,is_active=? WHERE id=?")
                ->execute([$tagline,$sortOrder,$isActive,$id]);
        }
        header('Location: ' . BASE_URL . '/admin/crud/taglines.php?msg=saved'); exit;
    }
}

$editing = null;
if ($action === 'edit' && $id) {
    $s = $pdo->prepare("SELECT * FROM taglines WHERE id=?"); $s->execute([$id]);
    $editing = $s->fetch();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'] === 'saved' ? 'Tagline saved.' : 'Tagline deleted.';

adminOpen('Loading Screen Taglines', 'taglines');
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<?php if (in_array($action,['add','edit'])): ?>
<div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;max-width:560px;">
  <h2 style="font-family:var(--ff-heading);font-size:1.4rem;margin-bottom:1.5rem;">
    <?= $action==='add' ? 'Add Tagline' : 'Edit Tagline' ?>
  </h2>
  <form method="POST" novalidate>
    <?= csrfField() ?>
    <div class="form-group">
      <label class="form-label">Tagline Text *</label>
      <input class="form-control" name="tagline" required maxlength="255" value="<?= e($editing['tagline'] ?? '') ?>">
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
      <a href="<?= BASE_URL ?>/admin/crud/taglines.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
<?php else: ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1.25rem;">
  <a href="?action=add" class="btn btn-primary">+ Add Tagline</a>
</div>
<table class="admin-table">
  <thead><tr><th>#</th><th>Tagline</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php
  $rows = $pdo->query("SELECT * FROM taglines ORDER BY sort_order")->fetchAll();
  foreach ($rows as $r):
  ?>
  <tr>
    <td style="color:var(--clr-muted);font-size:.8rem;"><?= $r['id'] ?></td>
    <td style="font-style:italic;">"<?= e($r['tagline']) ?>"</td>
    <td><?= $r['sort_order'] ?></td>
    <td><?= $r['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Hidden</span>' ?></td>
    <td>
      <a href="?action=edit&id=<?= $r['id'] ?>" style="color:var(--clr-dark);font-weight:600;font-size:.82rem;margin-right:.75rem;">Edit</a>
      <form method="POST" action="?action=delete&id=<?= $r['id'] ?>" style="display:inline;" onsubmit="return confirm('Delete?')">
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
