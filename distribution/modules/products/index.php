<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER]);

if (isset($_POST['add_product'])) {
    check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]); // Re-verify for action
    $name = sanitize($_POST['name']);
    $cat_id = $_POST['category_id'];
    $tp = $_POST['tp_rate'];
    $dp = $_POST['dp_rate'];
    $retail = $_POST['retail_rate'];
    $stock = $_POST['stock_qty'];

    db_query("INSERT INTO products (name, category_id, tp_rate, dp_rate, retail_rate, stock_qty) VALUES (?, ?, ?, ?, ?, ?)", 
             [$name, $cat_id, $tp, $dp, $retail, $stock]);
    log_activity($_SESSION['user_id'], "Added product: $name");
    redirect('modules/products/index.php', 'Product added successfully.');
}

$categories = fetch_all("SELECT * FROM categories WHERE isDelete = 0");
$products = fetch_all("SELECT p.*, c.name as cat_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.isDelete = 0 AND c.isDelete = 0");
?>

<div class="row">
    <div class="col-12 d-flex justify-content-between align-items-center mb-4">
        <h3>Product Inventory</h3>
        <?php if ($_SESSION['role'] != ROLE_VIEWER): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="fas fa-box-open me-2"></i> Add New Product
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>TP Rate</th>
                        <th>DP Rate</th>
                        <th>Retail Rate</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <?php if ($_SESSION['role'] != ROLE_VIEWER): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td><strong><?php echo $p['name']; ?></strong></td>
                        <td><?php echo $p['cat_name']; ?></td>
                        <td><?php echo format_currency($p['tp_rate']); ?></td>
                        <td><?php echo format_currency($p['dp_rate']); ?></td>
                        <td><?php echo format_currency($p['retail_rate']); ?></td>
                        <td>
                            <span class="badge <?php echo $p['stock_qty'] < 10 ? 'bg-danger' : 'bg-success'; ?>">
                                <?php echo $p['stock_qty']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($p['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($_SESSION['role'] != ROLE_VIEWER): ?>
                        <td>
                            <button class="btn btn-sm btn-primary stock-in-btn" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#stockInModal" 
                                    data-id="<?php echo $p['id']; ?>" 
                                    data-name="<?php echo $p['name']; ?>"
                                    title="Stock IN">
                                <i class="fas fa-plus-circle me-1"></i> Stock IN
                            </button>
                            <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-info" title="Edit"><i class="fas fa-edit"></i></a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Single Stock In Modal (Outside loop for stability) -->
<div class="modal fade" id="stockInModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form action="stock_in.php" method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Stock IN</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Product: <strong id="modal-product-name"></strong></p>
                <input type="hidden" name="product_id" id="modal-product-id">
                <div class="mb-3">
                    <label class="form-label">Quantity to Add</label>
                    <input type="number" name="qty" class="form-control form-control-lg" required min="1" autofocus>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Stock</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stockInModal = document.getElementById('stockInModal');
    if (stockInModal) {
        stockInModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            
            document.getElementById('modal-product-id').value = id;
            document.getElementById('modal-product-name').textContent = name;
        });
        
        stockInModal.addEventListener('shown.bs.modal', function () {
            stockInModal.querySelector('input[name="qty"]').focus();
        });
    }
});
</script>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">TP Rate</label>
                        <input type="number" step="0.01" name="tp_rate" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">DP Rate</label>
                        <input type="number" step="0.01" name="dp_rate" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Retail Rate</label>
                        <input type="number" step="0.01" name="retail_rate" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Initial Stock</label>
                    <input type="number" name="stock_qty" class="form-control" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_product" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
