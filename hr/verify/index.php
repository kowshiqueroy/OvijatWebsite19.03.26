<?php
/**
 * Employee ID Verification Page
 * Core PHP Employee Management System
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = $_GET['id'] ?? null;
$employee = null;
$error = '';

if ($id) {
    $employee = getEmployeeById((int)$id);
    if (!$employee) {
        $error = 'Employee not found';
    }
} else {
    $error = 'Invalid ID';
}

$companyName = getSetting('company_name') ?? 'HR System';
$companyLogo = getSetting('company_logo') ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Employee - <?php echo htmlspecialchars($companyName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
        }
        .verify-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 450px;
        }
        .status-active { color: #28a745; }
        .status-inactive { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="verify-card p-4">
                    <?php if ($error): ?>
                        <div class="text-center">
                            <i class="bi bi-x-circle text-danger" style="font-size: 48px;"></i>
                            <h4 class="mt-3 text-danger">Invalid Employee</h4>
                            <p class="text-muted"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    <?php elseif ($employee): ?>
                        <div class="text-center mb-3">
                            <?php if (!empty($companyLogo)): ?>
                                <img src="uploads/<?php echo htmlspecialchars($companyLogo); ?>" style="height: 50px;">
                            <?php endif; ?>
                            <h4 class="mt-2"><?php echo htmlspecialchars($companyName); ?></h4>
                        </div>
                        <hr>
                        <div class="text-center mb-4">
                            <?php if (!empty($employee['photo'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($employee['photo']); ?>" 
                                     alt="Photo" style="width: 120px; height: 130px; border-radius: 8px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light d-inline-flex align-items-center justify-content-center" 
                                     style="width: 120px; height: 130px; border-radius: 8px;">
                                    <i class="bi bi-person text-muted" style="font-size: 48px;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <table class="table table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 40%;">Name</td>
                                <td class="fw-bold"><?php echo htmlspecialchars($employee['emp_name']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Employee ID</td>
                                <td class="fw-bold"><?php echo generateEmployeeID($employee['id'], $employee['office_code'], $employee['dept_code']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Department</td>
                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Position</td>
                                <td><?php echo htmlspecialchars($employee['position']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status</td>
                                <td>
                                    <?php if ($employee['status'] === 'Active'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i><?php echo htmlspecialchars($employee['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Joining Date</td>
                                <td><?php echo !empty($employee['joining_date']) ? date('d M Y', strtotime($employee['joining_date'])) : 'N/A'; ?></td>
                            </tr>
                        </table>
                        <div class="text-center mt-4">
                            <?php if ($employee['status'] === 'Active'): ?>
                                <i class="bi bi-check-circle text-success" style="font-size: 36px;"></i>
                                <h5 class="text-success mt-2">Verified Employee</h5>
                            <?php else: ?>
                                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 36px;"></i>
                                <h5 class="text-warning mt-2">Employee Status: <?php echo htmlspecialchars($employee['status']); ?></h5>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="text-center mt-4 text-muted">
                        <small>Powered by HR System</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>