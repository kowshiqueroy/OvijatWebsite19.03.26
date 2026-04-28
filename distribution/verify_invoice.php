<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$id = $_GET['id'] ?? '';
$sale = null;
$items = [];
$company = get_company_settings();

if ($id) {
    $sale = fetch_one("SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address, 
                       u.username as creator_name 
                       FROM sales_drafts s 
                       JOIN customers c ON s.customer_id = c.id 
                       JOIN users u ON s.created_by = u.id 
                       WHERE s.id = ? AND s.isDelete = 0", [$id]);

    if ($sale) {
        $items = fetch_all("SELECT i.*, p.name as product_name FROM sales_items i JOIN products p ON i.product_id = p.id WHERE i.draft_id = ? AND i.isDelete = 0", [$id]);
    }
}

// QR Code for the verification page itself (Self-referencing)
$verify_url = BASE_URL . "verify_invoice.php?id=" . $id;
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verify_url);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Verification - <?php echo $company['name']; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Arial, sans-serif; color: #333; }
        .no-print-area { margin-bottom: 20px; }
        .verify-container { max-width: 900px; margin: 30px auto; }
        
        /* A4 Print Styling (Mirroring view.php) */
        @page { size: A4; margin: 8mm; }
        
        .invoice-wrap { 
            background: #fff; 
            padding: 0; 
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-radius: 10px;
            overflow: hidden;
        }

        .print-table { width: 100%; border-collapse: collapse; }
        .print-table thead { display: table-header-group; }

        .header-grid {
            display: grid;
            grid-template-columns: 80px 2.5fr 1.5fr 80px;
            gap: 15px;
            align-items: center;
            border-bottom: 2px solid #000;
            padding: 20px;
        }
        .header-logo img { max-width: 80px; max-height: 80px; }
        .header-company h2 { margin: 0; font-size: 18px; font-weight: 800; color: #0d6efd; }
        .header-company p { margin: 0; font-size: 10px; line-height: 1.3; }
        .header-invoice { border-left: 1px solid #ddd; padding-left: 15px; }
        .header-invoice h4 { margin: 0; font-size: 14px; font-weight: 800; text-transform: uppercase; }
        .header-qr img { width: 70px; height: 70px; display: block; margin-left: auto; }

        .bill-inline { font-size: 11px; border-bottom: 1px solid #eee; padding: 10px 20px; background: #fafafa; }

        .item-table-wrap { padding: 20px; }
        .item-table { width: 100%; border-collapse: collapse; }
        .item-table th { background: #000 !important; color: #fff !important; padding: 8px; font-size: 11px; text-transform: uppercase; text-align: left; border: 1px solid #000; }
        .item-table td { padding: 6px 8px; border: 1px solid #ddd; font-size: 11px; }
        
        .footer-layout { display: flex; justify-content: space-between; padding: 20px; margin-top: 10px; }
        .words-section { width: 60%; font-size: 11px; }
        .totals-section { width: 35%; }
        .total-row { display: flex; justify-content: space-between; font-size: 12px; padding: 2px 0; }
        .grand-total-row { border-top: 2px solid #000; font-weight: 900; font-size: 14px; margin-top: 5px; padding-top: 5px; }

        .sig-row { display: flex; justify-content: space-between; margin-top: 50px; padding: 0 20px 40px 20px; }
        .sig-col { width: 22%; text-align: center; border-top: 1px solid #000; font-size: 9px; padding-top: 5px; text-transform: uppercase; }

        .status-ribbon {
            width: 100%;
            text-align: center;
            padding: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #fff;
        }
        .bg-confirmed { background: #198754; }
        .bg-draft { background: #ffc107; color: #000; }

        @media print {
            body { background: #fff !important; }
            .no-print, .btn, .public-navbar, .search-box-wrap { display: none !important; }
            .verify-container { margin: 0; width: 100%; max-width: 100%; }
            .invoice-wrap { box-shadow: none !important; border: none !important; border-radius: 0; }
            .header-company h2 { color: #0d6efd !important; }
        }
    </style>
</head>
<body>

<nav class="public-navbar no-print">
    <div class="container d-flex justify-content-between align-items-center py-3">
        <a href="index.php" class="text-decoration-none d-flex align-items-center">
            <?php if ($company['logo_url']): ?>
                <img src="<?php echo $company['logo_url']; ?>" height="30" class="me-2">
            <?php endif; ?>
            <span class="fw-bold text-dark"><?php echo $company['name']; ?></span>
        </a>
        <div>
            <button onclick="window.print()" class="btn btn-dark btn-sm rounded-pill px-4 me-2"><i class="fas fa-print me-2"></i>Print A4</button>
            <a href="login.php" class="btn btn-outline-primary btn-sm rounded-pill px-4">Staff Login</a>
        </div>
    </div>
</nav>

<div class="container verify-container">
    
    <div class="search-box-wrap no-print row justify-content-center mb-4">
        <div class="col-md-6">
            <form method="GET" class="input-group">
                <input type="text" name="id" class="form-control rounded-start-pill ps-4" placeholder="Enter Invoice ID..." value="<?php echo $id; ?>">
                <button class="btn btn-primary rounded-end-pill px-4" type="submit">Verify</button>
            </form>
        </div>
    </div>

    <?php if ($id && $sale): ?>
        <div class="invoice-wrap">
            <div class="status-ribbon no-print <?php echo $sale['status'] == 'Confirmed' ? 'bg-confirmed' : 'bg-draft'; ?>">
                <i class="fas fa-shield-alt me-2"></i> Authentic <?php echo $sale['status']; ?> Invoice
            </div>

            <table class="print-table">
                <thead>
                    <tr>
                        <td style="border:none; padding:0;">
                            <!-- Header Grid -->
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
                                    <img src="<?php echo $qr_url; ?>" alt="QR">
                                </div>
                            </div>

                            <div class="bill-inline">
                                <strong>BILL TO:</strong> <?php echo $sale['customer_name']; ?> | 
                                <strong>PH:</strong> <?php echo $sale['customer_phone']; ?> | 
                                <strong>ADDR:</strong> <?php echo $sale['customer_address']; ?>
                            </div>
                        </td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border:none; padding:0;">
                            <div class="item-table-wrap">
                                <table class="item-table">
                                    <thead>
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
                                                <td><strong><?php echo $item['product_name']; ?></strong><?php echo $item['note'] ? " - ".$item['note'] : ""; ?></td>
                                                <td class="text-center"><?php echo $item['billed_qty']; ?></td>
                                                <td class="text-end"><?php echo number_format($item['rate'], 2); ?></td>
                                                <td class="text-end fw-bold"><?php echo number_format($item['total'], 2); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if ($item['free_qty'] > 0): ?>
                                            <tr style="color: #198754;">
                                                <td><?php echo $i++; ?></td>
                                                <td><small class="badge bg-light text-dark border me-1">FREE</small> <?php echo $item['product_name']; ?></td>
                                                <td class="text-center"><?php echo $item['free_qty']; ?></td>
                                                <td class="text-end"><?php echo number_format($item['rate'], 2); ?></td>
                                                <td class="text-end fw-bold">0.00</td>
                                            </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td style="border:none; padding:0;">
                            <div class="footer-layout">
                                <div class="words-section">
                                    <strong>Amount in Words:</strong><br>
                                    <?php echo number_to_words($sale['grand_total']); ?>
                                    <div class="mt-3 no-print">
                                        <div class="alert alert-success py-2 small d-inline-block">
                                            <i class="fas fa-check-circle me-1"></i> Digitally Verified Invoice
                                        </div>
                                    </div>
                                </div>
                                <div class="totals-section">
                                    <div class="total-row"><span>Sub Total</span><span><?php echo number_format($sale['total_amount'], 2); ?></span></div>
                                    <?php if ($sale['discount'] > 0): ?>
                                        <div class="total-row text-danger"><span>Discount</span><span>-<?php echo number_format($sale['discount'], 2); ?></span></div>
                                    <?php endif; ?>
                                    <div class="total-row grand-total-row"><span>NET TOTAL</span><span>৳ <?php echo number_format($sale['grand_total'], 2); ?></span></div>
                                </div>
                            </div>

                            <div class="sig-row">
                                <div class="sig-col">Prepared By</div>
                                <div class="sig-col">Warehouse Out</div>
                                <div class="sig-col">Customer Received</div>
                                <div class="sig-col">Authorized Authority</div>
                            </div>

                            <div class="text-center py-4 small text-muted border-top mx-4">
                                Powered by <strong>sohojweb</strong>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

    <?php elseif ($id): ?>
        <div class="alert alert-danger text-center p-5 shadow-sm rounded-4">
            <i class="fas fa-search fa-3x mb-3 opacity-50"></i>
            <h3>Verification Failed</h3>
            <p>We couldn't find an invoice with ID <strong>#<?php echo htmlspecialchars($id); ?></strong>.</p>
            <a href="verify_invoice.php" class="btn btn-primary rounded-pill px-5 mt-2">Try Again</a>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-fingerprint fa-4x text-primary opacity-25 mb-3"></i>
            <h4 class="fw-bold">Public Verification Portal</h4>
            <p class="text-muted">Type an Invoice ID above to confirm its authenticity.</p>
        </div>
    <?php endif; ?>

    <div class="text-center mt-5 mb-5 text-muted no-print" style="font-size: 11px;">
        &copy; <?php echo date('Y'); ?> <?php echo $company['name']; ?>. All rights reserved.<br>
        This portal is provided for public document verification.
    </div>
</div>

</body>
</html>
