<?php
/**
 * Secure Ephemeral Messaging Application - Setup & Database Manager
 * Run this file to initialize/reset the database
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'chat_secure');
define('DB_USER', 'root');
define('DB_PASS', '');

ini_set('display_errors', 1);
error_reporting(E_ALL);

class Database {
    private $pdo;
    private static $instance = null;

    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
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

    public function createDatabase() {
        $this->pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " 
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->pdo->exec("USE " . DB_NAME);
    }

    public function createTables() {
        $this->pdo->exec("DROP DATABASE IF EXISTS " . DB_NAME);
        $this->pdo->exec("CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->pdo->exec("USE " . DB_NAME);

        $this->pdo->exec("CREATE TABLE users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) DEFAULT NULL,
            duress_pin VARCHAR(10) DEFAULT '0000',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->pdo->exec("CREATE TABLE contacts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            contact_user_id INT UNSIGNED NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            thread_pin VARCHAR(10) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_message_at TIMESTAMP NULL,
            UNIQUE KEY unique_contact (user_id, contact_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->pdo->exec("CREATE TABLE messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_id INT UNSIGNED NOT NULL,
            receiver_id INT UNSIGNED NOT NULL,
            contact_id INT UNSIGNED NOT NULL,
            content TEXT NOT NULL,
            media_path VARCHAR(500) DEFAULT NULL,
            media_type VARCHAR(50) DEFAULT NULL,
            is_visible TINYINT(1) DEFAULT 1,
            viewed_at TIMESTAMP NULL,
            deletion_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->pdo->exec("CREATE TABLE dummy_contacts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            last_message_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->pdo->exec("CREATE TABLE dummy_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_id INT UNSIGNED NOT NULL,
            receiver_id INT UNSIGNED NOT NULL,
            contact_id INT UNSIGNED NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->pdo->exec("CREATE TABLE sessions (
            id VARCHAR(128) PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function seedTestData() {
        $passwordHash = password_hash('TestPass123!', PASSWORD_BCRYPT);
        
        $checkUser = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkUser->execute(['alice']);
        
        if (!$checkUser->fetch()) {
            $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash, duress_pin) VALUES (?, ?, ?)");
            $stmt->execute(['alice', $passwordHash, '0000']);
            $stmt->execute(['bob', $passwordHash, '0000']);
            $stmt->execute(['charlie', $passwordHash, '0000']);
        }

        $checkContact = $this->pdo->prepare("SELECT id FROM contacts WHERE user_id = 1 AND contact_user_id = 2");
        $checkContact->execute();
        
        if (!$checkContact->fetch()) {
            $contactStmt = $this->pdo->prepare("INSERT INTO contacts (user_id, contact_user_id, display_name, thread_pin) VALUES (?, ?, ?, ?)");
            $contactStmt->execute([1, 2, 'System Update', '1234']);
            $contactStmt->execute([1, 3, 'Carrier Settings', '5678']);
            
            $msgStmt = $this->pdo->prepare("INSERT INTO messages (sender_id, receiver_id, contact_id, content, created_at) VALUES (?, ?, ?, ?, NOW() - INTERVAL 2 MINUTE)");
            $msgStmt->execute([2, 1, 1, 'System update pending. Restart required.']);
            $msgStmt->execute([3, 1, 2, 'Carrier settings updated.']);
        }

        $checkDummy = $this->pdo->prepare("SELECT id FROM dummy_contacts WHERE user_id = 1");
        $checkDummy->execute([]);
        
        if (!$checkDummy->fetch()) {
            $dummyContact = $this->pdo->prepare("INSERT INTO dummy_contacts (user_id, display_name, last_message_at) VALUES (?, ?, NOW())");
            $dummyContact->execute([1, 'System Maintenance']);
            $dummyContact->execute([1, 'Firmware Update']);
            
            $dummyMsg = $this->pdo->prepare("INSERT INTO dummy_messages (sender_id, receiver_id, contact_id, content, created_at) VALUES (?, ?, ?, ?, NOW())");
            $dummyMsg->execute([99, 1, 1, 'System maintenance scheduled for 3:00 AM']);
            $dummyMsg->execute([99, 1, 2, 'Firmware update available.']);
        }
    }

    public function showTables() {
        $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        return $tables;
    }

    public function getTableStatus($table) {
        $status = $this->pdo->query("SELECT COUNT(*) as count FROM `$table`")->fetch();
        return $status['count'];
    }

    public function getLastError() {
        return $this->pdo->errorInfo();
    }
}

if (php_sapi_name() === 'cli' || isset($_GET['setup'])) {
    header('Content-Type: text/plain');
    
    echo "=== Secure Ephemeral Messaging - Database Setup ===\n\n";
    
    try {
        $db = Database::getInstance();
        $db->createDatabase();
        echo "[OK] Database created/verified\n";
        
        $db->createTables();
        echo "[OK] Tables created\n";
        
        $db->seedTestData();
        echo "[OK] Test data seeded\n";
        
        echo "\n=== Database Tables ===\n";
        foreach ($db->showTables() as $table) {
            $count = $db->getTableStatus($table);
            echo "  - $table: $count rows\n";
        }
        
        echo "\n=== Test Credentials ===\n";
        echo "  Username: alice, Password: TestPass123!\n";
        echo "  Username: bob, Password: TestPass123!\n";
        echo "  Username: charlie, Password: TestPass123!\n";
        
        echo "\n=== Setup Complete ===\n";
        
    } catch (Exception $e) {
        echo "[ERROR] " . $e->getMessage() . "\n";
    }
}
?>