<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'swp');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'SohojWeb Projects');

// ─── BASE URL ────────────────────────────────────────────────────────────────
// Uncomment the environment you are currently serving from.
// No trailing slash.

// define('BASE_URL', 'http://localhost/projects');                                    // Local XAMPP
// define('BASE_URL', 'https://811c-202-191-127-233.ngrok-free.app/projects');        // ngrok tunnel
// define('BASE_URL', 'https://projects.sohojweb.com');                               // Live domain (root)

// ─── AUTO-DETECT (used when none of the above is uncommented) ────────────────
if (!defined('BASE_URL')) {
    $scheme = 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

    $httpsOn        = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $port443        = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443;
    $forwardedHttps = (
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])    && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'])    === 'https') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTOCOL']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTOCOL']) === 'https')
    );
    if ($httpsOn || $port443 || $forwardedHttps) {
        $scheme = 'https';
    }

    // Derive the sub-directory from DOCUMENT_ROOT vs the app's physical location
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $appRoot = rtrim(str_replace('\\', '/', __DIR__), '/');
    $subPath = ($docRoot && strpos($appRoot, $docRoot) === 0)
        ? substr($appRoot, strlen($docRoot))
        : '';

    define('BASE_URL', $scheme . '://' . $host . $subPath);
}

