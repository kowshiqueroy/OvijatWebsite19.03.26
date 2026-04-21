<?php
/**
 * modules/system/logs.php
 */
include '../../includes/header.php';
requireRole('Admin');

$stmt = $pdo->query("SELECT l.*, u.username FROM audit_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 100");
$logs = $stmt->fetchAll();
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white py-3">
        <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2"></i> System Audit Logs</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="bg-light">
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Ref ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="small text-muted"><?php echo formatDate($log['created_at']); ?></td>
                        <td><span class="fw-bold"><?php echo $log['username'] ?: 'System'; ?></span></td>
                        <td><span class="badge bg-secondary"><?php echo $log['action']; ?></span></td>
                        <td class="small"><?php echo $log['description']; ?></td>
                        <td><?php echo $log['reference_id'] ?: '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
