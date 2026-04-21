<?php
/**
 * modules/sales/list.php
 */
include '../../includes/header.php';

$branch_id = $_SESSION['branch_id'];
$stmt = $pdo->prepare("SELECT s.*, c.name as customer_name, u.username as creator_name 
                     FROM sales s 
                     JOIN customers c ON s.customer_id = c.id 
                     JOIN users u ON s.user_id = u.id 
                     WHERE s.branch_id = ?
                     ORDER BY s.created_at DESC");
$stmt->execute([$branch_id]);
$sales = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Sales History</h5>
        <?php if (hasRole(['Admin', 'Manager'])): ?>
        <a href="create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> New Sale</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead>
                    <tr>
                        <th>Invoice ID</th>
                        <th>Customer</th>
                        <th>Created By</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td class="fw-bold text-primary">#INV-<?php echo str_pad($sale['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo $sale['customer_name']; ?></td>
                        <td><?php echo $sale['creator_name']; ?></td>
                        <td class="fw-bold"><?php echo formatCurrency($sale['total_amount']); ?></td>
                        <td>
                            <?php 
                            $badge = 'bg-secondary';
                            if ($sale['status'] == 'approved') $badge = 'bg-success';
                            if ($sale['status'] == 'pending_approval') $badge = 'bg-warning text-dark';
                            if ($sale['status'] == 'rejected') $badge = 'bg-danger';
                            if ($sale['status'] == 'draft') $badge = 'bg-info text-dark';
                            ?>
                            <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($sale['status']); ?></span>
                        </td>
                        <td><?php echo formatDate($sale['created_at']); ?></td>
                        <td class="text-end">
                            <a href="view.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-invoice"></i> View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
