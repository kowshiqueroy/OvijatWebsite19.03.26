<?php
define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Loan Management';
$currentPage = 'loans';

$message = '';
$messageType = 'success';

$conn = getDBConnection();

// Handle POST: add transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }
    if (isset($_POST['add_transaction'])) {
        $empId  = (int)$_POST['employee_id'];
        $type   = in_array($_POST['type'], ['debit','credit']) ? $_POST['type'] : 'debit';
        $amount = max(0.01, (float)$_POST['amount']);
        $date   = $_POST['transaction_date'];
        $desc   = sanitize($_POST['description'] ?? '');
        $adminId = $_SESSION['admin_id'];

        $stmt = $conn->prepare("INSERT INTO loan_transactions (employee_id, transaction_date, type, amount, description, created_by) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("issdsi", $empId, $date, $type, $amount, $desc, $adminId);
        if ($stmt->execute()) {
            $message = 'Transaction added successfully';
        } else {
            $message = 'Error adding transaction';
            $messageType = 'danger';
        }
        $stmt->close();
    }
    if (isset($_POST['delete_transaction'])) {
        $tid = (int)$_POST['transaction_id'];
        $stmt = $conn->prepare("DELETE FROM loan_transactions WHERE id = ?");
        $stmt->bind_param("i", $tid);
        $stmt->execute();
        $message = 'Transaction deleted';
        $stmt->close();
    }
}

// Selected employee
$selectedEmpId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
$selectedEmployee = $selectedEmpId ? getEmployeeById($selectedEmpId) : null;

// Load employee list with loan balances
$empResult = $conn->query(
    "SELECT e.id, e.emp_name, e.office_name, e.department, e.position, e.office_code, e.dept_code, e.status,
            COALESCE(SUM(CASE WHEN lt.type='debit' THEN lt.amount ELSE -lt.amount END), 0) as loan_balance
     FROM employees e
     LEFT JOIN loan_transactions lt ON lt.employee_id = e.id
     WHERE e.status = 'Active'
     GROUP BY e.id
     ORDER BY loan_balance DESC, e.emp_name ASC"
);
$employeesWithBalance = [];
while ($row = $empResult->fetch_assoc()) {
    $employeesWithBalance[] = $row;
}

// Load transactions for selected employee
$transactions = [];
if ($selectedEmpId) {
    $stmt = $conn->prepare("SELECT * FROM loan_transactions WHERE employee_id = ? ORDER BY transaction_date DESC, id DESC");
    $stmt->bind_param("i", $selectedEmpId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $transactions[] = $row;
    }
    $stmt->close();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">Loan Management</h4>
        <small class="text-muted">Employee loan debit &amp; credit transactions</small>
    </div>
</div>

<?php if ($message): ?><?php showAlert($message, $messageType); ?><?php endif; ?>

<div class="row g-4">
    <!-- Employee list -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-people me-2"></i>Employees</h5></div>
            <div class="card-body p-0" style="max-height:600px;overflow-y:auto;">
                <div class="list-group list-group-flush">
                    <?php foreach ($employeesWithBalance as $emp): ?>
                        <a href="loan.php?employee_id=<?php echo $emp['id']; ?>"
                           class="list-group-item list-group-item-action <?php echo $selectedEmpId === $emp['id'] ? 'active' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($emp['emp_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($emp['department']); ?></small>
                                </div>
                                <span class="badge <?php echo $emp['loan_balance'] > 0 ? 'bg-danger' : 'bg-success'; ?>">
                                    <?php echo number_format($emp['loan_balance'], 2); ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if (!$selectedEmployee): ?>
            <div class="card"><div class="card-body text-center text-muted py-5">
                <i class="bi bi-person-fill fs-1 d-block mb-3"></i>
                <p>Select an employee to view loan history and add transactions.</p>
            </div></div>
        <?php else: ?>
            <?php
            $loanBalance = array_sum(array_map(fn($t) => $t['type'] === 'debit' ? $t['amount'] : -$t['amount'], $transactions));
            ?>
            <!-- Summary -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0"><?php echo htmlspecialchars($selectedEmployee['emp_name']); ?></h5>
                            <small class="text-muted"><?php echo htmlspecialchars($selectedEmployee['department']); ?> | <?php echo htmlspecialchars($selectedEmployee['position']); ?></small>
                        </div>
                        <div class="col-auto text-end">
                            <small class="text-muted d-block">Outstanding Balance</small>
                            <h4 class="mb-0 <?php echo $loanBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo number_format($loanBalance, 2); ?>
                            </h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add transaction form -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Add Transaction</h6></div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="employee_id" value="<?php echo $selectedEmpId; ?>">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <select name="type" class="form-select" required>
                                    <option value="debit">Debit (Loan Given)</option>
                                    <option value="credit">Credit (Repayment)</option>
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

            <!-- Transactions list -->
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Transaction History</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Date</th><th>Type</th><th>Amount</th><th>Description</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No transactions yet</td></tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td><?php echo $t['transaction_date']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $t['type'] === 'debit' ? 'bg-danger' : 'bg-success'; ?>">
                                            <?php echo ucfirst($t['type']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold"><?php echo number_format($t['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($t['description'] ?? ''); ?></td>
                                    <td>
                                        <form method="POST" action="" style="display:inline">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
