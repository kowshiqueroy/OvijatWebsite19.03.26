<?php
// ============================================================
// index.php — Front controller / page router
// ============================================================
require_once __DIR__ . '/includes/bootstrap.php';

// ── Logout ─────────────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'logout') {
    logout();
}

// ── Redirect to login if not authenticated ─────────────────────
$publicPages = ['login'];
$page = $_GET['page'] ?? 'dashboard';

if (!isLoggedIn() && !in_array($page, $publicPages)) {
    //if    get ?pagefrom= then go to login and after login go to that page, else go to dashboard
    if (isset($_GET['pagefrom'])) {
        header('Location: ' . BASE_URL . '/login.php?pagefrom=' . $_GET['pagefrom']);
        exit;
    }

    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if ($page === 'login') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// ── Route map ─────────────────────────────────────────────────
$routes = [
    'dashboard'      => 'modules/dashboard/dashboard.php',
    'pos'            => 'modules/pos/pos.php',
    'pos_edit'       => 'modules/pos/pos_edit.php',
    'products'       => 'modules/products/products.php',
    'product_entries' => 'modules/products/product_entries.php',
    'categories'     => 'modules/categories/categories.php',
    'brands'         => 'modules/brands/brands.php',
    'customers'      => 'modules/customers/customers.php',
    'sms'      => 'modules/settings/sms.php',
    'sales'          => 'modules/sales/sales.php',
    'invoice'        => 'modules/invoices/invoice.php',
    'finance'        => 'modules/finance/finance.php',
    'inventory_report' => 'modules/reports/inventory_report.php',
    'reports'        => 'modules/reports/reports.php',
    'users'          => 'modules/users/users.php',
    'logs'           => 'modules/logs/logs.php',
    'settings'       => 'modules/settings/settings.php',
    'barcodes'       => 'modules/barcodes/barcodes.php',
  
    'thermal' => 'modules/invoices/thermal.php',
    'backup' => 'modules/tools/backup.php',
];

if (isset($routes[$page])) {
    $file = BASE_PATH . '/' . $routes[$page];
    if (file_exists($file)) {
        require_once $file;
    } else {
        http_response_code(404);
        echo '<h2>404 — Page not found</h2>';
    }
} else {
    http_response_code(404);
    echo '<h2>404 — Page not found</h2>';
}

