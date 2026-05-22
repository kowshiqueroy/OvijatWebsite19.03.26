<?php
/**
 * Core Database Class (PDO Wrapper)
 * Uses strict prepared statements for security.
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $error;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            throw new Exception("Database Connection Failed: " . $this->error);
        }
    }

    /**
     * Singleton Instance
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Main Query Executor
     * @param string $sql The SQL query
     * @param array $params Optional parameters for prepared statement
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Query Error: " . $e->getMessage());
        }
    }

    /**
     * Get the last inserted ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Transaction Helpers
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }

    /**
     * Quote a string for use in a query
     */
    public function quote($string) {
        return $this->pdo->quote($string);
    }
}
