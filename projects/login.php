<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';

if (currentUser()) redirect(BASE_URL . '/index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = dbFetch("SELECT * FROM users WHERE username = ?", [$username]);
    if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id'        => $user['id'],
            'username'  => $user['username'],
            'full_name' => $user['full_name'],
            'role'      => $user['role'],
        ];
        redirect(BASE_URL . '/index.php');
    } else {
        $error = 'Invalid username or password, or account is inactive.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — <?= APP_NAME ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#F4F6F9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:40px;width:100%;max-width:400px}
h1{font-size:1.5rem;font-weight:700;color:#1A1D23;margin-bottom:4px}
.sub{color:#6B7280;font-size:.875rem;margin-bottom:28px}
label{display:block;font-size:.8125rem;font-weight:500;color:#374151;margin-bottom:4px;margin-top:16px}
input{width:100%;padding:9px 12px;border:1px solid #E2E6EA;border-radius:6px;font-size:.9rem;outline:none;transition:.2s}
input:focus{border-color:#4F6BED;box-shadow:0 0 0 3px rgba(79,107,237,.12)}
button{width:100%;margin-top:24px;padding:11px;background:#4F6BED;color:#fff;border:none;border-radius:6px;font-size:.9375rem;font-weight:600;cursor:pointer;transition:.2s}
button:hover{background:#3A56D4}
.error{background:#FEF2F2;color:#DC2626;padding:12px 16px;border-radius:6px;font-size:.875rem;margin-bottom:16px;border:1px solid #FECACA}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:28px}
.brand-icon{width:40px;height:40px;background:#4F6BED;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.1rem}
</style>
</head>
<body>
<div class="card">
    <div class="brand">
        <div class="brand-icon">SW</div>
        <div>
            <h1><?= APP_NAME ?></h1>
            <p class="sub" style="margin:0">Sign in to continue</p>
        </div>
    </div>
    <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif ?>
    <form method="POST">
        <label>Username</label>
        <input name="username" autofocus autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>">
        <label>Password</label>
        <input name="password" type="password" autocomplete="current-password">
        <button type="submit">Sign In</button>
    </form>
</div>
</body>
</html>
