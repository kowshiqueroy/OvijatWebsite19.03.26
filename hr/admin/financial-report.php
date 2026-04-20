<?php
/**
 * Comprehensive Financial Report
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Financial Summary Report';
$currentPage = 'financial-report';

$filter = [
    'office' => $_GET['office'] ?? '',
    'department' => $_GET['department'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$showData = isset($_GET['office']) || isset($_GET['department']) || isset($_GET['status']) || isset($_GET['search']);
$reportData = [];
$conn = getDBConnection();

if ($showData) {
    // Comprehensive query for PF, Loans, Bonuses, and Salaries
    $sql = "SELECT 
                e.id, e.emp_name, e.office_name, e.office_code, e.department, e.dept_code, e.position, e.status,
                COALESCE(ss.total_net, 0) as total_net,
                COALESCE(ss.total_pf_deduction, 0) as salary_pf,
                COALESCE(pf_t.pf_manual, 0) as pf_manual,
                COALESCE(loan_t.loan_balance, 0) as loan_balance,
                COALESCE(bn.total_bonus, 0) as total_bonus
            FROM employees e
            LEFT JOIN (
                SELECT employee_id, SUM(net_payable) as total_net, SUM(pf_deduction) as total_pf_deduction
                FROM salary_sheets WHERE confirmed = 1 GROUP BY employee_id
            ) ss ON e.id = ss.employee_id
            LEFT JOIN (
                SELECT employee_id, SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END) as pf_manual
                FROM pf_transactions GROUP BY employee_id
            ) pf_t ON e.id = pf_t.employee_id
            LEFT JOIN (
                SELECT employee_id, SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END) as loan_balance
                FROM loan_transactions GROUP BY employee_id
            ) loan_t ON e.id = loan_t.employee_id
            LEFT JOIN (
                SELECT employee_id, SUM(bonus_amount) as total_bonus
                FROM bonus_sheets WHERE confirmed = 1 GROUP BY employee_id
            ) bn ON e.id = bn.employee_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filter['office'])) {
        $sql .= " AND e.office_name = ?";
        $params[] = $filter['office'];
        $types .= "s";
    }
    if (!empty($filter['department'])) {
        $sql .= " AND e.department = ?";
        $params[] = $filter['department'];
        $types .= "s";
    }
    if (!empty($filter['status'])) {
        $sql .= " AND e.status = ?";
        $params[] = $filter['status'];
        $types .= "s";
    }
    if (!empty($filter['search'])) {
        $sql .= " AND (e.emp_name LIKE ? OR e.id LIKE ?)";
        $searchParam = "%{$filter['search']}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "ss";
    }

    $sql .= " ORDER BY e.emp_name ASC";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['final_pf'] = $row['salary_pf'] + $row['pf_manual'];
        $reportData[] = $row;
    }
    $stmt->close();
}

$offices = getOfficeList();
$departments = getDepartmentList($filter['office']);

$companyName = getSetting('company_name') ?? 'My Company';
$companyTagline = getSetting('company_tagline') ?? '';
$companyAddress = getSetting('company_address') ?? '';
$companyLogo = getSetting('company_logo') ?? '';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3 no-print">
    <div>
        <h4 class="mb-1">Financial Summary Report</h4>
        <small class="text-muted">Consolidated report of Salaries, PF, Loans, and Bonuses</small>
    </div>
    <div class="d-flex gap-2">
        <?php if ($showData): ?>
            <button onclick="window.print()" class="btn btn-outline-primary">
                <i class="bi bi-printer me-1"></i> Print Report
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="" class="row g-2">
            <div class="col-md-3">
                <label class="form-label small mb-1">Office</label>
                <select name="office" class="form-select form-select-sm">
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
                <label class="form-label small mb-1">Department</label>
                <select name="department" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                            <?php echo $filter['department'] === $dept['department'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['Active', 'Inactive', 'Resigned', 'Terminated'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $filter['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Search Name/ID</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?php echo htmlspecialchars($filter['search']); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i> Generate Report</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$showData): ?>
    <div class="card no-print">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-funnel fs-1 d-block mb-3"></i>
            <p>Please apply filters to view the financial summary report.</p>
        </div>
    </div>
<?php else: ?>
    <!-- Print Header -->
    <div class="print-only mb-4">
        <div class="text-center pb-2 border-bottom border-2 border-dark">
            <?php if ($companyLogo): ?>
                <img src="../uploads/<?php echo htmlspecialchars($companyLogo); ?>" alt="Logo" style="height: 60px;" class="mb-2">
            <?php endif; ?>
            <h2 class="mb-0 fw-bold"><?php echo htmlspecialchars($companyName); ?></h2>
            <p class="mb-0"><?php echo htmlspecialchars($companyAddress); ?></p>
            <h4 class="mt-3 mb-1 fw-bold text-decoration-underline">FINANCIAL SUMMARY REPORT</h4>
            <div class="d-flex justify-content-center gap-3 small mt-2">
                <span><strong>Office:</strong> <?php echo $filter['office'] ?: 'All'; ?></span>
                <span><strong>Department:</strong> <?php echo $filter['department'] ?: 'All'; ?></span>
                <span><strong>Status:</strong> <?php echo $filter['status'] ?: 'All'; ?></span>
                <span><strong>Date:</strong> <?php echo date('d M Y'); ?></span>
            </div>
        </div>
    </div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center no-print">
        <h5 class="mb-0">Financial Summary List</h5>
        <span class="badge bg-primary"><?php echo count($reportData); ?> Records</span>
    </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0 report-table">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Employee Name</th>
                            <th>Position</th>
                            <th class="text-end">Total Salary</th>
                            <th class="text-end">Total Bonus</th>
                            <th class="text-end">Loan Balance</th>
                            <th class="text-end">PF Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $gTotalSalary = 0; $gTotalBonus = 0; $gTotalLoan = 0; $gTotalPF = 0;
                        if (empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No records found matching filters</td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData as $row): 
                                $gTotalSalary += $row['total_net'];
                                $gTotalBonus += $row['total_bonus'];
                                $gTotalLoan += $row['loan_balance'];
                                $gTotalPF += $row['final_pf'];
                            ?>
                            <tr>
                                <td>
                                    <small class="badge bg-dark">
                                        <?php echo generateEmployeeID($row['id'], $row['office_code'], $row['dept_code']); ?>
                                    </small>
                                </td>
                                <td><strong><?php echo htmlspecialchars($row['emp_name']); ?></strong></td>
                                <td><small><?php echo htmlspecialchars($row['position']); ?></small></td>
                                <td class="text-end"><?php echo number_format($row['total_net'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($row['total_bonus'], 2); ?></td>
                                <td class="text-end <?php echo $row['loan_balance'] > 0 ? 'text-danger' : ''; ?>">
                                    <?php echo number_format($row['loan_balance'], 2); ?>
                                </td>
                                <td class="text-end text-success fw-bold"><?php echo number_format($row['final_pf'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($reportData)): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="text-end">GRAND TOTAL:</td>
                            <td class="text-end"><?php echo number_format($gTotalSalary, 2); ?></td>
                            <td class="text-end"><?php echo number_format($gTotalBonus, 2); ?></td>
                            <td class="text-end text-danger"><?php echo number_format($gTotalLoan, 2); ?></td>
                            <td class="text-end text-primary"><?php echo number_format($gTotalPF, 2); ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
</div>
<?php endif; ?>

<style>
.report-table th { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
.report-table td { vertical-align: middle; }

@media print {
    @page { size: landscape; margin: 1cm; }
    .sidebar, .navbar, .no-print, .btn-group, .navbar-toggler, .page-header small { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-header h5 { font-size: 1.5rem; text-align: center; width: 100%; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
    .table-responsive { overflow: visible !important; }
    body { background: white !important; font-size: 10pt; }
    .table { border-collapse: collapse !important; width: 100% !important; border: 2px solid #000 !important; }
    .table th { background-color: #eee !important; color: #000 !important; border: 1px solid #000 !important; }
    .table td { border: 1px solid #000 !important; padding: 5px !important; }
    .table tfoot { background-color: #f9f9f9 !important; border-top: 2px solid #000 !important; }
    .badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
