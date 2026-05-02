<?php
require_once 'auth.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// CSRF Protection Check for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = getallheaders();
    $csrf_token = $headers['X-CSRF-TOKEN'] ?? '';
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF validation failed']);
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'send_message':
        $data = json_decode(file_get_contents('php://input'), true);
        $content = $data['content'] ?? '';
        $is_image = $data['is_image'] ?? 0;
        $receiver_id = ($user_id == 1) ? 2 : 1;

        // Content size limit check
        if (strlen($content) > 1000000) { // 1MB limit for safety
             echo json_encode(['success' => false, 'error' => 'Payload too large']);
             break;
        }

        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, is_image) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $receiver_id, $content, $is_image]);
        echo json_encode(['success' => true]);
        break;

    case 'get_messages':
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? OR receiver_id = ?) AND deleted_at IS NULL ORDER BY created_at ASC");
        $stmt->execute([$user_id, $user_id]);
        $messages = $stmt->fetchAll();
        
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
        $is_typing = (isset($data['is_typing']) && $data['is_typing'] == 1) ? 1 : 0;
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
        
        // Authorization: Verify user is the receiver
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$msg_id, $user_id]);
        $msg = $stmt->fetch();
        
        if ($msg && $msg['burn_after'] === null) {
            // Backend calculated burn seconds based on word count
            $wordCount = $msg['is_image'] ? 50 : count(explode(' ', $msg['content']));
            $burn_seconds = max(20, min(60, $wordCount * 2));
            $burn_at = time() + $burn_seconds;
            
            $stmtUpd = $pdo->prepare("UPDATE messages SET viewed_at = CURRENT_TIMESTAMP, burn_after = ? WHERE id = ?");
            $stmtUpd->execute([$burn_at, $msg_id]);
            echo json_encode(['success' => true, 'burn_after' => $burn_at]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'nuclear_wipe':
        $pdo->exec("UPDATE messages SET deleted_at = CURRENT_TIMESTAMP WHERE sender_id = $user_id OR receiver_id = $user_id");
        echo json_encode(['success' => true]);
        break;

    case 'reset_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $new_pin = $data['new_pin'] ?? '';
        if (strlen($new_pin) !== 4) {
            echo json_encode(['success' => false, 'error' => 'Invalid PIN']);
            break;
        }

        $pdo->exec("UPDATE messages SET deleted_at = CURRENT_TIMESTAMP WHERE sender_id = $user_id OR receiver_id = $user_id");
        $receiver_id = ($user_id == 1) ? 2 : 1;
        $stmtMsg = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, is_image) VALUES (0, ?, 'System: Other user has reset their PIN and wiped all messages.', 0)");
        $stmtMsg->execute([$receiver_id]);
        
        $pin_hash = password_hash($new_pin, PASSWORD_BCRYPT);
        $stmtPin = $pdo->prepare("UPDATE settings SET value = ? WHERE key = ?");
        $stmtPin->execute([$pin_hash, "pin_" . $user_id]);
        echo json_encode(['success' => true]);
        break;

    case 'verify_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $pin = $data['pin'] ?? '';
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute(["pin_" . $user_id]);
        $hash = $stmt->fetchColumn();
        if ($hash && password_verify($pin, $hash)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'update_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $old_pin = $data['old_pin'] ?? '';
        $new_pin = $data['new_pin'] ?? '';
        
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute(["pin_" . $user_id]);
        $hash = $stmt->fetchColumn();

        if ($hash && password_verify($old_pin, $hash)) {
            $new_hash = password_hash($new_pin, PASSWORD_BCRYPT);
            $stmtUpd = $pdo->prepare("UPDATE settings SET value = ? WHERE key = ?");
            $stmtUpd->execute([$new_hash, "pin_" . $user_id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Old PIN is incorrect']);
        }
        break;

    case 'update_password':
        $data = json_decode(file_get_contents('php://input'), true);
        $old_pass = $data['old_pass'] ?? '';
        $new_pass = $data['new_pass'] ?? '';
        
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute(["pass_" . $user_id]);
        $hash = $stmt->fetchColumn();

        if ($hash && password_verify($old_pass, $hash)) {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmtUpd = $pdo->prepare("UPDATE settings SET value = ? WHERE key = ?");
            $stmtUpd->execute([$new_hash, "pass_" . $user_id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Old password is incorrect']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
