<?php
include_once 'config.php';
$id = $_GET['id'];
$stmt = $conn->prepare("SELECT tp_rate, dp_rate FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo json_encode($row);
$stmt->close();
$conn->close();
?>
