<?php
if (!isset($_SESSION['user_id'])) {
    return;
}
$user_id = $_SESSION['user_id'];
global $pdo;

// Compute cart info for sidebar
$cart_count = !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$cart_total = 0;
if ($cart_count > 0) {
    // Group products by ID to minimize SQL calls
    $pids = [];
    foreach ($_SESSION['cart'] as $item_key => $qty) {
        $parts = explode('_', $item_key);
        $pid = (int)$parts[0];
        $pids[$pid] = true;
    }
    
    $ids = array_keys($pids);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id, base_price FROM products WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $products_price = [];
        foreach ($stmt->fetchAll() as $p) {
            $products_price[$p['id']] = (float)$p['base_price'];
        }
        
        // Calculate total including variant modifiers & tiers
        foreach ($_SESSION['cart'] as $item_key => $qty) {
            $parts = explode('_', $item_key);
            $pid = (int)$parts[0];
            $variant_id = (int)($parts[1] ?? 0);
            
            if (isset($products_price[$pid])) {
                $price = $products_price[$pid];
                
                // Apply tier pricing if applicable
                $t_stmt = $pdo->prepare("SELECT unit_price FROM product_price_tiers WHERE product_id = ? AND min_qty <= ? ORDER BY min_qty DESC LIMIT 1");
                $t_stmt->execute([$pid, $qty]);
                $tier = $t_stmt->fetch();
                
                $discount = get_product_discount($pdo, $pid, $user_id ?? 0);
                $base_unit_price = $tier ? (float)$tier['unit_price'] : $price;
                $price = calculate_discounted_price($base_unit_price, $discount);

                if ($variant_id > 0) {
                    $vstmt = $pdo->prepare("SELECT price_modifier FROM product_variants WHERE id = ? AND product_id = ? AND is_deleted = 0");
                    $vstmt->execute([$variant_id, $pid]);
                    $mod = $vstmt->fetchColumn();
                    if ($mod !== false) {
                        $price += (float)$mod;
                    }
                }
                $cart_total += $price * $qty;
            }
        }
    }
}

// Compute unread chats
$stmt_chat = $pdo->prepare("SELECT COUNT(*) FROM chats WHERE user_id = ? AND sender_role IN ('admin', 'manager') AND is_read = 0 AND is_deleted = 0");
$stmt_chat->execute([$user_id]);
$unread_chats = (int)$stmt_chat->fetchColumn();

// Compute outstanding orders/pay the rest count
$stmt_pay_rest = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND (payment_status = 'Unpaid' OR fulfillment_status = 'Pending Customer Approval') AND fulfillment_status NOT IN ('Cancelled', 'Rejected')");
$stmt_pay_rest->execute([$user_id]);
$pay_rest_count = (int)$stmt_pay_rest->fetchColumn();
?>
<div class="wholesale-sidebar">
  <?php
  $stmt_user = $pdo->prepare("SELECT email, role FROM users WHERE id = ?");
  $stmt_user->execute([$user_id]);
  $user_info = $stmt_user->fetch();
  $user_email = $user_info['email'] ?? '';
  $user_role_display = $user_info['role'] ?? 'wholesale_user';
  $base_path = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);
  if (!isset($company_logo)) {
      $company_logo_setting = get_setting($pdo, 'company_logo_url', '');
      if (empty($company_logo_setting)) {
          $company_logo = $base_path . 'public/images/logo/logoofbolakausa.png';
      } else {
          $company_logo = $base_path . $company_logo_setting;
      }
  }
  ?>
  <a href="/bolakausa/home" class="logo" style="margin-bottom: 1.5rem; text-decoration: none;">
      <img src="<?php echo $company_logo; ?>" alt="Bolakausa Logo" style="max-height: 42px; width: auto; object-fit: contain;">
      <span><span style="color: var(--primary);">Bolaka</span>USA.com</span>
  </a>
  
  <div style="background: rgba(15, 23, 42, 0.04); border: 1px solid rgba(15, 23, 42, 0.06); padding: 0.85rem 1rem; border-radius: 12px; margin-bottom: 2rem;">
      <div style="font-size: 0.8rem; font-weight: 700; color: var(--secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo e($user_email); ?>">
          <i class="far fa-envelope" style="color: var(--text-muted); margin-right: 5px;"></i><?php echo e($user_email); ?>
      </div>
      <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--primary-dark); margin-top: 0.25rem; display: flex; align-items: center; gap: 4px;">
          <i class="fas fa-user-shield" style="font-size: 0.65rem;"></i> Role: <?php echo e(str_replace('_', ' ', $user_role_display)); ?>
      </div>
  </div>
  
  <ul>
    <li><a href="/bolakausa/home"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
    <li><a href="/bolakausa/catalog"><i class="fas fa-store"></i> Catalog</a></li>
    <?php if ($cart_count > 0): ?>
    <li><a href="/bolakausa/cart" style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-shopping-cart"></i> Cart <span style="margin-left: auto; background: var(--rose); color: white; font-size: 0.7rem; font-weight: 800; padding: 1px 7px; border-radius: 10px;"><?php echo $cart_count; ?></span> <span style="font-size: 0.75rem; font-weight: 700; color: var(--primary);">$<?php echo number_format($cart_total, 2); ?></span></a></li>
    <?php endif; ?>
    <li><a href="/bolakausa/orders"><i class="fas fa-receipt"></i> Orders</a></li>
    <li><a href="/bolakausa/chats" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-comments"></i> Support Chat</span><?php if ($unread_chats > 0): ?><span style="background: var(--rose); color: white; font-size: 0.7rem; font-weight: 800; padding: 1px 7px; border-radius: 10px;"><?php echo $unread_chats; ?></span><?php endif; ?></a></li>
    <li><a href="/bolakausa/wallet"><i class="fas fa-wallet"></i> Wallet</a></li>
    <li><a href="/bolakausa/pay-later" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-file-invoice-dollar"></i> Pay the Rest</span><?php if ($pay_rest_count > 0): ?><span style="background: var(--rose); color: white; font-size: 0.7rem; font-weight: 800; padding: 1px 7px; border-radius: 10px;"><?php echo $pay_rest_count; ?></span><?php endif; ?></a></li>
    <li><a href="/bolakausa/account"><i class="fas fa-address-book"></i> Addresses & Profile</a></li>
    <li><a href="/bolakausa/logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
  </ul>
</div>
