<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$conn = get_db_connection();

echo "Starting database update...<br>";

// 1. Update sales_drafts table
$sql1 = "ALTER TABLE sales_drafts 
    ADD COLUMN IF NOT EXISTS delivery_status ENUM('Pending', 'Loading', 'In Transit', 'Delivered', 'Failed', 'Returned') DEFAULT 'Pending' AFTER confirmed_at,
    ADD COLUMN IF NOT EXISTS delivery_date DATE NULL AFTER delivery_status,
    ADD COLUMN IF NOT EXISTS hide_from_print TINYINT(1) DEFAULT 0 AFTER delivery_date";

if ($conn->query($sql1)) {
    echo "Updated 'sales_drafts' table successfully.<br>";
} else {
    echo "Error updating 'sales_drafts': " . $conn->error . "<br>";
}

// 2. Update transactions table
$sql2 = "ALTER TABLE transactions 
    ADD COLUMN IF NOT EXISTS hide_from_print TINYINT(1) DEFAULT 0 AFTER description";

if ($conn->query($sql2)) {
    echo "Updated 'transactions' table successfully.<br>";
} else {
    echo "Error updating 'transactions': " . $conn->error . "<br>";
}

// 3. Create truck_loads table
$sql3 = "CREATE TABLE IF NOT EXISTS truck_loads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    truck_no VARCHAR(50) NOT NULL,
    driver_name VARCHAR(100),
    status ENUM('Draft', 'Loaded', 'Departed', 'Completed') DEFAULT 'Draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    isDelete TINYINT(1) DEFAULT 0,
    FOREIGN KEY (created_by) REFERENCES users(id)
)";

if ($conn->query($sql3)) {
    echo "Created 'truck_loads' table successfully.<br>";
} else {
    echo "Error creating 'truck_loads': " . $conn->error . "<br>";
}

// 4. Create truck_load_items table
$sql4 = "CREATE TABLE IF NOT EXISTS truck_load_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    truck_load_id INT,
    invoice_id INT,
    FOREIGN KEY (truck_load_id) REFERENCES truck_loads(id),
    FOREIGN KEY (invoice_id) REFERENCES sales_drafts(id)
)";

if ($conn->query($sql4)) {
    echo "Created 'truck_load_items' table successfully.<br>";
} else {
    echo "Error creating 'truck_load_items': " . $conn->error . "<br>";
}

echo "Database update complete.";
?>
