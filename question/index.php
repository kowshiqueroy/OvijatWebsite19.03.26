<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (currentUser()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Question Paper Maker — Unicode Question Making, Any Device</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body class="landing">

<nav class="nav">
    <div class="nav-brand">Prosnopotro<span>.</span></div>
    <button class="btn btn-primary" id="openAuthBtn">Login / Enter</button>
</nav>

<header class="hero">
    <div class="hero-content">
        <h1>Unicode-based question making,<br>from any device.</h1>
        <p>Build board-standard exam question papers in Bangla or English — right from your phone,
           tablet, or desktop. No installs. No fuss.</p>
        <button class="btn btn-primary btn-lg" id="heroAuthBtn">Get Started — It's Free</button>
    </div>
</header>

<section class="features">
    <div class="feature">
        <div class="feature-icon">✍️</div>
        <h3>Full Question Builder</h3>
        <p>Instructions, section headers, MCQ, creative/passage questions, images and equations — all drag-to-reorder.</p>
    </div>
    <div class="feature">
        <div class="feature-icon">🖨️</div>
        <h3>Print-Perfect Output</h3>
        <p>A4 or Legal, full page, half-page duplicate, or bookbinding-ready booklet layouts.</p>
    </div>
    <div class="feature">
        <div class="feature-icon">🌐</div>
        <h3>Bangla &amp; English</h3>
        <p>Auto numbering in Bangla or English numerals, with your choice of Kalpurush, Siyam Rupali, SutonnyUniBanglaOMJ and more.</p>
    </div>
    <div class="feature">
        <div class="feature-icon">📱</div>
        <h3>Mobile First</h3>
        <p>Designed to work beautifully on a phone screen, not just a desktop.</p>
    </div>
</section>

<footer class="landing-footer">
    <p>&copy; <?= date('Y') ?> Prosnopotro — Question Paper Maker</p>
</footer>

<!-- Auth Modal -->
<div class="modal-overlay" id="authOverlay">
    <div class="modal" id="authModal">
        <button class="modal-close" id="authClose">&times;</button>

        <div class="modal-panel" id="panelLogin">
            <h2>Login / Enter</h2>
            <p class="modal-sub">Enter your username or phone number to continue.</p>
            <form id="loginForm">
                <label>Username or Phone</label>
                <input type="text" id="loginUsername" autocomplete="username" required>
                <label>Password</label>
                <input type="password" id="loginPassword" autocomplete="current-password" required>
                <div class="form-error" id="loginError"></div>
                <button type="submit" class="btn btn-primary btn-block">Continue</button>
            </form>
        </div>

        <div class="modal-panel" id="panelNoUser" hidden>
            <h2>No account found</h2>
            <p class="modal-sub">There's no account for <strong id="noUserName"></strong>. Create a new account with this username?</p>
            <div class="form-actions">
                <button class="btn btn-ghost" id="noUserCancel">Cancel</button>
                <button class="btn btn-primary" id="noUserCreate">Create Account</button>
            </div>
        </div>

        <div class="modal-panel" id="panelRegister" hidden>
            <h2>Create Account</h2>
            <p class="modal-sub">Just a few details to get you started.</p>
            <form id="registerForm">
                <label>Display Name</label>
                <input type="text" id="regDisplayName">
                <label>Username or Phone</label>
                <input type="text" id="regUsername" required>
                <label>Password</label>
                <input type="password" id="regPassword" autocomplete="new-password" required>
                <div class="form-error" id="registerError"></div>
                <button type="submit" class="btn btn-primary btn-block">Create Account &amp; Enter</button>
            </form>
        </div>
    </div>
</div>

<script>window.APP_BASE = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
