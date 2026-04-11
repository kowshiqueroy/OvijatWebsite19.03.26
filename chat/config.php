<?php
/**
 * Core Configuration & Database Connection
 * Hyper-secure ephemeral messaging application
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'chat_secure');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SESSION_TIMEOUT', 1800);
define('INBOX_LOCK_TIMEOUT', 30);
define('MESSAGE_DELETE_DELAY', 60);
define('MEDIA_PATH', dirname(__DIR__) . '/chat_media/');
define('DUMMY_MEDIA_PATH', dirname(__DIR__) . '/chat_dummy_media/');

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit(json_encode(['error' => 'Database connection failed']));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
}

function getDB() {
    return Database::getInstance();
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['session_token']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        http_response_code(401);
        exit(json_encode(['error' => 'Unauthorized']));
    }
    return $_SESSION['user_id'];
}

function isInboxUnlocked() {
    return isset($_SESSION['inbox_unlocked']) && $_SESSION['inbox_unlocked'] === true;
}

function isThreadUnlocked($contactId) {
    return isset($_SESSION['unlocked_threads'][$contactId]) && 
           $_SESSION['unlocked_threads'][$contactId] > time();
}

function unlockThread($contactId) {
    $_SESSION['unlocked_threads'][$contactId] = time() + INBOX_LOCK_TIMEOUT;
}

function lockAllThreads() {
    $_SESSION['unlocked_threads'] = [];
}

function checkInactivity() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > INBOX_LOCK_TIMEOUT)) {
        lockAllThreads();
        $_SESSION['inbox_unlocked'] = false;
    }
    $_SESSION['last_activity'] = time();
}