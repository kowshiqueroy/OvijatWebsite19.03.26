<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Change Password';
$breadcrumbs = ['Change Password' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_auth();

$pdo    = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $current = $_POST['current_password'] ?? '';
    $new1    = $_POST['new_password'] ?? '';
    $new2    = $_POST['confirm_password'] ?? '';

    $user = $pdo->prepare('SELECT password_hash FROM users WHERE id=:id');
    $user->execute([':id' => current_user_id()]);
    $u = $user->fetch();

    if (!password_verify($current, $u['password_hash'])) $errors[] = 'Current password is incorrect.';
    if (strlen($new1) < 6) $errors[] = 'New password must be at least 6 characters.';
    if ($new1 !== $new2)   $errors[] = 'New passwords do not match.';

    if (empty($errors)) {
        $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
            ->execute([password_hash($new1, PASSWORD_BCRYPT), current_user_id()]);
        log_activity('change_password', 'users', current_user_id());
        flash('success', 'Password changed successfully.');
        header('Location: ../../dashboard.php');
        exit;
    }
}

require_once EMS_ROOT . '/core/rbac.php';
require_once EMS_ROOT . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5">
    <h1 class="page-title"><i class="bi bi-key-fill me-2 text-primary"></i>Change Password</h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password <small class="text-muted">(min 6 chars)</small></label>
            <input type="password" name="new_password" class="form-control" required>
          </div>
          <div class="mb-4">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Change Password</button>
            <a href="../../dashboard.php" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
