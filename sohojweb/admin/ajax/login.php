<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../includes/config/database.php';
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    ob_clean();
    jsonResponse(['success' => false, 'message' => 'Invalid session token'], 403);
}

if (empty($email) || empty($password)) {
    ob_clean();
    jsonResponse(['success' => false, 'message' => 'Email and password required']);
}

try {
    $user = db()->selectOne("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Query Error: ' . $e->getMessage()]);
    exit;
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    ob_clean();
    jsonResponse(['success' => false, 'message' => 'Invalid credentials']);
}

session_regenerate_id(true);
db()->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_name'] = $user['full_name'];

logAudit('login', 'user', $user['id']);

ob_clean();
jsonResponse(['success' => true, 'message' => 'Login successful']);
