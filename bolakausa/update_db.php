<?php
require_once 'config/database.php';

try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'warehouse', 'viewer', 'wholesale_user') NOT NULL DEFAULT 'wholesale_user'");
    echo "Success: Users table ENUM updated.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
