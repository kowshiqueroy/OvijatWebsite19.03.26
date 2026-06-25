<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'My Profile';
$breadcrumbs = ['My Profile' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth();

$pdo  = db();
$uid  = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    if ($full_name) {
        $pdo->prepare('UPDATE users SET full_name=?,email=?,phone=? WHERE id=?')->execute([$full_name,$email,$phone,$uid]);
        $_SESSION['full_name'] = $full_name;
        flash('success', 'Profile updated.');
        header('Location: profile.php');
        exit;
    }
}

$user = $pdo->prepare('SELECT u.*, GROUP_CONCAT(r.role_name SEPARATOR ", ") as roles
                        FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id
                        WHERE u.id=:id GROUP BY u.id');
$user->execute([':id' => $uid]);
$u = $user->fetch();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-7">
    <h1 class="page-title"><i class="bi bi-person-circle me-2 text-primary"></i>My Profile</h1>

    <div class="card mb-3">
      <div class="card-body d-flex align-items-center gap-4 py-4">
        <div class="topbar-avatar" style="width:70px;height:70px;font-size:1.8rem;flex-shrink:0;">
          <?= strtoupper(substr($u['full_name'],0,1)) ?>
        </div>
        <div>
          <h4 class="fw-700 mb-0"><?= e($u['full_name']) ?></h4>
          <p class="text-muted mb-1"><?= e($u['email'] ?? '') ?></p>
          <div class="d-flex gap-2 flex-wrap">
            <?php foreach(explode(', ', $u['roles'] ?? '') as $role): if(!trim($role)) continue; ?>
              <span class="badge bg-primary"><?= e(trim($role)) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Edit Profile</span></div>
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" class="form-control" value="<?= e($u['full_name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= e($u['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= e($u['phone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Username <small class="text-muted">(cannot change)</small></label>
              <input type="text" class="form-control bg-light" value="<?= e($u['username']) ?>" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label">Member Since</label>
              <input type="text" class="form-control bg-light" value="<?= e(fmt_date($u['created_at'])) ?>" readonly>
            </div>
          </div>
          <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
            <a href="change_password.php" class="btn btn-outline-warning"><i class="bi bi-key me-1"></i>Change Password</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
