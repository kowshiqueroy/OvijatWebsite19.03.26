<?php
session_start();
require_once __DIR__ . '/../includes/config/database.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$token = $_POST['csrf_token'] ?? '';

if (!verify_csrf($token)) {
    $_SESSION['login_error'] = 'Invalid session (CSRF mismatch). Please try again.';
    header('Location: login.php');
    exit;
}

if (empty($email) || empty($password)) {
    $_SESSION['login_error'] = 'Email and password required';
    header('Location: login.php');
    exit;
}

$user = db()->selectOne("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = 'Invalid credentials';
    header('Location: login.php');
    exit;
}

session_regenerate_id(true);
db()->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_name'] = $user['full_name'];

logAudit('login', 'user', $user['id']);

header('Location: dashboard/index.php');
exit;
