<?php
// ============================================================
// modules/users/users.php — User management (admin only)
// ============================================================
requireRole(ROLE_ADMIN);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'save_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id   = (int)($_POST['user_id_db'] ?? 0);
    $data = [
        'username'  => strtoupper(trim($_POST['username'])),
        'full_name' => trim($_POST['full_name']),
        'role'      => in_array($_POST['role'], ['admin','sr']) ? $_POST['role'] : 'sr',
        'active'    => isset($_POST['active']) ? 1 : 0,
    ];
    if (!empty($_POST['password'])) {
        $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }
    if ($id) {
        // if session user id is not 1 and editing id is 1 then do not allow to update and show error message
        if (currentUser()['id'] !== 1 && $id === 1) {
            flash('error', 'You cannot edit the main admin user.');
            redirect('users');
        }
        dbUpdate('users', $data, 'id = ?', [$id]);
        logAction('UPDATE', 'users', $id, 'Updated user: ' . $data['username'] . ' with data: ' . json_encode($data));
        flash('success', 'User updated.');
    } else {
        if (empty($_POST['password'])) { flash('error', 'Password required for new users.'); redirect('users'); }
        $data['password']   = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $data['created_at'] = now();
        $newId = dbInsert('users', $data);
        logAction('CREATE', 'users', $newId, 'Created user: ' . $data['username']);
        flash('success', 'User created.');
    }
    redirect('users');
}



$users   = dbFetchAll('SELECT * FROM users  ORDER BY created_at DESC');
$editing = !empty($_GET['edit']) ? dbFetch('SELECT * FROM users WHERE id = ?', [(int)$_GET['edit']]) : null;

$pageTitle = 'Users';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-2">
  <h1>👥 Users</h1>
  <button class="btn btn-primary" onclick="openModal('userModal')">+ Add User</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= e($u['full_name']) ?></strong></td>
          <td><?= e($u['username']) ?></td>
          <td><span class="role-pill <?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
          <td><span class="badge badge-<?= $u['active'] ? 'success' : 'grey' ?>"><?= $u['active'] ? 'Active' : 'Inactive' ?></span></td>
          <td><?= fmtDate($u['created_at']) ?></td>
          <td>
            <a href="index.php?page=users&edit=<?= $u['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit</a>
            <?php if ($u['id'] !== currentUser()['id']): ?>
            <a href="index.php?page=users&action=delete&id=<?= $u['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Ask Developer to delete this user.">🗑️</a>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-backdrop <?= $editing ? 'open' : '' ?>" id="userModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><?= $editing ? 'Edit User' : 'Add User' ?></span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="userForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action"     value="save_user">
        <input type="hidden" name="user_id_db" value="<?= $editing['id'] ?? '' ?>">
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" required value="<?= e($editing['full_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Username *</label>
            <input type="text" name="username" class="form-control" required value="<?= e($editing['username'] ?? '') ?>">
          </div>
        </div>
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label"><?= $editing ? 'New Password' : 'Password *' ?></label>
            <input type="password" name="password" class="form-control" <?= $editing ? '' : 'required' ?> placeholder="<?= $editing ? 'Leave blank to keep' : '' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Role *</label>
            <select name="role" class="form-control">
              <option value="sr"    <?= ($editing['role'] ?? '') === 'sr'    ? 'selected' : '' ?>>SR (Sales Rep)</option>
              <option value="admin" <?= ($editing['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="active" value="1" <?= ($editing['active'] ?? 1) ? 'checked' : '' ?>>
            <span class="form-label" style="margin:0">Active</span>
          </label>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button type="submit" form="userForm" class="btn btn-primary">Save User</button>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
