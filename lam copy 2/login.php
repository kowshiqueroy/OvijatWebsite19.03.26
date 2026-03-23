<?php
// ============================================================
// login.php — Standalone login page
// ============================================================
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) { redirect('pos'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        redirect('pos');
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
<style>
  body { display:flex; align-items:center; justify-content:center; min-height:100vh; }
  .login-box { width:100%; max-width:380px; padding:16px; }
  .login-logo { text-align:center; font-size:2rem; font-weight:900; color:var(--accent); margin-bottom:8px; }
  .login-sub  { text-align:center; color:var(--text-muted); font-size:.9rem; margin-bottom:28px; }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-logo">🛒 <?= APP_NAME ?></div>
  <div class="login-sub">Sign in to continue</div>

  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif ?>

  <div class="card">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" autofocus required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px">Sign In</button>
    </form>
  </div>
  <p class="text-muted text-center" style="font-size:.78rem;margin-top:12px">
    <?= APP_NAME ?> v<?= APP_VERSION ?>
  </p>
</div>
</body>
</html>
