<?php
require_once __DIR__ . '/db.php';

function _appBaseUrl(): string {
    $docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $appDir  = rtrim(str_replace('\\', '/', realpath(__DIR__ . '/..')), '/');
    $rel     = substr($appDir, strlen($docRoot));
    return rtrim($rel, '/') . '/';
}

function _startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_lifetime', SESSION_LIFETIME);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_name('qmpro_sess');
        session_start();
    }
}

function requireLogin(): array {
    _startSession();
    if (empty($_SESSION['user_id'])) {
        $base = _appBaseUrl();
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthenticated', 'redirect' => $base]);
            exit;
        }
        header('Location: ' . $base);
        exit;
    }
    return ['id' => (int)$_SESSION['user_id'], 'username' => $_SESSION['username']];
}

function loginUser(string $username, string $password): array {
    if (strlen($username) < 2) return ['success' => false, 'error' => 'Username must be at least 2 characters.'];
    if (strlen($password) < 4) return ['success' => false, 'error' => 'Password must be at least 4 characters.'];

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Incorrect password for this username.'];
        }
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')->execute([$username, $hash]);
        $user = ['id' => $db->lastInsertId(), 'username' => $username, 'claude_api_key' => ''];
    }

    _startSession();
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    return ['success' => true];
}

function logoutUser(): void {
    _startSession();
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

function getCurrentUser(): ?array {
    _startSession();
    if (empty($_SESSION['user_id'])) return null;
    $stmt = getDB()->prepare('SELECT id, username, claude_api_key, ai_keys_json FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row  = $stmt->fetch();
    if (!$row) return null;
    $row['ai_keys'] = json_decode($row['ai_keys_json'] ?? '{}', true) ?: [];
    if (empty($row['ai_keys']['claude']) && !empty($row['claude_api_key'])) {
        $row['ai_keys']['claude'] = $row['claude_api_key'];
    }
    return $row;
}
