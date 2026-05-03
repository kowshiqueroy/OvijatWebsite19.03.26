<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require_once 'db.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function login($username, $password) {
    global $pdo;

    // Normalize input to lowercase for easier matching
    $input = strtolower(trim($username));

    // Support login by ID (1 or 2) or custom names
    $id = null;
    if ($input === '1' || $input === 'kush') {
        $id = 1;
    } elseif ($input === '2' || $input === 'rai') {
        $id = 2;
    }

    if (!$id) return false;

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute(['pass_' . $id]);
    $db_pass_hash = $stmt->fetchColumn();

    if ($db_pass_hash && password_verify($password, $db_pass_hash)) {
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = ($id === 1 ? "Kush" : "Rai");
        $_SESSION['unlocked'] = false;
        return true;
    }
    return false;
}

function logout() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: index.php");
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
}
