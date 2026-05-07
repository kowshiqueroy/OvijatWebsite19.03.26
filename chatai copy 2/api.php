<?php
require_once 'auth.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array('error' => 'Unauthorized'));
    exit();
}

// CSRF Protection Check for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['_csrf'] ?? ($_POST['_csrf'] ?? '');
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(array('error' => 'CSRF validation failed'));
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

function saveBase64File($base64Data, $prefix = 'file', $targetDir = 'uploads') {
    if (empty($base64Data)) return false;
    
    $allowedDirs = ['uploads', 'premium_vault'];
    if (!in_array($targetDir, $allowedDirs)) return false;

    $fullTargetDir = __DIR__ . '/' . $targetDir . '/';
    if (!file_exists($fullTargetDir)) mkdir($fullTargetDir, 0755, true);
    
    // Fix: Secure file copy from uploads to premium_vault (prevent traversal)
    if (is_string($base64Data) && strpos($base64Data, 'uploads/') === 0) {
        $cleanPath = basename($base64Data);
        $sourceFile = __DIR__ . '/uploads/' . $cleanPath;
        if (!file_exists($sourceFile)) return false;
        
        $extension = strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm', 'mov', 'ogg', 'wav'];
        if (!in_array($extension, $allowedExts)) return false;

        $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
        $destFile = $fullTargetDir . $filename;
        if (copy($sourceFile, $destFile)) {
            return $targetDir . '/' . $filename;
        }
        return false;
    }

    if (!preg_match('/^data:([^;]+);base64,(.*)$/', $base64Data, $matches)) return false;
    
    $mimeType = $matches[1];
    $data = base64_decode($matches[2]);
    
    $extension = '';
    switch ($mimeType) {
        case 'image/jpeg': $extension = 'jpg'; break;
        case 'image/png':  $extension = 'png'; break;
        case 'image/webp': $extension = 'webp'; break;
        case 'audio/webm': $extension = 'webm'; break;
        case 'audio/mp4':  $extension = 'mp4'; break;
        case 'audio/ogg':  $extension = 'ogg'; break;
        case 'audio/wav':  $extension = 'wav'; break;
        case 'video/mp4':  $extension = 'mp4'; break;
        case 'video/webm':  $extension = 'webm'; break;
        case 'video/quicktime': $extension = 'mov'; break;
        default: return false;
    }
    
    $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
    $filePath = $fullTargetDir . $filename;
    
    if (file_put_contents($filePath, $data)) {
        return $targetDir . '/' . $filename;
    }
    return false;
}

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
        
        if ($is_image || $is_voice || $is_video) {
            $prefix = $is_image ? 'img' : ($is_voice ? 'voice' : 'video');
            $filePath = saveBase64File($content, $prefix);
            if ($filePath) $content = $filePath;
            else {
                echo json_encode(array('success' => false, 'error' => 'Failed to save media file'));
                break;
            }
        }
        
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, content, is_image, is_voice, is_video) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute(array($user_id, $receiver_id, $content, $is_image, $is_voice, $is_video));

        $savedToVault = false;
        $savedError = null;
        if (($is_image || $is_voice || $is_video) && $content && strpos($content, 'data:') !== 0) {
            try {
                $is_vid = $is_video ? 1 : 0;
                $is_v = $is_voice ? 1 : 0;
                $stmt2 = $pdo->prepare('INSERT INTO saved_images (user_id, image_data, is_voice, is_video) VALUES (?, ?, ?, ?)');
                $stmt2->execute(array($user_id, $content, $is_v, $is_vid));
                $savedToVault = true;
            } catch (Exception $e) {
                $savedError = $e->getMessage();
                error_log('saved_images insert failed: ' . $e->getMessage());
            }
        }

        echo json_encode(array('success' => true, 'vault' => $savedToVault, 'err' => $savedError, 'path' => ($is_image || $is_voice || $is_video) ? $content : null));
        break;

    case 'get_messages':
        $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id, content, is_image, is_voice, is_video, burn_after, strftime('%Y-%m-%dT%H:%M:%SZ', created_at) as created_at, strftime('%Y-%m-%dT%H:%M:%SZ', viewed_at) as viewed_at FROM messages WHERE (sender_id = ? OR receiver_id = ?) AND deleted_at IS NULL ORDER BY created_at ASC");
        $stmt->execute(array($user_id, $user_id));
        $messages = $stmt->fetchAll();
        $now = time();
        foreach ($messages as $key => $msg) {
            if ($msg['burn_after'] !== null && $now >= $msg['burn_after']) {
                if ($msg['is_image'] || $msg['is_voice'] || $msg['is_video']) {
                    $filePath = $msg['content'];
                    if ($filePath && strpos($filePath, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $filePath)) {
                        unlink(__DIR__ . '/' . $filePath);
                    }
                }
                $stmtDel = $pdo->prepare('UPDATE messages SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmtDel->execute(array($msg['id']));
                unset($messages[$key]);
            }
        }
        echo json_encode(array_values($messages));
        break;

    case 'save_image':
        if (isset($_FILES['file'])) {
            $file = $_FILES['file'];
            $fileType = $file['type'];
            
            $allowedMimes = [
                'image/jpeg', 'image/png', 'image/webp',
                'video/mp4', 'video/webm', 'video/quicktime',
                'audio/webm', 'audio/mp4', 'audio/ogg', 'audio/wav'
            ];
            
            if (!in_array($fileType, $allowedMimes)) {
                echo json_encode(array('success' => false, 'error' => 'Invalid file type'));
                break;
            }

            $is_v = (strpos($fileType, 'audio') !== false) ? 1 : 0;
            $is_vid = (strpos($fileType, 'video') !== false) ? 1 : 0;
            $prefix = $is_v ? 'p_voice' : ($is_vid ? 'p_video' : 'p_img');
            
            $mimeToExt = array(
                'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
                'audio/webm' => 'webm', 'audio/mp4' => 'mp4', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav'
            );
            $extension = $mimeToExt[$fileType] ?? 'bin';
            
            $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
            $targetDir = __DIR__ . '/premium_vault/';
            if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);
            $targetPath = $targetDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $path = 'premium_vault/' . $filename;
                $stmt = $pdo->prepare('INSERT INTO saved_images (user_id, image_data, is_voice, is_video) VALUES (?, ?, ?, ?)');
                $stmt->execute(array($user_id, $path, $is_v, $is_vid));
                echo json_encode(array('success' => true));
            } else {
                echo json_encode(array('success' => false, 'error' => 'Failed to move uploaded file'));
            }
            break;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $media = $data['media_data'] ?? $data['image_data'] ?? '';
        $is_v = $data['is_voice'] ?? 0;
        $is_vid = $data['is_video'] ?? 0;
        if (empty($media)) { echo json_encode(array('success' => false, 'error' => 'No media')); break; }
        
        $prefix = $is_v ? 'p_voice' : ($is_vid ? 'p_video' : 'p_img');
        $path = saveBase64File($media, $prefix, 'premium_vault');
        
        if (!$path) { echo json_encode(array('success' => false, 'error' => 'Failed save')); break; }
        $pdo->prepare('INSERT INTO saved_images (user_id, image_data, is_voice, is_video) VALUES (?, ?, ?, ?)')->execute(array($user_id, $path, $is_v, $is_vid));
        echo json_encode(array('success' => true));
        break;

    case 'get_saved_images':
        $stmt = $pdo->prepare('SELECT * FROM saved_images WHERE user_id = ? ORDER BY saved_at DESC');
        $stmt->execute(array($user_id));
        $dbItems = $stmt->fetchAll();
        $knownPaths = array_column($dbItems, 'image_data');

        $vaultDir = __DIR__ . '/premium_vault/';
        if (is_dir($vaultDir)) {
            $extMap = array('mp4' => 'video', 'webm' => 'video', 'mov' => 'video', 'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'webp' => 'image', 'ogg' => 'audio', 'wav' => 'audio');
            foreach (scandir($vaultDir) as $f) {
                if ($f[0] === '.') continue;
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (!isset($extMap[$ext])) continue;
                $path = 'premium_vault/' . $f;
                if (in_array($path, $knownPaths)) continue;
                $isVid = $extMap[$ext] === 'video' ? 1 : 0;
                $isV = $extMap[$ext] === 'audio' ? 1 : 0;
                array_unshift($dbItems, array('id' => 0, 'user_id' => $user_id, 'image_data' => $path, 'is_voice' => $isV, 'is_video' => $isVid, 'saved_at' => date('Y-m-d\TH:i:s\Z', filemtime($vaultDir . $f))));
            }
        }

        echo json_encode($dbItems);
        break;

    case 'verify_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $pin = $data['pin'] ?? '';
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute(array('pin_' . $user_id));
        $hash = $stmt->fetchColumn();
        if ($hash && password_verify($pin, $hash)) {
            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('success' => false, 'error' => 'Invalid PIN'));
        }
        break;

    case 'nuclear_wipe':
        // Delete all messages and their media
        $stmt = $pdo->query("SELECT content FROM messages WHERE is_image=1 OR is_voice=1 OR is_video=1");
        while ($row = $stmt->fetch()) {
            $path = $row['content'];
            if ($path && strpos($path, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $path)) {
                unlink(__DIR__ . '/' . $path);
            }
        }
        $pdo->exec("DELETE FROM messages");
        
        // Delete all saved images and their media
        $stmt = $pdo->query("SELECT image_data FROM saved_images");
        while ($row = $stmt->fetch()) {
            $path = $row['image_data'];
            if ($path && strpos($path, 'premium_vault/') === 0 && file_exists(__DIR__ . '/' . $path)) {
                unlink(__DIR__ . '/' . $path);
            }
        }
        $pdo->exec("DELETE FROM saved_images");
        
        echo json_encode(array('success' => true));
        break;

    case 'burn_yt_comments':
        $pdo->prepare("UPDATE messages SET deleted_at = CURRENT_TIMESTAMP WHERE content LIKE '%YT_COMMENT:%'")->execute();
        echo json_encode(array('success' => true));
        break;

    case 'reset_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $new_pin = $data['new_pin'] ?? '';
        if (strlen($new_pin) !== 4) { echo json_encode(array('success' => false, 'error' => 'Invalid PIN')); break; }
        $hash = password_hash($new_pin, PASSWORD_BCRYPT);
        $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute(array('pin_' . $user_id, $hash));
        
        // Wipe messages as per description
        $stmt = $pdo->query("SELECT content FROM messages WHERE is_image=1 OR is_voice=1 OR is_video=1");
        while ($row = $stmt->fetch()) {
            $path = $row['content'];
            if ($path && strpos($path, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $path)) {
                unlink(__DIR__ . '/' . $path);
            }
        }
        $pdo->exec("DELETE FROM messages");
        echo json_encode(array('success' => true));
        break;

    case 'update_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $old_pin = $data['old_pin'] ?? '';
        $new_pin = $data['new_pin'] ?? '';
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute(array('pin_' . $user_id));
        $hash = $stmt->fetchColumn();
        if ($hash && password_verify($old_pin, $hash)) {
            $new_hash = password_hash($new_pin, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE settings SET value = ? WHERE key = ?')->execute(array($new_hash, 'pin_' . $user_id));
            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('success' => false, 'error' => 'Invalid old PIN'));
        }
        break;

    case 'update_password':
        $data = json_decode(file_get_contents('php://input'), true);
        $old_pass = $data['old_password'] ?? '';
        $new_pass = $data['new_password'] ?? '';
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute(array('pass_' . $user_id));
        $hash = $stmt->fetchColumn();
        if ($hash && password_verify($old_pass, $hash)) {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE settings SET value = ? WHERE key = ?')->execute(array($new_hash, 'pass_' . $user_id));
            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('success' => false, 'error' => 'Invalid old password'));
        }
        break;

    case 'send_knock_sms':
        $data = json_decode(file_get_contents('php://input'), true);
        $custom_text = $data['custom_text'] ?? '';
        
        $stmt = $pdo->query("SELECT key, value FROM settings WHERE key IN ('sms_api_key', 'sms_default_msg', 'sms_number_1', 'sms_number_2', 'sms_enabled_2')");
        $sets = [];
        while($r = $stmt->fetch()) $sets[$r['key']] = $r['value'];
        
        $api_key = $sets['sms_api_key'] ?? '';
        if (empty($api_key)) { echo json_encode(array('success' => false, 'error' => 'No API Key')); break; }
        
        $other_user_id = ($user_id == 1) ? 2 : 1;
        if ($other_user_id == 2 && ($sets['sms_enabled_2'] ?? '1') != '1') {
            echo json_encode(array('success' => false, 'error' => 'SMS disabled for partner'));
            break;
        }
        
        $to = $sets['sms_number_' . $other_user_id] ?? '';
        if (empty($to)) { echo json_encode(array('success' => false, 'error' => 'No partner number')); break; }
        
        $msg = !empty($custom_text) ? $custom_text : ($sets['sms_default_msg'] ?? 'Knock knock! Check the chat.');
        $url = 'https://api.sms.net.bd/sendsms?api_key=' . urlencode($api_key) . '&msg=' . urlencode($msg) . '&to=' . urlencode($to);
        
        $resp = @file_get_contents($url, false, stream_context_create(['http'=>['timeout'=>10,'ignore_errors'=>true]]));
        $success = false;
        $err = 'Connection failed';
        if ($resp) {
            $result = json_decode($resp, true);
            if (isset($result['error']) && $result['error'] == 0) { $success = true; }
            else { $err = $result['msg'] ?? 'API Error'; }
        }
        
        $pdo->prepare("INSERT INTO sms_history (user_id, phone_number, message, status, error_msg) VALUES (?, ?, ?, ?, ?)")
            ->execute([$user_id, $to, $msg, $success ? 'sent' : 'failed', $success ? null : $err]);
            
        echo json_encode(array('success' => $success, 'error' => $success ? null : $err));
        break;

    case 'delete_saved_image':
        $data = json_decode(file_get_contents('php://input'), true);
        $image_id = $data['image_id'] ?? 0;
        $image_path = $data['image_path'] ?? '';
        $path = '';

        if ($image_id > 0) {
            $stmt = $pdo->prepare('SELECT image_data, user_id FROM saved_images WHERE id = ?');
            $stmt->execute(array($image_id));
            $row = $stmt->fetch();
            if (!$row || $row['user_id'] != $user_id) {
                echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
                break;
            }
            $path = $row['image_data'];
            $pdo->prepare('DELETE FROM saved_images WHERE id = ?')->execute(array($image_id));
        } else if ($image_path) {
            $path = $image_path;
        }

        if ($path && strpos($path, 'premium_vault/') === 0) {
            $fullPath = __DIR__ . '/' . $path;
            if (file_exists($fullPath)) unlink($fullPath);
        }
        
        echo json_encode(array('success' => true));
        break;

    case 'update_status':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("SELECT * FROM user_status WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current = $stmt->fetch();
        
        $is_typing = isset($data['is_typing']) ? (int)$data['is_typing'] : ($current['is_typing'] ?? 0);
        $in_theater = isset($data['in_theater']) ? (int)$data['in_theater'] : ($current['in_theater'] ?? 0);
        
        if ($current) {
            $stmt = $pdo->prepare("UPDATE user_status SET last_seen = ?, is_typing = ?, in_theater = ? WHERE user_id = ?");
            $stmt->execute([time(), $is_typing, $in_theater, $user_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO user_status (user_id, last_seen, is_typing, in_theater) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, time(), $is_typing, $in_theater]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'mark_viewed':
        $data = json_decode(file_get_contents('php://input'), true);
        $msg_id = $data['msg_id'] ?? 0;
        $burn_after = 0;
        $stmt = $pdo->prepare("SELECT sender_id, receiver_id, viewed_at FROM messages WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$msg_id]);
        $msg = $stmt->fetch();
        if ($msg && $msg['receiver_id'] == $user_id && !$msg['viewed_at']) {
            $burn_after = time() + 30;
            $pdo->prepare("UPDATE messages SET viewed_at = CURRENT_TIMESTAMP, burn_after = ? WHERE id = ?")->execute([$burn_after, $msg_id]);
        }
        echo json_encode(['success' => true, 'burn_after' => $burn_after]);
        break;

    case 'get_other_status':
        $other_user_id = ($user_id == 1) ? 2 : 1;
        $stmt = $pdo->prepare("SELECT last_seen, is_typing, in_theater FROM user_status WHERE user_id = ?");
        $stmt->execute([$other_user_id]);
        $status = $stmt->fetch();
        
        $response = ['status' => 'offline', 'last_seen' => null];
        if ($status) {
            $response['last_seen'] = (int)$status['last_seen'];
            $is_online = (time() - $status['last_seen']) < 10;
            if ($is_online) {
                $response['status'] = $status['is_typing'] ? 'typing' : 'active';
                $response['in_theater'] = (bool)$status['in_theater'];
            }
        }
        echo json_encode($response);
        break;

    case 'theater_status':
        $stmt = $pdo->query("SELECT COUNT(*) FROM user_status WHERE last_seen > " . (time() - 10) . " AND in_theater = 1");
        $count = $stmt->fetchColumn();
        echo json_encode(['users_in_theater' => (int)$count]);
        break;

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
                
                // Add to history
                if ($video_id) {
                    $stmtHist = $pdo->prepare("INSERT OR REPLACE INTO video_history (video_id, title, watched_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
                    $stmtHist->execute([$video_id, $video_title]);
                }
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

    case 'get_video_history':
        $stmt = $pdo->query("SELECT * FROM video_history ORDER BY watched_at DESC LIMIT 20");
        echo json_encode($stmt->fetchAll());
        break;

    case 'get_youtube_sync':
        $stmt = $pdo->query("SELECT * FROM youtube_sync WHERE id = 1");
        $data = $stmt->fetch();
        
        $other_user_id = ($user_id == 1) ? 2 : 1;
        $stmtStatus = $pdo->prepare("SELECT last_seen FROM user_status WHERE user_id = ?");
        $stmtStatus->execute([$other_user_id]);
        $last_seen = $stmtStatus->fetchColumn();
        $other_online = $last_seen && (time() - $last_seen) < 12;
        
        $data['other_online'] = (bool)$other_online;
        // Fix: Don't automatically mark offline users as ready. 
        // Let the client decide how to handle playback when the partner is away.
        $data['server_time'] = microtime(true);
        echo json_encode($data);
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

    case 'leave_theater_beacon':
        $pdo->prepare("UPDATE user_status SET in_theater = 0 WHERE user_id = ?")->execute([$user_id]);
        echo json_encode(['success' => true]);
        break;

    case 'delete_youtube_comments':
        $pdo->prepare("UPDATE messages SET deleted_at = CURRENT_TIMESTAMP WHERE content LIKE '%YT_COMMENT:%'")->execute();
        echo json_encode(array('success' => true));
        break;

    case 'send_call_comment':
        $pdo->exec("CREATE TABLE IF NOT EXISTS call_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER,
            receiver_id INTEGER,
            content TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL
        )");
        $data = json_decode(file_get_contents('php://input'), true);
        $text = trim($data['content'] ?? '');
        if (!$text) { echo json_encode(array('success' => false, 'error' => 'Empty')); break; }
        $receiver_id = ($user_id == 1) ? 2 : 1;
        $stmt = $pdo->prepare('INSERT INTO call_comments (sender_id, receiver_id, content) VALUES (?, ?, ?)');
        $stmt->execute(array($user_id, $receiver_id, $text));
        echo json_encode(array('success' => true, 'id' => $pdo->lastInsertId(), 'created_at' => date('Y-m-d\TH:i:s\Z')));
        break;

    case 'get_call_comments':
        $pdo->exec("CREATE TABLE IF NOT EXISTS call_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER,
            receiver_id INTEGER,
            content TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL
        )");
        $other_id = ($user_id == 1) ? 2 : 1;
        $stmt = $pdo->prepare("SELECT id, sender_id, content, created_at FROM call_comments WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND deleted_at IS NULL AND created_at > datetime('now', '-10 seconds') ORDER BY created_at ASC");
        $stmt->execute(array($user_id, $other_id, $other_id, $user_id));
        echo json_encode($stmt->fetchAll());
        break;

    case 'delete_call_comment':
        $pdo->exec("CREATE TABLE IF NOT EXISTS call_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER,
            receiver_id INTEGER,
            content TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL
        )");
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;
        $stmt = $pdo->prepare("UPDATE call_comments SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute(array($id));
        echo json_encode(array('success' => true));
        break;

    case 'save_recording':
        $error = null;
        if (isset($_FILES['recording'])) {
            $file = $_FILES['recording'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Upload error code: ' . $file['error'];
            } else {
                $filename = 'recording_' . $user_id . '_' . time() . '.webm';
                $targetDir = __DIR__ . '/premium_vault/';
                if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);
                $targetPath = $targetDir . $filename;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $path = 'premium_vault/' . $filename;
                    try {
                        $stmt = $pdo->prepare('INSERT INTO saved_images (user_id, image_data, is_voice, is_video) VALUES (?, ?, 0, 1)');
                        $stmt->execute(array($user_id, $path));
                        echo json_encode(array('success' => true, 'filename' => $filename));
                        break;
                    } catch (Exception $e) {
                        $error = 'DB insert failed: ' . $e->getMessage();
                    }
                } else {
                    $error = 'move_uploaded_file failed';
                }
            }
        } else {
            $error = 'No recording file in $_FILES';
            $error .= ' Keys: ' . implode(', ', array_keys($_FILES));
        }
        echo json_encode(array('success' => false, 'error' => $error));
        break;
}
