<?php
require_once '../../templates/header.php';
check_login();
check_role(ROLE_ADMIN);

if (isset($_POST['add_user'])) {
    $username = sanitize($_POST['username']);
    $phone = sanitize($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = sanitize($_POST['role']);

    db_query("INSERT INTO users (username, password, phone, role) VALUES (?, ?, ?, ?)", [$username, $password, $phone, $role]);
    log_activity($_SESSION['user_id'], "Created new user: $username");
    redirect('modules/users/index.php', 'User created successfully.');
}

$users = fetch_all("SELECT * FROM users ORDER BY created_at DESC");
?>

<div class="row">
    <div class="col-12 d-flex justify-content-between align-items-center mb-4">
        <h3>User Management</h3>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-2"></i> Add New User
        </button>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Last Active</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong><?php echo $u['username']; ?></strong></td>
                        <td><?php echo $u['phone']; ?></td>
                        <td><span class="badge bg-info"><?php echo $u['role']; ?></span></td>
                        <td><?php echo $u['last_active'] ? date('d M, h:i A', strtotime($u['last_active'])) : 'Never'; ?></td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Blocked</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="toggle_status.php?id=<?php echo $u['id']; ?>" class="btn btn-sm <?php echo $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>" title="Toggle Status">
                                <i class="fas fa-power-off"></i>
                            </a>
                            <a href="logs.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Activity Logs">
                                <i class="fas fa-list"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create System User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control" required>
                        <option value="<?php echo ROLE_MANAGER; ?>"><?php echo ROLE_MANAGER; ?></option>
                        <option value="<?php echo ROLE_ACCOUNTANT; ?>"><?php echo ROLE_ACCOUNTANT; ?></option>
                        <option value="<?php echo ROLE_SR; ?>"><?php echo ROLE_SR; ?></option>
                        <option value="<?php echo ROLE_VIEWER; ?>"><?php echo ROLE_VIEWER; ?></option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_user" class="btn btn-primary">Save User</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
