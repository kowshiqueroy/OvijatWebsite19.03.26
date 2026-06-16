<?php
/**
 * Admin User Verification Module
 */
restrict_to(['admin']);

$error = '';
$success = '';

// Handle Status Changes
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $new_status = ($action === 'approve') ? 'active' : (($action === 'suspend') ? 'suspended' : null);

    if ($new_status) {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'wholesale_user'");
        if ($stmt->execute([$new_status, $user_id])) {
            $success = "User status updated to $new_status.";
            log_action($pdo, $_SESSION['user_id'], "User Status Changed to $new_status", "User ID: $user_id");
        }
    }
}

// Fetch Users
$pending_users = $pdo->query("SELECT * FROM users WHERE status = 'pending' AND role = 'wholesale_user' ORDER BY created_at DESC")->fetchAll();
$all_users = $pdo->query("SELECT * FROM users WHERE role = 'wholesale_user' ORDER BY created_at DESC")->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-users-cog" style="color: var(--primary);"></i>
    User Verification & Management
</div>

<?php if ($success): ?>
    <div style="background: rgba(16, 185, 129, 0.1); color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2);">
        <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
    </div>
<?php endif; ?>

<h3 style="font-weight: 800; color: var(--secondary); margin-bottom: 1rem;"><i class="fas fa-clock" style="color: var(--accent);"></i> Pending Approvals</h3>
<div class="table-wrap" style="margin-bottom: 3rem;">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Registered</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$pending_users): ?>
                <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No pending users found.</td></tr>
            <?php endif; ?>
            <?php foreach ($pending_users as $u): ?>
            <tr>
                <td><strong style="color: var(--secondary);"><?php echo e($u['full_name']); ?></strong><br><small style="color: var(--text-muted);">@<?php echo e($u['username']); ?></small></td>
                <td><?php echo e($u['email']); ?></td>
                <td><?php echo e($u['phone']); ?></td>
                <td><small><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($u['created_at'])); ?></small></td>
                <td>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="/bolakausa/admin/users?action=approve&id=<?php echo $u['id']; ?>" onclick="return confirm('Approve this user?')" class="btn btn-green" style="padding: 0.5rem 1rem; font-size: 0.8rem;"><i class="fas fa-check"></i> Approve</a>
                        <a href="/bolakausa/admin/users?action=suspend&id=<?php echo $u['id']; ?>" class="btn btn-red" style="padding: 0.5rem 1rem; font-size: 0.8rem;"><i class="fas fa-ban"></i> Reject</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h3 style="font-weight: 800; color: var(--secondary); margin-bottom: 1rem;"><i class="fas fa-users" style="color: #3b82f6;"></i> All Wholesale Users</h3>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($all_users as $u): ?>
            <tr>
                <td style="font-weight: 800; color: var(--text-muted);">#<?php echo $u['id']; ?></td>
                <td><strong style="color: var(--secondary);"><?php echo e($u['username']); ?></strong></td>
                <td>
                    <?php 
                        $status_bg = 'rgba(15,23,42,0.1)'; $status_color = 'var(--secondary)';
                        if ($u['status'] === 'active') { $status_bg = 'rgba(16,185,129,0.1)'; $status_color = 'var(--primary)'; }
                        if ($u['status'] === 'pending') { $status_bg = 'rgba(245,158,11,0.1)'; $status_color = '#f59e0b'; }
                        if ($u['status'] === 'suspended') { $status_bg = 'rgba(244,63,94,0.1)'; $status_color = 'var(--accent)'; }
                    ?>
                    <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; text-transform: uppercase;">
                        <?php echo $u['status']; ?>
                    </span>
                </td>
                <td>
                    <div style="display: flex; gap: 0.5rem;">
                        <?php if ($u['status'] !== 'active'): ?>
                            <a href="/bolakausa/admin/users?action=approve&id=<?php echo $u['id']; ?>" class="btn btn-green" style="padding: 0.5rem 1rem; font-size: 0.8rem;"><i class="fas fa-check"></i> Activate</a>
                        <?php endif; ?>
                        <?php if ($u['status'] !== 'suspended'): ?>
                            <a href="/bolakausa/admin/users?action=suspend&id=<?php echo $u['id']; ?>" class="btn btn-red" style="padding: 0.5rem 1rem; font-size: 0.8rem;" onclick="return confirm('Suspend user?')"><i class="fas fa-ban"></i> Suspend</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
