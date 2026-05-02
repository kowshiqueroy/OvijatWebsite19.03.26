<?php
require_once 'auth.php';

$pdo = new PDO("sqlite:" . __DIR__ . "/chat_database.sq3");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$message = '';
$error = '';
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        $api_key = $_POST['api_key'] ?? '';
        $default_msg = $_POST['default_msg'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_api_key'");
        $stmt->execute([$api_key]);
        
        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_default_msg'");
        $stmt->execute([$default_msg]);

        if ($user_id == 1) {
            $num1 = $_POST['sms_number_1'] ?? '';
            $num2 = $_POST['sms_number_2'] ?? '';
            $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_number_1'");
            $stmt->execute([$num1]);
            $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_number_2'");
            $stmt->execute([$num2]);
        } else {
            $num = $_POST['sms_number'] ?? '';
            $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_number_' || ?");
            $stmt->execute([$num, $user_id]);
        }
        
        $message = "Settings saved successfully!";
    } elseif (isset($_POST['send_custom_sms']) && $user_id == 1) {
        $api_key_stmt = $pdo->query("SELECT value FROM settings WHERE key = 'sms_api_key'");
        $api_key = $api_key_stmt->fetchColumn();
        
        $target_numbers = $_POST['custom_numbers'] ?? '';
        $custom_msg = $_POST['custom_message'] ?? '';
        
        if (empty($api_key)) {
            $error = "API Key not configured!";
        } elseif (empty($target_numbers) || empty($custom_msg)) {
            $error = "Target number(s) and message are required!";
        } else {
            $numbers = explode(',', $target_numbers);
            $sent_count = 0;
            $fail_count = 0;

            foreach ($numbers as $num) {
                $num = trim($num);
                if (empty($num)) continue;

                $url = 'https://api.sms.net.bd/sendsms?api_key=' . urlencode($api_key) . '&msg=' . urlencode($custom_msg) . '&to=' . urlencode($num);
                
                $context = stream_context_create([
                    'http' => ['timeout' => 10, 'ignore_errors' => true],
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
                ]);
                
                $response = @file_get_contents($url, false, $context);
                if ($response === FALSE) {
                    $err_info = error_get_last();
                    $stmt = $pdo->prepare("INSERT INTO sms_history (user_id, phone_number, message, status, error_msg) VALUES (?, ?, ?, 'failed', ?)");
                    $stmt->execute([$user_id, $num, $custom_msg, $err_info['message'] ?? 'Connection failed']);
                    $fail_count++;
                } else {
                    $result = json_decode($response, true);
                    if (isset($result['error']) && $result['error'] == 0) {
                        $stmt = $pdo->prepare("INSERT INTO sms_history (user_id, phone_number, message, status) VALUES (?, ?, ?, 'sent')");
                        $stmt->execute([$user_id, $num, $custom_msg]);
                        $sent_count++;
                    } else {
                        $err_msg = $result['msg'] ?? 'API error';
                        $stmt = $pdo->prepare("INSERT INTO sms_history (user_id, phone_number, message, status, error_msg) VALUES (?, ?, ?, 'failed', ?)");
                        $stmt->execute([$user_id, $num, $custom_msg, $err_msg]);
                        $fail_count++;
                    }
                }
            }
            $message = "SMS Processed: $sent_count sent, $fail_count failed.";
        }
    }
}

// Get current settings
$settings = [];
$stmt = $pdo->query("SELECT key, value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row['value'];
}

$api_key = $settings['sms_api_key'] ?? '';
$default_msg = $settings['sms_default_msg'] ?? 'Knock knock! Check the chat.';
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
                <?php if ($message): ?>
                    <div style="background: rgba(40,167,69,0.15); border: 1px solid rgba(40,167,69,0.3); padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; color: #28a745;">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div style="background: rgba(255,77,77,0.15); border: 1px solid rgba(255,77,77,0.3); padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; color: #ff4d4d;">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <section style="margin-bottom: 40px;">
                    <h2 style="font-size: 18px; margin-bottom: 20px; color: var(--gemini-blue);">Configuration</h2>
                    <form method="POST">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">API Key (Shared)</label>
                            <input type="text" name="api_key" value="<?php echo htmlspecialchars($api_key); ?>" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none;">
                        </div>
                        
                        <?php if ($user_id == 1): ?>
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">User 1 Phone Number</label>
                                <input type="text" name="sms_number_1" value="<?php echo htmlspecialchars($settings['sms_number_1'] ?? ''); ?>" placeholder="8801800000000" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none;">
                            </div>
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">User 2 Phone Number</label>
                                <input type="text" name="sms_number_2" value="<?php echo htmlspecialchars($settings['sms_number_2'] ?? ''); ?>" placeholder="8801800000000" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none;">
                            </div>
                        <?php else: ?>
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">Your Phone Number</label>
                                <input type="text" name="sms_number" value="<?php echo htmlspecialchars($settings['sms_number_' . $user_id] ?? ''); ?>" placeholder="8801800000000" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none;">
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-bottom: 30px;">
                            <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">Default Knock Template (Shared)</label>
                            <input type="text" name="default_msg" value="<?php echo htmlspecialchars($default_msg); ?>" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none;">
                        </div>
                        
                        <button type="submit" name="save_settings" class="btn-primary" style="width: 100%;">Save All Settings</button>
                    </form>
                </section>

                <?php if ($user_id == 1): ?>
                <section style="margin-bottom: 40px; padding: 25px; background: rgba(66, 133, 244, 0.05); border: 1px solid rgba(66, 133, 244, 0.1); border-radius: 20px;">
                    <h2 style="font-size: 18px; margin-bottom: 20px; color: var(--gemini-blue);">Send Custom SMS</h2>
                    <form method="POST">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">Phone Numbers (Comma separated)</label>
                            <input type="text" name="custom_numbers" placeholder="88017..., 88018..." style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none;">
                        </div>
                        <div style="margin-bottom: 25px;">
                            <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">Custom Message</label>
                            <textarea name="custom_message" rows="3" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none; resize: none; font-family: inherit;"></textarea>
                        </div>
                        <button type="submit" name="send_custom_sms" class="btn-primary" style="background: #34a853;">Send Now</button>
                    </form>
                </section>
                <?php endif; ?>
                
                <section>
                    <h2 style="font-size: 18px; margin-bottom: 16px;">SMS History</h2>
                    <?php
                    $history_query = ($user_id == 1) 
                        ? "SELECT * FROM sms_history ORDER BY sent_at DESC LIMIT 50"
                        : "SELECT * FROM sms_history WHERE user_id = ? ORDER BY sent_at DESC LIMIT 20";
                    
                    $stmt = $pdo->prepare($history_query);
                    if ($user_id != 1) {
                        $stmt->execute([$user_id]);
                    } else {
                        $stmt->execute();
                    }
                    $history = $stmt->fetchAll();
                    
                    if (empty($history)): ?>
                        <p style="color: var(--gemini-dim); font-size: 14px;">No SMS history yet.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($history as $sms): ?>
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 16px; border-radius: 12px; font-size: 13px; position: relative;">
                                <?php if ($user_id == 1): ?>
                                    <span style="position: absolute; top: 16px; right: 16px; font-size: 10px; color: var(--gemini-dim);">User ID: <?php echo $sms['user_id']; ?></span>
                                <?php endif; ?>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="color: var(--gemini-blue); font-weight: 500;"><?php echo htmlspecialchars($sms['phone_number']); ?></span>
                                    <span style="color: <?php echo $sms['status'] === 'sent' ? '#28a745' : '#ff4d4d'; ?>; font-weight: 500; font-size: 11px;">
                                        ● <?php echo strtoupper($sms['status']); ?>
                                    </span>
                                </div>
                                <div style="color: rgba(255,255,255,0.8); margin-bottom: 8px; line-height: 1.4;"><?php echo htmlspecialchars($sms['message']); ?></div>
                                <?php if ($sms['error_msg']): ?>
                                    <div style="color: #ff4d4d; font-size: 12px; background: rgba(255,77,77,0.05); padding: 8px; border-radius: 6px; margin-top: 5px;"><?php echo htmlspecialchars($sms['error_msg']); ?></div>
                                <?php endif; ?>
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
