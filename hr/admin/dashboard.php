<?php
define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

$selectedOffice = $_GET['office'] ?? '';
$allOffices = getOfficeList();

$totalEmployees = getEmployeeCount(null, $selectedOffice ?: null);
$activeEmployees = getEmployeeCount('Active', $selectedOffice ?: null);
$inactiveEmployees = getEmployeeCount('Inactive', $selectedOffice ?: null);

$monthList = getSalaryMonths();
$currentMonth = date('Y-m');
$currentSalaryStats = getSalaryStats($currentMonth, $selectedOffice ?: null);
$allTimeSalaryStats = getSalaryStats(null, $selectedOffice ?: null);

$recentFilter = [];
if ($selectedOffice) {
    $recentFilter['office_name'] = $selectedOffice;
}
$recentEmployees = getAllEmployees($recentFilter, 5);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h4 class="mb-1">Dashboard</h4>
        <small class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</small>
    </div>
    <div class="d-flex align-items-center gap-3">
        <form method="GET" class="d-flex gap-2">
            <select name="office" class="form-select" onchange="this.form.submit()" style="width: 200px;">
                <option value="">All Offices</option>
                <?php foreach ($allOffices as $office): ?>
                    <option value="<?php echo htmlspecialchars($office['office_name']); ?>" <?php echo ($selectedOffice === $office['office_name']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($office['office_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="employee-add.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Employee
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Employees</h6>
                        <h3 class="mb-0"><?php echo $totalEmployees; ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                        <i class="bi bi-people text-primary fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Active Employees</h6>
                        <h3 class="mb-0"><?php echo $activeEmployees; ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded">
                        <i class="bi bi-person-check text-success fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Inactive</h6>
                        <h3 class="mb-0"><?php echo $inactiveEmployees; ?></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                        <i class="bi bi-person-x text-warning fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card danger">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">This Month Payroll</h6>
                        <h3 class="mb-0"><?php echo formatCurrency($currentSalaryStats['total_payable'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                        <i class="bi bi-currency-dollar text-danger fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Recent Employees<?php echo $selectedOffice ? ' - ' . htmlspecialchars($selectedOffice) : ''; ?></h5>
                <a href="employees.php<?php echo $selectedOffice ? '?office=' . urlencode($selectedOffice) : ''; ?>" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentEmployees)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No employees found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentEmployees as $emp): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-dark">
                                                <?php echo generateEmployeeID($emp['id'], $emp['office_code'], $emp['dept_code']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['emp_name']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['department']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($emp['status']); ?>">
                                                <?php echo $emp['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Quick Stats<?php echo $selectedOffice ? ' - ' . htmlspecialchars($selectedOffice) : ''; ?></h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted">Total Payroll (All Time)</small>
                    <h4 class="mb-0"><?php echo formatCurrency($allTimeSalaryStats['total_payable'] ?? 0); ?></h4>
                </div>
                <hr>
                <div class="mb-3">
                    <small class="text-muted">Total PF Collected</small>
                    <h4 class="mb-0 text-success"><?php echo formatCurrency($allTimeSalaryStats['total_pf'] ?? 0); ?></h4>
                </div>
                <hr>
                <div class="mb-3">
                    <small class="text-muted">Employees Processed</small>
                    <h4 class="mb-0"><?php echo $allTimeSalaryStats['total_employees'] ?? 0; ?></h4>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="employee-add.php" class="btn btn-outline-primary">
                        <i class="bi bi-person-plus me-2"></i>Add Employee
                    </a>
                    <a href="employee-import.php" class="btn btn-outline-success">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i>Import CSV
                    </a>
                    <a href="salary-generate.php" class="btn btn-outline-warning">
                        <i class="bi bi-calculator me-2"></i>Generate Salary
                    </a>
                    <a href="../public/profile.php" class="btn btn-outline-secondary" target="_blank">
                        <i class="bi bi-globe me-2"></i>View Public Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>