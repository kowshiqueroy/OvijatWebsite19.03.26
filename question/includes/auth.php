<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = getDB()->prepare('SELECT id, username, display_name, settings_json FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function requireLogin(): array {
    $user = currentUser();
    if (!$user) jsonResponse(['ok' => false, 'error' => 'Not logged in'], 401);
    return $user;
}

function checkUserExists(string $username): bool {
    $stmt = getDB()->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    return (bool) $stmt->fetch();
}

function loginUser(string $username, string $password): array {
    $stmt = getDB()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['ok' => false, 'code' => 'no_user', 'error' => 'No user found'];
    }
    if (!password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'code' => 'wrong_password', 'error' => 'Wrong password, try again'];
    }

    $_SESSION['user_id'] = (int) $user['id'];
    unset($user['password_hash']);
    return ['ok' => true, 'user' => $user];
}

function registerUser(string $username, string $password, string $displayName = ''): array {
    if (checkUserExists($username)) {
        return ['ok' => false, 'code' => 'exists', 'error' => 'User already exists'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = getDB()->prepare('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)');
    $stmt->execute([$username, $hash, $displayName ?: $username]);
    $_SESSION['user_id'] = (int) getDB()->lastInsertId();
    return ['ok' => true, 'user' => currentUser()];
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
