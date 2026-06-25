<?php
// index.php — Login page
session_name('EMS_SESS');
session_start();

define('EMS_ROOT', __DIR__);
require_once EMS_ROOT . '/config/constants.php';

// Redirect to install if not yet set up
if (!file_exists(EMS_ROOT . '/config/.installed')) {
    header('Location: install.php');
    exit;
}

require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/auth.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';
$school_name = setting('school_name', 'EMS');
$school_type = setting('school_type', 'school');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $stmt = db()->prepare(
                'SELECT id, password_hash, status FROM users WHERE username = :u OR email = :e LIMIT 1'
            );
            $stmt->execute([':u' => $username, ':e' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Your account is ' . $user['status'] . '. Please contact the administrator.';
                } else {
                    session_regenerate_id(true);
                    load_user_session((int)$user['id']);
                    log_activity('login', 'auth', $user['id']);

                    $redirect = $_GET['redirect'] ?? 'dashboard.php';
                    // Safety: only allow relative redirects
                    if (!preg_match('/^[a-z0-9_\/\-\.]+$/i', $redirect)) $redirect = 'dashboard.php';
                    redirect($redirect);
                }
            } else {
                $error = 'Invalid username or password.';
                // Small delay to mitigate brute force
                usleep(500000);
            }
        } catch (\Throwable $e) {
            $error = 'System error. Please try again.';
            error_log('[EMS Login] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}

$type_label = match($school_type) {
    'college'            => 'College Management System',
    'school_and_college' => 'School & College ERP',
    'madrasa'            => 'Madrasa Management System',
    default              => 'School Management System',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= e($school_name) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
.login-page { background: linear-gradient(135deg,#0f172a 0%,#1e3a5f 50%,#0d1b2e 100%); }
.login-illustration { color:rgba(255,255,255,.06); font-size:16rem; position:fixed; right:-3rem; bottom:-3rem; pointer-events:none; }
.school-badge { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.15); border-radius:20px; display:inline-flex; align-items:center; gap:.5rem; padding:.35rem .9rem; font-size:.78rem; color:rgba(255,255,255,.75); }
</style>
</head>
<body class="login-page">
<i class="bi bi-mortarboard login-illustration"></i>

<div class="container-fluid d-flex align-items-center justify-content-center" style="min-height:100vh;padding:1rem;">
  <div class="login-card">
    <div class="login-header">
      <div class="mb-2">
        <span class="school-badge">
          <i class="bi bi-geo-alt-fill"></i> Bangladesh
        </span>
      </div>
      <i class="bi bi-mortarboard-fill mb-2" style="font-size:2.5rem;opacity:.9;"></i>
      <h1><?= e($school_name) ?></h1>
      <p><?= e($type_label) ?></p>
    </div>

    <div class="login-body">
      <h5 class="fw-700 mb-1">Welcome Back</h5>
      <p class="text-muted mb-3" style="font-size:.85rem;">Sign in to your account to continue</p>

      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
          <i class="bi bi-exclamation-circle-fill"></i> <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" autocomplete="on">
        <div class="mb-3">
          <label class="form-label">Username or Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" name="username" class="form-control" placeholder="Enter username"
                   value="<?= e($_POST['username'] ?? '') ?>" autofocus autocomplete="username" required>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label d-flex justify-content-between">
            Password
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="pwd" class="form-control" placeholder="Enter password"
                   autocomplete="current-password" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd()">
              <i class="bi bi-eye" id="pwd-icon"></i>
            </button>
          </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4">
          <div class="form-check mb-0">
            <input type="checkbox" class="form-check-input" id="remember">
            <label class="form-check-label" for="remember" style="font-size:.85rem;">Remember me</label>
          </div>
          <button type="button" class="btn btn-link p-0 text-decoration-none" style="font-size:.85rem;" data-bs-toggle="modal" data-bs-target="#contactModal">
            Contact Institute
          </button>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-600">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
      </form>

      <hr class="my-3">
      <p class="text-center text-muted mb-0" style="font-size:.75rem;">
        EMS v<?= EMS_VERSION ?> &nbsp;|&nbsp; Powered by Bangladesh Education Portal
      </p>
    </div>
  </div>
</div>

<!-- Contact Institute Modal -->
<div class="modal fade animate-fade-in" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white py-3">
        <h5 class="modal-title fw-600" id="contactModalLabel"><i class="bi bi-telephone-fill me-2"></i>Contact Institute</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted mb-4">If you have forgotten your password or need assistance with your credentials, please contact the administration office:</p>
        <div class="d-flex flex-column gap-3">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-light text-primary d-grid place-items-center" style="width:36px;height:36px;place-items:center;flex-shrink:0;">
              <i class="bi bi-telephone-outbound"></i>
            </div>
            <div>
              <div class="text-muted small">Phone Number</div>
              <div class="fw-600"><?= e(setting('school_phone', '+880 1234 567890')) ?></div>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-light text-primary d-grid place-items-center" style="width:36px;height:36px;place-items:center;flex-shrink:0;">
              <i class="bi bi-envelope"></i>
            </div>
            <div>
              <div class="text-muted small">Email Address</div>
              <div class="fw-600"><?= e(setting('school_email', 'info@ems.edu.bd')) ?></div>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-light text-primary d-grid place-items-center" style="width:36px;height:36px;place-items:center;flex-shrink:0;">
              <i class="bi bi-geo-alt"></i>
            </div>
            <div>
              <div class="text-muted small">Address</div>
              <div class="fw-600"><?= e(setting('school_address', 'Dhaka, Bangladesh')) ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
  const inp  = document.getElementById('pwd');
  const icon = document.getElementById('pwd-icon');
  if (inp.type === 'password') { inp.type = 'text'; icon.className = 'bi bi-eye-slash'; }
  else { inp.type = 'password'; icon.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
