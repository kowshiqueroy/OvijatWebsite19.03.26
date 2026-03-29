<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function requireRole(string $role): void {
    $user = currentUser();
    if (!$user) {
        redirect(BASE_URL . '/login.php');
    }
    if ($role === 'admin' && $user['role'] !== 'admin') {
        redirect(BASE_URL . '/index.php');
    }
}

function redirect(string $url): never {
    header("Location: $url");
    exit;
}

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function validateCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('CSRF validation failed.');
        }
    }
}
