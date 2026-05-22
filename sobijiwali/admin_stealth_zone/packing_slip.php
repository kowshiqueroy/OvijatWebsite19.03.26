<?php
/**
 * Thermal Packing Slip
 * Optimized for 80mm receipt printers.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthManager.php';

AuthManager::requireRole('admin', 'gatekeeper.php');

$db = Database::getInstance();
$orderId = (int)($_GET['order_id'] ?? 0);

$order = $db->query("SELECT o.*, u.email, p.first_name, p.last_name, p.shipping_address, p.phone 
                      FROM orders o 
                      JOIN users u ON o.user_id = u.id 
                      JOIN user_profiles p ON u.id = p.user_id 
                      WHERE o.id = ?", [$orderId])->fetch();

if (!$order) die("Order not found.");

$items = $db->query("SELECT i.*, v.sku, p.name as product_name, v.name_modifier 
                      FROM order_items i 
                      JOIN product_variations v ON i.product_variation_id = v.id 
                      JOIN products p ON v.product_id = p.id 
                      WHERE i.order_id = ?", [$orderId])->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Slip #<?php echo $orderId; ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 80mm; margin: 0; padding: 5mm; font-size: 12px; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .hr { border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; vertical-align: top; }
        @media print { .no-print { display: none; } @page { margin: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="center bold" style="font-size: 18px;">SOBJIWALI</div>
    <div class="center">PACKING SLIP</div>
    <div class="hr"></div>
    <div>ORDER ID: #<?php echo $orderId; ?></div>
    <div>DATE: <?php echo date('Y-m-d H:i'); ?></div>
    <div class="hr"></div>
    <div class="bold">SHIP TO:</div>
    <div><?php echo strtoupper($order['first_name'] . ' ' . $order['last_name']); ?></div>
    <div><?php echo strtoupper($order['shipping_address']); ?></div>
    <div>TEL: <?php echo $order['phone']; ?></div>
    <div class="hr"></div>
    <table>
        <thead>
            <tr class="bold">
                <td style="width: 70%;">ITEM</td>
                <td style="width: 30%; text-align: right;">QTY</td>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <?php echo strtoupper($item['product_name']); ?><br>
                    <small><?php echo $item['sku']; ?></small>
                </td>
                <td style="text-align: right;">[ ] <?php echo $item['quantity']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="hr"></div>
    <div class="center">*** THANK YOU ***</div>
    <div class="center">SOBJIWALI.COM</div>
</body>
</html>
