<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

try {
    $db = Database::getInstance();
    
    echo "--- Orders Table ---\n";
    $cols = $db->query("DESCRIBE orders")->fetchAll();
    foreach ($cols as $c) echo "- {$c['Field']}\n";

    echo "\n--- Checking for user_addresses table ---\n";
    $tables = $db->query("SHOW TABLES LIKE 'user_addresses'")->fetchAll();
    if ($tables) {
        echo "user_addresses exists.\n";
        $cols = $db->query("DESCRIBE user_addresses")->fetchAll();
        foreach ($cols as $c) echo "- {$c['Field']}\n";
    } else {
        echo "user_addresses DOES NOT EXIST.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
