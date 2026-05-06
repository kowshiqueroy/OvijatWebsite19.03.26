<?php
require_once '../../templates/header.php';
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_SR, ROLE_CUSTOMER, ROLE_VIEWER]);

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Filter based on role
if ($role == ROLE_CUSTOMER) {
    $cust = fetch_one("SELECT id FROM customers WHERE user_id = ? AND isDelete = 0", [$user_id]);
    $sales = fetch_all("SELECT s.*, c.name as customer_name FROM sales_drafts s JOIN customers c ON s.customer_id = c.id WHERE s.customer_id = ? AND s.isDelete = 0 AND c.isDelete = 0 ORDER BY s.created_at DESC", [$cust['id']]);
} elseif ($role == ROLE_SR) {
    $sales = fetch_all("SELECT s.*, c.name as customer_name FROM sales_drafts s JOIN customers c ON s.customer_id = c.id WHERE s.created_by = ? AND s.isDelete = 0 AND c.isDelete = 0 ORDER BY s.created_at DESC", [$user_id]);
} else {
    $sales = fetch_all("SELECT s.*, c.name as customer_name, u.username as creator_name FROM sales_drafts s JOIN customers c ON s.customer_id = c.id JOIN users u ON s.created_by = u.id WHERE s.isDelete = 0 AND c.isDelete = 0 AND u.isDelete = 0 ORDER BY s.created_at DESC");
}
?>

<div class="row">
    <div class="col-12 d-flex justify-content-between align-items-center mb-4">
        <h3>Sales Records</h3>
        <?php if ($role != ROLE_VIEWER): ?>
        <a href="pos.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> New Sale</a>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Draft #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $s): ?>
                    <tr>
                        <td><strong>#<?php echo $s['id']; ?></strong></td>
                        <td><?php echo date('d M Y, h:i A', strtotime($s['created_at'])); ?></td>
                        <td><?php echo $s['customer_name']; ?></td>
                        <td><strong><?php echo format_currency($s['grand_total']); ?></strong></td>
                        <td>
                            <?php if ($s['status'] == 'Draft'): ?>
                                <span class="badge bg-warning text-dark">DRAFT</span>
                            <?php else: ?>
                                <span class="badge bg-success">CONFIRMED</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $s['creator_name'] ?? 'N/A'; ?></td>
                        <td>
                            <a href="view.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                            <?php if ($s['status'] == 'Draft'): ?>
                                <a href="edit.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                            <?php if ($s['status'] == 'Draft' && (in_array($role, [ROLE_ADMIN, ROLE_ACCOUNTANT]))): ?>
                                <a href="confirm.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to confirm this sale? This will deduct stock and update customer balance.')">
                                    Confirm
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
