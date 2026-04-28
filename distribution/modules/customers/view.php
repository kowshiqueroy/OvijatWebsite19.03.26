<?php
require_once '../../templates/header.php';
check_login();

$id = $_GET['id'] ?? 0;

// Security: Customers can only view themselves
if ($_SESSION['role'] == ROLE_CUSTOMER) {
    $my_cust = fetch_one("SELECT id FROM customers WHERE user_id = ?", [$_SESSION['user_id']]);
    if ($id != $my_cust['id']) {
        redirect('index.php', 'Unauthorized access.', 'danger');
    }
}

$customer = fetch_one("SELECT * FROM customers WHERE id = ?", [$id]);
if (!$customer) redirect('modules/customers/index.php', 'Customer not found.', 'danger');

$transactions = fetch_all("SELECT * FROM transactions WHERE customer_id = ? ORDER BY created_at DESC", [$id]);
$sales = fetch_all("SELECT * FROM sales_drafts WHERE customer_id = ? ORDER BY created_at DESC", [$id]);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3>Customer: <?php echo $customer['name']; ?></h3>
        <p class="text-muted"><?php echo $customer['phone']; ?> | <?php echo $customer['address']; ?></p>
    </div>
    <div class="col-md-4 text-end">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <small>Current Balance</small>
                <h2 class="mb-0"><?php echo format_currency($customer['balance']); ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <ul class="nav nav-tabs mb-3" id="customerTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#trans">Transactions</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sales">Sales History</button>
            </li>
        </ul>
        <div class="tab-content card shadow-sm p-3">
            <div class="tab-pane fade show active" id="trans">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><?php echo date('d M y', strtotime($t['created_at'])); ?></td>
                            <td><span class="badge <?php echo $t['type'] == 'Credit' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $t['type']; ?></span></td>
                            <td><?php echo format_currency($t['amount']); ?></td>
                            <td><?php echo $t['description']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="tab-pane fade" id="sales">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $s): ?>
                        <tr>
                            <td>#<?php echo $s['id']; ?></td>
                            <td><?php echo date('d M y', strtotime($s['created_at'])); ?></td>
                            <td><?php echo format_currency($s['grand_total']); ?></td>
                            <td><?php echo $s['status']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php if (in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_ACCOUNTANT])): ?>
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Adjust Balance</strong></div>
            <div class="card-body">
                <form action="update_balance.php" method="POST">
                    <input type="hidden" name="customer_id" value="<?php echo $id; ?>">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="trans_type" class="form-control" required>
                            <option value="Credit">Credit (Received Money)</option>
                            <option value="Debit">Debit (Increased Debt)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Reason for adjustment..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning w-100">Post Transaction</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../templates/footer.php'; ?>
