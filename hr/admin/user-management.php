<?php
/**
 * User Management Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'User Management';
$currentPage = 'users';

$conn = getDBConnection();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }

    if (isset($_POST['add_user'])) {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($username) || empty($password)) {
            $message = 'Username and password are required.';
            $messageType = 'danger';
        } elseif ($password !== $confirmPassword) {
            $message = 'Passwords do not match.';
            $messageType = 'danger';
        } elseif (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters.';
            $messageType = 'danger';
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM admin WHERE username = ?");
            $checkStmt->bind_param("s", $username);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $message = 'Username already exists.';
                $messageType = 'danger';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $username, $hashedPassword);
                
                if ($stmt->execute()) {
                    logActivity('create', 'user', $conn->insert_id, 'User created: ' . $username);
                    $message = 'User created successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Error creating user.';
                    $messageType = 'danger';
                }
                $stmt->close();
            }
            $checkStmt->close();
        }
    }

    if (isset($_POST['edit_user'])) {
        $userId = (int)$_POST['user_id'];
        $username = sanitize($_POST['username'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';

        if (empty($username)) {
            $message = 'Username is required.';
            $messageType = 'danger';
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM admin WHERE username = ? AND id != ?");
            $checkStmt->bind_param("si", $username, $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $message = 'Username already exists.';
                $messageType = 'danger';
            } else {
                if (!empty($newPassword)) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admin SET username = ?, password = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $username, $hashedPassword, $userId);
                } else {
                    $stmt = $conn->prepare("UPDATE admin SET username = ? WHERE id = ?");
                    $stmt->bind_param("si", $username, $userId);
                }
                
                if ($stmt->execute()) {
                    $getStmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
                    $getStmt->bind_param("i", $userId);
                    $getStmt->execute();
                    $userResult = $getStmt->get_result();
                    $userData = $userResult->fetch_assoc();
                    $getStmt->close();
                    
                    logActivity('update', 'user', $userId, "Updated: $username | Data: " . json_encode($userData));
                    $message = 'User updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating user.';
                    $messageType = 'danger';
                }
                $stmt->close();
            }
            $checkStmt->close();
        }
    }

    if (isset($_POST['toggle_block'])) {
        $userId = (int)$_POST['user_id'];
        $currentStatus = $_POST['current_status'] ?? 'active';
        
        if ($userId === $_SESSION['admin_id']) {
            $message = 'You cannot block/unblock yourself.';
            $messageType = 'danger';
        } else {
            $getStmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
            $getStmt->bind_param("i", $userId);
            $getStmt->execute();
            $userResult = $getStmt->get_result();
            $userData = $userResult->fetch_assoc();
            $getStmt->close();
            
            if ($currentStatus === 'blocked') {
                $hashedPassword = password_hash('unblocked123', PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admin SET password = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param("si", $hashedPassword, $userId);
                $stmt->execute();
                $stmt->close();
                logActivity('update', 'user', $userId, "Unblocked: {$userData['username']} | Data: " . json_encode($userData));
                $message = 'User unblocked successfully. Password reset to default.';
            } else {
                $randomPassword = bin2hex(random_bytes(8));
                $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashedPassword, $userId);
                $stmt->execute();
                $stmt->close();
                logActivity('update', 'user', $userId, "Blocked: {$userData['username']} | Data: " . json_encode($userData));
                $message = 'User blocked successfully. Password changed to random.';
            }
            $messageType = 'success';
        }
    }

    if (isset($_POST['delete_user'])) {
        $userId = (int)$_POST['user_id'];
        
        if ($userId === $_SESSION['admin_id']) {
            $message = 'You cannot delete yourself.';
            $messageType = 'danger';
        } else {
            $getStmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
            $getStmt->bind_param("i", $userId);
            $getStmt->execute();
            $userResult = $getStmt->get_result();
            $userData = $userResult->fetch_assoc();
            $getStmt->close();
            
            $stmt = $conn->prepare("DELETE FROM admin WHERE id = ?");
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                logActivity('delete', 'user', $userId, "Deleted: {$userData['username']} | Data: " . json_encode($userData));
                $message = 'User deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error deleting user.';
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
}

$result = $conn->query("SELECT id, username, created_at FROM admin ORDER BY id ASC");
$users = [];
while ($row = $result->fetch_assoc()) {
    $row['is_blocked'] = ($row['created_at'] === '0000-00-00 00:00:00' || $row['created_at'] === null);
    $users[] = $row;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1"><i class="bi bi-people me-2"></i>User Management</h4>
        <small class="text-muted">Manage admin users and permissions</small>
    </div>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $messageType); ?>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Add New User</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-primary w-100">
                        <i class="bi bi-plus-lg me-1"></i> Create User
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>All Users</h5>
                <span class="badge bg-primary"><?php echo count($users); ?> Users</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No users found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    $isCurrentUser = ($user['id'] == $_SESSION['admin_id']);
                                    $createdAt = $user['is_blocked'] 
                                        ? '<span class="text-danger">Blocked</span>' 
                                        : ($user['created_at'] ? date('d M Y, h:i A', strtotime($user['created_at'])) : 'N/A');
                                    ?>
                                    <tr class="<?php echo $user['is_blocked'] ? 'table-danger' : ''; ?>">
                                        <td><?php echo $user['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                            <?php if ($isCurrentUser): ?>
                                                <span class="badge bg-info ms-1">You</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['is_blocked']): ?>
                                                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Blocked</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $createdAt; ?></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editModal<?php echo $user['id']; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if (!$isCurrentUser): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="current_status" value="<?php echo $user['is_blocked'] ? 'blocked' : 'active'; ?>">
                                                        <button type="submit" name="toggle_block" class="btn btn-outline-<?php echo $user['is_blocked'] ? 'success' : 'warning'; ?>"
                                                                onclick="return confirm('<?php echo $user['is_blocked'] ? 'Unblock this user?' : 'Block this user?'; ?>')">
                                                            <i class="bi bi-<?php echo $user['is_blocked'] ? 'unlock' : 'lock'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="" class="d-inline">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-outline-danger"
                                                                onclick="return confirm('Are you sure you want to delete this user?');">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php foreach ($users as $user): ?>
<div class="modal fade" id="editModal<?php echo $user['id']; ?>" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User: <?php echo htmlspecialchars($user['username']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" name="new_password" class="form-control" minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>