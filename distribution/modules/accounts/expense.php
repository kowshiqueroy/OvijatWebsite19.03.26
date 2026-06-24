<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) redirect('modules/accounts/expense.php', 'CSRF failed.', 'danger');

    $category    = sanitize($_POST['category']);
    $description = sanitize($_POST['description'] ?? '');
    $amount      = floatval($_POST['amount']);
    $date        = $_POST['expense_date'];
    $account_id  = intval($_POST['account_id'] ?? 0); // expense GL account
    $pay_from    = intval($_POST['pay_from'] ?? 1);    // cash or bank account

    if ($amount <= 0) redirect('modules/accounts/expense.php', 'Amount must be positive.', 'danger');

    $conn = get_db_connection();
    $conn->begin_transaction();
    try {
        db_query("INSERT INTO expenses (category,description,amount,expense_date,account_id,recorded_by) VALUES (?,?,?,?,?,?)",
            [$category, $description, $amount, $date, $account_id, $_SESSION['user_id']]);
        $exp_id = $conn->insert_id;

        // Auto-post journal: DR Expense account / CR Cash or Bank
        if ($account_id && $pay_from) {
            post_journal(
                $date,
                "$category — $description",
                'Expense',
                $exp_id,
                [
                    ['account_id' => $account_id, 'dr' => $amount, 'cr' => 0,      'note' => $category],
                    ['account_id' => $pay_from,   'dr' => 0,       'cr' => $amount, 'note' => 'Cash/Bank payment'],
                ]
            );
        }

        $conn->commit();
        log_activity($_SESSION['user_id'], "Expense recorded: $category ৳$amount on $date");
        redirect('modules/accounts/expense.php', "Expense of " . format_currency($amount) . " recorded.");
    } catch (Exception $e) {
        $conn->rollback();
        redirect('modules/accounts/expense.php', "Error: " . $e->getMessage(), 'danger');
    }
}

$start  = $_GET['start'] ?? date('Y-m-01');
$end    = $_GET['end']   ?? date('Y-m-d');
$cat_filter = $_GET['category'] ?? '';

$sql    = "SELECT e.*, u.username as recorder FROM expenses e LEFT JOIN users u ON e.recorded_by=u.id WHERE e.isDelete=0 AND e.expense_date BETWEEN ? AND ?";
$params = [$start, $end];
if ($cat_filter) { $sql .= " AND e.category = ?"; $params[] = $cat_filter; }
$sql   .= " ORDER BY e.expense_date DESC";
$expenses = fetch_all($sql, $params);
$total    = array_sum(array_column($expenses, 'amount'));

// Expense GL accounts (nature = Expense in account_groups)
$exp_accounts = fetch_all("SELECT a.id, a.name, a.code FROM accounts a JOIN account_groups ag ON a.group_id=ag.id WHERE ag.nature='Expense' AND a.isDelete=0 AND a.is_active=1 ORDER BY a.name");
// Payment sources (Cash/Bank)
$pay_accounts = fetch_all("SELECT a.id, a.name FROM accounts a JOIN account_groups ag ON a.group_id=ag.id WHERE ag.name IN ('Cash & Bank') AND a.isDelete=0 ORDER BY a.name");

$categories = ['Transport','Office Supplies','Salary','Utilities','Marketing','Maintenance','Miscellaneous'];
$existing_cats = array_unique(array_column(fetch_all("SELECT DISTINCT category FROM expenses WHERE isDelete=0"), 'category'));
$all_cats = array_unique(array_merge($categories, $existing_cats));
sort($all_cats);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3>Expenses</h3>
        <p class="text-muted small mb-0">Record and track operational expenses.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpModal">
        <i class="fa-solid fa-plus me-2"></i>Add Expense
    </button>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small">From</label><input type="date" name="start" class="form-control form-control-sm" value="<?php echo $start; ?>"></div>
            <div class="col-md-3"><label class="form-label small">To</label><input type="date" name="end" class="form-control form-control-sm" value="<?php echo $end; ?>"></div>
            <div class="col-md-3"><label class="form-label small">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($all_cats as $c): ?><option value="<?php echo $c; ?>" <?php echo $cat_filter===$c?'selected':''; ?>><?php echo $c; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary btn-sm">Filter</button><a href="expense.php" class="btn btn-outline-secondary btn-sm">Reset</a></div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <span class="text-muted small"><?php echo count($expenses); ?> expenses found</span>
        <strong class="text-danger fs-5"><?php echo format_currency($total); ?></strong>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Date</th><th>Category</th><th>Description</th><th class="text-end">Amount</th><th>Recorded By</th></tr></thead>
                <tbody>
                    <?php foreach ($expenses as $e): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($e['expense_date'])); ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($e['category']); ?></span></td>
                        <td><?php echo htmlspecialchars($e['description']); ?></td>
                        <td class="text-end fw-bold text-danger"><?php echo format_currency($e['amount']); ?></td>
                        <td class="small text-muted"><?php echo htmlspecialchars($e['recorder'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No expenses in this period.</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($expenses)): ?>
                <tfoot class="table-secondary fw-bold">
                    <tr><td colspan="3" class="text-end">Total</td><td class="text-end text-danger"><?php echo format_currency($total); ?></td><td></td></tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title">Add Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount (৳) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" class="form-control" min="0.01" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <?php foreach ($all_cats as $c): ?><option value="<?php echo $c; ?>"><?php echo $c; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">GL Account</label>
                        <select name="account_id" class="form-select select2">
                            <option value="">— Select Account —</option>
                            <?php foreach ($exp_accounts as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?> (<?php echo $a['code']; ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pay From</label>
                        <select name="pay_from" class="form-select">
                            <?php foreach ($pay_accounts as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option><?php endforeach; ?>
                            <option value="1">Cash in Hand</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Office rent for June">
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Expense</button></div>
        </form>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
