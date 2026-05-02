<?php
require_once 'auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        $api_key = $_POST['api_key'] ?? '';
        $default_msg = $_POST['default_msg'] ?? '';
        
        $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_api_key'")->execute([$api_key]);
        $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_default_msg'")->execute([$default_msg]);

        if ($is_admin) {
            // Admin can save specific user numbers if they want (simplified here)
        }
        
        $message = "Global settings saved successfully!";
    }
}

// Get current settings
$settings = [];
$stmt = $pdo->query("SELECT key, value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row['value'];
}

$api_key = $settings['sms_api_key'] ?? '';
$default_msg = $settings['sms_default_msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>SMS Settings - Gemini</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
</head>
<body class="app-page">
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <div class="app-container">
        <aside id="sidebar">
            <div class="sidebar-header">
                <div class="gemini-logo-small"></div>
                <span>Gemini</span>
                <button class="sidebar-close" id="sidebar-close">✕</button>
            </div>
            <nav>
                <div class="nav-section">
                    <p class="section-title">Menu</p>
                    <a href="index.php" class="nav-item"><span>💬</span> Chat</a>
                    <a href="sms.php" class="nav-item active"><span>📱</span> SMS Settings</a>
                </div>
            </nav>
            <div class="nav-footer">
                <a href="auth.php?action=logout" class="nav-item logout"><span>🚪</span> Logout</a>
            </div>
        </aside>
        
        <main>
            <header>
                <div class="header-left">
                    <button id="menu-toggle">☰</button>
                    SMS Settings
                </div>
            </header>
            
            <div style="padding: 20px; max-width: 600px; margin:0 auto; width: 100%; overflow-y: auto;">
                <?php if ($message): ?><div style="background:rgba(40,167,69,0.1); color:#28a745; padding:15px; border-radius:12px; margin-bottom:20px;"><?php echo $message; ?></div><?php endif; ?>
                
                <section style="margin-bottom: 40px;">
                    <h2 style="font-size: 18px; margin-bottom: 20px; color: var(--gemini-blue);">Configuration</h2>
                    <form method="POST">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">API Key (Shared)</label>
                            <input type="text" name="api_key" value="<?php echo htmlspecialchars($api_key); ?>" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none;">
                        </div>
                        <div style="margin-bottom: 30px;">
                            <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">Default Knock Template (Shared)</label>
                            <input type="text" name="default_msg" value="<?php echo htmlspecialchars($default_msg); ?>" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none;">
                        </div>
                        <button type="submit" name="save_settings" class="btn-primary" style="width: 100%;">Save Global Settings</button>
                    </form>
                </section>

                <section>
                    <h2 style="font-size: 18px; margin-bottom: 16px;">Personal SMS History</h2>
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM sms_history WHERE user_id = ? ORDER BY sent_at DESC LIMIT 20");
                    $stmt->execute([$user_id]);
                    $history = $stmt->fetchAll();
                    
                    if (empty($history)): ?>
                        <p style="color: var(--gemini-dim); font-size: 14px;">No personal SMS history yet.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($history as $sms): ?>
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 16px; border-radius: 12px; font-size: 13px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="color: var(--gemini-blue); font-weight: 500;"><?php echo htmlspecialchars($sms['phone_number']); ?></span>
                                    <span style="color: <?php echo $sms['status'] === 'sent' ? '#28a745' : '#ff4d4d'; ?>; font-weight: 500; font-size: 11px;">
                                        ● <?php echo strtoupper($sms['status']); ?>
                                    </span>
                                </div>
                                <div style="color: rgba(255,255,255,0.8); margin-bottom: 8px; line-height: 1.4;"><?php echo htmlspecialchars($sms['message']); ?></div>
                                <div style="color: var(--gemini-dim); font-size: 11px; margin-top: 8px; font-family: 'Roboto Mono', monospace;"><?php echo $sms['sent_at']; ?></div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
    
    <script>
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarClose = document.getElementById('sidebar-close');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        }
        
        function closeSidebar() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        }
        
        if (menuToggle) menuToggle.onclick = toggleSidebar;
        if (sidebarClose) sidebarClose.onclick = closeSidebar;
        if (sidebarOverlay) sidebarOverlay.onclick = closeSidebar;
    </script>
</body>
</html>
