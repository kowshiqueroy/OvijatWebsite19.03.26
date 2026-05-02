<?php
require_once 'auth.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// CSRF Protection Check for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF validation failed']);
        exit();
    }
}

switch ($action) {
    // --- Auth & Profile ---
    case 'get_user_info':
        $stmt = $pdo->prepare("SELECT u.*, p.* FROM users u LEFT JOIN user_privacy_settings p ON u.id = p.user_id WHERE u.id = ?");
        $stmt->execute([$user_id]);
        $data = $stmt->fetch();
        unset($data['password_hash'], $data['pin_hash']);
        echo json_encode($data);
        break;

    case 'update_privacy_settings':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("UPDATE user_privacy_settings SET 
            auto_lock_timer = ?, 
            reveal_on_arrival_duration = ?, 
            reveal_on_click_duration = ?, 
            camouflage_style = ?, 
            auto_reveal_unlocked = ? 
            WHERE user_id = ?");
        $stmt->execute([
            $data['auto_lock_timer'] ?? 60,
            $data['reveal_on_arrival_duration'] ?? 0,
            $data['reveal_on_click_duration'] ?? 5,
            $data['camouflage_style'] ?? 'c_code',
            $data['auto_reveal_unlocked'] ?? 0,
            $user_id
        ]);
        echo json_encode(['success' => true]);
        break;

    case 'update_theme':
        $data = json_decode(file_get_contents('php://input'), true);
        $theme = $data['theme'] ?? 'coding';
        $stmt = $pdo->prepare("UPDATE users SET camouflage_theme = ? WHERE id = ?");
        $stmt->execute([$theme, $user_id]);
        echo json_encode(['success' => true]);
        break;

    case 'update_profile':
        $data = json_decode(file_get_contents('php://input'), true);
        $display_name = trim($data['display_name'] ?? '');
        if (empty($display_name)) {
            echo json_encode(['success' => false, 'error' => 'Display name cannot be empty']);
            break;
        }
        $stmt = $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?");
        $stmt->execute([$display_name, $user_id]);
        $_SESSION['display_name'] = $display_name;
        echo json_encode(['success' => true]);
        break;

    case 'update_password':
        $data = json_decode(file_get_contents('php://input'), true);
        $old_pass = $data['old_pass'] ?? '';
        $new_pass = $data['new_pass'] ?? '';
        
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $hash = $stmt->fetchColumn();

        if ($hash && password_verify($old_pass, $hash)) {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmtUpd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmtUpd->execute([$new_hash, $user_id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Old password is incorrect']);
        }
        break;

    case 'update_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $old_pin = $data['old_pin'] ?? '';
        $new_pin = $data['new_pin'] ?? '';
        
        if (strlen($new_pin) !== 4) {
            echo json_encode(['success' => false, 'error' => 'New PIN must be 4 digits']);
            break;
        }

        $stmt = $pdo->prepare("SELECT pin_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $hash = $stmt->fetchColumn();

        if ($hash && password_verify($old_pin, $hash)) {
            $new_hash = password_hash($new_pin, PASSWORD_BCRYPT);
            $stmtUpd = $pdo->prepare("UPDATE users SET pin_hash = ? WHERE id = ?");
            $stmtUpd->execute([$new_hash, $user_id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Old PIN is incorrect']);
        }
        break;

    case 'set_nickname':
        $data = json_decode(file_get_contents('php://input'), true);
        $target_user_id = $data['target_user_id'];
        $nickname = trim($data['nickname'] ?? '');
        
        if (empty($nickname)) {
            $stmt = $pdo->prepare("DELETE FROM nicknames WHERE set_by_user_id = ? AND target_user_id = ?");
            $stmt->execute([$user_id, $target_user_id]);
        } else {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO nicknames (set_by_user_id, target_user_id, nickname) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $target_user_id, $nickname]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'get_rooms':
        $stmt = $pdo->prepare("
            SELECT r.*, 
            (SELECT GROUP_CONCAT(COALESCE(n.nickname, u.display_name), ', ') 
             FROM room_members rm2 
             JOIN users u ON rm2.user_id = u.id 
             LEFT JOIN nicknames n ON n.set_by_user_id = ? AND n.target_user_id = u.id
             WHERE rm2.room_id = r.id AND rm2.user_id != ?) as member_names
            FROM rooms r 
            JOIN room_members rm ON r.id = rm.room_id 
            WHERE rm.user_id = ? 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'search_users':
        $query = $_GET['query'] ?? '';
        if (strlen($query) < 2) { echo json_encode([]); break; }
        $stmt = $pdo->prepare("SELECT id, username, display_name FROM users WHERE (username LIKE ? OR display_name LIKE ?) AND status = 'active' AND id != ? LIMIT 10");
        $stmt->execute(['%' . $query . '%', '%' . $query . '%', $user_id]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'create_room':
        $data = json_decode(file_get_contents('php://input'), true);
        $type = $data['type'] ?? '1to1';
        $usernames = $data['members'] ?? [];

        if (empty($usernames)) {
            echo json_encode(['success' => false, 'error' => 'No members selected']);
            break;
        }

        $pdo->beginTransaction();
        try {
            if ($type === '1to1' && count($usernames) === 1) {
                $stmtId = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmtId->execute([$usernames[0]]);
                $other_id = $stmtId->fetchColumn();
                
                if ($other_id) {
                    $stmtCheck = $pdo->prepare("
                        SELECT room_id FROM room_members 
                        WHERE user_id IN (?, ?) 
                        GROUP BY room_id HAVING COUNT(DISTINCT user_id) = 2
                    ");
                    $stmtCheck->execute([$user_id, $other_id]);
                    $existing_room_id = $stmtCheck->fetchColumn();
                    
                    if ($existing_room_id) {
                        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM room_members WHERE room_id = ?");
                        $stmtCount->execute([$existing_room_id]);
                        if ($stmtCount->fetchColumn() == 2) {
                            $pdo->rollBack();
                            echo json_encode(['success' => true, 'room_id' => $existing_room_id]);
                            break;
                        }
                    }
                }
            }

            $name = $data['name'] ?? null;
            $stmt = $pdo->prepare("INSERT INTO rooms (name, type, creator_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $type, $user_id]);
            $room_id = $pdo->lastInsertId();

            $stmtMember = $pdo->prepare("INSERT INTO room_members (room_id, user_id) VALUES (?, ?)");
            $stmtMember->execute([$room_id, $user_id]);
            
            foreach ($usernames as $uname) {
                $stmtId = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmtId->execute([$uname]);
                $mid = $stmtId->fetchColumn();
                if ($mid && $mid != $user_id) {
                    $stmtMember->execute([$room_id, $mid]);
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'room_id' => $room_id]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'send_message':
        $data = json_decode(file_get_contents('php://input'), true);
        $room_id = $data['room_id'] ?? 0;
        $content = $data['content'] ?? '';
        $is_image = $data['is_image'] ?? 0;

        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM room_members WHERE room_id = ? AND user_id = ?");
        $stmtCheck->execute([$room_id, $user_id]);
        if ($stmtCheck->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'error' => 'Not a member of this room']);
            break;
        }

        $stmt = $pdo->prepare("INSERT INTO messages (room_id, sender_id, content, is_image) VALUES (?, ?, ?, ?)");
        $stmt->execute([$room_id, $user_id, $content, $is_image]);
        echo json_encode(['success' => true]);
        break;

    case 'get_messages':
        $room_id = $_GET['room_id'] ?? 0;
        $stmt = $pdo->prepare("
            SELECT m.*, COALESCE(n.nickname, u.display_name) as sender_name 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            LEFT JOIN nicknames n ON n.set_by_user_id = ? AND n.target_user_id = u.id
            WHERE m.room_id = ? AND m.deleted_at IS NULL 
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$user_id, $room_id]);
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

    case 'get_camouflage':
        $theme = $_GET['theme'] ?? 'coding';
        $stmt = $pdo->prepare("SELECT type, content FROM camouflage_library WHERE theme = ?");
        $stmt->execute([$theme]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'admin_get_users':
        if ($_SESSION['role'] !== 'admin') { echo json_encode(['error' => 'Unauthorized']); break; }
        $stmt = $pdo->query("SELECT id, username, display_name, role, status, camouflage_theme FROM users");
        echo json_encode($stmt->fetchAll());
        break;

    case 'admin_update_user':
        if ($_SESSION['role'] !== 'admin') { echo json_encode(['error' => 'Unauthorized']); break; }
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("UPDATE users SET status = ?, role = ? WHERE id = ?");
        $stmt->execute([$data['status'], $data['role'], $data['id']]);
        echo json_encode(['success' => true]);
        break;

    case 'admin_manage_camouflage':
        if ($_SESSION['role'] !== 'admin') { echo json_encode(['error' => 'Unauthorized']); break; }
        $data = json_decode(file_get_contents('php://input'), true);
        $sub_action = $data['sub_action']; 
        
        if ($sub_action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO camouflage_library (theme, type, content) VALUES (?, ?, ?)");
            $stmt->execute([$data['theme'], $data['type'], $data['content']]);
        } elseif ($sub_action === 'edit') {
            $stmt = $pdo->prepare("UPDATE camouflage_library SET theme = ?, type = ?, content = ? WHERE id = ?");
            $stmt->execute([$data['theme'], $data['type'], $data['content'], $data['id']]);
        } elseif ($sub_action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM camouflage_library WHERE id = ?");
            $stmt->execute([$data['id']]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'update_status':
        $data = json_decode(file_get_contents('php://input'), true);
        $is_typing = (isset($data['is_typing']) && $data['is_typing'] == 1) ? 1 : 0;
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO user_status (user_id, last_seen, is_typing) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, time(), $is_typing]);
        echo json_encode(['success' => true]);
        break;

    case 'mark_viewed':
        $data = json_decode(file_get_contents('php://input'), true);
        $msg_id = $data['msg_id'];
        
        $stmt = $pdo->prepare("SELECT m.* FROM messages m JOIN room_members rm ON m.room_id = rm.room_id WHERE m.id = ? AND rm.user_id = ?");
        $stmt->execute([$msg_id, $user_id]);
        $msg = $stmt->fetch();
        
        if ($msg && $msg['burn_after'] === null && $msg['sender_id'] !== $user_id) {
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

    case 'verify_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $pin = $data['pin'] ?? '';
        $stmt = $pdo->prepare("SELECT pin_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $hash = $stmt->fetchColumn();
        if ($hash && password_verify($pin, $hash)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
