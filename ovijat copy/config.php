<?php
// ============================================================
// config.php — Ovijat Food & Beverage Industries Ltd.
// Central configuration: DB, BASE_URL, CSRF, Session
// ============================================================

// --- Environment Detection & BASE_URL ---
// Uses __DIR__ (always the project root where config.php lives).
// This is reliable regardless of which page includes this file.
$domain = "ovijatfood.com";
$host = $_SERVER['HTTP_HOST'] ?? '';
$isNotDomain = $host !== $domain && $host !== 'www.' . $domain;

// --- Database Credentials ---
define('DB_HOST', 'localhost');

define('DB_CHARSET', 'utf8mb4');

if ($isNotDomain) {
    define('DB_NAME', 'ovijat_food');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    $docRoot     = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $projectRoot = rtrim(str_replace('\\', '/', __DIR__), '/');
    $subFolder = str_replace($docRoot, '', $projectRoot);
    define('BASE_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $subFolder);
} else {
    define('BASE_URL', 'https://' . $domain);
    define('DB_NAME', 'ovijattt_mainweb7678');
    define('DB_USER', 'ovijattt_KRkush12');
    define('DB_PASS', 'KRkush5877');
}


// --- PDO Connection (Singleton) ---
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            ensureTables($pdo);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Database connection failed. Please try again later.');
        }
    }
    return $pdo;
}

function ensureTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_info (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(100) NOT NULL,
        address TEXT,
        phone VARCHAR(100),
        email VARCHAR(150),
        whatsapp VARCHAR(50),
        show_header TINYINT(1) NOT NULL DEFAULT 1,
        show_footer TINYINT(1) NOT NULL DEFAULT 1,
        show_contact_page TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// --- Session ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !$isNotDomain,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// --- CSRF Token ---
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        exit('CSRF validation failed.');
    }
}

// --- Output Helper ---
function e(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// --- Rate Limiter (Contact Form) ---
function checkRateLimit(string $ip, int $maxPerDay = 5): bool {
    $pdo  = getPDO();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM contact_submissions
         WHERE ip_address = ? AND created_at >= CURDATE()"
    );
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn() < $maxPerDay;
}
