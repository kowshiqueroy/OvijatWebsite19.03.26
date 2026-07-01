<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => 'POST with image file required']);
    exit;
}

$f = $_FILES['image'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload error code: ' . $f['error']]);
    exit;
}
if ($f['size'] > MAX_UPLOAD_SIZE) {
    echo json_encode(['success' => false, 'error' => 'File too large (max 5 MB)']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $f['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, ALLOWED_IMG_TYPES)) {
    echo json_encode(['success' => false, 'error' => 'Only JPG, PNG, GIF, WEBP allowed']);
    exit;
}

$ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
$name = 'u' . $user['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$dest = UPLOAD_DIR . $name;

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!move_uploaded_file($f['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}

echo json_encode(['success' => true, 'url' => UPLOAD_URL . $name]);
