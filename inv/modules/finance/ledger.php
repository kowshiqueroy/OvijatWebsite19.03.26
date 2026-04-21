<?php
/**
 * modules/finance/ledger.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Accountant']);

$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;

$branch_id = $_SESSION['branch_id'];
$stmt = $pdo->prepare("SELECT id, name FROM customers WHERE branch_id = ? AND is_deleted = 0 ORDER BY name ASC");
$stmt->execute([$branch_id]);
$customers = $stmt->fetchAll();

$ledger = [];
$customer_info = null;

if ($customer_id) {
    $stmt = $pdo->prepare("SELECT name, balance FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer_info = $stmt->fetch();

    $stmtL = $pdo->prepare("SELECT * FROM customer_ledger WHERE customer_id = ? ORDER BY created_at DESC");
    $stmtL->execute([$customer_id]);
    $ledger = $stmtL->fetchAll();
}
?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold">Select Customer to View Ledger</label>
                <select name="customer_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Choose Customer --</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $customer_id == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo $c['name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if ($customer_id && $customer_info): ?>
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Customer Ledger: <?php echo $customer_info['name']; ?></h5>
        <h5 class="mb-0 fw-bold">Current Balance: <span class="<?php echo $customer_info['balance'] < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo formatCurrency($customer_info['balance']); ?></span></h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="bg-light">
                        <th>Date</th>
                        <th>Description</th>
                        <th>Reference</th>
                        <th class="text-end">Debit (Invoice)</th>
                        <th class="text-end">Credit (Payment)</th>
                        <th class="text-end">Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $running_balance = 0;
                    foreach ($ledger as $entry): 
                        if ($entry['type'] == 'debit') {
                            $running_balance += $entry['amount'];
                        } else {
                            $running_balance -= $entry['amount'];
                        }
                    ?>
                    <tr>
                        <td><?php echo formatDate($entry['created_at']); ?></td>
                        <td><?php echo $entry['description']; ?></td>
                        <td>
                            <?php if ($entry['reference_id']): ?>
                                <a href="../sales/view.php?id=<?php echo $entry['reference_id']; ?>">#INV-<?php echo str_pad($entry['reference_id'], 5, '0', STR_PAD_LEFT); ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-danger"><?php echo $entry['type'] == 'debit' ? formatCurrency($entry['amount']) : '-'; ?></td>
                        <td class="text-end text-success"><?php echo $entry['type'] == 'credit' ? formatCurrency($entry['amount']) : '-'; ?></td>
                        <td class="text-end fw-bold"><?php echo formatCurrency($running_balance); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($ledger)): ?>
                    <tr><td colspan="6" class="text-center p-4">No transactions found for this customer.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($customer_id): ?>
    <div class="alert alert-warning">Customer not found.</div>
<?php else: ?>
    <div class="text-center p-5 bg-white border rounded">
        <i class="fas fa-file-invoice-dollar fa-4x text-muted mb-3"></i>
        <h5>Select a customer to view their detailed transaction history and balance.</h5>
    </div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
