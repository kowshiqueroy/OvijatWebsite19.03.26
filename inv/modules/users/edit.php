<?php
/**
 * modules/users/edit.php
 */
include '../../includes/header.php';
requireRole('Admin');

$branch_id = $_SESSION['branch_id'];
$user_id = (int)$_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "<div class='alert alert-danger'>User not found.</div>";
    include '../../includes/footer.php';
    exit;
}

// Fetch branches for admin
$branches = $pdo->query("SELECT id, name FROM branches WHERE is_deleted = 0")->fetchAll();
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">Edit User</h5>
            </div>
            <div class="card-body">
                <form id="editUserForm">
                    <input type="hidden" name="id" value="<?php echo $user_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        <small class="text-muted">Username cannot be changed.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter new password">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="Admin" <?php echo $user['role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="Manager" <?php echo $user['role'] === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                            <option value="Accountant" <?php echo $user['role'] === 'Accountant' ? 'selected' : ''; ?>>Accountant</option>
                            <option value="Viewer" <?php echo $user['role'] === 'Viewer' ? 'selected' : ''; ?>>Viewer</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select">
                            <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo $b['id'] == $user['branch_id'] ? 'selected' : ''; ?>>
                                <?php echo $b['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active" <?php echo $user['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Blocked" <?php echo $user['status'] === 'Blocked' ? 'selected' : ''; ?>>Blocked</option>
                        </select>
                    </div>

                    <div class="text-end">
                        <a href="list.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#editUserForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/users.php?action=edit', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                alert(res.message);
                window.location.href = 'list.php';
            } else {
                alert(res.message);
            }
        }, 'json');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>