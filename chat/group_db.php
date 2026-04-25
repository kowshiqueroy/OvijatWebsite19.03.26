<?php
require_once 'db.php';

function getGroupDB() {
    $pdo = getDB();
    $pdo->exec("CREATE TABLE IF NOT EXISTS groups (
        id INTEGER PRIMARY KEY, 
        name TEXT, 
        created_by INTEGER, 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
        group_id INTEGER, 
        user_id INTEGER, 
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(group_id, user_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_messages (
        id INTEGER PRIMARY KEY, 
        group_id INTEGER, 
        sender_id INTEGER, 
        message TEXT, 
        original TEXT, 
        type TEXT DEFAULT 'text', 
        reply_to INTEGER DEFAULT 0, 
        delete_at INTEGER DEFAULT 0, 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_viewed (
        message_id INTEGER, 
        user_id INTEGER, 
        viewed_at INTEGER, 
        delete_at INTEGER,
        PRIMARY KEY(message_id, user_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_unlock (
        user_id INTEGER, 
        group_id INTEGER, 
        expires_at INTEGER,
        PRIMARY KEY(user_id, group_id)
    )");
    return $pdo;
}

function createGroup($name, $creatorId) {
    $pdo = getGroupDB();
    $stmt = $pdo->prepare("INSERT INTO groups (name, created_by) VALUES (?, ?)");
    $stmt->execute([$name, $creatorId]);
    $groupId = $pdo->lastInsertId();
    addGroupMember($groupId, $creatorId);
    return $groupId;
}

function addGroupMember($groupId, $userId) {
    $pdo = getGroupDB();
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)");
    $stmt->execute([$groupId, $userId]);
}

function removeGroupMember($groupId, $userId) {
    $pdo = getGroupDB();
    $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$groupId, $userId]);
}

function getGroupsForUser($userId) {
    $pdo = getGroupDB();
    $stmt = $pdo->prepare("
        SELECT g.*, 
        (SELECT message FROM group_messages WHERE group_id = g.id ORDER BY id DESC LIMIT 1) as last_msg,
        (SELECT type FROM group_messages WHERE group_id = g.id ORDER BY id DESC LIMIT 1) as last_type,
        (SELECT created_at FROM group_messages WHERE group_id = g.id ORDER BY id DESC LIMIT 1) as last_msg_time
        FROM groups g
        JOIN group_members gm ON g.id = gm.group_id
        WHERE gm.user_id = ?
        ORDER BY last_msg_time DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getGroupById($groupId) {
    $pdo = getGroupDB();
    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([$groupId]);
    return $stmt->fetch();
}

function getGroupMembers($groupId) {
    $pdo = getGroupDB();
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.display_name, u.avatar_emoji, u.last_active 
        FROM users u
        JOIN group_members gm ON u.id = gm.user_id
        WHERE gm.group_id = ?
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

function saveGroupMessage($groupId, $senderId, $message, $type = 'text', $replyTo = 0) {
    $pdo = getGroupDB();
    if ($type === 'image') {
        // For images, use a camouflage text and store image path in original
        $fake = camouflage('[Image]');
        $original = $message;
        $message = '[Image]';
    } else {
        $fake = camouflage($message);
        $original = $message;
    }
    $stmt = $pdo->prepare("INSERT INTO group_messages (group_id, sender_id, message, original, type, reply_to, delete_at) VALUES (?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([$groupId, $senderId, $fake, $original, $type, $replyTo]);
    $messageId = $pdo->lastInsertId();
    
    // Sender vanishes 30s after sending
    $deleteAt = time() + 30;
    $stmt = $pdo->prepare("INSERT INTO group_viewed (message_id, user_id, viewed_at, delete_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$messageId, $senderId, time(), $deleteAt]);
    
    return $messageId;
}

function getGroupMessages($groupId, $userId) {
    $pdo = getGroupDB();
    $now = time();
    
    // Get all member IDs for this group
    $stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $memberIds = array_column($stmt->fetchAll(), 'user_id');
    $totalMembers = count($memberIds);
    
    // Get messages with view data for all members
    $stmt = $pdo->prepare("
        SELECT gm.*, u.display_name as sender_name, u.avatar_emoji as sender_emoji,
        gv.viewed_at, gv.delete_at as user_delete_at
        FROM group_messages gm
        JOIN users u ON gm.sender_id = u.id
        LEFT JOIN group_viewed gv ON gm.id = gv.message_id AND gv.user_id = ?
        WHERE gm.group_id = ?
        ORDER BY gm.id DESC LIMIT 200
    ");
    $stmt->execute([$userId, $groupId]);
    $messages = array_reverse($stmt->fetchAll());
    
    // For each message, get all viewers and check if all have viewed
    foreach ($messages as &$msg) {
        $msgId = $msg['id'];
        
        // Get all viewers for this message
        $stmt = $pdo->prepare("SELECT user_id, viewed_at FROM group_viewed WHERE message_id = ?");
        $stmt->execute([$msgId]);
        $viewers = $stmt->fetchAll();
        $viewerIds = array_column($viewers, 'user_id');
        
        // Get viewer details
        $msg['viewers'] = [];
        if (!empty($viewers)) {
            $viewerIdsList = implode(',', array_map('intval', $viewerIds));
            $stmt = $pdo->prepare("SELECT id, display_name, avatar_emoji FROM users WHERE id IN ($viewerIdsList)");
            $stmt->execute();
            foreach ($stmt->fetchAll() as $v) {
                $msg['viewers'][] = [
                    'user_id' => $v['id'],
                    'display_name' => $v['display_name'],
                    'avatar_emoji' => $v['avatar_emoji']
                ];
            }
        }
        
        // Check if all members have viewed - then set global delete countdown
        $msg['all_viewed'] = (count($viewerIds) >= $totalMembers && $totalMembers > 0);
    }
    
    return $messages;
}

function markGroupMessageViewed($messageId, $userId) {
    $pdo = getGroupDB();
    // Only mark if not already viewed
    $stmt = $pdo->prepare("SELECT 1 FROM group_viewed WHERE message_id = ? AND user_id = ?");
    $stmt->execute([$messageId, $userId]);
    if (!$stmt->fetch()) {
        $deleteAt = time() + 30;
        $stmt = $pdo->prepare("INSERT INTO group_viewed (message_id, user_id, viewed_at, delete_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$messageId, $userId, time(), $deleteAt]);
    }
}

function isGroupUnlocked($userId, $groupId) {
    $pdo = getGroupDB();
    $stmt = $pdo->prepare("SELECT expires_at FROM group_unlock WHERE user_id = ? AND group_id = ?");
    $stmt->execute([$userId, $groupId]);
    $r = $stmt->fetch();
    return $r && $r['expires_at'] > time();
}

function unlockGroup($userId, $groupId) {
    $pdo = getGroupDB();
    $pdo->prepare("INSERT OR REPLACE INTO group_unlock (user_id, group_id, expires_at) VALUES (?, ?, ?)")->execute([$userId, $groupId, time() + 300]);
}

function lockGroup($userId, $groupId) {
    $pdo = getGroupDB();
    $pdo->prepare("DELETE FROM group_unlock WHERE user_id = ? AND group_id = ?")->execute([$userId, $groupId]);
}

function cleanupExpiredGroupMessages() {
    $pdo = getGroupDB();
    $now = time();
    
    // Get all group_messages that have an expiry (sender has delete_at)
    // and check if ALL members have viewed/expired
    $stmt = $pdo->query("SELECT gm.id, gm.group_id FROM group_messages gm WHERE gm.delete_at > 0");
    $messages = $stmt->fetchAll();
    
    foreach ($messages as $msg) {
        $msgId = $msg['id'];
        $groupId = $msg['group_id'];
        
        // Get total members count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ?");
        $stmt->execute([$groupId]);
        $totalMembers = $stmt->fetchColumn();
        
        // Get viewer count (including sender who auto-viewed)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_viewed WHERE message_id = ?");
        $stmt->execute([$msgId]);
        $viewerCount = $stmt->fetchColumn();
        
        // If all members have viewed, mark message for global deletion
        if ($viewerCount >= $totalMembers && $totalMembers > 0) {
            // Set global delete_at for the message itself
            $stmt = $pdo->prepare("UPDATE group_messages SET delete_at = ? WHERE id = ?");
            $stmt->execute([$now + 30, $msgId]);
        }
    }
    
    // Delete messages where global delete_at has passed
    $stmt = $pdo->prepare("DELETE FROM group_messages WHERE delete_at > 0 AND delete_at <= ?");
    $stmt->execute([$now]);
    
    // Clean up viewer records for deleted messages
    $stmt = $pdo->prepare("DELETE FROM group_viewed WHERE delete_at < ?");
    $stmt->execute([$now]);
    
    // For old messages (>24h), clean up
    $oneDayAgo = date('Y-m-d H:i:s', strtotime('-1 day'));
    $stmt = $pdo->prepare("DELETE FROM group_messages WHERE created_at < ? AND delete_at = 0");
    $stmt->execute([$oneDayAgo]);
}
