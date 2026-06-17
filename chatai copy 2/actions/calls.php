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
        $stmtStatus = $pdo->prepare("SELECT last_seen FROM user_status WHERE user_id = ?");
        $stmtStatus->execute([$other_user_id]);
        $lastSeen = $stmtStatus->fetchColumn();
        
        if (!$lastSeen || (time() - $lastSeen) > 30) {
            echo json_encode(array('peer_id' => null, 'offline' => true));
            break;
        }

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

    case 'save_vcall_recording':
        if ($user_id != 1) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); break; }
        if (!isset($_FILES['recording'])) { echo json_encode(['success' => false, 'error' => 'No file']); break; }
        
        $file = $_FILES['recording'];
        $sessionId = $_POST['session_id'] ?? 'vcall_' . time();
        $chunkIndex = (int)($_POST['chunk_index'] ?? 0);
        $mimeType = $_POST['mime_type'] ?? 'video/webm';
        
        $extension = (strpos($mimeType, 'mp4') !== false) ? 'mp4' : 'webm';
        $filename = 'v_rec_' . $sessionId . '.' . $extension;
        $targetPath = __DIR__ . '/../premium_vault/' . $filename;
        
        if (!file_exists(__DIR__ . '/../premium_vault/')) mkdir(__DIR__ . '/../premium_vault/', 0755, true);
        
        $content = file_get_contents($file['tmp_name']);
        if ($chunkIndex == 0) {
            if (file_put_contents($targetPath, $content)) {
                $dbPath = 'premium_vault/' . $filename;
                $stmt = $pdo->prepare('INSERT INTO saved_images (user_id, image_data, is_voice, is_video, file_hash) VALUES (?, ?, 0, 1, ?)');
                $stmt->execute([$user_id, $dbPath, md5($content)]);
                echo json_encode(['success' => true, 'filename' => $filename]);
            } else { echo json_encode(['success' => false, 'error' => 'Write failed']); }
        } else {
            if (file_put_contents($targetPath, $content, FILE_APPEND)) {
                echo json_encode(['success' => true, 'filename' => $filename, 'appended' => true]);
            } else { echo json_encode(['success' => false, 'error' => 'Append failed']); }
        }
        break;
}
