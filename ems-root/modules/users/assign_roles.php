<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Assign Roles';
$breadcrumbs = ['Users' => 'index.php', 'Assign Roles' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['roles.manage']);

$pdo = db();
$id  = int_param('id', 0, $_GET);
if (!$id) { flash('error','Invalid user.'); redirect('index.php'); }

$user = $pdo->prepare('SELECT id, username, full_name FROM users WHERE id=:id');
$user->execute([':id'=>$id]);
$user = $user->fetch();
if (!$user) { flash('error','User not found.'); redirect('index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $role_ids = array_map('intval', (array)($_POST['role_ids']??[]));
    $pdo->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$id]);
    $stmt = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)');
    foreach ($role_ids as $rid) { if($rid) $stmt->execute([$id,$rid]); }
    log_activity('assign_roles','users',$id,'','roles:'.implode(',',$role_ids));
    flash('success','Roles updated for '.e($user['full_name']).'.');
    header('Location: index.php');
    exit;
}

$allRoles = $pdo->query('SELECT id,role_name,role_slug,description FROM roles ORDER BY role_name')->fetchAll();
$current  = $pdo->prepare('SELECT role_id FROM user_roles WHERE user_id=:id');
$current->execute([':id'=>$id]);
$currentRoles = $current->fetchAll(PDO::FETCH_COLUMN);

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-shield-fill-check me-2 text-primary"></i>Assign Roles</h1>
  <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Users</a>
</div>
<div class="alert alert-info d-flex gap-2 mb-4">
  <i class="bi bi-person-fill fs-5"></i>
  <div>Assigning roles for <strong><?= e($user['full_name']) ?></strong> (<code><?= e($user['username']) ?></code>)</div>
</div>
<form method="POST">
  <?= csrf_field() ?>
  <div class="row g-3 mb-4">
    <?php foreach($allRoles as $role): $checked = in_array((string)$role['id'],$currentRoles); ?>
    <div class="col-md-4 col-sm-6">
      <div class="form-check border rounded p-3 h-100 <?= $checked?'border-primary bg-light':'' ?>">
        <input type="checkbox" class="form-check-input" name="role_ids[]" id="ar_<?= $role['id'] ?>" value="<?= $role['id'] ?>" <?= $checked?'checked':'' ?>>
        <label class="form-check-label w-100" for="ar_<?= $role['id'] ?>">
          <span class="fw-700 d-block"><?= e($role['role_name']) ?></span>
          <code class="small text-muted"><?= e($role['role_slug']) ?></code>
          <?php if($role['description']): ?><p class="small text-muted mb-0 mt-1"><?= e($role['description']) ?></p><?php endif; ?>
        </label>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Roles</button>
    <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
