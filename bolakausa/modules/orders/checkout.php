<?php
/**
 * Checkout Module - Premium Redesign
 */
restrict_to(['wholesale_user', 'executive']);

if (empty($_SESSION['cart'])) {
    header('Location: ' . BASE_URL . 'cart');
    exit;
}

$user_id = $_SESSION['user_id'];

$address_success = '';
$address_error = '';

// Fetch User Addresses with location info
$stmt = $pdo->prepare("SELECT a.*, l.name as location_name, l.tax_percent, l.base_delivery_charge, l.per_unit_weight_charge FROM user_addresses a LEFT JOIN locations l ON a.location_id = l.id WHERE a.user_id = ? AND a.is_deleted = 0");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll();

// Get default address location_id for pre-selecting the delivery zone
$default_location_id = null;
foreach ($addresses as $a) {
    if ($a['is_default'] && $a['location_id']) {
        $default_location_id = (int)$a['location_id'];
        break;
    }
}

// Fetch Wallet Balance
$stmt = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet_balance = (float)($stmt->fetch()['balance'] ?? 0);

// Fetch Active Shipping Locations
$locations = $pdo->query("SELECT * FROM locations WHERE is_deleted = 0")->fetchAll();

// Calculate Subtotal and Total Weight
$subtotal = 0;
$total_weight = 0;
foreach ($_SESSION['cart'] as $item_key => $qty) {
    $parts = explode('_', $item_key);
    $pid = (int)$parts[0];
    $variant_id = (int)($parts[1] ?? 0);

    $stmt = $pdo->prepare("SELECT base_price, weight FROM products WHERE id = ?");
    $stmt->execute([$pid]);
    $product = $stmt->fetch();
    if ($product) {
        // Find tier price
        $stmt = $pdo->prepare("SELECT unit_price FROM product_price_tiers WHERE product_id = ? AND min_qty <= ? ORDER BY min_qty DESC LIMIT 1");
        $stmt->execute([$pid, $qty]);
        $tier = $stmt->fetch();
        
        $discount = get_product_discount($pdo, $pid, $user_id);
        $base_unit_price = $tier ? $tier['unit_price'] : $product['base_price'];
        $price = calculate_discounted_price($base_unit_price, $discount);
        
        if ($variant_id > 0) {
            $vstmt = $pdo->prepare("SELECT price_modifier FROM product_variants WHERE id = ? AND product_id = ? AND is_deleted = 0");
            $vstmt->execute([$variant_id, $pid]);
            $mod = $vstmt->fetchColumn();
            if ($mod !== false) {
                $price += (float)$mod;
            }
        }
        
        $subtotal += ($price * $qty);
        $total_weight += ($product['weight'] * $qty);
    }
}

// AJAX Coupon Validation Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'apply_coupon') {
    header('Content-Type: application/json');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    
    if (!$code) {
        echo json_encode(['success' => false, 'message' => 'Please enter a coupon code.']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();
    
    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Invalid or inactive coupon code.']);
        exit;
    }
    
    $now = date('Y-m-d H:i:s');
    if ($coupon['start_date'] && $coupon['start_date'] > $now) {
        echo json_encode(['success' => false, 'message' => 'This coupon validity has not started yet.']);
        exit;
    }
    if ($coupon['end_date'] && $coupon['end_date'] < $now) {
        echo json_encode(['success' => false, 'message' => 'This coupon has expired.']);
        exit;
    }
    if ($coupon['expiry_date'] && $coupon['expiry_date'] < $now) {
        echo json_encode(['success' => false, 'message' => 'This coupon has expired.']);
        exit;
    }
    if ($coupon['used_count'] >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'This coupon has reached its maximum usage limit.']);
        exit;
    }
    if ($subtotal < $coupon['min_spend']) {
        echo json_encode(['success' => false, 'message' => 'Minimum spend of $' . number_format($coupon['min_spend'], 2) . ' is required.']);
        exit;
    }
    
    if (!is_wholesaler_targeted($pdo, $user_id, $coupon['target_wholesalers'])) {
        echo json_encode(['success' => false, 'message' => 'This coupon is not applicable to your account.']);
        exit;
    }
    
    // Valid! Save to session and calculate discount
    $discount = 0;
    if ($coupon['type'] === 'fixed') {
        $discount = (float)$coupon['value'];
    } elseif ($coupon['type'] === 'percentage') {
        $discount = ($subtotal * (float)$coupon['value']) / 100;
        if ($coupon['max_discount'] && $discount > $coupon['max_discount']) {
            $discount = (float)$coupon['max_discount'];
        }
    }
    if ($discount > $subtotal) $discount = $subtotal;
    
    $_SESSION['applied_coupon'] = [
        'id' => $coupon['id'],
        'code' => $coupon['code'],
        'discount' => $discount,
        'type' => $coupon['type'],
        'value' => (float)$coupon['value'],
        'max_discount' => $coupon['max_discount']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Coupon code applied!',
        'code' => $coupon['code'],
        'discount' => $discount,
        'type' => $coupon['type'],
        'value' => (float)$coupon['value'],
        'max_discount' => $coupon['max_discount']
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'remove_coupon') {
    header('Content-Type: application/json');
    unset($_SESSION['applied_coupon']);
    echo json_encode(['success' => true, 'message' => 'Coupon removed.']);
    exit;
}

// Load applied coupon details if exists
$session_coupon = $_SESSION['applied_coupon'] ?? null;
$global_discount = calculate_global_discount_amount($pdo, $subtotal, $user_id);
?>

<!-- Checkout Step Indicators -->
<div style="display: flex; justify-content: center; gap: 2rem; margin-bottom: 3.5rem; flex-wrap: wrap;">
    <div style="display: flex; align-items: center; gap: 0.5rem; opacity: 0.5;">
        <span style="width: 32px; height: 32px; border-radius: 50%; background: var(--text-muted); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">1</span>
        <strong style="color: var(--text-muted);">Review Cart</strong>
    </div>
    <div style="width: 60px; height: 1px; background: var(--border-light); align-self: center;"></div>
    <div style="display: flex; align-items: center; gap: 0.5rem;">
        <span style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; box-shadow: 0 0 10px var(--primary-glow);">2</span>
        <strong style="color: var(--secondary);">Checkout Details</strong>
    </div>
    <div style="width: 60px; height: 1px; background: var(--border-light); align-self: center;"></div>
    <div style="display: flex; align-items: center; gap: 0.5rem; opacity: 0.5;">
        <span style="width: 32px; height: 32px; border-radius: 50%; background: var(--text-muted); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">3</span>
        <strong style="color: var(--text-muted);">Order Confirmation</strong>
    </div>
</div>

<div class="section-title">
    <i class="fas fa-file-signature"></i> Partner Order Checkout
</div>

<form method="POST" action="/bolakausa/place-order">
    <div style="display: flex; gap: 2rem; flex-wrap: wrap; align-items: start;">
        <!-- Left Side: Form Inputs -->
        <div style="flex: 1.2; min-width: 300px;">
            <div class="card">
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-truck-loading" style="color: var(--primary);"></i> Shipping Address
                </h3>
                
                <?php if ($addresses): ?>
                    <div class="form-group">
                        <label>Select Shipping Address</label>
                        <select name="address_id" id="checkout-address-select" required style="border-radius: 8px;">
                            <option value="">-- Choose a shipping address --</option>
                            <?php foreach ($addresses as $addr): ?>
                                <option value="<?php echo $addr['id']; ?>" data-location="<?php echo $addr['location_id'] ?? ''; ?>" data-tax="<?php echo $addr['tax_percent'] ?? 0; ?>" data-base="<?php echo $addr['base_delivery_charge'] ?? 0; ?>" data-weight-charge="<?php echo $addr['per_unit_weight_charge'] ?? 0; ?>" <?php echo ($addr['is_default']) ? 'selected' : ''; ?>>
                                    <?php echo e($addr['address_line']) . ', ' . e($addr['city']); ?>
                                    <?php if (!empty($addr['location_name'])): ?>
                                        — <?php echo e($addr['location_name']); ?> (Tax: <?php echo $addr['tax_percent']; ?>%)
                                    <?php else: ?>
                                        — Missing US Delivery State
                                    <?php endif; ?>
                                    <?php echo $addr['is_default'] ? ' [Default]' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="button" id="toggle-new-address" class="btn btn-outline" style="border-radius: 10px; font-size: 0.85rem; padding: 0.5rem 1rem; margin-bottom: 1.5rem; width: 100%;" onclick="document.getElementById('checkout-new-address').style.display = document.getElementById('checkout-new-address').style.display === 'none' ? 'block' : 'none'; this.textContent = this.textContent === 'Add New Address' ? 'Cancel' : 'Add New Address';">Add New Address</button>

                    <div id="checkout-new-address" style="display: none; margin-bottom: 1.5rem;">
                <?php else: ?>
                    <div style="background: rgba(244,63,94,0.06); color: var(--rose); padding: 1.25rem; border-radius: 12px; border: 1px solid rgba(244,63,94,0.15); margin-bottom: 0.5rem; font-size: 0.85rem;">
                        <i class="fas fa-exclamation-circle"></i> No shipping addresses yet. Add one below to proceed.
                    </div>
                    <div id="checkout-new-address" style="margin-bottom: 1.5rem;">
                <?php endif; ?>

                <?php if ($address_error): ?>
                    <div style="background: rgba(244,63,94,0.06); color: var(--rose); padding: 0.75rem; border-radius: 8px; border: 1px solid rgba(244,63,94,0.15); margin-bottom: 0.75rem; font-size: 0.85rem;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo e($address_error); ?>
                    </div>
                <?php elseif ($address_success): ?>
                    <div style="background: rgba(16,185,129,0.06); color: var(--primary); padding: 0.75rem; border-radius: 8px; border: 1px solid rgba(16,185,129,0.15); margin-bottom: 0.75rem; font-size: 0.85rem;">
                        <i class="fas fa-check-circle"></i> <?php echo e($address_success); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$address_success): ?>
                    <div style="border: 1px dashed var(--border-light); border-radius: 12px; padding: 1.25rem;">
                        <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.95rem; font-weight: 700; color: var(--secondary); margin-bottom: 0.75rem;"><i class="fas fa-plus-circle" style="color: var(--accent);"></i> New Address</h4>
                        <input type="text" name="new_address_line" placeholder="Street address, P.O. box" style="border-radius: 8px; margin-bottom: 0.65rem;">
                        <input type="text" name="new_city" placeholder="City" style="border-radius: 8px; margin-bottom: 0.65rem;">
                        <select name="new_location_id" style="border-radius: 8px; margin-bottom: 0.65rem;">
                            <option value="">-- US Delivery State (required) --</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo $loc['id']; ?>"><?php echo e($loc['name']); ?> (Tax: <?php echo $loc['tax_percent']; ?>%)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="save-address-btn" class="btn btn-green" style="border-radius: 10px; padding: 0.55rem 1.25rem; font-size: 0.85rem;">Save Address</button>
                    </div>
                <?php endif; ?>
                </div>

                <?php if ($address_success): ?>
                    <div style="margin-top: 0.75rem; font-size: 0.85rem; color: var(--text-muted);">
                        <i class="fas fa-info-circle"></i> Select your new address above to calculate shipping & tax.
                    </div>
                <?php endif; ?>

                <input type="hidden" name="location_id" id="checkout-location-id" value="<?php echo $default_location_id ?? ''; ?>">
            </div>
        </div>

        <!-- Right Side: Payment + Coupon + Order Summary -->
        <div style="flex: 0.8; min-width: 280px; display: flex; flex-direction: column; gap: 2rem;">
            <div class="card" style="margin-bottom: 0;">
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-credit-card" style="color: var(--accent);"></i> Payment Method
                </h3>
                <div class="form-group">
                    <label>Select Payment Method</label>
                    <select name="payment_method" id="checkout-payment-select" required style="border-radius: 8px;">
                        <option value="">-- Choose payment method --</option>
                        <?php if (get_setting($pdo, 'payment_cod_enabled', '1') == '1'): ?>
                            <option value="COD">Cash on Delivery (COD)</option>
                        <?php endif; ?>
                        <?php if (get_setting($pdo, 'payment_bank_enabled', '1') == '1'): ?>
                            <option value="Bank Transfer">Corporate Bank Wire</option>
                        <?php endif; ?>
                        <?php if (get_setting($pdo, 'payment_paylater_enabled', '1') == '1'): ?>
                            <option value="Pay Later">Pay Later (Credit Terms)</option>
                        <?php endif; ?>
                        <?php if (get_setting($pdo, 'payment_stripe_enabled', '0') == '1'): ?>
                            <option value="Stripe">Stripe Card Processing</option>
                        <?php endif; ?>
                        <option value="Wallet" <?php echo ($wallet_balance <= 0) ? 'disabled' : ''; ?>><?php echo ($wallet_balance <= 0) ? 'Wallet (insufficient balance)' : 'Partner Digital Wallet ($' . number_format($wallet_balance, 2) . ')'; ?></option>
                    </select>
                    <div id="wallet-notice" style="font-size: 0.8rem; margin-top: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 6px; display: none;"></div>
                </div>

                <!-- Payment Amount input (shown for Stripe / Bank Wire) -->
                <div id="payment-amount-container" style="display:none; margin-top: 1.5rem; padding: 1.5rem; background: rgba(15, 23, 42, 0.02); border-radius: 12px; border: 1px solid var(--border-light);">
                    <div class="form-group" style="margin: 0;">
                        <label id="payment-amount-label" style="font-weight: 800; font-size: 0.85rem; color: var(--secondary); margin-bottom: 0.35rem; display: block;">Amount to Pay ($) *</label>
                        <input type="number" step="0.01" name="payment_amount" id="checkout-payment-amount" placeholder="0.00" style="border-radius: 8px; font-weight: 700;">
                        <small id="payment-amount-hint" style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.4rem; line-height: 1.4;"></small>
                    </div>
                </div>

                <!-- Bank details nested section -->
                <div id="bank-details" style="display:none; margin-top: 1.5rem; padding: 1.5rem; background: rgba(99, 102, 241, 0.04); border-radius: 12px; border: 1px solid rgba(99, 102, 241, 0.15);">
                    <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--accent);">Bank Transfer Routing Instruction</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.25rem;">
                        Submit payment to:<br>
                        Bank: <strong><?php echo e(get_setting($pdo, 'company_name', 'Bolakausa')); ?> Corporate Bank</strong><br>
                        Account #: <strong><?php echo e(get_setting($pdo, 'bank_account_number', '1234567890')); ?></strong> | Routing #: <strong><?php echo e(get_setting($pdo, 'bank_routing_number', '987654321')); ?></strong>
                    </p>
                    
                    <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.75rem;">Originating Bank Name *</label>
                            <input type="text" name="bank_name" id="bank_name" placeholder="e.g. Chase Bank">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.75rem;">Transaction / Reference ID *</label>
                            <input type="text" name="transaction_id" id="transaction_id" placeholder="e.g. TXN-10928374">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.75rem;">Date of Wire Transfer *</label>
                            <input type="date" name="transfer_date" id="transfer_date">
                        </div>
                    </div>
                </div>

                <!-- Stripe credit card details section -->
                <div id="stripe-details" style="display:none; margin-top: 1.5rem; padding: 1.5rem; background: rgba(16, 185, 129, 0.04); border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.15);">
                    <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--primary);">Stripe Credit Card Processing</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.25rem;">
                        Secure payments processed by Stripe. Use standard test cards (e.g., <strong>4242 4242 4242 4242</strong>) for simulated transactions.
                    </p>
                    
                    <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.75rem;">Cardholder Name *</label>
                            <input type="text" name="stripe_cardholder" id="stripe_cardholder" placeholder="e.g. John Doe">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.75rem;">Card Number *</label>
                            <input type="text" name="stripe_card_number" id="stripe_card_number" placeholder="4242 4242 4242 4242" maxlength="19">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group" style="margin: 0;">
                                <label style="font-size: 0.75rem;">Expiration Date *</label>
                                <input type="text" name="stripe_expiry" id="stripe_expiry" placeholder="MM/YY" maxlength="5">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label style="font-size: 0.75rem;">CVC *</label>
                                <input type="text" name="stripe_cvc" id="stripe_cvc" placeholder="123" maxlength="4">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Coupon Input -->
                <div style="margin-top: 1.5rem; margin-bottom: 1.5rem; padding: 1rem; background: rgba(15,23,42,0.02); border: 1px solid var(--border-light); border-radius: 12px;">
                    <label style="font-weight: 800; font-size: 0.8rem; margin-bottom: 0.35rem; color: var(--secondary); display: block;">Have a Promo Coupon?</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" id="coupon-code-input" placeholder="e.g. WELCOME10" style="padding: 0.5rem; font-size: 0.85rem; border-radius: 6px; border: 1px solid #cbd5e1; flex: 1; text-transform: uppercase;">
                        <button type="button" id="apply-coupon-btn" class="btn btn-blue" style="padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 6px; height: 38px;">Apply</button>
                    </div>
                    <div id="coupon-message" style="font-size: 0.75rem; margin-top: 0.35rem; font-weight: 700; display: none;"></div>
                    <div id="applied-coupon-container" style="display: <?php echo $session_coupon ? 'flex' : 'none'; ?>; align-items: center; justify-content: space-between; margin-top: 0.5rem; padding: 0.4rem 0.75rem; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.15); border-radius: 6px;">
                        <span style="font-size: 0.775rem; color: #166534; font-weight: 800;"><i class="fas fa-ticket-alt"></i> Applied: <span id="applied-coupon-code"><?php echo $session_coupon ? e($session_coupon['code']) : ''; ?></span></span>
                        <button type="button" id="remove-coupon-btn" style="background:none; border:none; color:var(--rose); cursor:pointer; font-size:0.8rem; padding:0 0.2rem;"><i class="fas fa-times-circle"></i> Remove</button>
                    </div>
                </div>

            </div>

            <!-- Order Summary Card -->
            <div class="card" style="margin-bottom: 0;">
                <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.95rem; font-weight: 800; margin-bottom: 1rem; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.05em;">Order Summary</h4>
                <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-muted);">
                    <span>Items Subtotal:</span>
                    <strong id="subtotal-display" style="color: var(--secondary);">$<?php echo number_format($subtotal, 2); ?></strong>
                </div>
                <div id="automatic-discount-row" style="display: <?php echo $global_discount > 0 ? 'flex' : 'none'; ?>; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.5rem; color: #166534; font-weight: 700;">
                    <span>Automatic Discount:</span>
                    <strong id="automatic-discount-display">-$<?php echo number_format($global_discount, 2); ?></strong>
                </div>
                <div id="coupon-discount-row" style="display: <?php echo $session_coupon ? 'flex' : 'none'; ?>; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.5rem; color: #166534; font-weight: 700;">
                    <span>Coupon Discount:</span>
                    <strong id="discount-display">-$<?php echo $session_coupon ? number_format($session_coupon['discount'], 2) : '0.00'; ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.75rem; color: var(--text-muted);">
                    <span>Logistics Shipping Charge:</span>
                    <strong id="shipping-display" style="color: var(--secondary);">$0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1rem; margin-bottom: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-light); color: var(--secondary);">
                    <strong>Total</strong>
                    <strong id="total-display" style="color: var(--secondary); font-family: 'Plus Jakarta Sans', sans-serif;">$<?php echo number_format($subtotal - $global_discount, 2); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.75rem; color: var(--text-muted);">
                    <span>Estimated State Tax:</span>
                    <strong id="tax-display" style="color: var(--secondary);">$0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1.1rem; padding-top: 0.75rem; border-top: 1px solid var(--border-light); color: var(--secondary);">
                    <strong>To Pay</strong>
                    <strong id="grand-total-display" style="color: var(--primary); font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800;">$<?php echo number_format($subtotal - $global_discount, 2); ?></strong>
                </div>
                <button type="submit" class="btn btn-green" style="width: 100%; justify-content: center; padding: 1.15rem; font-size: 1.05rem; border-radius: 12px; box-shadow: 0 10px 20px -5px var(--primary-glow); margin-top: 1.5rem;">
                    <i class="fas fa-check-circle"></i> Place Wholesale Order
                </button>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const paymentSelect = document.getElementById('checkout-payment-select');
    const bankDetails = document.getElementById('bank-details');
    const bankInputs = bankDetails ? bankDetails.querySelectorAll('input') : [];
    const stripeDetails = document.getElementById('stripe-details');
    const stripeInputs = stripeDetails ? stripeDetails.querySelectorAll('input') : [];
    
    if (paymentSelect) {
        paymentSelect.addEventListener('change', (e) => {
            if (e.target.value === 'Bank Transfer') {
                if (bankDetails) bankDetails.style.display = 'block';
                bankInputs.forEach(input => input.setAttribute('required', 'required'));
                if (stripeDetails) stripeDetails.style.display = 'none';
                stripeInputs.forEach(input => input.removeAttribute('required'));
            } else if (e.target.value === 'Stripe') {
                if (bankDetails) bankDetails.style.display = 'none';
                bankInputs.forEach(input => input.removeAttribute('required'));
                if (stripeDetails) stripeDetails.style.display = 'block';
                stripeInputs.forEach(input => input.setAttribute('required', 'required'));
            } else {
                if (bankDetails) bankDetails.style.display = 'none';
                bankInputs.forEach(input => input.removeAttribute('required'));
                if (stripeDetails) stripeDetails.style.display = 'none';
                stripeInputs.forEach(input => input.removeAttribute('required'));
            }
            updateCalculations();
        });
    }

    // B2B Real-time calculations
    const addressSelect = document.getElementById('checkout-address-select');
    const locationIdHidden = document.getElementById('checkout-location-id');
    const shippingDisplay = document.getElementById('shipping-display');
    const taxDisplay = document.getElementById('tax-display');
    const totalDisplay = document.getElementById('total-display');
    const grandTotalDisplay = document.getElementById('grand-total-display');

    const walletBalance = <?php echo $wallet_balance; ?>;
    const subtotal = <?php echo $subtotal; ?>;
    const totalWeight = <?php echo $total_weight; ?>;
    const taxOnShipping = <?php echo get_setting($pdo, 'tax_on_shipping', '0') ? 'true' : 'false'; ?>;
    const globalDiscount = <?php echo $global_discount; ?>;

    let couponType = '<?php echo $session_coupon ? $session_coupon['type'] : 'percentage'; ?>';
    let couponValue = <?php echo $session_coupon ? (float)$session_coupon['value'] : 0; ?>;
    let couponMax = <?php echo ($session_coupon && $session_coupon['max_discount']) ? (float)$session_coupon['max_discount'] : 'null'; ?>;

    function updateCalculations() {
        const selectedOption = addressSelect.options[addressSelect.selectedIndex];
        
        // Update hidden location_id
        if (locationIdHidden) {
            locationIdHidden.value = selectedOption ? (selectedOption.getAttribute('data-location') || '') : '';
        }

        // Calculate discount
        let discount = 0;
        if (couponValue > 0) {
            if (couponType === 'fixed') {
                discount = couponValue;
            } else if (couponType === 'percentage') {
                discount = (subtotal * couponValue) / 100;
                if (couponMax && discount > couponMax) discount = couponMax;
            }
        }
        if (discount > (subtotal - globalDiscount)) discount = subtotal - globalDiscount;
        
        // Update discount UI
        const discountRow = document.getElementById('coupon-discount-row');
        const discountValDisplay = document.getElementById('discount-display');
        if (discount > 0) {
            discountRow.style.display = 'flex';
            discountValDisplay.innerText = '-$' + discount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        } else {
            discountRow.style.display = 'none';
        }
        
        const discountedSubtotal = subtotal - globalDiscount - discount;

        if (!selectedOption || !selectedOption.value) {
            shippingDisplay.innerText = '$0.00';
            taxDisplay.innerText = '$0.00';
            totalDisplay.innerText = '$' + discountedSubtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            grandTotalDisplay.innerText = '$' + discountedSubtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            return;
        }
        
        const taxPercent = parseFloat(selectedOption.getAttribute('data-tax') || 0);
        const baseCharge = parseFloat(selectedOption.getAttribute('data-base') || 0);
        const weightCharge = parseFloat(selectedOption.getAttribute('data-weight-charge') || 0);
        
        const shipping = baseCharge + (totalWeight * weightCharge);
        const taxable = discountedSubtotal + (taxOnShipping ? shipping : 0);
        const tax = (taxable * taxPercent) / 100;
        const total = discountedSubtotal + shipping;
        const grandTotal = total + tax;
        
        shippingDisplay.innerText = '$' + shipping.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        taxDisplay.innerText = '$' + tax.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        totalDisplay.innerText = '$' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        grandTotalDisplay.innerText = '$' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        // Wallet / Payment logic
        const walletNotice = document.getElementById('wallet-notice');
        const paySelect = document.getElementById('checkout-payment-select');
        if (!walletNotice || !paySelect) return;

        const options = paySelect.querySelectorAll('option');
        const walletOpt = paySelect.querySelector('option[value="Wallet"]');

        if (grandTotal > 0 && walletBalance >= grandTotal) {
            // Wallet fully covers — do NOT disable other options, allow wholesaler flexibility
            options.forEach(o => {
                if (o.value !== '') o.disabled = false;
            });
            walletNotice.style.display = 'block';
            walletNotice.style.background = 'rgba(16,185,129,0.08)';
            walletNotice.style.color = '#166534';
            walletNotice.style.border = '1px solid rgba(16,185,129,0.15)';
            walletNotice.innerHTML = '<i class="fas fa-check-circle"></i> Your wallet balance of <strong>$' + walletBalance.toLocaleString('en-US', {minimumFractionDigits: 2}) + '</strong> fully covers this order.';
        } else if (grandTotal > 0 && walletBalance > 0 && walletBalance < grandTotal) {
            // Wallet partial — enable all, show shortfall
            options.forEach(o => {
                if (o.value !== 'Wallet' && o.value !== '') o.disabled = false;
            });
            if (walletOpt) {
                const remaining = grandTotal - walletBalance;
                walletOpt.innerText = 'Partner Digital Wallet ($' + walletBalance.toLocaleString('en-US', {minimumFractionDigits: 2}) + ') — need $' + remaining.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' more';
            }
            walletNotice.style.display = 'block';
            walletNotice.style.background = 'rgba(245,158,11,0.08)';
            walletNotice.style.color = '#92400e';
            walletNotice.style.border = '1px solid rgba(245,158,11,0.15)';
            walletNotice.innerHTML = '<i class="fas fa-info-circle"></i> Wallet balance: <strong>$' + walletBalance.toLocaleString('en-US', {minimumFractionDigits: 2}) + '</strong> — select another method or combine with Wallet.';
        } else {
            // No wallet or no total — restore defaults
            options.forEach(o => {
                if (o.value !== 'Wallet' && o.value !== '') o.disabled = false;
            });
            if (walletOpt) {
                walletOpt.disabled = walletBalance <= 0;
                walletOpt.innerText = walletBalance <= 0 ? 'Wallet (insufficient balance)' : 'Partner Digital Wallet ($' + walletBalance.toLocaleString('en-US', {minimumFractionDigits: 2}) + ')';
            }
            walletNotice.style.display = 'none';
        }

        const shortfall = Math.max(0, grandTotal - walletBalance);
        const paymentAmountContainer = document.getElementById('payment-amount-container');
        const paymentAmountInput = document.getElementById('checkout-payment-amount');
        const paymentAmountHint = document.getElementById('payment-amount-hint');
        
        if (paymentAmountContainer && paymentAmountInput && paymentAmountHint) {
            const payVal = paySelect.value;
            if (payVal === 'Stripe' || payVal === 'Bank Transfer') {
                paymentAmountContainer.style.display = 'block';
                paymentAmountInput.setAttribute('required', 'required');
                paymentAmountInput.setAttribute('min', shortfall.toFixed(2));
                
                const currentVal = parseFloat(paymentAmountInput.value) || 0;
                if (currentVal < shortfall) {
                    paymentAmountInput.value = shortfall.toFixed(2);
                }
                
                paymentAmountHint.innerHTML = '<i class="fas fa-info-circle"></i> Minimum additional payment required: <strong>$' + shortfall.toFixed(2) + '</strong>. Any overpayment will be credited to your wallet balance.';
            } else {
                paymentAmountContainer.style.display = 'none';
                paymentAmountInput.removeAttribute('required');
            }
        }
    }

    if (addressSelect) {
        addressSelect.addEventListener('change', updateCalculations);
        updateCalculations();
    }

    // AJAX Coupon code handlers
    const applyCouponBtn = document.getElementById('apply-coupon-btn');
    const removeCouponBtn = document.getElementById('remove-coupon-btn');
    const couponInput = document.getElementById('coupon-code-input');
    const couponMsg = document.getElementById('coupon-message');
    const appliedContainer = document.getElementById('applied-coupon-container');
    const appliedCodeSpan = document.getElementById('applied-coupon-code');

    if (applyCouponBtn) {
        applyCouponBtn.addEventListener('click', () => {
            const code = couponInput.value.trim();
            if (!code) {
                couponMsg.innerText = 'Please enter a coupon code.';
                couponMsg.style.color = 'var(--rose)';
                couponMsg.style.display = 'block';
                return;
            }
            
            const formData = new FormData();
            formData.append('code', code);
            
            fetch('/bolakausa/checkout?action=apply_coupon', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                couponMsg.style.display = 'block';
                if (data.success) {
                    couponMsg.innerText = data.message;
                    couponMsg.style.color = '#166534';
                    
                    couponType = data.type;
                    couponValue = data.value;
                    couponMax = data.max_discount;
                    
                    appliedCodeSpan.innerText = data.code;
                    appliedContainer.style.display = 'flex';
                    couponInput.value = '';
                    
                    updateCalculations();
                } else {
                    couponMsg.innerText = data.message;
                    couponMsg.style.color = 'var(--rose)';
                }
            })
            .catch(err => {
                couponMsg.innerText = 'Failed to apply coupon.';
                couponMsg.style.color = 'var(--rose)';
                couponMsg.style.display = 'block';
            });
        });
    }

    if (removeCouponBtn) {
        removeCouponBtn.addEventListener('click', () => {
            fetch('/bolakausa/checkout?action=remove_coupon', {
                method: 'POST'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    couponType = 'percentage';
                    couponValue = 0;
                    couponMax = null;
                    
                    appliedContainer.style.display = 'none';
                    couponMsg.style.display = 'none';
                    
                    updateCalculations();
                }
            });
        });
    }

    // Save new address inline via AJAX
    const saveAddrBtn = document.getElementById('save-address-btn');
    if (saveAddrBtn) {
        saveAddrBtn.addEventListener('click', function() {
            const wrapper = this.closest('#checkout-new-address') || this.closest('div[id]');
            const line = wrapper.querySelector('[name="new_address_line"]').value.trim();
            const city = wrapper.querySelector('[name="new_city"]').value.trim();
            const locId = wrapper.querySelector('[name="new_location_id"]').value;

            if (!line || !city) {
                alert('Address line and city are required.');
                return;
            }
            if (!locId) {
                alert('Please select a US Delivery State.');
                return;
            }

            const fd = new FormData();
            fd.append('add_checkout_address', '1');
            fd.append('new_address_line', line);
            fd.append('new_city', city);
            fd.append('new_location_id', locId);

            fetch('<?php echo BASE_URL; ?>ajax_add_address.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        location.reload();
                    } else {
                        alert(d.message || 'Failed to save address.');
                    }
                })
                .catch(() => alert('Network error. Please try again.'));
        });
    }
});
</script>
