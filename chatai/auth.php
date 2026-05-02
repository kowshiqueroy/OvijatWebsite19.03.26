<?php
session_start();
require_once 'db.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function login($username, $password) {
    global $pdo;

    $username = trim($username);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] !== 'active') {
            return ['success' => false, 'error' => 'Your account is ' . $user['status'] . '. Please wait for admin approval.'];
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['unlocked'] = false;
        return ['success' => true];
    }
    return ['success' => false, 'error' => 'Invalid username or password.'];
}

function register($username, $display_name, $password, $pin) {
    global $pdo;
    
    $username = trim($username);
    if (empty($username) || empty($password) || empty($pin)) {
        return ['success' => false, 'error' => 'All fields are required.'];
    }

    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmtCheck->execute([$username]);
    if ($stmtCheck->fetchColumn() > 0) {
        return ['success' => false, 'error' => 'Username already exists.'];
    }

    $passHash = password_hash($password, PASSWORD_BCRYPT);
    $pinHash = password_hash($pin, PASSWORD_BCRYPT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, display_name, password_hash, pin_hash) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $display_name, $passHash, $pinHash]);
        $userId = $pdo->lastInsertId();

        // Initialize default privacy settings
        $stmtPriv = $pdo->prepare("INSERT INTO user_privacy_settings (user_id) VALUES (?)");
        $stmtPriv->execute([$userId]);

        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Registration failed: ' . $e->getMessage()];
    }
}

function logout() {
    session_destroy();
    header("Location: index.php");
    exit();
}

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'logout') {
        logout();
    }
}
