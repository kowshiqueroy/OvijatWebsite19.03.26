<?php
ob_start();
/**
 * Global Configuration File
 */

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'food_company_db');

// Application Settings
define('APP_NAME', 'Food Distribution Management');
define('BASE_URL', '/distribution/');

// User Roles
define('ROLE_ADMIN', 'Admin');
define('ROLE_MANAGER', 'Manager');
define('ROLE_ACCOUNTANT', 'Accountant');
define('ROLE_SR', 'Sales Representative');
define('ROLE_CUSTOMER', 'Customer');
define('ROLE_VIEWER', 'Viewer');

// Database Connection
function get_db_connection() {
    static $conn;
    if (!$conn) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

// Global database query function
function db_query($sql, $params = []) {
    $conn = get_db_connection();
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Query preparation failed: " . $conn->error);
    }

    if ($params) {
        $types = "";
        foreach ($params as $param) {
            if (is_int($param)) $types .= "i";
            elseif (is_double($param)) $types .= "d";
            else $types .= "s";
        }
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    return $stmt;
}

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
