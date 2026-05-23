<?php
switch ($action) {
    case 'register_peer':
        $data = json_decode(file_get_contents('php://input'), true);
        $peerId = $data['peer_id'] ?? '';
        if (empty($peerId)) {
            echo json_encode(array('success' => false));
            break;
        }
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
        $stmt->execute(array('peer_' . $user_id, $peerId));
        echo json_encode(array('success' => true));
        break;

    case 'get_peer':
        $other_user_id = ($user_id == 1) ? 2 : 1;
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute(array('peer_' . $other_user_id));
        $peerId = $stmt->fetchColumn();
        echo json_encode(array('peer_id' => $peerId));
        break;

    case 'send_call_comment':
        $data = json_decode(file_get_contents('php://input'), true);
        $text = trim($data['content'] ?? '');
        if (!$text) { echo json_encode(array('success' => false, 'error' => 'Empty')); break; }
        $receiver_id = ($user_id == 1) ? 2 : 1;
        $stmt = $pdo->prepare('INSERT INTO call_comments (sender_id, receiver_id, content) VALUES (?, ?, ?)');
        $stmt->execute(array($user_id, $receiver_id, $text));
        echo json_encode(array('success' => true, 'id' => $pdo->lastInsertId(), 'created_at' => gmdate('Y-m-d\TH:i:s\Z')));
        break;

    case 'get_call_comments':
        $other_id = ($user_id == 1) ? 2 : 1;
        $stmt = $pdo->prepare("SELECT id, sender_id, content, strftime('%Y-%m-%dT%H:%M:%SZ', created_at) as created_at FROM call_comments WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND deleted_at IS NULL AND created_at > datetime('now', '-10 seconds') ORDER BY created_at ASC");
        $stmt->execute(array($user_id, $other_id, $other_id, $user_id));
        echo json_encode($stmt->fetchAll());
        break;

    case 'delete_call_comment':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;
        $stmt = $pdo->prepare("UPDATE call_comments SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND sender_id = ?");
        $stmt->execute(array($id, $user_id));
        echo json_encode(array('success' => true));
        break;
}
