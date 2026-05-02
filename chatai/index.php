<?php
require_once 'auth.php';

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $result = login($_POST['username'], $_POST['password']);
        if ($result['success']) {
            header("Location: index.php");
            exit();
        } else {
            $error = $result['error'];
        }
    } elseif (isset($_POST['register'])) {
        $result = register($_POST['username'], $_POST['display_name'], $_POST['password'], $_POST['pin']);
        if ($result['success']) {
            $message = "Registration successful! Please wait for admin approval.";
        } else {
            $error = $result['error'];
        }
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
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-branding">
            <div class="gemini-logo-large">✦</div>
            <div class="gemini-ai-text">Gemini <span>AI</span></div>
        </div>
        
        <div id="login-form">
            <h1>Sign in</h1>
            <form method="POST">
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username" required autocomplete="off">
                </div>
                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <?php if ($error): ?><p class="error"><?php echo $error; ?></p><?php endif; ?>
                <?php if ($message): ?><p style="color:#28a745; font-size:13px; margin-bottom:16px;"><?php echo $message; ?></p><?php endif; ?>
                <button type="submit" name="login" class="btn-primary">Sign In</button>
            </form>
            <p style="margin-top:20px; font-size:14px; color:var(--gemini-dim);">Don't have an account? <a href="#" onclick="toggleAuth()" style="color:var(--gemini-blue); text-decoration:none;">Create account</a></p>
        </div>

        <div id="register-form" style="display:none;">
            <h1>Create account</h1>
            <form method="POST">
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username" required autocomplete="off">
                </div>
                <div class="input-group">
                    <input type="text" name="display_name" placeholder="Display Name" required autocomplete="off">
                </div>
                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <div class="input-group">
                    <input type="text" name="pin" placeholder="Access PIN (4 digits)" required maxlength="4" pattern="\d{4}">
                </div>
                <button type="submit" name="register" class="btn-primary">Register</button>
            </form>
            <p style="margin-top:20px; font-size:14px; color:var(--gemini-dim);">Already have an account? <a href="#" onclick="toggleAuth()" style="color:var(--gemini-blue); text-decoration:none;">Sign in</a></p>
        </div>
    </div>
    <script>
        function toggleAuth() {
            const login = document.getElementById('login-form');
            const register = document.getElementById('register-form');
            if (login.style.display === 'none') {
                login.style.display = 'block';
                register.style.display = 'none';
            } else {
                login.style.display = 'none';
                register.style.display = 'block';
            }
        }
    </script>
</body>
</html>
<?php else: ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Gemini</title>
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
                    <div style="display:flex; justify-content:space-between; align-items:center; padding: 10px 15px;">
                        <p class="section-title" style="padding:0;">Conversations</p>
                        <button onclick="showModal('create-room-modal')" style="background:none; border:none; color:var(--gemini-blue); cursor:pointer; font-size:18px;">+</button>
                    </div>
                    <div id="room-list"></div>
                </div>
                <div class="nav-section">
                    <p class="section-title">Menu</p>
                    <a href="sms.php" class="nav-item"><span>📱</span> SMS Settings</a>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <button class="nav-item" onclick="showModal('admin-modal')"><span>🛠️</span> Admin Dashboard</button>
                    <?php endif; ?>
                    <button class="nav-item" onclick="showModal('settings-modal')"><span>⚙️</span> Privacy Settings</button>
                    <button class="nav-item" onclick="showModal('profile-modal')"><span>👤</span> My Profile</button>
                </div>
            </nav>
            <div class="nav-footer">
                <div style="padding: 10px 15px; font-size:12px; color:var(--gemini-dim);">
                    Signed in as <strong id="sidebar-display-name"><?php echo htmlspecialchars($_SESSION['display_name']); ?></strong>
                </div>
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
                    <span id="active-room-name">Gemini</span> <span class="sparkle-logo" id="panic-logo">✦</span>
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
                    <button id="knock-btn" title="Send Knock SMS">📱</button>
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
    <div id="settings-modal" class="modal">
        <div class="modal-content" style="max-width: 500px; text-align:left;">
            <h2 style="text-align:center;">Privacy Settings</h2>
            <div class="settings-group" style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:var(--gemini-dim); font-size:13px;">Auto-Lock Timer (seconds)</label>
                <input type="number" id="setting-lock-timer" value="60" style="width:100%; padding:10px; background:var(--gemini-input); border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:#fff;">
            </div>
            <div class="settings-group" style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:var(--gemini-dim); font-size:13px;">Camouflage Theme</label>
                <select id="setting-theme" style="width:100%; padding:10px; background:var(--gemini-input); border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:#fff;">
                    <option value="coding">Coding (Default)</option>
                    <option value="physics">Physics</option>
                    <option value="games">Games</option>
                    <option value="beauty">Beauty</option>
                </select>
            </div>
            <div class="settings-group" style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:var(--gemini-dim); font-size:13px;">Reveal Duration on Arrival (sec)</label>
                <input type="number" id="setting-arrival-dur" value="0" style="width:100%; padding:10px; background:var(--gemini-input); border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:#fff;">
            </div>
            <div class="settings-group" style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:var(--gemini-dim); font-size:13px;">Camouflage Reveal Style</label>
                <select id="setting-style" style="width:100%; padding:10px; background:var(--gemini-input); border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:#fff;">
                    <option value="none">Plain Text</option>
                    <option value="c_code">Monospace / C-Code</option>
                    <option value="dummy">Dummy Text (Random)</option>
                </select>
            </div>
            <div class="modal-buttons">
                <button class="btn-save" onclick="saveSettings()">Save Settings</button>
                <button class="btn-cancel" onclick="closeModal('settings-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="profile-modal" class="modal">
        <div class="modal-content" style="max-width: 450px; text-align:left;">
            <h2 style="text-align:center;">My Profile</h2>
            
            <div class="settings-group" style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:var(--gemini-dim); font-size:13px;">Display Name</label>
                <input type="text" id="prof-display-name" style="width:100%; padding:10px; background:var(--gemini-input); border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:#fff;">
                <button class="btn-save" onclick="updateProfile()" style="margin-top:10px; padding:8px 15px; font-size:12px; width:auto;">Update Name</button>
            </div>

            <hr style="border:0; border-top:1px solid rgba(255,255,255,0.05); margin:20px 0;">
            
            <div class="settings-group" style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:var(--gemini-dim); font-size:13px;">Change Password</label>
                <input type="password" id="prof-old-pass" placeholder="Old Password" style="width:100%; padding:10px; background:var(--gemini-input); border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:#fff; margin-bottom:10px;">
                <input type="password" id="prof-new-pass" placeholder="New Password" style="width:100%; padding:10px; background:var(--gemini-input); border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:#fff;">
                <button class="btn-save" onclick="updatePassword()" style="margin-top:10px; padding:8px 15px; font-size:12px; width:auto; background:#d96570;">Change Password</button>
            </div>

            <hr style="border:0; border-top:1px solid rgba(255,255,255,0.05); margin:20px 0;">

            <div class="settings-group" style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; color:var(--gemini-dim); font-size:13px;">Change Access PIN</label>
                <input type="text" id="prof-old-pin" placeholder="Old PIN (4 digits)" maxlength="4" style="width:100%; padding:10px; background:var(--gemini-input); border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:#fff; margin-bottom:10px;">
                <input type="text" id="prof-new-pin" placeholder="New PIN (4 digits)" maxlength="4" style="width:100%; padding:10px; background:var(--gemini-input); border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:#fff;">
                <button class="btn-save" onclick="updatePIN()" style="margin-top:10px; padding:8px 15px; font-size:12px; width:auto; background:#9b72cb;">Change PIN</button>
            </div>

            <div class="modal-buttons" style="margin-top:20px;">
                <button class="btn-cancel" onclick="closeModal('profile-modal')">Close</button>
            </div>
        </div>
    </div>

    <div id="create-room-modal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <h2>New Conversation</h2>
            <div class="input-group">
                <input type="text" id="new-room-user" placeholder="Search username..." oninput="searchUsers(this.value)" autocomplete="off">
            </div>
            <div id="user-search-results" style="max-height: 200px; overflow-y: auto; margin-bottom: 20px; border-radius: 8px; background: rgba(0,0,0,0.2);"></div>
            <div id="selected-users" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px;"></div>
            <div class="modal-buttons">
                <button class="btn-save" onclick="createRoom()">Start Chat</button>
                <button class="btn-cancel" onclick="closeModal('create-room-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div id="admin-modal" class="modal">
        <div class="modal-content" style="max-width: 800px; width:95%; height:80vh; overflow-y:auto; text-align:left;">
            <h2 style="text-align:center;">Admin Dashboard</h2>
            <div id="admin-user-list"></div>
            <hr style="border:0; border-top:1px solid rgba(255,255,255,0.1); margin:20px 0;">
            <h3>Camouflage Library</h3>
            <div id="admin-cam-list"></div>
            <div class="modal-buttons">
                <button class="btn-cancel" onclick="closeModal('admin-modal')">Close</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        window.CSRF_TOKEN = CSRF_TOKEN;
    </script>
    <script src="script.js?v=<?php echo filemtime(__DIR__ . '/script.js'); ?>"></script>
</body>
</html>
<?php endif; ?>
