<?php
// qc/setup.php
include_once 'config.php';

// Create users table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Insert default admin user if table is empty
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

// Create products table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS products (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    tp_rate DECIMAL(10,2) NOT NULL,
    dp_rate DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conn->query("CREATE TABLE IF NOT EXISTS damage_details (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_type ENUM('TP', 'DP') NOT NULL,
    received_date DATE NOT NULL,
    inspection_date DATE NOT NULL,
    trader_name VARCHAR(255) NOT NULL,
    shop_total_qty INT(11) NOT NULL,
    received_total_qty INT(11) NOT NULL,
    shop_total_amount DECIMAL(10,2) NOT NULL,
    received_total_amount DECIMAL(10,2) NOT NULL,
    actual_total_qty INT(11) NOT NULL,
    actual_total_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT(11) UNSIGNED,
    updated_by INT(11) UNSIGNED,

    status BOOLEAN DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conn->query("CREATE TABLE IF NOT EXISTS damage_items (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    damage_details_id INT(11) UNSIGNED NOT NULL,
    product_id INT(11) UNSIGNED NOT NULL,
    shop_qty INT(11) NOT NULL,
    shop_amount DECIMAL(10,2) NOT NULL,
    received_qty INT(11) NOT NULL,
    received_amount DECIMAL(10,2) NOT NULL,
    actual_qty INT(11) NOT NULL,
    actual_amount DECIMAL(10,2) NOT NULL,
    good INT(11) NOT NULL,
    label INT(11) NOT NULL,
    sealing INT(11) NOT NULL,
    expired INT(11) NOT NULL,
    date_problem INT(11) NOT NULL,
    broken INT(11) NOT NULL,
    VHsealing INT(11) NOT NULL,
    insect INT(11) NOT NULL,
    intentional INT(11) NOT NULL,
    soft INT(11) NOT NULL,
    bodyleakage INT(11) NOT NULL,
    others INT(11) NOT NULL,
    total_negative_qty INT(11) NOT NULL,
    total_negative_amount DECIMAL(10,2) NOT NULL,
    remarks TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
?>