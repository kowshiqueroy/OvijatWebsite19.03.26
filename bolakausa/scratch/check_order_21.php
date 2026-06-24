<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = 21");
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);
echo "ORDER 21:\n";
print_r($order);

$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = 21");
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nORDER ITEMS:\n";
print_r($items);
