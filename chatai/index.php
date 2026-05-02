<?php
require_once 'auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (login($_POST['username'], $_POST['password'])) {
        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid credentials";
    }
}

if (!isset($_SESSION['user_id'])):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gemini - Sign In</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-branding">
            <div class="gemini-logo-large">✦</div>
            <div class="gemini-ai-text">Gemini <span>AI</span></div>
        </div>
        <h1>Sign in</h1>
        <form method="POST">
            <div class="input-group">
                <input type="text" name="username" placeholder="Email or phone" required autocomplete="off">
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <?php if ($error): ?><p class="error"><?php echo $error; ?></p><?php endif; ?>
            <button type="submit" name="login" class="btn-primary">Next</button>
        </form>
    </div>
</body>
</html>
<?php else: 
$user_id = $_SESSION['user_id'];
$other_user_id = ($user_id == 1) ? 2 : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Gemini</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="app-page">
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <div class="app-container">
        <!-- Sidebar -->
        <aside id="sidebar">
            <div class="sidebar-header">
                <div class="gemini-logo-small"></div>
                <span>Gemini</span>
            </div>
            <nav>
                <div class="nav-section">
                    <p class="section-title">Recent</p>
                    <div id="fake-chats"></div>
                </div>
            </nav>
            <div class="nav-footer">
                <button class="nav-item" onclick="showModal('password-modal')">
                    <span>🔒</span> Update Password
                </button>
                <button class="nav-item" onclick="showModal('pin-modal')">
                    <span>🔑</span> Update PIN
                </button>
                <button class="nav-item" onclick="resetPIN()">
                    <span>☢️</span> Reset PIN & Wipe
                </button>
                <a href="auth.php?action=logout" class="nav-item logout">
                    <span>🚪</span> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main>
            <header>
                <div class="header-left">
                    <button id="menu-toggle">☰</button>
                    Gemini <span class="sparkle-logo" id="panic-logo">✦</span>
                </div>
                <div class="header-right">
                    <div class="ai-pro-text">AI Pro</div>
                    <div class="timer-container">
                        <svg viewBox="0 0 34 34"><circle class="bg" cx="17" cy="17" r="14"/><circle class="progress" id="timer-progress" cx="17" cy="17" r="14"/></svg>
                        <button class="timer-btn" id="lock-status">L</button>
                    </div>
                </div>
            </header>

            <div id="chat-window">
                <div id="chat-inner">
                    <div id="messages-container"></div>
                </div>
            </div>

            <div class="thinking-area">
                <div class="thinking-sparkle active" id="thinking-sparkle">✦</div>
                <div class="thinking-content">
                    <div class="thinking-main-text" id="thinking-text">Gemini is thinking...</div>
                    <div class="shimmer-line"></div>
                    <div id="system-logs" class="status-log">[Nodes_Synchronized]</div>
                </div>
            </div>

            <div class="input-outer">
                <div class="input-wrapper">
                    <button id="attach-btn" title="Attach Image">🖼️</button>
                    <input type="text" id="message-input" placeholder="Enter a prompt here" autocomplete="off">
                    <button id="view-text-btn" title="Peek Text">👁️</button>
                    <button id="send-btn">➤</button>
                </div>
                <input type="file" id="file-input" accept="image/*" style="display:none">
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div id="password-modal" class="modal">
        <div class="modal-content">
            <h2>Update Password</h2>
            <div class="input-group">
                <input type="password" id="old-password" placeholder="Old Password">
            </div>
            <div class="input-group">
                <input type="password" id="new-password" placeholder="New Password">
            </div>
            <div class="modal-buttons">
                <button class="btn-save" onclick="updatePassword()">Save Changes</button>
                <button class="btn-cancel" onclick="closeModal('password-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="pin-modal" class="modal">
        <div class="modal-content">
            <h2>Update PIN</h2>
            <div class="input-group">
                <input type="text" id="old-pin" placeholder="Old PIN (4 digits)" maxlength="4">
            </div>
            <div class="input-group">
                <input type="text" id="new-pin" placeholder="New PIN (4 digits)" maxlength="4">
            </div>
            <div class="modal-buttons">
                <button class="btn-save" onclick="updatePIN()">Update PIN</button>
                <button class="btn-cancel" onclick="closeModal('pin-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="reset-pin-modal" class="modal">
        <div class="modal-content">
            <h2>Reset & Wipe</h2>
            <p style="font-size: 14px; color: var(--gemini-dim); margin-bottom: 25px; line-height: 1.5;">This action will permanently DELETE all messages and set a new access PIN.</p>
            <div class="input-group">
                <input type="text" id="reset-new-pin" placeholder="New PIN (4 digits)" maxlength="4">
            </div>
            <div class="modal-buttons">
                <button class="btn-save" style="background:#ff4d4d;" onclick="confirmResetPIN()">Confirm Nuclear Wipe</button>
                <button class="btn-cancel" onclick="closeModal('reset-pin-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const CURRENT_USER_ID = <?php echo $_SESSION['user_id']; ?>;
        const OTHER_USER_ID = (CURRENT_USER_ID === 1) ? 2 : 1;
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        window.CSRF_TOKEN = CSRF_TOKEN;
    </script>
    <script src="script.js"></script>
</body>
</html>
<?php endif; ?>
