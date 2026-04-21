<?php
/**
 * modules/inventory/transfer.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Manager']);

$branch_id = $_SESSION['branch_id'];

// Fetch products with stock
$stmt = $pdo->prepare("SELECT p.id, p.name, i.quantity_pcs FROM products p JOIN inventory i ON p.id = i.product_id WHERE i.branch_id = ? AND i.quantity_pcs > 0");
$stmt->execute([$branch_id]);
$products = $stmt->fetchAll();

// Fetch other branches
$stmt = $pdo->prepare("SELECT id, name FROM branches WHERE id != ? AND is_deleted = 0");
$stmt->execute([$branch_id]);
$branches = $stmt->fetchAll();

// Recent Transfers
$stmt = $pdo->prepare("SELECT t.*, p.name as product_name, b.name as to_branch_name FROM stock_transfers t JOIN products p ON t.product_id = p.id JOIN branches b ON t.to_branch_id = b.id WHERE t.from_branch_id = ? ORDER BY t.created_at DESC LIMIT 10");
$stmt->execute([$branch_id]);
$transfers = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0 fw-bold">New Stock Transfer</h5></div>
            <div class="card-body">
                <form id="transferForm">
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <select name="product_id" class="form-select product-select" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>">
                                <?php echo $p['name']; ?> (Available: <?php echo $p['quantity_pcs']; ?> Pcs)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">To Branch</label>
                        <select name="to_branch_id" class="form-select" required>
                            <option value="">Select Target Branch</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo $b['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity (Pcs)</label>
                        <input type="number" name="quantity_pcs" class="form-control" min="1" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Send Transfer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0 fw-bold">Recent Transfers</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>To</th>
                                <th>Qty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transfers as $t): ?>
                            <tr>
                                <td><?php echo date('d M, h:i A', strtotime($t['created_at'])); ?></td>
                                <td><?php echo $t['product_name']; ?></td>
                                <td><?php echo $t['to_branch_name']; ?></td>
                                <td><?php echo $t['quantity_pcs']; ?> Pcs</td>
                                <td><span class="badge bg-success"><?php echo ucfirst($t['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.product-select').select2({ theme: 'bootstrap-5' });

    $('#transferForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/inventory.php?action=transfer', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                alert(res.message);
                location.reload();
            } else {
                alert(res.message);
            }
        }, 'json');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
