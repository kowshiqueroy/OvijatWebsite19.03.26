<?php
/**
 * Image Optimizer Utility
 * Handles WebP conversion and resizing.
 */

class ImageOptimizer {
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $targetExtension = '.webp';
    private $quality = 80;

    public function __construct() {
        // Class initialized
    }

    /**
     * Process an uploaded image
     * @param array $file The $_FILES element
     * @param string $destinationDir Where to save
     * @return string|false Path to the image (relative to dest) or false on failure
     */
    public function processUpload($file, $destinationDir) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        if (!in_array($file['type'], $this->allowedTypes)) {
            return false;
        }

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $filename = pathinfo($file['name'], PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Check for GD extension
        if (!extension_loaded('gd')) {
            // Fallback: Just move the file without optimization
            $fallbackName = $filename . '_' . time() . '.' . $extension;
            $destinationPath = rtrim($destinationDir, '/') . '/' . $fallbackName;
            if (move_uploaded_file($file['tmp_name'], $destinationPath)) {
                return $fallbackName;
            }
            return false;
        }

        // Proceed with WebP optimization if GD is available
        $uniqueName = $filename . '_' . time() . $this->targetExtension;
        $destinationPath = rtrim($destinationDir, '/') . '/' . $uniqueName;

        if ($this->convertToWebP($file['tmp_name'], $destinationPath)) {
            return $uniqueName;
        }

        return false;
    }

    /**
     * Convert Image to WebP
     */
    private function convertToWebP($sourcePath, $destinationPath) {
        $info = getimagesize($sourcePath);
        if (!$info) return false;

        $mime = $info['mime'];
        $image = null;

        try {
            switch ($mime) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($sourcePath);
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($sourcePath);
                    imagepalettetotruecolor($image);
                    break;
                case 'image/webp':
                    $image = imagecreatefromwebp($sourcePath);
                    break;
                default:
                    return false;
            }

            if ($image) {
                $result = imagewebp($image, $destinationPath, $this->quality);
                imagedestroy($image);
                return $result;
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }
}
