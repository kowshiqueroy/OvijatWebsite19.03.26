<?php

/**
 * Sanitize Input Data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape Output Data
 */
function escape($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF Token
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verify_csrf($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

/**
 * Get Site Setting
 */
function getSetting($key, $default = null) {
    $row = db()->selectOne("SELECT setting_value FROM site_settings WHERE setting_key = ?", [$key]);
    return $row ? $row['setting_value'] : $default;
}

/**
 * Get Site Feature
 */
function getFeature($key, $default = null) {
    $row = db()->selectOne("SELECT feature_value FROM site_features WHERE feature_key = ?", [$key]);
    return $row ? $row['feature_value'] : $default;
}

function getServices() {
    return db()->select("SELECT * FROM site_services WHERE is_active = 1 ORDER BY service_order");
}

function getStats() {
    return db()->select("SELECT * FROM site_stats WHERE is_active = 1 ORDER BY stat_order");
}

function getWhyChoose() {
    return db()->select("SELECT * FROM site_why_choose WHERE is_active = 1 ORDER BY item_order");
}

function getContactInfo() {
    return db()->select("SELECT * FROM site_contact_info WHERE is_active = 1");
}

function getSocialLinks() {
    return db()->select("SELECT * FROM site_social_links WHERE is_active = 1");
}

function getTestimonials() {
    return db()->select("SELECT * FROM site_testimonials WHERE is_active = 1 ORDER BY id DESC");
}

function getTeam() {
    return db()->select("SELECT * FROM site_team WHERE is_active = 1 ORDER BY member_order");
}

function getPublishedCirculars() {
    return db()->select("SELECT * FROM job_circulars WHERE status = 'published' ORDER BY created_at DESC");
}

/**
 * Auth Functions
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . ADMIN_URL . '/login.php');
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return db()->selectOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

function hasPermission($requiredRole) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    $roles = ['super_admin' => 4, 'editor' => 3, 'sales' => 2, 'hr' => 1];
    $userLevel = $roles[$user['role']] ?? 0;
    $requiredLevel = $roles[$requiredRole] ?? 0;
    
    return $userLevel >= $requiredLevel;
}

/**
 * Audit Logging
 */
function logAudit($action, $entityType = null, $entityId = null, $oldData = null, $newData = null) {
    if (!isLoggedIn()) return;
    
    db()->insert('audit_logs', [
        'user_id' => $_SESSION['user_id'],
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'old_data' => $oldData ? json_encode($oldData) : null,
        'new_data' => $newData ? json_encode($newData) : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
//get running job circulars
function getRunningJobCirculars() {
    return db()->select("SELECT * FROM job_circulars WHERE status = 'published' AND apply_deadline >= CURDATE() ORDER BY created_at DESC");
}
