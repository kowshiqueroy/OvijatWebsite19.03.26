<?php
/**
 * Admin Audit Logs Viewer
 */
restrict_to(['admin']);

$filter_action = $_GET['action'] ?? '';

// Fetch Logs
$query = "SELECT l.*, u.username FROM system_logs l LEFT JOIN users u ON l.user_id = u.id";
if ($filter_action) {
    $query .= " WHERE l.action_type LIKE " . $pdo->quote("%$filter_action%");
}
$query .= " ORDER BY l.timestamp DESC LIMIT 100";
$logs = $pdo->query($query)->fetchAll();

// Get unique actions for filter
$actions = $pdo->query("SELECT DISTINCT action_type FROM system_logs ORDER BY action_type ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="section-title">
    <i class="fas fa-history" style="color: var(--primary);"></i>
    System Audit Logs
</div>

<form method="GET" class="card" style="margin-bottom: 2rem; padding: 1.5rem; display: flex; align-items: center; gap: 1rem;">
    <strong style="color: var(--secondary);"><i class="fas fa-filter"></i> Filter by Action:</strong>
    <select name="action" onchange="this.form.submit()" style="padding: 0.5rem; border-radius: 8px; border: 1px solid var(--border-light); width: auto;">
        <option value="">All Actions</option>
        <?php foreach ($actions as $act): ?>
            <option value="<?php echo e($act); ?>" <?php echo ($filter_action === $act) ? 'selected' : ''; ?>><?php echo e($act); ?></option>
        <?php endforeach; ?>
    </select>
    <a href="/bolakausa/admin/logs" class="btn btn-blue" style="padding: 0.5rem 1rem;">Clear</a>
</form>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>Action</th>
                <th>Old Value</th>
                <th>New Value</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td style="white-space: nowrap;"><small style="color: var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('M d, H:i', strtotime($log['timestamp'])); ?></small></td>
                <td><strong style="color: var(--secondary);"><?php echo e($log['username'] ?? 'System'); ?></strong></td>
                <td><span style="background: rgba(15,23,42,0.05); padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 700;"><?php echo e($log['action_type']); ?></span></td>
                <td><small style="color: var(--text-muted); font-family: monospace;"><?php echo e($log['old_value'] ?? '-'); ?></small></td>
                <td><small style="color: var(--primary); font-weight: 600; font-family: monospace;"><?php echo e($log['new_value'] ?? '-'); ?></small></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
