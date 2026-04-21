<?php
/**
 * modules/products/add.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Manager']);

$branch_id = $_SESSION['branch_id'];

// Fetch categories for dropdown
$stmt = $pdo->prepare("SELECT * FROM categories WHERE branch_id = ? AND is_deleted = 0");
$stmt->execute([$branch_id]);
$categories = $stmt->fetchAll();
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">Add New Product</h5>
            </div>
            <div class="card-body">
                <form id="addProductForm">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Coca Cola 500ml" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Pack Unit Name</label>
                            <input type="text" name="unit_name" class="form-control" placeholder="e.g. Box, Carton" value="Box">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Conversion Ratio (1 Pack = ? Pcs)</label>
                            <input type="number" name="conversion_ratio" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Min Sale Price (Piece)</label>
                            <input type="number" step="0.01" name="min_sale_price" class="form-control" value="0.00">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold mb-3 text-primary">Multi-tier Pricing (Pack & Piece)</h6>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Customer Type</th>
                                    <th>Pack Price</th>
                                    <th>Piece Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (['Retail', 'TP', 'DP'] as $type): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $type; ?></td>
                                    <td>
                                        <input type="number" step="0.01" name="prices[<?php echo $type; ?>][pack]" class="form-control" value="0.00">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="prices[<?php echo $type; ?>][piece]" class="form-control" value="0.00">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end mt-4">
                        <a href="list.php" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Save Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#addProductForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/products.php?action=add_product', $(this).serialize(), function(res) {
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
