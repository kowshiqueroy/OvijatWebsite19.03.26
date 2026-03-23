<?php
require_once __DIR__ . '/includes/bootstrap.php';
$sql = "SHOW TABLES LIKE 'product_entries'";
$stmt = db()->query($sql);
if ($stmt->rowCount() > 0) {
    echo "TABLE_EXISTS";
} else {
    echo "TABLE_NOT_FOUND";
}
