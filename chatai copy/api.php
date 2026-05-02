<?php
require_once 'auth.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'send_message':
        $data = json_decode(file_get_contents('php://input'), true);
        $content = $data['content'] ?? '';
        $is_image = $data['is_image'] ?? 0;
        $receiver_id = ($user_id == 1) ? 2 : 1;

        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, is_image) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $receiver_id, $content, $is_image]);
        echo json_encode(['success' => true]);
        break;

    case 'get_messages':
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? OR receiver_id = ?) AND deleted_at IS NULL ORDER BY created_at ASC");
        $stmt->execute([$user_id, $user_id]);
        $messages = $stmt->fetchAll();
        
        // Check for messages that should be auto-deleted (burn logic)
        $now = time();
        foreach ($messages as $key => $msg) {
            if ($msg['burn_after'] !== null && $now >= $msg['burn_after']) {
                $stmtDel = $pdo->prepare("UPDATE messages SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmtDel->execute([$msg['id']]);
                unset($messages[$key]);
            }
        }
        
        echo json_encode(array_values($messages));
        break;

    case 'update_status':
        $data = json_decode(file_get_contents('php://input'), true);
        $is_typing = $data['is_typing'] ?? 0;
        
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO user_status (user_id, last_seen, is_typing) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, time(), $is_typing]);
        echo json_encode(['success' => true]);
        break;

    case 'get_other_status':
        $other_user_id = ($user_id == 1) ? 2 : 1;
        $stmt = $pdo->prepare("SELECT * FROM user_status WHERE user_id = ?");
        $stmt->execute([$other_user_id]);
        $status = $stmt->fetch();
        
        if ($status) {
            $last_seen = (int)$status['last_seen'];
            $diff = time() - $last_seen;
            if ($diff < 10) {
                $state = ($status['is_typing'] == 1) ? 'typing' : 'active';
            } else {
                $state = 'offline';
            }
            echo json_encode(['status' => $state]);
        } else {
            echo json_encode(['status' => 'offline']);
        }
        break;

    case 'mark_viewed':
        $data = json_decode(file_get_contents('php://input'), true);
        $msg_id = $data['msg_id'];
        $burn_seconds = $data['burn_seconds'];
        
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$msg_id]);
        $msg = $stmt->fetch();
        
        if ($msg && $msg['burn_after'] === null) {
            $burn_at = time() + $burn_seconds;
            $stmtUpd = $pdo->prepare("UPDATE messages SET viewed_at = CURRENT_TIMESTAMP, burn_after = ? WHERE id = ?");
            $stmtUpd->execute([$burn_at, $msg_id]);
            echo json_encode(['success' => true, 'burn_after' => $burn_at]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'nuclear_wipe':
        $pdo->exec("UPDATE messages SET deleted_at = CURRENT_TIMESTAMP");
        echo json_encode(['success' => true]);
        break;

    case 'reset_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $new_pin = $data['new_pin'] ?? '5877';

        // Wipe messages and send notification
        $pdo->exec("UPDATE messages SET deleted_at = CURRENT_TIMESTAMP");
        
        $receiver_id = ($user_id == 1) ? 2 : 1;
        $stmtMsg = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, is_image) VALUES (?, ?, ?, ?)");
        $stmtMsg->execute([0, $receiver_id, "System: Other user has reset their PIN and wiped all messages.", 0]);
        
        // Update PIN to new value
        $pin_key = "pin_" . $user_id;
        $stmtPin = $pdo->prepare("UPDATE settings SET value = ? WHERE key = ?");
        $stmtPin->execute([$new_pin, $pin_key]);
        
        echo json_encode(['success' => true]);
        break;

    case 'get_pin':
        $pin_key = "pin_" . $user_id;
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$pin_key]);
        $pin = $stmt->fetchColumn();
        echo json_encode(['pin' => $pin]);
        break;

    case 'update_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $old_pin = $data['old_pin'];
        $new_pin = $data['new_pin'];
        $pin_key = "pin_" . $user_id;

        // Verify old PIN
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$pin_key]);
        if ($stmt->fetchColumn() !== $old_pin) {
            echo json_encode(['success' => false, 'error' => 'Old PIN is incorrect']);
            break;
        }

        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key = ?");
        $stmt->execute([$new_pin, $pin_key]);
        echo json_encode(['success' => true]);
        break;

    case 'update_password':
        $data = json_decode(file_get_contents('php://input'), true);
        $old_pass = $data['old_pass'];
        $new_pass = $data['new_pass'];
        $pass_key = "pass_" . $user_id;

        // Verify old password
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$pass_key]);
        if ($stmt->fetchColumn() !== $old_pass) {
            echo json_encode(['success' => false, 'error' => 'Old password is incorrect']);
            break;
        }

        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key = ?");
        $stmt->execute([$new_pass, $pass_key]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
