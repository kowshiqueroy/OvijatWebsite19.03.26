<?php
/**
 * Full System Migration Script
 * This script updates your existing database to the latest version with all new features.
 * Features included: Truck Loading, Delivery Tracking, Stock Damages, Credit Limits, and Accountant Controls.
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

// Ensure only Admin/Accountant can run this if already logged in, 
// or allow if running from CLI/initial setup
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Optional: check_role([ROLE_ADMIN]); 

$conn = get_db_connection();
echo "<h2>Starting Full System Migration...</h2>";
echo "<p>Checking and updating database schema. Please wait...</p>";

// 1. Update sales_drafts table
$sql1 = "ALTER TABLE sales_drafts 
    ADD COLUMN IF NOT EXISTS delivery_status ENUM('Pending', 'Loading', 'In Transit', 'Delivered', 'Failed', 'Returned') DEFAULT 'Pending' AFTER confirmed_at,
    ADD COLUMN IF NOT EXISTS delivery_date DATE NULL AFTER delivery_status,
    ADD COLUMN IF NOT EXISTS hide_from_print TINYINT(1) DEFAULT 0 AFTER delivery_date";

if ($conn->query($sql1)) {
    echo "✅ Updated 'sales_drafts' with delivery and print control fields.<br>";
} else {
    echo "❌ Error updating 'sales_drafts': " . $conn->error . "<br>";
}

// 2. Update transactions table
$sql2 = "ALTER TABLE transactions 
    ADD COLUMN IF NOT EXISTS hide_from_print TINYINT(1) DEFAULT 0 AFTER description";

if ($conn->query($sql2)) {
    echo "✅ Updated 'transactions' with print control toggle.<br>";
} else {
    echo "❌ Error updating 'transactions': " . $conn->error . "<br>";
}

// 3. Update customers table
$sql3 = "ALTER TABLE customers 
    ADD COLUMN IF NOT EXISTS credit_limit DECIMAL(15,2) DEFAULT 0.00 AFTER balance";

if ($conn->query($sql3)) {
    echo "✅ Updated 'customers' with credit limit field.<br>";
} else {
    echo "❌ Error updating 'customers': " . $conn->error . "<br>";
}

// 4. Create truck_loads table
$sql4 = "CREATE TABLE IF NOT EXISTS truck_loads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    truck_no VARCHAR(50) NOT NULL,
    driver_name VARCHAR(100),
    source_location VARCHAR(255),
    destination_location VARCHAR(255),
    remarks TEXT,
    status ENUM('Draft', 'Loaded', 'Departed', 'Completed') DEFAULT 'Draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    isDelete TINYINT(1) DEFAULT 0
)";

if ($conn->query($sql4)) {
    echo "✅ Created 'truck_loads' table for logistics.<br>";
} else {
    echo "❌ Error creating 'truck_loads': " . $conn->error . "<br>";
}

// 5. Create truck_load_items table
$sql5 = "CREATE TABLE IF NOT EXISTS truck_load_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    truck_load_id INT,
    invoice_id INT,
    isDelete TINYINT(1) DEFAULT 0
)";

if ($conn->query($sql5)) {
    echo "✅ Created 'truck_load_items' mapping table.<br>";
} else {
    echo "❌ Error creating 'truck_load_items': " . $conn->error . "<br>";
}

// 6. Create stock_damages table
$sql6 = "CREATE TABLE IF NOT EXISTS stock_damages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    user_id INT,
    quantity INT NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    isDelete TINYINT(1) DEFAULT 0
)";

if ($conn->query($sql6)) {
    echo "✅ Created 'stock_damages' table for inventory control.<br>";
} else {
    echo "❌ Error creating 'stock_damages': " . $conn->error . "<br>";
}

// 7. Universal isDelete Safety Check
echo "🛡️ Running universal safety check for soft-delete columns...<br>";
$tables_res = $conn->query("SHOW TABLES");
while ($row = $tables_res->fetch_array()) {
    $table = $row[0];
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'isDelete'");
    if ($check->num_rows == 0) {
        if ($conn->query("ALTER TABLE `$table` ADD COLUMN isDelete TINYINT(1) DEFAULT 0")) {
            echo "&nbsp;&nbsp; - Added 'isDelete' to table: $table<br>";
        }
    }
}

echo "<br><h3>🎉 Migration Complete!</h3>";
echo "<p>All new features are now active. You should now <strong>delete this file (migrate_full.php)</strong> for security.</p>";
?>
