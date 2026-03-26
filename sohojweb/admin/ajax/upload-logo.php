<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config/database.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Ensure UPLOAD_PATH exists
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

$type = $_POST['type'] ?? 'logo';
$allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg+xml', 'image/webp', 'image/x-icon'];

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'No file uploaded or upload error']);
}

$file = $_FILES['file'];

// Basic mime type check
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
} else {
    $mime = $file['type'];
}

if (!in_array($mime, $allowedTypes) && !in_array($file['type'], $allowedTypes)) {
    jsonResponse(['success' => false, 'message' => 'Invalid file type. Allowed: PNG, JPG, GIF, SVG, WebP, ICO']);
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = $type . '_' . time() . '.' . $extension;
$targetPath = UPLOAD_PATH . '/' . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $settingKey = 'company_logo';
    if ($type === 'favicon') $settingKey = 'company_favicon';
    elseif ($type === 'logo_large') $settingKey = 'company_logo_large';
    
    $url = UPLOAD_URL . '/' . $filename;
    
    db()->query("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$settingKey, $url, $url]);
    
    jsonResponse(['success' => true, 'url' => $url, 'filename' => $filename]);
} else {
    jsonResponse(['success' => false, 'message' => 'Failed to move uploaded file']);
}
