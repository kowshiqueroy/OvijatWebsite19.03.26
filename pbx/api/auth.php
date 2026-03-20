<?php
require_once '../config.php';
require_once '../functions.php';
requireAdmin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'change_password') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $current = $data['current'] ?? '';
    $newPass = $data['new_pass'] ?? '';
    
    if (empty($current) || empty($newPass)) {
        echo json_encode(['status' => 'error', 'error' => 'All fields required']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user || !password_verify($current, $user['password'])) {
        echo json_encode(['status' => 'error', 'error' => 'Current password incorrect']);
        exit;
    }
    
    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param('si', $hash, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'error' => 'Failed to update password']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'error' => 'Unknown action']);
