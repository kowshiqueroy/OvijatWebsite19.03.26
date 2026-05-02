<?php
session_start(); 
require_once 'db.php';

// Update last active time
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("UPDATE users SET last_active = ? WHERE id = ?");
    $stmt->execute([time(), $_SESSION['user_id']]);
}

header('Content-Type: application/json');
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'check_auth':
        $loggedIn = isset($_SESSION['user_id']);
        $userId = $loggedIn ? $_SESSION['user_id'] : 0;
        echo json_encode(['loggedIn' => $loggedIn, 'user' => ['id' => $userId]]);
        break;
        
    case 'check_username':
        $username = $_POST['username'] ?? '';
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        echo json_encode(['exists' => $user ? true : false]);
        break;
        
    case 'verify_pin':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false]); break; }
        $pin = $_POST['pin'] ?? '';
        $stmt = $db->prepare("SELECT unlock_pin_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        $success = password_verify($pin, $user['unlock_pin_hash']);
        echo json_encode(['success' => $success]);
        break;
        
    case 'send_message':
        if (!isset($_SESSION['user_id'])) break;
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, original_text, timestamp) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $_POST['receiver_id'], $_POST['text'], time()]);
        echo json_encode(['success' => true]);
        break;
        
    case 'get_messages':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['messages' => []]); break; }
        $stmt = $db->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY timestamp ASC");
        $stmt->execute([$_SESSION['user_id'], $_GET['receiver_id'], $_GET['receiver_id'], $_SESSION['user_id']]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $res = [];
        $prompts = ["Explain Virtual DOM.", "Python web scraping.", "SQL vs NoSQL."];
        $responses = ["Virtual DOM is lightweight...", "Use BeautifulSoup...", "SQL is vertical..."];
        foreach ($msgs as $m) {
            $isSent = $m['sender_id'] == $_SESSION['user_id'];
            $key = array_rand($isSent ? $prompts : $responses);
            $camouflage = $isSent ? $prompts[$key] : $responses[$key];
            $res[] = [
                'id' => $m['id'],
                'sender_id' => $m['sender_id'],
                'original_text' => $m['original_text'],
                'camouflage_text' => $camouflage
            ];
        }
        echo json_encode(['messages' => $res]);
        break;
        
    case 'delete_message':
        if (!isset($_SESSION['user_id'])) break;
        $stmt = $db->prepare("DELETE FROM messages WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$_POST['message_id'], $_SESSION['user_id']]);
        echo json_encode(['success' => true]);
        break;
        
    case 'register':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $pin = $_POST['pin'] ?? '';
        try {
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, unlock_pin_hash, last_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), password_hash($pin, PASSWORD_DEFAULT), time()]);
            $_SESSION['user_id'] = $db->lastInsertId();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Username already exists']);
        }
        break;
        
    case 'login':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $stmt = $db->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
        
    case 'search_users':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['users' => []]); break; }
        $query = $_GET['query'] ?? '';
        $stmt = $db->prepare("SELECT id, username FROM users WHERE (username LIKE ? OR id = ?) AND id != ? LIMIT 10");
        $stmt->execute(["%$query%", (int)$query, $_SESSION['user_id']]);
        echo json_encode(['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;
        
    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;
        
    case 'get_online_status':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['online' => false]); break; }
        $userId = $_GET['user_id'] ?? 0;
        $stmt = $db->prepare("SELECT last_active FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $online = ($user && (time() - $user['last_active']) < 300);
        echo json_encode(['online' => $online]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>