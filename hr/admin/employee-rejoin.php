<?php
/**
 * Employee Rejoin Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$employee = getEmployeeById($id);

if (!$employee || ($employee['status'] !== 'Resigned' && $employee['status'] !== 'Terminated')) {
    header('Location: employees.php');
    exit;
}

$pageTitle = 'Rejoin Employee';
$currentPage = 'employees';

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }
    
    $rejoinDate = $_POST['rejoin_date'];
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE employees SET status = 'Active', joining_date = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $rejoinDate, $id);
    
    if ($stmt->execute()) {
        logEmploymentHistory($id, 'Rejoined', $rejoinDate, $remarks);
        logActivity('update', 'employee', $id, "Employee rejoined on $rejoinDate. Remarks: $remarks");
        
        header('Location: employees.php?msg=rejoined');
        exit;
    } else {
        $message = 'Error updating record: ' . $conn->error;
        $messageType = 'danger';
    }
    $stmt->close();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">Rejoin Employee</h4>
        <small class="text-muted">Process re-employment for <?php echo htmlspecialchars($employee['emp_name']); ?></small>
    </div>
    <div>
        <a href="employees.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Rejoin Details</h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <?php showAlert($message, $messageType); ?>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <strong>Current Status:</strong> <?php echo $employee['status']; ?><br>
                    <strong>Last Joining Date:</strong> <?php echo $employee['joining_date']; ?>
                </div>
                
                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <div class="mb-3">
                        <label class="form-label">New Rejoin Date *</label>
                        <input type="date" name="rejoin_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remarks / Reason</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Enter reason for rejoining..."></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-1"></i> Confirm Rejoin
                        </button>
                        <a href="employees.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Employment History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $history = getEmploymentHistory($id);
                            if (empty($history)): ?>
                                <tr><td colspan="3" class="text-center py-3 text-muted">No history found</td></tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($h['event_type']); ?>">
                                                <?php echo $h['event_type']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $h['event_date']; ?></td>
                                        <td><small><?php echo htmlspecialchars($h['remarks']); ?></small></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>