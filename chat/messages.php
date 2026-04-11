<?php
/**
 * Messages API
 * Handles sending messages, viewing with press-and-hold, and ephemeral deletion
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');
require_once __DIR__ . '/config.php';

session_start();
$userId = requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $contactId = (int)($_POST['contact_id'] ?? 0);
        $content = $_POST['content'] ?? '';
        $mediaData = $_POST['media'] ?? null;

        if (empty($contactId) || empty($content)) {
            exit(json_encode(['success' => false, 'message' => 'Invalid request']));
        }

        if (!isThreadUnlocked($contactId)) {
            exit(json_encode(['success' => false, 'message' => 'Thread locked']));
        }

        $contact = $db->fetchOne(
            "SELECT id, user_id, contact_user_id FROM contacts WHERE id = ? AND user_id = ?",
            [$contactId, $userId]
        );

        if (!$contact) {
            exit(json_encode(['success' => false, 'message' => 'Contact not found']));
        }

        $mediaPath = null;
        $mediaType = null;

        if ($mediaData) {
            $mediaInfo = saveMedia($mediaData, $contactId);
            if ($mediaInfo) {
                $mediaPath = $mediaInfo['path'];
                $mediaType = $mediaInfo['type'];
            }
        }

        $db->query(
            "INSERT INTO messages (sender_id, receiver_id, contact_id, content, media_path, media_type) VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $contact['contact_user_id'], $contactId, $content, $mediaPath, $mediaType]
        );

        $receiverId = $contact['contact_user_id'];
        $existingReverse = $db->fetchOne(
            "SELECT id FROM contacts WHERE user_id = ? AND contact_user_id = ?",
            [$receiverId, $userId]
        );

        if (!$existingReverse) {
            $db->query(
                "INSERT INTO contacts (user_id, contact_user_id, display_name, thread_pin) VALUES (?, ?, ?, ?)",
                [$receiverId, $userId, 'User ' . $userId, substr(md5(time()), 0, 8)]
            );
        }

        $reverseContact = $db->fetchOne(
            "SELECT id FROM contacts WHERE user_id = ? AND contact_user_id = ?",
            [$receiverId, $userId]
        );

        if ($reverseContact) {
            $db->query(
                "INSERT INTO messages (sender_id, receiver_id, contact_id, content, media_path, media_type) VALUES (?, ?, ?, ?, ?, ?)",
                [$userId, $receiverId, $reverseContact['id'], $content, $mediaPath, $mediaType]
            );

            $db->query(
                "UPDATE contacts SET last_message_at = NOW() WHERE id = ?",
                [$reverseContact['id']]
            );
        }

        $db->query(
            "UPDATE contacts SET last_message_at = NOW() WHERE id = ?",
            [$contactId]
        );

        exit(json_encode(['success' => true]));
    }

    if ($action === 'get_messages') {
        $contactId = (int)($_POST['contact_id'] ?? 0);
        $limit = (int)($_POST['limit'] ?? 50);

        if (empty($contactId)) {
            exit(json_encode(['success' => false, 'message' => 'Invalid request']));
        }

        if (!isThreadUnlocked($contactId)) {
            exit(json_encode(['success' => false, 'message' => 'Thread locked', 'locked' => true]));
        }

        $contact = $db->fetchOne(
            "SELECT id, user_id, contact_user_id, display_name FROM contacts WHERE id = ? AND user_id = ?",
            [$contactId, $userId]
        );

        if (!$contact) {
            exit(json_encode(['success' => false, 'message' => 'Contact not found']));
        }

        $isDuress = $_SESSION['is_duress'] ?? false;

        if ($isDuress) {
            $messages = $db->fetchAll(
                "SELECT id, content, created_at FROM dummy_messages 
                 WHERE contact_id = ? ORDER BY created_at ASC LIMIT ?",
                [$contactId, $limit]
            );

            $messages = array_map(function($m) {
                return [
                    'id' => $m['id'],
                    'content' => $m['content'],
                    'created_at' => $m['created_at'],
                    'is_dummy' => true,
                    'view_duration' => 5 + (int)(strlen($m['content']) / 20)
                ];
            }, $messages);

            exit(json_encode([
                'success' => true,
                'messages' => $messages,
                'is_dummy' => true
            ]));
        }

        $messages = $db->fetchAll(
            "SELECT id, sender_id, receiver_id, content, media_path, media_type, created_at, viewed_at, deletion_at 
             FROM messages 
             WHERE contact_id = ? AND is_visible = 1 AND (sender_id = ? OR receiver_id = ?)
             ORDER BY created_at ASC LIMIT ?",
            [$contactId, $userId, $userId, $limit]
        );

        $camouflageMessages = [
            'System update pending. Restart required.',
            'Carrier settings updated successfully.',
            'Firmware version 2.4.1 installed.',
            'Network configuration synchronized.',
            'Security certificate renewed.',
            'Background optimization completed.',
            'Service pack installed.',
            'Configuration backup created.'
        ];

        $processedMessages = [];
        foreach ($messages as $m) {
            $viewDuration = 5 + (int)(strlen($m['content']) / 20);
            
            $processedMessages[] = [
                'id' => $m['id'],
                'sender_id' => $m['sender_id'],
                'is_me' => $m['sender_id'] == $userId,
                'real_content' => $m['content'],
                'camouflage' => $camouflageMessages[array_rand($camouflageMessages)],
                'media_path' => $m['media_path'],
                'media_type' => $m['media_type'],
                'created_at' => $m['created_at'],
                'viewed_at' => $m['viewed_at'],
                'view_duration' => $viewDuration,
                'is_dummy' => false
            ];
        }

        exit(json_encode([
            'success' => true,
            'messages' => $processedMessages,
            'contact_name' => $contact['display_name'],
            'is_dummy' => false
        ]));
    }

    if ($action === 'view_message') {
        $messageId = (int)($_POST['message_id'] ?? 0);

        if (empty($messageId)) {
            exit(json_encode(['success' => false, 'message' => 'Invalid request']));
        }

        $message = $db->fetchOne(
            "SELECT id, sender_id, receiver_id, contact_id, viewed_at, deletion_at FROM messages WHERE id = ?",
            [$messageId]
        );

        if (!$message) {
            exit(json_encode(['success' => false, 'message' => 'Message not found']));
        }

        $contact = $db->fetchOne(
            "SELECT id, user_id FROM contacts WHERE id = ? AND user_id = ?",
            [$message['contact_id'], $userId]
        );

        if (!$contact) {
            exit(json_encode(['success' => false, 'message' => 'Access denied']));
        }

        if (!$message['viewed_at']) {
            $db->query(
                "UPDATE messages SET viewed_at = NOW() WHERE id = ?",
                [$messageId]
            );
        }

        $deletionTime = $message['deletion_at'] ?: date('Y-m-d H:i:s', strtotime('+1 minute'));
        
        $db->query(
            "UPDATE messages SET deletion_at = ? WHERE id = ?",
            [$deletionTime, $messageId]
        );

        exit(json_encode([
            'success' => true,
            'viewed' => true,
            'deletion_scheduled' => $deletionTime
        ]));
    }

    if ($action === 'delete_expired') {
        $now = date('Y-m-d H:i:s');
        
        $expired = $db->fetchAll(
            "SELECT id, media_path FROM messages WHERE deletion_at IS NOT NULL AND deletion_at <= ?",
            [$now]
        );

        foreach ($expired as $msg) {
            if ($msg['media_path'] && file_exists($msg['media_path'])) {
                @unlink($msg['media_path']);
            }
        }

        $db->query(
            "DELETE FROM messages WHERE deletion_at IS NOT NULL AND deletion_at <= ?",
            [$now]
        );

        exit(json_encode([
            'success' => true,
            'deleted' => count($expired)
        ]));
    }

    if ($action === 'schedule_deletion') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        $deletionTime = date('Y-m-d H:i:s', strtotime('+1 minute'));

        $db->query(
            "UPDATE messages SET deletion_at = ? WHERE id = ?",
            [$deletionTime, $messageId]
        );

        exit(json_encode(['success' => true]));
    }
}

function saveMedia($base64Data, $contactId) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
    
    $data = base64_decode($base64Data);
    if (!$data) return null;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($data);

    if (!in_array($mimeType, $allowedTypes)) {
        return null;
    }

    $extension = '.bin';
    switch ($mimeType) {
        case 'image/jpeg': $extension = '.jpg'; break;
        case 'image/png': $extension = '.png'; break;
        case 'image/gif': $extension = '.gif'; break;
        case 'application/pdf': $extension = '.pdf'; break;
    }

    $dir = MEDIA_PATH . $contactId;
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $filename = generateToken(32) . $extension;
    $fullPath = $dir . '/' . $filename;

    if (file_put_contents($fullPath, $data) === false) {
        return null;
    }

    return ['path' => $fullPath, 'type' => $mimeType];
}

http_response_code(400);
exit(json_encode(['success' => false, 'message' => 'Invalid request']));