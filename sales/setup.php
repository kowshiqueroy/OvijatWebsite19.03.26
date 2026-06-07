<?php
require_once 'config.php';

// 1. COMPANIES TABLE (parent)
$conn->query("
CREATE TABLE IF NOT EXISTS companies (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(100),
    website VARCHAR(100),
    logo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 2. USERS TABLE (child of companies)
$conn->query("
CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role TINYINT(1) NOT NULL DEFAULT 0,
    company_id INT(11) UNSIGNED,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 3. ROUTES TABLE
$conn->query("
CREATE TABLE IF NOT EXISTS routes (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(255) NOT NULL,
    company_id INT(11) UNSIGNED NOT NULL,
    status TINYINT(1) DEFAULT 1,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 4. SHOPS TABLE
$conn->query("
CREATE TABLE IF NOT EXISTS shops (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_name VARCHAR(255) NOT NULL,
    route_id INT(11) UNSIGNED NOT NULL,
    company_id INT(11) UNSIGNED NOT NULL,
    user_id INT(11) UNSIGNED,
    balance DECIMAL(15,2) DEFAULT 0,
    status TINYINT(1) DEFAULT 1,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 5. ITEMS TABLE
$conn->query("
CREATE TABLE IF NOT EXISTS items (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    stock INT(11) DEFAULT 0,
    company_id INT(11) UNSIGNED NOT NULL,
    status TINYINT(1) DEFAULT 1,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 6. ORDERS TABLE
$conn->query("
CREATE TABLE IF NOT EXISTS orders (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 7. ORDER ITEMS TABLE
$conn->query("
CREATE TABLE IF NOT EXISTS order_items (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT(11) UNSIGNED NOT NULL,
    item_id INT(11) UNSIGNED NOT NULL,
    quantity INT(11) NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 8. CASH COLLECTIONS TABLE
$conn->query("
CREATE TABLE IF NOT EXISTS cash_collections (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 9. Damage Reports TABLE


// 10. Market Surveys TABLE
$conn->query("
CREATE TABLE IF NOT EXISTS surveys (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

//serials TABLE
$conn->query("
CREATE TABLE IF NOT EXISTS serials (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// offline_orders TABLE

 /* items JSON requires MySQL 5.7+ or use TEXT emaple: 
       [{"name":"i","qty":"10","price":"06","total":"60.00"},{"name":"Ir","qty":"12","price":"05","total":"60.00"}] */
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

// 11. Gifts TABLE

// 12. Brandings TABLE

// 13. Promotions TABLE

// --- Insert Default Company & Admin ---
$res = $conn->query("SELECT COUNT(*) AS count FROM companies");
if ($res && ($row = $res->fetch_assoc()) && $row['count'] == 0) {
    $conn->query("INSERT INTO companies (name) VALUES ('Default Company')");
}
$companyId = $conn->insert_id ?: 1;
$msg='';
$res = $conn->query("SELECT COUNT(*) AS count FROM users");
if ($res && ($row = $res->fetch_assoc()) && $row['count'] == 0) {
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, company_id) VALUES (?, ?, ?, ?)");
    $user = 'admin';
    $pass = password_hash('1234', PASSWORD_DEFAULT);
    $role = 0; // Admin role
    $stmt->bind_param("ssii", $user, $pass, $role, $companyId);
    $stmt->execute();
    $stmt->close();
    $msg = "✅ Default admin created: username='admin', password='1234'\n";
}
?>
<?php
// session_start();

// Define a secure password (hashed)
$admin_password_hash = password_hash("5877", PASSWORD_DEFAULT);

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['password']) && !empty($_POST['password'])) {
        if (password_verify($_POST['password'], $admin_password_hash)) {
            // ✅ Password correct, allow actions
            if (isset($_POST['action'])) {
                if ($_POST['action'] === 'setup') {
                    header("Location: setup.php");
                    exit;
                } elseif ($_POST['action'] === 'delete') {
                    if (unlink(__FILE__)) {
                        echo "<script>alert('setup.php has been deleted for security reasons.'); window.location.href='index.php';</script>";
                        exit;
                    } else {
                        $msg = "❌ Error: Could not delete setup.php. Please delete it manually.";
                    }
                } elseif ($_POST['action'] === 'reset') {
                    // Reset DB tables
                    $tables = $conn->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = 'oeis' ORDER BY TABLE_NAME");
                    while ($row = $tables->fetch_assoc()) {
                        $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
                        $conn->query("DROP TABLE IF EXISTS `" . $row['TABLE_NAME'] . "`");
                    }
                    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
                    header("Location: setup.php");
                    exit;
                }
            }
        } else {
            // ❌ Wrong password
            $msg = "Error: Incorrect password.";
        }
    } else {
        $msg = "Error: Password is required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Setup</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #1d2b64, #f8cdda);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', sans-serif;
    }
    .card {
      border-radius: 15px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    }
    .btn-setup { background: #28a745; color: #fff; font-weight: bold; }
    .btn-delete { background: #dc3545; color: #fff; font-weight: bold; }
    .btn-warning { background: #ffc107; color: #fff; font-weight: bold; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card p-5 text-center">
      <h2 class="mb-4">🚀 Admin Setup</h2>
      <p class="mb-4">Choose an action to initialize or secure your system.</p>
      <p class="mb-4"><?=htmlspecialchars($msg); ?></p>
      <form method="post">
        <input type="password" name="password" placeholder="Enter admin password" required class="form-control mb-3">
        <button type="submit" name="action" value="setup" class="btn btn-setup btn-lg me-3">Start Setup</button>
        <button type="submit" name="action" value="delete" class="btn btn-delete btn-lg">Delete setup.php</button>
        <button type="submit" name="action" value="reset" class="btn btn-warning btn-lg">Reset setup</button>
      </form>
    </div>
  </div>
</body>
</html>