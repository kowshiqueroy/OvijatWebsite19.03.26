<?php
/**
 * modules/inventory/stock.php
 */
include '../../includes/header.php';

// Fetch inventory for the current branch
$branch_id = $_SESSION['branch_id'];
$stmt = $pdo->prepare("
    SELECT p.name, p.unit_name, p.conversion_ratio, i.quantity_pcs 
    FROM inventory i 
    JOIN products p ON i.product_id = p.id 
    WHERE i.branch_id = ? AND p.is_deleted = 0 
    ORDER BY p.name ASC");
$stmt->execute([$branch_id]);
$inventory = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Current Inventory (<?php echo $_SESSION['branch_name']; ?>)</h5>
        <div>
            <a href="stock_in.php" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i> Stock IN</a>
            <a href="stock_out.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-minus me-1"></i> Manual OUT</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Pack Unit</th>
                        <th>Stock in Packs</th>
                        <th>Current Stock (Pcs)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): 
                        $packs = floor($item['quantity_pcs'] / $item['conversion_ratio']);
                        $remaining_pcs = $item['quantity_pcs'] % $item['conversion_ratio'];
                    ?>
                    <tr>
                        <td class="fw-bold"><?php echo $item['name']; ?></td>
                        <td><?php echo $item['unit_name']; ?> X <?php echo $item['conversion_ratio']; ?> Pcs</td>
                        <td><?php echo $packs; ?> Packs + <?php echo $remaining_pcs; ?> Pcs</td>
                        <td class="fw-bold text-primary"><?php echo $item['quantity_pcs']; ?> Pcs</td>
                        <td>
                            <?php if ($item['quantity_pcs'] <= 0): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($item['quantity_pcs'] < 10): ?>
                                <span class="badge bg-warning text-dark">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($inventory)): ?>
                    <tr><td colspan="5" class="text-center p-4">No inventory data found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
