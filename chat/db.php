<?php
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
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages_read (message_id INTEGER, user_id INTEGER, PRIMARY KEY(message_id, user_id))");
        $pdo->exec("CREATE TABLE IF NOT EXISTS groups (id INTEGER PRIMARY KEY, name TEXT, created_by INTEGER, avatar_emoji TEXT DEFAULT '👥', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (group_id INTEGER, user_id INTEGER, role TEXT DEFAULT 'member', nickname TEXT DEFAULT '', PRIMARY KEY(group_id, user_id))");
        $pdo->exec("CREATE TABLE IF NOT EXISTS unlock (id INTEGER PRIMARY KEY, user_id INTEGER, chat_with INTEGER, expires_at INTEGER)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS typing (user_id INTEGER, chat_with INTEGER, expires_at INTEGER, PRIMARY KEY(user_id, chat_with))");
        $pdo->exec("CREATE TABLE IF NOT EXISTS nicknames (user_id INTEGER, contact_id INTEGER, nickname TEXT, PRIMARY KEY(user_id, contact_id))");
        
        // Migrate schema
        try { $pdo->exec("ALTER TABLE users ADD COLUMN last_active INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN avatar_emoji TEXT DEFAULT ''"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN viewing_target TEXT DEFAULT ''"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE messages ADD COLUMN group_id INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE group_members ADD COLUMN nickname TEXT DEFAULT ''"); } catch (Exception $e) {}
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
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.display_name, u.avatar_emoji, u.last_active, u.viewing_target, 
        (SELECT message FROM messages WHERE ((sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) ORDER BY id DESC LIMIT 1) as last_msg,
        (SELECT type FROM messages WHERE ((sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) ORDER BY id DESC LIMIT 1) as last_type,
        (SELECT sender_id FROM messages WHERE ((sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) ORDER BY id DESC LIMIT 1) as last_sender,
        (SELECT is_read FROM messages WHERE ((sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) ORDER BY id DESC LIMIT 1) as last_read,
        (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count,
        (SELECT created_at FROM messages WHERE ((sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) ORDER BY id DESC LIMIT 1) as last_msg_time
        FROM users u 
        WHERE id IN (
            SELECT receiver_id FROM messages WHERE sender_id = ?
            UNION 
            SELECT sender_id FROM messages WHERE receiver_id = ?
        )
        ORDER BY last_msg_time DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    $users = $stmt->fetchAll();
    foreach ($users as &$u) {
        $u['display_name'] = getNickname($userId, $u['id']) ?: $u['display_name'];
    }
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

function saveMessage($senderId, $receiverId, $message, $type = 'text', $replyTo = 0) {
    $pdo = getDB();
    $fake = ($type === 'text') ? camouflage($message) : $message;
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, original, type, reply_to, delete_at) VALUES (?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([$senderId, $receiverId, $fake, $message, $type, $replyTo]);
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

function camouflage($msg) {
    $phrases = ["Sounds good, let me know.", "I'll check on that later.", "Not sure yet, will tell you.", "Okay, noted.", "Thanks for the update.", "I'll get back to you soon.", "That works for me.", "Can we discuss this tomorrow?", "Send me the details please.", "I'm on it.", "Just finished the task.", "Wait, I am checking.", "Yes, that is fine.", "No problem at all.", "I'll be there in 10 minutes.", "Call me when you're free.", "Did you see the email?", "I'm busy right now, talk later.", "Have a great day!", "Let's meet at the usual place.", "I've already sent it.", "Looking forward to it.", "Everything is under control.", "Do you need any help?", "I'll handle it.", "Let me double check.", "Right, I see.", "Got it, thanks.", "Perfect, thanks.", "I'll call you back.", "No, not today.", "Maybe next week.", "I'm heading out now."];
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
