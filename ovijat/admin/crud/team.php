<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/upload_helper.php';
require_once dirname(__DIR__) . '/layout.php';

$pdo       = getPDO();
$uploadDir = dirname(__DIR__, 2) . '/uploads/team/';
$msg = $err = '';
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// DELETE
if ($action === 'delete' && $id) {
    verifyCsrf();
    $s = $pdo->prepare("SELECT photo FROM team_members WHERE id=?"); $s->execute([$id]);
    $row = $s->fetch();
    if ($row) { deleteUpload($uploadDir, $row['photo']); }
    $pdo->prepare("DELETE FROM team_members WHERE id=?")->execute([$id]);
    header('Location: ' . BASE_URL . '/admin/crud/team.php?msg=deleted'); exit;
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action,['add','edit'])) {
    verifyCsrf();
    $name      = trim($_POST['name'] ?? '');
    $position  = trim($_POST['position'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');
    $type      = in_array($_POST['type'] ?? '', ['chairman','md','management']) ? $_POST['type'] : 'management';
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if (!$name || !$position) { $err = 'Name and position are required.'; }
    else {
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $up = uploadImage($_FILES['photo'], $uploadDir, 600, 90);
            if (!$up['success']) { $err = $up['error']; }
            else $photo = $up['filename'];
        }
        if (!$err) {
            if ($action === 'add') {
                $pdo->prepare("INSERT INTO team_members (name,position,bio,photo,type,sort_order,is_active) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$name,$position,$bio,$photo,$type,$sortOrder,$isActive]);
            } else {
                if ($photo) {
                    $old = $pdo->prepare("SELECT photo FROM team_members WHERE id=?"); $old->execute([$id]);
                    deleteUpload($uploadDir, $old->fetchColumn() ?: null);
                }
                $pdo->prepare("UPDATE team_members SET name=?,position=?,bio=?,type=?,sort_order=?,is_active=?" . ($photo?",photo=?":"") . " WHERE id=?")
                    ->execute(array_filter([$name,$position,$bio,$type,$sortOrder,$isActive,$photo,$id], fn($v)=>$v!==null||is_int($v)));
                // Rebuild params cleanly
                $params = [$name,$position,$bio,$type,$sortOrder,$isActive];
                if ($photo) $params[] = $photo;
                $params[] = $id;
                $pdo->prepare("UPDATE team_members SET name=?,position=?,bio=?,type=?,sort_order=?,is_active=?" . ($photo?",photo=?":"") . " WHERE id=?")
                    ->execute($params);
            }
            header('Location: ' . BASE_URL . '/admin/crud/team.php?msg=saved'); exit;
        }
    }
}

$editing = null;
if ($action === 'edit' && $id) {
    $s = $pdo->prepare("SELECT * FROM team_members WHERE id=?"); $s->execute([$id]);
    $editing = $s->fetch();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'] === 'saved' ? 'Team member saved.' : 'Member deleted.';

adminOpen('Team Members', 'team');
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<?php if (in_array($action,['add','edit'])): ?>
<div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;max-width:680px;">
  <h2 style="font-family:var(--ff-heading);font-size:1.4rem;margin-bottom:1.5rem;">
    <?= $action === 'add' ? 'Add Team Member' : 'Edit Team Member' ?>
  </h2>
  <form method="POST" enctype="multipart/form-data" novalidate>
    <?= csrfField() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input class="form-control" name="name" required value="<?= e($editing['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Position / Title *</label>
        <input class="form-control" name="position" required value="<?= e($editing['position'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Member Type</label>
        <select class="form-control" name="type">
          <?php foreach (['chairman'=>'Chairman','md'=>'Managing Director','management'=>'Management'] as $val=>$lbl): ?>
            <option value="<?= $val ?>" <?= ($editing['type'] ?? 'management') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Sort Order</label>
        <input class="form-control" type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>">
      </div>
      <div class="form-group" style="grid-column:1/-1;">
        <label class="form-label">Short Bio</label>
        <textarea class="form-control" name="bio" rows="3"><?= e($editing['bio'] ?? '') ?></textarea>
      </div>
      <div class="form-group" style="grid-column:1/-1;">
        <label class="form-label">Photo (JPG/PNG/WebP, max 2MB)</label>
        <?php if (!empty($editing['photo'])): ?>
          <div style="margin-bottom:.75rem;">
            <img src="<?= BASE_URL ?>/uploads/team/<?= e($editing['photo']) ?>" alt="" style="height:80px;border-radius:50%;border:2px solid var(--clr-gold);">
          </div>
        <?php endif; ?>
        <input class="form-control" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
          <input type="checkbox" name="is_active" value="1" <?= ($editing['is_active'] ?? 1) ? 'checked' : '' ?>>
          <span class="form-label" style="margin:0;">Active</span>
        </label>
      </div>
    </div>
    <div style="display:flex;gap:1rem;margin-top:.5rem;">
      <button type="submit" class="btn btn-primary">Save Member</button>
      <a href="<?= BASE_URL ?>/admin/crud/team.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1.25rem;">
  <a href="?action=add" class="btn btn-primary">+ Add Member</a>
</div>
<table class="admin-table">
  <thead><tr><th>Photo</th><th>Name</th><th>Position</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php
  $members = $pdo->query("SELECT * FROM team_members ORDER BY sort_order,id")->fetchAll();
  foreach ($members as $m):
  ?>
  <tr>
    <td>
      <?php if ($m['photo']): ?>
        <img src="<?= BASE_URL ?>/uploads/team/<?= e($m['photo']) ?>" alt="" style="height:44px;width:44px;object-fit:cover;border-radius:50%;border:2px solid var(--clr-gold);">
      <?php else: ?>
        <div style="width:44px;height:44px;border-radius:50%;background:var(--clr-dark);display:flex;align-items:center;justify-content:center;color:var(--clr-gold);font-weight:800;">
          <?= strtoupper(substr($m['name'],0,1)) ?>
        </div>
      <?php endif; ?>
    </td>
    <td><strong><?= e($m['name']) ?></strong></td>
    <td><?= e($m['position']) ?></td>
    <td><span class="badge badge-gold"><?= e(ucfirst($m['type'])) ?></span></td>
    <td><?= $m['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Hidden</span>' ?></td>
    <td>
      <a href="?action=edit&id=<?= $m['id'] ?>" style="color:var(--clr-dark);font-weight:600;font-size:.82rem;margin-right:.75rem;">Edit</a>
      <form method="POST" action="?action=delete&id=<?= $m['id'] ?>" style="display:inline;" onsubmit="return confirm('Delete member?')">
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
