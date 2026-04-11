<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>System Settings</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div id="app">
        <div id="login-screen" class="screen">
            <div class="system-header">
                <div class="system-icon"></div>
                <h1>System Settings</h1>
                <p class="system-subtitle">Authenticate to continue</p>
            </div>
            
            <div class="auth-tabs">
                <button class="tab-btn active" data-tab="login">Login</button>
                <button class="tab-btn" data-tab="register">Register</button>
            </div>
            
            <form id="login-form" class="auth-form">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" autocomplete="username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-primary">Authenticate</button>
                <div class="auth-error" id="login-error"></div>
            </form>
            
            <form id="register-form" class="auth-form hidden">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" autocomplete="username" required>
                </div>
                <div class="form-group">
                    <label>Display Name</label>
                    <input type="text" name="display_name" autocomplete="name">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn-primary">Create Account</button>
                <div class="auth-error" id="register-error"></div>
            </form>
        </div>

        <div id="inbox-screen" class="screen hidden">
            <header class="system-nav">
                <div class="nav-brand">
                    <div class="system-icon small"></div>
                    <span>Settings</span>
                </div>
                <div class="nav-actions">
                    <button class="nav-btn" id="add-contact-btn">+</button>
                    <button class="nav-btn" id="logout-btn">⏻</button>
                </div>
            </header>
            
            <div class="inbox-camouflage">
                <div class="inbox-header">
                    <h2>System Configuration</h2>
                    <span class="badge">Active</span>
                </div>
                <div id="inbox-list" class="settings-list">
                    <div class="loading">Loading configurations...</div>
                </div>
            </div>
            
            <div class="lock-timer" id="lock-timer"></div>
        </div>

        <div id="chat-screen" class="screen hidden">
            <header class="system-nav">
                <button class="nav-btn back-btn" id="back-btn">←</button>
                <div class="nav-brand">
                    <span id="chat-contact-name">System Config</span>
                </div>
                <div class="nav-actions">
                    <button class="nav-btn" id="lock-thread-btn">⚙</button>
                </div>
            </header>
            
            <div id="messages-container" class="messages-camouflage">
                <div class="messages-list" id="messages-list"></div>
            </div>
            
            <div class="compose-area hidden" id="compose-area">
                <textarea id="message-input" placeholder="Enter configuration data..."></textarea>
                <button class="btn-send" id="send-btn">→</button>
            </div>
            
            <div class="reveal-overlay" id="reveal-overlay">
                <div class="revealed-content" id="revealed-content"></div>
                <div class="reveal-timer" id="reveal-timer"></div>
            </div>
        </div>

        <div id="modal-overlay" class="modal-overlay hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-title">Configure</h3>
                    <button class="modal-close" id="modal-close">×</button>
                </div>
                
                <div id="modal-body"></div>
            </div>
        </div>
    </div>

    <script src="app.js"></script>
</body>
</html>