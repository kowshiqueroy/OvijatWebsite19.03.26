<?php
/**
 * Admin Logout Redirector
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new AuthManager();
$auth->logout();

header("Location: ../login.php");
exit;
