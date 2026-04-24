<?php
date_default_timezone_set('Asia/Dhaka');
define('DB_FILE', __DIR__ . '/chat.db');

const DEFAULT_EMOJIS = ['😎', '🚀', '🐱', '🐶', '🦊', '🦁', '🐯', '🐼', '🐨', '🐸', '🦄', '🍎', '🍕', '🎮', '🎸', '⚽', '💎', '🔥', '🌈', '👻'];

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT UNIQUE, password TEXT, display_name TEXT, pin TEXT DEFAULT '', theme TEXT DEFAULT 'blue', last_active INTEGER DEFAULT 0, avatar_emoji TEXT DEFAULT '', viewing_target TEXT DEFAULT '')");
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY, sender_id INTEGER, receiver_id INTEGER, message TEXT, original TEXT, type TEXT DEFAULT 'text', reply_to INTEGER DEFAULT 0, is_read INTEGER DEFAULT 0, delete_at INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS unlock (id INTEGER PRIMARY KEY, user_id INTEGER, chat_with INTEGER, expires_at INTEGER)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS typing (user_id INTEGER, chat_with INTEGER, expires_at INTEGER, PRIMARY KEY(user_id, chat_with))");
        $pdo->exec("CREATE TABLE IF NOT EXISTS nicknames (user_id INTEGER, contact_id INTEGER, nickname TEXT, PRIMARY KEY(user_id, contact_id))");
        
        // Migrate schema
        try { $pdo->exec("ALTER TABLE users ADD COLUMN last_active INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN avatar_emoji TEXT DEFAULT ''"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN viewing_target TEXT DEFAULT ''"); } catch (Exception $e) {}
    }
    return $pdo;
}

function updateLastActive($userId, $target = null) {
    $pdo = getDB();
    if ($target !== null) {
        $pdo->prepare("UPDATE users SET last_active = ?, viewing_target = ? WHERE id = ?")->execute([time(), $target, $userId]);
    } else {
        $pdo->prepare("UPDATE users SET last_active = ? WHERE id = ?")->execute([time(), $userId]);
    }
}

function getDetailedStatus($u, $meViewing = null) {
    $isOnline = (time() - $u['last_active'] < 60);
    if (!$isOnline) return "Offline (Last seen " . getStatusText($u['last_active']) . ")";
    
    if ($meViewing && $u['viewing_target'] === $meViewing) return "In this chat";
    if (!empty($u['viewing_target'])) return "In other chat";
    return "Online";
}

function getStatusText($lastActive) {
    if ($lastActive == 0) return "Never";
    $diff = time() - $lastActive;
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . "m ago";
    if ($diff < 86400) return floor($diff / 3600) . "h ago";
    return date('M j', $lastActive);
}

function getNickname($userId, $contactId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT nickname FROM nicknames WHERE user_id = ? AND contact_id = ?");
    $stmt->execute([$userId, $contactId]);
    $r = $stmt->fetch();
    return $r ? $r['nickname'] : null;
}

function setNickname($userId, $contactId, $nickname) {
    $pdo = getDB();
    $pdo->prepare("INSERT OR REPLACE INTO nicknames (user_id, contact_id, nickname) VALUES (?, ?, ?)")->execute([$userId, $contactId, $nickname]);
}

function getConnectedUsers($userId) {
    $pdo = getDB();
    $userId = (int)$userId;
    
    // Get all users who have exchanged messages with current user (either direction)
    $stmt = $pdo->prepare("
        SELECT DISTINCT CASE 
            WHEN sender_id = ? THEN receiver_id 
            ELSE sender_id 
        END as other_id
        FROM messages 
        WHERE sender_id = ? OR receiver_id = ?
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $rows = $stmt->fetchAll();
    
    if (empty($rows)) {
        return [];
    }
    
    $userIds = array_column($rows, 'other_id');
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    
    // Get users with their last message info
    $sql = "SELECT id, username, display_name, avatar_emoji, last_active, viewing_target FROM users WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($userIds);
    $users = $stmt->fetchAll();
    
    foreach ($users as &$u) {
        $uid = $u['id'];
        
        // Get last message between these two users
        $msgStmt = $pdo->prepare("
            SELECT message, type, sender_id, is_read, created_at 
            FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
            ORDER BY id DESC LIMIT 1
        ");
        $msgStmt->execute([$userId, $uid, $uid, $userId]);
        $msg = $msgStmt->fetch();
        
        if ($msg) {
            $u['last_msg'] = $msg['message'];
            $u['last_type'] = $msg['type'];
            $u['last_sender'] = $msg['sender_id'];
            $u['last_read'] = $msg['is_read'];
            $u['last_msg_time'] = $msg['created_at'];
        }
        
        // Get unread count
        $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $unreadStmt->execute([$uid, $userId]);
        $u['unread_count'] = $unreadStmt->fetchColumn();
        
        $u['display_name'] = getNickname($userId, $uid) ?: $u['display_name'];
    }
    
    // Sort by last_msg_time descending
    usort($users, function($a, $b) {
        return strcmp($b['last_msg_time'] ?? '', $a['last_msg_time'] ?? '');
    });
    
    return $users;
}

function searchNewUsers($userId, $query) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, username, display_name, avatar_emoji FROM users WHERE (username LIKE ? OR display_name LIKE ?) AND id != ? LIMIT 10");
    $stmt->execute(["%$query%", "%$query%", $userId]);
    return $stmt->fetchAll();
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize($s) { return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8'); }
function isLoggedIn() { return isset($_SESSION['user_id']); }

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function getUserById($id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getAllUsers($excludeId = null) {
    $pdo = getDB();
    $sql = $excludeId ? "SELECT id, username, display_name, avatar_emoji FROM users WHERE id != ?" : "SELECT id, username, display_name, avatar_emoji FROM users";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($excludeId ? [$excludeId] : []);
    return $stmt->fetchAll();
}

function getUserByUsername($username) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function createUser($username, $password, $displayName) {
    $pdo = getDB();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $emoji = DEFAULT_EMOJIS[array_rand(DEFAULT_EMOJIS)];
    $stmt = $pdo->prepare("INSERT INTO users (username, password, display_name, avatar_emoji) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $hash, $displayName, $emoji]);
    return $pdo->lastInsertId();
}

function verifyPassword($user, $password) { return password_verify($password, $user['password']); }

function updateUserPin($userId, $pin) {
    $pdo = getDB();
    $hash = empty($pin) ? '' : password_hash($pin, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET pin = ? WHERE id = ?");
    $stmt->execute([$hash, $userId]);
}

function getPin($userId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT pin FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $r = $stmt->fetch();
    return $r ? $r['pin'] : '';
}

function getMessages($userId, $chatWithId) {
    $pdo = getDB();
    $now = time();
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND (delete_at = 0 OR delete_at > ?) ORDER BY id DESC LIMIT 200");
    $stmt->execute([$userId, $chatWithId, $chatWithId, $userId, $now]);
    return array_reverse($stmt->fetchAll());
}

function markMessagesAsRead($userId, $chatWithId) {
    $pdo = getDB();
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")->execute([$chatWithId, $userId]);
}

function markMessageViewed($msgId, $viewerId) {
    $pdo = getDB();
    $pdo->prepare("UPDATE messages SET delete_at = ? WHERE id = ? AND delete_at = 0")->execute([time() + 30, $msgId]);
}

function cleanupExpiredMessages() {
    $pdo = getDB();
    $now = time();
    $stmt = $pdo->prepare("SELECT message, type FROM messages WHERE delete_at > 0 AND delete_at <= ?");
    $stmt->execute([$now]);
    $toDelete = $stmt->fetchAll();
    foreach ($toDelete as $m) {
        if ($m['type'] === 'image' && file_exists($m['message'])) @unlink($m['message']);
    }
    $pdo->prepare("DELETE FROM messages WHERE delete_at > 0 AND delete_at <= ?")->execute([$now]);
}

function saveMessage($senderId, $receiverId, $message, $type = 'text', $replyTo = 0) {
    $pdo = getDB();
    $fake = ($type === 'text') ? camouflage($message) : $message;
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, original, type, reply_to, delete_at, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
    $stmt->execute([$senderId, $receiverId, $fake, $message, $type, $replyTo, $now]);
    $lastId = $pdo->lastInsertId();
    cleanupOldMessages($senderId, $receiverId);
    return $lastId;
}

function cleanupOldMessages($u1, $u2) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, message, type FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) ORDER BY id DESC LIMIT 1000 OFFSET 200");
    $stmt->execute([$u1, $u2, $u2, $u1]);
    $toDelete = $stmt->fetchAll();
    if ($toDelete) {
        $ids = [];
        foreach ($toDelete as $m) {
            $ids[] = $m['id'];
            if ($m['type'] === 'image' && file_exists($m['message'])) @unlink($m['message']);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM messages WHERE id IN ($placeholders)")->execute($ids);
    }
}

function deleteMyMessages($userId, $imagesOnly = false) {
    $pdo = getDB();
    if ($imagesOnly) {
        $stmt = $pdo->prepare("SELECT message FROM messages WHERE sender_id = ? AND type = 'image'");
        $stmt->execute([$userId]);
        $imgs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($imgs as $img) if (file_exists($img)) @unlink($img);
        $pdo->prepare("DELETE FROM messages WHERE sender_id = ? AND type = 'image'")->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("SELECT message FROM messages WHERE sender_id = ? AND type = 'image'");
        $stmt->execute([$userId]);
        $imgs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($imgs as $img) if (file_exists($img)) @unlink($img);
        $pdo->prepare("DELETE FROM messages WHERE sender_id = ?")->execute([$userId]);
    }
}

function cleanupGlobalOldMessages() {
    $pdo = getDB();
    $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
    
    // Find images to delete
    $stmt = $pdo->prepare("SELECT message FROM messages WHERE type = 'image' AND created_at < ?");
    $stmt->execute([$sevenDaysAgo]);
    $imgs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($imgs as $img) if (file_exists($img)) @unlink($img);
    
    // Delete messages
    $pdo->prepare("DELETE FROM messages WHERE created_at < ?")->execute([$sevenDaysAgo]);
}

function camouflage($msg) {
    $phrases = [
        "How can I assist you with your query today?",
        "I'm analyzing the data you provided. One moment.",
        "That's an interesting perspective. Could you elaborate?",
        "I have processed your request. Here are the findings.",
        "Based on my current training data, here is the answer.",
        "I'm sorry, I didn't quite catch that. Could you rephrase?",
        "Would you like me to generate a summary of this topic?",
        "I am here to help. What else would you like to know?",
        "The system is currently operating at optimal capacity.",
        "I've updated the logs with your recent interaction.",
        "Let me check my internal database for more information.",
        "I can help you with coding, writing, or analysis.",
        "That sounds like a complex task. Let me break it down.",
        "I'm learning from this conversation to improve my responses.",
        "Please provide more context for a more accurate response.",
        "My knowledge cutoff for this specific topic is recent.",
        "I've synthesized the information you requested below.",
        "Is there anything else you'd like to explore today?",
        "I'm ready to help you with your next project.",
        "Generating a response based on your specific parameters..."
    ];
    return $phrases[array_rand($phrases)];
}

function isChatUnlocked($userId, $chatWithId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT expires_at FROM unlock WHERE user_id = ? AND chat_with = ?");
    $stmt->execute([$userId, $chatWithId]);
    $r = $stmt->fetch();
    return $r && $r['expires_at'] > time();
}

function unlockChat($userId, $chatWithId) {
    $pdo = getDB();
    $pdo->prepare("DELETE FROM unlock WHERE user_id = ? AND chat_with = ?")->execute([$userId, $chatWithId]);
    $pdo->prepare("INSERT INTO unlock (user_id, chat_with, expires_at) VALUES (?, ?, ?)")->execute([$userId, $chatWithId, time() + 300]);
}

function lockChat($userId, $chatWithId) {
    $pdo = getDB();
    $pdo->prepare("DELETE FROM unlock WHERE user_id = ? AND chat_with = ?")->execute([$userId, $chatWithId]);
}

function setTyping($userId, $chatWithId) {
    $pdo = getDB();
    $pdo->prepare("DELETE FROM typing WHERE user_id = ? AND chat_with = ?")->execute([$userId, $chatWithId]);
    $pdo->prepare("INSERT INTO typing (user_id, chat_with, expires_at) VALUES (?, ?, ?)")->execute([$userId, $chatWithId, time() + 3]);
}

function getTypingStatus($userId, $chatWithId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT expires_at FROM typing WHERE user_id = ? AND chat_with = ?");
    $stmt->execute([$chatWithId, $userId]);
    $r = $stmt->fetch();
    return $r && $r['expires_at'] > time();
}

function isPinValid($userId, $enteredPin) {
    $storedPin = getPin($userId);
    if (empty($storedPin)) return empty($enteredPin);
    return password_verify($enteredPin, $storedPin);
}

function vanishChats($userId, $chatWithId) {
    $pdo = getDB();
    if ($chatWithId > 0) {
        $stmt = $pdo->prepare("SELECT message, type FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))");
        $stmt->execute([$userId, $chatWithId, $chatWithId, $userId]);
        $msgs = $stmt->fetchAll();
        foreach ($msgs as $m) if ($m['type'] === 'image' && file_exists($m['message'])) @unlink($m['message']);
        $pdo->prepare("DELETE FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))")->execute([$userId, $chatWithId, $chatWithId, $userId]);
        $pdo->prepare("DELETE FROM unlock WHERE user_id = ? AND chat_with = ?")->execute([$userId, $chatWithId]);
    } else {
        $stmt = $pdo->prepare("SELECT message, type FROM messages WHERE sender_id = ? OR receiver_id = ?");
        $stmt->execute([$userId, $userId]);
        $msgs = $stmt->fetchAll();
        foreach ($msgs as $m) if ($m['type'] === 'image' && file_exists($m['message'])) @unlink($m['message']);
        $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$userId, $userId]);
        $pdo->prepare("DELETE FROM unlock WHERE user_id = ?")->execute([$userId]);
    }
}
