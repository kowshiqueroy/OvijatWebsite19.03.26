<?php
require_once '../config.php';

// Simple protection: only allow users who know the URL or are logged in
// You can add more complex checks here if needed.

if (!isset($_SESSION['user_id'])) {
    // Optional: Only allow logged in users to download
    // header("Location: ../index.php");
    // exit;
}

$file = 'OvijatAppV2.apk';

if (file_exists($file)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="'.basename($file).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
} else {
    echo "File not found.";
}
?>