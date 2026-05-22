<?php
/**
 * Product Detail Page
 * Tactile, modern, and information-rich.
 */
require_once 'includes/Database.php';
require_once 'includes/ProductManager.php';

$pm = new ProductManager();
$slug = $_GET['slug'] ?? '';
$product = $pm->getProductBySlug($slug);

if (!$product || !$product['is_active']) {
    header("HTTP/1.0 404 Not Found");
    include 'home.php'; 
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'retail';

$images = $pm->getImages($product['id']);
$primaryImage = null;
foreach ($images as $img) { if ($img['is_primary']) { $primaryImage = $img['file_path']; break; } }
if (!$primaryImage && !empty($images)) $primaryImage = $images[0]['file_path'];

$pageTitle = $product['name'];
include 'templates/header.php';
?>
<style>
    .var-pill { padding: 0.8rem 1.5rem; border-radius: 50px; border: 2px solid var(--border); cursor: pointer; font-weight: 700; font-size: 0.9rem; transition: all 0.3s; position: relative; background: white; color: var(--text); }
    .var-pill:hover { border-color: var(--primary); background: var(--bg); }
    .var-pill.active { border-color: var(--primary); background: var(--primary); color: white; }
    .var-pill.disabled { opacity: 0.3; cursor: not-allowed; text-decoration: line-through; }
    .stock-badge { position: absolute; top: -12px; right: -5px; background: var(--error); color: white; font-size: 0.6rem; padding: 2px 8px; border-radius: 10px; font-weight: 800; border: 2px solid var(--white); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
</style>

<div class="section-container">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5rem; align-items: start;">
        
        <!-- Gallery -->
        <div class="reveal">
            <div style="background: white; border-radius: 30px; overflow: hidden; box-shadow: var(--card-shadow); margin-bottom: 1.5rem; border: 1px solid var(--border);">
                <?php if ($primaryImage): ?>
                    <img id="main-img" src="<?php echo SITE_URL; ?>/assets/img/products/<?php echo $primaryImage; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 550px; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 100%; height: 550px; display: flex; align-items: center; justify-content: center; background: var(--bg); font-size: 5rem;">🥦</div>
                <?php endif; ?>
            </div>
            
            <?php if (count($images) > 1): ?>
                <div style="display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 10px;">
                    <?php foreach ($images as $img): ?>
                        <img src="<?php echo SITE_URL; ?>/assets/img/products/<?php echo $img['file_path']; ?>" 
                             onclick="document.getElementById('main-img').src = this.src"
                             style="width: 90px; height: 90px; object-fit: cover; border-radius: 15px; cursor: pointer; border: 3px solid transparent; transition: var(--transition);"
                             onmouseover="this.style.borderColor='var(--primary)'"
                             onmouseout="this.style.borderColor='transparent'">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="reveal">
            <h1 style="font-size: 3.5rem; font-weight: 800; color: var(--text); margin-bottom: 0.5rem; letter-spacing: -2px;"><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 2.5rem;">
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary);" id="active-price">
                    $<?php echo number_format($product['base_price'], 2); ?>
                </div>
                <div id="original-price-box" style="display:none; font-size: 1.2rem; text-decoration: line-through; opacity: 0.3; font-weight: 700;"></div>
                <div id="discount-badge" style="display:none; background: var(--accent); color: white; padding: 4px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 800;"></div>
                <div id="wholesale-badge" style="display:none; background: var(--primary); color: white; padding: 6px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 800;">WHOLESALE</div>
            </div>

            <p style="line-height: 1.8; opacity: 0.7; margin-bottom: 3rem; font-weight: 500; font-size: 1.1rem;">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
            </p>

            <div style="background: white; padding: 2.5rem; border-radius: 30px; box-shadow: var(--card-shadow); border: 1px solid var(--border);">
                <div class="form-group">
                    <label style="font-size: 0.75rem; font-weight: 800; opacity: 0.5; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 1.2rem; display: block;">Available Options</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 1rem;" id="variation-pills">
                        <?php foreach ($product['variations'] as $v): ?>
                            <?php 
                                $price = $v['price_override'] ?? $product['base_price'];
                                $isWholesale = false;
                                $minQty = $v['retail_min_qty'] ?: 1;

                                if ($userRole === 'wholesale' && !empty($v['wholesale_price'])) {
                                    $price = $v['wholesale_price'];
                                    $isWholesale = true;
                                    $minQty = $v['wholesale_min_qty'] ?: 1;
                                }
                            ?>
                            <div class="var-pill <?php echo $v['total_stock'] <= 0 ? 'disabled' : ''; ?>" 
                                 onclick="selectVar(this, <?php echo $v['id']; ?>, <?php echo $price; ?>, <?php echo $v['original_price'] ?: 'null'; ?>, <?php echo $isWholesale ? 'true' : 'false'; ?>, <?php echo $minQty; ?>)"
                                 data-stock="<?php echo (int)$v['total_stock']; ?>">
                                <?php echo htmlspecialchars($v['name_modifier'] ?: 'Standard'); ?>
                                <?php if ($v['total_stock'] > 0 && $v['total_stock'] < 10): ?>
                                    <span class="stock-badge"><?php echo (int)$v['total_stock']; ?> Left</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="selected-var" value="">
                    <input type="hidden" id="min-order-qty" value="1">
                </div>

                <div style="display: flex; gap: 1.5rem; margin-top: 2.5rem; align-items: flex-end;">
                    <div style="width: 140px;">
                        <label style="font-size: 0.75rem; font-weight: 800; opacity: 0.5; text-transform: uppercase; margin-bottom: 0.6rem; display: block;">Quantity</label>
                        <div style="display: flex; align-items: center; background: var(--bg); border-radius: 15px; padding: 0.4rem;">
                            <button onclick="updateQty(-1)" style="width: 40px; height: 40px; border: none; background: none; font-size: 1.2rem; font-weight: 800; cursor: pointer; color: var(--primary);">−</button>
                            <input type="number" id="qty" value="1" min="1" readonly style="flex: 1; border: none; background: none; text-align: center; font-weight: 800; font-size: 1.1rem; width: 40px;">
                            <button onclick="updateQty(1)" style="width: 40px; height: 40px; border: none; background: none; font-size: 1.2rem; font-weight: 800; cursor: pointer; color: var(--primary);">+</button>
                        </div>
                    </div>
                    <button id="add-to-cart-btn" onclick="submitToCart()" class="btn-harvest" style="flex: 1; padding: 1.2rem; font-size: 1rem;">Add to Basket</button>
                </div>
                <div id="min-qty-notice" style="margin-top: 1rem; font-size: 0.75rem; color: var(--accent); font-weight: 800; display: none;"></div>
            </div>

            <div style="margin-top: 3rem; display: flex; gap: 3rem; font-size: 0.85rem; font-weight: 800; opacity: 0.5; text-transform: uppercase; letter-spacing: 1px;">
                <div style="display: flex; align-items: center; gap: 0.6rem;">🥬 Fresh Harvest</div>
                <div style="display: flex; align-items: center; gap: 0.6rem;">☀️ 100% Organic</div>
                <div style="display: flex; align-items: center; gap: 0.6rem;">📦 Fast Delivery</div>
            </div>
        </div>
    </div>
</div>

<script>
    function selectVar(el, id, price, original, isWholesale, minQty) {
        if (el.classList.contains('disabled')) return;
        document.querySelectorAll('.var-pill').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('selected-var').value = id;
        document.getElementById('min-order-qty').value = minQty;
        document.getElementById('qty').value = minQty; // Auto-set to min
        
        document.getElementById('active-price').innerText = '$' + parseFloat(price).toFixed(2);
        
        const origBox = document.getElementById('original-price-box');
        const badge = document.getElementById('discount-badge');
        const wBadge = document.getElementById('wholesale-badge');
        const minNotice = document.getElementById('min-qty-notice');
        
        wBadge.style.display = isWholesale ? 'block' : 'none';
        
        if (minQty > 1) {
            minNotice.innerText = `Minimum order for this selection is ${minQty} units.`;
            minNotice.style.display = 'block';
        } else {
            minNotice.style.display = 'none';
        }

        if (original && original > price) {
            origBox.innerText = '$' + parseFloat(original).toFixed(2);
            origBox.style.display = 'block';
            const pct = Math.round(((original - price) / original) * 100);
            badge.innerText = `SAVE ${pct}%`;
            badge.style.display = 'block';
        } else {
            origBox.style.display = 'none';
            badge.style.display = 'none';
        }
    }

    function updateQty(delta) {
        const input = document.getElementById('qty');
        const min = parseInt(document.getElementById('min-order-qty').value) || 1;
        let v = parseInt(input.value) + delta;
        if (v < min) v = min;
        input.value = v;
    }

    function submitToCart() {
        const varId = document.getElementById('selected-var').value;
        const qty = parseInt(document.getElementById('qty').value);
        const min = parseInt(document.getElementById('min-order-qty').value) || 1;

        if (!varId) { Toast.show("Please select a variant.", "error"); return; }
        if (qty < min) { Toast.show(`Minimum quantity is ${min}.`, "error"); return; }
        
        const btn = document.getElementById('add-to-cart-btn');
        const oldText = btn.innerText;
        btn.innerText = "✅ Added!";
        btn.style.background = "#4A8B42";
        
        Cart.add(parseInt(varId), qty);
        
        setTimeout(() => {
            btn.innerText = oldText;
            btn.style.background = "var(--primary)";
        }, 2000);
    }
</script>

<?php include 'templates/footer.php'; ?>
