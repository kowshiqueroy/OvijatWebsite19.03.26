<?php
/**
 * Database Configuration File
 * Core PHP Employee Management System
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hr_system');

$conn = null;

function getDBConnection() {
    global $conn;
    
    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                // If database doesn't exist, we don't die here so setup.php can handle it
                return null;
            }
            $conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            return null;
        }
    }
    return $conn;
}

function closeDB() {
    global $conn;
    if ($conn !== null) {
        $conn->close();
        $conn = null;
    }
}
