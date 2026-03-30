<?php
// Database configuration
$db_host = 'localhost';
$db_name = 'u312077073_qc';
$db_user = 'u312077073_kushqc';
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
$company_name = 'Ovijat QC';

function logAudit($conn, $action, $table_name, $record_id, $details = '') {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $action, $table_name, $record_id, $details, $ip_address);
    $stmt->execute();
    $stmt->close();
}
?>