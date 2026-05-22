<?php
/**
 * Stealth Admin Login Form
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthManager.php';

$auth = new AuthManager();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if ($auth->verifyCSRFToken($token)) {
        $result = $auth->login($email, $password);
        if ($result['success']) {
            if ($result['user']['role'] === 'admin') {
                header("Location: dashboard.php");
                exit;
            } else {
                $auth->logout();
                $error = "Unauthorized access.";
            }
        } else {
            $error = $result['message'];
        }
    } else {
        $error = "Security token mismatch.";
    }
}

$csrfToken = $auth->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stealth Access | Sobjiwali</title>
    <style>
        body { background: #1a1a1a; color: #fff; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #262626; padding: 2rem; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); width: 320px; }
        h2 { text-align: center; margin-bottom: 1.5rem; color: #4CAF50; font-size: 1.2rem; text-transform: uppercase; letter-spacing: 2px; }
        input { width: 100%; padding: 12px; margin-bottom: 1rem; border: 1px solid #444; background: #333; color: #fff; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #4CAF50; border: none; color: #fff; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #45a049; }
        .error { color: #ff5252; text-align: center; font-size: 0.9rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Gatekeeper</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="email" name="email" placeholder="Identifier" required>
            <input type="password" name="password" placeholder="Passcode" required>
            <button type="submit">Initialize Access</button>
        </form>
    </div>
</body>
</html>
