<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('admin');
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $fullName  = trim($_POST['full_name'] ?? '');
    $role      = $_POST['role'] ?? 'member';
    $password  = $_POST['password'] ?? '';
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if (!$username || !$fullName || !$password) { flash('error', 'Username, full name and password are required.'); redirect(BASE_URL . '/modules/users/create.php'); }
    if (dbFetch("SELECT id FROM users WHERE username=?", [$username])) { flash('error', 'Username already taken.'); redirect(BASE_URL . '/modules/users/create.php'); }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    dbInsert('users', ['username' => $username, 'password_hash' => $hash, 'full_name' => $fullName, 'role' => $role, 'is_active' => $isActive]);
    flash('success', 'User created.');
    redirect(BASE_URL . '/modules/users/index.php');
}

layoutStart('New User', 'users');
?>
<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/modules/users/index.php" style="font-size:.875rem;color:var(--text-muted)">&larr; Users</a>
        <h1 class="page-title" style="margin-top:4px">New User</h1>
    </div>
</div>
<div class="card" style="max-width:520px">
<div class="card-body">
<form method="POST">
    <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input name="full_name" class="form-control" value="<?= e($_POST['full_name'] ?? '') ?>" required autofocus>
    </div>
    <div class="form-group">
        <label class="form-label">Username *</label>
        <input name="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>" required autocomplete="off">
    </div>
    <div class="form-group">
        <label class="form-label">Password *</label>
        <input name="password" type="password" class="form-control" required autocomplete="new-password">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Role</label>
            <select name="role" class="form-control">
                <option value="member" <?= ($_POST['role'] ?? 'member') === 'member' ? 'selected' : '' ?>>Member</option>
                <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px">
            <label style="display:flex;align-items:center;gap:8px;font-size:.875rem;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= isset($_POST['is_active']) || !isset($_POST['username']) ? 'checked' : '' ?>>
                Active
            </label>
        </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Create User</button>
        <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>
</div>
</div>
<?php layoutEnd(); ?>
