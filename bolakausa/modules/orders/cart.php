<?php
/**
 * Shopping Cart Management
 */
restrict_to(['wholesale_user', 'admin', 'manager']);

// Initialize Cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Handle Actions
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)$_POST['product_id'];
    $qty = (int)$_POST['qty'];
    
    // Check if product exists and get min qty
    $stmt = $pdo->prepare("SELECT id, min_order_qty FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$pid]);
    $product = $stmt->fetch();

    if ($product) {
        if ($qty < $product['min_order_qty']) $qty = $product['min_order_qty'];
        
        if (isset($_SESSION['cart'][$pid])) {
            $_SESSION['cart'][$pid] += $qty;
        } else {
            $_SESSION['cart'][$pid] = $qty;
        }
        header('Location: /bolakausa/cart');
        exit;
    }
}

if ($action === 'remove') {
    $pid = (int)$_GET['id'];
    unset($_SESSION['cart'][$pid]);
    header('Location: /bolakausa/cart');
    exit;
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['qtys'] as $pid => $qty) {
        $qty = (int)$qty;
        if ($qty <= 0) {
            unset($_SESSION['cart'][$pid]);
        } else {
            $_SESSION['cart'][$pid] = $qty;
        }
    }
    header('Location: /bolakausa/cart');
    exit;
}

// Fetch Cart Item Details with Tiered Pricing Logic
$cart_items = [];
$total_subtotal = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $pid => $qty) {
        // Get product and its tiers
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $product = $stmt->fetch();

        if ($product) {
            // Find applicable tier price
            $stmt = $pdo->prepare("SELECT unit_price FROM product_price_tiers WHERE product_id = ? AND min_qty <= ? ORDER BY min_qty DESC LIMIT 1");
            $stmt->execute([$pid, $qty]);
            $tier = $stmt->fetch();

            $price = $tier ? $tier['unit_price'] : $product['base_price'];
            $subtotal = $price * $qty;
            $total_subtotal += $subtotal;

            $cart_items[] = [
                'id' => $pid,
                'name' => $product['name'],
                'qty' => $qty,
                'unit_price' => $price,
                'subtotal' => $subtotal,
                'is_bulk' => (bool)$tier
            ];
        }
    }
}

// Get MOV (Minimum Order Value)
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'min_order_value'");
$stmt->execute();
$mov = (float)($stmt->fetch()['setting_value'] ?? 0);

?>

<div class="section-title">
    <i class="fas fa-shopping-cart" style="color: var(--primary);"></i>
    Shopping Cart
</div>

<?php if (empty($cart_items)): ?>
    <div style="text-align: center; padding: 4rem 2rem; background: var(--glass-bg); border-radius: var(--radius-lg); border: 1px solid var(--glass-border);">
        <i class="fas fa-shopping-basket" style="font-size: 4rem; color: var(--text-muted); opacity: 0.3; margin-bottom: 1rem;"></i>
        <h3 style="color: var(--secondary); margin-bottom: 0.5rem;">Your cart is empty</h3>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Looks like you haven't added any wholesale items yet.</p>
        <a href="/bolakausa/home" class="btn btn-green"><i class="fas fa-store"></i> Browse Catalog</a>
    </div>
<?php else: ?>
    <form method="POST" action="/bolakausa/cart">
        <input type="hidden" name="action" value="update">
        <div class="table-wrap" style="margin-bottom: 2rem;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40%;">Product</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item): ?>
                    <tr>
                        <td>
                            <strong style="color: var(--secondary); font-size: 1.1rem;"><?php echo e($item['name']); ?></strong>
                            <?php if ($item['is_bulk']): ?><br><small style="background: rgba(16,185,129,0.1); color: var(--primary); padding: 2px 8px; border-radius: 4px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase;">Bulk Tier Applied</small><?php endif; ?>
                        </td>
                        <td>
                            <input type="number" name="qtys[<?php echo $item['id']; ?>]" value="<?php echo $item['qty']; ?>" style="width: 80px; padding: 0.5rem; text-align: center;">
                        </td>
                        <td style="color: var(--text-muted);">$<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td><strong style="color: var(--primary); font-size: 1.1rem;">$<?php echo number_format($item['subtotal'], 2); ?></strong></td>
                        <td style="text-align: right;">
                            <a href="/bolakausa/cart?action=remove&id=<?php echo $item['id']; ?>" class="btn btn-red" style="padding: 0.5rem 1rem; font-size: 0.8rem;"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: rgba(15,23,42,0.02);">
                        <td colspan="3" style="text-align: right; font-weight: 800; color: var(--secondary);">Total Subtotal:</td>
                        <td colspan="2"><strong style="color: var(--primary); font-size: 1.5rem;">$<?php echo number_format($total_subtotal, 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; background: var(--glass-bg); border-radius: var(--radius-lg); border: 1px solid var(--glass-border);">
            <button type="submit" class="btn btn-blue"><i class="fas fa-sync-alt"></i> Update Cart</button>
            
            <?php if ($total_subtotal >= $mov): ?>
                <a href="/bolakausa/checkout" class="btn btn-green" style="padding: 1.25rem 2rem; font-size: 1.1rem;"><i class="fas fa-check"></i> Proceed to Checkout</a>
            <?php else: ?>
                <div style="color: var(--accent); font-weight: 700; background: rgba(244,63,94,0.1); padding: 1rem; border-radius: 8px;">
                    <i class="fas fa-exclamation-triangle"></i> Minimum Order Value is $<?php echo number_format($mov, 2); ?>. Add $<?php echo number_format($mov - $total_subtotal, 2); ?> more to checkout.
                </div>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>
