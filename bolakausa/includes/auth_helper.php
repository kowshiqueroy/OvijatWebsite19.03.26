<?php
/**
 * Authentication Helpers
 */

/**
 * Check if a user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user role
 */
function get_user_role() {
    return $_SESSION['user_role'] ?? 'guest';
}

/**
 * Restrict access to specific roles
 * @param array $allowed_roles
 */
function restrict_to($allowed_roles) {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'login');
        exit;
    }

    $role = get_user_role();
    if (!in_array($role, $allowed_roles)) {
        require_once __DIR__ . '/access_denied.php';
        render_access_denied(
            'Access Denied',
            'You do not have permission to view this page. Please contact your administrator if you believe this is an error.'
        );
    }

    // Fetch live status from database to prevent bypassed suspensions/deletions
    global $pdo;
    $stmt = $pdo->prepare("SELECT status, is_deleted FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $live_user = $stmt->fetch();
    
    if (!$live_user || $live_user['is_deleted']) {
        session_destroy();
        header('Location: ' . BASE_URL . 'login?error=account_deleted');
        exit;
    }
    
    if ($live_user['status'] === 'suspended') {
        session_destroy();
        header('Location: ' . BASE_URL . 'login?error=suspended');
        exit;
    }

    // Also check if user is active (except for admin/manager/editor)
    if (in_array($role, ['wholesale_user', 'executive']) && $live_user['status'] !== 'active') {
        require_once __DIR__ . '/access_denied.php';
        render_account_pending();
    }
}

/**
 * Log a system action
 */
function log_action($pdo, $user_id, $action_type, $old_val = null, $new_val = null) {
    $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action_type, old_value, new_value) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action_type, $old_val, $new_val]);
}

/**
 * Get a global setting value
 */
function get_setting($pdo, $key, $default = '') {
    static $settings_cache = null;
    
    if ($settings_cache === null) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings_cache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    return $settings_cache[$key] ?? $default;
}

/**
 * Check if a wholesaler user matches the promotion/coupon target criteria
 * @param PDO $pdo
 * @param int $user_id
 * @param string $target_wholesalers ('all', 'top_3', 'top_5', 'top_10', or comma-separated user_ids)
 * @return bool
 */
/**
 * Generate CSRF token
 */
function csrf_token() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Validate CSRF token
 */
function verify_csrf_token($token) {
    return !empty($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * Render CSRF hidden input
 */
function csrf_field() {
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

function is_wholesaler_targeted($pdo, $user_id, $target_wholesalers) {
    if (!$user_id) return false;
    
    // Fetch user role to ensure they are a customer (wholesale_user or executive)
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$user_id]);
    $role = $stmt->fetchColumn();
    if (!in_array($role, ['wholesale_user', 'executive'])) {
        return false;
    }
    
    if (empty($target_wholesalers) || $target_wholesalers === 'all') {
        return true;
    }
    
    // Check for roles (e.g., 'executive', 'wholesale_user')
    $targets = array_map('trim', explode(',', $target_wholesalers));
    if (in_array($role, $targets)) {
        return true;
    }
    
    // Check for top buyers
    if (strpos($target_wholesalers, 'top_') === 0) {
        $limit = (int)substr($target_wholesalers, 4);
        if ($limit <= 0) return false;
        
        // Find top buyer user_ids based on sum of completed/active orders
        $stmt_top = $pdo->prepare("
            SELECT o.user_id 
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.status NOT IN ('Cancelled', 'Rejected') AND u.is_deleted = 0
            GROUP BY o.user_id 
            ORDER BY SUM(o.total_amount) DESC 
            LIMIT ?
        ");
        $stmt_top->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt_top->execute();
        $top_user_ids = $stmt_top->fetchAll(PDO::FETCH_COLUMN);
        
        return in_array((int)$user_id, array_map('intval', $top_user_ids));
    }
    
    // Check for specific user IDs
    $allowed_ids = [];
    foreach ($targets as $t) {
        if (is_numeric($t)) {
            $allowed_ids[] = (int)$t;
        }
    }
    return in_array((int)$user_id, $allowed_ids);
}

/**
 * Get active automatic discount for a product
 */
function get_product_discount($pdo, $product_id, $user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    $now = date('Y-m-d H:i:s');
    
    // 1. Product specific discounts
    $stmt = $pdo->prepare("
        SELECT * FROM discounts 
        WHERE is_active = 1 AND is_deleted = 0 
          AND discount_type = 'product_specific' 
          AND product_id = ?
          AND (start_date IS NULL OR start_date <= ?)
          AND (end_date IS NULL OR end_date >= ?)
    ");
    $stmt->execute([$product_id, $now, $now]);
    $discounts = $stmt->fetchAll();
    
    foreach ($discounts as $d) {
        if ($d['target_wholesalers'] === 'all' || ($user_id && is_wholesaler_targeted($pdo, $user_id, $d['target_wholesalers']))) {
            return $d;
        }
    }
    
    return null;
}

/**
 * Calculate discounted price from base price and discount rule
 */
function calculate_discounted_price($price, $discount) {
    if (!$discount) return $price;
    if ($discount['percent'] > 0) {
        return $price * (1 - ((float)$discount['percent'] / 100));
    } elseif ($discount['amount'] > 0) {
        return max(0, $price - (float)$discount['amount']);
    }
    return $price;
}

/**
 * Get active automatic global discounts
 */
function get_active_global_discounts($pdo, $user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        SELECT * FROM discounts 
        WHERE is_active = 1 AND is_deleted = 0 
          AND discount_type = 'global'
          AND (start_date IS NULL OR start_date <= ?)
          AND (end_date IS NULL OR end_date >= ?)
    ");
    $stmt->execute([$now, $now]);
    $discounts = $stmt->fetchAll();
    
    $applicable = [];
    foreach ($discounts as $d) {
        if ($d['target_wholesalers'] === 'all' || ($user_id && is_wholesaler_targeted($pdo, $user_id, $d['target_wholesalers']))) {
            $applicable[] = $d;
        }
    }
    return $applicable;
}

/**
 * Calculate total global discount amount based on subtotal
 */
function calculate_global_discount_amount($pdo, $subtotal, $user_id = null) {
    $discounts = get_active_global_discounts($pdo, $user_id);
    $discount_amount = 0;
    
    foreach ($discounts as $d) {
        if ($d['percent'] > 0) {
            $discount_amount += ($subtotal * ((float)$d['percent'] / 100));
        } elseif ($d['amount'] > 0) {
            $discount_amount += (float)$d['amount'];
        }
    }
    
    return min($subtotal, $discount_amount);
}

/**
 * Deduct product and variant stock when an order is verified/paid
 */
function deduct_order_stock($pdo, $order_id) {
    // Check if stock is already deducted
    $stmt = $pdo->prepare("SELECT stock_deducted FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $deducted = $stmt->fetchColumn();
    if ($deducted == 1) {
        return; // Already deducted
    }

    // 1. Fetch order items
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? AND is_deleted = 0");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    foreach ($items as $item) {
        // 2. Deduct from main product stock
        $uStmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
        $uStmt->execute([$item['qty'], $item['product_id']]);

        // 3. Deduct from variant stock if applicable
        if ($item['variant_id']) {
            $vStmt = $pdo->prepare("UPDATE product_variants SET stock_qty = stock_qty - ? WHERE id = ?");
            $vStmt->execute([$item['qty'], $item['variant_id']]);
        }
    }

    // Mark order stock as deducted
    $upStmt = $pdo->prepare("UPDATE orders SET stock_deducted = 1 WHERE id = ?");
    $upStmt->execute([$order_id]);
}

/**
 * Restore product and variant stock when an order is cancelled or reverted
 */
function restore_order_stock($pdo, $order_id) {
    // Check if stock was actually deducted
    $stmt = $pdo->prepare("SELECT stock_deducted FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $deducted = $stmt->fetchColumn();
    if ($deducted == 0) {
        return; // Already restored or never deducted
    }

    // 1. Fetch order items
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? AND is_deleted = 0");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    foreach ($items as $item) {
        // 2. Restore main product stock
        $uStmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
        $uStmt->execute([$item['qty'], $item['product_id']]);

        // 3. Restore variant stock if applicable
        if ($item['variant_id']) {
            $vStmt = $pdo->prepare("UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?");
            $vStmt->execute([$item['qty'], $item['variant_id']]);
        }
    }

    // Mark order stock as not deducted
    $upStmt = $pdo->prepare("UPDATE orders SET stock_deducted = 0 WHERE id = ?");
    $upStmt->execute([$order_id]);
}

