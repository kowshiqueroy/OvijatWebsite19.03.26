<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT, ROLE_VIEWER]);

// Customers see only their own, others see all
if ($_SESSION['role'] == ROLE_CUSTOMER) {
    $my_cust = fetch_one("SELECT id FROM customers WHERE user_id = ? AND isDelete = 0", [$_SESSION['user_id']]);
    $transactions = fetch_all("SELECT t.*, c.name as customer_name FROM transactions t JOIN customers c ON t.customer_id = c.id WHERE t.customer_id = ? AND t.isDelete = 0 AND c.isDelete = 0 ORDER BY t.created_at DESC", [$my_cust['id']]);
} else {
    $transactions = fetch_all("SELECT t.*, c.name as customer_name FROM transactions t JOIN customers c ON t.customer_id = c.id WHERE t.isDelete = 0 AND c.isDelete = 0 ORDER BY t.created_at DESC");
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h3>Transaction History</h3>
        <p class="text-muted">Global ledger of all credits and debits.</p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <?php if (in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_ACCOUNTANT])): ?>
                        <th>Print Status</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                    <tr class="<?php echo $t['hide_from_print'] ? 'table-warning opacity-75' : ''; ?>">
                        <td><?php echo date('d M Y, h:i A', strtotime($t['created_at'])); ?></td>
                        <td><strong><?php echo $t['customer_name']; ?></strong></td>
                        <td>
                            <span class="badge <?php echo $t['type'] == 'Credit' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo strtoupper($t['type']); ?>
                            </span>
                        </td>
                        <td class="fw-bold"><?php echo format_currency($t['amount']); ?></td>
                        <td>
                            <?php echo $t['description']; ?>
                        </td>
                        <?php if (in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_ACCOUNTANT])): ?>
                        <td>
                            <a href="toggle_print.php?id=<?php echo $t['id']; ?>" class="btn btn-sm <?php echo $t['hide_from_print'] ? 'btn-danger' : 'btn-outline-secondary'; ?>">
                                <i class="fas <?php echo $t['hide_from_print'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                <?php echo $t['hide_from_print'] ? 'Hidden' : 'Visible'; ?>
                            </a>
                            <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                                <a href="../admin/delete_record.php?table=transactions&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Delete this transaction? Note: This will NOT revert the customer balance automatically.')"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
