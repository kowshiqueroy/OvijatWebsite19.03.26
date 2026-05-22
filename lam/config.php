<?php
// ============================================================
// config.php — Central configuration for POS System
// ============================================================

define('APP_NAME',    'SohojWeb POS');
define('APP_VERSION', '3.2.1');

// ── Auto-detect BASE_URL (works on localhost, ngrok, and real domain) ──
$protocol = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    $_SERVER['SERVER_PORT'] == 443 ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
) ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
define('BASE_URL', $protocol . '://' . $host . $basePath);

// ── Database ─────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'pos_db');
define('DB_USER', 'root');
define('DB_PASS', '');

define('BASE_PATH',   __DIR__);

// ── Charset ──────────────────────────────────────────────────
define('DB_CHARSET', 'utf8mb4');

// ── Currency & Tax ────────────────────────────────────────────
define('CURRENCY_SYMBOL', '৳');
define('DEFAULT_VAT',      0.15);   // 15%
define('POINTS_RATE',      0.01);   // 1 point per $1 spent

// ── Roles ─────────────────────────────────────────────────────
define('ROLE_ADMIN', 'admin');
define('ROLE_SR',    'sr');

// ── Session ───────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600);   // seconds

// ── Paths ─────────────────────────────────────────────────────
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('BARCODE_PATH', UPLOADS_PATH . '/barcodes');
define('BARCODE_URL',  BASE_URL   . '/uploads/barcodes');

// ── Error display (set false in production) ───────────────────
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set('Asia/Dhaka');
