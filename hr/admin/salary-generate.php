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

$selectedMonth = $_GET['month'] ?? '';
$selectedWorkingDays = (int)($_GET['working_days'] ?? 26);

$offices = getOfficeList();
$departments = getDepartmentList($filter['office']);
$units = getUnitList($filter['department']);
$positions = getPositionList($filter['department']);

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }
    $conn = getDBConnection();

    if (isset($_POST['generate_salary'])) {
        $month = $_POST['month'];
        $workingDays = (int)$_POST['working_days'];

        if ($workingDays < 1 || $workingDays > 31) {
            $message = 'Working days must be between 1 and 31.';
            $messageType = 'danger';
        } else {
            $employees = getAllEmployees(array_merge($filter, ['status' => 'Active']));

            $successCount = 0;
            $skipCount = 0;
            $updateCount = 0;

            foreach ($employees as $emp) {
                $employeeId = $emp['id'];
                $presentDays = max(0, min(31, (int)($_POST["present_{$employeeId}"] ?? 0)));
                $leaveDays   = max(0, min(31, (int)($_POST["leave_{$employeeId}"] ?? 0)));

                // Clamp total attendance to working days
                if ($presentDays + $leaveDays > $workingDays) {
                    $leaveDays = max(0, $workingDays - $presentDays);
                }
                $absentDays = max(0, $workingDays - $presentDays - $leaveDays);

                $basicSalary = isset($_POST["salary_{$employeeId}"]) && $_POST["salary_{$employeeId}"] !== ''
                    ? (float)$_POST["salary_{$employeeId}"]
                    : $emp['basic_salary'];

                $pfPercentage = isset($_POST["pf_{$employeeId}"]) && $_POST["pf_{$employeeId}"] !== ''
                    ? (float)$_POST["pf_{$employeeId}"]
                    : ($emp['pf_percentage'] > 0 ? $emp['pf_percentage'] : 5.00);
                $pfPercentage = max(0, min(100, $pfPercentage));

                $bonus = isset($_POST["bonus_{$employeeId}"]) && $_POST["bonus_{$employeeId}"] !== ''
                    ? max(0, (float)$_POST["bonus_{$employeeId}"])
                    : ($bonusesByEmployee[$employeeId] ?? 0.0);

                $calc = calculateSalary($basicSalary, $workingDays, $presentDays, $leaveDays, $pfPercentage);

                $checkStmt = $conn->prepare("SELECT id, confirmed FROM salary_sheets WHERE employee_id = ? AND month = ?");
                $checkStmt->bind_param("is", $employeeId, $month);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $exists = $checkResult->num_rows > 0;
                $existing = $exists ? $checkResult->fetch_assoc() : null;
                $checkStmt->close();

                // Skip only confirmed entries — allow $0 salary records to be saved
                if ($existing && $existing['confirmed'] == 1) {
                    $skipCount++;
                    continue;
                }

                $netWithBonus = $calc['net_payable'] + $bonus;

                if ($exists) {
                    $stmt = $conn->prepare("UPDATE salary_sheets SET
                        working_days = ?, present_days = ?, absent_days = ?, leave_days = ?,
                        basic_salary = ?, pf_percentage = ?, pf_deduction = ?,
                        bonus = ?, gross_salary = ?, net_payable = ?
                        WHERE employee_id = ? AND month = ?");
                    $stmt->bind_param("iiiiddddddis",
                        $workingDays, $presentDays, $absentDays, $leaveDays,
                        $basicSalary, $pfPercentage, $calc['pf_deduction'],
                        $bonus, $calc['gross_salary'], $netWithBonus,
                        $employeeId, $month
                    );
                } else {
                    $stmt = $conn->prepare("INSERT INTO salary_sheets (
                        employee_id, month, working_days, present_days, absent_days, leave_days,
                        basic_salary, pf_percentage, pf_deduction, bonus, gross_salary, net_payable
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isiiiddddddi",
                        $employeeId, $month, $workingDays, $presentDays, $absentDays, $leaveDays,
                        $basicSalary, $pfPercentage, $calc['pf_deduction'],
                        $bonus, $calc['gross_salary'], $netWithBonus
                    );
                }

                if ($stmt->execute()) {
                    $exists ? $updateCount++ : $successCount++;
                }
                $stmt->close();
            }

            $message = "Salary generated: $successCount new, $updateCount updated, $skipCount skipped";
            if ($successCount == 0 && $updateCount == 0 && $skipCount > 0) {
                $messageType = 'warning';
            }
            logActivity('create', 'salary', null, "Salary generated for $month: $successCount new, $updateCount updated");
        } // end working days validation
    }
    
    if (isset($_POST['confirm_all'])) {
        $month = $_POST['month'];
        $adminId = $_SESSION['admin_id'];
        
        $stmt = $conn->prepare("UPDATE salary_sheets SET confirmed = 1, confirmed_by = ?, confirmed_at = NOW() WHERE month = ? AND confirmed = 0");
        $stmt->bind_param("is", $adminId, $month);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        logActivity('confirm', 'salary', null, "Confirmed all salary for $month: $affected entries");
        $message = "$affected salary(s) confirmed successfully";
    }
    
    if (isset($_POST['confirm_single'])) {
        $salaryId = (int)$_POST['salary_id'];
        $adminId = $_SESSION['admin_id'];
        
        $stmt = $conn->prepare("UPDATE salary_sheets SET confirmed = 1, confirmed_by = ?, confirmed_at = NOW() WHERE id = ? AND confirmed = 0");
        $stmt->bind_param("ii", $adminId, $salaryId);
        $stmt->execute();
        $stmt->close();
        
        logActivity('confirm', 'salary', $salaryId, "Confirmed salary entry");
        $message = "Salary confirmed successfully";
    }
}

$employees = !empty($selectedMonth) ? getAllEmployees(array_merge($filter, ['status' => 'Active'])) : [];
$sheetsByEmployee = [];
if (!empty($selectedMonth)) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT employee_id, confirmed, present_days, leave_days, basic_salary, pf_percentage, bonus FROM salary_sheets WHERE month = ?");
    $stmt->bind_param("s", $selectedMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $sheetsByEmployee[$row['employee_id']] = $row;
    }
    $stmt->close();
}

$bonusesByEmployee = [];
if (!empty($selectedMonth) && !empty($employees)) {
    $empIds = array_column($employees, 'id');
    $bonusesByEmployee = getBatchBonusesForMonth($empIds, $selectedMonth);
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
                <label class="form-label">Month <span class="text-danger">*</span></label>
                <select name="month" class="form-select" required>
                    <option value="">-- Select Month --</option>
                    <?php $salaryMonths = getSalaryMonths(); ?>
                    <?php foreach (generateMonthOptions() as $opt): ?>
                        <?php if (in_array($opt['value'], $salaryMonths)): ?>
                            <option value="<?php echo $opt['value']; ?>" <?php echo $selectedMonth === $opt['value'] ? 'selected' : ''; ?>>
                                <?php echo $opt['label']; ?> (Existing)
                            </option>
                        <?php else: ?>
                            <option value="<?php echo $opt['value']; ?>" <?php echo $selectedMonth === $opt['value'] ? 'selected' : ''; ?>>
                                <?php echo $opt['label']; ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Working Days</label>
                <input type="number" name="working_days" class="form-control" value="<?php echo $selectedWorkingDays; ?>" min="1" max="31">
            </div>
            <div class="col-md-3">
                <label class="form-label">Office</label>
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
                <label class="form-label">Department</label>
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

<?php if (empty($selectedMonth)): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-calendar-check fs-1 d-block mb-3"></i>
            <h5>Select a Month</h5>
            <p>Please select a month above to view employees and generate salary</p>
        </div>
    </div>
<?php elseif (empty($employees)): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-people fs-1 d-block mb-2"></i>
            No active employees found with the selected filters.
        </div>
    </div>
<?php else: ?>
    <?php
    $allConfirmed = true;
    $anyConfirmed = false;
    foreach ($employees as $emp) {
        if (isset($sheetsByEmployee[$emp['id']])) {
            if ($sheetsByEmployee[$emp['id']]['confirmed'] == 0) $allConfirmed = false;
            $anyConfirmed = true;
        } else {
            $allConfirmed = false;
        }
    }
    ?>

    <?php if ($anyConfirmed && $allConfirmed): ?>
        <div class="alert alert-info d-flex align-items-center mb-4">
            <i class="bi bi-lock-fill me-2"></i>
            <strong>This month's salary is locked.</strong> All entries have been confirmed.
        </div>
    <?php elseif ($anyConfirmed): ?>
        <div class="alert alert-warning d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Partial confirmation.</strong> Some entries confirmed, others pending.
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php echo csrfField(); ?>
        <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
        <input type="hidden" name="working_days" value="<?php echo $selectedWorkingDays; ?>">

        <?php if (!empty($filter['office'])): ?>
            <input type="hidden" name="office" value="<?php echo htmlspecialchars($filter['office']); ?>">
        <?php endif; ?>
        <?php if (!empty($filter['department'])): ?>
            <input type="hidden" name="department" value="<?php echo htmlspecialchars($filter['department']); ?>">
        <?php endif; ?>
        <?php if (!empty($filter['unit'])): ?>
            <input type="hidden" name="unit" value="<?php echo htmlspecialchars($filter['unit']); ?>">
        <?php endif; ?>
        <?php if (!empty($filter['position'])): ?>
            <input type="hidden" name="position" value="<?php echo htmlspecialchars($filter['position']); ?>">
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Employees (<?php echo count($employees); ?>)</h5>
                <?php if (!$allConfirmed): ?>
                    <button type="submit" name="generate_salary" class="btn btn-success">
                        <i class="bi bi-check2-circle me-1"></i> Generate Salary
                    </button>
                <?php endif; ?>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th rowspan="2" class="align-middle" style="min-width:150px;max-width:200px;">Employee</th>
                            <th colspan="4" class="text-center">Attendance</th>
                            <th colspan="5" class="text-center">Salary Details</th>
                            <th rowspan="2" class="align-middle text-end">Net Payable</th>
                            <th rowspan="2" class="align-middle text-center">Status</th>
                        </tr>
                        <tr>
                            <th>Present</th>
                            <th>Leave</th>
                            <th>Absent</th>
                            <th>Working</th>
                            <th>Basic</th>
                            <th>PF %</th>
                            <th>Bonus%</th>
                            <th>Bonus Amt</th>
                            <th>Gross</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <?php
                            $sheetData = $sheetsByEmployee[$emp['id']] ?? null;
                            $isConfirmed = $sheetData && $sheetData['confirmed'] == 1;
                            $present = isset($_POST["present_{$emp['id']}"]) 
                                ? (int)$_POST["present_{$emp['id']}"] 
                                : ($sheetData ? (int)$sheetData['present_days'] : 0);
                            $leave = isset($_POST["leave_{$emp['id']}"]) 
                                ? (int)$_POST["leave_{$emp['id']}"] 
                                : ($sheetData ? (int)$sheetData['leave_days'] : 0);
                            $absent = $selectedWorkingDays - $present - $leave;
                            if ($absent < 0) $absent = 0;
                            $salary = isset($_POST["salary_{$emp['id']}"]) && $_POST["salary_{$emp['id']}"] !== '' 
                                ? (float)$_POST["salary_{$emp['id']}"] 
                                : ($sheetData ? (float)$sheetData['basic_salary'] : $emp['basic_salary']);
                            $pf = isset($_POST["pf_{$emp['id']}"]) && $_POST["pf_{$emp['id']}"] !== ''
                                ? (float)$_POST["pf_{$emp['id']}"]
                                : ($sheetData ? (float)$sheetData['pf_percentage'] : ($emp['pf_percentage'] > 0 ? $emp['pf_percentage'] : 5.00));
                            
                            if ($present > 0 || $leave > 0) {
                                $calc = calculateSalary($salary, $selectedWorkingDays, $present, $leave, $pf);
                            } else {
                                $calc = ['gross_salary' => 0, 'pf_deduction' => 0, 'net_payable' => 0];
                            }
                            ?>
                            <tr class="<?php echo $isConfirmed ? 'table-success' : ''; ?>">
                                <td style="min-width:150px;max-width:200px;">
                                    <span class="badge bg-dark d-inline-block mb-1" style="font-size:10px;">
                                        <?php echo generateEmployeeID($emp['id'], $emp['office_code'], $emp['dept_code']); ?>
                                    </span>
                                    <div style="font-size:12px;font-weight:600;line-height:1.3;"><?php echo htmlspecialchars($emp['emp_name']); ?></div>
                                    <div class="text-muted" style="font-size:10px;line-height:1.3;">
                                        <?php echo htmlspecialchars($emp['department']); ?><br>
                                        <?php echo htmlspecialchars($emp['position']); ?> · <?php echo htmlspecialchars($emp['office_name']); ?>
                                    </div>
                                </td>
                                <td style="width: 80px;">
                                    <input type="number" name="present_<?php echo $emp['id']; ?>" 
                                           class="form-control form-control-sm calc-present" 
                                           value="<?php echo $present; ?>" min="0" max="31"
                                           <?php echo $isConfirmed ? 'readonly' : ''; ?>>
                                </td>
                                <td style="width: 80px;">
                                    <input type="number" name="leave_<?php echo $emp['id']; ?>" 
                                           class="form-control form-control-sm calc-leave" 
                                           value="<?php echo $leave; ?>" min="0" max="31"
                                           <?php echo $isConfirmed ? 'readonly' : ''; ?>>
                                </td>
                                <td style="width: 80px;">
                                    <input type="number" class="form-control form-control-sm calc-absent" 
                                           value="<?php echo $absent; ?>" readonly>
                                </td>
                                <td style="width: 80px;">
                                    <input type="number" class="form-control form-control-sm calc-working" 
                                           value="<?php echo $selectedWorkingDays; ?>" readonly>
                                </td>
                                <td style="width: 120px;">
                                    <input type="number" name="salary_<?php echo $emp['id']; ?>" 
                                           class="form-control form-control-sm calc-salary" 
                                           value="<?php echo $salary; ?>" step="0.01" min="0"
                                           <?php echo $isConfirmed ? 'readonly' : ''; ?>>
                                </td>
                                <td style="width: 90px;">
                                    <input type="number" name="pf_<?php echo $emp['id']; ?>"
                                           class="form-control form-control-sm calc-pf"
                                           value="<?php echo $pf; ?>" step="0.01" min="0" max="100"
                                           <?php echo $isConfirmed ? 'readonly' : ''; ?>>
                                </td>
                                <?php
                                $bonusAmt = isset($sheetsByEmployee[$emp['id']]) ? (float)($sheetsByEmployee[$emp['id']]['bonus'] ?? 0) : ($bonusesByEmployee[$emp['id']] ?? 0);
                                $bonusPct = ($salary > 0 && $bonusAmt > 0) ? round($bonusAmt / $salary * 100, 2) : 0;
                                ?>
                                <td style="width: 70px;">
                                    <input type="number" class="form-control form-control-sm calc-bonus-pct"
                                           value="<?php echo $bonusPct; ?>" step="0.01" min="0" max="100"
                                           <?php echo $isConfirmed ? 'readonly' : ''; ?>>
                                </td>
                                <td style="width: 95px;">
                                    <input type="number" name="bonus_<?php echo $emp['id']; ?>"
                                           class="form-control form-control-sm calc-bonus"
                                           value="<?php echo $bonusAmt; ?>"
                                           step="0.01" min="0"
                                           <?php echo $isConfirmed ? 'readonly' : ''; ?>>
                                </td>
                                <td class="text-end"><?php echo formatCurrency($calc['gross_salary']); ?></td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($calc['net_payable'] + (isset($sheetsByEmployee[$emp['id']]) ? (float)($sheetsByEmployee[$emp['id']]['bonus'] ?? 0) : ($bonusesByEmployee[$emp['id']] ?? 0))); ?></td>
                                <td class="text-center">
                                    <?php if ($isConfirmed): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-lock-fill me-1"></i>Confirmed
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Formula: Net Payable = (Basic / Working Days) × (Present + Leave) − PF Deduction + Bonus<br>
                    Absent = Working Days - Present - Leave
                </small>
                <div>
                    <?php if (!$allConfirmed): ?>
                        <button type="submit" name="generate_salary" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i> Generate Salary
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>

<script>
document.addEventListener('input', function(e) {
    const calcClasses = ['calc-present','calc-leave','calc-salary','calc-pf','calc-bonus','calc-bonus-pct'];
    if (!calcClasses.some(c => e.target.classList.contains(c))) return;

    let row = e.target.closest('tr');
    if (!row) return;

    let workingInput  = row.querySelector('.calc-working');
    let presentInput  = row.querySelector('.calc-present');
    let leaveInput    = row.querySelector('.calc-leave');
    let absentInput   = row.querySelector('.calc-absent');
    let salaryInput   = row.querySelector('.calc-salary');
    let pfInput       = row.querySelector('.calc-pf');
    let bonusInput    = row.querySelector('.calc-bonus');
    let bonusPctInput = row.querySelector('.calc-bonus-pct');

    let workingDays = parseInt(workingInput.value) || 0;
    let present     = parseInt(presentInput.value) || 0;
    let leave       = parseInt(leaveInput.value) || 0;
    let salary      = parseFloat(salaryInput.value) || 0;
    let pf          = parseFloat(pfInput.value) || 0;

    // Bonus % <-> Bonus Amt bidirectional sync
    if (e.target.classList.contains('calc-bonus-pct')) {
        let pct = parseFloat(bonusPctInput.value) || 0;
        bonusInput.value = salary > 0 ? (salary * pct / 100).toFixed(2) : '0.00';
    } else if (e.target.classList.contains('calc-bonus')) {
        let amt = parseFloat(bonusInput.value) || 0;
        bonusPctInput.value = salary > 0 ? (amt / salary * 100).toFixed(2) : '0.00';
    } else if (e.target.classList.contains('calc-salary')) {
        // When salary changes, keep bonus% and recalculate bonus amount
        let pct = parseFloat(bonusPctInput.value) || 0;
        if (pct > 0) {
            bonusInput.value = (salary * pct / 100).toFixed(2);
        }
    }

    let bonus = parseFloat(bonusInput.value) || 0;

    // Attendance
    let totalDays = present + leave;
    let absent = Math.max(0, workingDays - totalDays);
    absentInput.value = absent;

    // Clear all validation styles
    [presentInput, leaveInput, absentInput, salaryInput, pfInput, workingInput].forEach(el => {
        el.classList.remove('border-warning', 'border-danger');
        el.style.backgroundColor = '';
    });

    // Validate
    if (present < 0 || present > 31)  presentInput.classList.add('border-warning');
    if (leave < 0 || leave > 31)      leaveInput.classList.add('border-warning');
    if (salary < 0)                   salaryInput.classList.add('border-warning');
    if (pf < 0 || pf > 100)          pfInput.classList.add('border-warning');

    if (totalDays > workingDays) {
        presentInput.classList.add('border-warning');
        leaveInput.classList.add('border-warning');
        workingInput.classList.add('border-danger');
        workingInput.style.backgroundColor = '#ffd7d7';
    }

    // Recalculate gross / net
    if (workingDays > 0 && (present > 0 || leave > 0)) {
        let gross       = (salary / workingDays) * (present + leave);
        let pfDeduction = (gross * pf) / 100;
        let net         = gross - pfDeduction + bonus;

        // columns: [Employee, Present, Leave, Absent, Working, Basic, PF%, BonusPct, BonusAmt, Gross, Net, Status]
        let grossCell = row.cells[row.cells.length - 3];
        let netCell   = row.cells[row.cells.length - 2];

        if (grossCell) grossCell.textContent = gross.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        if (netCell)   netCell.textContent   = net.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
});
</script>

<style>
.border-warning { border: 2px solid #ffc107 !important; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>