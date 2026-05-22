<?php
/**
 * API for Mini-Cart updates
 */
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/CartManager.php';

header('Content-Type: application/json');

$clientCart = json_decode(file_get_contents('php://input'), true) ?? [];
$cartManager = new CartManager();
$synced = $cartManager->syncCart($clientCart);

echo json_encode($synced);
