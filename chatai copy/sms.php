<?php
require_once 'auth.php';

$pdo = new PDO("sqlite:" . __DIR__ . "/chat_database.sq3");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$message = '';
$error = '';
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        // User 1 can save shared settings (API Key, Template, User 2 Enable)
        if ($user_id == 1) {
            $api_key = $_POST['api_key'] ?? '';
            $default_msg = $_POST['default_msg'] ?? '';
            $num1 = $_POST['sms_number_1'] ?? '';
            $num2 = $_POST['sms_number_2'] ?? '';
            $enabled2 = isset($_POST['sms_enabled_2']) ? '1' : '0';

            $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_api_key'")->execute([$api_key]);
            $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_default_msg'")->execute([$default_msg]);
            $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_number_1'")->execute([$num1]);
            $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_number_2'")->execute([$num2]);
            $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_enabled_2'")->execute([$enabled2]);
        } else {
            // User 2 can only save their own number
            $num = $_POST['sms_number'] ?? '';
            $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'sms_number_2'")->execute([$num]);
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
$sms_enabled = ($user_id == 1) ? '1' : ($settings['sms_enabled_' . $user_id] ?? '1');
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
                <?php if ($user_id != 1): ?>
                <div class="header-right">
                    <span style="font-size: 11px; padding: 4px 8px; border-radius: 6px; background: <?php echo $sms_enabled == '1' ? 'rgba(40,167,69,0.2)' : 'rgba(255,77,77,0.2)'; ?>; color: <?php echo $sms_enabled == '1' ? '#28a745' : '#ff4d4d'; ?>;">
                        Status: <?php echo $sms_enabled == '1' ? 'ACTIVE' : 'DISABLED'; ?>
                    </span>
                </div>
                <?php endif; ?>
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
                        <?php if ($user_id == 1): ?>
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">API Key (Shared)</label>
                            <input type="text" name="api_key" value="<?php echo htmlspecialchars($api_key); ?>" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none;">
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($user_id == 1): ?>
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">User 1 Phone Number</label>
                                <input type="text" name="sms_number_1" value="<?php echo htmlspecialchars($settings['sms_number_1'] ?? ''); ?>" placeholder="8801800000000" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none;">
                            </div>
                            <div style="margin-bottom: 20px; padding: 15px; border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; background: rgba(255,255,255,0.02);">
                                <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">User 2 Phone Number</label>
                                <input type="text" name="sms_number_2" value="<?php echo htmlspecialchars($settings['sms_number_2'] ?? ''); ?>" placeholder="8801800000000" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none; margin-bottom: 12px;">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" name="sms_enabled_2" value="1" <?php echo ($settings['sms_enabled_2'] ?? '1') == '1' ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                                    <span style="font-size: 14px; color: var(--gemini-text);">Enable SMS Service for User 2</span>
                                </label>
                            </div>
                        <?php else: ?>
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">Your Phone Number</label>
                                <input type="text" name="sms_number" value="<?php echo htmlspecialchars($settings['sms_number_' . $user_id] ?? ''); ?>" placeholder="8801800000000" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none;">
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($user_id == 1): ?>
                        <div style="margin-bottom: 30px;">
                            <label style="display: block; margin-bottom: 8px; color: var(--gemini-dim); font-size: 13px;">Default Knock Template (Shared)</label>
                            <input type="text" name="default_msg" value="<?php echo htmlspecialchars($default_msg); ?>" style="width: 100%; padding: 12px 16px; background: var(--gemini-input); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: var(--gemini-text); font-size: 14px; outline: none;">
                        </div>
                        <?php endif; ?>
                        
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
                    <h2 style="font-size: 18px; margin-bottom: 20px; color: var(--gemini-dim); display: flex; align-items: center; gap: 10px;">
                        <span>📋</span> SMS Transaction History
                    </h2>
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
                        <div style="background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); padding: 30px; border-radius: 20px; text-align: center; color: var(--gemini-dim);">
                            No SMS transactions found in history.
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach ($history as $sms): 
                            $isSent = $sms['status'] === 'sent';
                            $statusBg = $isSent ? 'rgba(40,167,69,0.1)' : 'rgba(255,77,77,0.1)';
                            $statusColor = $isSent ? '#28a745' : '#ff4d4d';
                        ?>
                            <div style="background: var(--glass-bg); border: 1px solid var(--glass-border); padding: 18px; border-radius: 18px; position: relative; transition: transform 0.2s;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <span style="color: var(--gemini-blue); font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($sms['phone_number']); ?></span>
                                        <span style="color: var(--gemini-dim); font-size: 11px; font-family: 'Roboto Mono', monospace;">
                                            <?php echo date('d M Y • h:i A', strtotime($sms['sent_at'])); ?>
                                        </span>
                                    </div>
                                    <div style="padding: 4px 10px; border-radius: 20px; background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <?php echo $sms['status']; ?>
                                    </div>
                                </div>
                                
                                <div style="color: rgba(255,255,255,0.9); font-size: 13px; line-height: 1.5; background: rgba(0,0,0,0.2); padding: 12px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.03);">
                                    <?php echo htmlspecialchars($sms['message']); ?>
                                </div>

                                <?php if ($sms['error_msg']): ?>
                                    <div style="color: #ff4d4d; font-size: 11px; margin-top: 10px; padding: 8px 12px; background: rgba(255,77,77,0.05); border-left: 2px solid #ff4d4d; border-radius: 4px;">
                                        <strong>Error:</strong> <?php echo htmlspecialchars($sms['error_msg']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($user_id == 1): ?>
                                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                                        <span style="font-size: 10px; color: var(--gemini-dim);">Initiated by User #<?php echo $sms['user_id']; ?></span>
                                        <span style="font-size: 10px; color: var(--gemini-dim);">ID: <?php echo $sms['id']; ?></span>
                                    </div>
                                <?php endif; ?>
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
