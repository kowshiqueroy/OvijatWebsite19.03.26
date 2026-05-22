<?php
/**
 * Support Chat AJAX API
 */
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/AuthManager.php';
require_once 'includes/SupportManager.php';

header('Content-Type: application/json');

if (!AuthManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to chat.']);
    exit;
}

$sm = new SupportManager();
$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_messages':
        $threadId = (int)($_GET['thread_id'] ?? 0);
        
        // 1. Verify access: Admin/Staff can see all, Customers only their own
        $isAdmin = AuthManager::hasRole(['admin', 'editor', 'support']);
        $hasAccess = false;

        if ($isAdmin) {
            $hasAccess = true;
        } else {
            $thread = $db->query("SELECT id FROM chat_threads WHERE id = ? AND customer_id = ?", [$threadId, $userId])->fetch();
            if ($thread) $hasAccess = true;
        }

        if ($hasAccess) {
            // Mark as read based on who is viewing
            $sm->markAsRead($threadId, $isAdmin ? 'admin' : 'customer');
            echo json_encode(['success' => true, 'messages' => $sm->getMessages($threadId)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Thread not found or access denied.']);
        }
        break;

    case 'send_message':
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $senderType = $_POST['sender_type'] ?? 'customer';
        
        // Security check for admin sender
        if ($senderType === 'admin') {
            if (!AuthManager::hasRole(['admin', 'editor', 'support'])) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
                exit;
            }
        }

        if (!$threadId && $senderType === 'customer') {
            $threadId = $sm->startThread($userId, 'General Inquiry');
        }
        
        if ($message && $threadId) {
            $sm->sendMessage($threadId, $userId, $senderType, $message);
            echo json_encode(['success' => true, 'thread_id' => $threadId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid message or thread.']);
        }
        break;

    case 'get_threads':
        $threads = $db->query("SELECT * FROM chat_threads WHERE customer_id = ? ORDER BY last_message_at DESC", [$userId])->fetchAll();
        echo json_encode(['success' => true, 'threads' => $threads]);
        break;

    case 'get_recent_orders':
        $orders = $db->query("SELECT id, status, total_amount, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5", [$userId])->fetchAll();
        echo json_encode(['success' => true, 'orders' => $orders]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
