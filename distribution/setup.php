<?php
/**
 * Ovijat Food Distribution — Database Setup and Administration Panel
 * Creates all tables, seeds default data, and allows safe resets, automatic backups, and SQL imports.
 */

require_once 'config/config.php';
$required_pin = "5877";

// ── 1. Secure Backup Download Handler ────────────────────────────────────────
if (isset($_GET['download_backup'])) {
    $file = basename($_GET['download_backup']);
    $filepath = __DIR__ . '/backups/' . $file;
    if (file_exists($filepath) && preg_match('/^db_backup_[0-9_]+\.sql$/', $file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}

// ── 2. PIN Verification Screen ───────────────────────────────────────────────
if (!isset($_POST['pin']) || $_POST['pin'] !== $required_pin) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Setup & Reset — Ovijat Food</title>
        <!-- Google Fonts (Outfit) -->
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            body {
                font-family: 'Outfit', sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background: radial-gradient(circle at top right, #0f172a, #020617);
                color: #f1f5f9;
                padding: 20px;
            }
            .box {
                background: rgba(30, 41, 59, 0.7);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.08);
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
                width: 100%;
                max-width: 460px;
                text-align: center;
                box-sizing: border-box;
            }
            h3 {
                color: #f8fafc;
                margin: 0 0 10px 0;
                font-size: 1.6rem;
                font-weight: 600;
                letter-spacing: -0.025em;
            }
            p.subtitle {
                color: #94a3b8;
                font-size: .9rem;
                margin: 0 0 24px 0;
                line-height: 1.5;
            }
            input.pin-input {
                padding: 14px 16px;
                margin-bottom: 24px;
                width: 100%;
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 10px;
                box-sizing: border-box;
                font-size: 16px;
                background: rgba(15, 23, 42, 0.6);
                color: #f8fafc;
                transition: all 0.3s;
                text-align: center;
                letter-spacing: 0.2em;
            }
            input.pin-input:focus {
                outline: none;
                border-color: #6366f1;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            }
            .section-divider {
                height: 1px;
                background: rgba(255, 255, 255, 0.08);
                margin: 24px 0;
            }
            .section-title {
                text-align: left;
                font-size: 0.85rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #6366f1;
                margin-bottom: 12px;
            }
            .btn-group {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .btn {
                padding: 14px;
                width: 100%;
                cursor: pointer;
                color: white;
                border: none;
                border-radius: 10px;
                font-weight: 600;
                font-size: 15px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .btn-setup {
                background: linear-gradient(135deg, #10b981, #059669);
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
            }
            .btn-setup:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
            }
            .btn-reset {
                background: linear-gradient(135deg, #ef4444, #dc2626);
                box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
            }
            .btn-reset:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(239, 68, 68, 0.35);
            }
            .btn-import {
                background: linear-gradient(135deg, #6366f1, #4f46e5);
                box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
            }
            .btn-import:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(99, 102, 241, 0.35);
            }
            .btn:active {
                transform: translateY(1px);
            }
            .file-card {
                background: rgba(15, 23, 42, 0.4);
                border: 1px dashed rgba(255, 255, 255, 0.15);
                border-radius: 10px;
                padding: 16px;
                margin-bottom: 12px;
                text-align: left;
                box-sizing: border-box;
            }
            .file-card label {
                display: block;
                font-size: 0.8rem;
                color: #94a3b8;
                margin-bottom: 8px;
                font-weight: 500;
            }
            .file-card input[type="file"] {
                color: #cbd5e1;
                font-size: 0.85rem;
                width: 100%;
            }
            .err {
                color: #f87171;
                margin-bottom: 16px;
                font-size: .85rem;
                font-weight: 500;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }
        </style>
    </head>
    <body>
        <div class="box">
            <div style="font-size: 3rem; margin-bottom: 16px; background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><i class="fa-solid fa-screwdriver-wrench"></i></div>
            <h3>Database Setup</h3>
            <p class="subtitle">Ovijat Food Distribution — Administration Panel</p>
            
            <?php if (isset($_POST['pin'])): ?>
                <div class="err"><i class="fa-solid fa-circle-xmark"></i> Invalid PIN. Please try again.</div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="password" name="pin" class="pin-input" placeholder="ENTER SETUP PIN" required autofocus maxlength="4">
                
                <div class="section-title">Database Rebuild</div>
                <div class="btn-group">
                    <button type="submit" name="action" value="setup" class="btn btn-setup"><i class="fa-solid fa-shield-halved"></i> Run Safe Setup</button>
                    <button type="submit" name="action" value="reset" class="btn btn-reset" onclick="return confirm('WARNING:\nThis will export and download a full SQL backup of the current database, then erase all tables and recreate the architecture.\n\nAre you sure you want to proceed?');"><i class="fa-solid fa-triangle-exclamation"></i> Reset & Rebuild Database</button>
                </div>

                <div class="section-divider"></div>

                <div class="section-title">Restore SQL Database</div>
                <div class="file-card">
                    <label for="sql_file">Choose SQL backup file (.sql)</label>
                    <input type="file" id="sql_file" name="sql_file" accept=".sql">
                </div>
                <button type="submit" name="action" value="import" class="btn btn-import" onclick="if (!document.getElementById('sql_file').value) { alert('Please select an SQL file to import first.'); return false; } return confirm('Are you sure you want to import this SQL file? It will delete all current database tables and restore the file backup.');"><i class="fa-solid fa-file-import"></i> Upload & Import SQL</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ── 3. Database Backup SQL Generator Function ───────────────────────────────
function generate_db_backup($conn) {
    if (!$conn->select_db(DB_NAME)) {
        return "-- Database " . DB_NAME . " does not exist or cannot be accessed.\n";
    }
    
    $sql = "-- Database Backup for: " . DB_NAME . "\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
    }
    
    if (empty($tables)) {
        $sql .= "-- No tables found in database.\n";
    } else {
        foreach ($tables as $table) {
            // Structure
            try {
                $res = $conn->query("SHOW CREATE TABLE `$table`");
                if ($res) {
                    $show_create = $res->fetch_assoc();
                    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                    $sql .= $show_create['Create Table'] . ";\n\n";
                }
            } catch (mysqli_sql_exception $e) {
                $sql .= "-- Error reading table structure for `$table`: " . $e->getMessage() . "\n\n";
            }
            
            // Rows
            try {
                $res = $conn->query("SELECT * FROM `$table`");
                if ($res && $res->num_rows > 0) {
                    $num_fields = $res->field_count;
                    while ($row = $res->fetch_row()) {
                        $sql .= "INSERT INTO `$table` VALUES(";
                        for ($j = 0; $j < $num_fields; $j++) {
                            if ($row[$j] === null) {
                                $sql .= "NULL";
                            } else {
                                $sql .= "'" . $conn->real_escape_string($row[$j]) . "'";
                            }
                            if ($j < ($num_fields - 1)) {
                                $sql .= ",";
                            }
                        }
                        $sql .= ");\n";
                    }
                    $sql .= "\n";
                }
            } catch (mysqli_sql_exception $e) {
                $sql .= "-- Error reading data for `$table`: " . $e->getMessage() . "\n\n";
            }
        }
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $sql;
}

// ── 4. SQL File Parser & Importer Function ──────────────────────────────────
function import_sql_file($conn, $filepath) {
    if (!file_exists($filepath)) {
        return ["status" => "error", "msg" => "Uploaded SQL file could not be read."];
    }
    
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    $handle = fopen($filepath, "r");
    if (!$handle) {
        return ["status" => "error", "msg" => "Failed to open uploaded SQL file."];
    }
    
    $query = '';
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    while (($line = fgets($handle)) !== false) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0 || strpos($trimmed, '/*') === 0) {
            continue;
        }
        
        $query .= $line;
        
        if (substr(rtrim($trimmed), -1) === ';') {
            try {
                if ($conn->query($query) !== FALSE) {
                    $success_count++;
                } else {
                    $error_count++;
                    if (count($errors) < 5) {
                        $errors[] = $conn->error;
                    }
                }
            } catch (mysqli_sql_exception $e) {
                $error_count++;
                if (count($errors) < 5) {
                    $errors[] = $e->getMessage();
                }
            }
            $query = '';
        }
    }
    
    fclose($handle);
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    if ($error_count > 0) {
        return [
            "status" => "error",
            "msg" => "SQL file imported with errors. Success queries: $success_count, failed: $error_count. Error logs: " . implode(" | ", $errors)
        ];
    }
    
    return [
        "status" => "success",
        "msg" => "SQL database restore complete! Executed $success_count queries successfully."
    ];
}

// ── 5. Process Actions ────────────────────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create DB if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
$conn->select_db(DB_NAME);

$action = $_POST['action'] ?? 'setup';
$backup_filename = null;
$results = [];

// Handle custom SQL Import Action
if ($action === 'import') {
    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        $tmp_path = $_FILES['sql_file']['tmp_name'];
        
        // ── Drop all existing tables before import to avoid naming and PK conflicts ──
        $tables_to_drop = [];
        $res = $conn->query("SHOW TABLES");
        if ($res) {
            while ($row = $res->fetch_row()) {
                $tables_to_drop[] = $row[0];
            }
        }
        
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tables_to_drop as $t) {
            try {
                $conn->query("DROP TABLE IF EXISTS `$t`");
            } catch (mysqli_sql_exception $e) {
                // Ignore drop errors
            }
        }
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        $results[] = ["name" => "Database Clear", "status" => "success", "msg" => "Dropped " . count($tables_to_drop) . " tables to prepare for import."];
        
        // Import the SQL file
        $import_res = import_sql_file($conn, $tmp_path);
        $results[] = ["name" => "SQL Import", "status" => $import_res['status'], "msg" => $import_res['msg']];
    } else {
        $results[] = ["name" => "SQL Import", "status" => "error", "msg" => "Error uploading SQL file. Check file size limits."];
    }
} else {
    // Unified schemas array for all tables (Rebuild & Safe Setup)
    $tables_list = [
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "company_settings" => "CREATE TABLE IF NOT EXISTS company_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            logo_url VARCHAR(255) NULL,
            phone VARCHAR(20) NULL,
            email VARCHAR(100) NULL,
            address TEXT NULL,
            isDelete TINYINT(1) DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "categories" => "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            isDelete TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

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
            market_type ENUM('Local','Export','Custom') DEFAULT 'Local',
            sku VARCHAR(50) NULL,
            barcode VARCHAR(100) NULL,
            unit VARCHAR(50) DEFAULT 'pcs',
            low_stock_threshold INT DEFAULT 10,
            FOREIGN KEY (category_id) REFERENCES categories(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "customers" => "CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            address TEXT NULL,
            type ENUM('TP', 'DP', 'Retail') NOT NULL,
            opening_balance DECIMAL(15,2) DEFAULT 0.00,
            balance DECIMAL(15,2) DEFAULT 0.00,
            credit_limit DECIMAL(15,2) DEFAULT 0.00,
            is_active TINYINT(1) DEFAULT 1,
            isDelete TINYINT(1) DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id),
            INDEX (isDelete),
            INDEX (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "sales_drafts" => "CREATE TABLE IF NOT EXISTS sales_drafts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT,
            created_by INT,
            total_amount DECIMAL(15,2) DEFAULT 0.00,
            discount DECIMAL(15,2) DEFAULT 0.00,
            vat DECIMAL(15,2) DEFAULT 0.00,
            grand_total DECIMAL(15,2) DEFAULT 0.00,
            general_note TEXT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "sales_items" => "CREATE TABLE IF NOT EXISTS sales_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            draft_id INT,
            product_id INT,
            note TEXT NULL,
            rate DECIMAL(10,2) NULL,
            billed_qty INT NULL,
            free_qty INT DEFAULT 0,
            total DECIMAL(15,2) NULL,
            isDelete TINYINT(1) DEFAULT 0,
            FOREIGN KEY (draft_id) REFERENCES sales_drafts(id),
            FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "transactions" => "CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT,
            type ENUM('Credit', 'Debit') NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            description TEXT NULL,
            hide_from_print TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            isDelete TINYINT(1) DEFAULT 0,
            FOREIGN KEY (customer_id) REFERENCES customers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "activity_logs" => "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action TEXT NOT NULL,
            isDelete TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "stock_entries" => "CREATE TABLE IF NOT EXISTS stock_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT,
            user_id INT NULL,
            quantity INT NOT NULL,
            isDelete TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            batch_no VARCHAR(50) NULL,
            expiry_date DATE NULL,
            purchase_id INT NULL,
            notes TEXT NULL,
            FOREIGN KEY (product_id) REFERENCES products(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "truck_loads" => "CREATE TABLE IF NOT EXISTS truck_loads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            truck_no VARCHAR(50) NOT NULL,
            driver_name VARCHAR(100) NULL,
            source_location VARCHAR(255) NULL,
            destination_location VARCHAR(255) NULL,
            remarks TEXT NULL,
            status ENUM('Draft', 'Loaded', 'Departed', 'Completed') DEFAULT 'Draft',
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            isDelete TINYINT(1) DEFAULT 0,
            driver_phone VARCHAR(20) NULL,
            expected_delivery DATE NULL,
            FOREIGN KEY (created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "truck_load_items" => "CREATE TABLE IF NOT EXISTS truck_load_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            truck_load_id INT,
            invoice_id INT,
            isDelete TINYINT(1) DEFAULT 0,
            FOREIGN KEY (truck_load_id) REFERENCES truck_loads(id),
            FOREIGN KEY (invoice_id) REFERENCES sales_drafts(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "stock_damages" => "CREATE TABLE IF NOT EXISTS stock_damages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT,
            user_id INT NULL,
            quantity INT NOT NULL,
            reason TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            isDelete TINYINT(1) DEFAULT 0,
            FOREIGN KEY (product_id) REFERENCES products(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "suppliers" => "CREATE TABLE IF NOT EXISTS `suppliers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `address` TEXT DEFAULT NULL,
            `balance` DECIMAL(15,2) DEFAULT 0.00,
            `opening_balance` DECIMAL(15,2) DEFAULT 0.00,
            `is_active` TINYINT(1) DEFAULT 1,
            `isDelete` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "purchase_orders" => "CREATE TABLE IF NOT EXISTS `purchase_orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `supplier_id` INT DEFAULT NULL,
            `invoice_no` VARCHAR(50) DEFAULT NULL,
            `total_amount` DECIMAL(15,2) DEFAULT 0.00,
            `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
            `status` ENUM('Draft','Received','Partial','Paid') DEFAULT 'Draft',
            `notes` TEXT DEFAULT NULL,
            `received_by` INT DEFAULT NULL,
            `received_at` DATETIME DEFAULT NULL,
            `isDelete` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `supplier_id` (`supplier_id`),
            KEY `received_by` (`received_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "purchase_items" => "CREATE TABLE IF NOT EXISTS `purchase_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `purchase_id` INT DEFAULT NULL,
            `product_id` INT DEFAULT NULL,
            `batch_no` VARCHAR(50) DEFAULT NULL,
            `expiry_date` DATE DEFAULT NULL,
            `quantity` INT NOT NULL,
            `unit_cost` DECIMAL(10,2) NOT NULL,
            `total` DECIMAL(15,2) DEFAULT NULL,
            `isDelete` TINYINT(1) DEFAULT 0,
            KEY `purchase_id` (`purchase_id`),
            KEY `product_id` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "product_batches" => "CREATE TABLE IF NOT EXISTS `product_batches` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT DEFAULT NULL,
            `batch_no` VARCHAR(50) NOT NULL,
            `expiry_date` DATE DEFAULT NULL,
            `quantity_in` INT DEFAULT 0,
            `quantity_remaining` INT DEFAULT 0,
            `purchase_id` INT DEFAULT NULL,
            `source` ENUM('Purchase','Manual') DEFAULT 'Manual',
            `isDelete` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `product_id` (`product_id`),
            KEY `expiry_date` (`expiry_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "supplier_transactions" => "CREATE TABLE IF NOT EXISTS `supplier_transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `supplier_id` INT DEFAULT NULL,
            `purchase_id` INT DEFAULT NULL,
            `type` ENUM('Payable','Payment') NOT NULL,
            `amount` DECIMAL(15,2) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `isDelete` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `supplier_id` (`supplier_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "sales_returns" => "CREATE TABLE IF NOT EXISTS `sales_returns` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sale_id` INT DEFAULT NULL,
            `customer_id` INT DEFAULT NULL,
            `reason` TEXT DEFAULT NULL,
            `total_amount` DECIMAL(15,2) DEFAULT 0.00,
            `status` ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
            `restock` TINYINT(1) DEFAULT 1,
            `processed_by` INT DEFAULT NULL,
            `processed_at` DATETIME DEFAULT NULL,
            `isDelete` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `sale_id` (`sale_id`),
            KEY `customer_id` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "sales_return_items" => "CREATE TABLE IF NOT EXISTS `sales_return_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `return_id` INT DEFAULT NULL,
            `product_id` INT DEFAULT NULL,
            `quantity` INT NOT NULL,
            `unit_rate` DECIMAL(10,2) DEFAULT NULL,
            `total` DECIMAL(15,2) DEFAULT NULL,
            `isDelete` TINYINT(1) DEFAULT 0,
            KEY `return_id` (`return_id`),
            KEY `product_id` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "expenses" => "CREATE TABLE IF NOT EXISTS `expenses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `category` VARCHAR(100) DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `amount` DECIMAL(15,2) NOT NULL,
            `expense_date` DATE NOT NULL,
            `account_id` INT DEFAULT NULL,
            `recorded_by` INT DEFAULT NULL,
            `isDelete` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `expense_date` (`expense_date`),
            KEY `recorded_by` (`recorded_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "user_view_permissions" => "CREATE TABLE IF NOT EXISTS `user_view_permissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `show_local` TINYINT(1) DEFAULT 1,
            `show_export` TINYINT(1) DEFAULT 1,
            `show_custom` TINYINT(1) DEFAULT 1,
            `show_sales_kpis` TINYINT(1) DEFAULT 1,
            `show_inventory_section` TINYINT(1) DEFAULT 1,
            `show_delivery_section` TINYINT(1) DEFAULT 1,
            `show_accounts_section` TINYINT(1) DEFAULT 0,
            `can_see_stock_report` TINYINT(1) DEFAULT 1,
            `can_see_inventory_report` TINYINT(1) DEFAULT 1,
            `can_see_comprehensive_report` TINYINT(1) DEFAULT 1,
            `can_see_transactions` TINYINT(1) DEFAULT 0,
            `can_see_dmd_dashboard` TINYINT(1) DEFAULT 0,
            `show_rates` TINYINT(1) DEFAULT 1,
            `show_customer_balances` TINYINT(1) DEFAULT 0,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "account_groups" => "CREATE TABLE IF NOT EXISTS `account_groups` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `parent_id` INT DEFAULT NULL,
            `nature` ENUM('Assets','Liabilities','Income','Expense','Equity') NOT NULL,
            `is_system` TINYINT(1) DEFAULT 0,
            `isDelete` TINYINT(1) DEFAULT 0,
            KEY `parent_id` (`parent_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "accounts" => "CREATE TABLE IF NOT EXISTS `accounts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `group_id` INT NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `code` VARCHAR(20) DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `opening_balance` DECIMAL(15,2) DEFAULT 0.00,
            `opening_balance_type` ENUM('Dr','Cr') DEFAULT 'Dr',
            `is_system` TINYINT(1) DEFAULT 0,
            `entity_type` ENUM('Customer','Supplier','General') DEFAULT 'General',
            `entity_id` INT DEFAULT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `isDelete` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `group_id` (`group_id`),
            KEY `entity_type` (`entity_type`),
            KEY `entity_id` (`entity_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "journal_entries" => "CREATE TABLE IF NOT EXISTS `journal_entries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `entry_no` VARCHAR(30) NOT NULL,
            `date` DATE NOT NULL,
            `narration` TEXT DEFAULT NULL,
            `reference_type` ENUM('Invoice','Payment','Purchase','Expense','Return','Adjustment','Opening') DEFAULT 'Adjustment',
            `reference_id` INT DEFAULT NULL,
            `created_by` INT NOT NULL,
            `is_posted` TINYINT(1) DEFAULT 1,
            `is_verified` TINYINT(1) DEFAULT 0,
            `verified_by` INT DEFAULT NULL,
            `verified_at` DATETIME DEFAULT NULL,
            `isDelete` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `entry_no` (`entry_no`),
            KEY `date` (`date`),
            KEY `created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "journal_lines" => "CREATE TABLE IF NOT EXISTS `journal_lines` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `journal_id` INT NOT NULL,
            `account_id` INT NOT NULL,
            `dr_amount` DECIMAL(15,2) DEFAULT 0.00,
            `cr_amount` DECIMAL(15,2) DEFAULT 0.00,
            `narration` TEXT DEFAULT NULL,
            `isDelete` TINYINT(1) DEFAULT 0,
            KEY `journal_id` (`journal_id`),
            KEY `account_id` (`account_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    if ($action === 'reset') {
        // Run Database Backup Before Erasing
        $backup_sql = generate_db_backup($conn);
        
        $backup_dir = __DIR__ . '/backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $backup_filename = 'db_backup_' . date('Ymd_His') . '.sql';
        file_put_contents($backup_dir . '/' . $backup_filename, $backup_sql);
        $results[] = ["name" => "Database Backup", "status" => "success", "msg" => "Created backup archive: $backup_filename"];

        // Drop All Current Tables
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        foreach (array_keys($tables_list) as $t) {
            try {
                $conn->query("DROP TABLE IF EXISTS `$t`");
            } catch (mysqli_sql_exception $e) {
                // Ignore drop errors
            }
        }
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $results[] = ["name" => "Database Erase", "status" => "success", "msg" => "Dropped all existing database tables."];
    }

    // Rebuild tables
    foreach ($tables_list as $name => $sql) {
        try {
            if ($conn->query($sql) === TRUE) {
                $results[] = ["name" => $name, "status" => "success", "msg" => "Table '$name' created or exists."];
            } else {
                $results[] = ["name" => $name, "status" => "error", "msg" => "Error creating '$name': " . $conn->error];
            }
        } catch (mysqli_sql_exception $e) {
            $results[] = ["name" => $name, "status" => "error", "msg" => "Error creating '$name': " . $e->getMessage()];
        }
    }

    // Create Default Admin
    $admin_user = 'admin';
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $admin_phone = '0000000000';

    try {
        $check_admin = $conn->query("SELECT id FROM users WHERE username = '$admin_user'");
        if ($check_admin && $check_admin->num_rows == 0) {
            if ($conn->query("INSERT INTO users (username, password, phone, role) VALUES ('$admin_user', '$admin_pass', '$admin_phone', 'Admin')")) {
                $admin_id = $conn->insert_id;
                $results[] = ["name" => "Admin User", "status" => "success", "msg" => "Default admin created (admin/admin123)"];

                // Configure default permissions for created admin
                $conn->query("INSERT IGNORE INTO `user_view_permissions`
                    (user_id, show_local, show_export, show_custom, show_sales_kpis, show_inventory_section,
                     show_delivery_section, show_accounts_section, can_see_stock_report, can_see_inventory_report,
                     can_see_comprehensive_report, can_see_transactions, can_see_dmd_dashboard, show_rates, show_customer_balances)
                    VALUES ($admin_id, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1)");
                $results[] = ["name" => "Admin Permissions", "status" => "success", "msg" => "Admin view permissions configured."];
            }
        }
    } catch (mysqli_sql_exception $e) {
        $results[] = ["name" => "Admin Creation", "status" => "error", "msg" => "Error setting up default admin: " . $e->getMessage()];
    }

    // Create Initial Company Settings
    try {
        $check_settings = $conn->query("SELECT id FROM company_settings LIMIT 1");
        if ($check_settings && $check_settings->num_rows == 0) {
            $conn->query("INSERT INTO company_settings (name) VALUES ('Food Distribution Co.')");
            $results[] = ["name" => "Company Settings", "status" => "success", "msg" => "Initial settings created."];
        }
    } catch (mysqli_sql_exception $e) {
        $results[] = ["name" => "Company Settings", "status" => "error", "msg" => "Error setting up company: " . $e->getMessage()];
    }

    // Seed Account Groups
    try {
        $check_groups = $conn->query("SELECT id FROM account_groups LIMIT 1");
        if ($check_groups && $check_groups->num_rows == 0) {
            $group_seeds = [
                "(1, 'Assets', NULL, 'Assets', 1)",
                "(2, 'Liabilities', NULL, 'Liabilities', 1)",
                "(3, 'Income', NULL, 'Income', 1)",
                "(4, 'Expense', NULL, 'Expense', 1)",
                "(5, 'Equity', NULL, 'Equity', 1)",
                "(6, 'Cash & Bank', 1, 'Assets', 1)",
                "(7, 'Accounts Receivable', 1, 'Assets', 1)",
                "(8, 'Inventory', 1, 'Assets', 1)",
                "(9, 'Fixed Assets', 1, 'Assets', 1)",
                "(10, 'Accounts Payable', 2, 'Liabilities', 1)",
                "(11, 'Loans & Borrowings', 2, 'Liabilities', 1)",
                "(12, 'Sales Revenue', 3, 'Income', 1)",
                "(13, 'Other Income', 3, 'Income', 1)",
                "(14, 'Cost of Goods Sold', 4, 'Expense', 1)",
                "(15, 'Operating Expenses', 4, 'Expense', 1)",
                "(16, 'Salaries & Wages', 15, 'Expense', 1)",
                "(17, 'Transport & Logistics', 15, 'Expense', 1)",
                "(18, 'Office Expenses', 15, 'Expense', 1)",
                "(19, 'Owner Capital', 5, 'Equity', 1)",
                "(20, 'Retained Earnings', 5, 'Equity', 1)",
            ];
            $conn->query("INSERT IGNORE INTO `account_groups` (`id`, `name`, `parent_id`, `nature`, `is_system`) VALUES " . implode(',', $group_seeds));
            $results[] = ["name" => "Account Groups", "status" => "success", "msg" => "Account groups seeded."];
        }
    } catch (mysqli_sql_exception $e) {
        $results[] = ["name" => "Account Groups", "status" => "error", "msg" => "Error seeding account groups: " . $e->getMessage()];
    }

    // Seed Default System Accounts
    try {
        $check_accounts = $conn->query("SELECT id FROM accounts LIMIT 1");
        if ($check_accounts && $check_accounts->num_rows == 0) {
            $account_seeds = [
                "(1, 6, 'Cash in Hand', 'CASH', NULL, 0.00, 'Dr', 1, 'General', NULL)",
                "(2, 6, 'Bank Account', 'BANK', NULL, 0.00, 'Dr', 1, 'General', NULL)",
                "(3, 8, 'Stock Inventory', 'STK', NULL, 0.00, 'Dr', 1, 'General', NULL)",
                "(4, 12, 'Sales Revenue', 'SALES', NULL, 0.00, 'Cr', 1, 'General', NULL)",
                "(5, 14, 'Cost of Goods Sold', 'COGS', NULL, 0.00, 'Dr', 1, 'General', NULL)",
                "(6, 15, 'Miscellaneous Expense', 'MISC', NULL, 0.00, 'Dr', 1, 'General', NULL)",
                "(7, 16, 'Salaries', 'SAL', NULL, 0.00, 'Dr', 1, 'General', NULL)",
                "(8, 17, 'Transport', 'TRN', NULL, 0.00, 'Dr', 1, 'General', NULL)",
                "(9, 18, 'Office Expense', 'OFF', NULL, 0.00, 'Dr', 1, 'General', NULL)",
            ];
            $conn->query("INSERT IGNORE INTO `accounts` (`id`, `group_id`, `name`, `code`, `description`, `opening_balance`, `opening_balance_type`, `is_system`, `entity_type`, `entity_id`) VALUES " . implode(',', $account_seeds));
            $results[] = ["name" => "System Accounts", "status" => "success", "msg" => "Default system accounts seeded."];
        }
    } catch (mysqli_sql_exception $e) {
        $results[] = ["name" => "System Accounts", "status" => "error", "msg" => "Error seeding system accounts: " . $e->getMessage()];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Results — Ovijat Food</title>
    <!-- Google Fonts (Outfit) -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #090d16;
            color: #f1f5f9;
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }
        h2 {
            font-size: 1.8rem;
            color: #fff;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        p.desc {
            color: #94a3b8;
            font-size: 0.95rem;
            margin: 0 0 30px 0;
        }
        .backup-alert {
            background: rgba(56, 189, 248, 0.1);
            border: 1px solid rgba(56, 189, 248, 0.2);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .backup-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .backup-info i {
            font-size: 2.2rem;
            color: #38bdf8;
        }
        .backup-text h4 {
            margin: 0 0 4px 0;
            color: #f8fafc;
            font-size: 1rem;
        }
        .backup-text p {
            margin: 0;
            color: #94a3b8;
            font-size: 0.85rem;
        }
        .log-box {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.85rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .log-item {
            margin-bottom: 6px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .log-item.success { color: #4ade80; }
        .log-item.error { color: #f87171; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        .btn-download {
            background: #0284c7;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);
        }
        .btn-download:hover {
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.45);
            transform: translateY(-1px);
        }
        .btn-login {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
        }
        .btn-login:hover {
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.45);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fa-solid fa-circle-check" style="color: #4ade80;"></i> Setup Command Completed</h2>
        <p class="desc">Execution log of database configuration actions.</p>
        
        <?php if ($backup_filename): ?>
            <div class="backup-alert">
                <div class="backup-info">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <div class="backup-text">
                        <h4>Database Backup Exported</h4>
                        <p>A safety copy was saved as <code><?php echo htmlspecialchars($backup_filename); ?></code>.</p>
                    </div>
                </div>
                <a href="setup.php?download_backup=<?php echo urlencode($backup_filename); ?>" class="btn btn-download"><i class="fa-solid fa-download"></i> Save SQL File</a>
            </div>
        <?php endif; ?>

        <div class="log-box">
            <?php foreach ($results as $res): ?>
                <div class="log-item <?php echo $res['status']; ?>">
                    <span>[<?php echo strtoupper($res['status']); ?>]</span>
                    <span><?php echo htmlspecialchars($res['msg']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <a href="login.php" class="btn btn-login"><i class="fa-solid fa-right-to-bracket"></i> Proceed to Login</a>
    </div>

    <?php if ($backup_filename): ?>
    <script>
        // Automatically trigger file download inside browser
        setTimeout(function() {
            window.location.href = "setup.php?download_backup=<?php echo urlencode($backup_filename); ?>";
        }, 800);
    </script>
    <?php endif; ?>
</body>
</html>
