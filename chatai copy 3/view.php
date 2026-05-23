<?php
require_once 'auth.php';
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$file = $_GET['file'] ?? '';
if (!$file) {
    http_response_code(400);
    exit('No file specified');
}

// Security: Prevent directory traversal
$file = basename($file);
$dir = $_GET['dir'] ?? 'uploads';
$allowedDirs = ['uploads', 'premium_vault'];

if (!in_array($dir, $allowedDirs)) {
    http_response_code(403);
    exit('Forbidden directory');
}

$fullPath = __DIR__ . '/' . $dir . '/' . $file;

if (!file_exists($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

// Security: Permission check
$dbPath = $dir . '/' . $file;

if ($dir === 'premium_vault') {
    // Check if current user owns this file in their vault
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_images WHERE image_data = ? AND user_id = ?");
    $stmt->execute([$dbPath, $_SESSION['user_id']]);
    $isOwner = $stmt->fetchColumn() > 0;

    if (!$isOwner) {
        // Check if shared in messages (Search for path in content, handling '||' prefix if present)
        // We use LIKE to catch "📎 Vault: name.jpg||premium_vault/filename.jpg"
        $searchPath = '%' . $dbPath;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE content LIKE ? AND (sender_id = ? OR receiver_id = ?) AND deleted_at IS NULL");
        $stmt->execute([$searchPath, $_SESSION['user_id'], $_SESSION['user_id']]);
        $isShared = $stmt->fetchColumn() > 0;

        if (!$isShared) {
            http_response_code(403);
            exit('Unauthorized access to this file');
        }
    }
} else if ($dir === 'uploads') {
    // Check if shared in messages where user is sender or receiver
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE (content = ? OR content LIKE ?) AND (sender_id = ? OR receiver_id = ?) AND deleted_at IS NULL");
    $stmt->execute([$dbPath, '%' . $dbPath, $_SESSION['user_id'], $_SESSION['user_id']]);
    $isAuthorized = $stmt->fetchColumn() > 0;
    
    if (!$isAuthorized) {
        http_response_code(403);
        exit('Unauthorized access to this upload');
    }
}

// Serve the file
if (function_exists('mime_content_type')) {
    $mime = mime_content_type($fullPath);
} else {
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 
        'webp' => 'image/webp', 'gif' => 'image/gif',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
        'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav'
    ];
    $mime = $map[$ext] ?? 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=3600');
readfile($fullPath);
exit;
