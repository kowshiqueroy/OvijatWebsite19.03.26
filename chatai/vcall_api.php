<?php
// vcall_api.php - Standalone Signaling for VCall
header('Content-Type: application/json');

$db_file = __DIR__ . '/vcall.sq3';
try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("CREATE TABLE IF NOT EXISTS vcall_peers (
        user_id INTEGER PRIMARY KEY,
        peer_id TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die(json_encode(['error' => 'DB Connect Failed']));
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        $userId = (int)($_GET['user_id'] ?? 0);
        $peerId = $_GET['peer_id'] ?? '';
        if ($userId && $peerId) {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO vcall_peers (user_id, peer_id, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$userId, $peerId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'get_peer':
        $userId = (int)($_GET['user_id'] ?? 0);
        // Permissive 5-minute window for cross-timezone stability
        $stmt = $pdo->prepare("SELECT peer_id FROM vcall_peers WHERE user_id = ? AND updated_at > datetime('now', '-5 minutes') ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $peerId = $stmt->fetchColumn();
        echo json_encode(['peer_id' => $peerId]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
