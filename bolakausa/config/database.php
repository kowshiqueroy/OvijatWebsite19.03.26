<?php
/**
 * Auto-generated Database Configuration
 */
$host = 'localhost';
$db   = 'bolakausa_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     $pdo->exec("SET time_zone = '" . date('P') . "'");
     
     // Safe database migrations
     try {
         $check = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'stock_deducted'")->fetch();
         if (!$check) {
             $pdo->exec("ALTER TABLE `orders` ADD COLUMN `stock_deducted` TINYINT(1) DEFAULT 0");
             $pdo->exec("ALTER TABLE `orders` ADD COLUMN `requested_discount_type` ENUM('none', 'percent', 'amount') DEFAULT 'none'");
             $pdo->exec("ALTER TABLE `orders` ADD COLUMN `requested_discount_value` DECIMAL(10, 2) DEFAULT 0.00");
             $pdo->exec("ALTER TABLE `orders` ADD COLUMN `admin_adjusted_discount` DECIMAL(10, 2) DEFAULT 0.00");
             
             $pdo->exec("ALTER TABLE `locations` ADD COLUMN `min_order_amount` DECIMAL(10, 2) DEFAULT 0.00");
             $pdo->exec("ALTER TABLE `locations` ADD COLUMN `max_order_amount` DECIMAL(10, 2) DEFAULT 999999.99");
             $pdo->exec("ALTER TABLE `locations` ADD COLUMN `shipping_type` ENUM('default', 'free', 'manual') DEFAULT 'default'");
             
             $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `requested_discount_type` ENUM('none', 'percent', 'amount') DEFAULT 'none'");
             $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `requested_discount_value` DECIMAL(10, 2) DEFAULT 0.00");
             $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `admin_adjusted_discount` DECIMAL(10, 2) DEFAULT 0.00");
             
             $pdo->exec("CREATE TABLE IF NOT EXISTS `email_logs` (
               `id` INT AUTO_INCREMENT PRIMARY KEY,
               `to_email` VARCHAR(255) NOT NULL,
               `subject` VARCHAR(255) NOT NULL,
               `body` LONGTEXT NOT NULL,
               `status` VARCHAR(50) NOT NULL,
               `error_message` TEXT DEFAULT NULL,
               `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
         }
     } catch (\PDOException $mig_e) {
         // Silently ignore to avoid blocking site usage
     }
} catch (\PDOException $e) {
     die("Database Connection Failed: " . $e->getMessage());
}

