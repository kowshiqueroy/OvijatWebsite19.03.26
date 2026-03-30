<?php
// Database configuration
$db_host = 'localhost';
$db_name = 'u312077073_qc';
$db_user = 'u312077073_kushqc'; // Change to your database username
$db_pass = '6Q?eaoj4';

// Database connection using MySQLi
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error;
    exit;
}
// Set character set to utf8
$conn->set_charset("utf8");
// Start session
session_start();
$company_name = 'Ovijat QC'; // Set your company name here
?>