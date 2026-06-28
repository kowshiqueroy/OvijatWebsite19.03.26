<?php
// config.php

// Set default timezone to Dhaka
date_default_timezone_set('Asia/Dhaka');

// Helper to convert UTC date strings (e.g. from SQLite) to Dhaka timezone
function toDhaka(?string $dateStr): string {
    if (empty($dateStr)) return '';
    try {
        $dt = new DateTime($dateStr, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Dhaka'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return $dateStr;
    }
}

// Define constants
define('BASE_DIR', __DIR__);
define('DB_DIR', BASE_DIR . '/database');
define('CORE_DB_PATH', DB_DIR . '/core.sqlite');
define('CHAT_DB_DIR', DB_DIR . '/db_chats');
define('UPLOAD_DIR', BASE_DIR . '/public/uploads');
define('RECORDINGS_DIR', BASE_DIR . '/server_records');

// Ensure necessary directories exist
foreach ([DB_DIR, CHAT_DB_DIR, UPLOAD_DIR, RECORDINGS_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// Secure session start
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    // In production we should use session.cookie_secure, but on local dev (HTTP) we skip it
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

// Simple Class Autoloader
spl_autoload_register(function ($class) {
    // Convert namespace separators to directory separators in the relative class name, append with .php
    $file = BASE_DIR . '/src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Update last_seen for logged-in users on every request (using Unix timestamp for timezone safety)
if (isset($_SESSION['user_id'])) {
    try {
        $coreDb = Database::getCoreConnection();
        $updateStmt = $coreDb->prepare("UPDATE users SET last_seen = ? WHERE id = ?");
        $updateStmt->execute([time(), $_SESSION['user_id']]);
    } catch (\Exception $ex) {
        // Ignore DB/Autoload connection errors during bootstrap
    }
}

// Helper for security escaping
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
