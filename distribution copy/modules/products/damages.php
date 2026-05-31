<?php
require_once '../../templates/header.php';
check_role([ROLE_ADMIN, ROLE_MANAGER]);

$products = fetch_all("SELECT id, name, stock_qty FROM products WHERE isDelete = 0 ORDER BY name ASC");
$damages = fetch_all("SELECT d.*, p.name as product_name, u.username 
                      FROM stock_damages d 
                      JOIN products p ON d.product_id = p.id 
                      JOIN users u ON d.user_id = u.id 
                      WHERE d.isDelete = 0 
                      ORDER BY d.created_at DESC");
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3>Stock Damages</h3>
        <p class="text-muted">Record and track damaged or expired inventory.</p>
    </div>
    <div class="col-md-4 text-end">
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#addDamageModal">
            <i class="fas fa-plus me-2"></i> Record Damage
        </button>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Reason</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($damages)): ?>
                        <tr><td colspan="5" class="text-center py-4">No damage records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($damages as $d): ?>
                    <tr>
                        <td><?php echo date('d M Y, h:i A', strtotime($d['created_at'])); ?></td>
                        <td><strong><?php echo $d['product_name']; ?></strong></td>
                        <td class="text-danger fw-bold">-<?php echo $d['quantity']; ?></td>
                        <td><?php echo $d['reason']; ?></td>
                        <td><?php echo $d['username']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Damage Modal -->
<div class="modal fade" id="addDamageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record New Damage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_damage.php" method="POST">
                <?php csrf_field(); ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Product</label>
                        <select name="product_id" class="form-select select2" required style="width: 100%;">
                            <option value="">-- Choose --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo $p['name']; ?> (Available: <?php echo $p['stock_qty']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity Damaged</label>
                        <input type="number" name="qty" class="form-control" required min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason / Notes</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="e.g. Expired, Broken during loading..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Post Damage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
