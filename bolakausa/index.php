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
            require_once 'includes/access_denied.php';
            render_access_denied('Staff Portal Only', 'This area is restricted to Admins, Managers, Editors, and Viewers. If you need access, please contact your administrator.');
        }
        $subroute = $parts[1] ?? 'dashboard';
        if ($user_role === 'viewer' && !in_array($subroute, ['users', 'dashboard'])) {
            require_once 'includes/access_denied.php';
            render_access_denied('Viewer Access Restricted', 'Your Viewer role only has access to the Financial Overview and User Management sections.');
        }
        if ($user_role === 'editor' && !in_array($subroute, ['products', 'categories', 'orders', 'users', 'dashboard'])) {
            require_once 'includes/access_denied.php';
            render_access_denied('Editor Access Restricted', 'Your Editor role only has access to Catalog Control, Order Management, and User Management.');
        }
        if ($subroute === 'categories') {
            require_once 'modules/products/admin_categories.php';
        } elseif ($subroute === 'products') {
            require_once 'modules/products/admin_products.php';
        } elseif ($subroute === 'payments') {
            if ($user_role !== 'admin') {
                require_once 'includes/access_denied.php';
                render_access_denied('Administrators Only', 'Payment Approvals can only be accessed by system Administrators.');
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
        } elseif ($subroute === 'accounting') {
            require_once 'admin_views/accounting_hub.php';
        } elseif ($subroute === 'settings') {
            require_once 'admin_views/settings.php';
        } elseif ($subroute === 'emails') {
            require_once 'admin_views/email_logs.php';
        } else {
            require_once 'admin_views/dashboard.php';
        }
        break;

    case 'manager':
        if (!in_array($user_role, ['admin', 'manager'])) {
            require_once 'includes/access_denied.php';
            render_access_denied('Managers & Admins Only', 'The Operations area is restricted to Managers and Administrators.');
        }
        $subroute = $parts[1] ?? 'dashboard';
        if ($subroute === 'stock') {
            require_once 'manager_views/stock_in.php';
        } else {
            require_once 'manager_views/dashboard.php';
        }
        break;

    case 'warehouse':
        if ($user_role !== 'warehouse') {
            require_once 'includes/access_denied.php';
            render_access_denied('Warehouse Staff Only', 'The Fulfillment area is restricted to authorized Warehouse staff.');
        }
        require_once 'warehouse_views/dashboard.php';
        break;

    case 'viewer':
        if ($user_role !== 'viewer') {
            require_once 'includes/access_denied.php';
            render_access_denied('Auditors Only', 'The Financial Overview is restricted to Viewer/Auditor accounts.');
        }
        require_once 'viewer_views/dashboard.php';
        break;

    case 'orders':
        $subroute = $parts[1] ?? '';
        if ($subroute === 'edit') {
            require_once 'modules/orders/edit_order.php';
        } else {
            require_once 'modules/orders/list.php';
        }
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
        echo '<style>
        .error-page-wrap{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;padding:2rem 1rem;text-align:center}
        .error-icon-ring{width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(99,102,241,.04));border:2px solid rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;margin:0 auto 1.75rem;box-shadow:0 0 0 8px rgba(99,102,241,.06)}
        .error-icon-ring i{font-size:2.5rem;color:#6366f1}
        .error-code{font-family:"Plus Jakarta Sans",sans-serif;font-size:5rem;font-weight:800;color:#6366f1;line-height:1;letter-spacing:-2px;margin-bottom:.25rem;opacity:.15}
        .error-title{font-family:"Plus Jakarta Sans",sans-serif;font-size:1.65rem;font-weight:800;color:#0f172a;margin-bottom:.75rem}
        .error-msg{color:#64748b;font-size:.975rem;max-width:420px;line-height:1.65;margin-bottom:2rem}
        </style>
        <div class="error-page-wrap">
            <div class="error-icon-ring"><i class="fas fa-map-signs"></i></div>
            <div class="error-code">404</div>
            <h1 class="error-title">Page Not Found</h1>
            <p class="error-msg">The page you are looking for does not exist or may have been moved.</p>
            <a href="' . BASE_URL . 'home" style="background:#10b981;color:white;padding:.7rem 1.6rem;border-radius:10px;font-weight:700;font-size:.875rem;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;"><i class="fas fa-home"></i> Back to Home</a>
        </div>';
        break;
}

// End Layout
if ($route !== 'invoice') {
    require_once 'includes/footer.php';
}

