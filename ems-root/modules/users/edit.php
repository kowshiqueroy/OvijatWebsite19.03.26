<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Edit User';
$breadcrumbs = ['Users' => 'index.php', 'Edit' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['users.edit']);

$pdo    = db();
$id     = int_param('id', 0, $_GET);
$errors = [];

if (!$id) { flash('error','Invalid ID.'); redirect('index.php'); }

$user = $pdo->prepare('SELECT * FROM users WHERE id=:id');
$user->execute([':id' => $id]);
$u = $user->fetch();
if (!$u) { flash('error','User not found.'); redirect('index.php'); }

$userRoles = $pdo->prepare('SELECT role_id FROM user_roles WHERE user_id=:uid');
$userRoles->execute([':uid' => $id]);
$currentRoleIds = $userRoles->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $status    = $_POST['status'] ?? 'active';
    $role_ids  = array_map('intval', (array)($_POST['role_ids'] ?? []));
    $new_pass  = $_POST['new_password'] ?? '';

    if (!$full_name) $errors[] = 'Full name is required.';

    if (empty($errors)) {
        $pdo->prepare('UPDATE users SET full_name=?,email=?,phone=?,status=? WHERE id=?')
            ->execute([$full_name,$email,$phone,$status,$id]);

        if ($new_pass) {
            if (strlen($new_pass) < 4) {
                $errors[] = 'New password must be at least 4 characters.';
            } else {
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                    ->execute([password_hash($new_pass, PASSWORD_BCRYPT), $id]);
            }
        }

        if (empty($errors)) {
            $pdo->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$id]);
            $stmt = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)');
            foreach ($role_ids as $rid) { if ($rid) $stmt->execute([$id,$rid]); }

            log_activity('edit_user', 'users', $id, $u['full_name'], $full_name);
            flash('success', 'User updated successfully.');
            header('Location: index.php');
            exit;
        }
    }
}

$allRoles = $pdo->query('SELECT id, role_name FROM roles ORDER BY role_name')->fetchAll();
require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-person-gear me-2 text-primary"></i>Edit User</h1>
  <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="POST">
      <?= csrf_field() ?>
      <div class="form-section-title mt-0">Account Information</div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Username <small class="text-muted">(read-only)</small></label>
          <input type="text" class="form-control bg-light" value="<?= e($u['username']) ?>" readonly>
        </div>
        <div class="col-md-8">
          <label class="form-label">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= e($_POST['full_name'] ?? $u['full_name']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? $u['email'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $u['phone'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <?php foreach(['active','suspended','archived'] as $st): ?>
              <option value="<?= $st ?>" <?= ($u['status'] === $st) ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
          <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep unchanged">
        </div>
      </div>

      <div class="form-section-title">Roles</div>
      <div class="row g-2">
        <?php foreach ($allRoles as $role): ?>
        <div class="col-md-4 col-sm-6">
          <div class="form-check border rounded p-3">
            <input type="checkbox" class="form-check-input" name="role_ids[]"
                   id="er_<?= $role['id'] ?>" value="<?= $role['id'] ?>"
                   <?= in_array((int)$role['id'], array_map('intval', $currentRoleIds)) ? 'checked' : '' ?>>
            <label class="form-check-label fw-600" for="er_<?= $role['id'] ?>"><?= e($role['role_name']) ?></label>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
