<?php
require_once __DIR__ . '/config.php';

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? '';

// Admin check first (catches admin/ URL and index.php?page=admin)
if (strpos($page, 'admin') === 0 || strpos($_SERVER['REQUEST_URI'] ?? '', '/admin') !== false) {
    require __DIR__ . '/admin/index.php';
    exit;
}

if ($action === 'login' || $action === 'logout' || $action === 'register') {
    require __DIR__ . '/auth.php';
    exit;
}

$studentId = $_SESSION['student_id'] ?? null;
if (!$studentId) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <title><?= SITE_NAME ?> - Login</title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body class="login-page">
        <div class="login-container">
            <div class="logo">
                <span class="logo-icon">🏁</span>
                <h1><?= SITE_NAME ?></h1>
                <p class="subtitle">Fun Learning Races!</p>
            </div>
            <form id="loginForm" class="login-form" method="POST" action="index.php?action=login">
                <div class="input-group">
                    <label for="username">👤 Your Name</label>
                    <input type="text" id="username" name="username" placeholder="Enter your name" maxlength="20" required autocomplete="off">
                </div>
                <div class="input-group">
                    <label for="pin">🔢 4-Digit PIN</label>
                    <input type="password" id="pin" name="pin" placeholder="1234" pattern="[0-9]{4}" maxlength="4" inputmode="numeric" required>
                </div>
                <button type="submit" class="btn btn-primary btn-large">🚀 Let's Race!</button>
                <div id="loginError" class="error-msg"></div>
            </form>
            <div class="login-footer">
                <a href="admin/" class="link-small">👩‍🏫 Teacher Login</a>
            </div>
        </div>
        <script>
            document.getElementById('loginForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const form = e.target;
                const btn = form.querySelector('button');
                const err = document.getElementById('loginError');
                btn.disabled = true;
                btn.textContent = '⏳ Loading...';
                err.textContent = '';
                try {
                    const resp = await fetch(form.action, { method: 'POST', body: new FormData(form) });
                    const data = await resp.json();
                    if (data.success) {
                        window.location.href = data.redirect || 'index.php';
                    } else {
                        err.textContent = data.error || 'Something went wrong!';
                        btn.disabled = false;
                        btn.innerHTML = '🚀 Let\'s Race!';
                    }
                } catch(e) {
                    err.textContent = 'Connection error. Try again!';
                    btn.disabled = false;
                    btn.innerHTML = '🚀 Let\'s Race!';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

if ($page === 'dashboard') {
    require __DIR__ . '/dashboard.php';
    exit;
}

if ($page === 'race') {
    require __DIR__ . '/race.php';
    exit;
}

header('Location: index.php');
