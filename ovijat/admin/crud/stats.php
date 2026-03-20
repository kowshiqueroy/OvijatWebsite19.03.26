<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/layout.php';

$pdo    = getPDO();
$msg = $err = '';
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    verifyCsrf();
    $pdo->prepare("DELETE FROM stats WHERE id=?")->execute([$id]);
    header('Location: ' . BASE_URL . '/admin/crud/stats.php?msg=deleted'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action,['add','edit'])) {
    verifyCsrf();
    $label     = trim($_POST['label'] ?? '');
    $value     = (int)($_POST['value'] ?? 0);
    $suffix    = trim($_POST['suffix'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if (!$label) { $err = 'Label is required.'; }
    elseif ($value < 0) { $err = 'Value must be a positive number.'; }
    else {
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO stats (label,value,suffix,sort_order,is_active) VALUES (?,?,?,?,?)")
                ->execute([$label,$value,$suffix,$sortOrder,$isActive]);
        } else {
            $pdo->prepare("UPDATE stats SET label=?,value=?,suffix=?,sort_order=?,is_active=? WHERE id=?")
                ->execute([$label,$value,$suffix,$sortOrder,$isActive,$id]);
        }
        header('Location: ' . BASE_URL . '/admin/crud/stats.php?msg=saved'); exit;
    }
}

$editing = null;
if ($action === 'edit' && $id) {
    $s = $pdo->prepare("SELECT * FROM stats WHERE id=?"); $s->execute([$id]);
    $editing = $s->fetch();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'] === 'saved' ? 'Stat saved.' : 'Stat deleted.';

adminOpen('Stats Counters', 'stats');
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<?php if (in_array($action,['add','edit'])): ?>
<div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;max-width:560px;">
  <h2 style="font-family:var(--ff-heading);font-size:1.4rem;margin-bottom:1.5rem;">
    <?= $action==='add' ? 'Add Stat Counter' : 'Edit Stat Counter' ?>
  </h2>
  <form method="POST" novalidate>
    <?= csrfField() ?>
    <div class="form-group">
      <label class="form-label">Label *</label>
      <input class="form-control" name="label" required maxlength="100" value="<?= e($editing['label'] ?? '') ?>" placeholder="e.g., Years of Experience">
    </div>
    <div class="form-group">
      <label class="form-label">Number Value *</label>
      <input class="form-control" type="number" name="value" required min="0" value="<?= (int)($editing['value'] ?? 0) ?>" placeholder="e.g., 10">
    </div>
    <div class="form-group">
      <label class="form-label">Suffix</label>
      <input class="form-control" name="suffix" maxlength="20" value="<?= e($editing['suffix'] ?? '') ?>" placeholder="e.g., +, %, K+">
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
      <a href="<?= BASE_URL ?>/admin/crud/stats.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
<?php else: ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1.25rem;">
  <a href="?action=add" class="btn btn-primary">+ Add Stat Counter</a>
</div>
<table class="admin-table">
  <thead><tr><th>#</th><th>Label</th><th>Value</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php
  $rows = $pdo->query("SELECT * FROM stats ORDER BY sort_order")->fetchAll();
  foreach ($rows as $r):
  ?>
  <tr>
    <td style="color:var(--clr-muted);font-size:.8rem;"><?= $r['id'] ?></td>
    <td><?= e($r['label']) ?></td>
    <td><strong><?= $r['value'] ?><?= e($r['suffix']) ?></strong></td>
    <td><?= $r['sort_order'] ?></td>
    <td><?= $r['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Hidden</span>' ?></td>
    <td>
      <a href="?action=edit&id=<?= $r['id'] ?>" style="color:var(--clr-dark);font-weight:600;font-size:.82rem;margin-right:.75rem;">Edit</a>
      <form method="POST" action="?action=delete&id=<?= $r['id'] ?>" style="display:inline;" onsubmit="return confirm('Delete this stat?')">
        <?= csrfField() ?>
        <button type="submit" style="color:var(--clr-crimson);font-weight:600;font-size:.82rem;">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" style="text-align:center;color:var(--clr-muted);padding:2rem;">No stat counters yet.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php endif; ?>
<?php adminClose(); ?>
