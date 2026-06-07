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
$authKey = 'auth_' . md5($dbPath);

if (!isset($_SESSION[$authKey])) {
    if ($dir === 'premium_vault') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_images WHERE image_data = ? AND user_id = ?");
        $stmt->execute([$dbPath, $_SESSION['user_id']]);
        $isOwner = $stmt->fetchColumn() > 0;

        if (!$isOwner) {
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
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE (content = ? OR content LIKE ?) AND (sender_id = ? OR receiver_id = ?) AND deleted_at IS NULL");
        $stmt->execute([$dbPath, '%' . $dbPath, $_SESSION['user_id'], $_SESSION['user_id']]);
        $isAuthorized = $stmt->fetchColumn() > 0;
        
        if (!$isAuthorized) {
            http_response_code(403);
            exit('Unauthorized access to this upload');
        }
    }
    $_SESSION[$authKey] = true;
}

// Release session lock to allow concurrent Range requests from browsers (especially Safari)
session_write_close();

// Serve the file with Range support
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$map = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 
    'webp' => 'image/webp', 'gif' => 'image/gif',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
    'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav',
    'm4a' => 'audio/mp4'
];
$mime = $map[$ext] ?? (function_exists('mime_content_type') ? mime_content_type($fullPath) : 'application/octet-stream');

// Optimization for large files
set_time_limit(0);
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 'Off');

// Disable output buffering to prevent memory exhaustion and chunked encoding
while (ob_get_level()) {
    ob_end_clean();
}

$size = filesize($fullPath);
$start = 0;
$end = $size - 1;

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=31536000'); // Enable browser cache (1 year)
header('Content-Disposition: inline; filename="' . $file . '"');
header('Connection: keep-alive');

if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    if (preg_match('/bytes=(\d+)-(\d+)?/', $range, $matches)) {
        $start = (int)$matches[1];
        if (isset($matches[2]) && $matches[2] !== '') {
            $end = (int)$matches[2];
        }
    }

    if ($start > $end || $start >= $size) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: bytes */$size");
        exit;
    }

    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$size");
    $length = $end - $start + 1;
    header("Content-Length: $length");

    $fp = fopen($fullPath, 'rb');
    fseek($fp, $start);
    $buffer = 65536; // 64KB chunks
    while ($length > 0 && !feof($fp)) {
        $read = min($buffer, $length);
        echo fread($fp, $read);
        $length -= $read;
    }
    fclose($fp);
} else {
    header("Content-Length: $size");
    readfile($fullPath);
}
exit;
