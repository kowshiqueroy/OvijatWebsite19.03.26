<?php
require_once 'auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || $token !== $_SESSION['csrf_token']) {
        $error = "CSRF validation failed";
    } elseif (login($_POST['username'], $_POST['password'])) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Gemini - Sign In</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✦</text></svg>">
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-branding">
            <div class="gemini-logo-large">✦</div>
            <div class="gemini-ai-text">Gemini <span>AI</span></div>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✦</text></svg>">
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
</head>
<body class="app-page">
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <div class="app-container">
        <!-- Sidebar -->
        <aside id="sidebar">
            <div class="sidebar-header">
                <div class="gemini-logo-small"></div>
                <span>Gemini</span>
                <button class="sidebar-close" id="sidebar-close">✕</button>
            </div>
            <nav>
                <div class="nav-section">
                    <p class="section-title">Recent</p>
                    <div id="fake-chats"></div>
                    <div id="sidebar-protected-links" style="display:none;">
                        <a href="youtube.php" class="nav-item"><span>📺</span> Shared YouTube</a>
                        <?php if ($user_id != 2): ?><a href="saved-images.php" class="nav-item"><span>💎</span> Premium Vault</a><?php endif; ?>
                    </div>
                </div>
            </nav>
            <div class="nav-footer">
                <button class="nav-item" id="delete-unseen-btn" onclick="deleteMyUnseen()" style="display:none; color: #ff9800;">
                    <span>🧹</span> Delete My Unseen
                </button>
                <button class="nav-item" id="delete-sent-btn" onclick="deleteMySentMessages()" style="display:none; color: #ff9800;">
                    <span>🗑️</span> Delete My Sent Msgs
                </button>
                <button class="nav-item" onclick="showModal('pin-modal')">
                    <span>🔑</span> Update PIN
                </button>
                <button class="nav-item" id="enable-notifications">
                    <span>🔔</span> Enable Notifications
                </button>
                <button class="nav-item" id="toggle-background-mode" onclick="toggleBackgroundMode()">
                    <span>🔋</span> Stay Active (Background): OFF
                </button>
                <button class="nav-item" onclick="toggleDebugConsole()">
                    <span>🛠️</span> Toggle Debug Console
                </button>
                <button class="nav-item" onclick="clearAppCache()">
                    <span>🧹</span> Clear App Cache
                </button>

                <button class="nav-item" onclick="resetPIN()">
                    <span>🔄</span> Reset PIN & Wipe
                </button>
                <button class="nav-item" onclick="showModal('nuclear-modal')" style="color: #ff4d4d;">
                    <span>☢️</span> Total Nuclear Wipe
                </button>
                <button class="nav-item" onclick="burnYTComments()">
                    <span>🔥</span> Burn YT Comments
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
                    <span style="font-size: 24px; font-weight: 600;">Gemini</span>
                    <span class="sparkle-logo" id="panic-logo" style="font-size: 26px; margin-left: 6px;">✦</span>
                </div>
                <div class="header-right">
                    <a href="upload-media.php" id="upload-media-btn" class="upload-media-btn" style="display:none;background:none;border:none;font-size:20px;cursor:pointer;padding:6px 10px;text-decoration:none;margin-right:8px;background:linear-gradient(135deg,#4285f4,#ea4335,#fbbc05,#34a853);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;background-size:200% 200%;animation:gradientShift 3s ease infinite;">✦</a>
                    <div class="ai-pro-badge">AI PRO</div>
                    <div class="timer-container">
                        <svg viewBox="0 0 34 34"><circle class="bg" cx="17" cy="17" r="14"/><circle class="progress" id="timer-progress" cx="17" cy="17" r="14"/></svg>
                        <button class="timer-btn" id="lock-status">L</button>
                    </div>
                </div>
            </header>

            <div id="notification-area" class="notification-area"></div>

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
                    <div id="system-logs" class="status-log"></div>
                </div>
            </div>

            <div class="input-outer">
                <div class="input-wrapper">
                    <div class="input-actions-left">
                        <button id="attach-btn" title="Attach Media" class="input-action-btn">📎</button>
                        <button id="camera-btn" title="Open Camera" class="input-action-btn">📸</button>
                        <button id="mic-btn" title="Record Voice" class="input-action-btn">🎤</button>
                    </div>
                    <span id="recording-status" style="display:none; color: #ff4d4d; font-size: 12px; font-weight: 600; padding-bottom: 12px;"><span id="recording-time">0:00</span></span>
                    <input type="text" id="message-input" placeholder="Type a message..." autocomplete="off">
                    <button id="view-text-btn" title="Peek Text" class="input-action-btn" style="padding-bottom: 10px;">👁️</button>
                    <button id="send-btn" class="send-action-btn">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </div>
                <input type="file" id="file-input" accept="image/*,video/*" style="display:none">
                <input type="file" id="camera-input" accept="image/*,video/*" capture="camera" style="display:none">
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div id="upload-choice-modal" class="modal">
        <div class="modal-content glass">
            <h2>Send Media</h2>
            <p>Choose a destination for your file. Vault is recommended for sensitive content.</p>
            <div class="modal-buttons">
                <button onclick="confirmUploadDestination('vault')" class="modern-btn btn-gradient-blue">
                    <span>💎</span> Add to Vault
                </button>
                <button onclick="confirmUploadDestination('chat')" class="modern-btn btn-outline">
                    <span>💬</span> Send to Chat
                </button>
                <button onclick="closeModal('upload-choice-modal')" class="modern-btn btn-danger-outline">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <div id="audio-confirm-modal" class="modal">
        <div class="modal-content glass">
            <h2>Voice Message</h2>
            <p>Review your recording before sending it to the Gemini team.</p>
            <div id="audio-preview-container" style="margin: 20px 0; width:100%; background: rgba(255,255,255,0.03); padding: 12px; border-radius: 16px;"></div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <button id="btn-confirm-audio" class="modern-btn btn-gradient-green">
                    <span>🚀</span> Send
                </button>
                <button id="btn-cancel-audio" class="modern-btn btn-danger-outline">
                    Discard
                </button>
            </div>
        </div>
    </div>

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
            <p style="font-size: 14px; color: var(--gemini-dim); margin-bottom: 25px; line-height: 1.5;">This action will permanently DELETE all messages and set a new access PIN. <br><br><strong>Note:</strong> Premium Vault data and saved images are NOT affected.</p>
            <div class="input-group">
                <input type="text" id="reset-new-pin" placeholder="New PIN (4 digits)" maxlength="4">
            </div>
            <div class="modal-buttons">
                <button class="btn-save" style="background:#ff4d4d;" onclick="confirmResetPIN()">Confirm Reset & Wipe</button>
                <button class="btn-cancel" onclick="closeModal('reset-pin-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="nuclear-modal" class="modal">
        <div class="modal-content">
            <h2 style="color: #ff4d4d;">Total Nuclear Wipe</h2>
            <p style="font-size: 14px; color: var(--gemini-dim); margin-bottom: 25px; line-height: 1.5;">WARNING: This will permanently delete ALL messages and temporary uploads. This action cannot be undone. <br><br><strong>Note:</strong> Premium Vault files and the saved images database are EXEMPT and will be preserved.</p>
            <div class="input-group">
                <input type="text" id="nuclear-pin" placeholder="Enter Current PIN to Confirm" maxlength="4">
            </div>
            <div class="modal-buttons">
                <button class="btn-save" style="background:#ff4d4d;" onclick="confirmNuclearWipe()">WIPE EVERYTHING</button>
                <button class="btn-cancel" onclick="closeModal('nuclear-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const CURRENT_USER_ID = <?php echo $_SESSION['user_id']; ?>;
        const OTHER_USER_ID = (CURRENT_USER_ID === 1) ? 2 : 1;
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        window.CSRF_TOKEN = CSRF_TOKEN;

        // Heartbeat to keep user online and clear theater status
        setInterval(() => {
            const blob = new Blob([JSON.stringify({ is_typing: 0, in_theater: 0 })], { type: 'application/json' });
            navigator.sendBeacon(`api.php?action=update_status&_csrf=${CSRF_TOKEN}`, blob);
        }, 5000);

        // Update notification button status
        if ("Notification" in window) {
            if (typeof updateNotificationButton === 'function') {
                updateNotificationButton();
            } else {
                // If script.js isn't loaded yet, wait for it
                window.addEventListener('load', () => {
                    if (typeof updateNotificationButton === 'function') updateNotificationButton();
                });
            }
        }
    </script>

    <script src="script.js?v=<?php echo filemtime(__DIR__ . '/script.js'); ?>"></script>
    <script>
        // Auto-focus input on mobile to open keyboard
        if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            window.addEventListener('load', () => {
                setTimeout(() => {
                    document.getElementById('message-input').focus();
                }, 300);
            });
        }
    </script>
</body>
</html>
<?php endif; ?>
