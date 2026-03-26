<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    // Remove /admin/generators, /admin/content, etc. to get root
    $scriptDir = str_replace('\\', '/', $scriptDir);
    $segments = explode('/', trim($scriptDir, '/'));
    
    // Simple logic: if in admin, go up one level. 
    // Ideally, config should be in root/includes, so we can deduce root from __DIR__
    // But let's use a robust approach based on file location
    
    // We know this file is in includes/config/database.php
    // So ROOT is ../../ relative to this file.
    // However, for URL generation, we need the web path.
    
    // Let's rely on the server variables but be careful with subdirectories
    // A common trick is to remove the current file's relative path from the script name
    
    // Alternative: Just use the hardcoded one if detection fails, or allow override
    // But let's try to detect.
    
    // Current script could be /sohojweb/admin/index.php
    // We want /sohojweb
    
    // Let's use a fixed definition for now but allow environment override if needed
    // or just improve the hardcoded one to be a variable
    
    return $protocol . "://" . $host . "/sohojweb"; 
}

// Allow environment variable override
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'sohojweb');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Dynamic Base URL
$base_url = getBaseUrl();
define('BASE_URL', $base_url);
define('ADMIN_URL', BASE_URL . '/admin');
define('UPLOAD_PATH', __DIR__ . '/../../assets/uploads');
define('UPLOAD_URL', BASE_URL . '/assets/uploads');

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production, log this, don't show it
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check logs.");
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

    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function insert($table, $data) {
        $keys = array_keys($data);
        $fields = implode(', ', $keys);
        $placeholders = implode(', ', array_map(fn($k) => ":$k", $keys));
        
        $sql = "INSERT INTO $table ($fields) VALUES ($placeholders)";
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $key) {
            $setParts[] = "$key = :$key";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE $table SET $setClause WHERE $where";
        $this->query($sql, array_merge($data, $whereParams));
        return true;
    }

    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        $this->query($sql, $params);
        return true;
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }
    
    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }
}

function db() {
    return Database::getInstance();
}

// Load Core Functions
require_once __DIR__ . '/../functions/core.php';
