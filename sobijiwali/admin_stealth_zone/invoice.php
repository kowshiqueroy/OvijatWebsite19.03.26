<?php
/**
 * Order Invoice
 * A4 Printable layout with QR Code.
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

$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode(SITE_URL . "/verify.php?id=" . $orderId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $orderId; ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; line-height: 1.6; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
        .logo { font-size: 2rem; font-weight: bold; color: #4CAF50; }
        .invoice-details { text-align: right; }
        .address-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th { background: #f9f9f9; text-align: left; padding: 12px; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .totals { float: right; width: 250px; }
        .total-row { display: flex; justify-content: space-between; padding: 10px 0; }
        .grand-total { font-weight: bold; border-top: 2px solid #4CAF50; font-size: 1.2rem; }
        .qr-section { margin-top: 40px; text-align: center; font-size: 0.8rem; color: #666; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center; padding: 20px;">
        <button onclick="window.print()">Print Invoice</button>
    </div>
    <div class="invoice-box">
        <div class="header">
            <div class="logo">Sobjiwali</div>
            <div class="invoice-details">
                <strong>Invoice #<?php echo $orderId; ?></strong><br>
                Date: <?php echo date('M d, Y', strtotime($order['created_at'])); ?><br>
                Status: <?php echo strtoupper($order['status']); ?>
            </div>
        </div>

        <div class="address-grid">
            <div>
                <strong>From:</strong><br>
                Sobjiwali E-Commerce<br>
                Warehouse 12, Agro Park<br>
                support@sobjiwali.com
            </div>
            <div>
                <strong>To:</strong><br>
                <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?><br>
                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?><br>
                Phone: <?php echo htmlspecialchars($order['phone']); ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product_name']); ?> (<?php echo htmlspecialchars($item['name_modifier']); ?>)</td>
                    <td><code><?php echo htmlspecialchars($item['sku']); ?></code></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                    <td>$<?php echo number_format($item['total_price'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="total-row"><span>Subtotal:</span> <span>$<?php echo number_format($order['total_amount'] - $order['shipping_fee'] - $order['tax_amount'], 2); ?></span></div>
            <div class="total-row"><span>Shipping:</span> <span>$<?php echo number_format($order['shipping_fee'], 2); ?></span></div>
            <div class="total-row"><span>Tax:</span> <span>$<?php echo number_format($order['tax_amount'], 2); ?></span></div>
            <div class="total-row grand-total"><span>Total:</span> <span>$<?php echo number_format($order['total_amount'], 2); ?></span></div>
        </div>

        <div style="clear:both;"></div>

        <div class="qr-section">
            <img src="<?php echo $qrUrl; ?>" alt="Verification QR"><br>
            Scan to verify order status.
        </div>
    </div>
</body>
</html>
