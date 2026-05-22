<?php
/**
 * Global Checkout Page
 * Clean, distraction-free, and high-conversion.
 */
require_once 'includes/Database.php';
require_once 'includes/AuthManager.php';
require_once 'includes/CartManager.php';
require_once 'includes/CheckoutManager.php';

$db = Database::getInstance();
$cartManager = new CartManager();
$checkoutManager = new CheckoutManager();

$userId = AuthManager::isLoggedIn() ? $_SESSION['user_id'] : null;
$userEmail = AuthManager::isLoggedIn() ? $_SESSION['user_email'] : null;
$error = '';
$orderId = null;

// Initial Sync
$clientCart = json_decode($_POST['cart_json'] ?? '[]', true);
$cartData = $cartManager->syncCart($clientCart);

// Logic: Determine selected state for tax/shipping calculation
$selectedState = $_POST['shipping_state'] ?? null;
if ($userId && !$selectedState) {
    // If logged in and no post data, try default address
    $defAddr = $db->query("SELECT state FROM user_addresses WHERE user_id = ? AND address_type = 'shipping' AND is_default = 1", [$userId])->fetch();
    if ($defAddr) $selectedState = $defAddr['state'];
}

$totals = $cartManager->calculateTotals($cartData['subtotal'], $cartData['total_weight'], $selectedState);

// Fetch Active Gateways & States
$gateways = $db->query("SELECT * FROM payment_gateways WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
$states = $db->query("SELECT * FROM state_taxes ORDER BY state_name ASC")->fetchAll();

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh.';
    } elseif (empty($cartData['items'])) {
        $error = 'Your basket is empty.';
    } else {
        $gatewayId = (int)($_POST['payment_gateway_id'] ?? 0);
        $gateway = $db->query("SELECT * FROM payment_gateways WHERE id = ?", [$gatewayId])->fetch();
        
        if (!$gateway) {
            $error = 'Please select a valid payment method.';
        } else {
            $paymentMethod = $gateway['gateway_type'];
            $guestData = [
                'note' => $_POST['order_note'] ?? null,
                'email' => $_POST['shipping_email'] ?? $userEmail,
                'name' => $_POST['shipping_name'] ?? '',
                'phone' => $_POST['shipping_phone'] ?? '',
                'address' => $_POST['shipping_address'] ?? '',
                'state' => $_POST['shipping_state'] ?? '',
                'billing_name' => $_POST['billing_name'] ?? '',
                'billing_email' => $_POST['billing_email'] ?? '',
                'billing_phone' => $_POST['billing_phone'] ?? '',
                'billing_address' => $_POST['billing_address'] ?? '',
                'billing_state' => $_POST['billing_state'] ?? ''
            ];

            // If same as shipping
            if (isset($_POST['same_as_shipping'])) {
                $guestData['billing_name'] = $guestData['name'];
                $guestData['billing_email'] = $guestData['email'];
                $guestData['billing_phone'] = $guestData['phone'];
                $guestData['billing_address'] = $guestData['address'];
                $guestData['billing_state'] = $guestData['state'];
            }

            // Simple validation
            if (empty($guestData['name']) || empty($guestData['address']) || empty($guestData['state'])) {
                $error = 'Please provide complete shipping and state information.';
            } else {
                // Final calculation based on submission state
                $finalTotals = $cartManager->calculateTotals($cartData['subtotal'], $cartData['total_weight'], $guestData['state']);
                
                $orderId = $checkoutManager->createOrder($userId, $cartData, $finalTotals, $paymentMethod, $_POST['payment_details'] ?? null, $guestData);
                
                if ($orderId) {
                    // Handle Stripe Authorization
                    if ($paymentMethod === 'stripe' && !empty($_POST['stripe_payment_method_id'])) {
                        $authResult = $checkoutManager->authorizePayment($orderId, $_POST['stripe_payment_method_id']);
                        if (!$authResult['success']) {
                            $error = "Payment failed: " . $authResult['message'];
                            $db->query("UPDATE orders SET status = 'cancelled', order_note = ? WHERE id = ?", ["Payment failed: " . $authResult['message'], $orderId]);
                            $orderId = null;
                        }
                    }
                    
                    if ($orderId) {
                        $_SESSION['clear_cart'] = true;
                        header("Location: checkout?success=1&order_id=" . $orderId);
                        exit;
                    }
                }
            }
        }
    }
}

$pageTitle = 'Secure Checkout';
include 'templates/header.php';
?>

<script src="https://js.stripe.com/v3/"></script>

<style>
    .checkout-layout { display: grid; grid-template-columns: 1fr 400px; gap: 4rem; max-width: 1200px; margin: 0 auto; align-items: start; }
    .checkout-card { background: white; padding: 2.5rem; border-radius: 30px; box-shadow: var(--card-shadow); border: 1px solid var(--border); margin-bottom: 2rem; }
    .checkout-title { font-size: 1.3rem; font-weight: 800; display: flex; align-items: center; gap: 0.8rem; color: var(--text); margin-bottom: 2rem; }
    
    .summary-box { position: sticky; top: 120px; }
    
    .gateway-option { display: flex; align-items: center; gap: 1rem; padding: 1.2rem; border-radius: 16px; border: 2px solid var(--border); cursor: pointer; transition: var(--transition); margin-bottom: 0.8rem; }
    .gateway-option:has(input:checked) { border-color: var(--primary); background: var(--bg); }
    
    .step-num { background: var(--primary); color: white; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; }

    select { padding: 12px; border-radius: 12px; border: 2px solid var(--border); width: 100%; font-family: inherit; font-size: 0.9rem; outline: none; transition: 0.2s; background: #f8fafc; }
    select:focus { border-color: var(--primary); background: white; }

    @media (max-width: 992px) { .checkout-layout { grid-template-columns: 1fr; gap: 2rem; } .summary-box { position: static; } }
</style>

<div class="section-container" style="padding-top: 2rem;">
    
    <?php if (isset($_GET['success'])): ?>
        <div class="checkout-card" style="text-align: center; max-width: 600px; margin: 4rem auto;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">🌳</div>
            <h1 style="font-weight: 800; color: var(--primary);">Order Received!</h1>
            <p style="opacity: 0.7; margin-bottom: 2.5rem;">Your fresh harvest is being prepared. Order #<?php echo (int)$_GET['order_id']; ?></p>
            <a href="catalog" class="btn-harvest" style="display:inline-block; text-decoration:none;">Return to Shop</a>
            <script>localStorage.removeItem('sobji_cart');</script>
        </div>
    <?php else: ?>

        <div class="checkout-layout">
            
            <div id="logistics-flow">
                <?php if (!$userId): ?>
                    <div class="checkout-card reveal" style="padding: 1.5rem; margin-bottom: 2rem; border-style: dashed; border-color: var(--primary);">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 style="font-weight: 800; font-size: 1rem;">Checkout as Guest</h3>
                                <p style="font-size: 0.8rem; opacity: 0.6;">Or sign in to use your saved addresses.</p>
                            </div>
                            <a href="login?return=checkout" class="btn-harvest" style="padding: 0.6rem 1.5rem; text-decoration:none;">Sign In</a>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" id="checkout-form">
                    <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="place_order">
                    <input type="hidden" name="cart_json" id="final-cart-json" value='<?php echo htmlspecialchars(json_encode($clientCart), ENT_QUOTES, 'UTF-8'); ?>'>

                    <div class="checkout-card reveal">
                        <h2 class="checkout-title"><span class="step-num">1</span> Shipping Details</h2>
                        
                        <?php if ($userId): ?>
                            <?php $shipAddrs = $db->query("SELECT * FROM user_addresses WHERE user_id = ? AND address_type = 'shipping'", [$userId])->fetchAll(); ?>
                            <?php if ($shipAddrs): ?>
                                <div style="display: grid; gap: 0.8rem; margin-bottom: 2rem;">
                                    <?php foreach ($shipAddrs as $a): ?>
                                        <label class="gateway-option">
                                            <input type="radio" name="saved_address_id" value="<?php echo $a['id']; ?>" <?php echo $a['is_default'] ? 'checked' : ''; ?> onchange="document.getElementById('manual-ship').style.display='none'; resyncCart();">
                                            <div style="flex:1;"><div style="font-weight:800;"><?php echo htmlspecialchars($a['full_name']); ?></div><div style="font-size:0.8rem; opacity:0.6;"><?php echo htmlspecialchars($a['address_line1']); ?></div></div>
                                        </label>
                                    <?php endforeach; ?>
                                    <label class="gateway-option"><input type="radio" name="saved_address_id" value="new" onchange="document.getElementById('manual-ship').style.display='block'"><strong>+ Use Different Address</strong></label>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div id="manual-ship" style="<?php echo ($userId && !empty($shipAddrs)) ? 'display:none;' : ''; ?>">
                            <div class="form-group">
                                <label>Recipient Name / Full Name</label>
                                <input type="text" name="shipping_name" placeholder="Who is receiving the order?" value="<?php echo htmlspecialchars($_POST['shipping_name'] ?? ''); ?>">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="shipping_email" placeholder="email@example.com" value="<?php echo htmlspecialchars($_POST['shipping_email'] ?? $userEmail); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="tel" name="shipping_phone" placeholder="+1 234 567 890" value="<?php echo htmlspecialchars($_POST['shipping_phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>State / Region (For Tax & Shipping)</label>
                                <select name="shipping_state" onchange="resyncCart()" required>
                                    <option value="">-- Select State --</option>
                                    <?php foreach ($states as $st): ?>
                                        <option value="<?php echo $st['state_code']; ?>" <?php echo ($selectedState === $st['state_code']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($st['state_name']); ?> (<?php echo number_format($st['tax_rate']*100, 1); ?>%)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Street Address</label>
                                <textarea name="shipping_address" rows="3" placeholder="Apt, Street, City, ZIP"><?php echo htmlspecialchars($_POST['shipping_address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <hr style="border:none; border-top:1px solid var(--border); margin: 2.5rem 0;">
                        
                        <h2 class="checkout-title"><span class="step-num">2</span> Billing & Notes</h2>
                        <label style="display:flex; align-items:center; gap:0.6rem; font-weight:800; font-size:0.9rem; cursor:pointer; margin-bottom: 2rem; color: var(--primary);">
                            <input type="checkbox" name="same_as_shipping" <?php echo (!isset($_POST['action']) || isset($_POST['same_as_shipping'])) ? 'checked' : ''; ?> onchange="document.getElementById('billing-box').style.display = this.checked ? 'none' : 'block'" style="width:20px; height:20px; accent-color:var(--primary);"> 
                            Billing is same as shipping
                        </label>
                        
                        <div id="billing-box" style="<?php echo (!isset($_POST['action']) || isset($_POST['same_as_shipping'])) ? 'display:none;' : 'display:block;'; ?> padding: 1.5rem; background: var(--bg); border-radius: 20px; margin-bottom: 2rem;">
                            <div class="form-group"><label>Billing Recipient / Name</label><input type="text" name="billing_name" placeholder="Full Name" value="<?php echo htmlspecialchars($_POST['billing_name'] ?? ''); ?>"></div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group"><label>Billing Email</label><input type="email" name="billing_email" placeholder="email@example.com" value="<?php echo htmlspecialchars($_POST['billing_email'] ?? ''); ?>"></div>
                                <div class="form-group"><label>Billing Phone</label><input type="tel" name="billing_phone" placeholder="+1..." value="<?php echo htmlspecialchars($_POST['billing_phone'] ?? ''); ?>"></div>
                            </div>
                            <div class="form-group">
                                <label>Billing State</label>
                                <select name="billing_state">
                                    <?php foreach ($states as $st): ?>
                                        <option value="<?php echo $st['state_code']; ?>" <?php echo (($_POST['billing_state'] ?? '') === $st['state_code']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st['state_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Billing Street Address</label><textarea name="billing_address" rows="3" placeholder="Full billing address"><?php echo htmlspecialchars($_POST['billing_address'] ?? ''); ?></textarea></div>
                        </div>

                        <div class="form-group">
                            <label>Delivery Note / Special Instructions</label>
                            <textarea name="order_note" rows="2" placeholder="e.g. Please leave by the garden gate."><?php echo htmlspecialchars($_POST['order_note'] ?? ''); ?></textarea>
                        </div>
                    </div>
            </div>

            <aside>
                <div class="summary-box reveal">
                    <div class="checkout-card" style="padding: 2rem; margin-bottom: 1.5rem;">
                        <h3 style="font-weight:800; margin-bottom:1.5rem;">Order Recap</h3>
                        <div style="max-height: 250px; overflow-y: auto; margin-bottom: 1.5rem; padding-right: 5px;">
                            <?php foreach ($cartData['items'] as $item): ?>
                                <div style="display:flex; justify-content:space-between; margin-bottom:1rem; font-size:0.85rem; font-weight:700; align-items:center;">
                                    <div style="display:flex; align-items:center; gap:0.8rem;">
                                        <div style="background:var(--bg); padding:4px 8px; border-radius:8px; font-size:0.75rem;"><?php echo $item['quantity']; ?>x</div>
                                        <span><?php echo htmlspecialchars($item['name']); ?></span>
                                    </div>
                                    <span style="color:var(--primary);">$<?php echo number_format($item['total_price'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="display:grid; gap:0.6rem; font-size:0.85rem; font-weight:700; opacity:0.7; border-top:1px solid var(--border); padding-top:1.5rem;">
                            <div style="display:flex; justify-content:space-between;"><span>Subtotal</span><span>$<?php echo number_format($totals['subtotal'], 2); ?></span></div>
                            <div style="display:flex; justify-content:space-between;"><span>Shipping</span><span>$<?php echo number_format($totals['shipping'], 2); ?></span></div>
                            <div style="display:flex; justify-content:space-between;"><span>Taxes</span><span>$<?php echo number_format($totals['tax'], 2); ?></span></div>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.5rem; padding-top:1.5rem; border-top:2px solid var(--primary);">
                            <span style="font-weight:800; font-size:1.1rem;">GRAND TOTAL</span>
                            <span style="font-size:1.8rem; font-weight:800; color:var(--primary);">$<?php echo number_format($totals['total'], 2); ?></span>
                        </div>
                    </div>

                    <div class="checkout-card" style="padding: 2rem;">
                        <h3 style="font-weight:800; margin-bottom:1.5rem;">Payment Method</h3>
                        <?php if ($error): ?><div class="alert alert-error" style="font-size:0.8rem; padding:10px; margin-bottom: 1.5rem;"><?php echo $error; ?></div><?php endif; ?>

                        <div style="display: grid; gap: 0.8rem;">
                            <?php foreach ($gateways as $g): ?>
                                <label class="gateway-option" style="padding:1rem; margin-bottom:0;">
                                    <input type="radio" name="payment_gateway_id" value="<?php echo $g['id']; ?>" onchange="showGatewayDetails(<?php echo $g['id']; ?>)" <?php echo (($_POST['payment_gateway_id'] ?? '') == $g['id'] || $g['gateway_type'] === 'stripe') ? 'checked' : ''; ?> style="accent-color:var(--primary);">
                                    <div style="flex:1;">
                                        <div style="font-weight:800; font-size:0.9rem;"><?php echo $g['icon_emoji']; ?> <?php echo htmlspecialchars($g['gateway_name']); ?></div>
                                    </div>
                                </label>
                                <div id="details-<?php echo $g['id']; ?>" class="gateway-info-box" style="display:none; background:var(--bg); padding:1.2rem; border-radius:16px; font-size:0.8rem; margin-top:0.5rem; border:1px solid var(--border);">
                                    <?php if ($g['gateway_type'] === 'stripe'): ?>
                                        <div style="margin-bottom: 1rem; color: var(--primary); font-weight: 700;">💳 Secure Card Payment</div>
                                        <div id="stripe-card-mount" style="background: white; padding: 12px; border-radius: 8px; border: 1px solid var(--border);"></div>
                                        <div id="card-errors" role="alert" style="color: var(--error); font-size: 0.75rem; margin-top: 0.5rem;"></div>
                                        <input type="hidden" name="stripe_payment_method_id" id="stripe-payment-method-id">
                                    <?php else: ?>
                                        <?php echo $g['details_html'] ?: 'Secure automated processing.'; ?>
                                        <?php if ($g['gateway_type'] === 'manual'): ?>
                                            <div class="form-group" style="margin-top:1rem; margin-bottom:0;">
                                                <label style="font-size:0.65rem;">Transaction / Reference ID</label>
                                                <input type="text" name="payment_details" placeholder="Transfer Ref #" value="<?php echo htmlspecialchars($_POST['payment_details'] ?? ''); ?>">
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" onclick="handleCheckout(this)" class="btn-harvest" style="width:100%; padding:1.2rem; margin-top:2rem; font-size:1.1rem; border:none; cursor:pointer; box-shadow:0 15px 30px rgba(45,90,39,0.25);">Place My Order &rarr;</button>
                    </div>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>

<script>
    const stripe = Stripe('<?php echo $s['stripe_publishable_key'] ?? ''; ?>');
    let elements, card;

    function showGatewayDetails(id) {
        document.querySelectorAll('.gateway-info-box').forEach(el => el.style.display = 'none');
        const box = document.getElementById('details-' + id);
        if (box) {
            box.style.display = 'block';
            const mountPoint = box.querySelector('#stripe-card-mount');
            if (mountPoint && !card) {
                elements = stripe.elements();
                card = elements.create('card', {
                    style: { base: { fontSize: '16px', color: '#1A3015', fontFamily: 'Quicksand, sans-serif' } }
                });
                card.mount('#stripe-card-mount');
            }
        }
    }

    async function handleCheckout(btn) {
        const form = document.getElementById('checkout-form');
        const hiddenAction = form.querySelector('input[name="action"]');
        hiddenAction.value = 'place_order';

        const stripeBox = document.getElementById('stripe-card-mount');
        const isStripe = stripeBox && stripeBox.offsetParent !== null;

        if (isStripe) {
            btn.disabled = true;
            btn.textContent = 'Processing Security...';
            
            const {paymentMethod, error} = await stripe.createPaymentMethod({
                type: 'card',
                card: card,
            });

            if (error) {
                document.getElementById('card-errors').textContent = error.message;
                btn.disabled = false;
                btn.textContent = 'Place My Order →';
            } else {
                document.getElementById('stripe-payment-method-id').value = paymentMethod.id;
                form.submit();
            }
        } else {
            form.submit();
        }
    }

    function resyncCart() {
        const form = document.getElementById('checkout-form');
        const hiddenAction = form.querySelector('input[name="action"]');
        hiddenAction.value = 'resync';
        form.submit();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const active = document.querySelector('input[name="payment_gateway_id"]:checked');
        if (active) showGatewayDetails(active.value);

        const items = Cart.get();
        if (items.length === 0 && !window.location.search.includes('success')) {
            window.location.href = 'cart';
        } else if (!<?php echo isset($_POST['cart_json']) ? 'true' : 'false'; ?>) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.innerHTML = `<input type="hidden" name="cart_json" value='${JSON.stringify(items)}'>`;
            document.body.appendChild(f);
            f.submit();
        }
    });
</script>
<?php include 'templates/footer.php'; ?>