<?php
session_start();
require_once 'db.php';

if (!isLoggedIn()) {
    die("Unauthorized");
}

$msgId = (int)($_GET['id'] ?? 0);
if ($msgId > 0) {
    $pdo = getDB();
    // Check if the user is the sender or receiver
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
    $stmt->execute([$msgId, $_SESSION['user_id'], $_SESSION['user_id']]);
    $msg = $stmt->fetch();

    if ($msg) {
        // Soft delete - mark as deleted instead of actually deleting
        $pdo->prepare("UPDATE messages SET delete_at = 1 WHERE id = ?")->execute([$msgId]);
        echo "OK";
    } else {
        echo "Access Denied";
    }
} else {
    echo "Invalid ID";
}