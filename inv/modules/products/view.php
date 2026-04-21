<?php
/**
 * modules/products/view.php - View Product Details
 */
include '../../includes/header.php';

$branch_id = $_SESSION['branch_id'];
$product_id = (int)$_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.branch_id = ?");
$stmt->execute([$product_id, $branch_id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='alert alert-danger'>Product not found.</div>";
    include '../../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM product_prices WHERE product_id = ?");
$stmt->execute([$product_id]);
$prices = $stmt->fetchAll();
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Product Details</h5>
                <div>
                    <?php if (hasRole(['Admin', 'Manager'])): ?>
                    <a href="edit.php?id=<?php echo $product_id; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                    <?php endif; ?>
                    <a href="list.php" class="btn btn-sm btn-secondary">Back</a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="35%">Product Name</th>
                        <td class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td><span class="badge bg-info text-dark"><?php echo $product['category_name'] ?? 'N/A'; ?></span></td>
                    </tr>
                    <tr>
                        <th>Unit Name</th>
                        <td><?php echo htmlspecialchars($product['unit_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Pack Unit</th>
                        <td><?php echo htmlspecialchars($product['unit_name']); ?> X <?php echo $product['conversion_ratio']; ?> Pcs</td>
                    </tr>
                    <tr>
                        <th>Min Sale Price</th>
                        <td><?php echo formatCurrency($product['min_sale_price']); ?></td>
                    </tr>
                </table>

                <h6 class="border-bottom pb-2 mt-4">Prices by Customer Type</h6>
                <table class="table table-bordered">
                    <thead class="bg-light">
                        <tr>
                            <th>Customer Type</th>
                            <th>Pack Price</th>
                            <th>Piece Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prices as $price): ?>
                        <tr>
                            <td><?php echo $price['customer_type']; ?></td>
                            <td><?php echo formatCurrency($price['pack_price']); ?></td>
                            <td><?php echo formatCurrency($price['piece_price']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>