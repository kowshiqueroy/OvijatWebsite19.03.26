<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config/database.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Ensure UPLOAD_PATH exists
$teamDir = UPLOAD_PATH . '/team';
if (!is_dir($teamDir)) {
    mkdir($teamDir, 0777, true);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'No file uploaded or upload error']);
}

$file = $_FILES['file'];
$allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];

if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
} else {
    $mime = $file['type'];
}

if (!in_array($mime, $allowedTypes)) {
    jsonResponse(['success' => false, 'message' => 'Invalid file type. Allowed: PNG, JPG, WebP']);
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = 'member_' . time() . '_' . uniqid() . '.' . $extension;
$targetPath = $teamDir . '/' . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $url = UPLOAD_URL . '/team/' . $filename;
    jsonResponse(['success' => true, 'url' => $url, 'filename' => $filename]);
} else {
    jsonResponse(['success' => false, 'message' => 'Failed to move uploaded file']);
}
