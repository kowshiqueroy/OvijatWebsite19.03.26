<?php
/**
 * Redesigned Modern Standalone Invoice
 */
restrict_to(['wholesale_user', 'admin', 'manager', 'executive']);

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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order['id']; ?> — <?php echo e($company_name); ?></title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --secondary: #0f172a;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-light: #f1f5f9;
            --border-dark: #cbd5e1;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f8fafc;
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--text-main);
            line-height: 1.6;
            padding: 3rem 1.5rem;
        }
        
        .invoice-card {
            background: #ffffff;
            max-width: 850px;
            margin: 0 auto;
            padding: 3.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            border: 1px solid var(--border-light);
            position: relative;
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--border-light);
            padding-bottom: 2rem;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        
        .logo-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--secondary);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 1.5rem;
        }
        
        .logo-img {
            max-height: 42px;
            width: auto;
            object-fit: contain;
        }
        
        .meta-details {
            text-align: right;
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
        }
        
        .meta-details strong {
            color: var(--secondary);
        }
        
        .billing-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5rem;
            margin-bottom: 2.5rem;
        }
        
        .bill-box h5, .ship-box h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 0.35rem;
        }
        
        .bill-box p, .ship-box p {
            font-size: 0.9rem;
            color: var(--text-main);
            line-height: 1.5;
        }
        
        .bill-box strong, .ship-box strong {
            color: var(--secondary);
            font-weight: 700;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2.5rem;
        }
        
        .items-table th {
            background: #f8fafc;
            padding: 0.85rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            border-bottom: 2px solid #e2e8f0;
        }
        
        .items-table td {
            padding: 1.15rem 1rem;
            font-size: 0.9rem;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-main);
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .summary-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 2.5rem;
        }
        
        .summary-table {
            width: 320px;
            font-size: 0.9rem;
            border-collapse: collapse;
        }
        
        .summary-table td {
            padding: 0.45rem 1rem;
            text-align: right;
            color: var(--text-muted);
        }
        
        .summary-table td:last-child {
            color: var(--secondary);
            font-weight: 600;
        }
        
        .summary-table tr.discount-row td {
            color: #155724;
        }
        
        .summary-table tr.discount-row td:last-child {
            color: #155724;
            font-weight: 700;
        }
        
        .summary-table tr.total-row {
            font-size: 1.2rem;
            font-weight: 800;
        }
        
        .summary-table tr.total-row td {
            color: var(--secondary);
            border-top: 2px solid var(--border-light);
            padding-top: 1rem;
        }
        
        .summary-table tr.total-row td:last-child {
            color: var(--primary);
            font-weight: 800;
        }
        
        .payment-info {
            background: #f8fafc;
            border: 1px solid var(--border-light);
            border-radius: 10px;
            padding: 1.25rem;
            margin-top: 2rem;
            font-size: 0.85rem;
        }
        
        .payment-info h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        
        .payment-info p {
            color: var(--text-main);
            line-height: 1.4;
        }
        
        .actions-panel {
            text-align: center;
            margin-top: 2.5rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-print {
            background: var(--primary);
            color: white;
        }
        
        .btn-print:hover {
            background: var(--primary-dark);
        }
        
        .btn-back {
            background: transparent;
            border-color: var(--border-dark);
            color: var(--text-main);
        }
        
        .btn-back:hover {
            background: rgba(15, 23, 42, 0.04);
        }
        
        @media print {
            body {
                background: #ffffff;
                padding: 0;
                color: #000000;
            }
            .invoice-card {
                border: none;
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            .actions-panel {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="invoice-card">
        <!-- Header -->
        <div class="invoice-header">
            <div class="logo-title">
                <img src="<?php echo BASE_URL; ?>public/images/logo/logoofbolakausa.png" alt="Bolakausa Logo" class="logo-img">
                <span><span style="color: var(--primary);">Bolaka</span>USA.com</span>
            </div>
            
            <div class="meta-details">
                <strong>INVOICE</strong><br>
                Invoice ID: <strong>#<?php echo $order['id']; ?></strong><br>
                Date Placed: <?php echo date('M d, Y', strtotime($order['created_at'])); ?><br>
                Fulfillment Status: <strong style="text-transform: uppercase; color: <?php echo ($order['status'] === 'Delivered') ? 'var(--primary)' : 'var(--text-main)'; ?>"><?php echo $order['status']; ?></strong>
            </div>
        </div>
        
        <!-- Billing Details -->
        <div class="billing-grid">
            <div class="bill-box">
                <h5>Bill To</h5>
                <p>
                    <strong><?php echo e($order['full_name']); ?></strong><br>
                    Email: <?php echo e($order['email']); ?><br>
                    Phone: <?php echo e($order['phone']); ?>
                </p>
            </div>
            
            <div class="ship-box">
                <h5>Ship To</h5>
                <p>
                    <?php echo nl2br(e($order['delivery_address'])); ?>
                </p>
            </div>
        </div>
        
        <!-- Table Grid -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>Unit Price</th>
                    <th style="text-align: center;">Quantity</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $items_subtotal = 0;
                foreach ($items as $item): 
                    $sub = (float)$item['price_at_purchase'] * (int)$item['qty'];
                    $items_subtotal += $sub;
                ?>
                <tr>
                    <td><strong><?php echo e($item['name']); ?></strong></td>
                    <td>$<?php echo number_format($item['price_at_purchase'], 2); ?></td>
                    <td style="text-align: center;"><?php echo $item['qty']; ?></td>
                    <td style="text-align: right; font-weight: 600;">$<?php echo number_format($sub, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Summary Row -->
        <div class="summary-wrapper">
            <table class="summary-table">
                <tr>
                    <td>Items Subtotal:</td>
                    <td>$<?php echo number_format($items_subtotal, 2); ?></td>
                </tr>
                <?php if ((float)$order['discount_amount'] > 0): ?>
                <tr class="discount-row">
                    <td>Applied Discounts:</td>
                    <td>-$<?php echo number_format($order['discount_amount'], 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Shipping & Freight:</td>
                    <td>$<?php echo number_format($order['shipping_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td>State Tax Amount:</td>
                    <td>$<?php echo number_format($order['tax_amount'], 2); ?></td>
                </tr>
                <tr class="total-row">
                    <td>Grand Total:</td>
                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Payment & Route details -->
        <div class="payment-info">
            <h6>Payment Information</h6>
            <p>
                Method: <strong><?php echo $order['payment_method']; ?></strong>
                <?php 
                if ($order['payment_details']) {
                    $details = json_decode($order['payment_details'], true);
                    if ($details) {
                        if ($order['payment_method'] === 'Bank Transfer') {
                            echo " — Bank: <strong>" . e($details['bank_name'] ?? '') . "</strong> | Txn ID: <strong>" . e($details['transaction_id'] ?? '') . "</strong> | Wire Date: <strong>" . e($details['transfer_date'] ?? '') . "</strong>";
                        } elseif ($order['payment_method'] === 'Stripe') {
                            echo " — Stripe Charge: <strong>" . e($details['charge_id'] ?? '') . "</strong> | Status: <strong>" . e($details['status'] ?? '') . "</strong>";
                        }
                    }
                }
                ?>
            </p>
        </div>
        
        <!-- Thank you footer -->
        <div class="footer-note" style="text-align: center;">
            <p><strong>Thank you for choosing <?php echo e($company_name); ?> as your wholesale partner!</strong></p>
            <p style="font-size: 0.75rem; margin-top: 0.25rem;">For questions regarding logistics or delivery windows, please contact support via the dashboard chat.</p>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="actions-panel">
        <button onclick="window.print()" class="btn btn-print"><i class="fas fa-print"></i> Print Invoice</button>
        <a href="/bolakausa/orders" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to Orders</a>
    </div>

</body>
</html>
