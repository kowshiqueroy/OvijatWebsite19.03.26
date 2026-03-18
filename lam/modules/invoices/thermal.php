<?php
// ============================================================
// modules/invoices/thermal.php — 80mm Thermal Receipt
// ============================================================
$id   = (int)($_GET['id'] ?? 0);
$sale = dbFetch(
    "SELECT s.*, u.full_name AS staff_name, c.name AS customer_name, c.phone AS customer_phone 
     FROM sales s JOIN users u ON u.id = s.user_id
     LEFT JOIN customers c ON c.id = s.customer_id WHERE s.id = ?",
    [$id]
);
if (!$sale) { die('Invoice not found.'); }

$items  = dbFetchAll('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id', [$id]);
$S      = getAllSettings();
$cur    = $S['currency_symbol'] ?? '$';
$qrData = BASE_URL . '/inv.php?id=' . $id;
$qrUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($qrData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt <?= e($sale['invoice_no']) ?></title>
    <style>
        /* Base styles for screen preview */
        body { background: #333; font-family: 'Courier New', Courier, monospace; margin: 0; padding: 20px; color: #000; }
        .btn-print { display: block; width: 300px; margin: 0 auto 20px auto; padding: 15px; background: #6c5ce7; color: #fff; text-align: center; text-decoration: none; font-family: sans-serif; font-weight: bold; border-radius: 5px; cursor: pointer; border: none; }
        
        /* Thermal Receipt Wrapper */
        .receipt-wrapper { background: #fff; width: 80mm; margin: 0 auto; padding: 5mm; box-sizing: border-box; font-size: 12px; line-height: 1.4; }
        
        /* Typography & Layout */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 5px 0; }
        .cut-line { border-top: 1px dashed #000; margin: 15px 0; position: relative; }
        .cut-line::after { content: '✂ CUT HERE'; position: absolute; top: -8px; left: 50%; transform: translateX(-50%); background: #fff; padding: 0 5px; font-size: 10px; color: #666; }
        
        /* Header elements */
        .shop-logo { max-width: 150px; margin: 0 auto 5px; display: block; }
        .shop-name { font-size: 18px; font-weight: bold; margin-bottom: 2px; }
        .shop-info { font-size: 11px; margin-bottom: 5px; }
        
        /* Tables */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; border-bottom: 1px solid #000; padding-bottom: 3px; font-size: 11px; }
        td { vertical-align: top; padding: 3px 0; font-size: 12px; }
        
        .item-name { font-weight: bold; display: block; }
        .item-meta { font-size: 10px; color: #444; }
        
        .totals-grid { display: flex; justify-content: space-between; font-size: 12px; margin-top: 2px; }
        .grand-total { font-size: 16px; font-weight: bold; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 5px 0; margin-top: 5px; }
        
        .qr-code { width: 80px; height: 80px; display: block; margin: 10px auto; }

        @media print {
            body { background: #fff; padding: 0; }
            .btn-print { display: none; }
            .receipt-wrapper { width: 100%; padding: 0; margin: 0; }
            @page { margin: 0; width: 80mm; }
        }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">🖨️ Print Thermal Receipt</button>

<div class="receipt-wrapper">
    <div class="text-center">
        <?php if (!empty($S['shop_logo_url'])): ?>
            <img src="<?= e($S['shop_logo_url']) ?>" class="shop-logo" alt="Logo">
        <?php endif; ?>
        <div class="shop-name"><?= e($S['shop_name']) ?></div>
        <div class="shop-info">
            <?= e($S['shop_address']) ?><br>
            Tel: <?= e($S['shop_phone']) ?><br>
            <?= !empty($S['shop_tax_no']) ? 'Tax No: '.e($S['shop_tax_no']) : '' ?>
        </div>
    </div>
    
    <div class="divider"></div>
    
    <div>
        Inv: <span class="bold"><?= e($sale['invoice_no']) ?></span><br>
        Date: <?= date('d M Y h:i A', strtotime($sale['created_at'])) ?><br>
        Cashier: <?= e($sale['staff_name']) ?><br>
        Customer: <?= e($sale['customer_name'] ?? 'Walk-in') ?> <?= $sale['customer_phone'] ? '('.e($sale['customer_phone']).')' : '' ?>
    </div>
    
    <div class="divider"></div>
    
    <table>
        <thead>
            <tr>
                <th width="50%">Item</th>
                <th width="15%" class="text-center">Qty</th>
                <th width="35%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <?php $varInfo = trim(e($item['size']).' '.e($item['color'])); ?>
            <tr>
                <td>
                    <span class="item-name"><?= e($item['product_name']) ?></span>
                    <?php if($varInfo): ?><span class="item-meta"><?= $varInfo ?></span><br><?php endif; ?>
                    <span class="item-meta">@ <?= number_format($item['unit_price'],2) ?></span>
                </td>
                <td class="text-center"><?= $item['qty'] ?></td>
                <td class="text-right"><?= number_format($item['total_price'],2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="divider"></div>
    
    <div class="totals-grid"><span>Subtotal:</span><span><?= number_format($sale['subtotal'],2) ?></span></div>
    <?php if ($sale['discount_amount'] > 0): ?>
        <div class="totals-grid"><span>Discount:</span><span>-<?= number_format($sale['discount_amount'],2) ?></span></div>
    <?php endif; ?>
    <?php if ($sale['points_value'] > 0): ?>
        <div class="totals-grid"><span>Points Used:</span><span>-<?= number_format($sale['points_value'],2) ?></span></div>
    <?php endif; ?>
    <?php if ($sale['vat_amount'] > 0): ?>
        <div class="totals-grid"><span>VAT (<?= $sale['vat_rate'] ?>%):</span><span>+<?= number_format($sale['vat_amount'],2) ?></span></div>
    <?php endif; ?>
    
    <div class="totals-grid grand-total">
        <span>TOTAL <?= $cur ?>:</span>
        <span><?= number_format($sale['total'],2) ?></span>
    </div>
    
    <div class="totals-grid">
        <span>Payment Method:</span>
        <span class="bold"><?= strtoupper(e($sale['payment_method'])) ?></span>
    </div>
    
    <?php if ($sale['notes']): ?>
        <div class="divider"></div>
        <div style="font-size:11px;">Note: <?= e($sale['notes']) ?></div>
    <?php endif; ?>

    <div class="text-center">
        <img src="<?= $qrUrl ?>" class="qr-code" alt="QR">
        <div style="font-size:11px; margin-bottom: 5px;"><?= e($S['invoice_footer'] ?? 'Thank you for your purchase!') ?> <br>Powered by sohojweb.com</div>
    </div>
    
    <div class="cut-line"></div>
    
    <div class="text-center bold" style="font-size: 14px;">--- SHOP COPY ---</div>
    <div class="divider"></div>
    
    <div>
        Inv: <span class="bold"><?= e($sale['invoice_no']) ?></span><br>
        Date: <?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?><br>
        Cust: <?= e($sale['customer_name'] ?? 'Walk-in') ?>
    </div>
    
    <div class="divider"></div>
    
    <table style="font-size: 11px;">
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?= $item['qty'] ?>x <?= e($item['product_name']) ?></td>
            <td class="text-right"><?= number_format($item['total_price'],2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <div class="totals-grid grand-total" style="font-size: 14px;">
        <span>TOTAL:</span>
        <span><?= $cur ?><?= number_format($sale['total'],2) ?></span>
    </div>
    <div class="text-center" style="margin-top:5px; font-weight:bold;">
        PAYMENT: <?= strtoupper(e($sale['payment_method'])) ?>
    </div>
    
    <div style="height: 40px;"></div> </div>

<script>
    // Auto-trigger print dialog when opening this specific link
    window.onload = function() { window.print(); }
</script>
</body>
</html>