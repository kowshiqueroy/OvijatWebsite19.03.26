<?php
/**
 * Salary List Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Salary Sheets';
$currentPage = 'salary';

$filter = [
    'office' => $_GET['office'] ?? '',
    'department' => $_GET['department'] ?? '',
    'unit' => $_GET['unit'] ?? '',
    'position' => $_GET['position'] ?? ''
];

$selectedMonth = $_GET['month'] ?? '';
$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;

$sheets = getSalarySheets($employeeId, $selectedMonth, $filter);
$months = getMonthList();
$offices = getOfficeList();
$departments = getDepartmentList($filter['office']);

$monthStats = null;
if ($selectedMonth) {
    $monthStats = getSalaryStats($selectedMonth);
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM salary_sheets WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header('Location: salary-list.php?msg=deleted');
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = 'Salary sheet deleted successfully';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h4 class="mb-1">Salary Sheets</h4>
        <small class="text-muted">View and manage generated salary sheets</small>
    </div>
    <div>
        <a href="salary-generate.php" class="btn btn-primary">
            <i class="bi bi-calculator me-1"></i> Generate Salary
        </a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <?php showAlert($message); ?>
<?php endif; ?>

<?php if ($monthStats && $monthStats['total_employees'] > 0): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card primary">
                <div class="card-body py-3">
                    <small class="text-muted">Total Employees</small>
                    <h4 class="mb-0"><?php echo $monthStats['total_employees']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card success">
                <div class="card-body py-3">
                    <small class="text-muted">Total Gross</small>
                    <h4 class="mb-0">$<?php echo formatCurrency($monthStats['total_gross'] ?? 0); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card warning">
                <div class="card-body py-3">
                    <small class="text-muted">Total PF</small>
                    <h4 class="mb-0">$<?php echo formatCurrency($monthStats['total_pf'] ?? 0); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card danger">
                <div class="card-body py-3">
                    <small class="text-muted">Total Net Payable</small>
                    <h4 class="mb-0">$<?php echo formatCurrency($monthStats['total_payable'] ?? 0); ?></h4>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="GET" action="" class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <select name="month" class="form-select">
                    <option value="">All Months</option>
                    <?php foreach ($months as $m): ?>
                        <option value="<?php echo $m; ?>" <?php echo $selectedMonth === $m ? 'selected' : ''; ?>>
                            <?php echo date('F Y', strtotime($m . '-01')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="office" class="form-select filter-select">
                    <option value="">All Offices</option>
                    <?php foreach ($offices as $off): ?>
                        <option value="<?php echo htmlspecialchars($off['office_name']); ?>"
                            <?php echo $filter['office'] === $off['office_name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($off['office_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="department" class="form-select filter-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                            <?php echo $filter['department'] === $dept['department'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
                <a href="salary-list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i> Clear
                </a>
            </div>
            <?php if (!empty($selectedMonth) && count($sheets) > 0): ?>
                <div class="col-md-2">
                    <button type="button" class="btn btn-success w-100" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Salary Records (<?php echo count($sheets); ?>)</h5>
    </div>
    
    <?php if (empty($sheets)): ?>
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            No salary sheets found. Generate a salary sheet first.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Month</th>
                        <th>Working Days</th>
                        <th>Present</th>
                        <th>Leave</th>
                        <th>Absent</th>
                        <th>Basic Salary</th>
                        <th>PF %</th>
                        <th>PF Ded.</th>
                        <th>Gross</th>
                        <th>Net Payable</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sheets as $sheet): ?>
                        <tr>
                            <td>
                                <span class="badge bg-dark">
                                    <?php echo generateEmployeeID($sheet['id'], $sheet['office_code'], $sheet['dept_code']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($sheet['emp_name']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($sheet['department']); ?></small>
                            </td>
                            <td><?php echo date('M Y', strtotime($sheet['month'] . '-01')); ?></td>
                            <td><?php echo $sheet['working_days']; ?></td>
                            <td><?php echo $sheet['present_days']; ?></td>
                            <td><?php echo $sheet['leave_days']; ?></td>
                            <td><?php echo $sheet['absent_days']; ?></td>
                            <td>$<?php echo formatCurrency($sheet['basic_salary']); ?></td>
                            <td><?php echo $sheet['pf_percentage']; ?>%</td>
                            <td>$<?php echo formatCurrency($sheet['pf_deduction']); ?></td>
                            <td>$<?php echo formatCurrency($sheet['gross_salary']); ?></td>
                            <td class="fw-bold">$<?php echo formatCurrency($sheet['net_payable']); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="../public/profile.php?id=<?php echo $sheet['employee_id']; ?>" 
                                       class="btn btn-outline-info" title="View Profile" target="_blank">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="salary-list.php?delete=<?php echo $sheet['id']; ?>" 
                                       class="btn btn-outline-danger" title="Delete"
                                       onclick="return confirm('Are you sure?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($selectedMonth)): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="10" class="text-end">Total:</td>
                            <td>$<?php echo formatCurrency(array_sum(array_column($sheets, 'gross_salary'))); ?></td>
                            <td>$<?php echo formatCurrency(array_sum(array_column($sheets, 'net_payable'))); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
