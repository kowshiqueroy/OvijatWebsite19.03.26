<?php
/**
 * Employee List Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Employees';
$currentPage = 'employees';

$filter = [
    'office' => $_GET['office'] ?? '',
    'department' => $_GET['department'] ?? '',
    'unit' => $_GET['unit'] ?? '',
    'position' => $_GET['position'] ?? '',
    'employee_type' => $_GET['employee_type'] ?? '',
    'status' => $_GET['status'] ?? ''
];

$filterSubmitted = isset($_GET['office']);

$perPage = 50;
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$totalEmployees = 0;
$totalPages = 1;
$employees = [];
$balances = [];
if ($filterSubmitted) {
    $totalEmployees = countAllEmployees($filter);
    $totalPages = $totalEmployees > 0 ? (int)ceil($totalEmployees / $perPage) : 1;
    $employees = getAllEmployees($filter, $perPage, ($pageNum - 1) * $perPage);
    $employeeIds = array_column($employees, 'id');
    $balances = !empty($employeeIds) ? getBatchEmployeeBalances($employeeIds) : [];
}

// CSV export — must run before any HTML output
if ($filterSubmitted && isset($_GET['export']) && $_GET['export'] === 'csv') {
    // For CSV we need all employees (no pagination limit)
    $allEmployees = getAllEmployees($filter);
    $empIds = array_column($allEmployees, 'id');
    $balances = getBatchEmployeeBalances($empIds);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="employees_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Emp ID','Name','Office','Department','Unit','Position','Type','Basic Salary','PF %','Joining Date','Status','Loan Balance','PF Balance']);
    foreach ($allEmployees as $emp) {
        $bal = $balances[$emp['id']] ?? ['loan_balance' => 0, 'pf_balance' => 0];
        fputcsv($out, [
            generateEmployeeID($emp['id'], $emp['office_code'], $emp['dept_code']),
            $emp['emp_name'],
            $emp['office_name'],
            $emp['department'],
            $emp['unit'] ?? '',
            $emp['position'],
            $emp['employee_type'],
            $emp['basic_salary'],
            $emp['pf_percentage'],
            $emp['joining_date'] ?? '',
            $emp['status'],
            number_format($bal['loan_balance'], 2),
            number_format($bal['pf_balance'], 2),
        ]);
    }
    fclose($out);
    exit;
}

$offices = getOfficeList();
$departments = getDepartmentList($filter['office']);
$units = getUnitList($filter['department']);
$positions = getPositionList($filter['department']);

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn = getDBConnection();
    
    $empStmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
    $empStmt->bind_param("i", $id);
    $empStmt->execute();
    $empResult = $empStmt->get_result();
    $empData = $empResult->fetch_assoc();
    $empStmt->close();
    
    $empName = $empData['emp_name'] ?? 'Unknown';
    $empDetails = json_encode($empData);
    
    $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity('delete', 'employee', $id, "Deleted: $empName | Data: " . $empDetails);
        header('Location: employees.php?msg=deleted');
        exit;
    }
}

if (isset($_GET['msg'])) {
    $messages = [
        'deleted' => 'Employee deleted successfully',
        'updated' => 'Employee updated successfully'
    ];
    $msg = $messages[$_GET['msg']] ?? '';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h4 class="mb-1">Employees</h4>
        <small class="text-muted">Manage employee records</small>
    </div>
    <div class="d-flex gap-2">
        <?php if ($filterSubmitted && !empty($employees)): ?>
        <a href="employees.php?<?php echo http_build_query(array_filter($filter)); ?>&export=csv" class="btn btn-outline-success">
            <i class="bi bi-download me-1"></i> Download CSV
        </a>
        <?php endif; ?>
        <a href="employee-import.php" class="btn btn-success">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Import CSV
        </a>
        <a href="employee-add.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Employee
        </a>
    </div>
</div>

<?php if (!empty($msg)): ?>
    <?php showAlert($msg); ?>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4 col-lg-2">
                <select name="office" class="form-select filter-select" data-target="department">
                    <option value="">All Offices</option>
                    <?php foreach ($offices as $off): ?>
                        <option value="<?php echo htmlspecialchars($off['office_name']); ?>" 
                            <?php echo $filter['office'] === $off['office_name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($off['office_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
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
            <div class="col-md-4 col-lg-2">
                <select name="unit" class="form-select filter-select">
                    <option value="">All Units</option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?php echo htmlspecialchars($unit); ?>"
                            <?php echo $filter['unit'] === $unit ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($unit); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <select name="position" class="form-select filter-select">
                    <option value="">All Positions</option>
                    <?php foreach ($positions as $pos): ?>
                        <option value="<?php echo htmlspecialchars($pos); ?>"
                            <?php echo $filter['position'] === $pos ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pos); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <select name="employee_type" class="form-select">
                    <option value="">All Types</option>
                    <option value="Staff" <?php echo $filter['employee_type'] === 'Staff' ? 'selected' : ''; ?>>Staff</option>
                    <option value="Worker" <?php echo $filter['employee_type'] === 'Worker' ? 'selected' : ''; ?>>Worker</option>
                    <option value="Intern" <?php echo $filter['employee_type'] === 'Intern' ? 'selected' : ''; ?>>Intern</option>
                    <option value="Contextual" <?php echo $filter['employee_type'] === 'Contextual' ? 'selected' : ''; ?>>Contextual</option>
                    <option value="Others" <?php echo $filter['employee_type'] === 'Others' ? 'selected' : ''; ?>>Others</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="Active" <?php echo $filter['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $filter['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Resigned" <?php echo $filter['status'] === 'Resigned' ? 'selected' : ''; ?>>Resigned</option>
                    <option value="Terminated" <?php echo $filter['status'] === 'Terminated' ? 'selected' : ''; ?>>Terminated</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
                <a href="employees.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

<?php if (!$filterSubmitted): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-funnel fs-1 d-block mb-3"></i>
        <p class="mb-0">Select filters above and click <strong>Filter</strong> to view employees.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Employee Records (<?php echo $totalEmployees; ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Office</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Type</th>
                        <th>Basic Salary</th>
                        <th>PF Balance</th>
                        <th>Loan Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">No employees found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark">
                                        <?php echo generateEmployeeID($emp['id'], $emp['office_code'], $emp['dept_code']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($emp['photo']): ?>
                                        <img src="../uploads/photos/<?php echo htmlspecialchars($emp['photo']); ?>"
                                             alt="" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center"
                                             style="width: 40px; height: 40px;">
                                            <i class="bi bi-person text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($emp['emp_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($emp['unit'] ?? 'N/A'); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($emp['office_name']); ?></td>
                                <td><?php echo htmlspecialchars($emp['department']); ?></td>
                                <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $emp['employee_type']; ?></span>
                                </td>
                                <td><?php echo formatCurrency($emp['basic_salary']); ?></td>
                                <?php $bal = $balances[$emp['id']] ?? ['pf_balance' => 0, 'loan_balance' => 0]; ?>
                                <td class="text-success"><?php echo number_format($bal['pf_balance'], 2); ?></td>
                                <td class="<?php echo $bal['loan_balance'] > 0 ? 'text-danger' : 'text-muted'; ?>">
                                    <?php echo number_format($bal['loan_balance'], 2); ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($emp['status']); ?>">
                                        <?php echo $emp['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="employee-add.php?edit=<?php echo $emp['id']; ?>"
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="../public/profile.php?id=<?php echo $emp['id']; ?>"
                                           class="btn btn-outline-info" title="View Profile" target="_blank">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="employees.php?delete=<?php echo $emp['id']; ?>"
                                           class="btn btn-outline-danger" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this employee?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer">
        <?php
        $baseUrl = 'employees.php?' . http_build_query(array_filter($filter));
        renderPagination($pageNum, $totalPages, $baseUrl);
        ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
