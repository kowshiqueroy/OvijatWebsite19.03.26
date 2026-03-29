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
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            $conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
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
