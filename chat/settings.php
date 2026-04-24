<?php
session_start();
require_once 'db.php';

if (!isLoggedIn()) { header('Location: index.php'); exit; }

$user = getCurrentUser();
$msg = '';
$isSetup = isset($_GET['setup']);

if ($isSetup && empty($user['pin'])) {
    $msg = 'Please set a 4-digit PIN to secure your messages before continuing.';
}

$action = $_POST['action'] ?? '';
if ($action) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid CSRF token';
    } else {
        if ($action === 'profile') {
            $name = sanitize($_POST['name'] ?? '');
            $emoji = sanitize($_POST['emoji'] ?? '');
            if ($name) {
                getDB()->prepare("UPDATE users SET display_name = ?, avatar_emoji = ? WHERE id = ?")->execute([$name, $emoji, $_SESSION['user_id']]);
                $msg = 'Profile updated!';
                $user = getCurrentUser(); 
            }
        }
        if ($action === 'pin') {
            $old = $_POST['old'] ?? '';
            $new = $_POST['new'] ?? '';
            $confirm = $_POST['confirm'] ?? '';
            $validOld = empty($user['pin']) ? true : isPinValid($_SESSION['user_id'], $old);
            if (!$validOld) $msg = 'Wrong current PIN';
            elseif ($new !== $confirm) $msg = 'New PINs do not match';
            elseif (strlen($new) < 4) $msg = 'PIN must be at least 4 characters';
            else {
                updateUserPin($_SESSION['user_id'], $new);
                if ($isSetup) { header('Location: users.php'); exit; }
                $msg = 'PIN updated successfully!';
                $user = getCurrentUser();
            }
        }
        if ($action === 'password') {
            $old = $_POST['old_pass'] ?? '';
            $new = $_POST['new_pass'] ?? '';
            $confirm = $_POST['confirm_pass'] ?? '';
            if (!password_verify($old, $user['password'])) $msg = 'Wrong current password';
            elseif ($new !== $confirm) $msg = 'New passwords do not match';
            elseif (strlen($new) < 6) $msg = 'Password must be at least 6 characters';
            else {
                getDB()->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
                $msg = 'Password updated successfully!';
                $user = getCurrentUser();
            }
        }
        if ($action === 'forget_pin') {
            vanishChats($_SESSION['user_id'], 0);
            deleteMyMessages($_SESSION['user_id'], false);
            updateUserPin($_SESSION['user_id'], '');
            $msg = 'PIN forgotten and all chats vanished!';
            $user = getCurrentUser();
        }
        if ($action === 'delete_all_messages') {
            deleteMyMessages($_SESSION['user_id'], false);
            $msg = 'All your messages deleted!';
        }
        if ($action === 'delete_all_images') {
            deleteMyMessages($_SESSION['user_id'], true);
            $msg = 'All your images deleted!';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, sans-serif; background: #ECE5DD; color: #333; }
        .header { background: #128C7E; color: #fff; padding: 15px; display: flex; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header h1 { font-size: 20px; flex: 1; text-align: center; }
        .container { padding: 20px; padding-bottom: 100px; max-width: 500px; margin: 0 auto; }
        .msg { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; border-left: 5px solid #128C7E; }
        .card { background: #fff; padding: 20px; border-radius: 15px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        h2 { color: #128C7E; margin-bottom: 20px; font-size: 16px; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: #667781; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 10px; font-size: 15px; outline: none; transition: 0.2s; }
        .form-group input:focus { border-color: #128C7E; }
        .emoji-picker { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-top: 10px; }
        .emoji-item { display: flex; align-items: center; justify-content: center; font-size: 28px; padding: 12px; border: 2px solid #f0f0f0; border-radius: 12px; cursor: pointer; transition: 0.2s; }
        .emoji-item.active { border-color: #128C7E; background: #e7f3f1; }
        .btn { padding: 14px; background: #128C7E; color: #fff; border: none; border-radius: 12px; cursor: pointer; width: 100%; font-size: 15px; font-weight: 700; transition: 0.2s; }
        .btn.danger { background: #dc3545; }
        .tab-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; display: flex; border-top: 1px solid #ddd; z-index: 100; padding: 10px 0; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); }
        .tab-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; color: #667781; cursor: pointer; text-decoration: none; }
        .tab-item.active { color: #128C7E; }
        .tab-icon { font-size: 24px; }
        .tab-label { font-size: 11px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Settings</h1>
    </div>
    
    <div class="container">
        <?php if ($msg): ?>
            <div class="msg"><?= $msg ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>PIN & Privacy</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="pin">
                <?php if (!empty($user['pin'])): ?>
                    <div class="form-group">
                        <label>Current PIN</label>
                        <input type="password" name="old" required>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label>New PIN (4+ chars)</label>
                    <input type="password" name="new" required maxlength="12">
                </div>
                <div class="form-group">
                    <label>Confirm PIN</label>
                    <input type="password" name="confirm" required maxlength="12">
                </div>
                <button type="submit" class="btn"><?= empty($user['pin']) ? 'Enable PIN' : 'Update PIN' ?></button>
                <?php if (!empty($user['pin'])): ?>
                    <div style="margin-top:15px;padding-top:15px;border-top:1px solid #eee;">
                        <p style="font-size:13px;color:#888;margin-bottom:10px;">Forgot your PIN? This will vanish all your chats for safety.</p>
                        <button type="button" class="btn danger" onclick="forgetPin()">Forget PIN</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h2>Change Password</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="password">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="old_pass" required>
                </div>
                <div class="form-group">
                    <label>New Password (6+ chars)</label>
                    <input type="password" name="new_pass" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_pass" required minlength="6">
                </div>
                <button type="submit" class="btn">Update Password</button>
            </form>
        </div>

        <div class="card">
            <h2>Profile</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="profile">
                <input type="hidden" name="emoji" id="selected-emoji" value="<?= sanitize($user['avatar_emoji']) ?>">
                <div class="form-group">
                    <label>Display Name</label>
                    <input type="text" name="name" value="<?= sanitize($user['display_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Emoji Avatar</label>
                    <div class="emoji-picker">
                        <?php foreach (DEFAULT_EMOJIS as $e): ?>
                            <div class="emoji-item <?= $user['avatar_emoji'] === $e ? 'active' : '' ?>" onclick="selectEmoji(this, '<?= $e ?>')"><?= $e ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn">Update Profile</button>
            </form>
        </div>

        <div class="card">
            <h2>Data Cleanup</h2>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <form method="POST" onsubmit="return confirm('Delete all your sent messages?')">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="delete_all_messages">
                    <button type="submit" class="btn danger">Delete My Sent Messages</button>
                </form>
                <form method="POST" onsubmit="return confirm('Delete all your sent images?')">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="delete_all_images">
                    <button type="submit" class="btn danger">Delete My Sent Images</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="tab-bar">
        <a href="users.php" class="tab-item">
            <span class="tab-icon">💬</span>
            <span class="tab-label">Chats</span>
        </a>
        <a href="settings.php" class="tab-item active">
            <span class="tab-icon">⚙️</span>
            <span class="tab-label">Settings</span>
        </a>
    </div>

    <script>
    function selectEmoji(el, emoji) {
        document.querySelectorAll('.emoji-item').forEach(i => i.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('selected-emoji').value = emoji;
    }
    function forgetPin() {
        if (!confirm('Vanish all chats and reset PIN?')) return;
        const f = document.createElement('form');
        f.method = 'POST';
        f.innerHTML = '<input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>"><input type="hidden" name="action" value="forget_pin">';
        document.body.appendChild(f);
        f.submit();
    }
    </script>
</body>
</html>