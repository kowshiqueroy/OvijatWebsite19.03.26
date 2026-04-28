<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);

$id = $_GET['id'] ?? 0;
$product = fetch_one("SELECT * FROM products WHERE id = ?", [$id]);

if (!$product) {
    redirect('modules/products/index.php', 'Product not found.', 'danger');
}

if (isset($_POST['update_product'])) {
    $name = sanitize($_POST['name']);
    $cat_id = $_POST['category_id'];
    $tp = $_POST['tp_rate'];
    $dp = $_POST['dp_rate'];
    $retail = $_POST['retail_rate'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    db_query("UPDATE products SET name = ?, category_id = ?, tp_rate = ?, dp_rate = ?, retail_rate = ?, is_active = ? WHERE id = ?", 
             [$name, $cat_id, $tp, $dp, $retail, $is_active, $id]);
    
    log_activity($_SESSION['user_id'], "Updated product: $name");
    redirect('modules/products/index.php', 'Product updated successfully.');
}

$categories = fetch_all("SELECT * FROM categories WHERE isDelete = 0");
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Edit Product</strong></div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo $product['name']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $product['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo $cat['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">TP Rate</label>
                            <input type="number" step="0.01" name="tp_rate" class="form-control" value="<?php echo $product['tp_rate']; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">DP Rate</label>
                            <input type="number" step="0.01" name="dp_rate" class="form-control" value="<?php echo $product['dp_rate']; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Retail Rate</label>
                            <input type="number" step="0.01" name="retail_rate" class="form-control" value="<?php echo $product['retail_rate']; ?>" required>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="activeCheck" <?php echo $product['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="activeCheck">Is Active</label>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_product" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
