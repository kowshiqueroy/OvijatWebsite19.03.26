<?php
date_default_timezone_set('Asia/Dhaka');
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(array('error' => 'Not logged in'));
    exit;
}

$myUser = $_SESSION['user'];
$myId = $_SESSION['user_id'];
$other = getOtherUser($myUser);
$otherId = $other['id'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'send_message':
        $message = $_POST['message'] ?? '';
        $receiverId = (int)($_POST['user'] ?? 0);
        if ($message && $receiverId) {
            $chatDb = getChatDb();
            $wordCount = str_word_count($message);
            $stmt = $chatDb->prepare("INSERT INTO messages (sender_id, receiver_id, message, word_count, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute(array($myId, $receiverId, $message, $wordCount));
            $lastId = $chatDb->lastInsertId();
            echo json_encode(array('success' => true, 'last_id' => $lastId));
        }
        break;

    case 'send_image':
        $receiverId = (int)($_POST['user'] ?? 0);
        if (isset($_FILES['image']) && $receiverId) {
            $imageData = file_get_contents($_FILES['image']['tmp_name']);
            $chatDb = getChatDb();
            $stmt = $chatDb->prepare("INSERT INTO messages (sender_id, receiver_id, is_image, image_blob, word_count, created_at) VALUES (?, ?, 1, ?, 0, CURRENT_TIMESTAMP)");
            $stmt->execute(array($myId, $receiverId, $imageData));
            $lastId = $chatDb->lastInsertId();
            echo json_encode(array('success' => true, 'last_id' => $lastId));
        }
        break;

    case 'messages':
        $lastId = (int)($_GET['last_id'] ?? 0);
        $chatDb = getChatDb();
        $stmt = $chatDb->prepare("SELECT * FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND id > ? AND (viewed_at IS NULL OR datetime(viewed_at) > datetime('now', '-30 seconds')) ORDER BY created_at ASC");
        $stmt->execute(array($myId, $otherId, $otherId, $myId, $lastId));
        $messages = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $msg = array(
                'id' => $row['id'],
                'sender_id' => $row['sender_id'],
                'receiver_id' => $row['receiver_id'],
                'message' => $row['message'],
                'is_image' => $row['is_image'],
                'word_count' => $row['word_count'],
                'created_at' => $row['created_at'],
                'viewed' => $row['viewed'],
                'viewed_at' => $row['viewed_at']
            );
            if ($row['is_image'] && $row['image_blob']) {
                $msg['image_data'] = base64_encode($row['image_blob']);
            }
            $messages[] = $msg;
        }
        echo json_encode(array('messages' => $messages));
        break;

    case 'view_message':
        $msgId = (int)($_GET['id'] ?? 0);
        if ($msgId) {
            $chatDb = getChatDb();
            $stmt = $chatDb->prepare("UPDATE messages SET viewed = 1, viewed_at = CURRENT_TIMESTAMP WHERE id = ? AND receiver_id = ?");
            $stmt->execute(array($msgId, $myId));
        }
        echo json_encode(array('success' => true, 'deleted_id' => $msgId));
        break;

    case 'delete_check':
        $chatDb = getChatDb();
        $stmt = $chatDb->prepare("SELECT id FROM messages WHERE viewed = 1 AND viewed_at IS NOT NULL AND datetime(viewed_at) <= datetime('now', '-30 seconds')");
        $stmt->execute();
        $deletedIds = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $deletedIds[] = $row['id'];
        }
        $stmt = $chatDb->prepare("DELETE FROM messages WHERE viewed = 1 AND viewed_at IS NOT NULL AND datetime(viewed_at) <= datetime('now', '-30 seconds')");
        $stmt->execute();
        echo json_encode(array('success' => true, 'deleted_ids' => $deletedIds));
        break;

    case 'status':
        $otherDb = getUserDb($other['user']);
        $stmt = $otherDb->prepare("SELECT username, is_unlocked, last_active, is_typing, (julianday('now') - julianday(last_active)) * 86400 as seconds_ago FROM users WHERE id = 1");
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $isOnline = false;
        if ($res && isset($res['seconds_ago']) && $res['seconds_ago'] < 5) {
            $isOnline = true;
        }
        $isTyping = false;
        if ($res && isset($res['is_typing']) && $res['is_typing'] == 1 && $res['seconds_ago'] < 3) {
            $isTyping = true;
        }
        echo json_encode(array(
            'username' => $res['username'] ?? $other['user'] ?? '',
            'is_online' => $isOnline,
            'is_typing' => $isTyping,
            'last_active' => $res['last_active'] ?? null,
            'is_unlocked' => ($res['is_unlocked'] ?? 0) == 1
        ));
        break;

    case 'ping':
        $myDb = getUserDb($myUser);
        $stmt = $myDb->prepare("UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE id = 1");
        $stmt->execute();
        echo json_encode(array('success' => true));
        break;

    case 'typing':
        $myDb = getUserDb($myUser);
        $stmt = $myDb->prepare("UPDATE users SET is_typing = 1, last_active = CURRENT_TIMESTAMP WHERE id = 1");
        $stmt->execute();
        echo json_encode(array('success' => true));
        break;

    case 'reset_typing':
        $myDb = getUserDb($myUser);
        $stmt = $myDb->prepare("UPDATE users SET is_typing = 0 WHERE id = 1");
        $stmt->execute();
        echo json_encode(array('success' => true));
        break;

    case 'lock':
        $myDb = getUserDb($myUser);
        $stmt = $myDb->prepare("UPDATE users SET is_unlocked = 0 WHERE id = 1");
        $stmt->execute();
        echo json_encode(array('success' => true));
        break;

    case 'unlock':
        $pin = $_POST['pin'] ?? '';
        $myDb = getUserDb($myUser);
        $stmt = $myDb->prepare("SELECT pin FROM users WHERE id = 1");
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res && $res['pin'] === $pin) {
            $stmt = $myDb->prepare("UPDATE users SET is_unlocked = 1, last_active = CURRENT_TIMESTAMP WHERE id = 1");
            $stmt->execute();
            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('success' => false, 'error' => 'Invalid PIN'));
        }
        break;

    default:
        echo json_encode(array('error' => 'Unknown action'));
}
?>
