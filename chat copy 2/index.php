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
                cleanupGlobalOldMessages();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>kotha.sohojweb.com - AI Assistant</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background: #f4f7fb; 
            color: #1a1a1a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container { 
            background: #fff; 
            width: 100%; 
            max-width: 360px; 
            padding: 32px; 
            border-radius: 24px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            text-align: center;
        }

        .logo { 
            font-size: 32px; 
            margin-bottom: 8px; 
            color: #007bff;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        h1 { font-size: 20px; font-weight: 700; margin-bottom: 4px; color: #1a1a1a; }
        p.sub { font-size: 14px; color: #6c757d; margin-bottom: 24px; }

        .error { 
            background: #fff0f0; 
            color: #e03131; 
            padding: 12px; 
            border-radius: 12px; 
            margin-bottom: 20px; 
            font-size: 13px; 
            font-weight: 500;
            border: 1px solid #ffe3e3;
        }

        .form-group { margin-bottom: 16px; text-align: left; }
        .form-group label { 
            display: block; 
            margin-bottom: 6px; 
            font-size: 13px; 
            font-weight: 600; 
            color: #495057; 
        }
        .form-group input { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1px solid #e0e6ed; 
            border-radius: 12px; 
            font-size: 14px; 
            outline: none; 
            background: #f8f9fa;
            transition: all 0.2s;
        }
        .form-group input:focus { 
            background: #fff; 
            border-color: #007bff; 
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1); 
        }

        .btn { 
            width: 100%; 
            padding: 14px; 
            background: #007bff; 
            color: #fff; 
            border: none; 
            border-radius: 14px; 
            font-size: 15px; 
            font-weight: 700; 
            cursor: pointer; 
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0,123,255,0.2);
        }
        .btn:active { transform: scale(0.97); }

        .link { margin-top: 20px; font-size: 14px; color: #6c757d; }
        .link a { color: #007bff; text-decoration: none; font-weight: 600; }

        hr { border: none; border-top: 1px solid #e0e6ed; margin: 24px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">💬</div>
        <h1>kotha.sohojweb.com</h1>
        <p class="sub">AI-Powered Secure Messaging</p>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <div id="login-form">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Enter username">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn">Sign In</button>
            </form>
            <p class="link">New here? <a href="#" onclick="showRegister()">Create Account</a></p>
            <p class="link" style="margin-top: 12px;"><a href="https://drive.google.com/drive/folders/1R5RoKfLPiYLYXDxXiCdSDvKFnETuctfB?usp=sharing" target="_blank" style="color: #28a745;">📱 Download Android App</a></p>
        </div>
        
        <div id="register-form" style="display:<?= $userCount==0?'block':'none' ?>;">
            <?php if ($userCount > 0): ?><hr><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Choose username">
                </div>
                <div class="form-group">
                    <label>Display Name</label>
                    <input type="text" name="display" required placeholder="Your name">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn" style="background:#28a745; box-shadow: 0 4px 12px rgba(40,167,69,0.2);">Create Account</button>
            </form>
            <p class="link">Already have an account? <a href="#" onclick="showLogin()">Sign In</a></p>
            <p class="link" style="margin-top: 12px;"><a href="https://drive.google.com/drive/folders/1R5RoKfLPiYLYXDxXiCdSDvKFnETuctfB?usp=sharing" target="_blank" style="color: #28a745;">📱 Download Android App</a></p>
        </div>
    </div>

    <script>
    function showRegister() {
        document.getElementById('login-form').style.display = 'none';
        document.getElementById('register-form').style.display = 'block';
    }
    function showLogin() {
        document.getElementById('login-form').style.display = 'block';
        document.getElementById('register-form').style.display = 'none';
    }
    </script>
</body>
</html>