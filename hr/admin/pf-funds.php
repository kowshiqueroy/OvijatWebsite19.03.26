<?php
define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'PF Funds';
$currentPage = 'pf-funds';

$companyName = getSetting('company_name') ?? 'My Company';
$companyTagline = getSetting('company_tagline') ?? '';
$companyAddress = getSetting('company_address') ?? '';
$companyPhone = getSetting('company_phone') ?? '';
$companyEmail = getSetting('company_email') ?? '';
$companyLogo = getSetting('company_logo') ?? '';

$filter = [
    'office' => $_GET['office'] ?? '',
    'department' => $_GET['department'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$printOption = $_GET['print_option'] ?? 'full';
$selectedMonth = $_GET['month'] ?? '';

$offices = getOfficeList();
$departments = getDepartmentList($filter['office']);

$message = '';
$messageType = 'success';
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $month = $_POST['month'] ?? '';
    $adminId = (int)$_SESSION['admin_id'];

    if (isset($_POST['save_pf_funds']) && $month) {
        $employees = getAllEmployees(array_merge($filter, ['status' => 'Active']));
        $transDate = $month . '-01';
        $newCount = 0;
        $updateCount = 0;

        foreach ($employees as $emp) {
            $empId = (int)$emp['id'];
            $extraPf = isset($_POST['extra_' . $empId]) && $_POST['extra_' . $empId] !== '' ? (float)$_POST['extra_' . $empId] : 0.0;

            $chkT = $conn->prepare("SELECT id FROM pf_transactions WHERE employee_id = ? AND description = 'PF Contribution' AND transaction_date = ?");
            $chkT->bind_param("is", $empId, $transDate);
            $chkT->execute();
            $chkTRes = $chkT->get_result();
            $hasTrans = $chkTRes->num_rows > 0;
            $chkT->close();

            if ($extraPf > 0) {
                if ($hasTrans) {
                    $updT = $conn->prepare("UPDATE pf_transactions SET amount = ? WHERE employee_id = ? AND description = 'PF Contribution' AND transaction_date = ?");
                    $updT->bind_param("dis", $extraPf, $empId, $transDate);
                    $updT->execute();
                    $updateCount++;
                    $updT->close();
                } else {
                    $insT = $conn->prepare("INSERT INTO pf_transactions (employee_id, transaction_date, type, amount, description, created_by) VALUES (?, ?, 'credit', ?, 'PF Contribution', ?)");
                    $insT->bind_param("isdi", $empId, $transDate, $extraPf, $adminId);
                    $insT->execute();
                    $newCount++;
                    $insT->close();
                }
            } elseif ($hasTrans) {
                $delT = $conn->prepare("DELETE FROM pf_transactions WHERE employee_id = ? AND description = 'PF Contribution' AND transaction_date = ?");
                $delT->bind_param("is", $empId, $transDate);
                $delT->execute();
                $delT->close();
            }
        }

        $message = "{$newCount} new, {$updateCount} updated.";
        logActivity('update', 'pf_funds', null, "PF Funds saved for $month: $newCount new, $updateCount updated");
    }

    $qs = http_build_query(['month' => $month, 'office' => $filter['office'], 'department' => $filter['department'], 'print_option' => $printOption]);
    header("Location: pf-funds.php?" . $qs . "&msg=" . urlencode($message) . "&mt=" . $messageType);
    exit;
}

if (!empty($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $messageType = in_array($_GET['mt'] ?? '', ['success', 'danger', 'warning', 'info']) ? $_GET['mt'] : 'success';
}

$employees = !empty($selectedMonth) ? getAllEmployees(array_merge($filter, ['status' => 'Active'])) : [];
$extraPfByEmployee = [];

if (!empty($selectedMonth)) {
    $transDate = $selectedMonth . '-01';
    $stmt2 = $conn->prepare("SELECT employee_id, amount, id FROM pf_transactions WHERE transaction_date = ? AND type = 'credit' AND description = 'PF Contribution'");
    $stmt2->bind_param("s", $transDate);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row2 = $res2->fetch_assoc()) {
        $extraPfByEmployee[$row2['employee_id']] = $row2;
    }
    $stmt2->close();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">PF Funds</h4>
        <small class="text-muted">Extra PF fund contributions per month</small>
    </div>
</div>

<?php if ($message): ?>
<?php showAlert($message, $messageType); ?>
<?php endif; ?>

<?php
$totalPfExtra = array_sum(array_column($extraPfByEmployee, 'amount'));
$showBankDetails = in_array($printOption, ['full', 'minimal_bank']);
$isMinimal = in_array($printOption, ['minimal', 'minimal_bank']);
?>

<?php if (!empty($selectedMonth) && count($extraPfByEmployee) > 0): ?>
<div class="print-section">
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
        <p style="font-size:14px;font-weight:bold;margin:5px 0;">PF FUND SHEET - <?php echo date('F Y', strtotime($selectedMonth . '-01')); ?></p>
        <div style="font-size:11px;">
            <?php if (!empty($companyAddress)) echo htmlspecialchars($companyAddress); ?>
            <?php if (!empty($companyPhone)) echo ' | Phone: ' . htmlspecialchars($companyPhone); ?>
            <?php if (!empty($companyEmail)) echo ' | Email: ' . htmlspecialchars($companyEmail); ?>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <?php if (!$isMinimal): ?><th>ID</th><?php endif; ?>
                <th>Employee</th>
                <?php if (!$isMinimal): ?><th>Department</th><?php endif; ?>
                <th>Amount</th>
                <?php if ($showBankDetails): ?><th>Bank Name</th><th>Account No.</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($extraPfByEmployee as $empId => $row): ?>
            <?php
            $emp = null;
            foreach ($employees as $e) { if ($e['id'] == $empId) { $emp = $e; break; } }
            if (!$emp) continue;
            ?>
            <tr>
                <?php if (!$isMinimal): ?>
                <td><?php echo generateEmployeeID($emp['id'], $emp['office_code'], $emp['dept_code']); ?></td>
                <?php endif; ?>
                <td><?php echo htmlspecialchars($emp['emp_name']); ?></td>
                <?php if (!$isMinimal): ?><td><?php echo htmlspecialchars($emp['department']); ?></td><?php endif; ?>
                <td><?php echo formatCurrency($row['amount']); ?></td>
                <?php if ($showBankDetails): ?>
                <td><?php echo htmlspecialchars($emp['bank_name'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($emp['bank_account'] ?? '-'); ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <?php $leftCols = !$isMinimal ? 3 : 1; ?>
                <td colspan="<?php echo $leftCols; ?>" style="text-align:right;"><strong>Total:</strong></td>
                <td><strong><?php echo formatCurrency($totalPfExtra); ?></strong></td>
                <?php if ($showBankDetails): ?><td colspan="2"></td><?php endif; ?>
            </tr>
        </tfoot>
    </table>
    <div class="print-footer">
        <div style="display:flex;justify-content:space-between;">
            <span><strong>Prepared By:</strong> ____________________</span>
            <span><strong>Approved By:</strong> ____________________</span>
        </div>
        <div style="text-align:center;margin-top:10px;"><small>Total Entries: <?php echo count($extraPfByEmployee); ?></small></div>
    </div>
</div>
<?php endif; ?>

<form method="GET" action="" class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter Employees</h5></div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Month <span class="text-danger">*</span></label>
                <select name="month" class="form-select" required>
                    <option value="">-- Select Month --</option>
                    <?php foreach (generateMonthOptions() as $opt): ?>
                    <option value="<?php echo $opt['value']; ?>" <?php echo $selectedMonth === $opt['value'] ? 'selected' : ''; ?>><?php echo $opt['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Office</label>
                <select name="office" class="form-select">
                    <option value="">All Offices</option>
                    <?php foreach ($offices as $off): ?>
                    <option value="<?php echo htmlspecialchars($off['office_name']); ?>" <?php echo $filter['office'] === $off['office_name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($off['office_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Department</label>
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $filter['department'] === $dept['department'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['department']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Print Options</label>
                <select name="print_option" class="form-select">
                    <option value="full">Full Print</option>
                    <option value="minimal" <?php echo $printOption === 'minimal' ? 'selected' : ''; ?>>Minimal Print</option>
                    <option value="minimal_bank" <?php echo $printOption === 'minimal_bank' ? 'selected' : ''; ?>>Minimal + Bank</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="ID or Name" value="<?php echo htmlspecialchars($filter['search'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Search</button>
            </div>
            <?php if (!empty($selectedMonth) && count($extraPfByEmployee) > 0): ?>
            <div class="col-md-2">
                <button type="button" class="btn btn-success w-100" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if (empty($selectedMonth)): ?>
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-wallet2 fs-1 d-block mb-3"></i>
        <h5>Select a Month</h5>
        <p>Please select a month above to add extra PF fund contributions.</p>
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
<form method="POST" action="">
    <?php echo csrfField(); ?>
    <input type="hidden" name="month" value="<?php echo htmlspecialchars($selectedMonth); ?>">
    <?php if (!empty($filter['office'])): ?>
    <input type="hidden" name="office" value="<?php echo htmlspecialchars($filter['office']); ?>">
    <?php endif; ?>
    <?php if (!empty($filter['department'])): ?>
    <input type="hidden" name="department" value="<?php echo htmlspecialchars($filter['department']); ?>">
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-people me-2"></i>Employees
                <span class="badge bg-secondary ms-1"><?php echo count($employees); ?></span>
                &mdash; <span class="text-muted fw-normal fs-6"><?php echo date('F Y', strtotime($selectedMonth . '-01')); ?></span>
            </h5>
            <button type="submit" name="save_pf_funds" class="btn btn-success"><i class="bi bi-floppy me-1"></i> Save PF Funds</button>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:160px;">Employee</th>
                        <th style="width:130px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <?php
                    $empId = (int)$emp['id'];
                    $extra = $extraPfByEmployee[$empId] ?? null;
                    $extraAmt = $extra ? (float)$extra['amount'] : 0.0;
                    ?>
                    <tr class="<?php echo $extra ? 'table-success' : ''; ?>">
                        <td>
                            <span class="badge bg-dark d-inline-block mb-1" style="font-size:10px;">
                                <?php echo htmlspecialchars(generateEmployeeID($emp['id'], $emp['office_code'], $emp['dept_code'])); ?>
                            </span>
                            <div style="font-size:12px;font-weight:600;line-height:1.3;"><?php echo htmlspecialchars($emp['emp_name']); ?></div>
                            <div class="text-muted" style="font-size:10px;line-height:1.3;">
                                <?php echo htmlspecialchars($emp['department']); ?><br>
                                <?php echo htmlspecialchars($emp['position']); ?> &middot; <?php echo htmlspecialchars($emp['office_name']); ?>
                            </div>
                        </td>
                        <td>
                            <input type="number" name="extra_<?php echo $empId; ?>" class="form-control form-control-sm" value="<?php echo $extraAmt; ?>" step="0.01" min="0" placeholder="0.00" <?php echo $extraAmt > 0 ? 'readonly' : ''; ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted">Total Extra: <?php echo formatCurrency($totalPfExtra); ?></small>
        </div>
    </div>
</form>
<?php endif; ?>

<style>
.print-section { display: none; }
@media print {
    @page { margin: 0.5cm; }
    .sidebar, .navbar, .page-header, .alert, .row.g-3, form[method="GET"], form[method="POST"] { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    body { background: white !important; }
    .print-section { display: block !important; width: 100%; margin: 0; padding: 10px; }
    .print-header { text-align: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #333; }
    .print-header h2 { margin: 0; font-size: 18px; }
    .print-header p { margin: 5px 0; font-size: 12px; font-weight: bold; }
    .print-header div { font-size: 10px; }
    table { width: 100%; font-size: 9px; border-collapse: collapse; }
    table th, table td { padding: 2px; border: 1px solid #333; }
    table th { background: #ddd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-weight: bold; }
    table tfoot td { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-weight: bold; }
    .print-footer { margin-top: 15px; font-size: 9px; border-top: 1px solid #333; padding-top: 10px; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>