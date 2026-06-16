<?php
/**
 * Dynamic Invoice Generation
 */
restrict_to(['wholesale_user', 'admin', 'manager']);

$order_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = in_array($_SESSION['user_role'], ['admin', 'manager']);

// Fetch Order
$stmt = $pdo->prepare("SELECT o.*, u.full_name, u.email, u.phone FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order || (!$is_admin && $order['user_id'] != $user_id)) {
    die("Invoice not found or access denied.");
}

// Fetch Items
$stmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// Fetch Settings
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_name'");
$stmt->execute();
$company_name = $stmt->fetch()['setting_value'] ?? 'Bolakausa Wholesale';
?>

<div style="max-width: 800px; margin: 20px auto; padding: 40px; border: 1px solid #eee; font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;">
    <table cellpadding="0" cellspacing="0" style="width: 100%; line-height: inherit; text-align: left;">
        <tr class="top">
            <td colspan="4">
                <table style="width: 100%;">
                    <tr>
                        <td style="font-size: 45px; line-height: 45px; color: #333;">
                            <?php echo e($company_name); ?>
                        </td>
                        <td style="text-align: right;">
                            Invoice #: <?php echo $order['id']; ?><br>
                            Created: <?php echo date('M d, Y', strtotime($order['created_at'])); ?><br>
                            Status: <?php echo $order['status']; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        
        <tr style="padding-top: 40px;">
            <td colspan="2" style="padding: 20px 0;">
                <strong>Bill To:</strong><br>
                <?php echo e($order['full_name']); ?><br>
                <?php echo e($order['email']); ?><br>
                <?php echo e($order['phone']); ?>
            </td>
            <td colspan="2" style="text-align: right; padding: 20px 0;">
                <strong>Ship To:</strong><br>
                <?php echo nl2br(e($order['delivery_address'])); ?>
            </td>
        </tr>

        <tr style="background: #eee; border-bottom: 1px solid #ddd; font-weight: bold;">
            <td style="padding: 10px;">Item</td>
            <td style="padding: 10px;">Unit Price</td>
            <td style="padding: 10px;">Qty</td>
            <td style="padding: 10px; text-align: right;">Subtotal</td>
        </tr>

        <?php 
        $items_subtotal = 0;
        foreach ($items as $item): 
            $sub = $item['price_at_purchase'] * $item['qty'];
            $items_subtotal += $sub;
        ?>
        <tr style="border-bottom: 1px solid #eee;">
            <td style="padding: 10px;"><?php echo e($item['name']); ?></td>
            <td style="padding: 10px;">$<?php echo number_format($item['price_at_purchase'], 2); ?></td>
            <td style="padding: 10px;"><?php echo $item['qty']; ?></td>
            <td style="padding: 10px; text-align: right;">$<?php echo number_format($sub, 2); ?></td>
        </tr>
        <?php endforeach; ?>

        <tr><td colspan="4" style="padding-top: 20px;"></td></tr>
        
        <tr>
            <td colspan="3" style="text-align: right; padding: 5px 10px;">Subtotal:</td>
            <td style="text-align: right; padding: 5px 10px;">$<?php echo number_format($items_subtotal, 2); ?></td>
        </tr>
        <tr>
            <td colspan="3" style="text-align: right; padding: 5px 10px;">Shipping:</td>
            <td style="text-align: right; padding: 5px 10px;">$<?php echo number_format($order['shipping_amount'], 2); ?></td>
        </tr>
        <tr>
            <td colspan="3" style="text-align: right; padding: 5px 10px;">Tax:</td>
            <td style="text-align: right; padding: 5px 10px;">$<?php echo number_format($order['tax_amount'], 2); ?></td>
        </tr>
        <tr style="font-weight: bold; font-size: 1.2em;">
            <td colspan="3" style="text-align: right; padding: 10px;">Grand Total:</td>
            <td style="text-align: right; padding: 10px;">$<?php echo number_format($order['total_amount'], 2); ?></td>
        </tr>
        
        <tr>
            <td colspan="4" style="padding-top: 40px;">
                <strong>Payment Method:</strong> <?php echo $order['payment_method']; ?><br>
                <em>Thank you for your business!</em>
            </td>
        </tr>
    </table>
</div>

<div style="text-align: center; margin-top: 20px;">
    <button onclick="window.print()">Print Invoice</button> | 
    <a href="/bolakausa/orders">Back to Orders</a>
</div>
