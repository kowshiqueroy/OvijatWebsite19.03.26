<?php
/**
 * Shopping Cart Management - Premium Redesign
 */
restrict_to(['wholesale_user', 'admin', 'manager', 'executive']);

// Initialize Cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Handle Actions
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)$_POST['product_id'];
    $qty = (int)$_POST['qty'];
    $variant_id = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
    
    // Check if product exists and get min qty + stock
    $stmt = $pdo->prepare("SELECT id, min_order_qty, stock_qty FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$pid]);
    $product = $stmt->fetch();

    if ($product) {
        if ($qty < $product['min_order_qty']) $qty = $product['min_order_qty'];
        
        $max_stock = $product['stock_qty'];
        if ($variant_id > 0) {
            $vstmt = $pdo->prepare("SELECT stock_qty FROM product_variants WHERE id = ? AND product_id = ? AND is_deleted = 0");
            $vstmt->execute([$variant_id, $pid]);
            $v_stock = $vstmt->fetchColumn();
            if ($v_stock !== false) {
                $max_stock = (int)$v_stock;
            }
        }
        
        $item_key = $pid . '_' . $variant_id;
        $new_qty = ($_SESSION['cart'][$item_key] ?? 0) + $qty;
        if ($new_qty > $max_stock) {
            $_SESSION['cart_error'] = "Requested quantity ($new_qty) exceeds available stock ($max_stock) for this item.";
            header('Location: ' . BASE_URL . 'cart');
            exit;
        }
        
        $_SESSION['cart'][$item_key] = $new_qty;
        header('Location: ' . BASE_URL . 'cart');
        exit;
    } else {
        $_SESSION['cart_error'] = "Product not found or unavailable.";
        header('Location: ' . BASE_URL . 'catalog');
        exit;
    }
}

if ($action === 'remove') {
    $item_key = $_GET['id'] ?? '';
    unset($_SESSION['cart'][$item_key]);
    header('Location: ' . BASE_URL . 'cart');
    exit;
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['qtys'] as $item_key => $qty) {
        $qty = (int)$qty;
        
        $parts = explode('_', $item_key);
        $pid = (int)$parts[0];
        $variant_id = (int)($parts[1] ?? 0);
        
        // Validate against stock limit (variant or product)
        $max_stock = 0;
        if ($variant_id > 0) {
            $vstmt = $pdo->prepare("SELECT stock_qty FROM product_variants WHERE id = ? AND product_id = ? AND is_deleted = 0");
            $vstmt->execute([$variant_id, $pid]);
            $max_stock = (int)$vstmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $max_stock = (int)$stmt->fetchColumn();
        }
        
        if ($qty > $max_stock) {
            $_SESSION['cart_error'] = "Requested quantity exceeds available stock for this product.";
            $qty = $max_stock; // Cap at stock
        }

        if ($qty <= 0) {
            unset($_SESSION['cart'][$item_key]);
        } else {
            $_SESSION['cart'][$item_key] = $qty;
        }
    }
    
    if (isset($_POST['redirect_to_checkout']) && $_POST['redirect_to_checkout'] === '1' && empty($_SESSION['cart_error'])) {
        header('Location: ' . BASE_URL . 'checkout');
    } else {
        header('Location: ' . BASE_URL . 'cart');
    }
    exit;
}

// Fetch user's default address location for delivery estimation
$user_location = null;
$user_has_address = false;
if (is_logged_in()) {
    $uid = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT a.location_id FROM user_addresses a WHERE a.user_id = ? AND a.is_default = 1 AND a.is_deleted = 0 LIMIT 1");
    $stmt->execute([$uid]);
    $default_addr = $stmt->fetch();
    if ($default_addr && $default_addr['location_id']) {
        $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$default_addr['location_id']]);
        $user_location = $stmt->fetch();
    }
    // Check if user has any address at all
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ? AND is_deleted = 0");
    $stmt->execute([$uid]);
    $user_has_address = $stmt->fetchColumn() > 0;
}

$per_unit_weight_charge = $user_location ? (float)$user_location['per_unit_weight_charge'] : 0;

// Fetch Cart Item Details with Tiered Pricing Logic
$cart_items = [];
$total_subtotal = 0;
$total_weight = 0;
$total_delivery = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item_key => $qty) {
        $parts = explode('_', $item_key);
        $pid = (int)$parts[0];
        $variant_id = (int)($parts[1] ?? 0);

        // Get product and its tiers
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $product = $stmt->fetch();

        if ($product) {
            // Find applicable tier price
            $stmt = $pdo->prepare("SELECT unit_price FROM product_price_tiers WHERE product_id = ? AND min_qty <= ? ORDER BY min_qty DESC LIMIT 1");
            $stmt->execute([$pid, $qty]);
            $tier = $stmt->fetch();

            $discount = get_product_discount($pdo, $pid);
            $base_unit_price = $tier ? $tier['unit_price'] : $product['base_price'];
            $price = calculate_discounted_price($base_unit_price, $discount);
            
            // Apply variant price modifier if any
            $variant_name = '';
            if ($variant_id > 0) {
                $vstmt = $pdo->prepare("SELECT * FROM product_variants WHERE id = ? AND product_id = ? AND is_deleted = 0");
                $vstmt->execute([$variant_id, $pid]);
                $variant = $vstmt->fetch();
                if ($variant) {
                    $price += (float)$variant['price_modifier'];
                    $variant_name = $variant['variant_type'] . ': ' . $variant['variant_value'];
                }
            }

            $subtotal = $price * $qty;
            $total_subtotal += $subtotal;

            $weight = (float)($product['weight'] ?? 0);
            $item_weight = $weight * $qty;
            $total_weight += $item_weight;

            $delivery = $item_weight * $per_unit_weight_charge;
            $total_delivery += $delivery;

            $cart_items[] = [
                'item_key' => $item_key,
                'id' => $pid,
                'variant_id' => $variant_id,
                'weight' => $weight,
                'item_weight' => $item_weight,
                'delivery' => $delivery,
                'name' => $product['name'] . ($variant_name ? ' (' . $variant_name . ')' : ''),
                'qty' => $qty,
                'unit_price' => $price,
                'subtotal' => $subtotal,
                'is_bulk' => (bool)$tier
            ];
        }
    }
}

$base_delivery = $user_location ? (float)$user_location['base_delivery_charge'] : 0;
$grand_delivery = $base_delivery + $total_delivery;

// Get MOV (Minimum Order Value)
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'min_order_value'");
$stmt->execute();
$mov = (float)($stmt->fetch()['setting_value'] ?? 0);

?>

<!-- Checkout Step Indicators -->
<div style="display: flex; justify-content: center; gap: 2rem; margin-bottom: 3.5rem; flex-wrap: wrap;">
    <div style="display: flex; align-items: center; gap: 0.5rem;">
        <span style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; box-shadow: 0 0 10px var(--primary-glow);">1</span>
        <strong style="color: var(--secondary);">Review Cart</strong>
    </div>
    <div style="width: 60px; height: 1px; background: var(--border-light); align-self: center;"></div>
    <div style="display: flex; align-items: center; gap: 0.5rem; opacity: 0.5;">
        <span style="width: 32px; height: 32px; border-radius: 50%; background: var(--text-muted); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">2</span>
        <strong style="color: var(--text-muted);">Checkout Details</strong>
    </div>
    <div style="width: 60px; height: 1px; background: var(--border-light); align-self: center;"></div>
    <div style="display: flex; align-items: center; gap: 0.5rem; opacity: 0.5;">
        <span style="width: 32px; height: 32px; border-radius: 50%; background: var(--text-muted); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">3</span>
        <strong style="color: var(--text-muted);">Order Confirmation</strong>
    </div>
</div>

<div class="section-title">
    <i class="fas fa-shopping-cart"></i>
    Wholesale Cart Summary
</div>

<?php if (!empty($_SESSION['cart_error'])): ?>
    <div style="background: rgba(244, 63, 94, 0.08); color: #991b1b; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.15);">
        <i class="fas fa-exclamation-triangle"></i> <?php echo e($_SESSION['cart_error']); ?>
    </div>
    <?php unset($_SESSION['cart_error']); ?>
<?php endif; ?>

<?php if (empty($cart_items)): ?>
    <div style="text-align: center; padding: 5rem 2rem; background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-light); box-shadow: var(--glass-shadow);">
        <i class="fas fa-shopping-basket" style="font-size: 4.5rem; color: var(--text-muted); opacity: 0.2; margin-bottom: 1.5rem;"></i>
        <h3 style="color: var(--secondary); font-size: 1.45rem; font-weight: 700; margin-bottom: 0.5rem;">Your cart is empty</h3>
        <p style="color: var(--text-muted); margin-bottom: 2.25rem;">Looks like you haven't added any bulk catalog items yet.</p>
        <a href="/bolakausa/home" class="btn btn-green"><i class="fas fa-store"></i> Browse wholesale Catalog</a>
    </div>
<?php else: ?>
    <form method="POST" action="/bolakausa/cart" id="cart-form">
        <input type="hidden" name="action" value="update">
        
        <div class="grid-stack-mobile" style="display: grid; grid-template-columns: 1.6fr 1fr; gap: 2rem; align-items: flex-start; flex-wrap: wrap;">
            <!-- Cart Items List -->
            <div class="table-wrap" style="margin-bottom: 0; overflow-x: hidden;">
                    <table style="min-width: 0; width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 28%;">Product</th>
                            <th style="width: 10%;">Qty</th>
                            <th style="width: 14%;">Unit Price</th>
                            <th style="width: 20%; text-align: right;">Subtotal</th>
                            <th style="width: 20%; text-align: right;">Delivery</th>
                            <th style="width: 8%;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                        <tr id="cart-row-<?php echo $item['item_key']; ?>" data-price="<?php echo $item['unit_price']; ?>" data-weight="<?php echo $item['weight']; ?>" data-del-rate="<?php echo $per_unit_weight_charge; ?>">
                            <td>
                                <strong style="color: var(--secondary); font-size: 0.9rem; display: block;"><?php echo e($item['name']); ?></strong>
                                <?php if ($item['is_bulk']): ?>
                                    <span style="display: inline-block; background: rgba(16,185,129,0.1); color: var(--primary-dark); padding: 1px 6px; border-radius: 4px; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; margin-top: 0.15rem; border: 1px solid rgba(16,185,129,0.15);">Bulk</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number" name="qtys[<?php echo $item['item_key']; ?>]" value="<?php echo $item['qty']; ?>" min="1" class="cart-qty-input" data-row="<?php echo $item['item_key']; ?>" style="width: 52px; padding: 0.3rem; text-align: center; border-radius: 6px; font-size: 0.85rem;">
                            </td>
                            <td style="color: var(--text-muted); font-weight: 500; font-size: 0.85rem;">$<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td style="text-align: right;" class="subtotal-cell">$<?php echo number_format($item['subtotal'], 2); ?></td>
                            <td style="text-align: right; font-size: 0.8rem; color: var(--accent);" class="delivery-cell">
                                <small style="display: block; font-size: 0.6rem; color: var(--text-muted); font-weight: 600;"><?php echo number_format($item['weight'], 2); ?>kg &times; $<?php echo number_format($per_unit_weight_charge, 2); ?> &times; <?php echo $item['qty']; ?></small>
                                <strong style="font-size: 0.9rem;">$<?php echo number_format($item['delivery'], 2); ?></strong>
                            </td>
                            <td style="text-align: right;">
                                <a href="/bolakausa/cart?action=remove&id=<?php echo $item['item_key']; ?>" class="btn btn-red" style="padding: 0.3rem 0.5rem; border-radius: 6px; font-size: 0.7rem;"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Side Summary Box -->
            <div>
                <div class="card" style="padding: 2rem;">
                    <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); padding-bottom: 0.75rem;">Summary</h3>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: 0.95rem; color: var(--text-muted);">
                        <span>Items Count:</span>
                        <strong id="cart-items-count" style="color: var(--secondary);"><?php echo count($cart_items); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-light); font-size: 1.1rem;">
                        <span style="font-weight: 700; color: var(--secondary);">Subtotal</span>
                        <strong id="cart-grand-subtotal" style="color: var(--primary); font-size: 1.5rem; font-family: 'Plus Jakarta Sans', sans-serif;">$<?php echo number_format($total_subtotal, 2); ?></strong>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.85rem; color: var(--text-muted);">
                        <span>Total Weight:</span>
                        <strong id="cart-total-weight" style="color: var(--secondary);"><?php echo number_format($total_weight, 2); ?> kg</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.85rem; color: var(--text-muted);">
                        <span>Weight Delivery:</span>
                        <strong id="cart-weight-delivery" style="color: var(--accent);">$<?php echo number_format($total_delivery, 2); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1.25rem; font-size: 0.85rem; color: var(--text-muted);">
                        <span>Base Delivery Fee:</span>
                        <strong id="cart-base-delivery" style="color: var(--accent);">$<?php echo number_format($base_delivery, 2); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-light); font-size: 0.95rem;">
                        <span style="font-weight: 800; color: var(--secondary);">Total Delivery:</span>
                        <strong id="cart-grand-delivery" style="color: var(--accent); font-size: 1.1rem;">$<?php echo number_format($grand_delivery, 2); ?></strong>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; font-size: 1.15rem;">
                        <span style="font-weight: 800; color: var(--secondary);">Total</span>
                        <strong id="cart-grand-total" style="color: var(--secondary); font-size: 1.7rem; font-family: 'Plus Jakarta Sans', sans-serif;">$<?php echo number_format($total_subtotal + $grand_delivery, 2); ?></strong>
                    </div>
                    
                    <?php if ($user_location): ?>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-bottom: 1.25rem; text-align: center; line-height: 1.4;">
                            <i class="fas fa-info-circle"></i> Delivery estimated for your default address: <strong><?php echo e($user_location['name']); ?></strong>.
                        </div>
                    <?php elseif ($user_has_address): ?>
                        <div style="font-size: 0.7rem; color: var(--rose); margin-bottom: 1.25rem; text-align: center; line-height: 1.4;">
                            <i class="fas fa-exclamation-triangle"></i> Your default address is missing a delivery zone. <a href="/bolakausa/account" style="font-weight: 800; color: var(--rose);">Update your address</a> to get accurate delivery estimates.
                        </div>
                    <?php else: ?>
                        <div style="font-size: 0.7rem; color: var(--rose); margin-bottom: 1.25rem; text-align: center; line-height: 1.4;">
                            <i class="fas fa-exclamation-triangle"></i> No shipping address set. <a href="/bolakausa/account" style="font-weight: 800; color: var(--rose);">Add a delivery address</a> in your account settings to see accurate delivery estimates.
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($total_subtotal >= $mov): ?>
                        <button type="button" id="proceed-to-checkout-btn" class="btn btn-green" style="width: 100%; border-radius: 10px; padding: 1rem; font-size: 1rem; border: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; cursor: pointer;">
                            Proceed to Checkout <i class="fas fa-chevron-right" style="font-size: 0.8rem;"></i>
                        </button>
                    <?php else: ?>
                        <div style="color: var(--rose); font-weight: 700; background: rgba(244,63,94,0.06); padding: 1rem; border-radius: 8px; border: 1px solid rgba(244,63,94,0.15); font-size: 0.8rem; line-height: 1.4; text-align: center;">
                            <i class="fas fa-exclamation-triangle" style="margin-bottom: 0.4rem; display: block; font-size: 1.1rem;"></i> 
                            Wholesale minimum order value is <strong>$<?php echo number_format($mov, 2); ?></strong>.<br>
                            Add <strong>$<?php echo number_format($mov - $total_subtotal, 2); ?></strong> more to proceed.
                        </div>
                    <?php endif; ?>
                </div>
                
                <p style="text-align: center; margin-top: 1rem;"><a href="/bolakausa/home" style="text-decoration: none; color: var(--text-muted); font-size: 0.85rem; font-weight: 700;"><i class="fas fa-arrow-left"></i> Continue Catalog Shopping</a></p>
            </div>
        </div>
    </form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.cart-qty-input');
    inputs.forEach(function(inp) {
        inp.addEventListener('input', function() {
            recalcRow(this);
            recalcSummary();
        });
    });

    function recalcRow(inp) {
        const row = inp.closest('tr');
        if (!row) return;
        const price = parseFloat(row.getAttribute('data-price')) || 0;
        const weight = parseFloat(row.getAttribute('data-weight')) || 0;
        const rate = parseFloat(row.getAttribute('data-del-rate')) || 0;
        const qty = parseInt(inp.value) || 0;

        const subtotal = price * qty;
        const itemWeight = weight * qty;
        const delivery = itemWeight * rate;

        row.querySelector('.subtotal-cell').textContent = '$' + subtotal.toFixed(2);
        const dc = row.querySelector('.delivery-cell');
        if (dc) {
            const small = dc.querySelector('small');
            if (small) small.textContent = weight.toFixed(2) + 'kg \u00d7 $' + rate.toFixed(2) + ' \u00d7 ' + qty;
            const strong = dc.querySelector('strong');
            if (strong) strong.textContent = '$' + delivery.toFixed(2);
        }
    }

    function recalcSummary() {
        let totalSub = 0, totalW = 0, totalDel = 0, count = 0;
        document.querySelectorAll('#cart-form .cart-qty-input').forEach(function(inp) {
            const row = inp.closest('tr');
            if (!row) return;
            const price = parseFloat(row.getAttribute('data-price')) || 0;
            const weight = parseFloat(row.getAttribute('data-weight')) || 0;
            const rate = parseFloat(row.getAttribute('data-del-rate')) || 0;
            const qty = parseInt(inp.value) || 0;
            if (qty > 0) {
                totalSub += price * qty;
                totalW += weight * qty;
                totalDel += weight * qty * rate;
                count++;
            }
        });

        const base = <?php echo json_encode($base_delivery); ?>;
        const grandDel = base + totalDel;
        const grandTotal = totalSub + grandDel;
        document.getElementById('cart-items-count').textContent = count;
        document.getElementById('cart-total-weight').textContent = totalW.toFixed(2) + ' kg';
        document.getElementById('cart-weight-delivery').textContent = '$' + totalDel.toFixed(2);
        document.getElementById('cart-grand-delivery').textContent = '$' + grandDel.toFixed(2);
        document.getElementById('cart-grand-subtotal').textContent = '$' + totalSub.toFixed(2);
        const gt = document.getElementById('cart-grand-total');
        if (gt) gt.textContent = '$' + grandTotal.toFixed(2);
    }

    const checkoutBtn = document.getElementById('proceed-to-checkout-btn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = document.getElementById('cart-form');
            if (form) {
                const redirectInput = document.createElement('input');
                redirectInput.type = 'hidden';
                redirectInput.name = 'redirect_to_checkout';
                redirectInput.value = '1';
                form.appendChild(redirectInput);
                form.submit();
            }
        });
    }
});
</script>
<?php endif; ?>
