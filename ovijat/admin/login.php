<?php
// admin/login.php
require_once dirname(__DIR__) . '/config.php';

// Already logged in?
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $pdo  = getPDO();
        $stmt = $pdo->prepare("SELECT id, name, password_hash, role FROM admin_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_role'] = $admin['role'];
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            exit;
        }
    }
    $error = 'Invalid email or password. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — Ovijat</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    body { background: var(--clr-dark); display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .login-card {
      background: rgba(255,255,255,.04);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(201,168,76,.2);
      border-radius: 12px;
      padding: clamp(2rem, 5vw, 3rem);
      width: min(440px, 94vw);
    }
    .login-logo {
      text-align: center; margin-bottom: 2rem;
      font-family: var(--ff-ui); font-size: 1.5rem; font-weight: 800;
      color: var(--clr-gold); letter-spacing: .06em;
    }
    .login-logo small {
      display: block; font-size: .7rem; font-weight: 600;
      letter-spacing: .15em; text-transform: uppercase;
      color: rgba(247,245,240,.5); margin-top: .3rem;
    }
    .form-label { color: rgba(247,245,240,.7); }
    .form-control { background: rgba(255,255,255,.06); border-color: rgba(201,168,76,.25); color: var(--clr-offwhite); }
    .form-control:focus { background: rgba(255,255,255,.09); }
    .form-control::placeholder { color: rgba(247,245,240,.3); }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-logo">
      OFB Admin
      <small>Ovijat Food &amp; Beverage</small>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input class="form-control" type="email" id="email" name="email"
               value="<?= e($_POST['email'] ?? '') ?>"
               required autocomplete="email" placeholder="admin@ovijatfood.com">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password"
               required autocomplete="current-password" placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem;">
        Sign In
      </button>
    </form>
  </div>
</body>
</html>
