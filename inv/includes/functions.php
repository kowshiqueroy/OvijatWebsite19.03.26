<?php
/**
 * includes/functions.php
 * Helper Functions
 */

/**
 * Sanitize user input
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Redirect to a given URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 */
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    }
    return $_SESSION['role'] === $roles;
}

/**
 * Require login to access page
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . 'login.php');
    }
}

/**
 * Require specific role to access page
 */
function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        redirect(BASE_URL . 'index.php?error=unauthorized');
    }
}

/**
 * Log an action to audit_logs table
 */
function auditLog($pdo, $action, $description = '', $reference_id = null) {
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, description, reference_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $description, $reference_id]);
}

/**
 * Get current stock of a product at a branch
 */
function getStock($pdo, $product_id, $branch_id) {
    $stmt = $pdo->prepare("SELECT quantity_pcs FROM inventory WHERE product_id = ? AND branch_id = ?");
    $stmt->execute([$product_id, $branch_id]);
    $result = $stmt->fetch();
    return $result ? $result['quantity_pcs'] : 0;
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return number_format($amount, 2) . ' ' . (defined('CURRENCY') ? CURRENCY : 'BDT');
}

/**
 * Format date
 */
function formatDate($date) {
    return date('d M, Y h:i A', strtotime($date));
}

/**
 * JSON Response for AJAX
 */
function jsonResponse($status, $message, $data = [], $extra = []) {
    header('Content-Type: application/json');
    $response = ['status' => $status, 'message' => $message, 'data' => $data];
    if (!empty($extra)) {
        $response = array_merge($response, $extra);
    }
    echo json_encode($response);
    exit();
}
?>
