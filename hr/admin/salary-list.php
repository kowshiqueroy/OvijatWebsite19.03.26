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

$perPage = 50;
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$totalSheets = (!empty($selectedMonth)) ? countSalarySheets($employeeId, $selectedMonth, $filter) : 0;
$totalPages = $totalSheets > 0 ? (int)ceil($totalSheets / $perPage) : 1;
$sheets = getSalarySheets($employeeId, $selectedMonth, $filter, $perPage, ($pageNum - 1) * $perPage);
$months = getSalaryMonths();
$offices = getOfficeList();
$departments = getDepartmentList($filter['office']);

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
    
    $stmt = $conn->prepare("SELECT confirmed FROM salary_sheets WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sheet = $result->fetch_assoc();
    $stmt->close();
    
    if ($sheet && $sheet['confirmed'] == 1) {
        $message = 'Cannot delete confirmed salary sheet.';
        $messageType = 'danger';
    } else {
        $stmt = $conn->prepare("DELETE FROM salary_sheets WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
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
                <select name="month" class="form-select" required>
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
                <select name="office" class="form-select filter-select">
                    <option value="">All Offices</option>
                    <?php foreach ($offices as $off): ?>
                        <option value="<?php echo htmlspecialchars($off['office_name']); ?>" <?php echo $filter['office'] === $off['office_name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($off['office_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="department" class="form-select filter-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $filter['department'] === $dept['department'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i> Filter</button>
                <a href="salary-list.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i> Clear</a>
            </div>
            <?php if (!empty($selectedMonth) && count($sheets) > 0): ?>
                <div class="col-md-2">
                    <button type="button" class="btn btn-success w-100" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
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
                    <th>ID</th>
                    <th>Employee</th>
                    <th>Attendance</th>
                    <th>Basic</th>
                    <th>PF%</th>
                    <th>PF Ded</th>
                    <th>Bonus</th>
                    <th>Gross</th>
                    <th>Net</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sheets as $sheet): ?>
                <tr>
                    <td><?php echo generateEmployeeID($sheet['id'], $sheet['office_code'], $sheet['dept_code']); ?></td>
                    <td><?php echo htmlspecialchars($sheet['emp_name']); ?><br><small><?php echo htmlspecialchars($sheet['department']); ?></small></td>
                    <td>P:<?php echo $sheet['present_days']; ?> L:<?php echo $sheet['leave_days']; ?> A:<?php echo $sheet['absent_days']; ?></td>
                    <td>$<?php echo formatCurrency($sheet['basic_salary']); ?></td>
                    <td><?php echo $sheet['pf_percentage']; ?>%</td>
                    <td>$<?php echo formatCurrency($sheet['pf_deduction']); ?></td>
                    <td>$<?php echo formatCurrency($sheet['bonus'] ?? 0); ?></td>
                    <td>$<?php echo formatCurrency($sheet['gross_salary']); ?></td>
                    <td>$<?php echo formatCurrency($sheet['net_payable']); ?></td>
                    <td><?php echo $sheet['confirmed'] ? 'Confirmed' : 'Pending'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" style="text-align:right"><strong>Total:</strong></td>
                    <td>$<?php echo formatCurrency(array_sum(array_column($sheets, 'bonus'))); ?></td>
                    <td>$<?php echo formatCurrency(array_sum(array_column($sheets, 'gross_salary'))); ?></td>
                    <td>$<?php echo formatCurrency(array_sum(array_column($sheets, 'net_payable'))); ?></td>
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
                        <?php if ($confirmedCount > 0): ?>
                        <button type="submit" name="bulk_unconfirm" class="btn btn-warning" onclick="return confirm('Unconfirm selected entries? This will unlock them for editing.');">
                            <i class="bi bi-unlock me-1"></i> Unconfirm Selected
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
                $baseUrl = 'salary-list.php?month=' . urlencode($selectedMonth) .
                    (!empty($filter['office']) ? '&office=' . urlencode($filter['office']) : '') .
                    (!empty($filter['department']) ? '&department=' . urlencode($filter['department']) : '');
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
</script>

<style>
.print-section { display: none; }

@media print {
    @page { margin: 0.5cm; }

    /* Hide all page chrome and non-print content using display:none
       so they take up zero space and cause no extra blank pages */
    .sidebar,
    .navbar,
    .page-header,
    .alert,
    .row.g-3,
    form[method="GET"],
    form[method="POST"] { display: none !important; }

    .main-content { margin-left: 0 !important; padding: 0 !important; }

    body { background: white !important; }

    /* Show the print section */
    .print-section {
        display: block !important;
        width: 100%;
        margin: 0;
        padding: 10px;
    }

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