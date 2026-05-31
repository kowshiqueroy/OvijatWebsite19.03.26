<?php
require_once '../../templates/header.php';
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_SR, ROLE_CUSTOMER, ROLE_VIEWER]);

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Filter based on role
if ($_SESSION['role'] == ROLE_CUSTOMER) {
    $cust = fetch_one("SELECT id FROM customers WHERE user_id = ? AND isDelete = 0", [$user_id]);
    $sales = fetch_all("SELECT s.*, c.name as customer_name FROM sales_drafts s JOIN customers c ON s.customer_id = c.id WHERE s.customer_id = ? AND s.isDelete = 0 AND c.isDelete = 0 ORDER BY s.created_at DESC", [$cust['id']]);
} elseif ($_SESSION['role'] == ROLE_SR) {
    $sales = fetch_all("SELECT s.*, c.name as customer_name FROM sales_drafts s JOIN customers c ON s.customer_id = c.id WHERE s.created_by = ? AND s.isDelete = 0 AND c.isDelete = 0 ORDER BY s.created_at DESC", [$user_id]);
} else {
    $sales = fetch_all("SELECT s.*, c.name as customer_name, u.username as creator_name FROM sales_drafts s JOIN customers c ON s.customer_id = c.id JOIN users u ON s.created_by = u.id WHERE s.isDelete = 0 AND c.isDelete = 0 AND u.isDelete = 0 ORDER BY s.created_at DESC");
}
?>

<div class="row">
    <div class="col-12 d-flex justify-content-between align-items-center mb-4">
        <h3>Sales Records</h3>
        <div class="btn-group">
            <?php if ($role != ROLE_VIEWER): ?>
            <a href="pos.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> New Sale</a>
            <?php endif; ?>
            <?php if (in_array($role, [ROLE_ADMIN, ROLE_ACCOUNTANT, ROLE_MANAGER])): ?>
            <button type="button" id="create-truck-load" class="btn btn-dark d-none"><i class="fas fa-truck me-2"></i> Create Truck Load</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="bulk-actions-form" action="../delivery/create.php" method="POST">
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="select-all"></th>
                        <th>Draft #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Delivery Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $s): ?>
                    <tr>
                        <td>
                            <?php if ($s['status'] == 'Confirmed' && $s['delivery_status'] == 'Pending'): ?>
                                <input type="checkbox" name="invoice_ids[]" value="<?php echo $s['id']; ?>" class="invoice-checkbox">
                            <?php endif; ?>
                        </td>
                        <td><strong>#<?php echo $s['id']; ?></strong></td>
                        <td><?php echo date('d M Y', strtotime($s['created_at'])); ?></td>
                        <td><?php echo $s['customer_name']; ?></td>
                        <td><strong><?php echo format_currency($s['grand_total']); ?></strong></td>
                        <td>
                            <?php if ($s['status'] == 'Draft'): ?>
                                <span class="badge bg-warning text-dark">DRAFT</span>
                            <?php else: ?>
                                <span class="badge bg-success">CONFIRMED</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($s['status'] == 'Confirmed'): ?>
                                <?php 
                                    $delivery_color = [
                                        'Pending' => 'bg-secondary',
                                        'Loading' => 'bg-info',
                                        'In Transit' => 'bg-primary',
                                        'Delivered' => 'bg-success',
                                        'Failed' => 'bg-danger',
                                        'Returned' => 'bg-warning text-dark'
                                    ][$s['delivery_status']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?php echo $delivery_color; ?>"><?php echo strtoupper($s['delivery_status']); ?></span>
                                <?php if ($s['delivery_date']): ?>
                                    <div class="small text-muted mt-1"><?php echo date('d M y', strtotime($s['delivery_date'])); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
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
                            <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                                <a href="../admin/delete_record.php?table=sales_drafts&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-danger" title="Master Delete" onclick="return confirm('Delete this sale permanently?')"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    const truckLoadBtn = document.getElementById('create-truck-load');
    const form = document.getElementById('bulk-actions-form');

    function toggleBtn() {
        const checkedCount = document.querySelectorAll('.invoice-checkbox:checked').length;
        if (checkedCount > 0) {
            truckLoadBtn.classList.remove('d-none');
        } else {
            truckLoadBtn.classList.add('d-none');
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            toggleBtn();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', toggleBtn);
    });

    truckLoadBtn.addEventListener('click', function() {
        form.submit();
    });
});
</script>

<?php require_once '../../templates/footer.php'; ?>
