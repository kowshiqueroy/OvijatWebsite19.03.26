<?php
require_once __DIR__ . '/../config/database.php';

function columnExists($pdo, $table, $column) {
    try {
        $rs = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $rs->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

try {
    $pdo->beginTransaction();

    // 1. Alter orders table
    if (!columnExists($pdo, 'orders', 'stock_deducted')) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `stock_deducted` TINYINT(1) DEFAULT 0");
        echo "Added 'stock_deducted' to 'orders'.\n";
    }
    if (!columnExists($pdo, 'orders', 'requested_discount_type')) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `requested_discount_type` ENUM('none', 'percent', 'amount') DEFAULT 'none'");
        echo "Added 'requested_discount_type' to 'orders'.\n";
    }
    if (!columnExists($pdo, 'orders', 'requested_discount_value')) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `requested_discount_value` DECIMAL(10, 2) DEFAULT 0.00");
        echo "Added 'requested_discount_value' to 'orders'.\n";
    }
    if (!columnExists($pdo, 'orders', 'admin_adjusted_discount')) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `admin_adjusted_discount` DECIMAL(10, 2) DEFAULT 0.00");
        echo "Added 'admin_adjusted_discount' to 'orders'.\n";
    }

    // 2. Alter locations table
    if (!columnExists($pdo, 'locations', 'min_order_amount')) {
        $pdo->exec("ALTER TABLE `locations` ADD COLUMN `min_order_amount` DECIMAL(10, 2) DEFAULT 0.00");
        echo "Added 'min_order_amount' to 'locations'.\n";
    }
    if (!columnExists($pdo, 'locations', 'max_order_amount')) {
        $pdo->exec("ALTER TABLE `locations` ADD COLUMN `max_order_amount` DECIMAL(10, 2) DEFAULT 999999.99");
        echo "Added 'max_order_amount' to 'locations'.\n";
    }
    if (!columnExists($pdo, 'locations', 'shipping_type')) {
        $pdo->exec("ALTER TABLE `locations` ADD COLUMN `shipping_type` ENUM('default', 'free', 'manual') DEFAULT 'default'");
        echo "Added 'shipping_type' to 'locations'.\n";
    }

    // 3. Alter order_items table
    if (!columnExists($pdo, 'order_items', 'requested_discount_type')) {
        $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `requested_discount_type` ENUM('none', 'percent', 'amount') DEFAULT 'none'");
        echo "Added 'requested_discount_type' to 'order_items'.\n";
    }
    if (!columnExists($pdo, 'order_items', 'requested_discount_value')) {
        $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `requested_discount_value` DECIMAL(10, 2) DEFAULT 0.00");
        echo "Added 'requested_discount_value' to 'order_items'.\n";
    }
    if (!columnExists($pdo, 'order_items', 'admin_adjusted_discount')) {
        $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `admin_adjusted_discount` DECIMAL(10, 2) DEFAULT 0.00");
        echo "Added 'admin_adjusted_discount' to 'order_items'.\n";
    }

    // 4. Create email_logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `email_logs` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `to_email` VARCHAR(255) NOT NULL,
      `subject` VARCHAR(255) NOT NULL,
      `body` LONGTEXT NOT NULL,
      `status` VARCHAR(50) NOT NULL,
      `error_message` TEXT DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'email_logs' table if it did not exist.\n";

    $pdo->commit();
    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
