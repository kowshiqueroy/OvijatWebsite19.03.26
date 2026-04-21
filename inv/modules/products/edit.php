<?php
/**
 * modules/products/edit.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Manager']);

$branch_id = $_SESSION['branch_id'];
$product_id = (int)$_GET['id'] ?? 0;

// Fetch product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND branch_id = ?");
$stmt->execute([$product_id, $branch_id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='alert alert-danger'>Product not found.</div>";
    include '../../includes/footer.php';
    exit;
}

// Fetch categories
$stmt = $pdo->prepare("SELECT * FROM categories WHERE branch_id = ? AND is_deleted = 0 ORDER BY name ASC");
$stmt->execute([$branch_id]);
$categories = $stmt->fetchAll();

// Fetch prices
$stmt = $pdo->query("SELECT customer_type, pack_price, piece_price FROM product_prices WHERE product_id = " . (int)$product_id);
$prices = [];
while ($row = $stmt->fetch()) {
    $prices[$row['customer_type']] = ['pack' => $row['pack_price'], 'piece' => $row['piece_price']];
}
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">Edit Product</h5>
            </div>
            <div class="card-body">
                <form id="editProductForm">
                    <input type="hidden" name="id" value="<?php echo $product_id; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $product['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo $c['name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Unit Name</label>
                            <input type="text" name="unit_name" class="form-control" value="<?php echo $product['unit_name']; ?>" placeholder="Box, Carton">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Conversion Ratio</label>
                            <input type="number" name="conversion_ratio" class="form-control" value="<?php echo $product['conversion_ratio']; ?>" min="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Min Sale Price</label>
                            <input type="number" step="0.01" name="min_sale_price" class="form-control" value="<?php echo $product['min_sale_price']; ?>">
                        </div>
                    </div>

                    <h6 class="border-bottom pb-2 mt-4">Prices by Customer Type</h6>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Retail - Pack</label>
                            <input type="number" step="0.01" name="prices[Retail][pack]" class="form-control" value="<?php echo $prices['Retail']['pack'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Retail - Piece</label>
                            <input type="number" step="0.01" name="prices[Retail][piece]" class="form-control" value="<?php echo $prices['Retail']['piece'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">TP - Pack</label>
                            <input type="number" step="0.01" name="prices[TP][pack]" class="form-control" value="<?php echo $prices['TP']['pack'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">TP - Piece</label>
                            <input type="number" step="0.01" name="prices[TP][piece]" class="form-control" value="<?php echo $prices['TP']['piece'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">DP - Pack</label>
                            <input type="number" step="0.01" name="prices[DP][pack]" class="form-control" value="<?php echo $prices['DP']['pack'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">DP - Piece</label>
                            <input type="number" step="0.01" name="prices[DP][piece]" class="form-control" value="<?php echo $prices['DP']['piece'] ?? ''; ?>">
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="list.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#editProductForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/products.php?action=edit_product', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                alert(res.message);
                window.location.href = 'list.php';
            } else {
                alert(res.message);
            }
        }, 'json');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>