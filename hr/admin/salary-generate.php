<?php
/**
 * Salary Generation Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Generate Salary';
$currentPage = 'salary-generate';

$filter = [
    'office' => $_GET['office'] ?? '',
    'department' => $_GET['department'] ?? '',
    'unit' => $_GET['unit'] ?? '',
    'position' => $_GET['position'] ?? ''
];

$selectedMonth = $_GET['month'] ?? date('Y-m');
$selectedWorkingDays = (int)($_GET['working_days'] ?? 26);

$employees = getAllEmployees(array_merge($filter, ['status' => 'Active']));
$offices = getOfficeList();
$departments = getDepartmentList($filter['office']);
$units = getUnitList($filter['department']);
$positions = getPositionList($filter['department']);

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_salary'])) {
    $conn = getDBConnection();
    
    $month = $_POST['month'];
    $workingDays = (int)$_POST['working_days'];
    
    $successCount = 0;
    $skipCount = 0;
    $updateCount = 0;
    
    foreach ($employees as $emp) {
        $employeeId = $emp['id'];
        $presentDays = (int)($_POST["present_{$employeeId}"] ?? 0);
        $leaveDays = (int)($_POST["leave_{$employeeId}"] ?? 0);
        $absentDays = (int)($_POST["absent_{$employeeId}"] ?? 0);
        
        $basicSalary = isset($_POST["salary_{$employeeId}"]) && $_POST["salary_{$employeeId}"] !== '' 
            ? (float)$_POST["salary_{$employeeId}"] 
            : $emp['basic_salary'];
        
        $pfPercentage = isset($_POST["pf_{$employeeId}"]) && $_POST["pf_{$employeeId}"] !== ''
            ? (float)$_POST["pf_{$employeeId}"]
            : $emp['pf_percentage'];
        
        $calc = calculateSalary($basicSalary, $workingDays, $presentDays, $leaveDays, $pfPercentage);
        
        $checkStmt = $conn->prepare("SELECT id FROM salary_sheets WHERE employee_id = ? AND month = ?");
        $checkStmt->bind_param("is", $employeeId, $month);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $exists = $checkResult->num_rows > 0;
        $checkStmt->close();
        
        if ($presentDays == 0 && $leaveDays == 0) {
            $skipCount++;
            continue;
        }
        
        if ($exists) {
            $stmt = $conn->prepare("UPDATE salary_sheets SET 
                working_days = ?, present_days = ?, absent_days = ?, leave_days = ?,
                basic_salary = ?, pf_percentage = ?, pf_deduction = ?,
                gross_salary = ?, net_payable = ?
                WHERE employee_id = ? AND month = ?");
            $stmt->bind_param("iiidddddiis", 
                $workingDays, $presentDays, $absentDays, $leaveDays,
                $basicSalary, $pfPercentage, $calc['pf_deduction'],
                $calc['gross_salary'], $calc['net_payable'],
                $employeeId, $month
            );
        } else {
            $stmt = $conn->prepare("INSERT INTO salary_sheets (
                employee_id, month, working_days, present_days, absent_days, leave_days,
                basic_salary, pf_percentage, pf_deduction, gross_salary, net_payable
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isiiidddddi", 
                $employeeId, $month, $workingDays, $presentDays, $absentDays, $leaveDays,
                $basicSalary, $pfPercentage, $calc['pf_deduction'],
                $calc['gross_salary'], $calc['net_payable']
            );
        }
        
        if ($stmt->execute()) {
            if ($exists) {
                $updateCount++;
            } else {
                $successCount++;
            }
        }
        $stmt->close();
    }
    
    $message = "Salary generated: $successCount new, $updateCount updated, $skipCount skipped (no attendance)";
    if ($successCount == 0 && $updateCount == 0 && $skipCount > 0) {
        $messageType = 'warning';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">Generate Salary</h4>
        <small class="text-muted">Create monthly salary sheets</small>
    </div>
    <div>
        <a href="salary-list.php" class="btn btn-outline-secondary">
            <i class="bi bi-list me-1"></i> View Salary Sheets
        </a>
    </div>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $messageType); ?>
<?php endif; ?>

<form method="GET" action="" class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter Employees</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Month</label>
                <input type="month" name="month" class="form-control" value="<?php echo $selectedMonth; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Working Days</label>
                <input type="number" name="working_days" class="form-control" value="<?php echo $selectedWorkingDays; ?>" min="1" max="31">
            </div>
            <div class="col-md-3">
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
            <div class="col-md-3">
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
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>
    </div>
</form>

<form method="POST" action="">
    <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
    <input type="hidden" name="working_days" value="<?php echo $selectedWorkingDays; ?>">
    
    <?php if (!empty($filter['office'])): ?>
        <input type="hidden" name="office" value="<?php echo htmlspecialchars($filter['office']); ?>">
    <?php endif; ?>
    <?php if (!empty($filter['department'])): ?>
        <input type="hidden" name="department" value="<?php echo htmlspecialchars($filter['department']); ?>">
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Employees (<?php echo count($employees); ?>)</h5>
            <button type="submit" name="generate_salary" class="btn btn-success">
                <i class="bi bi-check2-circle me-1"></i> Generate Salary
            </button>
        </div>
        
        <?php if (empty($employees)): ?>
            <div class="card-body text-center text-muted py-4">
                No active employees found with the selected filters.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th rowspan="2" class="align-middle">ID</th>
                            <th rowspan="2" class="align-middle">Name</th>
                            <th rowspan="2" class="align-middle">Department</th>
                            <th rowspan="2" class="align-middle">Position</th>
                            <th colspan="4" class="text-center">Attendance</th>
                            <th colspan="3" class="text-center">Salary Details</th>
                            <th rowspan="2" class="align-middle text-end">Net Payable</th>
                        </tr>
                        <tr>
                            <th>Present</th>
                            <th>Leave</th>
                            <th>Absent</th>
                            <th>Working</th>
                            <th>Basic</th>
                            <th>PF %</th>
                            <th>Gross</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <?php
                            $present = (int)($_POST["present_{$emp['id']}"] ?? 0);
                            $leave = (int)($_POST["leave_{$emp['id']}"] ?? 0);
                            $salary = isset($_POST["salary_{$emp['id']}"]) && $_POST["salary_{$emp['id']}"] !== '' 
                                ? (float)$_POST["salary_{$emp['id']}"] 
                                : $emp['basic_salary'];
                            $pf = isset($_POST["pf_{$emp['id']}"]) && $_POST["pf_{$emp['id']}"] !== ''
                                ? (float)$_POST["pf_{$emp['id']}"]
                                : $emp['pf_percentage'];
                            
                            if ($present > 0 || $leave > 0) {
                                $calc = calculateSalary($salary, $selectedWorkingDays, $present, $leave, $pf);
                            } else {
                                $calc = ['gross_salary' => 0, 'pf_deduction' => 0, 'net_payable' => 0];
                            }
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark">
                                        <?php echo generateEmployeeID($emp['id'], $emp['office_code'], $emp['dept_code']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($emp['emp_name']); ?></td>
                                <td><?php echo htmlspecialchars($emp['department']); ?></td>
                                <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                <td style="width: 80px;">
                                    <input type="number" name="present_<?php echo $emp['id']; ?>" 
                                           class="form-control form-control-sm" 
                                           value="<?php echo $present; ?>" min="0" max="31">
                                </td>
                                <td style="width: 80px;">
                                    <input type="number" name="leave_<?php echo $emp['id']; ?>" 
                                           class="form-control form-control-sm" 
                                           value="<?php echo $leave; ?>" min="0" max="31">
                                </td>
                                <td style="width: 80px;">
                                    <input type="number" name="absent_<?php echo $emp['id']; ?>" 
                                           class="form-control form-control-sm" 
                                           value="0" min="0" max="31" readonly>
                                </td>
                                <td style="width: 80px;">
                                    <input type="number" class="form-control form-control-sm" 
                                           value="<?php echo $selectedWorkingDays; ?>" readonly>
                                </td>
                                <td style="width: 100px;">
                                    <input type="number" name="salary_<?php echo $emp['id']; ?>" 
                                           class="form-control form-control-sm" 
                                           value="<?php echo $salary; ?>" step="0.01" min="0">
                                </td>
                                <td style="width: 70px;">
                                    <input type="number" name="pf_<?php echo $emp['id']; ?>" 
                                           class="form-control form-control-sm" 
                                           value="<?php echo $pf; ?>" step="0.01" min="0" max="100">
                                </td>
                                <td class="text-end">$<?php echo formatCurrency($calc['gross_salary']); ?></td>
                                <td class="text-end fw-bold">$<?php echo formatCurrency($calc['net_payable']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Formula: Net Payable = (Basic / Working Days) * (Present + Leave) - PF Deduction
                </small>
                <button type="submit" name="generate_salary" class="btn btn-success">
                    <i class="bi bi-check2-circle me-1"></i> Generate Salary
                </button>
            </div>
        <?php endif; ?>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
