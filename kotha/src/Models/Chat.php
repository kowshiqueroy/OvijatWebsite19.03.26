<?php
// src/Models/Chat.php

namespace Models;

use Database;
use PDO;

class Chat {
    /**
     * Get or create a direct 1-on-1 chat between two users.
     */
    public static function getOrCreateDirectChat(int $userA, int $userB): string {
        $db = Database::getCoreConnection();

        // Sort IDs to ensure unique pairing
        $min = min($userA, $userB);
        $max = max($userA, $userB);
        $chatId = "1on1_{$min}_{$max}";

        // Check if chat index already exists
        $stmt = $db->prepare("SELECT chat_id FROM chats_index WHERE chat_id = ?");
        $stmt->execute([$chatId]);
        if ($stmt->fetch()) {
            return $chatId;
        }

        // Create chat index and add participants in a transaction
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO chats_index (chat_id, chat_type) VALUES (?, 'direct')");
            $stmt->execute([$chatId]);

            $stmt = $db->prepare("INSERT INTO chat_participants (chat_id, user_id) VALUES (?, ?)");
            $stmt->execute([$chatId, $userA]);
            $stmt->execute([$chatId, $userB]);

            $db->commit();

            // Initialize chat shard database
            Database::getChatConnection($chatId);

            return $chatId;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Create a group chat.
     */
    public static function createGroup(string $name, int $creatorId, array $participantIds): string {
        $db = Database::getCoreConnection();
        $chatId = "group_" . bin2hex(random_bytes(8));

        $db->beginTransaction();
        try {
            // Insert index
            $stmt = $db->prepare("INSERT INTO chats_index (chat_id, chat_type) VALUES (?, 'group')");
            $stmt->execute([$chatId]);

            // Insert group metadata
            $stmt = $db->prepare("INSERT INTO groups (group_id, name, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$chatId, $name, $creatorId]);

            // Add creator as participant
            $stmt = $db->prepare("INSERT INTO chat_participants (chat_id, user_id) VALUES (?, ?)");
            $stmt->execute([$chatId, $creatorId]);

            // Add other participants
            foreach ($participantIds as $pId) {
                if ($pId != $creatorId) {
                    $stmt->execute([$chatId, $pId]);
                }
            }

            $db->commit();

            // Initialize shard
            Database::getChatConnection($chatId);

            return $chatId;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Fetch all active chats for a user, including display name, nickname, last message, and unread counts.
     */
    public static function getActiveChatsForUser(int $userId): array {
        $db = Database::getCoreConnection();
        
        // Find all chats the user is participating in
        $stmt = $db->prepare("
            SELECT c.chat_id, c.chat_type 
            FROM chats_index c
            JOIN chat_participants cp ON c.chat_id = cp.chat_id
            WHERE cp.user_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$userId]);
        $chats = $stmt->fetchAll();

        $activeChats = [];

        foreach ($chats as $c) {
            $chatId = $c['chat_id'];
            $chatType = $c['chat_type'];
            $displayName = '';
            $avatarName = '';
            
            if ($chatType === 'direct') {
                // Get other participant
                $stmtPart = $db->prepare("
                    SELECT u.id, u.full_name, u.email 
                    FROM chat_participants cp
                    JOIN users u ON cp.user_id = u.id
                    WHERE cp.chat_id = ? AND cp.user_id != ?
                    LIMIT 1
                ");
                $stmtPart->execute([$chatId, $userId]);
                $partner = $stmtPart->fetch();
                
                if (!$partner) continue; // Partner deleted/not found

                // Check for local nickname
                $stmtNick = $db->prepare("
                    SELECT nickname FROM nicknames 
                    WHERE user_id = ? AND target_type = 'user' AND target_id = ?
                    LIMIT 1
                ");
                $stmtNick->execute([$userId, (string)$partner['id']]);
                $nicknameRow = $stmtNick->fetch();

                $displayName = $nicknameRow ? $nicknameRow['nickname'] : $partner['full_name'];
                $avatarName = $partner['full_name'];
                $partnerId = $partner['id'];
            } else {
                // Group chat
                $stmtGrp = $db->prepare("SELECT name FROM groups WHERE group_id = ? LIMIT 1");
                $stmtGrp->execute([$chatId]);
                $group = $stmtGrp->fetch();
                
                if (!$group) continue;

                // Check for local nickname for group
                $stmtNick = $db->prepare("
                    SELECT nickname FROM nicknames 
                    WHERE user_id = ? AND target_type = 'chat' AND target_id = ?
                    LIMIT 1
                ");
                $stmtNick->execute([$userId, $chatId]);
                $nicknameRow = $stmtNick->fetch();

                $displayName = $nicknameRow ? $nicknameRow['nickname'] : $group['name'];
                $avatarName = $group['name'];
                $partnerId = null;
            }

            // Retrieve last message and unread count from shard database
            $lastMessage = null;
            $unreadCount = 0;
            $chatDbFile = CHAT_DB_DIR . "/chat_{$chatId}.sqlite";

            if (file_exists($chatDbFile)) {
                try {
                    $chatPdo = Database::getChatConnection($chatId);
                    
                    // Last message
                    $stmtMsg = $chatPdo->query("SELECT * FROM messages ORDER BY created_at DESC, id DESC LIMIT 1");
                    $lastMessage = $stmtMsg->fetch();

                    // Unread count
                    $stmtUnread = $chatPdo->prepare("SELECT COUNT(*) as count FROM messages WHERE sender_id != ? AND is_read = 0");
                    $stmtUnread->execute([$userId]);
                    $unreadCount = $stmtUnread->fetch()['count'];
                } catch (\PDOException $e) {
                    // Fail gracefully if shard is temporarily locked/unreadable
                    $lastMessage = null;
                    $unreadCount = 0;
                }
            }

            $isOnline = false;
            $partnerLastSeen = null;
            if ($chatType === 'direct' && isset($partnerId)) {
                $stmtSeen = $db->prepare("SELECT last_seen FROM users WHERE id = ?");
                $stmtSeen->execute([$partnerId]);
                $seenRow = $stmtSeen->fetch();
                if ($seenRow && !empty($seenRow['last_seen'])) {
                    $partnerLastSeen = $seenRow['last_seen'];
                    $lastSeenTime = is_numeric($partnerLastSeen) ? intval($partnerLastSeen) : strtotime($partnerLastSeen);
                    if (time() - $lastSeenTime < 30) {
                        $isOnline = true;
                    }
                }
            }

            $activeChats[] = [
                'chat_id' => $chatId,
                'chat_type' => $chatType,
                'display_name' => $displayName,
                'avatar_name' => $avatarName,
                'partner_id' => $partnerId ?? null,
                'last_message' => $lastMessage,
                'unread_count' => $unreadCount,
                'is_online' => $isOnline,
                'last_seen' => $partnerLastSeen
            ];
        }

        // Sort active chats so that those with a last message appear first, sorted by last message time
        usort($activeChats, function($a, $b) {
            $timeA = $a['last_message'] ? strtotime($a['last_message']['created_at']) : 0;
            $timeB = $b['last_message'] ? strtotime($b['last_message']['created_at']) : 0;
            return $timeB <=> $timeA;
        });

        return $activeChats;
    }

    /**
     * Get all participants in a chat.
     */
    public static function getParticipants(string $chatId): array {
        $db = Database::getCoreConnection();
        $stmt = $db->prepare("
            SELECT u.id, u.email, u.full_name 
            FROM chat_participants cp
            JOIN users u ON cp.user_id = u.id
            WHERE cp.chat_id = ?
        ");
        $stmt->execute([$chatId]);
        return $stmt->fetchAll();
    }

    /**
     * Send a message to a shard database.
     */
    public static function addMessage(
        string $chatId, 
        int $senderId, 
        string $msgType, 
        string $content, 
        ?string $filePath = null
    ): array {
        $chatPdo = Database::getChatConnection($chatId);
        
        $msgId = 'msg_' . bin2hex(random_bytes(10));
        $createdAt = gmdate('Y-m-d H:i:s');

        $stmt = $chatPdo->prepare("
            INSERT INTO messages (id, sender_id, message_type, content, file_path, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, 0, ?)
        ");
        $stmt->execute([$msgId, $senderId, $msgType, $content, $filePath, $createdAt]);

        return [
            'id' => $msgId,
            'sender_id' => $senderId,
            'message_type' => $msgType,
            'content' => $content,
            'file_path' => $filePath,
            'is_read' => 0,
            'created_at' => $createdAt
        ];
    }

    /**
     * Get messages from a shard database.
     */
    public static function getMessages(string $chatId, int $userId = 0): array {
        $chatDbFile = CHAT_DB_DIR . "/chat_{$chatId}.sqlite";
        if (!file_exists($chatDbFile)) {
            return [];
        }

        $chatPdo = Database::getChatConnection($chatId);
        if ($userId > 0) {
            $stmt = $chatPdo->prepare("
                SELECT * FROM messages 
                WHERE id NOT IN (
                    SELECT message_id FROM user_vanished_messages WHERE user_id = ?
                ) 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } else {
            $stmt = $chatPdo->query("SELECT * FROM messages ORDER BY created_at ASC");
            return $stmt->fetchAll();
        }
    }

    /**
     * Mark all messages in a chat as read (except those sent by the current user).
     */
    public static function markAsRead(string $chatId, int $userId): void {
        $chatDbFile = CHAT_DB_DIR . "/chat_{$chatId}.sqlite";
        if (file_exists($chatDbFile)) {
            $chatPdo = Database::getChatConnection($chatId);
            $stmt = $chatPdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id != ?");
            $stmt->execute([$userId]);
        }
    }

    /**
     * Hard-delete a message from a shard.
     * $forceDelete = true bypasses the per-user vanish-count logic (used by admin).
     */
    public static function hardDeleteMessage(string $chatId, string $messageId, int $userId, bool $forceDelete = false): bool {
        $chatDbFile = CHAT_DB_DIR . "/chat_{$chatId}.sqlite";
        if (!file_exists($chatDbFile)) {
            return false;
        }

        $chatPdo = Database::getChatConnection($chatId);

        // Admin force-delete: bypass participant vanish logic, remove immediately
        if ($forceDelete) {
            $stmt = $chatPdo->prepare("SELECT file_path FROM messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $row = $stmt->fetch();
            if ($row && !empty($row['file_path'])) {
                self::_safeUnlinkUpload($row['file_path']);
            }
            $chatPdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$messageId]);
            $chatPdo->prepare("DELETE FROM user_vanished_messages WHERE message_id = ?")->execute([$messageId]);
            return true;
        }

        // Normal vanish flow: log this user, delete only when all participants have vanished
        $stmtIns = $chatPdo->prepare("INSERT OR IGNORE INTO user_vanished_messages (message_id, user_id) VALUES (?, ?)");
        $stmtIns->execute([$messageId, $userId]);

        $participants      = self::getParticipants($chatId);
        $totalParticipants = count($participants);

        $stmtCount = $chatPdo->prepare("SELECT COUNT(*) as count FROM user_vanished_messages WHERE message_id = ?");
        $stmtCount->execute([$messageId]);
        $vanishedCount = $stmtCount->fetch()['count'];

        if ($vanishedCount >= $totalParticipants) {
            $stmt = $chatPdo->prepare("SELECT file_path FROM messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $row = $stmt->fetch();
            if ($row && !empty($row['file_path'])) {
                self::_safeUnlinkUpload($row['file_path']);
            }
            $chatPdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$messageId]);
            $chatPdo->prepare("DELETE FROM user_vanished_messages WHERE message_id = ?")->execute([$messageId]);
        }
        return true;
    }

    /** Safely delete a file only if it resolves inside UPLOAD_DIR (prevents path traversal). */
    private static function _safeUnlinkUpload(string $filePath): void {
        $uploadsReal = realpath(UPLOAD_DIR);
        if (!$uploadsReal) return;
        $target = realpath(BASE_DIR . '/public' . '/' . ltrim($filePath, '/'));
        if ($target && strpos($target, $uploadsReal) === 0 && file_exists($target)) {
            @unlink($target);
        }
    }

    /**
     * Set a local nickname.
     */
    public static function setNickname(int $userId, string $targetType, string $targetId, string $nickname): bool {
        $db = Database::getCoreConnection();
        
        $stmt = $db->prepare("
            INSERT INTO nicknames (user_id, target_type, target_id, nickname)
            VALUES (?, ?, ?, ?)
            ON CONFLICT(user_id, target_type, target_id) DO UPDATE SET nickname = excluded.nickname
        ");
        
        return $stmt->execute([$userId, $targetType, $targetId, trim($nickname)]);
    }

    /** Add a user to a group chat. */
    public static function addMember(string $chatId, int $userId): bool {
        $db = Database::getCoreConnection();
        return $db->prepare("INSERT OR IGNORE INTO chat_participants (chat_id, user_id) VALUES (?,?)")
                  ->execute([$chatId, $userId]);
    }

    /** Remove a user from a group chat. */
    public static function removeMember(string $chatId, int $userId): bool {
        $db = Database::getCoreConnection();
        return $db->prepare("DELETE FROM chat_participants WHERE chat_id=? AND user_id=?")
                  ->execute([$chatId, $userId]);
    }

    /**
     * Check if a user is a participant of a chat.
     */
    public static function isParticipant(string $chatId, int $userId): bool {
        $db = Database::getCoreConnection();
        $stmt = $db->prepare("
            SELECT 1 FROM chat_participants 
            WHERE chat_id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$chatId, $userId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Purge orphaned rows from user_vanished_messages across every chat shard.
     * A row is orphaned when its parent message was already hard-deleted.
     * Returns ['shards' => N, 'rows' => N].
     */
    public static function purgeVanishedMessages(): array {
        $result = ['shards' => 0, 'rows' => 0];
        $files  = glob(CHAT_DB_DIR . '/chat_*.sqlite') ?: [];

        foreach ($files as $file) {
            $rawId  = basename($file, '.sqlite'); // chat_1on1_1_2
            $chatId = substr($rawId, 5);          // 1on1_1_2
            try {
                $pdo = Database::getChatConnection($chatId);
                $pdo->exec("DELETE FROM user_vanished_messages
                             WHERE message_id NOT IN (SELECT id FROM messages)");
                $result['rows']  += (int)$pdo->query("SELECT changes()")->fetchColumn();
                $result['shards']++;
            } catch (\Exception $e) { /* skip locked / corrupt shards */ }
        }
        return $result;
    }

    /**
     * Delete every message (+ associated upload files + vanish records) from one chat shard.
     */
    public static function purgeAllMessages(string $chatId): bool {
        $clean  = preg_replace('/[^a-zA-Z0-9_]/', '', $chatId);
        $dbFile = CHAT_DB_DIR . '/chat_' . $clean . '.sqlite';
        if (!file_exists($dbFile)) return false;

        try {
            $pdo = Database::getChatConnection($chatId);

            // Delete upload files referenced by messages
            $stmt = $pdo->query("SELECT file_path FROM messages
                                  WHERE file_path IS NOT NULL AND file_path != ''");
            foreach ($stmt->fetchAll() as $row) {
                self::_safeUnlinkUpload($row['file_path']);
            }

            $pdo->exec("DELETE FROM messages");
            $pdo->exec("DELETE FROM user_vanished_messages");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete every message from ALL chat shards (nuclear wipe).
     * Returns ['shards' => N, 'messages' => N].
     */
    public static function purgeAllChatsMessages(): array {
        $result = ['shards' => 0, 'messages' => 0];
        $files  = glob(CHAT_DB_DIR . '/chat_*.sqlite') ?: [];

        foreach ($files as $file) {
            $chatId = substr(basename($file, '.sqlite'), 5);
            try {
                $pdo = Database::getChatConnection($chatId);

                $result['messages'] += (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();

                $stmt = $pdo->query("SELECT file_path FROM messages
                                      WHERE file_path IS NOT NULL AND file_path != ''");
                foreach ($stmt->fetchAll() as $row) {
                    self::_safeUnlinkUpload($row['file_path']);
                }

                $pdo->exec("DELETE FROM messages");
                $pdo->exec("DELETE FROM user_vanished_messages");
                $result['shards']++;
            } catch (\Exception $e) { /* skip */ }
        }
        return $result;
    }

    /**
     * Scan all chat shard databases and return media file records.
     * Returns up to $limit rows, newest first.
     * Each row: chat_id, message_id, message_type, file_path, file_exists,
     *            file_size, created_at, is_vanishing (any vanish record exists).
     */
    public static function getAllChatMedia(int $limit = 600): array {
        $shards = glob(CHAT_DB_DIR . '/chat_*.sqlite') ?: [];
        $media  = [];

        foreach ($shards as $shardFile) {
            $chatId = substr(basename($shardFile, '.sqlite'), 5);
            try {
                $pdo  = Database::getChatConnection($chatId);
                $stmt = $pdo->query("
                    SELECT m.id, m.sender_id, m.message_type, m.file_path, m.created_at,
                           (SELECT COUNT(*) FROM user_vanished_messages v WHERE v.message_id = m.id) AS vanish_count
                    FROM messages m
                    WHERE m.file_path IS NOT NULL AND m.file_path != ''
                    ORDER BY m.created_at DESC
                    LIMIT 300
                ");
                foreach ($stmt->fetchAll() as $row) {
                    $rel  = ltrim($row['file_path'], '/');
                    $full = BASE_DIR . '/public/' . $rel;
                    $media[] = [
                        'chat_id'      => $chatId,
                        'message_id'   => $row['id'],
                        'message_type' => $row['message_type'],
                        'file_path'    => $row['file_path'],
                        'file_exists'  => file_exists($full),
                        'file_size'    => file_exists($full) ? filesize($full) : 0,
                        'created_at'   => $row['created_at'],
                        'is_vanishing' => (int)$row['vanish_count'] > 0,
                    ];
                }
            } catch (\Exception $e) { /* skip corrupt/locked shards */ }
        }

        // Sort newest first, cap total
        usort($media, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return array_slice($media, 0, $limit);
    }

    /**
     * Delete a single media file and its parent message from the shard.
     * Returns: 'deleted' | 'no_file' | 'not_found'
     */
    public static function deleteMediaFile(string $chatId, string $messageId): string {
        $clean  = preg_replace('/[^a-zA-Z0-9_]/', '', $chatId);
        $dbFile = CHAT_DB_DIR . '/chat_' . $clean . '.sqlite';
        if (!file_exists($dbFile)) return 'not_found';

        try {
            $pdo  = Database::getChatConnection($chatId);
            $stmt = $pdo->prepare("SELECT file_path FROM messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $row  = $stmt->fetch();
            if (!$row) return 'not_found';

            if (!empty($row['file_path'])) self::_safeUnlinkUpload($row['file_path']);

            $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$messageId]);
            $pdo->prepare("DELETE FROM user_vanished_messages WHERE message_id = ?")->execute([$messageId]);
            return 'deleted';
        } catch (\Exception $e) { return 'not_found'; }
    }

    /**
     * Delete ALL media files from every chat shard (images, videos, audio, files).
     * Returns ['files' => N, 'messages' => N].
     */
    public static function deleteAllChatMedia(): array {
        $result = ['files' => 0, 'messages' => 0];
        $shards = glob(CHAT_DB_DIR . '/chat_*.sqlite') ?: [];

        foreach ($shards as $shardFile) {
            $chatId = substr(basename($shardFile, '.sqlite'), 5);
            try {
                $pdo  = Database::getChatConnection($chatId);
                $rows = $pdo->query("SELECT id, file_path FROM messages
                                      WHERE file_path IS NOT NULL AND file_path != ''")->fetchAll();
                foreach ($rows as $r) {
                    if (!empty($r['file_path'])) {
                        self::_safeUnlinkUpload($r['file_path']);
                        $result['files']++;
                    }
                    $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$r['id']]);
                    $pdo->prepare("DELETE FROM user_vanished_messages WHERE message_id = ?")->execute([$r['id']]);
                    $result['messages']++;
                }
            } catch (\Exception $e) { /* skip */ }
        }
        return $result;
    }

    /**
     * Delete media files for messages that are currently in the vanishing state
     * (at least one participant has triggered the vanish timer).
     * Returns ['files' => N, 'messages' => N].
     */
    public static function deleteVanishingMedia(): array {
        $result = ['files' => 0, 'messages' => 0];
        $shards = glob(CHAT_DB_DIR . '/chat_*.sqlite') ?: [];

        foreach ($shards as $shardFile) {
            $chatId = substr(basename($shardFile, '.sqlite'), 5);
            try {
                $pdo  = Database::getChatConnection($chatId);
                $rows = $pdo->query("
                    SELECT DISTINCT m.id, m.file_path
                    FROM messages m
                    JOIN user_vanished_messages v ON v.message_id = m.id
                    WHERE m.file_path IS NOT NULL AND m.file_path != ''
                ")->fetchAll();
                foreach ($rows as $r) {
                    self::_safeUnlinkUpload($r['file_path']);
                    $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$r['id']]);
                    $pdo->prepare("DELETE FROM user_vanished_messages WHERE message_id = ?")->execute([$r['id']]);
                    $result['files']++;
                    $result['messages']++;
                }
            } catch (\Exception $e) { /* skip */ }
        }
        return $result;
    }

    /**
     * List upload files on disk that are NOT referenced by any message in any shard.
     * These accumulate from aborted uploads or messages that were deleted without file cleanup.
     */
    public static function getOrphanedUploads(): array {
        // Collect all file_path references from every shard
        $referenced = [];
        $shards = glob(CHAT_DB_DIR . '/chat_*.sqlite') ?: [];
        foreach ($shards as $shardFile) {
            $chatId = substr(basename($shardFile, '.sqlite'), 5);
            try {
                $pdo  = Database::getChatConnection($chatId);
                $stmt = $pdo->query("SELECT file_path FROM messages WHERE file_path IS NOT NULL AND file_path != ''");
                foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN, 0) as $fp) {
                    $referenced[basename($fp)] = true;
                }
            } catch (\Exception $e) { /* skip */ }
        }

        // Scan uploads directory for actual files (skip temp_upload_* dirs)
        $orphaned = [];
        $uploadsDir = UPLOAD_DIR;
        foreach (glob($uploadsDir . '/*') as $path) {
            if (is_dir($path)) continue;               // skip subdirectories
            $name = basename($path);
            if (!isset($referenced[$name])) {
                $orphaned[] = [
                    'filename' => $name,
                    'path'     => $path,
                    'size'     => filesize($path),
                    'modified' => filemtime($path),
                ];
            }
        }
        usort($orphaned, fn($a, $b) => $b['modified'] - $a['modified']);
        return $orphaned;
    }

    /**
     * Publish an event to the sse_events table for real-time notifications.
     * Cleanup runs only every ~50 publishes to avoid per-message DELETE overhead.
     */
    public static function publishEvent(string $chatId, int $senderId, string $eventType, array $payload): void {
        $db = Database::getCoreConnection();
        $db->prepare("
            INSERT INTO sse_events (chat_id, sender_id, event_type, payload)
            VALUES (?, ?, ?, ?)
        ")->execute([$chatId, $senderId, $eventType, json_encode($payload)]);

        // Batch cleanup — cheap random gate so cleanup is not per-message
        if (random_int(1, 50) === 1) {
            $db->exec("DELETE FROM sse_events WHERE created_at < datetime('now', '-10 minutes')");
        }
    }
}
