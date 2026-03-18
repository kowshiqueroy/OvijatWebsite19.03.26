<?php
// ============================================================
// includes/auth.php — Session / authentication helpers
// ============================================================

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(SESSION_LIFETIME);
        session_start();
    }
}

function login(string $username, string $password): bool {
    $user = dbFetch('SELECT * FROM users WHERE username = ? AND active = 1', [$username]);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        logAction('LOGIN', 'auth', null, "User logged in as {$_SESSION['full_name']} (ID: {$_SESSION['user_id']}, Role: {$_SESSION['role']})");
        return true;
    }
    return false;
}

function logout(): void {
    logAction('LOGOUT', 'auth', null, "User logged out as {$_SESSION['full_name']} (ID: {$_SESSION['user_id']}, Role: {$_SESSION['role']})");
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php?page=login');
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        die('<h2>403 — Access Denied</h2>');
    }
}

function currentUser(): array {
    return [
        'id'        => $_SESSION['user_id']   ?? 0,
        'username'  => $_SESSION['username']  ?? '',
        'role'      => $_SESSION['role']      ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
    ];
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === ROLE_ADMIN;
}

function canDelete(): bool {
    return isAdmin();
}

/**
 * ── CSRF Protection ───────────────────────────────────────────
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            die('Error: CSRF token validation failed. Please refresh the page and try again.');
        }
    }
}
