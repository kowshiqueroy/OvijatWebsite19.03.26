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

/**
 * Helper to save base64 data as a file.
 */
function saveBase64File($base64Data, $prefix = 'file', $targetDir = 'uploads') {
    global $pdo;
    if (empty($base64Data)) return false;
    
    $allowedDirs = ['uploads', 'premium_vault'];
    if (!in_array($targetDir, $allowedDirs)) return false;

    $fullTargetDir = __DIR__ . '/' . $targetDir . '/';
    if (!file_exists($fullTargetDir)) mkdir($fullTargetDir, 0755, true);
    
    // Secure file copy from uploads to premium_vault (prevent traversal)
    if (is_string($base64Data) && strpos($base64Data, 'uploads/') === 0) {
        $cleanPath = basename($base64Data);
        $sourceFile = __DIR__ . '/uploads/' . $cleanPath;
        if (!file_exists($sourceFile)) return false;
        
        $extension = strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm', 'mov', 'ogg', 'wav'];
        if (!in_array($extension, $allowedExts)) return false;

        $hash = md5_file($sourceFile);
        $table = ($targetDir === 'premium_vault') ? 'saved_images' : 'messages';
        $col = ($targetDir === 'premium_vault') ? 'image_data' : 'content';
        $stmt = $pdo->prepare("SELECT $col FROM $table WHERE file_hash = ? LIMIT 1");
        $stmt->execute([$hash]);
        $existing = $stmt->fetchColumn();
        if ($existing && file_exists(__DIR__ . '/' . $existing)) return $existing;

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
    $hash = md5($data);

    $table = ($targetDir === 'premium_vault') ? 'saved_images' : 'messages';
    $col = ($targetDir === 'premium_vault') ? 'image_data' : 'content';
    $stmt = $pdo->prepare("SELECT $col FROM $table WHERE file_hash = ? LIMIT 1");
    $stmt->execute([$hash]);
    $existing = $stmt->fetchColumn();
    if ($existing && file_exists(__DIR__ . '/' . $existing)) return $existing;
    
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

// Action Dispatcher
$actionMap = [
    'send_message' => 'messaging',
    'get_messages' => 'messaging',
    'mark_viewed' => 'messaging',
    'schedule_delete' => 'messaging',
    'delete_my_unseen' => 'messaging',
    'delete_my_messages' => 'messaging',
    
    'verify_pin' => 'auth_settings',
    'nuclear_wipe' => 'auth_settings',
    'reset_pin' => 'auth_settings',
    'update_pin' => 'auth_settings',
    'update_password' => 'auth_settings',
    
    'save_image' => 'media',
    'get_saved_images' => 'media',
    'vault_audit' => 'media',
    'vault_cleanup' => 'media',
    'delete_saved_image' => 'media',
    'save_recording' => 'media',
    'upload_chunk' => 'media',
    
    'update_status' => 'status',
    'get_other_status' => 'status',
    'theater_status' => 'status',
    'leave_theater_beacon' => 'status',
    
    'get_youtube_sync' => 'youtube',
    'update_youtube_sync' => 'youtube',
    'send_youtube_comment' => 'youtube',
    'get_youtube_comments' => 'youtube',
    'delete_youtube_comments' => 'youtube',
    'get_video_history' => 'youtube',
    'burn_yt_comments' => 'youtube',
    
    'register_peer' => 'calls',
    'get_peer' => 'calls',
    'send_call_comment' => 'calls',
    'get_call_comments' => 'calls',
    'delete_call_comment' => 'calls'
];

if (isset($actionMap[$action])) {
    $file = __DIR__ . '/actions/' . $actionMap[$action] . '.php';
    if (file_exists($file)) {
        require_once $file;
    } else {
        echo json_encode(['success' => false, 'error' => 'Action file missing']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
