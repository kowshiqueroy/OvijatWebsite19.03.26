<?php
/* DB Config */
define('DB_HOST', 'localhost');
if ($_SERVER['SERVER_NAME'] != "localhost" && strpos($_SERVER['SERVER_NAME'], "free.app") === false) {
    define('DB_NAME', 'u312077073_app');
    define('DB_USER', 'u312077073_app');
    define('DB_PASS', 'KR5877kush');
} else {
    define('DB_NAME', 'oeis');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}
define('DB_CHARSET', 'utf8mb4');

/* App Info */
define('APP_NAME',       'Ovijat EIS');
define('DEVELOPER_NAME', 'Kowshique Roy');
define('VERSION_NAME',   '3.0.0');

session_start();
date_default_timezone_set('Asia/Dhaka');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#dc2626;">
            <strong>Database connection failed.</strong><br>
            <small>' . htmlspecialchars($conn->connect_error) . '</small>
         </div>');
}
$conn->set_charset('utf8mb4');

/* ── CSRF helpers ─────────────────────────────────────────── */

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/* ── XSS helper ───────────────────────────────────────────── */
function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
