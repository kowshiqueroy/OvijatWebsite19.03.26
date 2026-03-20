<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../functions.php';

requireLogin();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $notifs = [];
        while ($row = $stmt->get_result()->fetch_assoc()) {
            $notifs[] = $row;
        }
        echo json_encode($notifs);
        break;

    case 'mark_read':
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
        break;

    case 'count':
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['c'];
        echo json_encode(['count' => $count]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
