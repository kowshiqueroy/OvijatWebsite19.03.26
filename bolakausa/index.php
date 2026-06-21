<?php
/**
 * Main Front Controller & Router
 * Maps clean URLs to specific modules/files.
 */

// Fallback Default Timezone
date_default_timezone_set('America/New_York');

// Dynamic base URL for portability (works on any domain/subfolder)
$script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_url = rtrim($script_path, '/') . '/';
define('BASE_URL', $base_url);

// Output buffer replace all hardcoded /bolakausa/ paths with actual BASE_URL
ob_start(function($buffer) use ($base_url) {
    return str_replace('/bolakausa/', $base_url, $buffer);
});

session_start();

// Load Configuration
require_once 'config/database.php';
require_once 'includes/auth_helper.php';

// Dynamic Timezone Synchronization
$sys_timezone = get_setting($pdo, 'system_timezone', 'America/New_York');
date_default_timezone_set($sys_timezone);
$pdo->exec("SET time_zone = '" . date('P') . "'");

// Helper for XSS Prevention
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Basic Routing Logic
$url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : '';
$parts = explode('/', $url);
$route = $parts[0] ?: 'home';

if ($route === 'chat-api') {
    require_once 'modules/chat/chat_api.php';
    exit;
}

// Include Header (Shared Layout)
if ($route !== 'invoice') {
    require_once 'includes/header.php';
}

// Basic Auth Middleware
$user_role = get_user_role();

switch ($route) {
    case 'home':
        if ($user_role === 'wholesale_user' || $user_role === 'executive') {
            require_once 'modules/products/wholesale_home.php';
        } else {
            require_once 'modules/products/list.php';
        }
        break;

    case 'catalog':
        if ($user_role === 'wholesale_user' || $user_role === 'executive') {
            require_once 'modules/products/wholesale_catalog.php';
        } else {
            require_once 'modules/products/list.php';
        }
        break;

    case 'login':
        require_once 'modules/auth/login.php';
        break;

    case 'register':
        require_once 'modules/auth/register.php';
        break;

    case 'logout':
        require_once 'modules/auth/logout.php';
        break;

    case 'product':
        require_once 'modules/products/detail.php';
        break;

    case 'cart':
        require_once 'modules/orders/cart.php';
        break;

    case 'checkout':
        require_once 'modules/orders/checkout.php';
        break;

    case 'place-order':
        require_once 'modules/orders/place_order.php';
        break;

    case 'account':
        // Protected route example
        require_once 'modules/auth/account.php';
        break;

    case 'admin':
        if (!in_array($user_role, ['admin', 'manager', 'editor', 'viewer'])) {
            die("Access Denied: Admins, Managers, Editors, and Viewers Only");
        }
        $subroute = $parts[1] ?? 'dashboard';
        if ($user_role === 'viewer' && !in_array($subroute, ['users', 'dashboard'])) {
            die("Access Denied: Viewers can only access User Management.");
        }
        if ($user_role === 'editor' && !in_array($subroute, ['products', 'categories', 'orders', 'users', 'dashboard'])) {
            die("Access Denied: Editors can only access Products, Orders, and User Management.");
        }
        if ($subroute === 'categories') {
            require_once 'modules/products/admin_categories.php';
        } elseif ($subroute === 'products') {
            require_once 'modules/products/admin_products.php';
        } elseif ($subroute === 'payments') {
            if ($user_role !== 'admin') {
                die("Access Denied: Payment Approvals can only be accessed by Administrators.");
            }
            require_once 'admin_views/payment_approvals.php';
        } elseif ($subroute === 'users') {
            require_once 'admin_views/user_verification.php';
        } elseif ($subroute === 'wallet') {
            require_once 'admin_views/wallet_management.php';
        } elseif ($subroute === 'orders') {
            require_once 'admin_views/order_management.php';
        } elseif ($subroute === 'chats') {
            require_once 'admin_views/chat_management.php';
        } elseif ($subroute === 'logs') {
            require_once 'admin_views/audit_logs.php';
        } elseif ($subroute === 'promotions') {
            require_once 'admin_views/promotions_management.php';
        } elseif ($subroute === 'inventory-insights') {
            require_once 'admin_views/inventory_insights.php';
        } elseif ($subroute === 'settings') {
            require_once 'admin_views/settings.php';
        } else {
            require_once 'admin_views/dashboard.php';
        }
        break;

    case 'manager':
        if (!in_array($user_role, ['admin', 'manager'])) {
            die("Access Denied: Managers/Admins Only");
        }
        $subroute = $parts[1] ?? 'dashboard';
        if ($subroute === 'stock') {
            require_once 'manager_views/stock_in.php';
        } else {
            require_once 'manager_views/dashboard.php';
        }
        break;

    case 'warehouse':
        if ($user_role !== 'warehouse') die("Access Denied: Warehouse Staff Only");
        require_once 'warehouse_views/dashboard.php';
        break;

    case 'viewer':
        if ($user_role !== 'viewer') die("Access Denied: Auditors Only");
        require_once 'viewer_views/dashboard.php';
        break;

    case 'orders':
        require_once 'modules/orders/list.php';
        break;

    case 'chats':
        require_once 'modules/chat/chat.php';
        break;

    case 'wallet':
        require_once 'modules/wallet/user_wallet.php';
        break;

    case 'invoice':
        require_once 'modules/orders/invoice.php';
        break;

    case 'pay-later':
        require_once 'modules/orders/pay_later.php';
        break;

    case 'notifications':
        if (!is_logged_in()) {
            header('Location: ' . BASE_URL . 'login');
            exit;
        }
        $subroute = $parts[1] ?? '';
        if ($subroute === 'mark-read') {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . 'home'));
            exit;
        }
        break;

    default:
        http_response_code(404);
        echo "404 - Page Not Found";
        break;
}

// End Layout
if ($route !== 'invoice') {
    require_once 'includes/footer.php';
}

