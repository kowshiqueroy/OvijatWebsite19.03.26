<?php
/**
 * modules/products/list.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Manager']);

$branch_id = $_SESSION['branch_id'];

// Fetch products for current branch
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.branch_id = ? AND p.is_deleted = 0 ORDER BY p.name ASC");
$stmt->execute([$branch_id]);
$products = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Product Inventory</h5>
        <?php if (hasRole(['Admin', 'Manager'])): ?>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Add New Product
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Pack Unit</th>
                        <th>Pack Unit</th>
                        <th>Min Price</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td class="fw-bold"><?php echo $p['name']; ?></td>
                        <td><span class="badge bg-info text-dark"><?php echo $p['category_name'] ?: 'N/A'; ?></span></td>
                        <td><?php echo $p['unit_name']; ?> X <?php echo $p['conversion_ratio']; ?> Pcs</td>
                        <td><?php echo formatCurrency($p['min_sale_price']); ?></td>
                        <td class="text-end">
                            <a href="view.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                            <?php if (hasRole(['Admin', 'Manager'])): ?>
                            <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-info" title="Edit"><i class="fas fa-edit"></i></a>
                            <button class="btn btn-sm btn-outline-danger delete-product" data-id="<?php echo $p['id']; ?>" title="Delete"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.delete-product').on('click', function() {
        if (confirm('Are you sure you want to delete this product?')) {
            const id = $(this).data('id');
            const row = $(this).closest('tr');
            $.post('../../actions/products.php?action=delete_product', {id: id}, function(res) {
                if (res.status === 'success') {
                    row.fadeOut();
                } else {
                    alert(res.message);
                }
            }, 'json');
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
