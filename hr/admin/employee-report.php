<?php
define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle   = 'Employee Report';
$currentPage = 'employee-report';

$conn = getDBConnection();

$companyName = getSetting('company_name') ?? 'My Company';
$companyTagline = getSetting('company_tagline') ?? '';
$companyAddress = getSetting('company_address') ?? '';
$companyPhone = getSetting('company_phone') ?? '';
$companyEmail = getSetting('company_email') ?? '';
$companyLogo = getSetting('company_logo') ?? '';

// ── Load all active employees for the selector ────────────────────────────────
$allEmployees = getAllEmployees(['status' => 'Active']);

// ── Defaults ──────────────────────────────────────────────────────────────────
$defaultFrom = date('Y-m', strtotime('-11 months'));
$defaultTo   = date('Y-m');

$submitted = isset($_GET['emp_ids']);
$empIds    = [];
$fromMonth = $_GET['from_month'] ?? $defaultFrom;
$toMonth   = $_GET['to_month']   ?? $defaultTo;
$printOption = $_GET['print_option'] ?? 'full';

if ($submitted && !empty($_GET['emp_ids'])) {
    foreach ((array)$_GET['emp_ids'] as $id) {
        $id = (int)$id;
        if ($id > 0) $empIds[] = $id;
    }
}

// ── Query helpers ─────────────────────────────────────────────────────────────
$salaryRows  = [];
$loanSummary = [];
$pfSummary   = [];
$bonusRows   = [];

if ($submitted && !empty($empIds)) {
    $pl = implode(',', array_fill(0, count($empIds), '?'));
    $ti = str_repeat('i', count($empIds));

    // Salary sheets
    $stmt = $conn->prepare(
        "SELECT ss.*, e.emp_name, e.department, e.position, e.office_code, e.dept_code
         FROM salary_sheets ss
         JOIN employees e ON ss.employee_id = e.id
         WHERE ss.employee_id IN ($pl) AND ss.month >= ? AND ss.month <= ?
         ORDER BY e.emp_name ASC, ss.month DESC"
    );
    $params = array_merge($empIds, [$fromMonth, $toMonth]);
    $stmt->bind_param($ti . 'ss', ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $salaryRows[] = $row;
    $stmt->close();

    // Loan summary
    if (tableExists('loan_transactions')) {
        $stmt = $conn->prepare(
            "SELECT lt.employee_id, e.emp_name,
                    COALESCE(SUM(CASE WHEN lt.type='debit'  THEN lt.amount ELSE 0 END), 0) as total_debited,
                    COALESCE(SUM(CASE WHEN lt.type='credit' THEN lt.amount ELSE 0 END), 0) as total_repaid
             FROM loan_transactions lt
             JOIN employees e ON lt.employee_id = e.id
             WHERE lt.employee_id IN ($pl)
             GROUP BY lt.employee_id, e.emp_name
             ORDER BY e.emp_name ASC"
        );
        $stmt->bind_param($ti, ...$empIds);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $loanSummary[$row['employee_id']] = $row;
        $stmt->close();
    }

    // PF summary
    $stmt = $conn->prepare(
        "SELECT ss.employee_id, e.emp_name,
                COALESCE(SUM(CASE WHEN ss.confirmed=1 THEN ss.pf_deduction ELSE 0 END), 0) as pf_from_salary,
                COALESCE(SUM(CASE WHEN ss.confirmed=0 THEN ss.pf_deduction ELSE 0 END), 0) as pf_pending
         FROM salary_sheets ss
         JOIN employees e ON ss.employee_id = e.id
         WHERE ss.employee_id IN ($pl)
         GROUP BY ss.employee_id, e.emp_name"
    );
    $stmt->bind_param($ti, ...$empIds);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $pfSummary[$row['employee_id']] = $row;
    $stmt->close();

    // PF manual transactions
    if (tableExists('pf_transactions')) {
        $stmt = $conn->prepare(
            "SELECT employee_id,
                    COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE 0 END), 0) as manual_credit,
                    COALESCE(SUM(CASE WHEN type='debit'  THEN amount ELSE 0 END), 0) as manual_debit
             FROM pf_transactions WHERE employee_id IN ($pl) GROUP BY employee_id"
        );
        $stmt->bind_param($ti, ...$empIds);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $eid = $row['employee_id'];
            if (isset($pfSummary[$eid])) {
                $pfSummary[$eid]['manual_credit'] = (float)$row['manual_credit'];
                $pfSummary[$eid]['manual_debit']  = (float)$row['manual_debit'];
            }
        }
        $stmt->close();
    }

    // Bonus sheets
    if (tableExists('bonus_sheets')) {
        $stmt = $conn->prepare(
            "SELECT bs.*, e.emp_name
             FROM bonus_sheets bs
             JOIN employees e ON bs.employee_id = e.id
             WHERE bs.employee_id IN ($pl) AND bs.month >= ? AND bs.month <= ?
             ORDER BY e.emp_name ASC, bs.month DESC"
        );
        $params = array_merge($empIds, [$fromMonth, $toMonth]);
        $stmt->bind_param($ti . 'ss', ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $bonusRows[] = $row;
        $stmt->close();
    }
}

// ── Group salary rows by employee for subtotals ───────────────────────────────
$salaryByEmp = [];
foreach ($salaryRows as $row) {
    $salaryByEmp[$row['employee_id']][] = $row;
}

$singleEmployee = count($empIds) === 1;
$showPerEmployee = $_GET['view_mode'] ?? ($singleEmployee ? 'combined' : 'separate');
$viewMode = $_GET['view_mode'] ?? 'combined';

// ── Print Section ─────────────────────────────────────────────────────────
$showSalary = $printOption === 'full' || $printOption === 'salary';
$showBonus = $printOption === 'full' || $printOption === 'bonus';

if ($submitted && !empty($empIds) && !empty($salaryRows) && $showSalary):
?>
<div class="print-section">
    <?php if ($showPerEmployee === 'combined' || $singleEmployee): ?>
    <div class="print-header">
        <div style="display:flex; align-items:center; justify-content:center; gap:15px; margin-bottom:10px;">
            <?php if (!empty($companyLogo)): ?>
                <img src="../uploads/<?php echo htmlspecialchars($companyLogo); ?>" style="height:50px;width:auto;">
            <?php endif; ?>
            <div>
                <h2 style="margin:0;font-size:20px;"><?php echo htmlspecialchars($companyName); ?></h2>
                <?php if (!empty($companyTagline)): ?>
                    <p style="margin:0;font-size:12px;"><?php echo htmlspecialchars($companyTagline); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <p style="font-size:14px;font-weight:bold;margin:5px 0;">SALARY REPORT</p>
        <p style="font-size:11px;"><?php echo date('M Y', strtotime($fromMonth . '-01')); ?> - <?php echo date('M Y', strtotime($toMonth . '-01')); ?></p>
        <div style="font-size:10px;">
            <?php if (!empty($companyAddress)) echo htmlspecialchars($companyAddress); ?>
            <?php if (!empty($companyPhone)) echo ' | Phone: ' . htmlspecialchars($companyPhone); ?>
            <?php if (!empty($companyEmail)) echo ' | Email: ' . htmlspecialchars($companyEmail); ?>
        </div>
    </div>
    <table class="Excel-style">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Month</th>
                <th>Basic</th>
                <th>Present</th>
                <th>Gross</th>
                <th>PF</th>
                <th>Bonus</th>
                <th>Net</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($salaryByEmp as $empId => $rows): ?>
                <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['emp_name']); ?></td>
                <td><?php echo date('M y', strtotime($row['month'].'-01')); ?></td>
                <td><?php echo formatCurrency($row['basic_salary']); ?></td>
                <td><?php echo $row['present_days']; ?>/<?php echo $row['working_days']; ?></td>
                <td><?php echo formatCurrency($row['gross_salary']); ?></td>
                <td><?php echo formatCurrency($row['pf_deduction']); ?></td>
                <td><?php echo formatCurrency($row['bonus']); ?></td>
                <td><?php echo formatCurrency($row['net_payable']); ?></td>
                <td><?php echo $row['confirmed'] ? 'C' : 'P'; ?></td>
            </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <?php foreach ($salaryByEmp as $empId => $rows): ?>
            <?php $empName = $rows[0]['emp_name']; $dept = $rows[0]['department']; ?>
    <div class="print-header">
        <div style="display:flex; align-items:center; justify-content:center; gap:15px; margin-bottom:10px;">
            <?php if (!empty($companyLogo)): ?>
                <img src="../uploads/<?php echo htmlspecialchars($companyLogo); ?>" style="height:50px;width:auto;">
            <?php endif; ?>
            <div>
                <h2 style="margin:0;font-size:20px;"><?php echo htmlspecialchars($companyName); ?></h2>
                <?php if (!empty($companyTagline)): ?>
                    <p style="margin:0;font-size:12px;"><?php echo htmlspecialchars($companyTagline); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <p style="font-size:14px;font-weight:bold;margin:5px 0;">SALARY REPORT - <?php echo htmlspecialchars($empName); ?></p>
        <p style="font-size:11px;"><?php echo date('M Y', strtotime($fromMonth . '-01')); ?> - <?php echo date('M Y', strtotime($toMonth . '-01')); ?></p>
    </div>
    <table class="Excel-style">
        <thead>
            <tr>
                <th>Month</th>
                <th>Basic</th>
                <th>Present</th>
                <th>Gross</th>
                <th>PF</th>
                <th>Bonus</th>
                <th>Net</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo date('M y', strtotime($row['month'].'-01')); ?></td>
                <td><?php echo formatCurrency($row['basic_salary']); ?></td>
                <td><?php echo $row['present_days']; ?>/<?php echo $row['working_days']; ?></td>
                <td><?php echo formatCurrency($row['gross_salary']); ?></td>
                <td><?php echo formatCurrency($row['pf_deduction']); ?></td>
                <td><?php echo formatCurrency($row['bonus']); ?></td>
                <td><?php echo formatCurrency($row['net_payable']); ?></td>
                <td><?php echo $row['confirmed'] ? 'C' : 'P'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="print-footer" style="margin-top:15px;font-size:9px;border-top:1px solid #333;padding-top:10px;page-break-after:always;">
        <div style="display:flex;justify-content:space-between;">
            <span><strong>Prepared By:</strong> ____________________</span>
            <span><strong>Approved By:</strong> ____________________</span>
        </div>
    </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
.print-section { display: none; }
@media print {
    .sidebar, .navbar, .page-header, .filter-card, .btn, .alert { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; break-inside: avoid; }
    .card:hover { transform: none !important; }
    .section-break { page-break-before: always; }
    body { background: #fff !important; print-color-adjust: exact; }
    .print-section { display: block !important; width: 100%; margin: 0; padding: 10px; }
    .print-header { text-align: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #333; }
    .print-header h2 { margin: 0; font-size: 18px; }
    .print-header p { margin: 5px 0; font-size: 12px; font-weight: bold; }
    table { width: 100%; font-size: 9px; border-collapse: collapse; }
    table th, table td { padding: 2px; border: 1px solid #333; }
    table th { background: #ddd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-weight: bold; }
}
.table th { white-space: nowrap; }
.table.Excel-style {
    border-collapse: collapse;
    width: 100%;
}
.table.Excel-style th,
.table.Excel-style td {
    border: 1px solid #000 !important;
    padding: 6px 8px;
}
.table.Excel-style th {
    background: #f0f0f0 !important;
    font-weight: bold;
}
.table.Excel-style tbody tr:nth-child(even) {
    background: #f9f9f9;
}
.table.Excel-style tbody tr:hover {
    background: #e8f4ff;
}
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Employee Report</h4>
        <small class="text-muted">Full HR data for selected employees — salary, PF, loans, bonuses</small>
    </div>
    <?php if ($submitted && !empty($empIds)): ?>
    <div class="d-flex gap-2 align-items-center">
        <select name="view_mode" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
            <option value="combined" <?php echo $viewMode === 'combined' ? 'selected' : ''; ?>>Combined Table</option>
            <option value="separate" <?php echo $viewMode === 'separate' ? 'selected' : ''; ?>>Separate Tables</option>
        </select>
        <select name="print_option" class="form-select form-select-sm" style="width:120px" onchange="this.form.submit()">
            <option value="full" <?php echo $printOption === 'full' ? 'selected' : ''; ?>>Full Print</option>
            <option value="salary" <?php echo $printOption === 'salary' ? 'selected' : ''; ?>>Salary Only</option>
            <option value="bonus" <?php echo $printOption === 'bonus' ? 'selected' : ''; ?>>Bonus Only</option>
        </select>
        <button onclick="window.print()" class="btn btn-success">
            <i class="bi bi-printer me-1"></i> Print
        </button>
    </div>
    <?php endif; ?>
</div>

<div class="card mb-4 filter-card">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-search me-2"></i>Search Employees</h5></div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Employees <span class="text-danger">*</span></label>
                    <input type="text" id="empSearch" class="form-control form-control-sm mb-1" placeholder="Type to filter list...">
                    <select name="emp_ids[]" id="empSelect" class="form-select" multiple size="6" required>
                        <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"
                                <?php echo in_array($emp['id'], $empIds) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['emp_name']); ?> — <?php echo htmlspecialchars($emp['department']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                    <div class="mt-1 d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAll()">Select All</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAll()">Clear</button>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Month</label>
                    <input type="month" name="from_month" class="form-control" value="<?php echo htmlspecialchars($fromMonth); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Month</label>
                    <input type="month" name="to_month" class="form-control" value="<?php echo htmlspecialchars($toMonth); ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($submitted && empty($empIds)): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Please select at least one employee.</div>
<?php endif; ?>

<?php if ($submitted && !empty($empIds)): ?>

    <?php if ($showSalary && !empty($salaryRows)): ?>
        <?php if ($viewMode === 'combined' || count($empIds) === 1): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white;">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-currency-dollar me-2"></i>Salary History
                            <span class="badge bg-light text-dark ms-2"><?php echo count($salaryRows); ?> records</span>
                        </h5>
                        <small><?php echo date('M Y', strtotime($fromMonth . '-01')); ?> – <?php echo date('M Y', strtotime($toMonth . '-01')); ?></small>
                    </div>
                    <div class="text-end">
                        <small><?php echo count($empIds); ?> employee(s)</small>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 Excel-style">
                        <thead class="table-dark">
                            <tr>
                                <th>Employee</th>
                                <th>Month</th>
                                <th class="text-end">Basic</th>
                                <th class="text-center">Present</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">PF</th>
                                <th class="text-end">Bonus</th>
                                <th class="text-end">Net</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $grandTotals = ['gross' => 0, 'pf' => 0, 'bonus' => 0, 'net' => 0];
                            $rowNum = 0;
                            foreach ($salaryByEmp as $empId => $rows):
                                $empTotals = ['gross' => 0, 'pf' => 0, 'bonus' => 0, 'net' => 0];
                                foreach ($rows as $row):
                                    $rowNum++;
                                    $empTotals['gross'] += (float)$row['gross_salary'];
                                    $empTotals['pf']    += (float)$row['pf_deduction'];
                                    $empTotals['bonus'] += (float)$row['bonus'];
                                    $empTotals['net']   += (float)$row['net_payable'];
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['emp_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($row['department']); ?></small>
                                    </td>
                                    <td><?php echo date('M Y', strtotime($row['month'] . '-01')); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($row['basic_salary']); ?></td>
                                    <td class="text-center"><?php echo $row['present_days']; ?>/<?php echo $row['working_days']; ?></td>
                                    <td class="text-end"><?php echo formatCurrency($row['gross_salary']); ?></td>
                                    <td class="text-end text-danger"><?php echo formatCurrency($row['pf_deduction']); ?></td>
                                    <td class="text-end text-success"><?php echo formatCurrency($row['bonus']); ?></td>
                                    <td class="text-end fw-bold"><?php echo formatCurrency($row['net_payable']); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $row['confirmed'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $row['confirmed'] ? 'C' : 'P'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                                <tr class="table-active fw-bold">
                                    <td colspan="2"><?php echo htmlspecialchars($rows[0]['emp_name']); ?> (<?php echo count($rows); ?> mo)</td>
                                    <td class="text-end"><?php echo formatCurrency(array_sum(array_column($rows, 'basic_salary'))); ?></td>
                                    <td></td>
                                    <td class="text-end"><?php echo formatCurrency($empTotals['gross']); ?></td>
                                    <td class="text-end text-danger"><?php echo formatCurrency($empTotals['pf']); ?></td>
                                    <td class="text-end text-success"><?php echo formatCurrency($empTotals['bonus']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($empTotals['net']); ?></td>
                                    <td></td>
                                </tr>
                            <?php
                                $grandTotals['gross'] += $empTotals['gross'];
                                $grandTotals['pf']    += $empTotals['pf'];
                                $grandTotals['bonus'] += $empTotals['bonus'];
                                $grandTotals['net']   += $empTotals['net'];
                            endforeach; 
                            ?>
                        </tbody>
                        <tfoot class="table-dark fw-bold">
                            <tr>
                                <td colspan="4">Grand Total (<?php echo $rowNum; ?> records)</td>
                                <td class="text-end"><?php echo formatCurrency($grandTotals['gross']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($grandTotals['pf']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($grandTotals['bonus']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($grandTotals['net']); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($salaryByEmp as $empId => $rows): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white;">
                        <div>
                            <h5 class="mb-0"><i class="bi bi-person me-2"></i><?php echo htmlspecialchars($rows[0]['emp_name']); ?>
                                <span class="badge bg-light text-dark ms-2"><?php echo count($rows); ?> records</span>
                            </h5>
                            <small><?php echo htmlspecialchars($rows[0]['department']); ?> | <?php echo htmlspecialchars($rows[0]['position']); ?></small>
                        </div>
                        <div class="text-end">
                            <small><?php echo date('M Y', strtotime($fromMonth . '-01')); ?> – <?php echo date('M Y', strtotime($toMonth . '-01')); ?></small>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 Excel-style">
                            <thead class="table-dark">
                                <tr>
                                    <th>Month</th>
                                    <th class="text-end">Basic</th>
                                    <th class="text-center">Present</th>
                                    <th class="text-end">Gross</th>
                                    <th class="text-end">PF</th>
                                    <th class="text-end">Bonus</th>
                                    <th class="text-end">Net</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $empTotals = ['gross' => 0, 'pf' => 0, 'bonus' => 0, 'net' => 0];
                                foreach ($rows as $row):
                                    $empTotals['gross'] += (float)$row['gross_salary'];
                                    $empTotals['pf']    += (float)$row['pf_deduction'];
                                    $empTotals['bonus'] += (float)$row['bonus'];
                                    $empTotals['net']   += (float)$row['net_payable'];
                                ?>
                                    <tr>
                                        <td><?php echo date('M Y', strtotime($row['month'] . '-01')); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($row['basic_salary']); ?></td>
                                        <td class="text-center"><?php echo $row['present_days']; ?>/<?php echo $row['working_days']; ?></td>
                                        <td class="text-end"><?php echo formatCurrency($row['gross_salary']); ?></td>
                                        <td class="text-end text-danger"><?php echo formatCurrency($row['pf_deduction']); ?></td>
                                        <td class="text-end text-success"><?php echo formatCurrency($row['bonus']); ?></td>
                                        <td class="text-end fw-bold"><?php echo formatCurrency($row['net_payable']); ?></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $row['confirmed'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $row['confirmed'] ? 'Confirmed' : 'Pending'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-dark fw-bold">
                                <tr>
                                    <td>Total (<?php echo count($rows); ?> mo)</td>
                                    <td class="text-end"><?php echo formatCurrency(array_sum(array_column($rows, 'basic_salary'))); ?></td>
                                    <td></td>
                                    <td class="text-end"><?php echo formatCurrency($empTotals['gross']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($empTotals['pf']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($empTotals['bonus']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($empTotals['net']); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Loan Summary</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 Excel-style">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th class="text-end">Total Debited (Loan Given)</th>
                        <th class="text-end">Total Repaid</th>
                        <th class="text-end">Outstanding Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $loanGrandDebit = $loanGrandRepaid = $loanGrandBal = 0;
                    foreach ($empIds as $eid):
                        $emp = null;
                        foreach ($allEmployees as $e) { if ($e['id'] == $eid) { $emp = $e; break; } }
                        $loan = $loanSummary[$eid] ?? ['total_debited' => 0, 'total_repaid' => 0];
                        $bal  = (float)$loan['total_debited'] - (float)$loan['total_repaid'];
                        $loanGrandDebit  += (float)$loan['total_debited'];
                        $loanGrandRepaid += (float)$loan['total_repaid'];
                        $loanGrandBal    += $bal;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($emp ? $emp['emp_name'] : "Employee #$eid"); ?></strong>
                            <?php if ($emp): ?><br><small class="text-muted"><?php echo htmlspecialchars($emp['department']); ?></small><?php endif; ?>
                        </td>
                        <td class="text-end"><?php echo number_format((float)$loan['total_debited'], 2); ?></td>
                        <td class="text-end text-success"><?php echo number_format((float)$loan['total_repaid'], 2); ?></td>
                        <td class="text-end fw-bold <?php echo $bal > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo number_format($bal, 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-end"><?php echo number_format($loanGrandDebit, 2); ?></td>
                        <td class="text-end"><?php echo number_format($loanGrandRepaid, 2); ?></td>
                        <td class="text-end <?php echo $loanGrandBal > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo number_format($loanGrandBal, 2); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-piggy-bank me-2"></i>PF Summary</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 Excel-style">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th class="text-end">From Salary (Confirmed)</th>
                        <th class="text-end">Manual Credits</th>
                        <th class="text-end">Manual Debits</th>
                        <th class="text-end">Net PF Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $pfGrandSal = $pfGrandCr = $pfGrandDr = $pfGrandNet = 0;
                    foreach ($empIds as $eid):
                        $emp = null;
                        foreach ($allEmployees as $e) { if ($e['id'] == $eid) { $emp = $e; break; } }
                        $pf       = $pfSummary[$eid] ?? ['pf_from_salary' => 0, 'manual_credit' => 0, 'manual_debit' => 0];
                        $fromSal  = (float)($pf['pf_from_salary'] ?? 0);
                        $manCr    = (float)($pf['manual_credit'] ?? 0);
                        $manDr    = (float)($pf['manual_debit']  ?? 0);
                        $netPF    = $fromSal + $manCr - $manDr;
                        $pfGrandSal += $fromSal; $pfGrandCr += $manCr; $pfGrandDr += $manDr; $pfGrandNet += $netPF;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($emp ? $emp['emp_name'] : "Employee #$eid"); ?></strong>
                            <?php if ($emp): ?><br><small class="text-muted"><?php echo htmlspecialchars($emp['department']); ?></small><?php endif; ?>
                        </td>
                        <td class="text-end"><?php echo number_format($fromSal, 2); ?></td>
                        <td class="text-end text-success"><?php echo number_format($manCr, 2); ?></td>
                        <td class="text-end text-danger"><?php echo number_format($manDr, 2); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($netPF, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-end"><?php echo number_format($pfGrandSal, 2); ?></td>
                        <td class="text-end"><?php echo number_format($pfGrandCr, 2); ?></td>
                        <td class="text-end"><?php echo number_format($pfGrandDr, 2); ?></td>
                        <td class="text-end"><?php echo number_format($pfGrandNet, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-gift me-2"></i>Bonus History
                <span class="badge bg-secondary ms-2"><?php echo count($bonusRows); ?> records</span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 Excel-style">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Month</th>
                        <th>Type</th>
                        <th class="text-end">Basic</th>
                        <th class="text-end">Bonus %</th>
                        <th class="text-end">Bonus Amount</th>
                        <th>Description</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bonusRows)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-3">No bonus records found</td></tr>
                    <?php else: ?>
                        <?php
                        $bonusGrandTotal = 0;
                        foreach ($bonusRows as $b):
                            $bonusGrandTotal += (float)$b['bonus_amount'];
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($b['emp_name']); ?></strong></td>
                            <td><?php echo date('M Y', strtotime($b['month'] . '-01')); ?></td>
                            <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($b['bonus_type']); ?></span></td>
                            <td class="text-end"><?php echo number_format($b['basic_salary'], 2); ?></td>
                            <td class="text-end"><?php echo number_format($b['bonus_pct'], 2); ?>%</td>
                            <td class="text-end fw-bold"><?php echo number_format($b['bonus_amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($b['description'] ?? ''); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $b['confirmed'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $b['confirmed'] ? 'Confirmed' : 'Pending'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($bonusRows)): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="5" class="text-end">Total Bonus:</td>
                        <td class="text-end"><?php echo number_format($bonusGrandTotal, 2); ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

<?php else: ?>

    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-file-earmark-spreadsheet fs-1 d-block mb-3"></i>
            <h5>Select employees and date range</h5>
            <p>Choose one or more employees above to generate a full HR report</p>
        </div>
    </div>

<?php endif; ?>

<script>
document.getElementById('empSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    Array.from(document.getElementById('empSelect').options).forEach(opt => {
        opt.style.display = opt.text.toLowerCase().includes(q) ? '' : 'none';
    });
});

function selectAll() {
    Array.from(document.getElementById('empSelect').options).forEach(opt => {
        if (opt.style.display !== 'none') opt.selected = true;
    });
}

function clearAll() {
    Array.from(document.getElementById('empSelect').options).forEach(opt => opt.selected = false);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>