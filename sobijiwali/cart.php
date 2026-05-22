<?php
/**
 * Shopping Cart Page
 */
require_once 'includes/Database.php';
require_once 'includes/AuthManager.php';
require_once 'includes/CartManager.php';

$cartManager = new CartManager();
$clientCart = [];
$cartData = ['items' => [], 'subtotal' => 0];
$totals = ['subtotal' => 0, 'shipping' => 0, 'tax' => 0, 'total' => 0];

// Handle AJAX Sync or Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Security violation.");
    }
    $clientCart = json_decode($_POST['cart_json'] ?? '[]', true);
    $cartData = $cartManager->syncCart($clientCart);
    $totals = $cartManager->calculateTotals($cartData['subtotal']);
}

$pageTitle = 'Your Harvest Basket';
include 'templates/header.php';
?>

<style>
    .cart-layout { display: grid; grid-template-columns: 1fr 380px; gap: 4rem; max-width: 1100px; margin: 0 auto; align-items: start; }
    .cart-list-card { background: white; padding: 2.5rem; border-radius: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow); }
    .cart-item { display: flex; align-items: center; gap: 2rem; padding: 1.5rem 0; border-bottom: 1px solid #f9f9f9; }
    .cart-item:last-child { border: none; }
    
    .qty-control { display: flex; align-items: center; gap: 1rem; background: var(--bg); padding: 0.5rem 1rem; border-radius: 12px; }
    .qty-btn { border: none; background: none; font-size: 1.2rem; font-weight: 800; cursor: pointer; color: var(--primary); width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: var(--transition); }
    .qty-btn:hover { background: var(--white); }

    @media (max-width: 992px) {
        .cart-layout { grid-template-columns: 1fr; gap: 2rem; }
    }
</style>

<div class="section-container" style="padding-top: 2rem;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3rem;">
        <h1 style="font-weight: 800; font-size: 2.5rem; color: var(--text);">Your Basket</h1>
        <a href="catalog" style="font-weight: 800; color: var(--primary); text-decoration: none;">&larr; Keep Shopping</a>
    </div>

    <div id="cart-content">
        <div style="text-align: center; padding: 5rem; opacity: 0.5;" id="cart-loading">
            <div class="loader-logo">🧺</div>
            <p style="margin-top: 1rem; font-weight: 800;">Gathering your items...</p>
        </div>
    </div>

    <!-- Hidden Sync Form -->
    <form id="sync-form" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
        <input type="hidden" name="cart_json" id="cart-json-input">
    </form>

    <?php if (!empty($cartData['items'])): ?>
        <div class="cart-layout">
            
            <!-- Items -->
            <div class="cart-list-card reveal">
                <?php foreach ($cartData['items'] as $item): ?>
                    <div class="cart-item">
                        <div style="width: 80px; height: 80px; background: var(--bg); border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 2.5rem;">🥗</div>
                        <div style="flex: 1;">
                            <h3 style="font-weight: 800; margin-bottom: 0.2rem;"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <div style="font-size: 0.75rem; opacity: 0.5; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">SKU: <?php echo $item['sku']; ?></div>
                            <?php if (!$item['in_stock']): ?>
                                <p style="color: var(--error); font-size: 0.7rem; font-weight: 800; margin-top: 0.5rem;">Only <?php echo $item['quantity']; ?> available</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="qty-control">
                            <button class="qty-btn" onclick="updateQty(<?php echo $item['variation_id']; ?>, -1)">−</button>
                            <span style="font-weight: 800; min-width: 25px; text-align: center;"><?php echo $item['quantity']; ?></span>
                            <button class="qty-btn" onclick="updateQty(<?php echo $item['variation_id']; ?>, 1)">+</button>
                        </div>

                        <div style="width: 100px; text-align: right; font-weight: 800; font-size: 1.1rem; color: var(--primary);">
                            $<?php echo number_format($item['total_price'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Summary Sidebar -->
            <aside>
                <div class="cart-list-card reveal" style="position: sticky; top: 120px;">
                    <h3 style="font-weight: 800; margin-bottom: 2rem;">Order Summary</h3>
                    
                    <div style="display: grid; gap: 1rem; font-size: 0.95rem; font-weight: 700; opacity: 0.7; margin-bottom: 2rem;">
                        <div style="display: flex; justify-content: space-between;"><span>Subtotal</span> <span>$<?php echo number_format($totals['subtotal'], 2); ?></span></div>
                        <div style="display: flex; justify-content: space-between;"><span>Shipping</span> <span style="color: var(--primary);"><?php echo $totals['shipping'] > 0 ? '$' . number_format($totals['shipping'], 2) : 'FREE'; ?></span></div>
                        <div style="display: flex; justify-content: space-between;"><span>Estimated Tax</span> <span>$<?php echo number_format($totals['tax'], 2); ?></span></div>
                    </div>

                    <div style="border-top: 1px dashed var(--border); padding-top: 1.5rem; display: flex; justify-content: space-between; align-items: flex-end;">
                        <span style="font-weight: 800;">Grand Total</span>
                        <span style="font-size: 2rem; font-weight: 800; color: var(--primary); letter-spacing: -1.5px;">$<?php echo number_format($totals['total'], 2); ?></span>
                    </div>

                    <form action="checkout" method="POST" style="margin-top: 2.5rem;">
                        <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                        <input type="hidden" name="cart_json" value='<?php echo htmlspecialchars(json_encode($clientCart), ENT_QUOTES, 'UTF-8'); ?>'>
                        <button type="submit" class="btn-harvest" style="width: 100%; padding: 1.4rem; font-size: 1rem; border: none; cursor: pointer;">Proceed to Checkout</button>
                    </form>
                </div>
            </aside>

        </div>
    <?php endif; ?>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const items = Cart.get();
        const content = document.getElementById('cart-content');
        
        if (items.length === 0) {
            content.innerHTML = `
                <div style="text-align: center; padding: 6rem; background: white; border-radius: 30px; border: 1px dashed var(--border);">
                    <div style="font-size: 4rem; margin-bottom: 1.5rem;">🧺</div>
                    <h2 style="font-weight: 800; margin-bottom: 1rem;">Your basket is empty</h2>
                    <p style="opacity: 0.6; margin-bottom: 2.5rem;">Start adding fresh produce from our fields.</p>
                    <a href="catalog" class="btn-harvest" style="display: inline-block; text-decoration: none;">Browse Catalog</a>
                </div>
            `;
        } else if (!<?php echo isset($_POST['cart_json']) ? 'true' : 'false'; ?>) {
            document.getElementById('cart-json-input').value = JSON.stringify(items);
            document.getElementById('sync-form').submit();
        } else {
            document.getElementById('cart-loading').style.display = 'none';
        }
    });

    function updateQty(id, delta) {
        let items = Cart.get();
        let i = items.find(x => x.variation_id === id);
        if (i) {
            i.quantity += delta;
            if (i.quantity <= 0) items = items.filter(x => x.variation_id !== id);
            Cart.save(items);
            document.getElementById('cart-json-input').value = JSON.stringify(items);
            document.getElementById('sync-form').submit();
        }
    }
</script>

<?php include 'templates/footer.php'; ?>
