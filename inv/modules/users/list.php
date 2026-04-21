<?php
/**
 * modules/users/list.php
 */
include '../../includes/header.php';
requireRole('Admin');

$current_branch_id = $_SESSION['branch_id'];
$is_admin = $_SESSION['role'] === 'Admin';

// Admin sees all users, others see only their branch
if ($is_admin) {
    $stmt = $pdo->query("SELECT u.*, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.is_deleted = 0");
    $users = $stmt->fetchAll();
    $branches = $pdo->query("SELECT id, name FROM branches WHERE is_deleted = 0")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT u.*, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.branch_id = ? AND u.is_deleted = 0");
    $stmt->execute([$current_branch_id]);
    $users = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$current_branch_id]);
    $branches = $stmt->fetchAll();
}
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 fw-bold">User Management</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus me-1"></i> Add New User
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="bg-light">
                        <th>Username</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="fw-bold"><?php echo $u['username']; ?></td>
                        <td><span class="badge bg-info text-dark"><?php echo $u['role']; ?></span></td>
                        <td><?php echo $u['branch_name'] ?: 'N/A'; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $u['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                <?php echo $u['status']; ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="edit.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-info" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-sm btn-outline-<?php echo $u['status'] == 'Active' ? 'warning' : 'success'; ?> toggle-status" data-id="<?php echo $u['id']; ?>" data-status="<?php echo $u['status']; ?>">
                                <i class="fas fa-<?php echo $u['status'] == 'Active' ? 'user-slash' : 'user-check'; ?>"></i>
                            </button>
                            <?php endif; ?>
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
        <form id="addUserForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="Admin">Admin</option>
                            <option value="Manager">Manager</option>
                            <option value="Accountant">Accountant</option>
                            <option value="Viewer">Viewer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select">
                            <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo $b['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#addUserForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/users.php?action=add', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                location.reload();
            } else {
                alert(res.message);
            }
        }, 'json');
    });

    $('.toggle-status').on('click', function() {
        const id = $(this).data('id');
        const currentStatus = $(this).data('status');
        const newStatus = currentStatus === 'Active' ? 'Blocked' : 'Active';
        
        if (confirm(`Are you sure you want to ${newStatus} this user?`)) {
            $.post('../../actions/users.php?action=toggle_status', {id: id, status: newStatus}, function(res) {
                if (res.status === 'success') {
                    location.reload();
                } else {
                    alert(res.message);
                }
            }, 'json');
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
