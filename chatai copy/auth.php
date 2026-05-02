<?php
session_start();
require_once 'db.php';

function login($username, $password) {
    global $pdo;
    
    // Support login by ID (1 or 2) or "User 1" / "User 2"
    $id = ($username == 'User 1' || $username == '1') ? 1 : (($username == 'User 2' || $username == '2') ? 2 : null);
    
    if (!$id) return false;

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute(['pass_' . $id]);
    $db_pass = $stmt->fetchColumn();

    if ($db_pass === $password) {
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = "User " . $id;
        $_SESSION['unlocked'] = false;
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    header("Location: index.php");
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
}
