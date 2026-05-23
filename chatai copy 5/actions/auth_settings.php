<?php
switch ($action) {
    case 'verify_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $pin = $data['pin'] ?? '';
        
        // Rate limiting check
        $stmt = $pdo->prepare('SELECT pin_attempts, last_lockout FROM user_status WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $status = $stmt->fetch();
        
        $now = time();
        if ($status && $status['pin_attempts'] >= 5 && ($now - $status['last_lockout']) < 300) {
            $remaining = 300 - ($now - $status['last_lockout']);
            echo json_encode(array('success' => false, 'error' => "Too many attempts. Try again in " . ceil($remaining/60) . " minutes."));
            break;
        }

        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute(array('pin_' . $user_id));
        $hash = $stmt->fetchColumn();
        
        if ($hash && password_verify($pin, $hash)) {
            $pdo->prepare('UPDATE user_status SET pin_attempts = 0 WHERE user_id = ?')->execute([$user_id]);
            echo json_encode(array('success' => true));
        } else {
            $pdo->prepare('UPDATE user_status SET pin_attempts = pin_attempts + 1, last_lockout = ? WHERE user_id = ?')->execute([$now, $user_id]);
            echo json_encode(array('success' => false, 'error' => 'Invalid PIN'));
        }
        break;

    case 'nuclear_wipe':
        $data = json_decode(file_get_contents('php://input'), true);
        $pin = $data['pin'] ?? '';

        // Rate limiting check
        $stmt = $pdo->prepare('SELECT pin_attempts, last_lockout FROM user_status WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $status = $stmt->fetch();
        $now = time();
        if ($status && $status['pin_attempts'] >= 5 && ($now - $status['last_lockout']) < 300) {
            $remaining = 300 - ($now - $status['last_lockout']);
            echo json_encode(array('success' => false, 'error' => "Too many attempts. Try again in " . ceil($remaining/60) . " minutes."));
            break;
        }

        // Verify PIN
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute(array('pin_' . $user_id));
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($pin, $hash)) {
            $pdo->prepare('UPDATE user_status SET pin_attempts = pin_attempts + 1, last_lockout = ? WHERE user_id = ?')->execute([$now, $user_id]);
            echo json_encode(array('success' => false, 'error' => 'Invalid PIN'));
            break;
        }

        // Reset attempts on success
        $pdo->prepare('UPDATE user_status SET pin_attempts = 0 WHERE user_id = ?')->execute([$user_id]);

        // 1. Delete all messages and their media (only from uploads/)
        $stmt = $pdo->query("SELECT content FROM messages WHERE is_image=1 OR is_voice=1 OR is_video=1");
        while ($row = $stmt->fetch()) {
            $path = $row['content'];
            // Explicitly only delete from uploads/, exempting premium_vault/
            if ($path && strpos($path, 'uploads/') === 0 && file_exists(__DIR__ . '/../' . $path)) {
                @unlink(__DIR__ . '/../' . $path);
            }
        }
        $pdo->exec("DELETE FROM messages");
        
        // 2. Clear out the uploads directory entirely (optional but safer for "Nuclear Wipe")
        $uploadsDir = __DIR__ . '/../uploads/';
        if (is_dir($uploadsDir)) {
            $files = scandir($uploadsDir);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..' || $f === '.htaccess') continue;
                $full = $uploadsDir . $f;
                if (is_dir($full)) {
                    // Recursive delete for temp dirs
                    $chunks = scandir($full);
                    foreach ($chunks as $c) {
                        if ($c !== '.' && $c !== '..') @unlink($full . '/' . $c);
                    }
                    @rmdir($full);
                } else {
                    @unlink($full);
                }
            }
        }
        
        // Explicitly NOT deleting from saved_images or premium_vault/ as per instructions.
        
        echo json_encode(array('success' => true));
        break;

    case 'reset_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $new_pin = $data['new_pin'] ?? '';
        if (strlen($new_pin) !== 4) { echo json_encode(array('success' => false, 'error' => 'Invalid PIN')); break; }
        $hash = password_hash($new_pin, PASSWORD_BCRYPT);
        $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute(array('pin_' . $user_id, $hash));
        
        // Wipe messages as per description
        $stmt = $pdo->query("SELECT content FROM messages WHERE is_image=1 OR is_voice=1 OR is_video=1");
        while ($row = $stmt->fetch()) {
            $path = $row['content'];
            if ($path && strpos($path, 'uploads/') === 0 && file_exists(__DIR__ . '/../' . $path)) {
                @unlink(__DIR__ . '/../' . $path);
            }
        }
        $pdo->exec("DELETE FROM messages");
        echo json_encode(array('success' => true));
        break;

    case 'update_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $old_pin = $data['old_pin'] ?? '';
        $new_pin = $data['new_pin'] ?? '';
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute(array('pin_' . $user_id));
        $hash = $stmt->fetchColumn();
        if ($hash && password_verify($old_pin, $hash)) {
            $new_hash = password_hash($new_pin, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE settings SET value = ? WHERE key = ?')->execute(array($new_hash, 'pin_' . $user_id));
            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('success' => false, 'error' => 'Invalid old PIN'));
        }
        break;

    case 'update_password':
        $data = json_decode(file_get_contents('php://input'), true);
        $old_pass = $data['old_password'] ?? '';
        $new_pass = $data['new_password'] ?? '';
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute(array('pass_' . $user_id));
        $hash = $stmt->fetchColumn();
        if ($hash && password_verify($old_pass, $hash)) {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE settings SET value = ? WHERE key = ?')->execute(array($new_hash, 'pass_' . $user_id));
            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('success' => false, 'error' => 'Invalid old password'));
        }
        break;
}
