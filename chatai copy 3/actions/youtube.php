<?php
switch ($action) {
    case 'get_youtube_sync':
        $stmt = $pdo->query("SELECT * FROM youtube_sync WHERE id = 1");
        $data = $stmt->fetch();
        
        $other_user_id = ($user_id == 1) ? 2 : 1;
        $stmtStatus = $pdo->prepare("SELECT last_seen, in_theater FROM user_status WHERE user_id = ?");
        $stmtStatus->execute([$other_user_id]);
        $status = $stmtStatus->fetch();
        $other_online = $status && (time() - $status['last_seen']) < 12;
        
        $data['other_online'] = (bool)$other_online;
        $data['other_in_theater'] = $other_online && $status['in_theater'] == 1;
        $data['server_time'] = microtime(true);
        echo json_encode($data);
        break;

    case 'update_youtube_sync':
        $data = json_decode(file_get_contents('php://input'), true);
        $video_id = $data['video_id'] ?? null;
        $state = $data['state'] ?? null;
        $current_time = $data['current_time'] ?? null;
        $ready = $data['ready'] ?? null;
        $comments_enabled = $data['comments_enabled'] ?? null;
        $video_title = $data['video_title'] ?? '';
        $now = microtime(true);
        
        $ready_col = ($user_id == 1) ? 'ready_user1' : 'ready_user2';

        if ($video_id !== null) {
            $stmtCheck = $pdo->query("SELECT video_id FROM youtube_sync WHERE id = 1");
            $currentVideoId = $stmtCheck->fetchColumn();
            
            if ($video_id !== $currentVideoId) {
                $stmt = $pdo->prepare("UPDATE youtube_sync SET video_id = ?, state = 0, current_time = 0, last_updated_by = ?, updated_at = ?, ready_user1 = 0, ready_user2 = 0 WHERE id = 1");
                $stmt->execute([$video_id, $user_id, $now]);
            }

            if ($video_id) {
                $stmtHist = $pdo->prepare("INSERT INTO video_history (video_id, title, watched_at) VALUES (?, ?, CURRENT_TIMESTAMP) 
                                           ON CONFLICT(video_id) DO UPDATE SET title = CASE WHEN title = '' OR title IS NULL THEN excluded.title ELSE title END, watched_at = CURRENT_TIMESTAMP");
                $stmtHist->execute([$video_id, $video_title]);
            }
        }
        
        $updates = [];
        $params = [];
        if ($state !== null) { $updates[] = "state = ?"; $params[] = $state; }
        if ($current_time !== null) { $updates[] = "current_time = ?"; $params[] = $current_time; }
        if ($ready !== null) { $updates[] = "$ready_col = ?"; $params[] = (int)$ready; }
        if ($comments_enabled !== null) { $updates[] = "comments_enabled = ?"; $params[] = (int)$comments_enabled; }
        
        if (!empty($updates)) {
            $updates[] = "last_updated_by = ?"; $params[] = $user_id;
            $updates[] = "updated_at = ?"; $params[] = $now;
            $sql = "UPDATE youtube_sync SET " . implode(", ", $updates) . " WHERE id = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        echo json_encode(['success' => true]);
        break;

    case 'send_youtube_comment':
        $data = json_decode(file_get_contents('php://input'), true);
        $content = $data['content'] ?? '';
        if (!$content) { echo json_encode(['success' => false]); break; }
        
        $full_content = 'YT_COMMENT:' . $content;
        $receiver_id = ($user_id == 1) ? 2 : 1;
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $receiver_id, $full_content]);
        echo json_encode(['success' => true]);
        break;

    case 'get_youtube_comments':
        $stmt = $pdo->prepare("SELECT id, sender_id, content FROM messages WHERE content LIKE 'YT_COMMENT:%' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 50");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['content'] = str_replace('YT_COMMENT:', '', $row['content']);
        }
        echo json_encode($rows);
        break;

    case 'delete_youtube_comments':
    case 'burn_yt_comments':
        $pdo->prepare("UPDATE messages SET deleted_at = CURRENT_TIMESTAMP WHERE content LIKE '%YT_COMMENT:%'")->execute();
        echo json_encode(array('success' => true));
        break;

    case 'get_video_history':
        $stmt = $pdo->query("SELECT id, video_id, title, strftime('%Y-%m-%dT%H:%M:%SZ', watched_at) as watched_at FROM video_history ORDER BY watched_at DESC LIMIT 20");
        echo json_encode($stmt->fetchAll());
        break;
}
