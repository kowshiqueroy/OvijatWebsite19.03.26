<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('admin');
$user = currentUser();
$id = (int)($_GET['id'] ?? 0);
$u = dbFetch("SELECT * FROM users WHERE id=?", [$id]);
if (!$u) { flash('error', 'User not found.'); redirect(BASE_URL . '/modules/users/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $role     = $_POST['role'] ?? $u['role'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $newPass  = $_POST['password'] ?? '';

    if (!$fullName) { flash('error', 'Full name required.'); redirect(BASE_URL . '/modules/users/edit.php?id='.$id); }

    $data = ['full_name' => $fullName, 'role' => $role, 'is_active' => $isActive];
    if ($newPass) $data['password_hash'] = password_hash($newPass, PASSWORD_DEFAULT);
    dbUpdate('users', $data, ['id' => $id]);
    flash('success', 'User updated.');
    redirect(BASE_URL . '/modules/users/index.php');
}

layoutStart('Edit User', 'users');
?>
<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/modules/users/index.php" style="font-size:.875rem;color:var(--text-muted)">&larr; Users</a>
        <h1 class="page-title" style="margin-top:4px">Edit User</h1>
    </div>
</div>
<div class="card" style="max-width:520px">
<div class="card-body">
<form method="POST">
    <div class="form-group">
        <label class="form-label">Username</label>
        <input class="form-control" value="<?= e($u['username']) ?>" disabled>
    </div>
    <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input name="full_name" class="form-control" value="<?= e($_POST['full_name'] ?? $u['full_name']) ?>" required autofocus>
    </div>
    <div class="form-group">
        <label class="form-label">New Password <span style="color:var(--text-muted);font-weight:400">(leave blank to keep current)</span></label>
        <input name="password" type="password" class="form-control" autocomplete="new-password">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Role</label>
            <select name="role" class="form-control" <?= $u['id'] === $user['id'] ? 'disabled' : '' ?>>
                <option value="member" <?= ($_POST['role'] ?? $u['role']) === 'member' ? 'selected' : '' ?>>Member</option>
                <option value="admin"  <?= ($_POST['role'] ?? $u['role']) === 'admin'  ? 'selected' : '' ?>>Admin</option>
            </select>
            <?php if ($u['id'] === $user['id']): ?><input type="hidden" name="role" value="<?= e($u['role']) ?>"><?php endif ?>
        </div>
        <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px">
            <label style="display:flex;align-items:center;gap:8px;font-size:.875rem;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= ($u['is_active']) ? 'checked' : '' ?> <?= $u['id'] === $user['id'] ? 'disabled' : '' ?>>
                Active
                <?php if ($u['id'] === $user['id']): ?><input type="hidden" name="is_active" value="1"><?php endif ?>
            </label>
        </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>
</div>
</div>
<?php layoutEnd(); ?>
