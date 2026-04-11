<?php
/**
 * Secure Media Handler
 * Serves media files only to authorized users in unlocked threads
 * Media is stored outside public_html/www
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'chat_secure');
define('DB_USER', 'root');
define('DB_PASS', '');

ini_set('display_errors', 0);
ini_set('log_errors', 1);

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Access denied');
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
}

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        http_response_code(401);
        exit('Access denied');
    }

    return $_SESSION['user_id'];
}

function isThreadUnlocked($contactId) {
    return isset($_SESSION['unlocked_threads'][$contactId]) && 
           $_SESSION['unlocked_threads'][$contactId] > time();
}

function checkMediaDeletion($messageId, $userId) {
    $db = Database::getInstance();
    
    $message = $db->getConnection()->prepare(
        "SELECT m.deletion_at, m.contact_id, c.user_id 
         FROM messages m 
         JOIN contacts c ON m.contact_id = c.id 
         WHERE m.id = ?"
    );
    $message->execute([$messageId]);
    $msg = $message->fetch();

    if (!$msg) {
        return false;
    }

    if ($msg['user_id'] != $userId) {
        return false;
    }

    if ($msg['deletion_at'] && strtotime($msg['deletion_at']) <= time()) {
        return false;
    }

    return true;
}

function serveMedia($mediaPath, $mediaType) {
    if (!file_exists($mediaPath)) {
        http_response_code(404);
        exit('File not found');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $finfo);
    finfo_close($finfo);

    header('Content-Type: ' . ($mediaType ?: $realMime));
    header('Content-Length: ' . filesize($mediaPath));
    header('Content-Disposition: inline; filename="file"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    readfile($mediaPath);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $userId = requireLogin();
    $messageId = (int)($_GET['message_id'] ?? 0);
    $contactId = (int)($_GET['contact_id'] ?? 0);

    if (empty($messageId) || empty($contactId)) {
        http_response_code(400);
        exit('Invalid request');
    }

    if (!isThreadUnlocked($contactId)) {
        http_response_code(403);
        exit('Thread locked');
    }

    if (!checkMediaDeletion($messageId, $userId)) {
        http_response_code(410);
        exit('Message expired');
    }

    $db = Database::getInstance();
    
    $message = $db->getConnection()->prepare(
        "SELECT media_path, media_type FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)"
    );
    $message->execute([$messageId, $userId, $userId]);
    $msg = $message->fetch();

    if (!$msg || !$msg['media_path']) {
        http_response_code(404);
        exit('Media not found');
    }

    serveMedia($msg['media_path'], $msg['media_type']);
}

http_response_code(400);
exit('Invalid request');