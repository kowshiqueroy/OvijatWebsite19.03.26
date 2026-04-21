<?php
/**
 * setup.php
 * Database Initialization, Migration and Reset
 * Run from browser: /inv/setup.php
 */
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Security: Only Admin can run setup/reset after initial installation
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt->fetchColumn();
    if ($user_count > 0) {
        requireRole('Admin');
    }
} catch (PDOException $e) {
    // Database might not exist yet, allow setup
}

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db_name = DB_NAME;

$action = $_GET['action'] ?? 'install';

if ($action === 'reset') {
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
        $pdo->exec("DROP DATABASE IF EXISTS $db_name");
        $pdo->exec("CREATE DATABASE $db_name");
        echo "<p style='color:red'>Database RESET and re-creating...</p>";
        $action = 'install';
    } catch (Exception $e) {
        die("Reset failed: " . $e->getMessage());
    }
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

echo "<h2>Inventory System Setup</h2>";
echo "<p>Action: <b>$action</b></p>";

// 1. Branches
$pdo->exec("CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    contact VARCHAR(50),
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// 2. Users
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Manager', 'Accountant', 'Viewer') NOT NULL,
    branch_id INT,
    status ENUM('Active', 'Blocked') DEFAULT 'Active',
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB");

// 3. Categories
$pdo->exec("CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT,
    name VARCHAR(100) NOT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB");

// 4. Products
$pdo->exec("CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT,
    name VARCHAR(255) NOT NULL,
    category_id INT,
    unit_name VARCHAR(50) DEFAULT 'Box',
    conversion_ratio INT DEFAULT 1,
    min_sale_price DECIMAL(15, 2) DEFAULT 0.00,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB");

// 5. Product Prices
$pdo->exec("CREATE TABLE IF NOT EXISTS product_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    customer_type VARCHAR(20),
    pack_price DECIMAL(15, 2) DEFAULT 0.00,
    piece_price DECIMAL(15, 2) DEFAULT 0.00,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB");

// 6. Customers
$pdo->exec("CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('Retail', 'TP', 'DP') DEFAULT 'Retail',
    address TEXT,
    balance DECIMAL(15, 2) DEFAULT 0.00,
    branch_id INT,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB");

// 7. Inventory
$pdo->exec("CREATE TABLE IF NOT EXISTS inventory (
    product_id INT,
    branch_id INT,
    quantity_pcs INT DEFAULT 0,
    PRIMARY KEY (product_id, branch_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB");

// 8. Stock Ledger
$pdo->exec("CREATE TABLE IF NOT EXISTS stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    branch_id INT,
    type ENUM('stock_in', 'sale_out', 'manual_out', 'return_in', 'adjustment') NOT NULL,
    quantity_pcs INT NOT NULL,
    reference_id INT,
    person_name VARCHAR(255),
    reason TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB");

// 9. Sales
$pdo->exec("CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    user_id INT,
    branch_id INT,
    total_amount DECIMAL(15, 2) DEFAULT 0.00,
    discount_amount DECIMAL(15, 2) DEFAULT 0.00,
    status ENUM('draft', 'pending_approval', 'approved', 'rejected', 'completed') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT,
    approved_at DATETIME,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB");

// 10. Sale Items
$pdo->exec("CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT,
    product_id INT,
    unit_type VARCHAR(20),
    quantity INT,
    unit_price DECIMAL(15, 2),
    is_free TINYINT(1) DEFAULT 0,
    subtotal DECIMAL(15, 2),
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB");

// 11. Customer Ledger
$pdo->exec("CREATE TABLE IF NOT EXISTS customer_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    type VARCHAR(20),
    amount DECIMAL(15, 2),
    reference_id INT,
    description TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB");

// 12. Settings
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// 13. Audit Logs
$pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100),
    description TEXT,
    reference_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB");

// 14. Discounts
$pdo->exec("CREATE TABLE IF NOT EXISTS discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    product_id INT,
    discount_percent DECIMAL(5, 2) DEFAULT 0,
    valid_from DATE,
    valid_until DATE,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB");

echo "<p>Tables created successfully.</p>";

// Migration: Add branch_id where missing
try {
    $pdo->exec("ALTER TABLE categories ADD COLUMN branch_id INT DEFAULT 1");
    $pdo->exec("ALTER TABLE products ADD COLUMN branch_id INT DEFAULT 1");
    echo "<p>Migration: branch_id added to categories/products</p>";
} catch (Exception $e) {
    // Columns may already exist
}

// Seed Data
$branch_count = $pdo->query("SELECT COUNT(*) FROM branches")->fetchColumn();
if ($branch_count == 0) {
    $pdo->exec("INSERT INTO branches (id, name, location) VALUES (1, 'Main Branch', 'City Center')");
    echo "<p>Default branch created</p>";
}

$user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($user_count == 0) {
    $users = [
        ['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Admin', 1],
        ['manager', password_hash('manager123', PASSWORD_DEFAULT), 'Manager', 1],
        ['accountant', password_hash('acc123', PASSWORD_DEFAULT), 'Accountant', 1],
        ['viewer', password_hash('viewer123', PASSWORD_DEFAULT), 'Viewer', 1]
    ];
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, branch_id) VALUES (?, ?, ?, ?)");
    foreach ($users as $u) {
        $stmt->execute($u);
    }
    echo "<p>Default users created (admin/admin123)</p>";
}

$settings_count = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
if ($settings_count == 0) {
    $settings = [
        ['app_name', 'Pro Inventory System'],
        ['company_name', 'Dynamic Solutions Ltd.'],
        ['currency', 'BDT']
    ];
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $s) {
        $stmt->execute($s);
    }
    echo "<p>Default settings created</p>";
}

$category_count = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
if ($category_count == 0) {
    $pdo->exec("INSERT INTO categories (id, branch_id, name) VALUES (1, 1, 'Electronics'), (2, 1, 'Groceries')");
    echo "<p>Default categories created</p>";
}

$product_count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
if ($product_count == 0) {
    $pdo->exec("INSERT INTO products (id, branch_id, name, category_id, unit_name, conversion_ratio, min_sale_price) VALUES 
        (1, 1, 'Sample Product 1', 1, 'Box', 12, 100.00),
        (2, 1, 'Sample Product 2', 2, 'Carton', 6, 50.00)");
    $pdo->exec("INSERT INTO product_prices (product_id, customer_type, pack_price, piece_price) VALUES
        (1, 'Retail', 1200, 120),
        (1, 'TP', 1100, 100),
        (1, 'DP', 1000, 90),
        (2, 'Retail', 350, 70),
        (2, 'TP', 300, 60),
        (2, 'DP', 280, 50)");
    echo "<p>Default products created</p>";
}

echo "<p style='color:green'><b>Setup complete! Database ready.</b></p>";
echo "<hr>";
echo "<p><a href='?action=reset' class='btn btn-danger' onclick=\"return confirm('This will delete ALL data! Continue?')\">Reset Database</a></p>";
?>