<?php
require_once 'config/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create Database
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

$conn->select_db(DB_NAME);

// Tables Creation
$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20) UNIQUE NOT NULL,
        role ENUM('Admin', 'Manager', 'Accountant', 'Sales Representative', 'Customer', 'Viewer') NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        last_active DATETIME NULL,
        force_password_change TINYINT(1) DEFAULT 0,
        isDelete TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "company_settings" => "CREATE TABLE IF NOT EXISTS company_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        logo_url VARCHAR(255),
        phone VARCHAR(20),
        email VARCHAR(100),
        address TEXT,
        isDelete TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "categories" => "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        isDelete TINYINT(1) DEFAULT 0
    )",
    "products" => "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT,
        name VARCHAR(100) NOT NULL,
        tp_rate DECIMAL(10,2) NOT NULL,
        dp_rate DECIMAL(10,2) NOT NULL,
        retail_rate DECIMAL(10,2) NOT NULL,
        stock_qty INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        isDelete TINYINT(1) DEFAULT 0,
        FOREIGN KEY (category_id) REFERENCES categories(id)
    )",
    "customers" => "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address TEXT,
        type ENUM('TP', 'DP', 'Retail') NOT NULL,
        opening_balance DECIMAL(15,2) DEFAULT 0.00,
        balance DECIMAL(15,2) DEFAULT 0.00,
        is_active TINYINT(1) DEFAULT 1,
        isDelete TINYINT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id),
        INDEX (isDelete),
        INDEX (phone)
    )",
    "sales_drafts" => "CREATE TABLE IF NOT EXISTS sales_drafts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        created_by INT,
        total_amount DECIMAL(15,2) DEFAULT 0.00,
        discount DECIMAL(15,2) DEFAULT 0.00,
        vat DECIMAL(15,2) DEFAULT 0.00,
        grand_total DECIMAL(15,2) DEFAULT 0.00,
        status ENUM('Draft', 'Confirmed') DEFAULT 'Draft',
        confirmed_by INT NULL,
        confirmed_at DATETIME NULL,
        isDelete TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id),
        FOREIGN KEY (created_by) REFERENCES users(id),
        FOREIGN KEY (confirmed_by) REFERENCES users(id)
    )",
    "sales_items" => "CREATE TABLE IF NOT EXISTS sales_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        draft_id INT,
        product_id INT,
        note TEXT,
        rate DECIMAL(10,2),
        billed_qty INT,
        free_qty INT DEFAULT 0,
        total DECIMAL(15,2),
        isDelete TINYINT(1) DEFAULT 0,
        FOREIGN KEY (draft_id) REFERENCES sales_drafts(id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    )",
    "transactions" => "CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        type ENUM('Credit', 'Debit') NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        isDelete TINYINT(1) DEFAULT 0,
        FOREIGN KEY (customer_id) REFERENCES customers(id)
    )",
    "activity_logs" => "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action TEXT NOT NULL,
        isDelete TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
    "stock_entries" => "CREATE TABLE IF NOT EXISTS stock_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT,
        user_id INT,
        quantity INT NOT NULL,
        isDelete TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )"
];

foreach ($tables as $name => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table '$name' created successfully.<br>";
    } else {
        echo "Error creating table '$name': " . $conn->error . "<br>";
    }
}

// Fix for existing tables missing isDelete
$conn->query("ALTER TABLE company_settings ADD COLUMN IF NOT EXISTS isDelete TINYINT(1) DEFAULT 0 AFTER address");
$conn->query("ALTER TABLE activity_logs ADD COLUMN IF NOT EXISTS isDelete TINYINT(1) DEFAULT 0 AFTER action");
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('Admin', 'Manager', 'Accountant', 'Sales Representative', 'Customer', 'Viewer') NOT NULL");
$conn->query("ALTER TABLE customers ADD COLUMN IF NOT EXISTS opening_balance DECIMAL(15,2) DEFAULT 0.00 AFTER type");
$conn->query("ALTER TABLE customers ADD INDEX IF NOT EXISTS (isDelete)");
$conn->query("ALTER TABLE products ADD INDEX IF NOT EXISTS (isDelete)");
$conn->query("ALTER TABLE sales_drafts ADD INDEX IF NOT EXISTS (isDelete)");
$conn->query("ALTER TABLE transactions ADD INDEX IF NOT EXISTS (isDelete)");

// Create Default Admin
$admin_user = 'admin';
$admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
$admin_phone = '0000000000';

$check_admin = $conn->query("SELECT id FROM users WHERE username = '$admin_user'");
if ($check_admin->num_rows == 0) {
    $conn->query("INSERT INTO users (username, password, phone, role) VALUES ('$admin_user', '$admin_pass', '$admin_phone', 'Admin')");
    echo "Default Admin user created (admin / admin123).<br>";
}

// Create Initial Company Settings
$check_settings = $conn->query("SELECT id FROM company_settings LIMIT 1");
if ($check_settings->num_rows == 0) {
    $conn->query("INSERT INTO company_settings (name) VALUES ('Food Distribution Co.')");
    echo "Initial Company Settings created.<br>";
}

echo "Setup Complete.";
?>
