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
    'position' => $_GET['position'] ?? '',
    'confirmed_status' => $_GET['confirmed_status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$selectedMonth = $_GET['month'] ?? '';
$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;

$perPage = 50;
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$totalSheets = (!empty($selectedMonth)) ? countSalarySheets($employeeId, $selectedMonth, $filter) : 0;
$totalPages = $totalSheets > 0 ? (int)ceil($totalSheets / $perPage) : 1;
$sheets = getSalarySheets($employeeId, $selectedMonth, $filter, $perPage, ($pageNum - 1) * $perPage);
$months = getSalaryMonths();
$offices = getOfficeList();
$departments = getDepartmentList($filter['office']);
$units = getUnitList($filter['department']);
$positions = getPositionList($filter['department']);

$monthStats = null;
if ($selectedMonth) {
    $monthStats = getSalaryStats($selectedMonth);
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }
    $conn = getDBConnection();
    $adminId = $_SESSION['admin_id'];

    if (isset($_POST['bulk_confirm'])) {
        $selectedIds = array_map('intval', $_POST['salary_ids'] ?? []);
        if (empty($selectedIds)) {
            $message = 'Please select at least one salary entry';
            $messageType = 'warning';
        } else {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $conn->prepare("UPDATE salary_sheets SET confirmed = 1, confirmed_by = ?, confirmed_at = NOW() WHERE id IN ($placeholders) AND confirmed = 0");
            $stmt->bind_param("i" . str_repeat('i', count($selectedIds)), $adminId, ...$selectedIds);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            
            logActivity('confirm', 'salary', null, "Bulk confirmed $affected salary entries for $selectedMonth");
            $message = "$affected salary entry(s) confirmed successfully";
            header("Location: salary-list.php?month={$selectedMonth}&msg=confirmed");
            exit;
        }
    }

    if (isset($_POST['bulk_unconfirm'])) {
        $selectedIds = array_map('intval', $_POST['salary_ids'] ?? []);
        if (empty($selectedIds)) {
            $message = 'Please select at least one salary entry';
            $messageType = 'warning';
        } else {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $conn->prepare("UPDATE salary_sheets SET confirmed = 0, unconfirmed_by = ?, unconfirmed_at = NOW() WHERE id IN ($placeholders) AND confirmed = 1");
            $stmt->bind_param("i" . str_repeat('i', count($selectedIds)), $adminId, ...$selectedIds);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            $message = "$affected salary entry(s) unconfirmed successfully";
            header("Location: salary-list.php?month={$selectedMonth}&msg=unconfirmed");
            exit;
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT * FROM salary_sheets WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sheet = $result->fetch_assoc();
    $stmt->close();
    
    if ($sheet && $sheet['confirmed'] == 1) {
        $message = 'Cannot delete confirmed salary sheet.';
        $messageType = 'danger';
    } else {
        $sheetDetails = json_encode($sheet);
        $stmt = $conn->prepare("DELETE FROM salary_sheets WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            logActivity('delete', 'salary', $id, "Deleted salary for emp {$sheet['employee_id']} | Data: " . $sheetDetails);
            header('Location: salary-list.php?month=' . $selectedMonth . '&msg=deleted');
            exit;
        }
        $stmt->close();
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') {
        $message = 'Salary sheet deleted successfully';
    } elseif ($_GET['msg'] === 'confirmed') {
        $message = 'Salary entries confirmed successfully';
    } elseif ($_GET['msg'] === 'unconfirmed') {
        $message = 'Salary entries unconfirmed successfully';
    }
}

$companyName = getSetting('company_name') ?? 'My Company';
$companyTagline = getSetting('company_tagline') ?? '';
$companyAddress = getSetting('company_address') ?? '';
$companyPhone = getSetting('company_phone') ?? '';
$companyEmail = getSetting('company_email') ?? '';
$companyWebsite = getSetting('company_website') ?? '';
$companyLogo = getSetting('company_logo') ?? '';

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
    <?php showAlert($message, $messageType ?? 'success'); ?>
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
                    <h4 class="mb-0"><?php echo formatCurrency($monthStats['total_gross'] ?? 0); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card warning">
                <div class="card-body py-3">
                    <small class="text-muted">Total PF</small>
                    <h4 class="mb-0"><?php echo formatCurrency($monthStats['total_pf'] ?? 0); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card danger">
                <div class="card-body py-3">
                    <small class="text-muted">Total Net Payable</small>
                    <h4 class="mb-0"><?php echo formatCurrency($monthStats['total_payable'] ?? 0); ?></h4>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="GET" action="" class="card mb-4">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-2">
                <label class="form-label small mb-1">Month</label>
                <select name="month" class="form-select form-select-sm" required>
                    <option value="">-- Select Month --</option>
                    <?php $salaryMonths = getSalaryMonths(); ?>
                    <?php foreach ($salaryMonths as $m): ?>
                        <option value="<?php echo $m; ?>" <?php echo $selectedMonth === $m ? 'selected' : ''; ?>>
                            <?php echo date('F Y', strtotime($m . '-01')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Office</label>
                <select name="office" class="form-select form-select-sm filter-select">
                    <option value="">All Offices</option>
                    <?php foreach ($offices as $off): ?>
                        <option value="<?php echo htmlspecialchars($off['office_name']); ?>" <?php echo $filter['office'] === $off['office_name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($off['office_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Department</label>
                <select name="department" class="form-select form-select-sm filter-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $filter['department'] === $dept['department'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Unit</label>
                <select name="unit" class="form-select form-select-sm filter-select">
                    <option value="">All Units</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $filter['unit'] === $u ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Position</label>
                <select name="position" class="form-select form-select-sm filter-select">
                    <option value="">All Positions</option>
                    <?php foreach ($positions as $p): ?>
                        <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $filter['position'] === $p ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="confirmed_status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="1" <?php echo ($_GET['confirmed_status'] ?? '') === '1' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="0" <?php echo ($_GET['confirmed_status'] ?? '') === '0' ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Search ID or Name</label>
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                    <a href="salary-list.php" class="btn btn-outline-secondary" title="Clear Filters"><i class="bi bi-x-circle"></i></a>
                </div>
            </div>
            <?php if (!empty($selectedMonth) && count($sheets) > 0): ?>
                <div class="col-md-2">
                    <label class="form-label small mb-1">&nbsp;</label>
                    <select class="form-select form-select-sm bg-success text-white border-success" onchange="handlePrint(this)">
                        <option value="" selected disabled>&#128424; Print Options...</option>
                        <option value="general" class="text-dark bg-white">General Sheet</option>
                        <option value="bank_all" class="text-dark bg-white">Bank Advice (All)</option>
                        <option value="bank_confirmed" class="text-dark bg-white">Bank Advice (Confirmed)</option>
                        <option value="bank_pending" class="text-dark bg-white">Bank Advice (Pending)</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php
// Main logic: show different content based on selection
if (empty($selectedMonth)) {
    // No month selected
    echo '<div class="card"><div class="card-body text-center text-muted py-5">
        <i class="bi bi-calendar-check fs-1 d-block mb-3"></i>
        <h5>Select a Month</h5>
        <p>Please select a month above to view salary sheets</p>
    </div></div>';
} elseif (empty($sheets)) {
    // Month selected but no sheets
    echo '<div class="card"><div class="card-body text-center text-muted py-4">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        No salary sheets found for this month. Generate a salary sheet first.
    </div></div>';
} else {
    // Has sheets - show both print section and interactive UI
    $confirmedCount = count(array_filter($sheets, fn($s) => $s['confirmed'] === 1));
    $pendingCount = count($sheets) - $confirmedCount;
    
    // Print Section (hidden on screen, visible when printing)
    ?>
    <!-- General Sheet Print Section -->
    <div class="print-section general-print-section">
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
            <p style="font-size:14px;font-weight:bold;margin:5px 0;">SALARY SHEET - <?php echo date('F Y', strtotime($selectedMonth . '-01')); ?></p>
            <div style="font-size:11px;">
                <?php if (!empty($companyAddress)) echo htmlspecialchars($companyAddress); ?>
                <?php if (!empty($companyPhone)) echo ' | Phone: ' . htmlspecialchars($companyPhone); ?>
                <?php if (!empty($companyEmail)) echo ' | Email: ' . htmlspecialchars($companyEmail); ?>
                <?php if (!empty($companyWebsite)) echo ' | Web: ' . htmlspecialchars($companyWebsite); ?>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID / Employee</th>
                    <th>Month</th>
                    <th>Attendance</th>
                    <th>Basic Salary</th>
                    <th>PF %</th>
                    <th>PF Ded.</th>
                    <th>Bonus</th>
                    <th>Gross</th>
                    <th>Net Payable</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sheets as $sheet): ?>
                <tr>
                    <td>
                        <strong><?php echo generateEmployeeID($sheet['id'], $sheet['office_code'], $sheet['dept_code']); ?></strong><br>
                        <?php echo htmlspecialchars($sheet['emp_name']); ?>
                    </td>
                    <td><?php echo date('M Y', strtotime($sheet['month'] . '-01')); ?></td>
                    <td>P:<?php echo $sheet['present_days']; ?> L:<?php echo $sheet['leave_days']; ?> A:<?php echo $sheet['absent_days']; ?> W:<?php echo $sheet['working_days']; ?></td>
                    <td><?php echo formatCurrency($sheet['basic_salary']); ?></td>
                    <td><?php echo $sheet['pf_percentage']; ?>%</td>
                    <td><?php echo formatCurrency($sheet['pf_deduction']); ?></td>
                    <td><?php echo formatCurrency($sheet['bonus'] ?? 0); ?></td>
                    <td><?php echo formatCurrency($sheet['gross_salary']); ?></td>
                    <td><?php echo formatCurrency($sheet['net_payable']); ?></td>
                    <td><?php echo $sheet['confirmed'] ? 'Confirmed' : 'Pending'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" style="text-align:right"><strong>Total:</strong></td>
                    <td><?php echo formatCurrency(array_sum(array_column($sheets, 'bonus'))); ?></td>
                    <td><?php echo formatCurrency(array_sum(array_column($sheets, 'gross_salary'))); ?></td>
                    <td><?php echo formatCurrency(array_sum(array_column($sheets, 'net_payable'))); ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <div class="print-footer">
            <div style="display:flex;justify-content:space-between;">
                <span><strong>Prepared By:</strong> ____________________</span>
                <span><strong>Approved By:</strong> ____________________</span>
            </div>
            <div style="text-align:center;margin-top:10px;">
                <small>Total: <?php echo count($sheets); ?> | Confirmed: <?php echo $confirmedCount; ?> | Pending: <?php echo $pendingCount; ?></small>
            </div>
        </div>
    </div>
    
    <!-- Bank Sheet Print Section -->
    <div class="print-section bank-print-section">
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
            <p style="font-size:14px;font-weight:bold;margin:5px 0;">BANK ADVICE / SALARY SHEET - <?php echo date('F Y', strtotime($selectedMonth . '-01')); ?></p>
            <p class="print-status-label" style="font-size:11px; margin-bottom:10px;"></p>
        </div>
        <table class="bank-table">
            <thead>
                <tr>
                    <th>SL</th>
                    <th>ID</th>
                    <th>Employee Name</th>
                    <th>Position</th>
                    <th>Bank Name</th>
                    <th>Account Number</th>
                    <th style="text-align:right">Net Payable</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sl = 1;
                foreach ($sheets as $sheet): 
                    $emp = getEmployeeById($sheet['employee_id']);
                ?>
                <tr class="bank-row" data-confirmed="<?php echo $sheet['confirmed']; ?>">
                    <td><?php echo $sl++; ?></td>
                    <td><?php echo generateEmployeeID($sheet['id'], $sheet['office_code'], $sheet['dept_code']); ?></td>
                    <td><?php echo htmlspecialchars($sheet['emp_name']); ?></td>
                    <td><?php echo htmlspecialchars($sheet['position']); ?></td>
                    <td><?php echo htmlspecialchars($emp['bank_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($emp['bank_account'] ?? '-'); ?></td>
                    <td style="text-align:right"><?php echo formatCurrency($sheet['net_payable']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" style="text-align:right"><strong>Total:</strong></td>
                    <td style="text-align:right" id="bank-total-cell"><strong>0.00</strong></td>
                </tr>
            </tfoot>
        </table>
        <div class="print-footer">
            <div style="display:flex;justify-content:space-between; margin-top:40px;">
                <div style="text-align:center;">
                    <br>____________________<br><strong>Prepared By</strong>
                </div>
                <div style="text-align:center;">
                    <br>____________________<br><strong>Verified By</strong>
                </div>
                <div style="text-align:center;">
                    <br>____________________<br><strong>Approved By</strong>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Interactive UI -->
    <form method="POST" action="">
        <?php echo csrfField(); ?>
        <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Salary Records (<?php echo $totalSheets; ?>) - <?php echo date('F Y', strtotime($selectedMonth . '-01')); ?></h5>
                <div class="d-flex align-items-center gap-2">
                    <div class="badge bg-success"><?php echo $confirmedCount; ?> Confirmed</div>
                    <div class="badge bg-secondary"><?php echo $pendingCount; ?> Pending</div>
                </div>
            </div>

            <div class="card-header bg-light border-bottom-0 pb-2">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label fw-bold" for="selectAll">Select All</label>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <?php if ($pendingCount > 0): ?>
                        <button type="submit" name="bulk_confirm" class="btn btn-success" onclick="return confirm('Confirm selected entries?');">
                            <i class="bi bi-check-all me-1"></i> Confirm Selected
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;"></th>
                            <th>ID / Employee</th>
                            <th>Month</th>
                            <th>Attendance</th>
                            <th>Basic Salary</th>
                            <th>PF %</th>
                            <th>PF Ded.</th>
                            <th>Bonus</th>
                            <th>Gross</th>
                            <th>Net Payable</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sheets as $sheet): ?>
                        <tr class="<?php echo $sheet['confirmed'] === 1 ? 'table-success' : ''; ?>">
                            <td>
                                <input type="checkbox" name="salary_ids[]" value="<?php echo $sheet['id']; ?>" class="salary-checkbox">
                            </td>
                            <td>
                                <span class="badge bg-dark"><?php echo generateEmployeeID($sheet['id'], $sheet['office_code'], $sheet['dept_code']); ?></span>
                                <br><strong><?php echo htmlspecialchars($sheet['emp_name']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($sheet['department']); ?> | <?php echo htmlspecialchars($sheet['position']); ?></small>
                            </td>
                            <td><?php echo date('M Y', strtotime($sheet['month'] . '-01')); ?></td>
                            <td>P:<?php echo $sheet['present_days']; ?> | L:<?php echo $sheet['leave_days']; ?> | A:<?php echo $sheet['absent_days']; ?> | W:<?php echo $sheet['working_days']; ?></td>
                            <td><?php echo formatCurrency($sheet['basic_salary']); ?></td>
                            <td><?php echo $sheet['pf_percentage']; ?>%</td>
                            <td><?php echo formatCurrency($sheet['pf_deduction']); ?></td>
                            <td><?php echo formatCurrency($sheet['bonus'] ?? 0); ?></td>
                            <td><?php echo formatCurrency($sheet['gross_salary']); ?></td>
                            <td class="fw-bold"><?php echo formatCurrency($sheet['net_payable']); ?></td>
                            <td>
                                <?php if ($sheet['confirmed'] === 1): ?>
                                    <span class="badge bg-success"><i class="bi bi-lock-fill me-1"></i>Confirmed</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="../public/profile.php?id=<?php echo $sheet['employee_id']; ?>" class="btn btn-outline-info" title="View Profile" target="_blank">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($sheet['confirmed'] === 0): ?>
                                        <a href="salary-list.php?delete=<?php echo $sheet['id']; ?>&month=<?php echo $selectedMonth; ?>" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="7" class="text-end">Total:</td>
                            <td><?php echo formatCurrency(array_sum(array_column($sheets, 'bonus'))); ?></td>
                            <td><?php echo formatCurrency(array_sum(array_column($sheets, 'gross_salary'))); ?></td>
                            <td><?php echo formatCurrency(array_sum(array_column($sheets, 'net_payable'))); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <?php
                $baseUrl = 'salary-list.php?' . http_build_query(array_filter([
                    'month' => $selectedMonth,
                    'office' => $filter['office'],
                    'department' => $filter['department'],
                    'unit' => $filter['unit'],
                    'position' => $filter['position'],
                    'confirmed_status' => $filter['confirmed_status'],
                    'search' => $filter['search']
                ], fn($v) => $v !== ''));
                renderPagination($pageNum, $totalPages, $baseUrl);
                ?>
            </div>
            <?php endif; ?>
        </div>
    </form>
    <?php
}
?>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.salary-checkbox').forEach(cb => cb.checked = this.checked);
});

function handlePrint(sel) {
    const val = sel.value;
    if (val === 'general') {
        document.querySelector('.bank-print-section').classList.add('no-print');
        document.querySelector('.bank-print-section').classList.remove('print-only');
        
        document.querySelector('.general-print-section').classList.remove('no-print');
        document.querySelector('.general-print-section').classList.add('print-only');
        window.print();
    }
    else if (val === 'bank_all') printBankSheet('all');
    else if (val === 'bank_confirmed') printBankSheet('confirmed');
    else if (val === 'bank_pending') printBankSheet('pending');
    sel.value = ""; // Reset
}

function printBankSheet(status) {
    const bankRows = document.querySelectorAll('.bank-row');
    const statusLabel = document.querySelector('.print-status-label');
    let total = 0;
    let sl = 1;

    bankRows.forEach(row => {
        const isConfirmed = row.getAttribute('data-confirmed') === '1';
        let show = false;

        if (status === 'all') show = true;
        else if (status === 'confirmed' && isConfirmed) show = true;
        else if (status === 'pending' && !isConfirmed) show = true;

        if (show) {
            row.style.display = '';
            row.cells[0].textContent = sl++;
            // Extract amount from net payable cell (strip currency symbol and commas)
            const amtText = row.cells[6].textContent.replace(/[^0-9.-]+/g, "");
            total += parseFloat(amtText) || 0;
        } else {
            row.style.display = 'none';
        }
    });

    statusLabel.textContent = 'Status: ' + status.charAt(0).toUpperCase() + status.slice(1);
    
    // Update total cell
    const currencySymbol = '<?php echo getSetting("currency_symbol", "৳"); ?>';
    document.getElementById('bank-total-cell').innerHTML = '<strong>' + currencySymbol + ' ' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</strong>';

    // Toggle print sections
    document.querySelector('.general-print-section').classList.add('no-print');
    document.querySelector('.general-print-section').classList.remove('print-only');
    
    document.querySelector('.bank-print-section').classList.remove('no-print');
    document.querySelector('.bank-print-section').classList.add('print-only');

    window.print();
}
</script>

<style>
.no-transform:hover { transform: none !important; box-shadow: none !important; }
.print-section { display: none; }
.no-print { display: none !important; }

@media print {
    @page { margin: 0.5cm; }
    .no-print { display: none !important; }
    .print-only { display: block !important; }

    .sidebar, .navbar, .page-header, .alert, .stat-card, .row.g-3, 
    form[method="GET"], form[method="POST"], .btn-group, .btn, .card { display: none !important; }

    .main-content { margin-left: 0 !important; padding: 0 !important; }
    body { background: white !important; }

    .print-section { width: 100%; margin: 0; padding: 10px; }
    .print-header { text-align: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #333; }
    .print-header h2 { margin: 0; font-size: 18px; }
    .print-header p { margin: 5px 0; font-size: 12px; font-weight: bold; }
    .print-header div { font-size: 10px; }

    table { width: 100%; font-size: 9px; border-collapse: collapse; margin-bottom: 10px; }
    table th, table td { padding: 4px; border: 1px solid #333; }
    table th { background: #ddd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-weight: bold; }
    table tfoot td { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-weight: bold; }

    .print-footer { margin-top: 15px; font-size: 9px; border-top: 1px solid #333; padding-top: 10px; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>