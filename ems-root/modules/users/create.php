<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Add User';
$breadcrumbs = ['Users' => 'index.php', 'Add User' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['users.create']);

$pdo    = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $role_ids  = array_map('intval', (array)($_POST['role_ids'] ?? []));
    $status    = $_POST['status'] ?? 'active';

    if (!$username)        $errors[] = 'Username is required.';
    if (!$full_name)       $errors[] = 'Full name is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password2) $errors[] = 'Passwords do not match.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

    if (empty($errors)) {
        $check = $pdo->prepare('SELECT id FROM users WHERE username=:u OR (email=:e AND email != "")');
        $check->execute([':u' => $username, ':e' => $email]);
        if ($check->fetch()) $errors[] = 'Username or email already exists.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO users (username, password_hash, full_name, email, phone, status) VALUES (?,?,?,?,?,?)')
            ->execute([$username, $hash, $full_name, $email, $phone, $status]);
        $userId = (int)$pdo->lastInsertId();

        // Assign roles
        $rpStmt = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?,?)');
        foreach ($role_ids as $rid) { if ($rid) $rpStmt->execute([$userId, $rid]); }

        log_activity('create_user', 'users', $userId, '', $username);
        flash('success', "User '{$full_name}' created successfully.");
        header('Location: index.php');
        exit;
    }
}

$allRoles = $pdo->query('SELECT id, role_name FROM roles ORDER BY role_name')->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Add New User</h1>
  <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="POST">
      <?= csrf_field() ?>

      <div class="form-section-title mt-0">Account Information</div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= e($_POST['full_name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Username <span class="text-danger">*</span></label>
          <input type="text" name="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>"
                 pattern="[a-zA-Z0-9_.]+" title="Letters, numbers, underscore, dot only" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Password <span class="text-danger">*</span> <small class="text-muted">(min 6)</small></label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
          <input type="password" name="password2" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
          </select>
        </div>
      </div>

      <div class="form-section-title">Assign Roles <small class="text-muted fw-normal">(multiple allowed)</small></div>
      <div class="row g-2">
        <?php foreach ($allRoles as $role): ?>
        <div class="col-md-4 col-sm-6">
          <div class="form-check border rounded p-3">
            <input type="checkbox" class="form-check-input" name="role_ids[]"
                   id="role_<?= $role['id'] ?>" value="<?= $role['id'] ?>"
                   <?= in_array((int)$role['id'], array_map('intval', (array)($_POST['role_ids'] ?? []))) ? 'checked' : '' ?>>
            <label class="form-check-label" for="role_<?= $role['id'] ?>">
              <span class="fw-600"><?= e($role['role_name']) ?></span>
            </label>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create User</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
