<?php
/**
 * Logout Page
 */
require_once 'includes/AuthManager.php';
session_start();
$auth = new AuthManager();
$auth->logout();
header("Location: index.php");
exit;
