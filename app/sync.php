<?php
// sync.php
header('Content-Type: application/json');
require_once 'config.php';

// Connect to MySQL
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]);
    exit;
}

// Ensure table exists


$raw = file_get_contents("php://input");
$orders = json_decode($raw, true);

if (!is_array($orders)) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO offline_orders 
    (user_name, route_name, shop_details, order_date, delivery_date, note, items, total, synced, admin_approval_id, admin_approval_timedate) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");

$count = 0;
foreach ($orders as $order) {
    $itemsJson = json_encode($order["items"] ?? []);
    $stmt->bind_param(
        "sssssssdss",
        $order["user_name"],
        $order["route_name"],
        $order["shop_details"],
        $order["order_date"],
        $order["delivery_date"],
        $order["note"],
        $itemsJson,
        $order["total"],
        $order["admin_approval_id"],
        $order["admin_approval_timedate"]
    );
    $stmt->execute();
    $count++;
}

echo json_encode(["status" => "success", "message" => "Orders synced", "count" => $count]);

$stmt->close();
$conn->close();