<?php
/**
 * System Logs Viewer
 */
$pageTitle = 'Audit Logs';
require_once 'layout_header.php';

$db = Database::getInstance();
$logs = $db->query("SELECT l.*, u.email as admin_email 
                    FROM system_logs l 
                    LEFT JOIN users u ON l.admin_id = u.id 
                    ORDER BY l.created_at DESC LIMIT 100")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>System Audit Trail</h1>
</div>

<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Date / Time</th>
                <th>Admin</th>
                <th>Action</th>
                <th>Target</th>
                <th>IP Address</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $l): ?>
            <tr>
                <td><small style="font-weight:700;"><?php echo date('M d, H:i:s', strtotime($l['created_at'])); ?></small></td>
                <td><strong><?php echo htmlspecialchars($l['admin_email'] ?: 'System'); ?></strong></td>
                <td><span class="badge" style="background:#edf2f7; color:var(--primary);"><?php echo strtoupper($l['action']); ?></span></td>
                <td><small><?php echo $l['target_type']; ?> #<?php echo $l['target_id']; ?></small></td>
                <td><code><?php echo $l['ip_address']; ?></code></td>
                <td><div style="max-width:300px; overflow:hidden; text-overflow:ellipsis; font-size:0.75rem;"><?php echo htmlspecialchars($l['details']); ?></div></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'layout_footer.php'; ?>
