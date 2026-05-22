<?php
/**
 * Test Script for Authentication System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthManager.php';

echo "<h1>Authentication System Test</h1>";

$auth = new AuthManager();
$db = Database::getInstance();

try {
    // 1. Register Retail User
    echo "Registering Retail User... ";
    $retailEmail = "retail_" . time() . "@example.com";
    $userId = $auth->registerRetail($retailEmail, "password123", ['first_name' => 'John', 'last_name' => 'Doe']);
    echo "Done (ID: $userId)<br>";

    // 2. Register Wholesale User (Pending)
    echo "Registering Wholesale User... ";
    $wholesaleEmail = "wholesale_" . time() . "@example.com";
    $userId = $auth->registerWholesale($wholesaleEmail, "password123", ['first_name' => 'Jane', 'last_name' => 'Shop']);
    echo "Done (ID: $userId)<br>";

    // 3. Test Retail Login
    echo "Testing Retail Login... ";
    $loginResult = $auth->login($retailEmail, "password123");
    if ($loginResult['success']) {
        echo "Success! Logged in as: " . $_SESSION['user_email'] . "<br>";
    } else {
        echo "Failed: " . $loginResult['message'] . "<br>";
    }

    // 4. Test Wholesale Login (Should Fail due to Pending)
    echo "Testing Wholesale Login (Pending)... ";
    $loginResult = $auth->login($wholesaleEmail, "password123");
    if (!$loginResult['success']) {
        echo "Correctly Blocked: " . $loginResult['message'] . "<br>";
    } else {
        echo "Error: Pending wholesale should not be able to log in!<br>";
    }

    // 5. Create Admin User manually and test
    echo "Creating Admin User... ";
    $adminEmail = "admin_" . time() . "@example.com";
    $hashedPassword = password_hash("adminpass", PASSWORD_DEFAULT);
    $db->query("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'admin')", [$adminEmail, $hashedPassword]);
    echo "Done<br>";

    echo "Testing Admin Login... ";
    $loginResult = $auth->login($adminEmail, "adminpass");
    if ($loginResult['success'] && AuthManager::hasRole('admin')) {
        echo "Success! Admin authenticated.<br>";
    } else {
        echo "Failed.<br>";
    }

    // 6. Test Logout
    echo "Testing Logout... ";
    $auth->logout();
    if (!AuthManager::isLoggedIn()) {
        echo "Success! Logged out.<br>";
    } else {
        echo "Failed.<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
