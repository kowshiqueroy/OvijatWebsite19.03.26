<?php
require_once 'auth.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// CSRF Protection Check for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['_csrf'] ?? '';
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF validation failed']);
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

/**
 * Helper to save base64 data to a file or copy existing file
 */
function saveBase64File($base64Data, $prefix = 'file', $targetDir = 'uploads') {
    if (empty($base64Data)) return false;
    
    $fullTargetDir = __DIR__ . '/' . $targetDir . '/';
    if (!file_exists($fullTargetDir)) mkdir($fullTargetDir, 0777, true);
    
    // Check if it's already a file path (for copying to persistent vault)
    if (is_string($base64Data) && strpos($base64Data, 'uploads/') === 0) {
        $sourceFile = __DIR__ . '/' . $base64Data;
        if (!file_exists($sourceFile)) return false;
        
        $extension = pathinfo($sourceFile, PATHINFO_EXTENSION);
        $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
        $destFile = $fullTargetDir . $filename;
        
        if (copy($sourceFile, $destFile)) {
            return $targetDir . '/' . $filename;
        }
        return false;
    }

    // Extract base64 and extension
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
        case 'video/webm': $extension = 'webm'; break;
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

        if (strlen($content) > 140000000) { // ~100MB limit for Base64 (approx 1.37x overhead)
             echo json_encode(['success' => false, 'error' => 'Payload too large (100MB max)']);
             break;
        }

        if ($is_image || $is_voice || $is_video) {
            $prefix = $is_image ? 'img' : ($is_voice ? 'voice' : 'video');
            $filePath = saveBase64File($content, $prefix);
            if ($filePath) $content = $filePath;
            else {
                echo json_encode(['success' => false, 'error' => 'Failed to save media file']);
                break;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, is_image, is_voice, is_video) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $receiver_id, $content, $is_image, $is_voice, $is_video]);
        echo json_encode(['success' => true]);
        break;

    case 'get_messages':
        $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id, content, is_image, is_voice, is_video, burn_after, strftime('%Y-%m-%dT%H:%M:%SZ', created_at) as created_at, strftime('%Y-%m-%dT%H:%M:%SZ', viewed_at) as viewed_at FROM messages WHERE (sender_id = ? OR receiver_id = ?) AND deleted_at IS NULL ORDER BY created_at ASC");
        $stmt->execute([$user_id, $user_id]);
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
                $stmtDel = $pdo->prepare("UPDATE messages SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmtDel->execute([$msg['id']]);
                unset($messages[$key]);
            }
        }
        echo json_encode(array_values($messages));
        break;

    case 'update_status':
        $data = json_decode(file_get_contents('php://input'), true);
        $updates = ['last_seen = ?'];
        $params = [time()];
        if (isset($data['is_typing'])) {
            $updates[] = 'is_typing = ?';
            $params[] = ($data['is_typing'] == 1) ? 1 : 0;
        }
        if (isset($data['in_theater'])) {
            $updates[] = 'in_theater = ?';
            $params[] = ($data['in_theater'] == 1) ? 1 : 0;
        }
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO user_status (user_id, last_seen, is_typing, in_theater) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, time(), 0, 0]);
        $params[] = $user_id;
        $sql = "UPDATE user_status SET " . implode(', ', $updates) . " WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true]);
        break;

    case 'heartbeat':
        $stmt = $pdo->prepare("UPDATE user_status SET last_seen = ? WHERE user_id = ?");
        $stmt->execute([time(), $user_id]);
        echo json_encode(['success' => true]);
        break;

    case 'get_other_status':
        $other_user_id = ($user_id == 1) ? 2 : 1;
        $stmt = $pdo->prepare("SELECT * FROM user_status WHERE user_id = ?");
        $stmt->execute([$other_user_id]);
        $status = $stmt->fetch();
        if ($status) {
            $diff = time() - (int)$status['last_seen'];
            $state = ($diff < 10) ? (($status['is_typing'] == 1) ? 'typing' : 'active') : 'offline';
            echo json_encode(['status' => $state, 'last_seen' => $status['last_seen']]);
        } else echo json_encode(['status' => 'offline', 'last_seen' => 0]);
        break;

    case 'is_other_in_theater':
        $other_user_id = ($user_id == 1) ? 2 : 1;
        $stmt = $pdo->prepare("SELECT * FROM user_status WHERE user_id = ?");
        $stmt->execute([$other_user_id]);
        $row = $stmt->fetch();
        $in_theater = ($row && (int)$row['in_theater'] === 1 && (time() - (int)$row['last_seen'] < 10));
        echo json_encode(['in_theater' => $in_theater]);
        break;

    case 'theater_status':
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM user_status WHERE in_theater = 1 AND last_seen > ?");
        $stmt->execute([time() - 10]);
        $row = $stmt->fetch();
        echo json_encode(['users_in_theater' => (int)$row['cnt']]);
        break;

    case 'leave_theater_beacon':
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("UPDATE user_status SET in_theater = 0 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'mark_viewed':
        $data = json_decode(file_get_contents('php://input'), true);
        $msg_id = $data['msg_id'];
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$msg_id, $user_id]);
        $msg = $stmt->fetch();
        if ($msg && $msg['burn_after'] === null) {
            $burn_at = time() + 120;
            $stmtUpd = $pdo->prepare("UPDATE messages SET viewed_at = CURRENT_TIMESTAMP, burn_after = ? WHERE id = ?");
            $stmtUpd->execute([$burn_at, $msg_id]);
            echo json_encode(['success' => true, 'burn_after' => $burn_at]);
        } else echo json_encode(['success' => false]);
        break;

    case 'nuclear_wipe':
        $stmt = $pdo->prepare("SELECT content FROM messages WHERE (sender_id = ? OR receiver_id = ?) AND (is_image = 1 OR is_voice = 1 OR is_video = 1) AND deleted_at IS NULL");
        $stmt->execute([$user_id, $user_id]);
        $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($files as $f) {
            if ($f && strpos($f, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $f)) unlink(__DIR__ . '/' . $f);
        }
        $stmt = $pdo->prepare("UPDATE messages SET deleted_at = CURRENT_TIMESTAMP WHERE sender_id = ? OR receiver_id = ?");
        $stmt->execute([$user_id, $user_id]);
        echo json_encode(['success' => true]);
        break;

    case 'reset_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $new_pin = $data['new_pin'] ?? '';
        if (strlen($new_pin) !== 4) { echo json_encode(['success' => false, 'error' => 'Invalid PIN']); break; }
        $stmtDel = $pdo->prepare("UPDATE messages SET deleted_at = CURRENT_TIMESTAMP WHERE sender_id = ? OR receiver_id = ?");
        $stmtDel->execute([$user_id, $user_id]);
        $stmtPin = $pdo->prepare('UPDATE settings SET value = ? WHERE key = ?');
        $stmtPin->execute([password_hash($new_pin, PASSWORD_BCRYPT), "pin_" . $user_id]);
        echo json_encode(['success' => true]);
        break;

    case 'verify_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute(["pin_" . $user_id]);
        $hash = $stmt->fetchColumn();
        echo json_encode(['success' => ($hash && password_verify($data['pin'] ?? '', $hash))]);
        break;

    case 'update_pin':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute(["pin_" . $user_id]);
        $hash = $stmt->fetchColumn();
        if ($hash && password_verify($data['old_pin'] ?? '', $hash)) {
            $stmtUpd = $pdo->prepare("UPDATE settings SET value = ? WHERE key = ?");
            $stmtUpd->execute([password_hash($data['new_pin'], PASSWORD_BCRYPT), "pin_" . $user_id]);
            echo json_encode(['success' => true]);
        } else echo json_encode(['success' => false, 'error' => 'Old PIN incorrect']);
        break;

    case 'update_password':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute(["pass_" . $user_id]);
        $hash = $stmt->fetchColumn();
        if ($hash && password_verify($data['old_pass'] ?? '', $hash)) {
            $stmtUpd = $pdo->prepare("UPDATE settings SET value = ? WHERE key = ?");
            $stmtUpd->execute([password_hash($data['new_pass'], PASSWORD_BCRYPT), "pass_" . $user_id]);
            echo json_encode(['success' => true]);
        } else echo json_encode(['success' => false, 'error' => 'Old password incorrect']);
        break;

    case 'get_youtube_sync':
        $stmt = $pdo->prepare("SELECT * FROM youtube_sync WHERE id = 1");
        $stmt->execute();
        $sync = $stmt->fetch();
        $stmt = $pdo->prepare("SELECT last_seen FROM user_status WHERE user_id = ?");
        $stmt->execute([($user_id == 1) ? 2 : 1]);
        $ls = $stmt->fetchColumn();
        $sync['other_online'] = ($ls && (time() - (int)$ls < 10));
        echo json_encode($sync);
        break;

    case 'update_youtube_sync':
        $data = json_decode(file_get_contents('php://input'), true);
        $sql = "UPDATE youtube_sync SET state = ?, current_time = ?, last_updated_by = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$data['state'] ?? 0, $data['current_time'] ?? 0, $user_id];
        if (isset($data['video_id'])) {
            $sql = "UPDATE youtube_sync SET video_id = ?, state = ?, current_time = ?, last_updated_by = ?, updated_at = CURRENT_TIMESTAMP";
            array_unshift($params, $data['video_id']);
        }
        $stmt = $pdo->prepare($sql . " WHERE id = 1");
        $stmt->execute($params);
        echo json_encode(['success' => true]);
        break;

    case 'send_youtube_comment':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, ($user_id == 1) ? 2 : 1, "YT_COMMENT:" . ($data['content'] ?? '')]);
        echo json_encode(['success' => true]);
        break;

    case 'get_youtube_comments':
        $stmt = $pdo->prepare("SELECT id, sender_id, content FROM messages WHERE content LIKE 'YT_COMMENT:%' AND created_at > datetime('now', '-2 minutes') AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 10");
        $stmt->execute();
        $comments = $stmt->fetchAll();
        foreach($comments as &$c) $c['content'] = str_replace('YT_COMMENT:', '', $c['content']);
        echo json_encode($comments);
        break;

    case 'send_knock_sms':
        $stmt = $pdo->prepare("SELECT key, value FROM settings WHERE key IN ('sms_api_key', 'sms_number_1', 'sms_number_2', 'sms_default_msg', 'sms_enabled_2')");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch()) $settings[$row['key']] = $row['value'];
        if ($user_id != 1 && ($settings['sms_enabled_' . $user_id] ?? '1') != '1') {
            echo json_encode(['success' => false, 'error' => 'SMS disabled']);
            break;
        }
        $api_key = $settings['sms_api_key'] ?? '';
        $to_number = $settings['sms_number_' . (($user_id == 1) ? 2 : 1)] ?? '';
        $msg = $settings['sms_default_msg'] ?? 'Waiting for response at {time}.';
        $data = json_decode(file_get_contents('php://input'), true);
        if (!empty($data['custom_text'])) $msg .= " " . $data['custom_text'];
        $msg = str_replace('{time}', (new DateTime('now', new DateTimeZone('Asia/Dhaka')))->format('Y-m-d h:i A'), $msg);
        if (empty($api_key) || empty($to_number)) { echo json_encode(['success' => false, 'error' => 'SMS not configured']); break; }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.sms.net.bd/sendsms?" . http_build_query(['api_key' => $api_key, 'msg' => $msg, 'to' => $to_number]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15
        ]);
        $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        if ($resp === false) {
            $pdo->prepare("INSERT INTO sms_history (user_id, phone_number, message, status, error_msg) VALUES (?, ?, ?, 'failed', ?)")->execute([$user_id, $to_number, $msg, "Error: $err"]);
            echo json_encode(['success' => false, 'error' => "Failed: $err"]);
        } else {
            $res = json_decode($resp, true);
            $status = (isset($res['error']) && $res['error'] == 0) ? 'sent' : 'failed';
            $pdo->prepare("INSERT INTO sms_history (user_id, phone_number, message, status, error_msg) VALUES (?, ?, ?, ?, ?)")->execute([$user_id, $to_number, $msg, $status, $res['msg'] ?? '']);
            echo json_encode(['success' => ($status === 'sent'), 'error' => $res['msg'] ?? '']);
        }
        break;

    case 'save_image':
        if ($user_id != 1) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); break; }
        $data = json_decode(file_get_contents('php://input'), true);
        $media = $data['media_data'] ?? $data['image_data'] ?? '';
        $is_v = $data['is_voice'] ?? 0;
        $is_vid = $data['is_video'] ?? 0;
        if (empty($media)) { echo json_encode(['success' => false, 'error' => 'No media']); break; }
        
        $prefix = $is_v ? 'p_voice' : ($is_vid ? 'p_video' : 'p_img');
        // Save to persistent premium_vault
        $path = saveBase64File($media, $prefix, 'premium_vault');
        
        if (!$path) { echo json_encode(['success' => false, 'error' => 'Failed save']); break; }
        $pdo->prepare("INSERT INTO saved_images (user_id, image_data, is_voice, is_video) VALUES (?, ?, ?, ?)")->execute([$user_id, $path, $is_v, $is_vid]);
        echo json_encode(['success' => true]);
        break;

    case 'get_saved_images':
        if ($user_id != 1) { echo json_encode([]); break; }
        $stmt = $pdo->prepare("SELECT * FROM saved_images WHERE user_id = ? ORDER BY saved_at DESC");
        $stmt->execute([$user_id]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'delete_saved_image':
        if ($user_id != 1) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); break; }
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("SELECT image_data FROM saved_images WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['image_id'] ?? 0, $user_id]);
        $path = $stmt->fetchColumn();
        // Delete from premium_vault
        if ($path && strpos($path, 'premium_vault/') === 0 && file_exists(__DIR__ . '/' . $path)) {
            unlink(__DIR__ . '/' . $path);
        }
        $pdo->prepare("DELETE FROM saved_images WHERE id = ? AND user_id = ?")->execute([$data['image_id'] ?? 0, $user_id]);
        echo json_encode(['success' => true]);
        break;
}
