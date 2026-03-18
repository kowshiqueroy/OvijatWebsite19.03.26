<?php
// admin/upload_helper.php — Shared image upload + GD compression
// Returns ['success'=>true,'filename'=>'abc.jpg'] or ['success'=>false,'error'=>'...']

function uploadImage(
    array  $file,
    string $destDir,
    int    $maxWidth  = 1200,
    int    $quality   = 85,
    int    $maxBytes  = 2097152  // 2 MB
): array {

    // 1. Basic checks
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error code: ' . ($file['error'] ?? '?')];
    }
    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'error' => 'File exceeds 2 MB limit.'];
    }

    // 2. MIME validation via finfo (not relying on extension alone)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($file['tmp_name']);
    $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!array_key_exists($mime, $allowed)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, and WebP images are allowed.'];
    }
    $ext = $allowed[$mime];

    // 3. Random filename
    $newName  = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = rtrim($destDir, '/') . '/' . $newName;

    // 4. GD resize / compress
    [$origW, $origH] = getimagesize($file['tmp_name']);

    // Load source
    $src = match($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
        default      => false,
    };

    if (!$src) {
        return ['success' => false, 'error' => 'Could not process image with GD.'];
    }

    // Calculate target dimensions
    if ($origW > $maxWidth) {
        $ratio  = $maxWidth / $origW;
        $newW   = $maxWidth;
        $newH   = (int)round($origH * $ratio);
    } else {
        $newW = $origW;
        $newH = $origH;
    }

    $dst = imagecreatetruecolor($newW, $newH);

    // Preserve transparency for PNG/WebP
    if (in_array($mime, ['image/png', 'image/webp'])) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagedestroy($src);

    // Save
    $saved = match($mime) {
        'image/jpeg' => imagejpeg($dst, $destPath, $quality),
        'image/png'  => imagepng($dst, $destPath, (int)round((100 - $quality) / 10)),
        'image/webp' => imagewebp($dst, $destPath, $quality),
        default      => false,
    };
    imagedestroy($dst);

    if (!$saved) {
        return ['success' => false, 'error' => 'Could not save processed image.'];
    }

    return ['success' => true, 'filename' => $newName];
}

function deleteUpload(string $dir, ?string $filename): void {
    if ($filename) {
        $path = rtrim($dir, '/') . '/' . basename($filename);
        if (file_exists($path)) unlink($path);
    }
}
