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
        header('Location: /bolakausa/login');
        exit;
    }

    $role = get_user_role();
    if (!in_array($role, $allowed_roles)) {
        http_response_code(403);
        die("Access Denied: You do not have permission to view this page.");
    }

    // Also check if user is active (except for admin/manager)
    if ($role === 'wholesale_user' && ($_SESSION['user_status'] ?? '') !== 'active') {
        die("Account Pending: Your account is awaiting admin approval.");
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
