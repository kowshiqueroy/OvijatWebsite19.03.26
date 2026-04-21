<?php
/**
 * modules/sales/pending.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Accountant']);

$branch_id = $_SESSION['branch_id'];
$stmt = $pdo->prepare("SELECT s.*, c.name as customer_name, c.balance as customer_balance, u.username as creator_name 
                     FROM sales s 
                     JOIN customers c ON s.customer_id = c.id 
                     JOIN users u ON s.user_id = u.id 
                     WHERE s.branch_id = ? AND s.status = 'pending_approval' 
                     ORDER BY s.created_at ASC");
$stmt->execute([$branch_id]);
$pending_sales = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0 fw-bold">Pending Sales Approvals</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Invoice ID</th>
                        <th>Customer</th>
                        <th>Current Balance</th>
                        <th>Invoice Amount</th>
                        <th>Requested By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_sales as $sale): ?>
                    <tr id="sale-row-<?php echo $sale['id']; ?>">
                        <td>#INV-<?php echo str_pad($sale['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td class="fw-bold"><?php echo $sale['customer_name']; ?></td>
                        <td><?php echo formatCurrency($sale['customer_balance']); ?></td>
                        <td class="fw-bold text-primary"><?php echo formatCurrency($sale['total_amount']); ?></td>
                        <td><?php echo $sale['creator_name']; ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-success approve-btn" data-id="<?php echo $sale['id']; ?>">
                                <i class="fas fa-check me-1"></i> Approve
                            </button>
                            <button class="btn btn-sm btn-danger reject-btn" data-id="<?php echo $sale['id']; ?>">
                                <i class="fas fa-times me-1"></i> Reject
                            </button>
                            <a href="view.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pending_sales)): ?>
                    <tr><td colspan="6" class="text-center p-4">No pending sales for approval.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.approve-btn').on('click', function() {
        const id = $(this).data('id');
        if (confirm('Approve this sale? This will deduct stock and update customer ledger.')) {
            $.post('../../actions/sales.php?action=approve_sale', {id: id}, function(res) {
                if (res.status === 'success') {
                    alert(res.message);
                    $('#sale-row-' + id).fadeOut();
                } else {
                    alert(res.message);
                }
            }, 'json');
        }
    });

    $('.reject-btn').on('click', function() {
        const id = $(this).data('id');
        if (confirm('Reject this sale?')) {
            $.post('../../actions/sales.php?action=reject_sale', {id: id}, function(res) {
                if (res.status === 'success') {
                    alert(res.message);
                    $('#sale-row-' + id).fadeOut();
                } else {
                    alert(res.message);
                }
            }, 'json');
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
