<?php
/**
 * Main Front Controller & Router
 * Maps clean URLs to specific modules/files.
 */

ob_start();
session_start();

// Load Configuration
require_once 'config/database.php';
require_once 'includes/auth_helper.php';

// Include Header (Shared Layout)
require_once 'includes/header.php';

// Helper for XSS Prevention
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Basic Routing Logic
$url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : '';
$parts = explode('/', $url);

$route = $parts[0] ?: 'home';

// Basic Auth Middleware
$user_role = get_user_role();

switch ($route) {
    case 'home':
        require_once 'modules/products/list.php';
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
        if ($user_role !== 'admin') {
            die("Access Denied: Admins Only");
        }
        $subroute = $parts[1] ?? 'dashboard';
        if ($subroute === 'categories') {
            require_once 'modules/products/admin_categories.php';
        } elseif ($subroute === 'products') {
            require_once 'modules/products/admin_products.php';
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

    default:
        http_response_code(404);
        echo "404 - Page Not Found";
        break;
}

// End Layout
require_once 'includes/footer.php';

