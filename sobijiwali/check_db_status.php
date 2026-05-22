<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

try {
    $db = Database::getInstance();
    echo "Connection Successful!<br>";
    
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in " . DB_NAME . ":<br>";
    foreach ($tables as $table) {
        echo "- $table<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
