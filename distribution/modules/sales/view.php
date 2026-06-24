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

// Fetch Truck Load Details
$truck_load = fetch_one("SELECT tl.* FROM truck_loads tl JOIN truck_load_items tli ON tl.id = tli.truck_load_id WHERE tli.invoice_id = ? AND tl.isDelete = 0", [$id]);

// QR Code URL — must be a full absolute URL so the QR scanner can open it
$proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
$verify_url = $proto . '://' . $host . BASE_URL . 'verify_invoice.php?id=' . $id;
$qr_url     = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . urlencode($verify_url);
?>

<style>
    /* Full Page A4 Design - High Density */
    @page { size: A4; margin: 10mm 10mm 20mm 10mm; }
    body { background-color: #fff !important; color: #000 !important; font-family: 'Segoe UI', Arial, sans-serif; }
    .invoice-wrap { width: 100%; margin: 0; padding: 0; border: none !important; }
    
    .print-table { width: 100%; border-collapse: collapse; }
    .print-table thead { 
        display: table-header-group; 
    }

    /* Modern 4-Column Header */
    .header-grid {
        display: grid;
        grid-template-columns: 80px 2fr 2fr 80px;
        gap: 15px;
        align-items: center;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
        margin-bottom: 10px;
    }
    .header-logo img { max-width: 80px; max-height: 80px; object-fit: contain; }
    .header-company h2 { margin: 0; font-size: 18px; font-weight: 800; color: #000 !important; }
    .header-company p { margin: 0; font-size: 10px; line-height: 1.3; color: #000 !important; }
    .header-invoice { border-left: 1px solid #000; padding-left: 15px; color: #000 !important; }
    .header-invoice h4 { margin: 0; font-size: 14px; font-weight: 800; text-transform: uppercase; color: #000 !important; }
    .header-invoice p { margin: 0; font-size: 10px; color: #000 !important; }
    .header-invoice .info-line { font-size: 10px; line-height: 1.4; color: #000 !important; }
    .header-qr img { width: 70px; height: 70px; display: block; margin-left: auto; }

    /* Billing Line */
    .bill-inline { font-size: 12px; border-bottom: 1px solid #000; padding-bottom: 8px; margin-bottom: 10px; color: #000 !important; }
    .bill-inline i { margin-right: 5px; }

    /* Product Grid */
    .item-table { width: 100%; border-collapse: collapse; }
    .item-table th { background: #000 !important; color: #fff !important; padding: 6px 8px; font-size: 11px; text-transform: uppercase; text-align: left; border: 1px solid #000; }
    .item-table td { padding: 5px 8px; border: 1px solid #000; font-size: 11px; vertical-align: top; color: #000 !important; }
    
    .free-row td { color: #000 !important; font-style: italic; }
    .free-badge { font-size: 9px; background: #eee; padding: 1px 4px; border-radius: 3px; font-weight: bold; color: #000; margin-right: 5px; border: 1px solid #000; }

    /* Summary & Signatures */
    .footer-layout { display: flex; justify-content: space-between; margin-top: 15px; color: #000 !important; }
    .words-section { width: 65%; font-size: 11px; color: #000 !important; }
    .totals-section { width: 30%; }
    
    .total-row { display: flex; justify-content: space-between; font-size: 12px; padding: 2px 0; color: #000 !important; }
    .grand-total-row { border-top: 2px solid #000; font-weight: 900; font-size: 14px; margin-top: 5px; padding-top: 5px; color: #000 !important; }

    .sig-row { display: flex; justify-content: space-between; margin-top: 50px; }
    .sig-col { width: 22%; text-align: center; border-top: 1px solid #000; font-size: 9px; padding-top: 5px; text-transform: uppercase; color: #000 !important; }

    @media print {
        * { 
            color: #000 !important; 
            background: transparent !important;
            box-shadow: none !important;
            text-shadow: none !important;
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important; 
        }
        .text-muted, .text-success, .text-danger, .text-primary, .text-info, .text-warning { color: #000 !important; }
        
        html, body, #wrapper, #page-content-wrapper, .container-fluid, .invoice-wrap, .print-table, tr, td, th { 
            background: #fff !important; 
            background-color: #fff !important; 
        }

        body { counter-reset: page; }

        #sidebar-wrapper, .navbar, .btn, .no-print, .alert { display: none !important; }
        #page-content-wrapper { padding: 0 !important; width: 100% !important; margin: 0 !important; }
        .container-fluid { padding: 0 !important; }
        .header-company h2 { color: #000 !important; }

        .print-table thead { 
            display: table-header-group; 
        }
        
        /* New Fixed Footer Logic */
        .fixed-page-footer {
            display: none;
            position: fixed;
            bottom: 5mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            color: #fff !important;
        }

        /* Show page numbers ONLY if body has .is-multipage class */
        body.is-multipage .fixed-page-footer {
            display: block;
        }

        .fixed-page-footer:after {
            counter-increment: page;
            content: "Page " counter(page);
        }
    }
</style>

<script>
    function checkMultipage() {
        const wrap = document.querySelector('.invoice-wrap');
        // Threshold for A4 multi-page (approx 255mm)
        // 1mm = 3.78px roughly at 96dpi
        const threshold = 960; 
        if (wrap.offsetHeight > threshold) {
            document.body.classList.add('is-multipage');
        } else {
            document.body.classList.remove('is-multipage');
        }
    }
    
    // Check on load and before printing
    window.addEventListener('load', checkMultipage);
    window.addEventListener('beforeprint', checkMultipage);
</script>

<div class="row no-print mb-4">
    <div class="col-md-6">
        <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
    <div class="col-md-6 text-end">
        <?php if (in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_ACCOUNTANT])): ?>
            <button type="button" class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#adminModal">
                <i class="fas fa-user-shield me-1"></i> Accountant Tools
            </button>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-dark"><i class="fas fa-print me-1"></i> Print Invoice (A4)</button>
    </div>
</div>

<?php if (in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_ACCOUNTANT])): ?>
<!-- Admin/Accountant Modal -->
<div class="modal fade no-print" id="adminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Administrative Tools</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_admin_fields.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Delivery Date</label>
                        <input type="date" name="delivery_date" class="form-control" value="<?php echo $sale['delivery_date']; ?>" <?php echo ($sale['delivery_status'] != 'Pending') ? 'readonly' : ''; ?>>
                        <?php if($sale['delivery_status'] != 'Pending'): ?>
                            <small class="text-muted">Date can only be changed while status is PENDING.</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="hide_from_print" id="hidePrint" <?php echo $sale['hide_from_print'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="hidePrint">Hide from Print/Ledger</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Delivery Status</label>
                        <select name="delivery_status" class="form-select">
                            <option value="Pending" <?php if($sale['delivery_status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                            <option value="Loading" <?php if($sale['delivery_status'] == 'Loading') echo 'selected'; ?>>Loading</option>
                            <option value="In Transit" <?php if($sale['delivery_status'] == 'In Transit') echo 'selected'; ?>>In Transit</option>
                            <option value="Delivered" <?php if($sale['delivery_status'] == 'Delivered') echo 'selected'; ?>>Delivered</option>
                            <option value="Failed" <?php if($sale['delivery_status'] == 'Failed') echo 'selected'; ?>>Failed</option>
                            <option value="Returned" <?php if($sale['delivery_status'] == 'Returned') echo 'selected'; ?>>Returned</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($sale['hide_from_print']): ?>
    <div class="alert alert-warning no-print border-warning">
        <i class="fas fa-eye-slash me-2"></i> This invoice is marked as <strong>HIDDEN FROM PRINT</strong> and will not appear in the ledger.
    </div>
<?php endif; ?>

<div class="invoice-wrap">
    <table class="print-table">
        <thead>
            <tr>
                <td colspan="5" style="border:none; padding:0;">
                    <!-- 4-Column Header -->
                    <div class="header-grid">
                        <div class="header-logo">
                            <?php if ($company['logo_url']): ?>
                                <?php 
                                    $logo_path = $company['logo_url'];
                                    if (!filter_var($logo_path, FILTER_VALIDATE_URL) && strpos($logo_path, 'data:') !== 0) {
                                        $logo_path = BASE_URL . ltrim($logo_path, '/');
                                    }
                                ?>
                                <img src="<?php echo $logo_path; ?>" alt="Logo">
                            <?php endif; ?>
                        </div>
                        <div class="header-company">
                            <h2 class="text-primary"><?php echo $company['name']; ?></h2>
                            <p><?php echo $company['address']; ?></p>
                            <p>Phone: <?php echo $company['phone']; ?> | Email: <?php echo $company['email']; ?></p>
                        </div>
                        <div class="header-invoice">
                            <div class="info-line">
                                <strong>INV NO:</strong> #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?> |
                                <strong>DATE:</strong> <?php echo date('d-m-Y', strtotime($sale['created_at'])); ?> |
                                <strong>STATUS:</strong> <?php echo strtoupper($sale['status']); ?> |
                                <strong>TYPE:</strong> <?php echo strtoupper($sale['order_type'] ?? 'LOCAL'); ?>
                            </div>
                            <div class="info-line">
                                <strong>DELIVERY STATUS:</strong> <?php echo strtoupper($sale['delivery_status']); ?> | 
                                <strong>DATE:</strong> <?php echo $sale['delivery_date'] ? date('d-m-Y', strtotime($sale['delivery_date'])) : 'PENDING'; ?>
                            </div>
                            <div class="info-line">
                                <strong>TRUCK:</strong> <?php echo $truck_load['truck_no'] ?? 'N/A'; ?> | 
                                <strong>DRIVER:</strong> <?php echo $truck_load['driver_name'] ?? 'N/A'; ?>
                            </div>
                        </div>
                        <div class="header-qr">
                            <img src="<?php echo $qr_url; ?>" alt="Verification QR">
                        </div>
                    </div>

                    <div class="bill-inline text-center fw-bold">
                        <i class="fas fa-user"></i> <?php echo $sale['customer_name']; ?> &nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp; 
                        <i class="fas fa-phone"></i> <?php echo $sale['customer_phone']; ?> &nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp; 
                        <i class="fas fa-map-marker-alt"></i> <?php echo $sale['customer_address']; ?>
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
                            <strong><?php echo $item['product_name']; ?></strong>
                            <br>
                            <?php if ($item['note']): ?><small class="text-muted"><?php echo $item['note']; ?></small> &nbsp; <?php endif; ?>
                            <small class="text-success fw-bold">FREE ITEM</small>
                        </td>
                        <td class="text-center"><?php echo $item['free_qty']; ?></td>
                        <td class="text-end"><?php echo number_format($item['rate'], 2); ?></td>
                        <td class="text-end fw-bold">0.00</td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
        <tbody class="summary-section">
            <tr>
                <td colspan="5" style="border:none; padding:0;">
                    <div class="footer-layout">
                        <div class="words-section">
                            <strong>Amount in Words:</strong><br>
                            <?php echo number_to_words($sale['grand_total']); ?>

                            <?php if (!empty($sale['general_note'])): ?>
                                <div class="mt-2">
                                    <strong>Note:</strong> <?php echo nl2br(htmlspecialchars($sale['general_note'])); ?>
                                </div>
                            <?php endif; ?>
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
        </tbody>
    </table>
    <!-- Fixed Page Numbering Footer -->
    <div class="fixed-page-footer"></div>
</div>

<?php require_once '../../templates/footer.php'; ?>
