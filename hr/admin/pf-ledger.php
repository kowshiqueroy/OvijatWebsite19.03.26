<?php
define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'PF Ledger';
$currentPage = 'pf-ledger';

$message = '';
$messageType = 'success';

$conn = getDBConnection();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }
    if (isset($_POST['add_transaction'])) {
        $empId   = (int)$_POST['employee_id'];
        $type    = in_array($_POST['type'], ['credit','debit']) ? $_POST['type'] : 'credit';
        $amount  = max(0.01, (float)$_POST['amount']);
        $date    = $_POST['transaction_date'];
        $desc    = sanitize($_POST['description'] ?? '');
        $adminId = $_SESSION['admin_id'];

        $stmt = $conn->prepare("INSERT INTO pf_transactions (employee_id, transaction_date, type, amount, description, created_by) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("issdsi", $empId, $date, $type, $amount, $desc, $adminId);
        if ($stmt->execute()) {
            $message = 'PF transaction added';
        } else {
            $message = 'Error adding transaction';
            $messageType = 'danger';
        }
        $stmt->close();
    }
    if (isset($_POST['delete_transaction'])) {
        $tid = (int)$_POST['transaction_id'];
        $stmt = $conn->prepare("DELETE FROM pf_transactions WHERE id = ?");
        $stmt->bind_param("i", $tid);
        $stmt->execute();
        $message = 'Transaction deleted';
        $stmt->close();
    }
}

$selectedEmpId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
$selectedEmployee = $selectedEmpId ? getEmployeeById($selectedEmpId) : null;

// Employee list with PF balance from confirmed salary sheets
$empResult = $conn->query(
    "SELECT e.id, e.emp_name, e.department, e.position, e.office_code, e.dept_code,
            COALESCE(SUM(ss.pf_deduction), 0) as pf_balance
     FROM employees e
     LEFT JOIN salary_sheets ss ON ss.employee_id = e.id AND ss.confirmed = 1
     WHERE e.status = 'Active'
     GROUP BY e.id
     ORDER BY pf_balance DESC, e.emp_name ASC"
);
$employeesWithBalance = [];
while ($row = $empResult->fetch_assoc()) {
    $employeesWithBalance[] = $row;
}

// Detail data for selected employee
$salaryPFRows = [];
$manualTransactions = [];
if ($selectedEmpId) {
    $stmt = $conn->prepare("SELECT month, pf_deduction, confirmed FROM salary_sheets WHERE employee_id = ? ORDER BY month DESC");
    $stmt->bind_param("i", $selectedEmpId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $salaryPFRows[] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM pf_transactions WHERE employee_id = ? ORDER BY transaction_date DESC, id DESC");
    $stmt->bind_param("i", $selectedEmpId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $manualTransactions[] = $row;
    }
    $stmt->close();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">PF Ledger</h4>
        <small class="text-muted">Employee provident fund transactions</small>
    </div>
</div>

<?php if ($message): ?><?php showAlert($message, $messageType); ?><?php endif; ?>

<div class="row g-4">
    <!-- Employee list -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-people me-2"></i>Employees</h5></div>
            <div class="card-body pb-0 pt-2 px-2">
                <input type="text" id="empSearch" class="form-control form-control-sm mb-2" placeholder="Search employee...">
            </div>
            <div class="card-body p-0" style="max-height:560px;overflow-y:auto;">
                <div class="list-group list-group-flush" id="empList">
                    <?php foreach ($employeesWithBalance as $emp): ?>
                        <a href="pf-ledger.php?employee_id=<?php echo $emp['id']; ?>"
                           class="list-group-item list-group-item-action emp-item <?php echo $selectedEmpId === $emp['id'] ? 'active' : ''; ?>"
                           data-name="<?php echo strtolower(htmlspecialchars($emp['emp_name'])); ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($emp['emp_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($emp['department']); ?></small>
                                </div>
                                <span class="badge bg-primary"><?php echo number_format($emp['pf_balance'], 2); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($employeesWithBalance)): ?>
                        <div class="p-3 text-muted text-center">No active employees</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if (!$selectedEmployee): ?>
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-piggy-bank fs-1 d-block mb-3"></i>
                    <p>Select an employee to view PF ledger and add manual adjustments.</p>
                </div>
            </div>
        <?php else: ?>
            <?php
            $pfFromSalary   = array_sum(array_column(array_filter($salaryPFRows, fn($r) => $r['confirmed'] == 1), 'pf_deduction'));
            $pfManualCredit = array_sum(array_column(array_filter($manualTransactions, fn($t) => $t['type'] === 'credit'), 'amount'));
            $pfManualDebit  = array_sum(array_column(array_filter($manualTransactions, fn($t) => $t['type'] === 'debit'),  'amount'));
            $totalPF = $pfFromSalary + $pfManualCredit - $pfManualDebit;
            ?>

            <!-- Summary -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-center mb-3">
                        <div class="col">
                            <h5 class="mb-0"><?php echo htmlspecialchars($selectedEmployee['emp_name']); ?></h5>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($selectedEmployee['department']); ?> |
                                <?php echo htmlspecialchars($selectedEmployee['position']); ?>
                            </small>
                        </div>
                    </div>
                    <div class="row text-center g-3">
                        <div class="col-4">
                            <div class="p-2 bg-light rounded">
                                <small class="text-muted d-block">Salary Deductions</small>
                                <h5 class="mb-0 text-primary"><?php echo number_format($pfFromSalary, 2); ?></h5>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-light rounded">
                                <small class="text-muted d-block">Manual Adjustments</small>
                                <h6 class="mb-0">
                                    <span class="text-success">+<?php echo number_format($pfManualCredit, 2); ?></span>
                                    &nbsp;/&nbsp;
                                    <span class="text-danger">-<?php echo number_format($pfManualDebit, 2); ?></span>
                                </h6>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-primary text-white rounded">
                                <small class="d-block" style="opacity:.8">Total PF Balance</small>
                                <h5 class="mb-0"><?php echo number_format($totalPF, 2); ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manual adjustment form -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Manual Adjustment</h6></div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="employee_id" value="<?php echo $selectedEmpId; ?>">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <select name="type" class="form-select" required>
                                    <option value="credit">Credit (Deposit)</option>
                                    <option value="debit">Debit (Withdrawal)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="amount" class="form-control" placeholder="Amount" step="0.01" min="0.01" required>
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="transaction_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="description" class="form-control" placeholder="Description (optional)" maxlength="255">
                            </div>
                            <div class="col-12">
                                <button type="submit" name="add_transaction" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus me-1"></i> Add
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- PF from salary sheets -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-currency-dollar me-2"></i>PF from Salary Sheets</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Month</th><th>PF Deducted</th><th>Sheet Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($salaryPFRows)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">No salary sheets yet</td></tr>
                            <?php else: ?>
                                <?php foreach ($salaryPFRows as $row): ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                                    <td><?php echo number_format($row['pf_deduction'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo $row['confirmed'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $row['confirmed'] ? 'Confirmed' : 'Pending'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Manual transactions -->
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Manual Transactions</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Date</th><th>Type</th><th>Amount</th><th>Description</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($manualTransactions)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No manual transactions</td></tr>
                            <?php else: ?>
                                <?php foreach ($manualTransactions as $t): ?>
                                <tr>
                                    <td><?php echo $t['transaction_date']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $t['type'] === 'credit' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo ucfirst($t['type']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold"><?php echo number_format($t['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($t['description'] ?? ''); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="transaction_id" value="<?php echo $t['id']; ?>">
                                            <input type="hidden" name="employee_id" value="<?php echo $selectedEmpId; ?>">
                                            <button type="submit" name="delete_transaction" class="btn btn-outline-danger btn-sm"
                                                    onclick="return confirm('Delete this transaction?');">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('empSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#empList .emp-item').forEach(el => {
        el.style.display = el.dataset.name.includes(q) ? '' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
