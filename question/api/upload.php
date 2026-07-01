<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['ok' => false, 'error' => 'No image uploaded'], 400);
}

$file = $_FILES['image'];
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

if (!isset($allowed[$mime])) {
    jsonResponse(['ok' => false, 'error' => 'Unsupported image type'], 400);
}
if ($file['size'] > 8 * 1024 * 1024) {
    jsonResponse(['ok' => false, 'error' => 'Image too large (max 8MB)'], 400);
}

$userDir = UPLOAD_DIR . '/' . $user['id'];
if (!is_dir($userDir)) mkdir($userDir, 0755, true);

$filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
$dest = $userDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    jsonResponse(['ok' => false, 'error' => 'Failed to save image'], 500);
}

jsonResponse(['ok' => true, 'url' => BASE_URL . '/uploads/' . $user['id'] . '/' . $filename]);
