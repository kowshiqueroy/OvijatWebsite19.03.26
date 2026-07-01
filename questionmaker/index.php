<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
_startSession();
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?> — <?= APP_TAGLINE ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/css/app.css">
<style>
body{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--bg-0);padding:20px;}
.login-card{background:var(--bg-1);border:1px solid var(--border);border-radius:20px;padding:44px 40px;width:100%;max-width:400px;box-shadow:0 24px 64px rgba(0,0,0,.4);}
.login-logo{text-align:center;margin-bottom:36px;}
.brand-mark{width:64px;height:64px;background:linear-gradient(135deg,var(--accent),#8b5cf6);border-radius:18px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:22px;color:#fff;letter-spacing:-1px;margin-bottom:14px;}
.login-logo h1{font-size:24px;font-weight:700;color:var(--text-0);margin:0 0 4px;}
.login-logo .tagline{font-size:12px;color:var(--text-3);margin:0 0 4px;}
.login-logo .domain{font-size:11px;color:var(--accent);font-weight:500;}
.err-box{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#f87171;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;display:none;}
.login-note{text-align:center;margin-top:18px;font-size:12px;color:var(--text-3);line-height:1.5;}
.login-note strong{color:var(--accent);}
.powered{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);font-size:11px;color:var(--text-3);}
.powered a{color:var(--accent);}
</style>
</head>
<body>
<div class="login-card">
  <div class="login-logo">
    <div class="brand-mark">QM</div>
    <h1><?= APP_NAME ?></h1>
    <p class="tagline"><?= APP_TAGLINE ?></p>
    <p class="domain"><?= APP_DOMAIN ?></p>
  </div>
  <div class="err-box" id="err"></div>
  <form id="loginForm">
    <div class="field-group">
      <label class="field-label">Username</label>
      <input class="field-input" type="text" name="username" placeholder="Enter your username" required autocomplete="username">
    </div>
    <div class="field-group">
      <label class="field-label">Password</label>
      <input class="field-input" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary btn-full" id="loginBtn">Sign In / Create Account</button>
  </form>
  <p class="login-note"><strong>New user?</strong> Pick a username &amp; password — your account is created instantly.</p>
</div>
<div class="powered">Powered by <a href="https://<?= APP_DOMAIN ?>" target="_blank"><?= APP_BRAND ?></a></div>

<script>
document.getElementById('loginForm').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('loginBtn');
  const err = document.getElementById('err');
  btn.textContent = 'Signing in…'; btn.disabled = true; err.style.display = 'none';
  const fd = new FormData(e.target); fd.append('action','login');
  try {
    const d = await fetch('api/auth.php',{method:'POST',body:fd}).then(r=>r.json());
    if (d.success) window.location.href = 'dashboard.php';
    else { err.textContent = d.error||'Login failed'; err.style.display='block'; btn.textContent='Sign In / Create Account'; btn.disabled=false; }
  } catch { err.textContent='Network error'; err.style.display='block'; btn.textContent='Sign In / Create Account'; btn.disabled=false; }
});
</script>
</body>
</html>
