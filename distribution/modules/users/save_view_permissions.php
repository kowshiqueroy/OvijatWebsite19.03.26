<?php
/**
 * Save / Get viewer permission profiles (AJAX endpoint)
 */
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN]);
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get') {
    $user_id = intval($_GET['user_id'] ?? 0);
    $perms = fetch_one("SELECT * FROM user_view_permissions WHERE user_id = ?", [$user_id]);
    if (!$perms) {
        // Return defaults
        $perms = [
            'user_id' => $user_id,
            'show_local' => 1, 'show_export' => 1, 'show_custom' => 1,
            'show_sales_kpis' => 1, 'show_inventory_section' => 1,
            'show_delivery_section' => 1, 'show_accounts_section' => 0,
            'can_see_stock_report' => 1, 'can_see_inventory_report' => 1,
            'can_see_comprehensive_report' => 1, 'can_see_transactions' => 0,
            'can_see_dmd_dashboard' => 0, 'show_rates' => 1,
            'show_customer_balances' => 0,
        ];
    }
    echo json_encode(['success' => true, 'data' => $perms]);
    exit;
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);
        exit;
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user_id']);
        exit;
    }

    // Verify target user is a Viewer
    $target = fetch_one("SELECT id, role FROM users WHERE id = ? AND isDelete = 0", [$user_id]);
    if (!$target || $target['role'] !== ROLE_VIEWER) {
        echo json_encode(['success' => false, 'message' => 'User not found or not a Viewer']);
        exit;
    }

    $fields = [
        'show_local', 'show_export', 'show_custom',
        'show_sales_kpis', 'show_inventory_section', 'show_delivery_section', 'show_accounts_section',
        'can_see_stock_report', 'can_see_inventory_report', 'can_see_comprehensive_report',
        'can_see_transactions', 'can_see_dmd_dashboard',
        'show_rates', 'show_customer_balances',
    ];

    // Check if exists
    $exists = fetch_one("SELECT id FROM user_view_permissions WHERE user_id = ?", [$user_id]);

    if ($exists) {
        $sets = implode(', ', array_map(fn($f) => "`$f` = ?", $fields));
        $vals = array_map(fn($f) => isset($_POST[$f]) ? 1 : 0, $fields);
        $vals[] = $user_id;
        db_query("UPDATE user_view_permissions SET $sets WHERE user_id = ?", $vals);
    } else {
        $cols = '`user_id`, ' . implode(', ', array_map(fn($f) => "`$f`", $fields));
        $placeholders = implode(', ', array_fill(0, count($fields) + 1, '?'));
        $vals = array_merge([$user_id], array_map(fn($f) => isset($_POST[$f]) ? 1 : 0, $fields));
        db_query("INSERT INTO user_view_permissions ($cols) VALUES ($placeholders)", $vals);
    }

    // Clear cached permissions
    clear_view_permissions_cache($user_id);

    log_activity($_SESSION['user_id'], "Updated view permissions for User ID: $user_id");
    echo json_encode(['success' => true, 'message' => 'Permissions saved.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
