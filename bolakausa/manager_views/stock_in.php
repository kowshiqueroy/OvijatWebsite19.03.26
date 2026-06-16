<?php
/**
 * Manager/Warehouse - Stock In / Inventory Logging
 */
restrict_to(['admin', 'manager', 'warehouse']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stock_in'])) {
    $product_id = (int)$_POST['product_id'];
    $qty = (int)$_POST['qty'];
    $notes = $_POST['notes'] ?? '';

    if ($product_id && $qty > 0) {
        $pdo->beginTransaction();
        try {
            // Get old qty for logging
            $stmt = $pdo->prepare("SELECT stock_qty, name FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if ($product) {
                $old_qty = $product['stock_qty'];
                $new_qty = $old_qty + $qty;

                // Update Stock
                $stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
                $stmt->execute([$qty, $product_id]);

                // Log Action
                log_action($pdo, $_SESSION['user_id'], "Stock In", "Product: {$product['name']}, Added: $qty", "New Total: $new_qty. Notes: $notes");

                $pdo->commit();
                $success = "Stock updated for " . e($product['name']) . ".";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error updating stock: " . $e->getMessage();
        }
    }
}

$products = $pdo->query("SELECT id, name, stock_qty FROM products ORDER BY name ASC")->fetchAll();
$low_stock = $pdo->query("SELECT id, name, stock_qty FROM products WHERE stock_qty <= 10")->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-dolly" style="color: var(--primary);"></i>
    Inventory Receiving
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2.5rem;">
    <!-- Log Form -->
    <div class="card">
        <h3 style="font-weight: 800; margin-bottom: 1.5rem; color: var(--secondary);">Log Incoming Stock</h3>
        
        <?php if ($success): ?>
            <div style="background: rgba(16, 185, 129, 0.1); color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid rgba(16, 185, 129, 0.2);">
                <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: rgba(244, 63, 94, 0.1); color: var(--accent); padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid rgba(244, 63, 94, 0.2);">
                <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Select Product *</label>
                <select name="product_id" required>
                    <option value="">-- Choose Product --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo e($p['name']); ?> (Current: <?php echo $p['stock_qty']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Quantity Received *</label>
                <input type="number" name="qty" min="1" required placeholder="e.g. 50">
            </div>

            <div class="form-group">
                <label>Receiving Notes / BOL #</label>
                <input type="text" name="notes" placeholder="Bill of Lading or condition notes">
            </div>

            <button type="submit" name="stock_in" class="btn btn-green" style="width: 100%; justify-content: center; margin-top: 1rem;">
                <i class="fas fa-save"></i> Record Stock
            </button>
        </form>
    </div>

    <!-- Low Stock Alerts -->
    <div class="card" style="background: rgba(244, 63, 94, 0.02); border-color: rgba(244, 63, 94, 0.2);">
        <h3 style="font-weight: 800; margin-bottom: 1.5rem; color: var(--accent);"><i class="fas fa-exclamation-circle"></i> Low Stock Alerts</h3>
        
        <?php if (!$low_stock): ?>
            <p style="color: var(--text-muted);">All inventory levels are healthy.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($low_stock as $ls): ?>
                    <li style="padding: 1rem; background: white; border-radius: 12px; border: 1px solid var(--border-light); margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm);">
                        <strong><?php echo e($ls['name']); ?></strong>
                        <span style="background: rgba(244, 63, 94, 0.1); color: var(--accent); padding: 4px 12px; border-radius: 20px; font-weight: 800; font-size: 0.85rem;">
                            Only <?php echo $ls['stock_qty']; ?> left
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
