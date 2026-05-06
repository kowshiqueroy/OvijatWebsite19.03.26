<?php
require_once '../../templates/header.php';
check_login();
check_role(ROLE_ADMIN);

$user_id = $_GET['id'] ?? 0;
$user = fetch_one("SELECT username FROM users WHERE id = ?", [$user_id]);

if (!$user) {
    redirect('modules/users/index.php', 'User not found.', 'danger');
}

$logs = fetch_all("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC", [$user_id]);
?>

<div class="row mb-4">
    <div class="col-12">
        <h3>Activity Logs for: <?php echo $user['username']; ?></h3>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="2" class="text-center">No logs found for this user.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?></td>
                        <td><?php echo $log['action']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="index.php" class="btn btn-secondary">Back to Users</a>
</div>

<?php require_once '../../templates/footer.php'; ?>
