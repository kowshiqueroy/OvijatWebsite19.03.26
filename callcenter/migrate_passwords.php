<?php
/**
 * Migration: Encrypt plaintext passwords in settings and pbx_settings.
 * Run once then DELETE.
 */
require_once 'config.php';
requireLogin();

// Restricted to IT/Management
$dept = strtolower(trim($_SESSION['department'] ?? ''));
if (!in_array($dept, ['it', 'management'])) {
    die('Unauthorized');
}

echo "<h3>Starting password migration...</h3>";

// 1. settings table
$keys = ['pbx_password', 'db_password'];
foreach ($keys as $key) {
    $val = getSetting($key);
    if ($val && !str_contains($val, '==')) { // Basic check for base64 encoded string
        $enc = encryptData($val);
        $conn->query("UPDATE settings SET setting_value='" . $conn->real_escape_string($enc) . "' WHERE setting_key='$key'");
        echo "Updated setting: $key<br>";
    }
}

// 2. pbx_settings table
$res = $conn->query("SELECT id, db_password FROM pbx_settings");
while ($row = $res->fetch_assoc()) {
    $pw = $row['db_password'];
    if ($pw && !str_contains($pw, '==')) {
        $enc = encryptData($pw);
        $conn->query("UPDATE pbx_settings SET db_password='" . $conn->real_escape_string($enc) . "' WHERE id=" . $row['id']);
        echo "Updated pbx_settings ID: " . $row['id'] . "<br>";
    }
}

echo "<h4>Migration complete. Please delete this file.</h4>";
