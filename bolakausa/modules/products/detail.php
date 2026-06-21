<?php
global $pdo;
$url_path = trim($_GET['url'] ?? '', '/');
$segments = explode('/', $url_path);
$pid = (int)($segments[1] ?? (int)($parts[1] ?? 0));

$stmt = $pdo->prepare("SELECT p.*, c.name as category_name, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.is_active = 1");
$stmt->execute([$pid]);
$product = $stmt->fetch();

if (!$product) {
    echo '<div style="text-align: center; padding: 5rem 2rem;"><h2>Product not found</h2><a href="' . BASE_URL . 'catalog" class="btn btn-green">Back to Catalog</a></div>';
    return;
}

$stmt = $pdo->prepare("SELECT * FROM product_price_tiers WHERE product_id = ? ORDER BY min_qty ASC");
$stmt->execute([$pid]);
$tiers = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC");
$stmt->execute([$pid]);
$images = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? AND is_deleted = 0");
$stmt->execute([$pid]);
$variants = $stmt->fetchAll();

$discount = get_product_discount($pdo, $pid);
$discount_price = $discount ? calculate_discounted_price($product['base_price'], $discount) : null;
$unit_price = $discount_price ?: $product['base_price'];
?>

<style>
@media (max-width: 768px) {
    .detail-grid { grid-template-columns: 1fr !important; }
    .detail-grid .product-img { height: 280px !important; }
}
</style>

<div style="max-width: 1000px; margin: 0 auto;">
    <a href="<?php echo BASE_URL; ?>catalog" style="display: inline-flex; align-items: center; gap: 0.4rem; color: var(--text-muted); text-decoration: none; font-weight: 700; font-size: 0.85rem; margin-bottom: 2rem;">
        <i class="fas fa-arrow-left"></i> Back to Catalog
    </a>

    <div class="detail-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: start;">
        <!-- Left: Images -->
        <div>
            <div class="product-img" style="height: 350px; border-radius: 16px; overflow: hidden; box-shadow: var(--glass-shadow); border: 1px solid var(--border-light);">
                <?php if (!empty($images)): ?>
                    <img id="detail-main-img" src="<?php echo e($images[0]['image_path']); ?>" alt="<?php echo e($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php elseif (!empty($product['main_image'])): ?>
                    <img id="detail-main-img" src="<?php echo e($product['main_image']); ?>" alt="<?php echo e($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <img id="detail-main-img" src="<?php echo BASE_URL; ?>public/images/default_product.png" alt="Default Product" style="width: 100%; height: 100%; object-fit: cover;">
                <?php endif; ?>
            </div>
            <?php if (count($images) > 1): ?>
                <div id="detail-thumbs" style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
                    <?php foreach ($images as $i => $img): ?>
                        <div class="detail-thumb" data-src="<?php echo e($img['image_path']); ?>" style="width: 64px; height: 64px; border-radius: 8px; overflow: hidden; border: 2px solid <?php echo $i === 0 ? 'var(--primary)' : 'var(--border-light)'; ?>; cursor: pointer;">
                            <img src="<?php echo e($img['image_path']); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Details -->
        <div>
            <div class="product-category" style="margin-bottom: 0.5rem;"><?php echo e($product['category_name']); ?></div>
            <h1 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.8rem; font-weight: 800; color: var(--secondary); margin: 0 0 0.5rem 0;"><?php echo e($product['name']); ?></h1>

            <?php if ($discount && $discount_price !== null): ?>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                    <span class="detail-unit-price" style="font-size: 1.8rem; font-weight: 800; color: var(--rose);">$<?php echo number_format($discount_price, 2); ?></span>
                    <span style="font-size: 1.1rem; color: var(--text-muted); text-decoration: line-through;">$<?php echo number_format($product['base_price'], 2); ?></span>
                    <span style="background: rgba(244,63,94,0.1); color: var(--rose); padding: 0.15rem 0.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 800;">
                        -<?php echo $discount['percent'] > 0 ? (float)$discount['percent'] . '%' : '$' . number_format($discount['amount'], 2); ?>
                    </span>
                </div>
            <?php else: ?>
                <div class="detail-unit-price" style="font-size: 1.8rem; font-weight: 800; color: var(--secondary); margin-bottom: 1rem;">$<?php echo number_format($product['base_price'], 2); ?></div>
            <?php endif; ?>

            <?php if (!empty($tiers)): ?>
                <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
                    <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.9rem; font-weight: 800; color: var(--secondary); margin: 0 0 0.75rem 0; display: flex; align-items: center; gap: 0.4rem;">
                        <i class="fas fa-tags" style="color: var(--primary);"></i> Bulk Pricing Tiers
                    </h4>
                    <?php foreach ($tiers as $t): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px solid var(--border-light);">
                            <span style="font-weight: 600; color: var(--text-main);">Buy <?php echo $t['min_qty']; ?>+</span>
                            <span style="font-weight: 800; color: var(--secondary); font-size: 1.05rem;">$<?php echo number_format($t['unit_price'], 2); ?> <span style="font-weight: 600; font-size: 0.75rem; color: var(--text-muted);">/ unit</span></span>
                        </div>
                    <?php endforeach; ?>
                    <?php
                    $savings = [];
                    foreach ($tiers as $t) {
                        $saving = round((1 - $t['unit_price'] / $product['base_price']) * 100);
                        if ($saving > 0) $savings[] = $saving;
                    }
                    if (!empty($savings)): ?>
                        <div style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--primary-dark); font-weight: 700; display: flex; align-items: center; gap: 0.3rem;">
                            <i class="fas fa-arrow-down"></i> Save up to <?php echo max($savings); ?>%
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($product['description'])): ?>
                <p style="color: var(--text-main); line-height: 1.6; margin-bottom: 1.5rem; font-size: 0.9rem;"><?php echo nl2br(e($product['description'])); ?></p>
            <?php endif; ?>

            <div style="display: flex; gap: 0.75rem; margin-bottom: 1rem;">
                <div style="background: rgba(15,23,42,0.03); padding: 1rem; border-radius: 10px; flex: 1; text-align: center;">
                    <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Min Order</div>
                    <div style="font-size: 1.1rem; font-weight: 800; color: var(--secondary);"><?php echo $product['min_order_qty']; ?> units</div>
                </div>
                <div style="background: rgba(15,23,42,0.03); padding: 1rem; border-radius: 10px; flex: 1; text-align: center;">
                    <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Weight</div>
                    <div style="font-size: 1.1rem; font-weight: 800; color: var(--secondary);"><?php echo number_format($product['weight'] ?? 0, 2); ?> kg</div>
                </div>
                <div style="background: rgba(15,23,42,0.03); padding: 1rem; border-radius: 10px; flex: 1; text-align: center;">
                    <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Stock</div>
                    <div style="font-size: 1.1rem; font-weight: 800; color: <?php echo $product['stock_qty'] > 0 ? 'var(--primary)' : 'var(--rose)'; ?>;"><?php echo $product['stock_qty']; ?> units</div>
                </div>
            </div>

            <?php if (is_logged_in()): ?>
                <form action="<?php echo BASE_URL; ?>cart" method="POST" style="margin-top: 1.5rem;">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    
                    <?php if (!empty($variants)): ?>
                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="font-size: 0.75rem; font-weight: 800; color: var(--secondary); text-transform: uppercase; display: block; margin-bottom: 0.35rem;">Select Variant</label>
                            <select name="variant_id" id="detail-variant" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 2px solid var(--border); font-size: 0.9rem; outline: none; font-family: inherit;">
                                <option value="" data-price-mod="0" data-stock="<?php echo $product['stock_qty']; ?>">-- Base Product --</option>
                                <?php foreach ($variants as $v): ?>
                                    <option value="<?php echo $v['id']; ?>" data-price-mod="<?php echo $v['price_modifier']; ?>" data-stock="<?php echo $v['stock_qty']; ?>">
                                        <?php echo e($v['variant_type']) . ': ' . e($v['variant_value']); ?> 
                                        <?php if ($v['price_modifier'] != 0): ?>
                                            (<?php echo $v['price_modifier'] > 0 ? '+' : ''; ?>$<?php echo number_format($v['price_modifier'], 2); ?>)
                                        <?php endif; ?>
                                        <?php echo ' [Stock: ' . $v['stock_qty'] . ']'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                            <span style="font-size: 0.7rem; font-weight: 800; color: var(--secondary); text-transform: uppercase;">Qty</span>
                            <input type="number" name="qty" id="detail-qty" value="<?php echo $product['min_order_qty']; ?>" min="<?php echo $product['min_order_qty']; ?>" max="<?php echo $product['stock_qty']; ?>" style="width: 90px; padding: 0.6rem; text-align: center; border-radius: 8px; font-size: 1rem; border: 2px solid var(--border); outline: none; transition: border-color 0.2s;">
                        </div>
                        <button type="submit" class="btn btn-green" id="detail-add-btn" style="flex: 1; padding: 0.85rem; border-radius: 10px; font-size: 1rem; font-weight: 800;" <?php echo $product['stock_qty'] <= 0 ? 'disabled style="opacity: 0.55; cursor: not-allowed;"' : ''; ?>>
                            <?php if ($product['stock_qty'] <= 0): ?>
                                Out of Stock
                            <?php else: ?>
                                <i class="fas fa-cart-plus"></i> Add to Cart — $<?php echo number_format($unit_price * $product['min_order_qty'], 2); ?>
                            <?php endif; ?>
                        </button>
                    </div>
                </form>
                <div style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--text-muted); text-align: right; font-weight: 600;">
                    <span id="detail-unit-label">$<?php echo number_format($unit_price, 2); ?></span> / unit
                </div>
            <?php else: ?>
                <a href="/bolakausa/login" class="btn btn-blue" style="width: 100%; border-radius: 10px; padding: 0.85rem; font-size: 1rem;"><i class="fas fa-sign-in-alt"></i> Login to Order</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (is_logged_in()): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var qtyInput = document.getElementById('detail-qty');
    var addBtn = document.getElementById('detail-add-btn');
    var unitLabel = document.getElementById('detail-unit-label');
    var variantSelect = document.getElementById('detail-variant');
    var tierData = <?php echo json_encode($tiers); ?>;
    var baseUnitPrice = <?php echo $unit_price; ?>;
    var basePrice = <?php echo $product['base_price']; ?>;

    function getUnitPrice(qty) {
        var price = baseUnitPrice;
        for (var i = tierData.length - 1; i >= 0; i--) {
            if (qty >= parseInt(tierData[i].min_qty)) {
                price = parseFloat(tierData[i].unit_price);
                break;
            }
        }
        if (variantSelect) {
            var selectedOpt = variantSelect.options[variantSelect.selectedIndex];
            var mod = parseFloat(selectedOpt.getAttribute('data-price-mod') || 0);
            price += mod;
        }
        return price;
    }

    function updateDisplay() {
        var qty = parseInt(qtyInput.value) || 0;
        var maxStock = <?php echo $product['stock_qty']; ?>;
        
        if (variantSelect) {
            var selectedOpt = variantSelect.options[variantSelect.selectedIndex];
            maxStock = parseInt(selectedOpt.getAttribute('data-stock') || 0);
            qtyInput.setAttribute('max', maxStock);
            if (qty > maxStock) {
                qtyInput.value = maxStock;
                qty = maxStock;
            }
        }
        
        if (maxStock <= 0) {
            addBtn.innerHTML = 'Out of Stock';
            addBtn.disabled = true;
            addBtn.style.opacity = '0.55';
            addBtn.style.cursor = 'not-allowed';
        } else {
            var unitPrice = getUnitPrice(qty);
            var total = unitPrice * qty;
            addBtn.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart — $' + total.toFixed(2);
            unitLabel.textContent = '$' + unitPrice.toFixed(2);
            addBtn.disabled = false;
            addBtn.style.opacity = '1';
            addBtn.style.cursor = 'pointer';
        }
    }

    qtyInput.addEventListener('input', updateDisplay);
    qtyInput.addEventListener('change', updateDisplay);
    qtyInput.addEventListener('focus', function() { this.style.borderColor = 'var(--primary)'; });
    qtyInput.addEventListener('blur', function() { this.style.borderColor = 'var(--border)'; });
    
    if (variantSelect) {
        variantSelect.addEventListener('change', updateDisplay);
    }
    
    // Run initially to set max values and buttons correctly
    updateDisplay();

    var thumbs = document.querySelectorAll('.detail-thumb');
    var mainImg = document.getElementById('detail-main-img');
    thumbs.forEach(function(thumb) {
        thumb.addEventListener('click', function() {
            mainImg.src = this.getAttribute('data-src');
            thumbs.forEach(function(t) { t.style.borderColor = 'var(--border-light)'; });
            this.style.borderColor = 'var(--primary)';
        });
    });
});
</script>
<?php endif; ?>
<?php
