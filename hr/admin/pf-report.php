<?php
/**
 * PF Balance Report
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'PF Balance Report';
$currentPage = 'pf-report';

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
    // Build query to get all employees and their PF components
    $sql = "SELECT 
                e.id, e.emp_name, e.office_name, e.office_code, e.department, e.dept_code, e.position, e.status, e.pf_percentage,
                COALESCE(ss.salary_pf, 0) as salary_pf,
                COALESCE(tr.credit_pf, 0) as credit_pf,
                COALESCE(tr.debit_pf, 0) as debit_pf
            FROM employees e
            LEFT JOIN (
                SELECT employee_id, SUM(pf_deduction) as salary_pf
                FROM salary_sheets
                WHERE confirmed = 1
                GROUP BY employee_id
            ) ss ON e.id = ss.employee_id
            LEFT JOIN (
                SELECT 
                    employee_id,
                    SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as credit_pf,
                    SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) as debit_pf
                FROM pf_transactions
                GROUP BY employee_id
            ) tr ON e.id = tr.employee_id
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
        $row['total_pf'] = $row['salary_pf'] + $row['credit_pf'] - $row['debit_pf'];
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
        <h4 class="mb-1">PF Balance Report</h4>
        <small class="text-muted">Consolidated Providend Fund balances for all employees</small>
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
            <div class="col-md-3">
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
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$showData): ?>
    <div class="card no-print">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-funnel fs-1 d-block mb-3"></i>
            <p>Please apply filters to view the PF balance report.</p>
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
            <h4 class="mt-3 mb-1 fw-bold text-decoration-underline">PF BALANCE REPORT</h4>
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
        <h5 class="mb-0">PF Summary List</h5>
        <span class="badge bg-primary"><?php echo count($reportData); ?> Employees</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Employee Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th class="text-end">Salary PF</th>
                        <th class="text-end">Manual Adj.</th>
                        <th class="text-end">Total Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalSalaryPF = 0;
                    $totalManualPF = 0;
                    $totalBalance = 0;
                    if (empty($reportData)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No records found matching filters</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportData as $row): 
                            $manualAdj = $row['credit_pf'] - $row['debit_pf'];
                            $totalSalaryPF += $row['salary_pf'];
                            $totalManualPF += $manualAdj;
                            $totalBalance += $row['total_pf'];
                        ?>
                        <tr>
                            <td>
                                <small class="badge bg-dark">
                                    <?php echo generateEmployeeID($row['id'], $row['office_code'], $row['dept_code']); ?>
                                </small>
                            </td>
                            <td><strong><?php echo htmlspecialchars($row['emp_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                            <td><?php echo htmlspecialchars($row['position']); ?></td>
                            <td class="text-end"><?php echo number_format($row['salary_pf'], 2); ?></td>
                            <td class="text-end <?php echo $manualAdj >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($manualAdj > 0 ? '+' : '') . number_format($manualAdj, 2); ?>
                            </td>
                            <td class="text-end fw-bold"><?php echo number_format($row['total_pf'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($reportData)): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">GRAND TOTAL:</td>
                        <td class="text-end"><?php echo number_format($totalSalaryPF, 2); ?></td>
                        <td class="text-end <?php echo $totalManualPF >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format($totalManualPF, 2); ?>
                        </td>
                        <td class="text-end text-primary"><?php echo number_format($totalBalance, 2); ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<style>
@media print {
    .sidebar, .navbar, .no-print, .btn-group, .navbar-toggler { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
    .table-responsive { overflow: visible !important; }
    body { background: white !important; }
    .page-header { margin-bottom: 30px !important; border-bottom: 2px solid #333 !important; border-radius: 0 !important; }
    .table { border-collapse: collapse !important; width: 100% !important; }
    .table th, .table td { border: 1px solid #000 !important; padding: 8px !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
