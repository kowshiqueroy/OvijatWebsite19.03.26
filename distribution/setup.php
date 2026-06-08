<?php
$required_pin = "5877";

if (!isset($_POST['pin']) || $_POST['pin'] !== $required_pin) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Protection</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f0f2f5; }
            .login-box { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); width: 100%; max-width: 350px; text-align: center; }
            h3 { color: #1c1e21; margin-bottom: 20px; }
            input { padding: 12px; margin-bottom: 20px; width: 100%; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
            button { padding: 12px; width: 100%; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 6px; font-weight: 600; font-size: 16px; transition: background 0.2s; }
            button:hover { background: #0056b3; }
            .error { color: #dc3545; margin-bottom: 15px; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h3>Database Setup</h3>
            <?php if (isset($_POST['pin'])): ?>
                <div class="error">Invalid PIN. Please try again.</div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="pin" placeholder="Enter PIN" required autofocus>
                <button type="submit">Unlock & Run Setup</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once 'config/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create Database
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    // Database created successfully
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
        credit_limit DECIMAL(15,2) DEFAULT 0.00,
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
        general_note TEXT,
        status ENUM('Draft', 'Confirmed') DEFAULT 'Draft',
        delivery_status ENUM('Pending', 'Loading', 'In Transit', 'Delivered', 'Failed', 'Returned') DEFAULT 'Pending',
        delivery_date DATE NULL,
        hide_from_print TINYINT(1) DEFAULT 0,
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
        hide_from_print TINYINT(1) DEFAULT 0,
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
    )",
    "truck_loads" => "CREATE TABLE IF NOT EXISTS truck_loads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        truck_no VARCHAR(50) NOT NULL,
        driver_name VARCHAR(100),
        source_location VARCHAR(255),
        destination_location VARCHAR(255),
        remarks TEXT,
        status ENUM('Draft', 'Loaded', 'Departed', 'Completed') DEFAULT 'Draft',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        isDelete TINYINT(1) DEFAULT 0,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",
    "truck_load_items" => "CREATE TABLE IF NOT EXISTS truck_load_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        truck_load_id INT,
        invoice_id INT,
        isDelete TINYINT(1) DEFAULT 0,
        FOREIGN KEY (truck_load_id) REFERENCES truck_loads(id),
        FOREIGN KEY (invoice_id) REFERENCES sales_drafts(id)
    )",
    "stock_damages" => "CREATE TABLE IF NOT EXISTS stock_damages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT,
        user_id INT,
        quantity INT NOT NULL,
        reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        isDelete TINYINT(1) DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )"
];

$results = [];
foreach ($tables as $name => $sql) {
    if ($conn->query($sql) === TRUE) {
        $results[] = ["name" => $name, "status" => "success", "msg" => "Table '$name' created or exists."];
    } else {
        $results[] = ["name" => $name, "status" => "error", "msg" => "Error creating '$name': " . $conn->error];
    }
}

// Create Default Admin
$admin_user = 'admin';
$admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
$admin_phone = '0000000000';

$check_admin = $conn->query("SELECT id FROM users WHERE username = '$admin_user'");
if ($check_admin->num_rows == 0) {
    $conn->query("INSERT INTO users (username, password, phone, role) VALUES ('$admin_user', '$admin_pass', '$admin_phone', 'Admin')");
    $results[] = ["name" => "Admin User", "status" => "success", "msg" => "Default admin created (admin/admin123)"];
}

// Create Initial Company Settings
$check_settings = $conn->query("SELECT id FROM company_settings LIMIT 1");
if ($check_settings->num_rows == 0) {
    $conn->query("INSERT INTO company_settings (name) VALUES ('Food Distribution Co.')");
    $results[] = ["name" => "Company Settings", "status" => "success", "msg" => "Initial settings created."];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Results</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 40px; }
        .container { max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        h2 { color: #1c1e21; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .result-item { padding: 10px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; }
        .success { color: #28a745; font-weight: 600; }
        .error { color: #dc3545; font-weight: 600; }
        .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Setup Execution Results</h2>
        <?php foreach ($results as $res): ?>
            <div class="result-item">
                <span><?php echo $res['msg']; ?></span>
                <span class="<?php echo $res['status']; ?>"><?php echo strtoupper($res['status']); ?></span>
            </div>
        <?php endforeach; ?>
        <a href="login.php" class="btn">Go to Login</a>
    </div>
</body>
</html>
