<?php
include_once 'config.php';

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$res = $conn->query("SELECT COUNT(*) as count FROM users");
if ($res) {
    $row = $res->fetch_assoc();
    if ($row && isset($row['count']) && $row['count'] == 0) {
        $default_user = 'admin';
        $default_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("ss", $default_user, $default_pass);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS products (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    tp_rate DECIMAL(10,2) NOT NULL,
    dp_rate DECIMAL(10,2) NOT NULL,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS damage_details (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_type ENUM('TP', 'DP') NOT NULL,
    received_date DATE NOT NULL,
    inspection_date DATE NOT NULL,
    trader_name VARCHAR(255) NOT NULL,
    shop_total_qty INT(11) NOT NULL DEFAULT 0,
    received_total_qty INT(11) NOT NULL DEFAULT 0,
    shop_total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    received_total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_total_qty INT(11) NOT NULL DEFAULT 0,
    actual_total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT(11) UNSIGNED,
    updated_by INT(11) UNSIGNED,
    status TINYINT(1) DEFAULT 0,
    INDEX idx_trader_name (trader_name),
    INDEX idx_inspection_date (inspection_date),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_created_by (created_by),
    INDEX idx_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS damage_items (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    damage_details_id INT(11) UNSIGNED NOT NULL,
    product_id INT(11) UNSIGNED NOT NULL,
    shop_qty INT(11) NOT NULL DEFAULT 0,
    shop_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    received_qty INT(11) NOT NULL DEFAULT 0,
    received_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_qty INT(11) NOT NULL DEFAULT 0,
    actual_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    good INT(11) NOT NULL DEFAULT 0,
    label INT(11) NOT NULL DEFAULT 0,
    sealing INT(11) NOT NULL DEFAULT 0,
    expired INT(11) NOT NULL DEFAULT 0,
    date_problem INT(11) NOT NULL DEFAULT 0,
    broken INT(11) NOT NULL DEFAULT 0,
    VHsealing INT(11) NOT NULL DEFAULT 0,
    insect INT(11) NOT NULL DEFAULT 0,
    intentional INT(11) NOT NULL DEFAULT 0,
    soft INT(11) NOT NULL DEFAULT 0,
    bodyleakage INT(11) NOT NULL DEFAULT 0,
    others INT(11) NOT NULL DEFAULT 0,
    total_negative_qty INT(11) NOT NULL DEFAULT 0,
    total_negative_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    remarks TEXT,
    UNIQUE INDEX idx_unique_product_damage (damage_details_id, product_id),
    INDEX idx_damage_details_id (damage_details_id),
    INDEX idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT(11),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_table_name (table_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

echo "Database setup completed successfully!";
?>
