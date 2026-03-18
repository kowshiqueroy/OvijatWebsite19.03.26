<?php
// ============================================================
// inv.php — Public Invoice Verification Page
// ============================================================
require_once __DIR__ . '/includes/bootstrap.php';

$id   = (int)($_GET['id'] ?? 0);
$sale = dbFetch(
    "SELECT s.*, u.full_name AS staff_name, c.name AS customer_name, c.phone AS customer_phone 
     FROM sales s 
     JOIN users u ON u.id = s.user_id
     LEFT JOIN customers c ON c.id = s.customer_id 
     WHERE s.id = ?",
    [$id]
);

if (!$sale) {
    die("
    <div style='font-family:sans-serif; text-align:center; padding:50px; color:#333; background:#f4f6f9; min-height:100vh;'>
        <div style='background:#fff; max-width:400px; margin:0 auto; padding:30px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.05);'>
            <h2 style='color:#d63031; margin-top:0;'>❌ Invalid Invoice</h2>
            <p style='color:#666;'>We could not find this invoice in our system. It may have been deleted or the URL is incorrect.</p>
        </div>
    </div>
    ");
}

$items = dbFetchAll('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id', [$id]);
$S     = getAllSettings();
$cur   = $S['currency_symbol'] ?? '$';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= e($sale['invoice_no']) ?></title>
    <style>
        /* Mobile-first, scrollable body */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0; 
            padding: 20px 15px; 
            color: #222;
            overflow-y: auto; /* Ensures smooth scrolling */
        }
        .container {
            max-width: 600px; 
            margin: 0 auto; 
            background: #fff;
            border-radius: 10px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.08); 
            overflow: hidden;
        }
        
        /* Header & Branding */
        .header {
            background: #6c5ce7; 
            color: #fff; 
            padding: 25px 20px; 
            text-align: center;
        }
        .logo-img {
            max-height: 60px; 
            max-width: 200px; 
            object-fit: contain; 
            margin-bottom: 15px;
            background: #fff;
            padding: 5px;
            border-radius: 4px;
        }
        .header h1 { margin: 0 0 8px 0; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
        .header p { margin: 0 0 4px 0; opacity: 0.9; font-size: 13px; line-height: 1.4; }
        .badge {
            display: inline-block; background: #2ed573; color: #fff;
            padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 11px;
            margin-top: 15px; text-transform: uppercase; letter-spacing: 0.5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Information Grid */
        .section { padding: 20px; border-bottom: 1px solid #f0f0f0; }
        .grid-2 { display: flex; justify-content: space-between; gap: 15px; }
        .grid-2 div { flex: 1; }
        .label { font-size: 11px; color: #888; text-transform: uppercase; margin-bottom: 3px; font-weight: 600; }
        .value { font-size: 14px; font-weight: 600; margin-bottom: 15px; color: #111; }
        
        /* Items Table */
        .table-wrapper { overflow-x: auto; } /* Allows table to scroll sideways on tiny screens if needed */
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th { text-align: left; padding: 12px 8px; border-bottom: 2px solid #eee; font-size: 12px; color: #666; text-transform: uppercase; }
        td { padding: 12px 8px; border-bottom: 1px solid #f5f5f5; font-size: 14px; vertical-align: top; }
        .item-name { font-weight: 600; color: #222; margin-bottom: 2px; }
        .item-var { font-size: 12px; color: #777; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        /* Summary & Totals */
        .totals { padding: 20px; background: #fafbfc; }
        .tot-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #444; }
        .tot-row.grand { 
            font-size: 18px; font-weight: bold; color: #111; 
            margin-top: 15px; padding-top: 15px; border-top: 2px solid #e1e4e8; 
        }
        
        /* Notes & Footer */
        .notes-box {
            margin-top: 20px; padding: 15px; background: #fff8e1; 
            border-left: 4px solid #f39c12; border-radius: 4px; font-size: 13px; color: #555;
        }
        .footer { text-align: center; padding: 25px 20px; font-size: 12px; color: #999; line-height: 1.5; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <?php if (!empty($S['shop_logo_url'])): ?>
            <img src="<?= e($S['shop_logo_url']) ?>" alt="Logo" class="logo-img">
        <?php endif; ?>
        <h1><?= e($S['shop_name']) ?></h1>
        <p><?= e($S['shop_address']) ?></p>
        <p>☎ <?= e($S['shop_phone']) ?> <?= !empty($S['shop_email']) ? '| ✉ ' . e($S['shop_email']) : '' ?></p>
        <?php if (!empty($S['shop_tax_no'])): ?><p>Tax No: <?= e($S['shop_tax_no']) ?></p><?php endif; ?>
        
        <div class="badge">✓ Verified Authentic</div>
    </div>

    <div class="section grid-2">
        <div>
            <div class="label">Invoice Number</div>
            <div class="value">#<?= e($sale['invoice_no']) ?></div>
            
            <div class="label">Date & Time</div>
            <div class="value"><?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?></div>
            
            <div class="label">Served By</div>
            <div class="value" style="margin-bottom:0;"><?= e($sale['staff_name']) ?></div>
        </div>
        <div class="text-right">
            <div class="label">Billed To</div>
            <div class="value"><?= e($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
            
            <?php if ($sale['customer_phone']): ?>
            <div class="label">Phone</div>
            <div class="value"><?= e($sale['customer_phone']) ?></div>
            <?php endif; ?>
            
            <div class="label">Payment Method</div>
            <div class="value" style="margin-bottom:0; text-transform: capitalize;">
                <?= e($sale['payment_method']) ?>
            </div>
        </div>
    </div>

    <div class="section" style="padding-top: 10px; padding-bottom: 10px;">
        <div class="label" style="margin-bottom: 10px;">Order Items</div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <?php $variantInfo = trim(e($item['size']) . ' ' . e($item['color'])); ?>
                    <tr>
                        <td>
                            <div class="item-name"><?= e($item['product_name']) ?></div>
                            <?php if ($variantInfo): ?><div class="item-var"><?= $variantInfo ?></div><?php endif ?>
                            <div class="item-var" style="margin-top:2px;"><?= $cur . number_format($item['unit_price'], 2) ?> each</div>
                        </td>
                        <td class="text-center" style="font-weight:600;"><?= $item['qty'] ?></td>
                        <td class="text-right" style="font-weight:600;"><?= $cur . number_format($item['total_price'], 2) ?></td>
                    </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="totals">
        <div class="tot-row">
            <span>Subtotal</span>
            <span><?= $cur . number_format($sale['subtotal'], 2) ?></span>
        </div>
        
        <?php if ($sale['discount_amount'] > 0): ?>
        <div class="tot-row" style="color:#d63031;">
            <span>Discount <?= $sale['discount_pct'] > 0 ? '('.$sale['discount_pct'].'%)' : '' ?></span>
            <span>-<?= $cur . number_format($sale['discount_amount'], 2) ?></span>
        </div>
        <?php endif ?>
        
        <?php if ($sale['points_value'] > 0): ?>
        <div class="tot-row" style="color:#d63031;">
            <span>Points Redeemed (<?= $sale['points_used'] ?> pts)</span>
            <span>-<?= $cur . number_format($sale['points_value'], 2) ?></span>
        </div>
        <?php endif ?>
        
        <?php if ($sale['vat_amount'] > 0): ?>
        <div class="tot-row">
            <span>VAT <?= $sale['vat_rate'] > 0 ? '('.$sale['vat_rate'].'%)' : '' ?></span>
            <span>+<?= $cur . number_format($sale['vat_amount'], 2) ?></span>
        </div>
        <?php endif ?>
        
        <div class="tot-row grand">
            <span>Total Paid</span>
            <span><?= $cur . number_format($sale['total'], 2) ?></span>
        </div>

        <?php if ($sale['notes']): ?>
        <div class="notes-box">
            <strong style="display:block; margin-bottom:5px; color:#d35400;">Invoice Notes:</strong>
            <?= nl2br(e($sale['notes'])) ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <strong><?= e($S['invoice_footer'] ?? 'Thank you for your business!') ?></strong><br>
        <span style="display:inline-block; margin-top:8px; font-size:11px;">This is a digitally verified electronic receipt.<br>Powered by sohojweb.com.</span>
    </div>
</div>

</body>
</html>