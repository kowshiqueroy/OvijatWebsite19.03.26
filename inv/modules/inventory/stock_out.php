<?php
/**
 * modules/inventory/stock_out.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Manager']);

$branch_id = $_SESSION['branch_id'];
$stmt = $pdo->prepare("SELECT id, name, unit_name, conversion_ratio FROM products WHERE branch_id = ? AND is_deleted = 0 ORDER BY name ASC");
$stmt->execute([$branch_id]);
$products = $stmt->fetchAll();
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">Manual Stock OUT</h5>
            </div>
            <div class="card-body">
                <form id="stockOutForm">
                    <div class="mb-3">
                        <label class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" data-unit="<?php echo $p['unit_name']; ?>" data-ratio="<?php echo $p['conversion_ratio']; ?>"><?php echo $p['name']; ?> (<?php echo $p['unit_name']; ?> X <?php echo $p['conversion_ratio']; ?> Pcs)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Pack Unit</label>
                            <select name="unit_type" class="form-select" required>
                                <option value="pack">Full Pack</option>
                                <option value="piece">Per Piece</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Responsible Person <span class="text-danger">*</span></label>
                        <input type="text" name="person_name" class="form-control" placeholder="Who is taking this stock?" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Reason (Damage/Loss/Other) <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Explain why this stock is being removed" required></textarea>
                    </div>

                    <div class="text-end">
                        <a href="stock.php" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-danger px-4">Confirm Stock OUT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#stockOutForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/inventory.php?action=manual_out', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                alert(res.message);
                window.location.href = 'stock.php';
            } else {
                alert(res.message);
            }
        }, 'json');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
