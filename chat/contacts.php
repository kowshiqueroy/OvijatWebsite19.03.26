<?php
/**
 * Contacts & Inbox API
 * Handles contact discovery, adding contacts, and inbox fetching
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');
require_once __DIR__ . '/config.php';

session_start();
$userId = requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'search') {
        $username = trim($_POST['username'] ?? '');
        
        if (empty($username) || strlen($username) < 3) {
            exit(json_encode(['success' => false, 'message' => 'Invalid username']));
        }

        $user = $db->fetchOne(
            "SELECT id, username, display_name FROM users WHERE username = ? AND id != ?",
            [$username, $userId]
        );

        if (!$user) {
            exit(json_encode(['success' => false, 'message' => 'User not found']));
        }

        $existing = $db->fetchOne(
            "SELECT id FROM contacts WHERE user_id = ? AND contact_user_id = ?",
            [$userId, $user['id']]
        );

        if ($existing) {
            exit(json_encode(['success' => false, 'message' => 'Contact already exists']));
        }

        exit(json_encode([
            'success' => true,
            'user' => ['id' => $user['id'], 'username' => $user['username']]
        ]));
    }

    if ($action === 'add') {
        $contactUsername = trim($_POST['contact_username'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $threadPin = $_POST['thread_pin'] ?? '';

        if (empty($contactUsername) || empty($displayName) || empty($threadPin)) {
            exit(json_encode(['success' => false, 'message' => 'All fields required']));
        }

        if (strlen($threadPin) < 4 || strlen($threadPin) > 10) {
            exit(json_encode(['success' => false, 'message' => 'PIN must be 4-10 characters']));
        }

        $contactUser = $db->fetchOne(
            "SELECT id, username FROM users WHERE username = ? AND id != ?",
            [$contactUsername, $userId]
        );

        if (!$contactUser) {
            exit(json_encode(['success' => false, 'message' => 'User not found']));
        }

        $existing = $db->fetchOne(
            "SELECT id FROM contacts WHERE user_id = ? AND contact_user_id = ?",
            [$userId, $contactUser['id']]
        );

        if ($existing) {
            exit(json_encode(['success' => false, 'message' => 'Contact already exists']));
        }

        $db->query(
            "INSERT INTO contacts (user_id, contact_user_id, display_name, thread_pin) VALUES (?, ?, ?, ?)",
            [$userId, $contactUser['id'], $displayName, $threadPin]
        );

        exit(json_encode(['success' => true, 'message' => 'Contact added']));
    }

    if ($action === 'inbox') {
        if ($_SESSION['is_duress'] ?? false) {
            $contacts = $db->fetchAll(
                "SELECT dc.id, dc.display_name, dc.last_message_at,
                    (SELECT content FROM dummy_messages WHERE contact_id = dc.id ORDER BY created_at DESC LIMIT 1) as last_message
                 FROM dummy_contacts dc WHERE dc.user_id = ? ORDER BY dc.last_message_at DESC",
                [$userId]
            );

            $inbox = array_map(function($c) {
                return [
                    'id' => $c['id'],
                    'display_name' => $c['display_name'],
                    'last_message' => $c['last_message'] ?? 'No messages',
                    'last_message_at' => $c['last_message_at'],
                    'is_dummy' => true
                ];
            }, $contacts);

            exit(json_encode([
                'success' => true,
                'inbox' => $inbox,
                'is_duress' => true
            ]));
        }

        $contacts = $db->fetchAll(
            "SELECT c.id, c.display_name, c.thread_pin, c.last_message_at,
                u.username as contact_username
             FROM contacts c
             JOIN users u ON c.contact_user_id = u.id
             WHERE c.user_id = ?
             ORDER BY c.last_message_at DESC",
            [$userId]
        );

        $inbox = [];
        foreach ($contacts as $c) {
            $lastMsg = $db->fetchOne(
                "SELECT content, created_at, viewed_at, deletion_at FROM messages 
                 WHERE contact_id = ? AND (sender_id = ? OR receiver_id = ?) 
                 ORDER BY created_at DESC LIMIT 1",
                [$c['id'], $userId, $userId]
            );

            $camouflageMessages = [
                'System update pending. Restart required.',
                'Carrier settings updated.',
                'Firmware check completed.',
                'Network configuration synchronized.',
                'Security certificate renewed.',
                'System optimization in progress.',
                'Background service refreshed.',
                'Configuration backup completed.'
            ];

            $inbox[] = [
                'id' => $c['id'],
                'display_name' => $c['display_name'],
                'thread_pin' => $c['thread_pin'],
                'contact_username' => $c['contact_username'],
                'last_message' => $lastMsg ? $camouflageMessages[array_rand($camouflageMessages)] : 'No messages',
                'last_message_at' => $c['last_message_at'],
                'has_unread' => $lastMsg && !$lastMsg['viewed_at'],
                'is_dummy' => false
            ];
        }

        exit(json_encode([
            'success' => true,
            'inbox' => $inbox,
            'is_duress' => false
        ]));
    }

    if ($action === 'unlock') {
        $contactId = (int)($_POST['contact_id'] ?? 0);
        $pin = $_POST['pin'] ?? '';

        if (empty($contactId) || empty($pin)) {
            exit(json_encode(['success' => false, 'message' => 'Invalid request']));
        }

        if ($_SESSION['is_duress'] ?? false) {
            $_SESSION['inbox_unlocked'] = true;
            $_SESSION['unlocked_threads'][$contactId] = time() + INBOX_LOCK_TIMEOUT;
            exit(json_encode(['success' => true]));
        }

        $contact = $db->fetchOne(
            "SELECT id, thread_pin FROM contacts WHERE id = ? AND user_id = ?",
            [$contactId, $userId]
        );

        if (!$contact) {
            exit(json_encode(['success' => false, 'message' => 'Contact not found']));
        }

        if ($pin === '0000') {
            $_SESSION['is_duress'] = true;
            $_SESSION['inbox_unlocked'] = true;
            exit(json_encode(['success' => true, 'duress_activated' => true]));
        }

        if ($pin !== $contact['thread_pin']) {
            exit(json_encode(['success' => false, 'message' => 'Invalid PIN']));
        }

        $_SESSION['inbox_unlocked'] = true;
        $_SESSION['unlocked_threads'][$contactId] = time() + INBOX_LOCK_TIMEOUT;
        
        exit(json_encode(['success' => true]));
    }

    if ($action === 'lock') {
        lockAllThreads();
        $_SESSION['inbox_unlocked'] = false;
        exit(json_encode(['success' => true]));
    }

    if ($action === 'status') {
        checkInactivity();
        exit(json_encode([
            'success' => true,
            'inbox_unlocked' => isInboxUnlocked(),
            'is_duress' => $_SESSION['is_duress'] ?? false
        ]));
    }
}

http_response_code(400);
exit(json_encode(['success' => false, 'message' => 'Invalid request']));