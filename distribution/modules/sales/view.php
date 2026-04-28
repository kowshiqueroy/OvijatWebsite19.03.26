<?php
require_once '../../templates/header.php';
check_login();

$id = $_GET['id'] ?? 0;
$sale = fetch_one("SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address, 
                   u.username as creator_name, conf.username as confirmer_name 
                   FROM sales_drafts s 
                   JOIN customers c ON s.customer_id = c.id 
                   JOIN users u ON s.created_by = u.id 
                   LEFT JOIN users conf ON s.confirmed_by = conf.id
                   WHERE s.id = ? AND s.isDelete = 0 AND c.isDelete = 0", [$id]);

if (!$sale) redirect('modules/sales/index.php', 'Sale not found.', 'danger');

$items = fetch_all("SELECT i.*, p.name as product_name FROM sales_items i JOIN products p ON i.product_id = p.id WHERE i.draft_id = ? AND i.isDelete = 0 AND p.isDelete = 0", [$id]);

// QR Code URL (Verify Invoice)
$verify_url = BASE_URL . "verify_invoice.php?id=" . $id;
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verify_url);
?>

<style>
    /* Full Page A4 Design - High Density */
    @page { size: A4; margin: 8mm; }
    body { background-color: #fff !important; color: #000 !important; font-family: 'Segoe UI', Arial, sans-serif; }
    .invoice-wrap { width: 100%; margin: 0; padding: 0; border: none !important; }
    
    .print-table { width: 100%; border-collapse: collapse; }
    .print-table thead { display: table-header-group; }

    /* Modern 4-Column Header */
    .header-grid {
        display: grid;
        grid-template-columns: 80px 2.5fr 1.5fr 80px;
        gap: 15px;
        align-items: center;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
        margin-bottom: 10px;
    }
    .header-logo img { max-width: 80px; max-height: 80px; object-fit: contain; }
    .header-company h2 { margin: 0; font-size: 18px; font-weight: 800; }
    .header-company p { margin: 0; font-size: 10px; line-height: 1.3; }
    .header-invoice { border-left: 1px solid #ddd; padding-left: 15px; }
    .header-invoice h4 { margin: 0; font-size: 14px; font-weight: 800; text-transform: uppercase; }
    .header-invoice p { margin: 0; font-size: 10px; }
    .header-qr img { width: 70px; height: 70px; display: block; margin-left: auto; }

    /* Billing Line */
    .bill-inline { font-size: 11px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px; }

    /* Product Grid */
    .item-table { width: 100%; border-collapse: collapse; }
    .item-table th { background: #000 !important; color: #fff !important; padding: 6px 8px; font-size: 11px; text-transform: uppercase; text-align: left; border: 1px solid #000; }
    .item-table td { padding: 5px 8px; border: 1px solid #ddd; font-size: 11px; vertical-align: top; }
    
    .free-row td { color: #198754; }
    .free-badge { font-size: 9px; background: #e9ecef; padding: 1px 4px; border-radius: 3px; font-weight: bold; color: #000; margin-right: 5px; }

    /* Summary & Signatures */
    .footer-layout { display: flex; justify-content: space-between; margin-top: 15px; }
    .words-section { width: 65%; font-size: 11px; }
    .totals-section { width: 30%; }
    
    .total-row { display: flex; justify-content: space-between; font-size: 12px; padding: 2px 0; }
    .grand-total-row { border-top: 2px solid #000; font-weight: 900; font-size: 14px; margin-top: 5px; padding-top: 5px; }

    .sig-row { display: flex; justify-content: space-between; margin-top: 50px; }
    .sig-col { width: 22%; text-align: center; border-top: 1px solid #000; font-size: 9px; padding-top: 5px; text-transform: uppercase; }

    @media print {
        #sidebar-wrapper, .navbar, .btn, .no-print, .alert { display: none !important; }
        #page-content-wrapper { padding: 0 !important; width: 100% !important; }
        .container-fluid { padding: 0 !important; }
        .header-company h2 { color: #0d6efd !important; }
    }
</style>

<div class="no-print mb-3 text-end">
    <button onclick="window.print()" class="btn btn-dark shadow-sm"><i class="fas fa-print me-1"></i> Print Invoice (A4)</button>
    <a href="index.php" class="btn btn-outline-secondary shadow-sm">Back</a>
</div>

<div class="invoice-wrap">
    <table class="print-table">
        <thead>
            <tr>
                <td colspan="5" style="border:none; padding:0;">
                    <!-- 4-Column Header -->
                    <div class="header-grid">
                        <div class="header-logo">
                            <?php if ($company['logo_url']): ?>
                                <img src="<?php echo $company['logo_url']; ?>" alt="Logo">
                            <?php endif; ?>
                        </div>
                        <div class="header-company">
                            <h2 class="text-primary"><?php echo $company['name']; ?></h2>
                            <p><?php echo $company['address']; ?></p>
                            <p>Phone: <?php echo $company['phone']; ?> | Email: <?php echo $company['email']; ?></p>
                        </div>
                        <div class="header-invoice">
                            <h4>Invoice</h4>
                            <p><strong>No:</strong> #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></p>
                            <p><strong>Date:</strong> <?php echo date('d-m-Y', strtotime($sale['created_at'])); ?></p>
                            <p><strong>Status:</strong> <?php echo strtoupper($sale['status']); ?></p>
                        </div>
                        <div class="header-qr">
                            <img src="<?php echo $qr_url; ?>" alt="Verification QR">
                        </div>
                    </div>

                    <div class="bill-inline">
                        <strong>BILL TO:</strong> <?php echo $sale['customer_name']; ?> | 
                        <strong>PH:</strong> <?php echo $sale['customer_phone']; ?> | 
                        <strong>ADDR:</strong> <?php echo $sale['customer_address']; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 55%;">Product Description</th>
                <th style="width: 10%; text-align: center;">Qty</th>
                <th style="width: 15%; text-align: right;">Rate</th>
                <th style="width: 15%; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            foreach ($items as $item): ?>
                <?php if ($item['billed_qty'] > 0): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td>
                        <strong><?php echo $item['product_name']; ?></strong>
                        <?php if ($item['note']): ?><br><small class="text-muted"><?php echo $item['note']; ?></small><?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo $item['billed_qty']; ?></td>
                    <td class="text-end"><?php echo number_format($item['rate'], 2); ?></td>
                    <td class="text-end fw-bold"><?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php endif; ?>

                <?php if ($item['free_qty'] > 0): ?>
                    <tr class="free-row">
                        <td><?php echo $i++; ?></td>
                        <td>
                            <span class="free-badge">FREE</span>
                            <strong><?php echo $item['product_name']; ?></strong>
                            <?php if ($item['note']): ?><br><small class="text-muted"><?php echo $item['note']; ?></small><?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $item['free_qty']; ?></td>
                        <td class="text-end"><?php echo number_format($item['rate'], 2); ?></td>
                        <td class="text-end fw-bold">0.00</td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="border:none; padding:0;">
                    <div class="footer-layout">
                        <div class="words-section">
                            <strong>Amount in Words:</strong><br>
                            <?php echo number_to_words($sale['grand_total']); ?>
                        </div>
                        <div class="totals-section">
                            <div class="total-row">
                                <span>Sub Total</span>
                                <span><?php echo number_format($sale['total_amount'], 2); ?></span>
                            </div>
                            <?php if ($sale['discount'] > 0): ?>
                            <div class="total-row text-danger">
                                <span>Discount</span>
                                <span>-<?php echo number_format($sale['discount'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($sale['vat'] > 0): ?>
                            <div class="total-row">
                                <span>VAT (<?php echo $sale['vat']; ?>%)</span>
                                <span><?php echo number_format(($sale['total_amount'] - $sale['discount']) * $sale['vat'] / 100, 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="total-row grand-total-row">
                                <span>NET TOTAL</span>
                                <span>৳ <?php echo number_format($sale['grand_total'], 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="sig-row">
                        <div class="sig-col">Prepared By (<?php echo $sale['creator_name']; ?>)</div>
                        <div class="sig-col">Warehouse Out</div>
                        <div class="sig-col">Customer Received</div>
                        <div class="sig-col">Authorized Authority</div>
                    </div>

                    <div class="mt-4 text-center" style="font-size: 9px; color: #888;">
                        Verification: Scan QR on top right to verify this invoice online. | Powered by <strong>sohojweb</strong>
                    </div>
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>
