<?php
session_start();
require_once 'db.php';

if (isLoggedIn()) { header('Location: users.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'register') {
            $username = sanitize($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm'] ?? '';
            $display = sanitize($_POST['display'] ?? $username);
            
            if (empty($username) || empty($password)) {
                $error = 'Username and password required';
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match';
            } else {
                try {
                    $id = createUser($username, $password, $display);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $id;
                    header('Location: users.php'); exit;
                } catch (Exception $e) {
                    $error = 'Username exists';
                }
            }
        }
        
        if ($action === 'login') {
            $username = sanitize($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $user = getUserByUsername($username);
            if ($user && verifyPassword($user, $password)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                header('Location: users.php'); exit;
            } else {
                $error = 'Invalid credentials';
            }
        }
    }
}

$userCount = getDB()->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>kotha.SohojWeb.com</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ECE5DD; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 90%; max-width: 350px; padding: 30px; }
        h1 { color: #128C7E; text-align: center; margin-bottom: 5px; }
        p.sub { text-align: center; color: #667781; margin-bottom: 20px; font-size: 14px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .btn { width: 100%; padding: 12px; background: #128C7E; color: #fff; border: none; border-radius: 5px; font-size: 14px; cursor: pointer; }
        .btn:hover { background: #25D366; }
        .link { text-align: center; margin-top: 15px; font-size: 14px; }
        .link a { color: #128C7E; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>kotha.SohojWeb.com</h1>
        <p class="sub">Secure messaging</p>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required placeholder="Username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Password">
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        
        <p class="link"><a href="#" onclick="document.getElementById('register').style.display='block';this.style.display='none'">Create Account</a></p>
        
        <form method="POST" id="register" style="display:<?= $userCount==0?'block':'none' ?>;">
            <hr style="margin:20px 0;border:none;border-top:1px solid #ddd;">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required placeholder="Username">
            </div>
            <div class="form-group">
                <label>Display Name</label>
                <input type="text" name="display" required placeholder="Your name">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Password">
            </div>
            <div class="form-group">
                <label>Confirm</label>
                <input type="password" name="confirm" required placeholder="Confirm password">
            </div>
            <button type="submit" class="btn">Create Account</button>
        </form>
    </div>
</body>
</html>