<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tablesToCheck = ['product_entries', 'requisitions'];
$allExist = true;

foreach ($tablesToCheck as $table) {
    $stmt = db()->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() == 0) {
        $allExist = false;
        break;
    }
}

if ($allExist) {
    echo "TABLE_EXISTS";
} else {
    echo "TABLE_NOT_FOUND";
}
//cretae table requisitions
// CREATE TABLE IF NOT EXISTS requisitions (
//     id                  INT AUTO_INCREMENT PRIMARY KEY,
//     requisition_no      VARCHAR(30) NOT NULL UNIQUE,
//     customer_id         INT,
//     customer_name       VARCHAR(150),
//     customer_phone      VARCHAR(30),
//     customer_email      VARCHAR(150),
//     school_name         VARCHAR(200),
//     status              ENUM('draft','order_confirm','edit_confirm','on_design','on_production','on_packaging','delivery_details','delivered','failed') DEFAULT 'draft',
//     notes               TEXT,
//     created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
//     updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//     FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
// );

// CREATE TABLE IF NOT EXISTS requisition_items (
//     id              INT AUTO_INCREMENT PRIMARY KEY,
//     requisition_id  INT NOT NULL,
//     product_name    VARCHAR(200) NOT NULL,
//     size            VARCHAR(50),
//     color           VARCHAR(50),
//     label           VARCHAR(100),
//     notes           TEXT,
//     qty             INT NOT NULL DEFAULT 1,
//     unit_price      DECIMAL(12,2) NOT NULL DEFAULT 0,
//     total_price     DECIMAL(12,2) NOT NULL DEFAULT 0,
//     created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
//     FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE CASCADE
// );
//create these tables requisitions and requisition_items if not exist
try {
    db()->exec("CREATE TABLE IF NOT EXISTS requisitions (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        requisition_no      VARCHAR(30) NOT NULL UNIQUE,
        customer_id         INT,
        customer_name       VARCHAR(150),
        customer_phone      VARCHAR(30),
        customer_email      VARCHAR(150),
        school_name         VARCHAR(200),
        status              ENUM('draft','order_confirm','edit_confirm','on_design','on_production','on_packaging','delivery_details','delivered','failed') DEFAULT 'draft',
        notes               TEXT,
        created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
    )");

    db()->exec("CREATE TABLE IF NOT EXISTS requisition_items (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        requisition_id  INT NOT NULL,
        product_name    VARCHAR(200) NOT NULL,
        size            VARCHAR(50),
        color           VARCHAR(50),
        label           VARCHAR(100),
        notes           TEXT,
        qty             INT NOT NULL DEFAULT 1,
        unit_price      DECIMAL(12,2) NOT NULL DEFAULT 0,
        total_price     DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    error_log("Error creating tables: " . $e->getMessage());
}

//show all tables in database
$stmt = db()->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "TABLES_IN_DATABASE: " . implode(", ", $tables);