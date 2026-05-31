<?php
require_once 'config.php';

// Define the secure PIN
$setup_pin = "5877";

$msg = "";
$authenticated = false;

// Check if PIN is submitted or already in session
if (isset($_POST['pin']) && $_POST['pin'] === $setup_pin) {
    $_SESSION['setup_authenticated'] = true;
    $authenticated = true;
} elseif (isset($_SESSION['setup_authenticated']) && $_SESSION['setup_authenticated'] === true) {
    $authenticated = true;
}

// If not authenticated, show PIN form
if (!$authenticated) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup - Security Required</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .pin-card { max-width: 400px; width: 100%; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); background: white; }
    </style>
</head>
<body>
    <div class="pin-card text-center">
        <h3 class="mb-4">🔐 Setup Security</h3>
        <p class="text-muted mb-4">Please enter the 4-digit PIN to access system setup.</p>
        <form method="POST">
            <input type="password" name="pin" maxlength="4" class="form-control form-control-lg text-center mb-3" placeholder="Enter PIN" required autofocus>
            <button type="submit" class="btn btn-primary btn-lg w-100">Unlock</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'setup') {
        // 1. COMPANIES TABLE
        $conn->query("CREATE TABLE IF NOT EXISTS companies (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            address VARCHAR(255),
            phone VARCHAR(50),
            email VARCHAR(100),
            website VARCHAR(100),
            logo VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 2. USERS TABLE
        $conn->query("CREATE TABLE IF NOT EXISTS users (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role TINYINT(1) NOT NULL DEFAULT 0,
            company_id INT(11) UNSIGNED,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 3. ROUTES TABLE
        $conn->query("CREATE TABLE IF NOT EXISTS routes (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            route_name VARCHAR(255) NOT NULL,
            company_id INT(11) UNSIGNED NOT NULL,
            status TINYINT(1) DEFAULT 1,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 4. SHOPS TABLE
        $conn->query("CREATE TABLE IF NOT EXISTS shops (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shop_name VARCHAR(255) NOT NULL,
            route_id INT(11) UNSIGNED NOT NULL,
            company_id INT(11) UNSIGNED NOT NULL,
            user_id INT(11) UNSIGNED,
            balance DECIMAL(15,2) DEFAULT 0,
            status TINYINT(1) DEFAULT 1,
            FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 5. ITEMS TABLE
        $conn->query("CREATE TABLE IF NOT EXISTS items (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_name VARCHAR(255) NOT NULL,
            price DECIMAL(15,2) NOT NULL,
            stock INT(11) DEFAULT 0,
            company_id INT(11) UNSIGNED NOT NULL,
            status TINYINT(1) DEFAULT 1,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 6. ORDERS TABLE
        $conn->query("CREATE TABLE IF NOT EXISTS orders (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            route_id INT(11) UNSIGNED NOT NULL,
            shop_id INT(11) UNSIGNED NOT NULL,
            order_date DATE NOT NULL,
            delivery_date DATE NOT NULL,
            order_status TINYINT(1) DEFAULT 0,
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            remarks VARCHAR(255),
            company_id INT(11) UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT(11) UNSIGNED,
            updated_by INT(11) UNSIGNED,
            approved_at TIMESTAMP NULL,
            approved_by INT(11) UNSIGNED,
            status TINYINT(1) DEFAULT 1,
            FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
            FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 7. ORDER ITEMS TABLE
        $conn->query("CREATE TABLE IF NOT EXISTS order_items (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT(11) UNSIGNED NOT NULL,
            item_id INT(11) UNSIGNED NOT NULL,
            quantity INT(11) NOT NULL,
            price DECIMAL(15,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 8. CASH COLLECTIONS TABLE
        $conn->query("CREATE TABLE IF NOT EXISTS cash_collections (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            route_id INT(11) UNSIGNED NOT NULL,
            shop_id INT(11) UNSIGNED NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            collection_date TIMESTAMP ,
            collected_by INT(11) UNSIGNED NOT NULL,
            remarks VARCHAR(255),
            approved_at TIMESTAMP NULL,
            approved_by INT(11) UNSIGNED,
            company_id INT(11) UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status TINYINT(1) DEFAULT 1,
            FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 9. SURVEYS TABLE
        $conn->query("CREATE TABLE IF NOT EXISTS surveys (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            survey_name VARCHAR(255) NOT NULL,
            survey_type VARCHAR(255) NOT NULL,
            survey_address VARCHAR(255) NOT NULL,
            survey_phone VARCHAR(255) NOT NULL,
            route_id INT(11) UNSIGNED NOT NULL,
            company_id INT(11) UNSIGNED NOT NULL,
            user_id INT(11) UNSIGNED,
            balance DECIMAL(15,2) DEFAULT 0,
            status TINYINT(1) DEFAULT 1,
            FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 10. SERIALS TABLE
        $conn->query("CREATE TABLE IF NOT EXISTS serials (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            serial_name VARCHAR(255) NOT NULL,
            order_ids VARCHAR(255) NOT NULL,
            user_id INT(11) UNSIGNED NOT NULL,
            company_id INT(11) UNSIGNED NOT NULL,
            status TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT(11) UNSIGNED,
            printed_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 11. OFFLINE ORDERS TABLE
        $conn->query("CREATE TABLE IF NOT EXISTS offline_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_name VARCHAR(255),
            route_name VARCHAR(255),
            shop_details VARCHAR(255),
            order_date DATE,
            delivery_date DATE,
            note TEXT,
            items JSON,
            total DECIMAL(10,2),
            synced TINYINT DEFAULT 0,
            admin_approval_id INT DEFAULT NULL,
            admin_approval_timedate DATETIME DEFAULT NULL
        )");

        // 12. ORDER RETURNS TABLE (Merged from 1/return_orders.php)
        $conn->query("CREATE TABLE IF NOT EXISTS order_returns (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_ids VARCHAR(255) NOT NULL,
            route_id INT(11) UNSIGNED NOT NULL,
            shop_id INT(11) UNSIGNED NOT NULL,
            user_id INT(11) UNSIGNED NOT NULL,
            company_id INT(11) UNSIGNED NOT NULL,
            total_return_value DECIMAL(15,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT(11) UNSIGNED
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 13. ORDER RETURN ITEMS TABLE (Merged from 1/return_orders.php)
        $conn->query("CREATE TABLE IF NOT EXISTS order_return_items (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            return_id INT(11) UNSIGNED NOT NULL,
            order_id INT(11) UNSIGNED NOT NULL,
            item_id INT(11) UNSIGNED NOT NULL,
            return_qty INT(11) NOT NULL,
            price DECIMAL(15,2) NOT NULL,
            FOREIGN KEY (return_id) REFERENCES order_returns(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // --- Insert Default Company & Admin ---
        $res = $conn->query("SELECT COUNT(*) AS count FROM companies");
        if ($res && ($row = $res->fetch_assoc()) && $row['count'] == 0) {
            $conn->query("INSERT INTO companies (name) VALUES ('Default Company')");
        }
        $companyId = $conn->insert_id ?: 1;

        $res = $conn->query("SELECT COUNT(*) AS count FROM users");
        if ($res && ($row = $res->fetch_assoc()) && $row['count'] == 0) {
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, company_id) VALUES (?, ?, ?, ?)");
            $user = 'admin';
            $pass = password_hash('1234', PASSWORD_DEFAULT);
            $role = 0; // Admin role
            $stmt->bind_param("ssii", $user, $pass, $role, $companyId);
            $stmt->execute();
            $stmt->close();
            $msg = "✅ System Initialized. Default admin: admin/1234";
        } else {
            $msg = "✅ Database tables checked/created.";
        }
    } elseif ($_POST['action'] === 'reset') {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = $conn->query("SHOW TABLES");
        while ($row = $tables->fetch_array()) {
            $conn->query("DROP TABLE IF EXISTS `" . $row[0] . "`");
        }
        $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
        $msg = "⚠️ All tables dropped. You can now re-run setup.";
    } elseif ($_POST['action'] === 'logout') {
        unset($_SESSION['setup_authenticated']);
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Setup Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1d2b64, #f8cdda); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { border-radius: 15px; box-shadow: 0 8px 20px rgba(0,0,0,0.2); max-width: 600px; width: 100%; }
    </style>
</head>
<body>
    <div class="card p-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>🚀 Admin Setup</h2>
            <form method="POST"><button type="submit" name="action" value="logout" class="btn btn-sm btn-outline-secondary">Exit</button></form>
        </div>
        
        <?php if($msg): ?>
            <div class="alert alert-info"><?= $msg ?></div>
        <?php endif; ?>

        <p class="text-muted">Use this panel to initialize or reset the system database tables.</p>
        
        <div class="d-grid gap-3">
            <form method="POST">
                <button type="submit" name="action" value="setup" class="btn btn-success btn-lg w-100 mb-3">Initialize / Update Database</button>
            </form>
            <form method="POST" onsubmit="return confirm('WARNING: This will delete ALL data! Are you sure?')">
                <button type="submit" name="action" value="reset" class="btn btn-danger btn-lg w-100">Reset Database (Delete All)</button>
            </form>
        </div>
    </div>
</body>
</html>