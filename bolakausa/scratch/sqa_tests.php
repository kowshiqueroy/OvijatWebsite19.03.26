<?php
/**
 * Bolakausa SQA Automated Test Suite
 * Run via CLI: php scratch/sqa_tests.php
 */

define('CLI_MODE', php_sapi_name() === 'cli');

function log_test($name, $status, $message = '') {
    if (CLI_MODE) {
        $color = $status === 'PASS' ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
        echo "$color $name " . ($message ? "- $message" : "") . "\n";
    } else {
        $color = $status === 'PASS' ? 'green' : 'red';
        echo "<div style='color: $color; margin-bottom: 5px;'><strong>[$status]</strong> $name " . ($message ? "- " . htmlspecialchars($message) : "") . "</div>";
    }
}

echo "=========================================\n";
echo "       BOLAKAUSA SQA TEST HARNESS        \n";
echo "=========================================\n\n";

// --- PHASE 1: DATABASE TESTS ---
echo "--- Phase 1: Database Integrity ---\n";

if (!file_exists(__DIR__ . '/../config/database.php')) {
    log_test("Database Config File Existence", "FAIL", "config/database.php does not exist.");
    exit(1);
}
log_test("Database Config File Existence", "PASS");

require_once __DIR__ . '/../config/database.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    log_test("Database Connection", "FAIL", "PDO connection object not initialized.");
    exit(1);
}
log_test("Database Connection", "PASS");

$tables_to_verify = [
    'users' => ['id', 'username', 'email', 'password', 'role', 'status', 'is_deleted'],
    'user_addresses' => ['id', 'user_id', 'address_line', 'is_default', 'is_deleted'],
    'locations' => ['id', 'name', 'tax_percent', 'is_deleted'],
    'categories' => ['id', 'name', 'is_deleted'],
    'products' => ['id', 'category_id', 'name', 'base_price', 'stock_qty', 'is_deleted'],
    'product_images' => ['id', 'product_id', 'image_path', 'is_deleted'],
    'product_variants' => ['id', 'product_id', 'price_modifier', 'is_deleted'],
    'product_price_tiers' => ['id', 'product_id', 'unit_price', 'is_deleted'],
    'discounts' => ['id', 'name', 'target_wholesalers', 'is_deleted'],
    'coupons' => ['id', 'code', 'target_wholesalers', 'is_deleted'],
    'orders' => ['id', 'user_id', 'status', 'total_amount', 'is_deleted'],
    'order_items' => ['id', 'order_id', 'product_id', 'qty', 'is_deleted'],
    'inventory_lots' => ['id', 'product_id', 'lot_number', 'qty_remaining', 'is_deleted'],
    'order_item_picks' => ['id', 'order_item_id', 'lot_id', 'qty', 'is_deleted'],
    'order_status_history' => ['id', 'order_id', 'status', 'changed_by'],
    'wallet_transactions' => ['id', 'user_id', 'type', 'amount'],
    'wallet_topups' => ['id', 'user_id', 'amount', 'status', 'order_id'],
    'settings' => ['setting_key', 'setting_value'],
    'system_logs' => ['id', 'action_type'],
    'chats' => ['id', 'user_id', 'message', 'is_deleted'],
    'notifications' => ['id', 'user_id', 'title', 'is_deleted'],
    'promotions' => ['id', 'title', 'target_wholesalers', 'is_deleted']
];

foreach ($tables_to_verify as $table => $columns) {
    try {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $existing_cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missing = [];
        foreach ($columns as $col) {
            if (!in_array($col, $existing_cols)) {
                $missing[] = $col;
            }
        }
        
        if (!empty($missing)) {
            log_test("Table Integrity: `$table`", "FAIL", "Missing columns: " . implode(', ', $missing));
        } else {
            log_test("Table Integrity: `$table`", "PASS");
        }
    } catch (PDOException $e) {
        log_test("Table Integrity: `$table`", "FAIL", "Table does not exist or description failed: " . $e->getMessage());
    }
}

// Check basic CRUD
try {
    // Insert test user
    $test_username = 'sqa_test_user_' . time();
    $test_email = $test_username . '@example.com';
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'wholesale_user', 'active')");
    $stmt->execute([$test_username, $test_email, password_hash('password123', PASSWORD_BCRYPT)]);
    $user_id = $pdo->lastInsertId();
    log_test("DB Write Operations", "PASS", "Inserted test user ID: $user_id");

    // Read test user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $fetched = $stmt->fetch();
    if ($fetched && $fetched['username'] === $test_username) {
        log_test("DB Read Operations", "PASS", "Successfully fetched written user.");
    } else {
        log_test("DB Read Operations", "FAIL", "Failed to fetch inserted user or data mismatch.");
    }

    // Delete test user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    log_test("DB Delete Operations", "PASS", "Cleaned up test user.");
} catch (PDOException $e) {
    log_test("DB CRUD Operations", "FAIL", $e->getMessage());
}

// --- PHASE 2: BACKEND FILE EXISTENCE AND ROUTING TESTS ---
echo "\n--- Phase 2: Backend Route and Core Module Files ---\n";

$core_files = [
    'includes/auth_helper.php',
    'includes/header.php',
    'includes/footer.php',
    'modules/auth/login.php',
    'modules/auth/register.php',
    'modules/auth/logout.php',
    'modules/auth/account.php',
    'modules/chat/chat.php',
    'modules/chat/chat_api.php',
    'modules/orders/cart.php',
    'modules/orders/checkout.php',
    'modules/orders/place_order.php',
    'modules/orders/invoice.php',
    'modules/orders/list.php',
    'modules/orders/pay_later.php',
    'modules/products/admin_categories.php',
    'modules/products/admin_products.php',
    'modules/products/detail.php',
    'modules/products/list.php',
    'modules/products/wholesale_catalog.php',
    'modules/products/wholesale_home.php',
    'modules/products/wholesale_sidebar.php',
    'modules/wallet/user_wallet.php',
    'admin_views/audit_logs.php',
    'admin_views/chat_management.php',
    'admin_views/dashboard.php',
    'admin_views/inventory_insights.php',
    'admin_views/order_management.php',
    'admin_views/payment_approvals.php',
    'admin_views/promotions_management.php',
    'admin_views/settings.php',
    'admin_views/user_verification.php',
    'admin_views/wallet_management.php',
    'manager_views/dashboard.php',
    'manager_views/stock_in.php',
    'warehouse_views/dashboard.php',
    'viewer_views/dashboard.php'
];

foreach ($core_files as $file) {
    if (file_exists(__DIR__ . '/../' . $file)) {
        log_test("Core File: `$file`", "PASS");
    } else {
        log_test("Core File: `$file`", "FAIL", "File is missing!");
    }
}

// --- PHASE 3: FRONTEND ENDPOINT AND RENDER TESTS ---
echo "\n--- Phase 3: Frontend Endpoint Accessibility & Render Validation ---\n";

$local_urls = [
    'Home Catalog' => 'http://localhost/bolakausa/home',
    'Login Portal' => 'http://localhost/bolakausa/login',
    'Registration Portal' => 'http://localhost/bolakausa/register',
    'Non-existent Route (404)' => 'http://localhost/bolakausa/some-bad-route-name-123'
];

foreach ($local_urls as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($html === false) {
        log_test("URL: $name ($url)", "FAIL", "Failed to connect to local server.");
        continue;
    }

    // Check HTTP status code
    if ($name === 'Non-existent Route (404)') {
        if ($http_code === 404) {
            log_test("HTTP Code: $name", "PASS", "Correctly returned 404.");
        } else {
            log_test("HTTP Code: $name", "FAIL", "Expected 404, got $http_code.");
        }
    } else {
        if ($http_code === 200) {
            log_test("HTTP Code: $name", "PASS", "Returned 200 OK.");
        } else {
            log_test("HTTP Code: $name", "FAIL", "Expected 200, got $http_code.");
        }
    }

    // Check for PHP errors in the output HTML
    $php_errors = [];
    $error_patterns = [
        '/Fatal error/i',
        '/Parse error/i',
        '/Warning:/i',
        '/Notice:/i',
        '/Uncaught Exception/i',
        '/PDOException/i'
    ];
    
    foreach ($error_patterns as $pattern) {
        if (preg_match($pattern, $html)) {
            $php_errors[] = $pattern;
        }
    }

    if (!empty($php_errors)) {
        log_test("PHP Errors: $name", "FAIL", "Found PHP error patterns: " . implode(', ', $php_errors));
    } else {
        log_test("PHP Errors: $name", "PASS", "No visible PHP errors, notices, or warnings.");
    }

    // Check critical HTML layouts
    if ($name === 'Login Portal') {
        if (strpos($html, 'name="username"') !== false && strpos($html, 'type="password"') !== false) {
            log_test("HTML Elements: $name", "PASS", "Contains username and password input elements.");
        } else {
            log_test("HTML Elements: $name", "FAIL", "Missing username or password input fields.");
        }
    } elseif ($name === 'Home Catalog') {
        if (strpos($html, 'class="app-layout"') !== false || strpos($html, 'app-layout') !== false) {
            log_test("HTML Elements: $name", "PASS", "Contains core app layout elements.");
        } else {
            log_test("HTML Elements: $name", "FAIL", "Missing '.app-layout' class selector.");
        }
    }
}

echo "\n=========================================\n";
echo "             TEST COMPLETE               \n";
echo "=========================================\n";
