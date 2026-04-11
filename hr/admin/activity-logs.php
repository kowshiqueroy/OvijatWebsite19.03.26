<?php
/**
 * Activity Logs Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Activity Logs';
$currentPage = 'activity-logs';

$today = date('Y-m-d');

$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'action' => $_GET['action'] ?? '',
    'entity_type' => $_GET['entity_type'] ?? '',
    'from_date' => $_GET['from_date'] ?? $today,
    'to_date' => $_GET['to_date'] ?? $today
];

$perPage = 50;
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$totalLogs = countActivityLogs($filters);
$totalPages = $totalLogs > 0 ? (int)ceil($totalLogs / $perPage) : 1;
$logs = getActivityLogs($filters, $perPage, ($pageNum - 1) * $perPage);

$conn = getDBConnection();
$users = [];
$userResult = $conn->query("SELECT id, username FROM admin ORDER BY username");
while ($row = $userResult->fetch_assoc()) {
    $users[] = $row;
}
$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1"><i class="bi bi-clock-history me-2"></i>Activity Logs</h4>
        <small class="text-muted">Track all user activities and system changes</small>
    </div>
</div>

<form method="GET" action="" class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">User</label>
                <select name="user_id" class="form-select">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filters['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Action</label>
                <select name="action" class="form-select">
                    <option value="">All Actions</option>
                    <option value="create" <?php echo $filters['action'] === 'create' ? 'selected' : ''; ?>>Create</option>
                    <option value="update" <?php echo $filters['action'] === 'update' ? 'selected' : ''; ?>>Update</option>
                    <option value="delete" <?php echo $filters['action'] === 'delete' ? 'selected' : ''; ?>>Delete</option>
                    <option value="login" <?php echo $filters['action'] === 'login' ? 'selected' : ''; ?>>Login</option>
                    <option value="logout" <?php echo $filters['action'] === 'logout' ? 'selected' : ''; ?>>Logout</option>
                    <option value="confirm" <?php echo $filters['action'] === 'confirm' ? 'selected' : ''; ?>>Confirm</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Entity Type</label>
                <select name="entity_type" class="form-select">
                    <option value="">All Types</option>
                    <option value="employee" <?php echo $filters['entity_type'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                    <option value="salary" <?php echo $filters['entity_type'] === 'salary' ? 'selected' : ''; ?>>Salary</option>
                    <option value="bonus" <?php echo $filters['entity_type'] === 'bonus' ? 'selected' : ''; ?>>Bonus</option>
                    <option value="user" <?php echo $filters['entity_type'] === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="settings" <?php echo $filters['entity_type'] === 'settings' ? 'selected' : ''; ?>>Settings</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?php echo $filters['from_date']; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?php echo $filters['to_date']; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Filter</button>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Activity Logs (<?php echo $totalLogs; ?>)</h5>
        <?php if ($totalLogs > 0): ?>
            <a href="activity-logs.php" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No activity logs found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                                <td>
                                    <?php 
                                    $actionColors = [
                                        'create' => 'success',
                                        'update' => 'primary',
                                        'delete' => 'danger',
                                        'login' => 'info',
                                        'logout' => 'secondary',
                                        'confirm' => 'warning'
                                    ];
                                    $color = $actionColors[$log['action']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($log['action']); ?></span>
                                </td>
                                <td>
                                    <?php if ($log['entity_id']): ?>
                                        <?php echo ucfirst($log['entity_type']); ?> #<?php echo $log['entity_id']; ?>
                                    <?php else: ?>
                                        <?php echo ucfirst($log['entity_type']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['details'] ?? '-'); ?></td>
                                <td><small class="text-muted"><?php echo $log['ip_address']; ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer">
        <?php
        $baseUrl = 'activity-logs.php?' . http_build_query(array_filter([
            'user_id' => $filters['user_id'],
            'action' => $filters['action'],
            'entity_type' => $filters['entity_type'],
            'from_date' => $filters['from_date'],
            'to_date' => $filters['to_date']
        ]));
        renderPagination($pageNum, $totalPages, $baseUrl);
        ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>