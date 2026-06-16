<?php
/**
 * Product Listing for Wholesale Users - Premium Redesign
 */
restrict_to(['wholesale_user', 'admin', 'manager']);

// Fetch all active products
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY c.name ASC, p.name ASC");
$stmt->execute();
$products = $stmt->fetchAll();

// Fetch all price tiers
$tiers = $pdo->query("SELECT * FROM product_price_tiers ORDER BY product_id, min_qty ASC")->fetchAll();
$product_tiers = [];
foreach ($tiers as $t) {
    $product_tiers[$t['product_id']][] = $t;
}
?>

<div class="section-title">
    <i class="fas fa-store"></i>
    Wholesale Catalog
</div>

<div class="product-grid">
    <?php foreach ($products as $p): ?>
        <div class="product-card">
            <div class="product-img">
                <i class="fas fa-box"></i>
            </div>
            <div class="product-info">
                <div style="font-size: 0.75rem; font-weight: 700; color: var(--primary); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.025em;">
                    <?php echo e($p['category_name']); ?>
                </div>
                <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--secondary);">
                    <?php echo e($p['name']); ?>
                </h3>
                
                <div class="product-price">
                    $<?php echo number_format($p['base_price'], 2); ?>
                </div>
                
                <?php if (isset($product_tiers[$p['id']])): ?>
                    <div class="tier-badge">
                        <div style="font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-tag"></i> Bulk Savings
                        </div>
                        <?php foreach ($product_tiers[$p['id']] as $t): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 0.25rem;">
                                <span>Buy <?php echo $t['min_qty']; ?>+</span>
                                <span style="font-weight: 700;">$<?php echo number_format($t['unit_price'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="/bolakausa/cart" method="POST" style="margin-top: 1.5rem;">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                    <div style="display: flex; gap: 0.75rem;">
                        <input type="number" name="qty" value="<?php echo $p['min_order_qty']; ?>" min="<?php echo $p['min_order_qty']; ?>" 
                               style="width: 80px; padding: 0.5rem;">
                        <button type="submit" class="btn btn-green" style="flex: 1; justify-content: center;">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
