<?php
/**
 * User Management Module
 */
$pageTitle = 'User Management';
require_once 'layout_header.php';

$db = Database::getInstance();
$message = '';
$error = '';

// Handle Role Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token invalid.";
    } else {
        $targetUserId = (int)$_POST['user_id'];
        if ($_POST['action'] === 'update_role') {
            $newRole = $_POST['role'];
            $db->query("UPDATE users SET role = ? WHERE id = ?", [$newRole, $targetUserId]);
            $message = "User #$targetUserId role updated to $newRole.";
        }
    }
}

// Fetch All Users
$roleFilter = $_GET['role'] ?? 'all';
$sql = "SELECT u.*, p.first_name, p.last_name, p.phone, w.balance 
        FROM users u 
        LEFT JOIN user_profiles p ON u.id = p.user_id 
        LEFT JOIN wallets w ON u.id = w.user_id";

if ($roleFilter !== 'all') {
    $sql .= " WHERE u.role = " . $db->quote($roleFilter);
}
$sql .= " ORDER BY u.created_at DESC";

$users = $db->query($sql)->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>User Management</h1>
    <div style="display: flex; gap: 10px;">
        <a href="?role=all" class="btn <?php echo $roleFilter == 'all' ? 'btn-primary' : 'btn-outline'; ?>">All Users</a>
        <a href="?role=pending_wholesale" class="btn <?php echo $roleFilter == 'pending_wholesale' ? 'btn-primary' : 'btn-outline'; ?>">Pending Wholesale</a>
        <a href="?role=wholesale" class="btn <?php echo $roleFilter == 'wholesale' ? 'btn-primary' : 'btn-outline'; ?>">Wholesale</a>
        <a href="?role=admin" class="btn <?php echo $roleFilter == 'admin' ? 'btn-primary' : 'btn-outline'; ?>">Admins</a>
        <a href="?role=editor" class="btn <?php echo $roleFilter == 'editor' ? 'btn-primary' : 'btn-outline'; ?>">Editors</a>
        <a href="?role=warehouse" class="btn <?php echo $roleFilter == 'warehouse' ? 'btn-primary' : 'btn-outline'; ?>">Warehouse</a>
        <a href="?role=reports" class="btn <?php echo $roleFilter == 'reports' ? 'btn-primary' : 'btn-outline'; ?>">Reports</a>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name / Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Wallet</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>#<?php echo $u['id']; ?></td>
                <td>
                    <strong><?php echo htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); ?></strong>
                    <div style="font-size: 0.75rem; opacity: 0.6;"><?php echo htmlspecialchars($u['email']); ?></div>
                </td>
                <td><?php echo htmlspecialchars($u['phone'] ?? 'N/A'); ?></td>
                <td>
                    <span class="badge" style="background: <?php 
                        echo $u['role'] === 'admin' ? '#fed7d7; color: #9b2c2c;' : 
                            ($u['role'] === 'wholesale' ? '#c6f6d5; color: #22543d;' : 
                            ($u['role'] === 'pending_wholesale' ? '#feebc8; color: #744210;' : '#edf2f7; color: #2d3748;')); 
                    ?>">
                        <?php echo str_replace('_', ' ', $u['role']); ?>
                    </span>
                </td>
                <td><strong>$<?php echo number_format($u['balance'] ?? 0, 2); ?></strong></td>
                <td><small><?php echo date('M d, Y', strtotime($u['created_at'])); ?></small></td>
                <td>
                    <form method="POST" style="display: flex; gap: 5px; align-items: center;">
                        <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                        <select name="role" style="width: auto; padding: 4px 8px; font-size: 0.7rem;" onchange="this.form.submit()">
                            <option value="retail" <?php echo $u['role'] === 'retail' ? 'selected' : ''; ?>>Retail</option>
                            <option value="wholesale" <?php echo $u['role'] === 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                            <option value="pending_wholesale" <?php echo $u['role'] === 'pending_wholesale' ? 'selected' : ''; ?>>Pending</option>
                            <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="editor" <?php echo $u['role'] === 'editor' ? 'selected' : ''; ?>>Editor</option>
                            <option value="warehouse" <?php echo $u['role'] === 'warehouse' ? 'selected' : ''; ?>>Warehouse</option>
                            <option value="reports" <?php echo $u['role'] === 'reports' ? 'selected' : ''; ?>>Reports</option>
                        </select>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'layout_footer.php'; ?>
?>
