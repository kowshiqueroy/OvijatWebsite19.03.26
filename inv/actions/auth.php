<?php
/**
 * actions/auth.php
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'logout') {
    auditLog($pdo, 'Logout', 'User logged out');
    session_destroy();
    redirect('../login.php');
}

if ($action === 'switch_branch') {
    requireLogin();
    $branch_id = (int)$_GET['branch_id'];
    
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$branch_id]);
    $branch = $stmt->fetch();
    
    if ($branch) {
        $_SESSION['branch_id'] = $branch['id'];
        $_SESSION['branch_name'] = $branch['name'];
        auditLog($pdo, 'Switch Branch', 'Switched to: ' . $branch['name']);
    }
    redirect('../index.php');
}

redirect('../index.php');
?>
