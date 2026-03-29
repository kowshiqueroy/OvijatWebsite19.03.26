<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireRole('member');
$user = currentUser();
validateCsrf();

header('Content-Type: application/json');
$action = $_POST['action'] ?? '';

if ($action === 'get') {
    $notifs = dbFetchAll("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20", [$user['id']]);
    $unread = (int)(dbFetch("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0", [$user['id']])['c'] ?? 0);
    echo json_encode(['success' => true, 'data' => ['notifications' => $notifs, 'unread' => $unread]]);
} elseif ($action === 'mark_read') {
    dbQuery("UPDATE notifications SET is_read=1 WHERE user_id=?", [$user['id']]);
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
