<?php
/* ── Invoices / Delivery Notes
   Accepts: ?order_ids=1,2,3  OR  ?truck_load_id=X
   Requires valid session (any role). ── */
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit; }
if (!defined('APP_NAME')) define('APP_NAME', 'EIS');

$cid = (int)$_SESSION['company_id'];

/* Resolve order IDs */
$clean_ids = [];

if (isset($_GET['truck_load_id'])) {
    /* New: load from truck_loads system */
    $tlid  = (int)$_GET['truck_load_id'];
    $stmt  = $conn->prepare(
        "SELECT tlo.order_id FROM truck_load_orders tlo
         JOIN truck_loads tl ON tl.id=tlo.truck_load_id
         WHERE tlo.truck_load_id=? AND tlo.is_active=1 AND tl.company_id=?"
    );
    $stmt->bind_param("ii", $tlid, $cid); $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $clean_ids[] = (int)$r['order_id'];
    $stmt->close();
} else {
    /* Legacy: comma-separated order IDs */
    $order_ids_input = $_GET['order_ids'] ?? '';
    if (!$order_ids_input) die("No Order IDs provided.");
    foreach (explode(',', $order_ids_input) as $id) {
        if ((int)$id > 0) $clean_ids[] = (int)$id;
    }
}

$unique_requested_ids = array_values(array_unique($clean_ids));
if (empty($unique_requested_ids)) die("No valid Order IDs.");
$sql_id_list = implode(',', $unique_requested_ids);

/* Fetch Orders (scoped to company) */
$orders_res = $conn->query(
    "SELECT o.id AS order_id, o.order_date, o.shop_id,
            s.shop_name, r.route_name,
            c.name AS company_name, c.address AS company_address,
            c.phone AS company_phone, c.logo AS company_logo
     FROM orders o
     JOIN shops s ON o.shop_id=s.id
     JOIN routes r ON o.route_id=r.id
     JOIN companies c ON o.company_id=c.id
     WHERE o.id IN ($sql_id_list) AND o.company_id=$cid"
);

/* Fetch Items */
$items_res = $conn->query(
    "SELECT oi.order_id, oi.item_id, oi.quantity, oi.price,
            (oi.quantity * oi.price) AS total, i.item_name
     FROM order_items oi
     JOIN items i ON oi.item_id=i.id
     WHERE oi.order_id IN ($sql_id_list)"
);

// ==========================================
// 2. Data Processing
// ==========================================
$items_map = [];
while ($row = $items_res->fetch_assoc()) {
    $items_map[$row['order_id']][] = $row;
}

$global_item_totals = []; 
$grand_total_amount = 0;
$grand_total_qty = 0;
$merged_groups = []; 
$order_shop_map = []; 

while ($row = $orders_res->fetch_assoc()) {
    $oid = $row['order_id'];
    $shop_id = $row['shop_id'];
    $order_shop_map[$oid] = $shop_id;

    if (!isset($merged_groups[$shop_id])) {
        $merged_groups[$shop_id] = [
            'shop_name' => $row['shop_name'],
            'route_name' => $row['route_name'],
            'company_name' => $row['company_name'],
            'company_address' => $row['company_address'],
            'company_phone' => $row['company_phone'],
            'company_logo' => $row['company_logo'],
            'order_ids' => [], 
            'items' => [], 
            'invoice_total' => 0
        ];
    }

    $merged_groups[$shop_id]['order_ids'][] = $oid;
    $current_items = $items_map[$oid] ?? [];

    foreach ($current_items as $item) {
        // Unique Key: ID + Price
        $key = $item['item_id'] . '_' . $item['price']; 
        
        if (!isset($merged_groups[$shop_id]['items'][$key])) {
            $merged_groups[$shop_id]['items'][$key] = [
                'item_name' => $item['item_name'],
                'quantity' => 0,
                'price' => $item['price'], 
                'total' => 0
            ];
        }
        $merged_groups[$shop_id]['items'][$key]['quantity'] += $item['quantity'];
        $merged_groups[$shop_id]['items'][$key]['total'] += $item['total'];
        $merged_groups[$shop_id]['invoice_total'] += $item['total'];

        // Stats
        if (!isset($global_item_totals[$item['item_name']])) $global_item_totals[$item['item_name']] = 0;
        $global_item_totals[$item['item_name']] += $item['quantity'];
        $grand_total_qty += $item['quantity'];
        $grand_total_amount += $item['total'];
    }
}

// 3. Final Preparation & Summary String Generation
$final_sorted_groups = [];
$processed_shops = [];

foreach ($unique_requested_ids as $req_id) {
    if (isset($order_shop_map[$req_id])) {
        $sid = $order_shop_map[$req_id];
        if (!isset($processed_shops[$sid]) && isset($merged_groups[$sid])) {
            $group = $merged_groups[$sid];
            
            // Build Summary String: "ItemName (Qty @ Price)"
            $summ = [];
            foreach($group['items'] as $itm) {
                // Formatting price to remove trailing .00 if desired, or keep standard
                $p_str = number_format($itm['price'], 2);
                if(substr($p_str, -3) == '.00') $p_str = substr($p_str, 0, -3);
                
                $summ[] = "<b>" . $itm['item_name'] . "</b> (" . $itm['quantity'] . "@" . $p_str . ")";
            }
            $group['item_summary_str'] = implode(', ', $summ);
            $group['display_ids'] = implode(', ', $group['order_ids']);
            $group['items'] = array_values($group['items']);

            // === DYNAMIC DENSITY CALCULATION ===
            $count = count($group['items']);
            if ($count > 25) {
                $group['css_class'] = 'layout-ultra';
            } elseif ($count > 12) {
                $group['css_class'] = 'layout-compact';
            } else {
                $group['css_class'] = 'layout-comfort';
            }

            $final_sorted_groups[] = $group;
            $processed_shops[$sid] = true;
        }
    }
}
ksort($global_item_totals);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
   <title>Invoices-<?php echo date('d M Y, h:i A'); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        :root {
            --primary: #2563eb; /* Royal Blue */
            --primary-dark: #1e3a8a;
            --secondary: #64748b;
            --accent-bg: #eff6ff;
            --text-dark: #0f172a;
            --border-color: #cbd5e1;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #555;
            margin: 0;
            color: var(--text-dark);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* =========================================
           BASE PAGE CONFIG (A4 LANDSCAPE)
           ========================================= */
        .page {
            background: white;
            width: 297mm;
            min-height: 209mm; 
            margin: 10mm auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
        }

        @media print {
            body { background: white; }
            .page { width: 98%; margin: 0; box-shadow: none; border: none; page-break-after: always; height: 100%; }
            @page { size: A4 landscape; margin: 0; }
        }

        /* =========================================
           PART 1: DELIVERY REPORT STYLES
           ========================================= */
        .report-wrapper { padding: 15mm; }
        
        .report-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            border-bottom: 3px solid var(--primary); padding-bottom: 10px; margin-bottom: 15px;
        }
        .report-title { font-size: 22pt; font-weight: 800; color: var(--primary-dark); text-transform: uppercase; }
        .copy-tag { 
            background: var(--primary); color: white; padding: 4px 10px; 
            font-weight: bold; font-size: 7pt; text-transform: uppercase; border-radius: 4px; 
        }

        .report-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        .report-table th { 
            text-align: left; background: var(--accent-bg); color: var(--primary-dark);
            border-bottom: 2px solid var(--primary); padding: 8px; font-weight: 700; text-transform: uppercase; font-size: 8pt; 
        }
        .report-table td { padding: 8px; border-bottom: 1px solid var(--border-color); vertical-align: top; }
        
        /* Specific: Item names in black */
        .manifest-items { color: #000; font-size: 9pt; line-height: 1.4; }
        .manifest-items b { font-weight: 700; }

        .loading-box { 
            margin-top: 20px; background: var(--accent-bg); padding: 15px; border-radius: 6px; 
            border: 1px solid var(--primary);
        }
        .loading-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; font-size: 8.5pt; }

        /* =========================================
           PART 2: INVOICE STYLES (SIDE BY SIDE)
           ========================================= */
        .invoice-split-container {
            display: grid;
            grid-template-columns: 1fr auto 1fr; /* Content | Cut Line | Content */
            height: 100%;
            position: relative;
        }

        /* Vertical Cut Line */
        .cut-line-vert {
            width: 0;
            border-right: 1px dashed #94a3b8;
            position: relative;
            height: 100%;
            margin: 0 2px;
        }
        .cut-line-vert::after {
            content: '✂';
            position: absolute;
            top: 50%; left: -9px;
            background: white; color: #94a3b8;
            padding: 8px 0; font-size: 14pt;
        }

        /* Invoice Content Panel */
        .inv-panel {
            padding: 10mm;
            position: relative;
            display: flex;
            flex-direction: column;
            height: 209mm; /* Full page height */
            box-sizing: border-box;
        }

        /* Watermark */
        .watermark {
            position: absolute;
            top: 55%; left: 50%;
            transform: translate(-50%, -50%) rotate(0deg);
            width: 80%;
            opacity: 0.05; /* Low opacity */
            pointer-events: none;
            z-index: 0;
            filter: grayscale(10%);
        }

        /* Header Area */
        .inv-head { display: flex; justify-content: space-between; margin-bottom: 15px; position: relative; z-index: 1; }
        
        .logo-box { display: flex; align-items: flex-start; gap: 12px; }
        .inv-logo { height: 50px; width: auto; object-fit: contain; }
        
        .company-block h1 { margin: 0; font-size: 16pt; font-weight: 800; color: var(--primary-dark); }
        .company-block p { margin: 2px 0; font-size: 8pt; color: var(--secondary); }
        
        .meta-block { text-align: right; }
        .inv-label { font-size: 15pt; font-weight: 900; color: var(--primary); line-height: 1; letter-spacing: 1px; }
        .meta-data { font-size: 9pt; font-weight: 600; margin-top: 5px; color: var(--text-dark); }

        /* Bill To Box */
        .bill-strip {
            background: var(--accent-bg);
            border-left: 4px solid var(--primary);
            padding: 10px;
            margin-bottom: 15px;
            display: flex; justify-content: space-between; align-items: center;
            position: relative; z-index: 1;
            border-radius: 0 4px 4px 0;
        }
        .bill-label { font-size: 7pt; text-transform: uppercase; color: var(--secondary); font-weight: 700; margin-bottom: 2px;}
        .shop-name { font-size: 8pt; font-weight: 700; color: var(--primary-dark); }
        .route-name { font-size: 8pt; font-weight: 600; text-align: right; }

        /* Items Table */
        .inv-table-wrap { flex-grow: 1; position: relative; z-index: 1; }
        .inv-table { width: 100%; border-collapse: collapse; }
        
        .inv-table th { 
            border-bottom: 2px solid var(--primary); 
            background: #fff;
            color: var(--primary-dark);
            font-size: 8pt; text-align: left; padding: 6px 4px; 
            text-transform: uppercase; font-weight: 700;
        }
        .inv-table td { border-bottom: 1px solid var(--border-color); padding: 6px 4px; vertical-align: top; }
        .inv-table tr:last-child td { border-bottom: none; }
        .num { text-align: right; }

        /* Footer */
        .inv-foot { position: relative; z-index: 1; margin-top: auto; }
     .total-box {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    background: #eff6ff;
    color: black;
    padding: 8px 12px;
    margin-bottom: 50px;
    border-radius: 4px;
    /* Added border property with a solid blue color */
    border: 1px solid blue;
}

        .total-label { font-size: 10pt; font-weight: 600; text-transform: uppercase; margin-right: 15px; }
        .total-val { font-size: 14pt; font-weight: 800; }

        .sign-area { display: flex; justify-content: space-between; font-size: 7pt; color: var(--secondary); margin-top: 10px; }
        .sign-line { width: 30%; border-top: 1px dashed var(--border-color); padding-top: 4px; text-align: center; }

        /* =========================================
           DYNAMIC DENSITY CLASSES (Fit to Page)
           ========================================= */
        
        /* 1. COMFORT (Normal) */
        .layout-comfort .inv-table td { font-size: 8pt; padding: 8px 4px; }

        /* 2. COMPACT (12-25 items) */
        .layout-compact .company-block h1 { font-size: 16pt; }
        .layout-compact .inv-table td { font-size: 8pt; padding: 4px 2px; }
        .layout-compact .inv-table th { font-size: 7.5pt; }
        .layout-compact .bill-strip { padding: 6px 8px; margin-bottom: 8px; }

        /* 3. ULTRA COMPACT (25+ items) */
        .layout-ultra .inv-head { margin-bottom: 5px; }
        .layout-ultra .inv-logo { height: 35px; }
        .layout-ultra .company-block h1 { font-size: 10pt; }
        .layout-ultra .company-block p { display: none; }
        .layout-ultra .inv-label { font-size: 14pt; }
        .layout-ultra .bill-strip { padding: 4px; margin-bottom: 4px; }
        .layout-ultra .shop-name { font-size: 7pt; }
        .layout-ultra .inv-table td { font-size: 7pt; padding: 2px; height: 14px; }
        .layout-ultra .inv-table th { font-size: 7pt; padding: 2px; }
        .layout-ultra .total-box { padding: 4px 8px; margin-bottom: 10px; }
        .layout-ultra .total-val { font-size: 11pt; }
        
    </style>
</head>
<body>

    <?php 
    $report_types = ['OFFICE COPY', 'DRIVER / LOADING COPY'];
    foreach($report_types as $rtype): 
    ?>
    <div class="page" style="height: auto; min-height: 209mm;">
        <div class="report-wrapper">
            <div class="report-header">
                <div>
                    <div class="report-title">Delivery Summary</div>
                    <div style="font-size: 9pt; color: var(--secondary);">Generated: <?php echo date('d M Y, h:i A'); ?></div>
                </div>
                <div style="text-align: right;">
                    <span class="copy-tag"><?php echo $rtype; ?></span>
                </div>
            </div>

            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 25%;">Customer / Route</th>
                        <th style="width: 55%;">Items Breakdown (Qty @ Price)</th>
                        <th class="num" style="width: 15%;">Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sl=1; foreach($final_sorted_groups as $g): ?>
                    <tr>
                        <td><?php echo $sl++;  echo " # ".$g['display_ids'];?> </td>
                        <td>
                            <div style="font-weight: 700; font-size: 10pt; color: var(--text-dark);"><?php echo htmlspecialchars($g['shop_name']); ?></div>
                            <div style="color: var(--secondary); font-size: 8pt;"><?php echo htmlspecialchars($g['route_name']); ?></div>
                        </td>
                        <td class="manifest-items">
                            <?php echo $g['item_summary_str']; // already html safe via build logic ?>
                        </td>
                        <td class="num" style="font-weight: 700;">
                            <?php echo number_format($g['invoice_total'], 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background: var(--accent-bg); border-top: 2px solid var(--primary);">
                        <td colspan="3" class="num" style="padding-right: 15px; font-weight: 800; color: var(--primary-dark);">GRAND TOTAL:</td>
                        <td class="num" style="font-weight: 800; font-size: 12pt; color: var(--primary-dark);"><?php echo number_format($grand_total_amount, 2); ?></td>
                    </tr>
                </tbody>
            </table>

       
            <div class="loading-box">
                <div style="font-weight: 800; margin-bottom: 10px; border-bottom: 1px solid #ccc; color: var(--primary-dark);">TOTAL LOADING SUMMARY</div>
                <div class="loading-grid">
                    <?php foreach($global_item_totals as $iname => $iqty): ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px dotted #ccc;">
                        <span><?php echo htmlspecialchars($iname); ?></span>
                        <span style="font-weight: 700; color: var(--primary);"><?php echo $iqty; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
       

            <div class="sign-area" style="margin-top: 30px;">
                <div class="sign-line">Prepared By</div>
                <div class="sign-line">Driver/SV Signature</div>
                <div class="sign-line">Manager Signature</div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php foreach($final_sorted_groups as $inv): ?>
    <div class="page">
        <div class="invoice-split-container">
            
            <div class="inv-panel <?php echo $inv['css_class']; ?>">
                <?php renderInvoiceContent($inv, 'OFFICE COPY'); ?>
            </div>

            <div class="cut-line-vert"></div>

            <div class="inv-panel <?php echo $inv['css_class']; ?>">
                <?php renderInvoiceContent($inv, 'CUSTOMER COPY'); ?>
            </div>

        </div>
    </div>
    <?php endforeach; ?>

</body>
</html>

<?php
// Helper function to render invoice HTML
function renderInvoiceContent($inv, $copyType) {
?>
    <?php if(!empty($inv['company_logo'])): ?>
        <img src="<?php echo htmlspecialchars($inv['company_logo']); ?>" class="watermark">
    <?php endif; ?>

    <div class="inv-head">
        <div class="logo-box">
            <?php if(!empty($inv['company_logo'])): ?>
                <img src="<?php echo htmlspecialchars($inv['company_logo']); ?>" class="inv-logo">
            <?php endif; ?>
            <div class="company-block">
                <h1><?php echo htmlspecialchars($inv['company_name']); ?></h1>
                <p><?php echo htmlspecialchars($inv['company_address']); ?> <?php echo htmlspecialchars($inv['company_phone']); ?></p>
            </div>
        </div>
        
        <div class="meta-block">
            <div class="inv-label">INVOICE</div>
            <div class="copy-tag" style="display:inline-block; margin-top:5px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db;"><?php echo $copyType; ?></div>
            <div class="meta-data">
                <div>Ref: <?php echo $inv['display_ids']; ?></div>
                <div><?php echo date('d M Y'); ?></div>
            </div>
        </div>
    </div>

    <div class="bill-strip">
        <div>
            <div class="bill-label">Billed To</div>
            <div class="shop-name"><?php echo htmlspecialchars($inv['shop_name']); ?></div>
        </div>
        <div>
            <div class="bill-label" style="text-align: right;">Route</div>
            <div class="route-name"><?php echo htmlspecialchars($inv['route_name']); ?></div>
        </div>
    </div>

    <div class="inv-table-wrap">
        <table class="inv-table">
            <thead>
                <tr>
                    <th style="width: 55%;">Item Description</th>
                    <th class="num" style="width: 10%;">Qty</th>
                    <th class="num" style="width: 15%;">Price</th>
                    <th class="num" style="width: 20%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($inv['items'] as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td class="num" style="font-weight: 600;"><?php echo $item['quantity']; ?></td>
                    <td class="num"><?php echo number_format($item['price'], 2); ?></td>
                    <td class="num" style="font-weight: 600;"><?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="inv-foot">
        <div class="total-box">
            <span class="total-label">Payable Amount</span>
            <span class="total-val"><?php echo number_format($inv['invoice_total'], 2); ?></span>
        </div>
        
        

        <div class="sign-area">
            <div class="sign-line">Authorized By</div>
            <div class="sign-line">Delivery Man</div>
            <div class="sign-line">Received By</div>
        </div>
        <div style="text-align: center; font-size: 6pt; color: #cbd5e1; margin-top: 5px;">
            <?php echo APP_NAME; ?> - System Generated Invoice. Developed by kowshiqueroy@gmail.com
        </div>
    </div>
<?php
}
?>