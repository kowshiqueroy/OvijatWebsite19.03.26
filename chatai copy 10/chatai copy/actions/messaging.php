<?php
switch ($action) {
    case 'send_message':
        $data = json_decode(file_get_contents('php://input'), true);
        $content = $data['content'] ?? '';
        $is_image = $data['is_image'] ?? 0;
        $is_voice = $data['is_voice'] ?? 0;
        $is_video = $data['is_video'] ?? 0;
        $receiver_id = ($user_id == 1) ? 2 : 1;
        
        if (strlen($content) > 140000000) {
            echo json_encode(array('success' => false, 'error' => 'Payload too large (100MB max)'));
            break;
        }
        
        $file_hash = null;
        if ($is_image || $is_voice || $is_video) {
            $mediaPath = $content;
            $sepIdx = strpos($content, '||');
            if ($sepIdx !== false) $mediaPath = substr($content, $sepIdx + 2);
            if (strpos($mediaPath, 'uploads/') === 0 || strpos($mediaPath, 'premium_vault/') === 0) {
                if (file_exists(__DIR__ . '/../' . $mediaPath)) $file_hash = md5_file(__DIR__ . '/../' . $mediaPath);
            } else {
                $prefix = $is_image ? 'img' : ($is_voice ? 'voice' : 'video');
                $filePath = saveBase64File($content, $prefix);
                if ($filePath) {
                    $content = $filePath;
                    $file_hash = md5_file(__DIR__ . '/../' . $content);
                } else {
                    echo json_encode(array('success' => false, 'error' => 'Failed to save media file'));
                    break;
                }
            }
        }
        
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, content, is_image, is_voice, is_video, file_hash) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(array($user_id, $receiver_id, $content, $is_image, $is_voice, $is_video, $file_hash));

        echo json_encode(array('success' => true, 'path' => ($is_image || $is_voice || $is_video) ? $content : null));
        break;

    case 'get_messages':
        /* Auto-cleanup disabled as per user request. Manual cleanup will be implemented in the vault.
        $cleanupFile = __DIR__ . '/../last_cleanup.txt';
        if (!file_exists($cleanupFile) || (time() - filemtime($cleanupFile)) > 3600) {
            touch($cleanupFile);
            
            // 1. Clean orphaned uploads
            $stmt = $pdo->query("SELECT content FROM messages WHERE is_image=1 OR is_voice=1 OR is_video=1");
            $dbFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $dbFiles = array_map(function($f) { return basename($f); }, $dbFiles);
            
            $uploadsDir = __DIR__ . '/../uploads/';
            if (is_dir($uploadsDir)) {
                $diskFiles = scandir($uploadsDir);
                foreach ($diskFiles as $f) {
                    if ($f === '.' || $f === '..' || $f === '.htaccess') continue;
                    $fullPath = $uploadsDir . $f;
                    
                    if (is_dir($fullPath)) {
                        // Delete old temp dirs (> 1 hour)
                        if (strpos($f, 'temp_') === 0 && (time() - filemtime($fullPath)) > 3600) {
                            $chunks = scandir($fullPath);
                            foreach ($chunks as $c) {
                                if ($c !== '.' && $c !== '..') @unlink($fullPath . '/' . $c);
                            }
                            @rmdir($fullPath);
                        }
                    } else {
                        // Delete files not in DB
                        if (!in_array($f, $dbFiles)) {
                            @unlink($fullPath);
                        }
                    }
                }
            }

            // 2. Clean orphaned premium_vault
            $stmt = $pdo->query("SELECT image_data FROM saved_images");
            $vaultDbFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $vaultDbFiles = array_map(function($f) { return basename($f); }, $vaultDbFiles);
            
            $vaultDir = __DIR__ . '/../premium_vault/';
            if (is_dir($vaultDir)) {
                $vaultDiskFiles = scandir($vaultDir);
                foreach ($vaultDiskFiles as $f) {
                    if ($f === '.' || $f === '..' || $f === '.htaccess') continue;
                    if (!in_array($f, $vaultDbFiles)) {
                        @unlink($vaultDir . $f);
                    }
                }
            }

            // 3. Clean files marked for deletion in DB
            $stmtDelFiles = $pdo->query("SELECT content FROM messages WHERE deleted_at IS NOT NULL AND (is_image=1 OR is_voice=1 OR is_video=1)");
            while ($f = $stmtDelFiles->fetchColumn()) {
                if ($f && file_exists(__DIR__ . '/../' . $f)) {
                    // Only unlink if no other message uses this file (deduplication safety check)
                    $stCheck = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE content = ? AND deleted_at IS NULL");
                    $stCheck->execute([$f]);
                    if ($stCheck->fetchColumn() == 0) @unlink(__DIR__ . '/../' . $f);
                }
            }
        }
        */

        $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id, content, is_image, is_voice, is_video, burn_after, strftime('%Y-%m-%dT%H:%M:%SZ', created_at) as created_at, strftime('%Y-%m-%dT%H:%M:%SZ', viewed_at) as viewed_at FROM messages WHERE (sender_id = ? OR receiver_id = ?) AND deleted_at IS NULL ORDER BY created_at ASC");
        $stmt->execute(array($user_id, $user_id));
        $messages = $stmt->fetchAll();
        $now = time();
        foreach ($messages as $key => $msg) {
            if ($msg['burn_after'] !== null && $now >= $msg['burn_after']) {
                if ($msg['is_image'] || $msg['is_voice'] || $msg['is_video']) {
                    $filePath = $msg['content'];
                    if ($filePath && strpos($filePath, 'uploads/') === 0 && file_exists(__DIR__ . '/../' . $filePath)) {
                        // Deduplication safety check
                        $stCheck = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE content = ? AND (deleted_at IS NULL OR burn_after > ?)");
                        $stCheck->execute([$filePath, $now]);
                        if ($stCheck->fetchColumn() <= 1) unlink(__DIR__ . '/../' . $filePath);
                    }
                }
                $stmtDel = $pdo->prepare('UPDATE messages SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmtDel->execute(array($msg['id']));
                unset($messages[$key]);
            }
        }
        echo json_encode(array_values($messages));
        break;

    case 'mark_viewed':
        $data = json_decode(file_get_contents('php://input'), true);
        $msg_id = $data['msg_id'] ?? 0;
        $burn_after = 0;
        $stmt = $pdo->prepare("SELECT sender_id, receiver_id, viewed_at FROM messages WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$msg_id]);
        $msg = $stmt->fetch();
        if ($msg && $msg['receiver_id'] == $user_id && !$msg['viewed_at']) {
            $burn_after = time() + 120;
            $pdo->prepare("UPDATE messages SET viewed_at = CURRENT_TIMESTAMP, burn_after = ? WHERE id = ?")->execute([$burn_after, $msg_id]);
        }
        echo json_encode(['success' => true, 'burn_after' => $burn_after]);
        break;

    case 'schedule_delete':
        $data = json_decode(file_get_contents('php://input'), true);
        $msg_id = $data['msg_id'] ?? 0;
        $burn_after = time() + 120;
        $pdo->prepare("UPDATE messages SET burn_after = ? WHERE id = ? AND deleted_at IS NULL")->execute([$burn_after, $msg_id]);
        echo json_encode(['success' => true, 'burn_after' => $burn_after]);
        break;

    case 'delete_my_unseen':
        $stmt = $pdo->prepare("DELETE FROM messages WHERE sender_id = ? AND viewed_at IS NULL");
        $stmt->execute(array($user_id));
        echo json_encode(array('success' => true, 'count' => $stmt->rowCount()));
        break;

    case 'delete_my_messages':
        // 1. Find all media sent by me
        $stmt = $pdo->prepare("SELECT content FROM messages WHERE sender_id = ? AND (is_image=1 OR is_voice=1 OR is_video=1)");
        $stmt->execute([$user_id]);
        while ($f = $stmt->fetchColumn()) {
            if ($f && strpos($f, 'uploads/') === 0 && file_exists(__DIR__ . '/../' . $f)) {
                // Deduplication safety check: only delete file if no OTHER message (not sent by me) uses it
                $stCheck = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE content = ? AND sender_id != ? AND deleted_at IS NULL");
                $stCheck->execute([$f, $user_id]);
                if ($stCheck->fetchColumn() == 0) @unlink(__DIR__ . '/../' . $f);
            }
        }
        // 2. Delete all records sent by me
        $stmt = $pdo->prepare("DELETE FROM messages WHERE sender_id = ?");
        $stmt->execute([$user_id]);
        echo json_encode(array('success' => true, 'count' => $stmt->rowCount()));
        break;
}
