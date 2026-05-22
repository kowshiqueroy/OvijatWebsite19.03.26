<?php
// Mock session for CLI
session_start();

require_once 'auth.php';

$test_user = 'kush';
$test_pass = 'iloverai';

echo "Testing login for user: $test_user\n";

if (login($test_user, $test_pass)) {
    echo "SUCCESS: Login successful!\n";
    echo "Session data: " . print_r($_SESSION, true) . "\n";
} else {
    echo "FAILURE: Login failed with credentials from config.php.\n";
    
    // Debug: check what's in the DB
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'pass_1'");
    $stmt->execute();
    $hash = $stmt->fetchColumn();
    echo "Hash in DB for pass_1: $hash\n";
    if (password_verify($test_pass, $hash)) {
        echo "password_verify() actually WORKS with this hash and password.\n";
    } else {
        echo "password_verify() FAILS with this hash and password.\n";
    }
}
