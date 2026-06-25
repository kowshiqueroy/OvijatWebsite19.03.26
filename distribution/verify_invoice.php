<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$id = $_GET['id'] ?? '';
$sale = null;
$items = [];
$truck_load = null;
$company = get_company_settings();

if ($id) {
    $sale = fetch_one("SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address, 
                       u.username as creator_name 
                       FROM sales_drafts s 
                       JOIN customers c ON s.customer_id = c.id 
                       JOIN users u ON s.created_by = u.id 
                       WHERE s.id = ? AND s.isDelete = 0", [$id]);

    if ($sale) {
        $items = fetch_all("SELECT i.*, p.name as product_name FROM sales_items i JOIN products p ON i.product_id = p.id WHERE i.draft_id = ? AND i.isDelete = 0 AND p.isDelete = 0", [$id]);
        $truck_load = fetch_one("SELECT tl.* FROM truck_loads tl JOIN truck_load_items tli ON tl.id = tli.truck_load_id WHERE tli.invoice_id = ? AND tl.isDelete = 0", [$id]);
    }
}

// QR Code for the verification page itself (Self-referencing)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$verify_url = $protocol . $host . BASE_URL . "verify_invoice.php?id=" . $id;
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verify_url);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Verification - <?php echo htmlspecialchars($company['name']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Google Font for Premium Aesthetic */
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --success: #10b981;
            --success-light: #ecfdf5;
            --dark: #0f172a;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-600: #475569;
            --gray-700: #334155;
            --card-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.05);
            --border-radius: 12px;
        }

        body {
            background-color: #f3f4f6;
            background-image: radial-gradient(at 0% 0%, rgba(243, 244, 246, 1) 0, transparent 50%), 
                              radial-gradient(at 50% 0%, rgba(239, 246, 255, 1) 0, transparent 50%);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            color: var(--gray-700);
            min-height: 100vh;
        }

        /* Beautiful Navigation Bar */
        .public-navbar {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--gray-200);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-brand span {
            letter-spacing: -0.5px;
        }

        /* Container limits */
        .verify-container {
            max-width: 900px;
            margin: 40px auto;
        }

        /* Modern Verification Card */
        .search-card {
            background: #fff;
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .search-card .form-control {
            border: 1px solid var(--gray-200);
            padding: 12px 20px;
            font-size: 15px;
            border-radius: 30px;
            transition: all 0.3s ease;
        }
        .search-card .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
        }
        .search-card .btn-primary {
            background: linear-gradient(135deg, var(--primary), #3b82f6);
            border: none;
            font-weight: 600;
            border-radius: 30px;
            padding: 12px 30px;
            transition: all 0.3s ease;
        }
        .search-card .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -6px rgba(79, 70, 229, 0.4);
        }

        /* Verification Status Banner */
        .status-banner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 14px;
            color: #fff;
            background: linear-gradient(135deg, #059669, #10b981);
            box-shadow: inset 0 -2px 10px rgba(0,0,0,0.05);
        }
        .status-banner.status-draft {
            background: linear-gradient(135deg, #d97706, #f59e0b);
            color: #fff;
        }

        /* Authentic Document Style */
        .invoice-card {
            background: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(226, 232, 240, 0.8);
            overflow: hidden;
            position: relative;
        }

        .invoice-body {
            padding: 40px;
        }

        /* Grid Layout for Header */
        .invoice-header-grid {
            display: grid;
            grid-template-columns: 80px 2fr 1.2fr 80px;
            gap: 24px;
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: 24px;
            margin-bottom: 24px;
            align-items: center;
        }
        .company-details h2 {
            font-size: 20px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 6px;
        }
        .company-details p {
            font-size: 12px;
            color: var(--gray-600);
            margin: 0;
            line-height: 1.5;
        }
        .invoice-meta {
            text-align: left;
            border-left: 1px solid var(--gray-200);
            padding-left: 20px;
        }
        .invoice-meta h3 {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .invoice-meta p {
            font-size: 13px;
            margin: 0;
            line-height: 1.6;
            color: var(--gray-700);
        }
        .qr-container {
            display: flex;
            justify-content: flex-end;
        }
        .qr-container img {
            border: 1px solid var(--gray-200);
            padding: 4px;
            border-radius: 8px;
            background: #fff;
            transition: transform 0.2s ease;
        }
        .qr-container img:hover {
            transform: scale(1.05);
        }

        /* Info Cards (Bill To & Delivery Info) */
        .info-section-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .info-card {
            background: var(--gray-50);
            border: 1px solid var(--gray-100);
            border-radius: 8px;
            padding: 16px 20px;
        }
        .info-card-title {
            font-size: 11px;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .info-card-text {
            font-size: 13px;
            line-height: 1.6;
            color: var(--gray-700);
            margin: 0;
        }

        /* Modern Item Table */
        .items-table-container {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .invoice-items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-items-table th {
            background-color: var(--gray-100);
            color: var(--dark);
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        .invoice-items-table td {
            padding: 12px 16px;
            font-size: 13px;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
        }
        .invoice-items-table tr:last-child td {
            border-bottom: none;
        }
        .free-badge {
            background-color: var(--success-light);
            color: var(--success);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
        }

        /* Totals & Signature layout */
        .invoice-summary-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
            align-items: start;
        }
        .words-card {
            padding: 16px 20px;
            border-left: 4px solid var(--primary);
            background-color: var(--gray-50);
            border-radius: 0 8px 8px 0;
        }
        .words-title {
            font-size: 11px;
            font-weight: 800;
            color: var(--gray-600);
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .words-content {
            font-size: 13px;
            font-style: italic;
            color: var(--dark);
            font-weight: 500;
        }
        .totals-box {
            background-color: var(--gray-50);
            border-radius: 8px;
            padding: 16px 20px;
            border: 1px solid var(--gray-100);
        }
        .total-line {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 6px 0;
        }
        .grand-total-line {
            border-top: 2px solid var(--gray-200);
            margin-top: 8px;
            padding-top: 10px;
            font-weight: 800;
            font-size: 16px;
            color: var(--dark);
        }

        /* Signatures section */
        .signatures-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-top: 50px;
            border-top: 1px solid var(--gray-200);
            padding-top: 24px;
        }
        .sig-box {
            text-align: center;
        }
        .sig-line {
            width: 80%;
            height: 1px;
            background-color: var(--gray-200);
            margin: 0 auto 10px auto;
        }
        .sig-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Verification State styling (Welcome & Failed) */
        .state-card {
            background: #fff;
            border-radius: var(--border-radius);
            padding: 50px 30px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(226, 232, 240, 0.8);
            text-align: center;
        }
        .state-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: rgba(79, 70, 229, 0.08);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 24px auto;
        }
        .state-icon.failed {
            background-color: #fef2f2;
            color: #ef4444;
        }
        .state-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 8px;
        }
        .state-desc {
            font-size: 14px;
            color: var(--gray-600);
            max-width: 450px;
            margin: 0 auto;
        }

        /* Responsive CSS for Mobile Screens */
        @media screen and (max-width: 767px) {
            .verify-container {
                margin: 20px auto;
                padding: 0 16px;
            }
            .invoice-body {
                padding: 24px 16px;
            }
            .invoice-header-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                text-align: center;
            }
            .invoice-meta {
                text-align: center;
                border-left: none;
                padding-left: 0;
            }
            .qr-container {
                justify-content: center;
            }
            .info-section-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .invoice-summary-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .signatures-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 24px;
            }
            .sig-line {
                width: 90%;
            }
            .items-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .invoice-items-table {
                min-width: 500px;
            }
            .public-navbar .container {
                flex-direction: column;
                gap: 12px;
                align-items: center !important;
            }
        }

        /* Print CSS - overrides screen CSS for physical paper A4 prints */
        @media print {
            @page {
                size: A4;
                margin: 8mm;
            }
            body {
                background: #fff !important;
                background-image: none !important;
                color: #000 !important;
                font-family: Arial, sans-serif !important;
                font-size: 11px !important;
            }
            .no-print, .btn, .public-navbar, .search-card {
                display: none !important;
            }
            .verify-container {
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            .invoice-card {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                background: #fff !important;
            }
            .status-banner {
                display: none !important;
            }
            .invoice-body {
                padding: 0 !important;
            }
            .invoice-header-grid {
                grid-template-columns: 80px 2.5fr 1.5fr 80px !important;
                display: grid !important;
                border-bottom: 2px solid #000 !important;
                padding-bottom: 15px !important;
                margin-bottom: 15px !important;
                align-items: center !important;
            }
            .qr-container {
                grid-column: 4;
                display: block !important;
            }
            .invoice-meta {
                grid-column: 3;
                text-align: left !important;
                border-left: 1px solid #ddd !important;
                padding-left: 15px !important;
            }
            .company-details {
                grid-column: 2;
            }
            .company-details h2 {
                color: #000 !important;
                font-size: 18px !important;
            }
            .info-section-grid {
                grid-template-columns: 1fr 1fr !important;
                display: grid !important;
                gap: 15px !important;
            }
            .info-card {
                background: #fff !important;
                border: 1px solid #ddd !important;
                padding: 10px !important;
            }
            .invoice-items-table th {
                background-color: #000 !important;
                color: #fff !important;
                border: 1px solid #000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .invoice-items-table td {
                border: 1px solid #ddd !important;
                padding: 6px 8px !important;
            }
            .invoice-summary-grid {
                grid-template-columns: 1.3fr 1fr !important;
                display: grid !important;
                gap: 20px !important;
                margin-bottom: 20px !important;
            }
            .words-card {
                border-left: 2px solid #000 !important;
                background: #fff !important;
            }
            .totals-box {
                background: #fff !important;
                border: none !important;
                padding: 0 !important;
            }
            .grand-total-line {
                border-top: 2px solid #000 !important;
            }
            .signatures-row {
                grid-template-columns: repeat(5, 1fr) !important;
                display: grid !important;
                margin-top: 40px !important;
            }
            .sig-line {
                background-color: #000 !important;
            }
        }
    </style>
</head>
<body>

<nav class="public-navbar no-print">
    <div class="container d-flex justify-content-between align-items-center py-3">
        <a href="index.php" class="text-decoration-none d-flex align-items-center navbar-brand">
            <?php if ($company['logo_url']): ?>
                <img src="<?php echo htmlspecialchars($company['logo_url']); ?>" height="30" class="me-2">
            <?php endif; ?>
            <span class="fw-bold text-dark"><?php echo htmlspecialchars($company['name']); ?></span>
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-dark btn-sm rounded-pill px-4"><i class="fas fa-print me-2"></i>Print A4</button>
            <a href="login.php" class="btn btn-outline-primary btn-sm rounded-pill px-4">Staff Login</a>
        </div>
    </div>
</nav>

<div class="container verify-container">
    
    <!-- Search Box Card -->
    <div class="search-card no-print mb-4">
        <form method="GET" class="row g-3 align-items-center justify-content-center">
            <div class="col-md-8 col-sm-12">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 ps-3 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="id" class="form-control border-start-0 ps-2" placeholder="Enter Invoice ID to verify (e.g. 336)..." value="<?php echo htmlspecialchars($id); ?>">
                </div>
            </div>
            <div class="col-md-3 col-sm-12 d-grid">
                <button class="btn btn-primary" type="submit">Verify Now</button>
            </div>
        </form>
    </div>

    <?php if ($id && $sale): ?>
        <div class="invoice-card">
            <div class="status-banner <?php echo $sale['status'] == 'Confirmed' ? '' : 'status-draft'; ?>">
                <i class="fas <?php echo $sale['status'] == 'Confirmed' ? 'fa-shield-halved' : 'fa-triangle-exclamation'; ?> me-2"></i>
                Authentic <?php echo htmlspecialchars($sale['status']); ?> Invoice
            </div>

            <div class="invoice-body">
                <!-- Header Grid -->
                <div class="invoice-header-grid">
                    <div class="company-logo">
                        <?php if ($company['logo_url']): ?>
                            <?php 
                                $logo_path = $company['logo_url'];
                                if (!filter_var($logo_path, FILTER_VALIDATE_URL) && strpos($logo_path, 'data:') !== 0) {
                                    $logo_path = BASE_URL . ltrim($logo_path, '/');
                                }
                            ?>
                            <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" style="max-width: 80px; max-height: 80px;">
                        <?php endif; ?>
                    </div>
                    <div class="company-details">
                        <h2><?php echo htmlspecialchars($company['name']); ?></h2>
                        <p><?php echo htmlspecialchars($company['address']); ?></p>
                        <p>Phone: <?php echo htmlspecialchars($company['phone']); ?> | Email: <?php echo htmlspecialchars($company['email']); ?></p>
                    </div>
                    <div class="invoice-meta">
                        <h3>Invoice</h3>
                        <p><strong>No:</strong> #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></p>
                        <p><strong>Date:</strong> <?php echo date('d-m-Y', strtotime($sale['created_at'])); ?></p>
                        <p><strong>Status:</strong> <span class="badge <?php echo $sale['status'] == 'Confirmed' ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo strtoupper($sale['status']); ?></span></p>
                    </div>
                    <div class="qr-container">
                        <img src="<?php echo $qr_url; ?>" alt="QR" style="width: 75px; height: 75px;">
                    </div>
                </div>

                <!-- Info Section (Bill To / Delivery Details) -->
                <div class="info-section-grid">
                    <div class="info-card">
                        <div class="info-card-title">
                            <i class="fas fa-user-tie"></i> Bill To
                        </div>
                        <p class="info-card-text">
                            <strong>Name:</strong> <?php echo htmlspecialchars($sale['customer_name']); ?><br>
                            <strong>Phone:</strong> <?php echo htmlspecialchars($sale['customer_phone']); ?><br>
                            <strong>Address:</strong> <?php echo htmlspecialchars($sale['customer_address']); ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-card-title">
                            <i class="fas fa-truck-ramp-box"></i> Delivery details
                        </div>
                        <p class="info-card-text">
                            <?php if ($sale['status'] == 'Confirmed'): ?>
                                <strong>Delivery Status:</strong> <?php echo htmlspecialchars($sale['delivery_status']); ?><br>
                                <strong>Delivery Date:</strong> <?php echo $sale['delivery_date'] ? date('d-m-Y', strtotime($sale['delivery_date'])) : 'Pending'; ?><br>
                                <?php if ($truck_load): ?>
                                    <strong>Truck No:</strong> <?php echo htmlspecialchars($truck_load['truck_no']); ?><br>
                                    <strong>Driver:</strong> <?php echo htmlspecialchars($truck_load['driver_name']); ?>
                                <?php else: ?>
                                    <strong>Truck / Driver:</strong> Unassigned
                                <?php endif; ?>
                            <?php else: ?>
                                <em>Invoice is in Draft status. Delivery details will be available once confirmed.</em>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="items-table-container">
                    <table class="invoice-items-table">
                        <thead>
                            <tr>
                                <th style="width: 8%;">#</th>
                                <th style="width: 50%;">Product Description</th>
                                <th style="width: 12%; text-align: center;">Qty</th>
                                <th style="width: 15%; text-align: right;">Rate (BDT)</th>
                                <th style="width: 15%; text-align: right;">Total (BDT)</th>
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
                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                        <?php echo $item['note'] ? "<br><small class='text-muted'>" . htmlspecialchars($item['note']) . "</small>" : ""; ?>
                                    </td>
                                    <td class="text-center"><?php echo $item['billed_qty']; ?></td>
                                    <td class="text-end"><?php echo number_format($item['rate'], 2); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($item['total'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($item['free_qty'] > 0): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                        <br><span class="free-badge">FREE ITEM</span>
                                    </td>
                                    <td class="text-center"><?php echo $item['free_qty']; ?></td>
                                    <td class="text-end"><?php echo number_format($item['rate'], 2); ?></td>
                                    <td class="text-end fw-bold">0.00</td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Summary Section -->
                <div class="invoice-summary-grid">
                    <div class="words-card">
                        <div class="words-title">Amount in Words</div>
                        <div class="words-content"><?php echo number_to_words($sale['grand_total']); ?></div>
                        <div class="mt-3 no-print">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                <i class="fas fa-circle-check me-1"></i> Digital Authenticity Verified
                            </span>
                        </div>
                    </div>
                    <div class="totals-box">
                        <div class="total-line">
                            <span class="text-muted">Sub Total</span>
                            <span><?php echo number_format($sale['total_amount'], 2); ?></span>
                        </div>
                        <?php if ($sale['discount'] > 0): ?>
                            <div class="total-line text-danger">
                                <span>Discount</span>
                                <span>-<?php echo number_format($sale['discount'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="total-line grand-total-line">
                            <span>NET TOTAL</span>
                            <span>৳ <?php echo number_format($sale['grand_total'], 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Signatures -->
                <div class="signatures-row">
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-label">Driver</div>
                    </div>
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-label">Security</div>
                    </div>
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-label">Distribution</div>
                    </div>
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-label">Accounts</div>
                    </div>
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-label">Authorized Authority</div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($id): ?>
        <div class="state-card text-danger">
            <div class="state-icon failed">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <h3 class="state-title text-dark">Verification Failed</h3>
            <p class="state-desc mb-4">We couldn't find an invoice matching ID <strong>#<?php echo htmlspecialchars($id); ?></strong> in our system. Please check the ID and try again.</p>
            <a href="verify_invoice.php" class="btn btn-primary rounded-pill px-5">Go Back</a>
        </div>
    <?php else: ?>
        <div class="state-card">
            <div class="state-icon">
                <i class="fas fa-fingerprint"></i>
            </div>
            <h3 class="state-title">Public Verification Portal</h3>
            <p class="state-desc mb-4">Enter an Invoice ID in the search bar above to instantly verify its authenticity and delivery details.</p>
            <div class="d-flex justify-content-center gap-2 no-print">
                <span class="badge bg-light text-dark border px-3 py-2"><i class="fas fa-shield-alt text-primary me-1"></i> Secure Authentication</span>
                <span class="badge bg-light text-dark border px-3 py-2"><i class="fas fa-circle-check text-success me-1"></i> Official Document</span>
            </div>
        </div>
    <?php endif; ?>

    <div class="text-center mt-5 mb-5 text-muted no-print" style="font-size: 11px;">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($company['name']); ?>. All rights reserved.<br>
        This portal is provided for public document verification. Powered by <strong>sohojweb</strong>
    </div>
</div>

</body>
</html>
