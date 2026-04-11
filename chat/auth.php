<?php
/**
 * Authentication API
 * Secure login/registration with no username enumeration
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');
require_once __DIR__ . '/config.php';

session_start();

function errorResponse() {
    http_response_code(401);
    exit(json_encode([
        'success' => false,
        'message' => 'Invalid credentials or user not found'
    ]));
}

function successResponse($user) {
    http_response_code(200);
    exit(json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name']
        ]
    ]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db = getDB();

    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $displayName = trim($_POST['display_name'] ?? '');

        if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'Username must be 3-50 characters']));
        }

        if (empty($password) || strlen($password) < 8) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']));
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores']));
        }

        $existing = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
        if ($existing) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'Username already taken']));
        }

        $passwordHash = hashPassword($password);
        $db->query(
            "INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)",
            [$username, $passwordHash, $displayName ?: $username]
        );

        $user = $db->fetchOne("SELECT id, username, display_name FROM users WHERE username = ?", [$username]);
        initSession($user);
        successResponse($user);
    }

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            errorResponse();
        }

        $user = $db->fetchOne("SELECT * FROM users WHERE username = ?", [$username]);

        if (!$user || !verifyPassword($password, $user['password_hash'])) {
            errorResponse();
        }

        if ($user['is_active'] != 1) {
            errorResponse();
        }

        if ($user['duress_pin'] === $password && strlen($password) === 4 && is_numeric($password)) {
            initSession($user, true);
            successResponse($user);
        }

        initSession($user);
        successResponse($user);
    }

    if ($action === 'logout') {
        session_destroy();
        exit(json_encode(['success' => true]));
    }

    if ($action === 'check') {
        if (isLoggedIn()) {
            $user = $db->fetchOne("SELECT id, username, display_name FROM users WHERE id = ?", [$_SESSION['user_id']]);
            if ($user) {
                exit(json_encode([
                    'success' => true,
                    'logged_in' => true,
                    'user' => $user,
                    'is_duress' => $_SESSION['is_duress'] ?? false
                ]));
            }
        }
        exit(json_encode(['success' => true, 'logged_in' => false]));
    }
}

function initSession($user, $isDuress = false) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['session_token'] = generateToken(128);
    $_SESSION['inbox_unlocked'] = false;
    $_SESSION['unlocked_threads'] = [];
    $_SESSION['last_activity'] = time();
    $_SESSION['is_duress'] = $isDuress;

    $db = getDB();
    $db->query(
        "INSERT INTO sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))",
        [session_id(), $user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown', substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255), SESSION_TIMEOUT]
    );

    $db->query("UPDATE users SET last_active = NOW() WHERE id = ?", [$user['id']]);
}

http_response_code(400);
exit(json_encode(['success' => false, 'message' => 'Invalid request']));