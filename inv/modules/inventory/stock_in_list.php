<?php
/**
 * modules/inventory/stock_in_list.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Manager', 'Accountant']);

$branch_id = $_SESSION['branch_id'];
$stmt = $pdo->prepare("
    SELECT sl.id, p.name as product_name, sl.quantity_pcs, sl.person_name, sl.reason, sl.created_at, u.username
    FROM stock_ledger sl
    JOIN products p ON sl.product_id = p.id
    LEFT JOIN users u ON sl.user_id = u.id
    WHERE sl.branch_id = ? AND sl.type = 'stock_in'
    ORDER BY sl.created_at DESC
    LIMIT 100");
$stmt->execute([$branch_id]);
$stock_in_list = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Stock In History</h5>
        <a href="stock_in.php" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i> New Stock IN</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="stockInTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Quantity (Pcs)</th>
                        <th>Source Person/Supplier</th>
                        <th>Reason/Reference</th>
                        <th>Entry By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($stock_in_list) > 0): ?>
                        <?php foreach ($stock_in_list as $row): ?>
                        <tr>
                            <td><?php echo date('d-M-Y h:i A', strtotime($row['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td><?php echo number_format($row['quantity_pcs']); ?></td>
                            <td><?php echo htmlspecialchars($row['person_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['reason'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['username'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No Stock In records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>