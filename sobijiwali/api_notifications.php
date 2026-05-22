<?php
/**
 * Notifications AJAX API
 */
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/AuthManager.php';
require_once 'includes/NotificationManager.php';

header('Content-Type: application/json');

if (!AuthManager::isLoggedIn()) {
    echo json_encode(['success' => false]);
    exit;
}

$nm = new NotificationManager();
$userId = $_SESSION['user_id'];

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_recent':
        echo json_encode(['success' => true, 'notifications' => $nm->getRecent($userId), 'unread_count' => $nm->getUnreadCount($userId)]);
        break;

    case 'mark_read':
        $nm->markAllAsRead($userId);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false]);
}
