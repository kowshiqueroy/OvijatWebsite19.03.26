<?php
/**
 * Checkout Module
 */
restrict_to(['wholesale_user']);

if (empty($_SESSION['cart'])) {
    header('Location: /bolakausa/cart');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch User Addresses
$stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ?");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll();

// Fetch Wallet Balance
$stmt = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet_balance = (float)($stmt->fetch()['balance'] ?? 0);

// Fetch Shipping Locations
$locations = $pdo->query("SELECT * FROM locations")->fetchAll();

// Calculation Logic (Simplified for the View)
// Real calculation happens on POST submit
?>

<h2>Checkout</h2>

<form method="POST" action="/bolakausa/place-order">
    <div style="display: flex; gap: 40px;">
        <div style="flex: 1;">
            <h3>1. Shipping Address</h3>
            <?php if ($addresses): ?>
                <?php foreach ($addresses as $addr): ?>
                    <label style="display: block; border: 1px solid #ddd; padding: 10px; margin-bottom: 10px;">
                        <input type="radio" name="address_id" value="<?php echo $addr['id']; ?>" required>
                        <?php echo e($addr['address_line']); ?>, <?php echo e($addr['city']); ?>
                    </label>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: red;">No address found. Please <a href="/bolakausa/account/addresses">add an address</a> first.</p>
            <?php endif; ?>

            <h3>2. Delivery Location (for Shipping/Tax Calculation)</h3>
            <select name="location_id" required>
                <option value="">Select Location</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo $loc['id']; ?>"><?php echo e($loc['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1;">
            <h3 style="margin-bottom: 1.5rem; font-weight: 800;">3. Payment Selection</h3>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php if (get_setting($pdo, 'payment_cod_enabled', '1') == '1'): ?>
                    <label class="btn btn-blue" style="background: rgba(255,255,255,0.5); color: var(--secondary); justify-content: flex-start; cursor: pointer; border: 1px solid var(--glass-border);">
                        <input type="radio" name="payment_method" value="COD" required style="width: auto; margin-right: 10px;"> 
                        <i class="fas fa-hand-holding-usd"></i> Cash on Delivery
                    </label>
                <?php endif; ?>

                <?php if (get_setting($pdo, 'payment_bank_enabled', '1') == '1'): ?>
                    <label class="btn btn-blue" style="background: rgba(255,255,255,0.5); color: var(--secondary); justify-content: flex-start; cursor: pointer; border: 1px solid var(--glass-border);">
                        <input type="radio" name="payment_method" value="Bank Transfer" style="width: auto; margin-right: 10px;"> 
                        <i class="fas fa-university"></i> Bank Transfer
                    </label>
                <?php endif; ?>

                <?php if (get_setting($pdo, 'payment_paylater_enabled', '1') == '1'): ?>
                    <label class="btn btn-blue" style="background: rgba(255,255,255,0.5); color: var(--secondary); justify-content: flex-start; cursor: pointer; border: 1px solid var(--glass-border);">
                        <input type="radio" name="payment_method" value="Pay Later" style="width: auto; margin-right: 10px;"> 
                        <i class="fas fa-credit-card"></i> Pay Later (Credit Terms)
                    </label>
                <?php endif; ?>

                <?php if (get_setting($pdo, 'payment_stripe_enabled', '0') == '1'): ?>
                    <label class="btn btn-blue" style="background: rgba(255,255,255,0.5); color: var(--secondary); justify-content: flex-start; cursor: pointer; border: 1px solid var(--glass-border);">
                        <input type="radio" name="payment_method" value="Stripe" style="width: auto; margin-right: 10px;"> 
                        <i class="fab fa-stripe"></i> Credit/Debit Card (Stripe)
                    </label>
                <?php endif; ?>

                <label class="btn btn-blue" style="background: rgba(255,255,255,0.5); color: var(--secondary); justify-content: flex-start; cursor: pointer; border: 1px solid var(--glass-border); <?php echo ($wallet_balance <= 0) ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>">
                    <input type="radio" name="payment_method" value="Wallet" <?php echo ($wallet_balance <= 0) ? 'disabled' : ''; ?> style="width: auto; margin-right: 10px;"> 
                    <i class="fas fa-wallet"></i> Wallet Balance ($<?php echo number_format($wallet_balance, 2); ?>)
                </label>
            </div>

            <div id="bank-details" style="display:none; margin-top: 2rem; padding: 2rem; background: rgba(16, 185, 129, 0.05); border-radius: var(--radius-md); border: 1px solid rgba(16, 185, 129, 0.2);">
                <h4 style="margin-bottom: 1rem; color: var(--primary); font-weight: 800;">Bank Transfer Details</h4>
                <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                    Transfer to: <strong><?php echo e(get_setting($pdo, 'company_name', 'Bolakausa')); ?> Bank</strong><br>
                    Account: <strong>1234567890</strong><br>
                    Routing: <strong>987654321</strong>
                </p>
                <div class="form-group">
                    <label>Originating Bank Name *</label>
                    <input type="text" name="bank_name" id="bank_name" placeholder="e.g. Chase Bank">
                </div>
                <div class="form-group">
                    <label>Transaction Reference ID *</label>
                    <input type="text" name="transaction_id" id="transaction_id" placeholder="Reference ID">
                </div>
                <div class="form-group">
                    <label>Date of Transfer *</label>
                    <input type="date" name="transfer_date" id="transfer_date">
                </div>
            </div>

            <div style="margin-top: 3rem;">
                <button type="submit" class="btn btn-green" style="width: 100%; justify-content: center; padding: 1.25rem; font-size: 1.125rem;">Confirm & Place Order</button>
            </div>
        </div>
    </div>
</form>

<script>
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            const bankDetails = document.getElementById('bank-details');
            const bankInputs = bankDetails.querySelectorAll('input');
            
            if (e.target.value === 'Bank Transfer') {
                bankDetails.style.display = 'block';
                bankInputs.forEach(input => input.setAttribute('required', 'required'));
            } else {
                bankDetails.style.display = 'none';
                bankInputs.forEach(input => input.removeAttribute('required'));
            }
        });
    });
</script>
