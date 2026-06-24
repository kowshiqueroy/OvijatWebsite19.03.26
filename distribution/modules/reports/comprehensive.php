<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER]);

// Filters
$report_type = $_GET['report_type'] ?? 'sales';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$search_query = $_GET['search'] ?? '';
$customer_id = $_GET['customer_id'] ?? '';

$all_customers = fetch_all("SELECT id, name, phone FROM customers WHERE isDelete = 0 ORDER BY name ASC");

// Viewer order type + product visibility filters
$order_type_sql = get_order_type_filter('s');
$hidden_prod_ids = get_hidden_product_ids(null, 'reports');
$hidden_prod_sql = $hidden_prod_ids ? "AND p.id NOT IN (" . implode(',', array_map('intval', $hidden_prod_ids)) . ")" : "";

$data = [];
if ($report_type == 'sales') {
    $sql = "SELECT s.*, c.name as customer_name, u.username as creator_name, 
            (SELECT CONCAT(truck_no, ' (', driver_name, ')') FROM truck_loads tl JOIN truck_load_items tli ON tl.id = tli.truck_load_id WHERE tli.invoice_id = s.id AND tl.isDelete = 0 LIMIT 1) as truck_info
            FROM sales_drafts s 
            JOIN customers c ON s.customer_id = c.id 
            JOIN users u ON s.created_by = u.id 
            WHERE s.isDelete = 0 
            AND DATE(s.created_at) BETWEEN ? AND ?";
    $params = [$start_date, $end_date];

    if ($order_type_sql) $sql .= " AND $order_type_sql";

    if ($search_query) {
        $sql .= " AND (c.name LIKE ? OR s.id LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    if ($customer_id) {
        $sql .= " AND s.customer_id = ?";
        $params[] = $customer_id;
    }
    $sql .= " ORDER BY s.created_at DESC";
    $sales = fetch_all($sql, $params);
    
    // For each sale, fetch items
    foreach ($sales as $key => $sale) {
        $sales[$key]['items'] = fetch_all("SELECT i.*, p.name as product_name FROM sales_items i JOIN products p ON i.product_id = p.id WHERE i.draft_id = ? AND i.isDelete = 0 $hidden_prod_sql", [$sale['id']]);
    }
    $data = $sales;
} elseif ($report_type == 'stock') {
    // Union Stock Entries and Damages
    $sql_base = "SELECT 'Entry' as source, se.id, se.created_at, se.quantity, p.name as product_name, u.username as creator_name, cat.name as cat_name
            FROM stock_entries se 
            JOIN products p ON se.product_id = p.id 
            JOIN categories cat ON p.category_id = cat.id
            JOIN users u ON se.user_id = u.id 
            WHERE se.isDelete = 0 
            AND DATE(se.created_at) BETWEEN ? AND ?
            UNION ALL
            SELECT 'Damage' as source, sd.id, sd.created_at, sd.quantity * -1 as quantity, p.name as product_name, u.username as creator_name, cat.name as cat_name
            FROM stock_damages sd
            JOIN products p ON sd.product_id = p.id 
            JOIN categories cat ON p.category_id = cat.id
            JOIN users u ON sd.user_id = u.id 
            WHERE sd.isDelete = 0 
            AND DATE(sd.created_at) BETWEEN ? AND ?";
    
    $params = [$start_date, $end_date, $start_date, $end_date];

    if ($search_query) {
        $sql = "SELECT * FROM ($sql_base) as combined WHERE (product_name LIKE ? OR cat_name LIKE ? OR creator_name LIKE ?) ORDER BY created_at DESC";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    } else {
        $sql = "SELECT * FROM ($sql_base) as combined ORDER BY created_at DESC";
    }
    $data = fetch_all($sql, $params);
} elseif ($report_type == 'ledger') {
    if ($customer_id) {
        $customer_info = fetch_one("SELECT * FROM customers WHERE id = ?", [$customer_id]);
        
        // Calculate balance before start date (Wallet Model: Credit increases, Debit/Invoice decreases)
        $pre_trans = fetch_one("SELECT 
            SUM(CASE WHEN type = 'Credit' THEN amount ELSE 0 END) as total_credit,
            SUM(CASE WHEN type = 'Debit' THEN amount ELSE 0 END) as total_debit
            FROM transactions 
            WHERE customer_id = ? AND DATE(created_at) < ? AND isDelete = 0", [$customer_id, $start_date]);
        
        $pre_sales = fetch_one("SELECT SUM(grand_total) as total_sales FROM sales_drafts WHERE customer_id = ? AND status = 'Confirmed' AND DATE(confirmed_at) < ? AND isDelete = 0", [$customer_id, $start_date]);

        $opening_bal = $customer_info['opening_balance'] + ($pre_trans['total_credit'] ?? 0) - ($pre_trans['total_debit'] ?? 0) - ($pre_sales['total_sales'] ?? 0);
        
        // Fetch both transactions and invoices for the period
        $trans = fetch_all("SELECT created_at, description, amount, type FROM transactions WHERE customer_id = ? AND DATE(created_at) BETWEEN ? AND ? AND isDelete = 0", [$customer_id, $start_date, $end_date]);
        $invoices = fetch_all("SELECT confirmed_at as created_at, CONCAT('Invoice #', id) as description, grand_total as amount, 'Debit' as type FROM sales_drafts WHERE customer_id = ? AND status = 'Confirmed' AND DATE(confirmed_at) BETWEEN ? AND ? AND isDelete = 0", [$customer_id, $start_date, $end_date]);
        
        $combined = array_merge($trans, $invoices);
        usort($combined, function($a, $b) { return strtotime($a['created_at']) - strtotime($b['created_at']); });

        $data = ['info' => $customer_info, 'opening_bal' => $opening_bal, 'transactions' => $combined];
    }
}
?>

<style>
    .report-filter-box { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; }
    /* A4 Print Optimization */
    @page { size: A4; margin: 10mm; }
    
    .excel-table { border: 1px solid #000; width: 100%; border-collapse: collapse; }
    .excel-table th { background: #eee !important; border: 1px solid #000 !important; color: #000; font-weight: bold; text-transform: uppercase; font-size: 10px; padding: 4px; }
    .excel-table td { border: 1px solid #000 !important; font-size: 10px; padding: 3px 5px; vertical-align: top; }
    .item-detail-row { background-color: #fff; font-size: 9px !important; }
    
    @media print {
        html, body, #wrapper, #page-content-wrapper, .container-fluid, .card, .card-body { 
            background: #fff !important; 
            background-color: #fff !important; 
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color: #000 !important;
        }
        #sidebar-wrapper, .navbar, .btn, .alert, .no-print, .report-filter-box { display: none !important; }
        #page-content-wrapper { width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .container-fluid { padding: 0 !important; }
        .card { border: none !important; box-shadow: none !important; }
        .excel-table { width: 100% !important; }
        .report-header-print { display: block !important; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px; }
    }
    .report-header-print { display: none; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h3>Comprehensive Reports</h3>
    <div class="d-flex gap-2">
        <button onclick="copyComprehensiveWhatsApp(this)" class="btn btn-outline-success btn-sm"><i class="fa-brands fa-whatsapp me-1"></i>WhatsApp</button>
        <button onclick="downloadTableCSV('report_<?php echo $report_type; ?>_<?php echo date('Y-m-d'); ?>.csv','#comp-table')" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
        <button onclick="window.print()" class="btn btn-dark btn-sm"><i class="fa-solid fa-print me-1"></i>Print</button>
    </div>
</div>

<!-- Filter Section -->
<div class="card report-filter-box mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label small fw-bold">Report Type</label>
                <select name="report_type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="sales" <?php echo $report_type == 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                    <option value="stock" <?php echo $report_type == 'stock' ? 'selected' : ''; ?>>Stock In/Damage</option>
                    <option value="ledger" <?php echo $report_type == 'ledger' ? 'selected' : ''; ?>>Customer Ledger</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Start Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">End Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
            </div>
            
            <?php if ($report_type == 'sales' || $report_type == 'ledger'): ?>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Customer</label>
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="">-- All Customers --</option>
                    <?php foreach ($all_customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $customer_id == $c['id'] ? 'selected' : ''; ?>><?php echo $c['name']; ?> (<?php echo $c['phone']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-3">
                <label class="form-label small fw-bold">Search Keywords</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Keywords..." value="<?php echo $search_query; ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Print Header -->
<div class="report-header-print text-center">
    <h2 class="mb-1 text-primary"><?php echo $company['name']; ?></h2>
    <p class="mb-3 small"><?php echo $company['address']; ?> | <?php echo $company['phone']; ?></p>
    <h4 class="text-uppercase border-bottom pb-2">
        <?php 
            if($report_type == 'sales') echo 'Sales & Itemized Distribution Report';
            elseif($report_type == 'stock') echo 'Stock Inward & Damage Report';
            else echo 'Customer Ledger Report';
        ?>
    </h4>
    <p class="small mt-2">
        <strong>Period:</strong> <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?>
        <?php if($search_query) echo " | <strong>Search:</strong> $search_query"; ?>
    </p>
</div>

<!-- Data Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <?php if ($report_type == 'sales'): ?>
            <table class="table table-bordered excel-table mb-0 export-table" id="comp-table">
                <thead>
                    <tr>
                        <th style="width: 12%;">Date / Inv / By</th>
                        <th style="width: 15%;">Customer / Delivery</th>
                        <th style="width: 50%;">Itemized Distribution Details</th>
                        <th style="width: 13%; text-align: right;">Amt - Disc</th>
                        <th style="width: 10%; text-align: right;">Net Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_amt = 0;
                    $total_invoices = count($data);
                    $total_items_qty = 0;
                    $total_free_qty = 0;
                    $product_summary = [];

                    foreach ($data as $row): 
                    ?>
                        <tr>
                            <td>
                                <div class="fw-bold">#<?php echo $row['id']; ?></div>
                                <div class="small text-muted"><?php echo date('d-m-y', strtotime($row['created_at'])); ?></div>
                                <div class="small italic"><?php echo $row['creator_name']; ?></div>
                            </td>
                            <td>
                                <strong><?php echo $row['customer_name']; ?></strong>
                                <div class="small mt-1 text-uppercase fw-bold" style="font-size: 8px;">
                                    <?php echo $row['delivery_status']; ?>
                                    <?php if($row['truck_info']): ?>
                                        <br><span class="text-muted"><?php echo $row['truck_info']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-0">
                                <table class="table table-sm table-borderless mb-0 w-100" style="font-size: 9px; table-layout: fixed;">
                                    <thead>
                                        <tr class="border-bottom">
                                            <th style="width: 65%; padding-left: 5px;">Product</th>
                                            <th style="width: 15%; text-align: center;">QTY</th>
                                            <th style="width: 20%; text-align: right; padding-right: 5px;">Item Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($row['items'] as $item): 
                                        $pid = $item['product_id'];
                                        if (!isset($product_summary[$pid])) {
                                            $product_summary[$pid] = ['name' => $item['product_name'], 'billed' => 0, 'free' => 0, 'amount' => 0];
                                        }
                                        $product_summary[$pid]['billed'] += $item['billed_qty'];
                                        $product_summary[$pid]['free'] += $item['free_qty'];
                                        $product_summary[$pid]['amount'] += $item['total'];

                                        $total_items_qty += $item['billed_qty'];
                                        $total_free_qty += $item['free_qty'];
                                    ?>
                                        <?php if ($item['billed_qty'] > 0): ?>
                                        <tr>
                                            <td style="padding-left: 5px;"><?php echo $item['product_name']; ?></td>
                                            <td class="text-center"><?php echo $item['billed_qty']; ?></td>
                                            <td class="text-end" style="padding-right: 5px;"><?php echo number_format($item['total'], 2); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if ($item['free_qty'] > 0): ?>
                                        <tr class="text-success italic">
                                            <td style="padding-left: 5px;"><?php echo $item['product_name']; ?></td>
                                            <td class="text-center"><?php echo $item['free_qty']; ?></td>
                                            <td class="text-end fw-bold" style="padding-right: 5px;">FREE</td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </td>
                            <td class="text-end">
                                <div><?php echo number_format($row['total_amount'], 2); ?></div>
                                <div class="text-danger small">- <?php echo number_format($row['discount'], 2); ?></div>
                            </td>
                            <td class="text-end fw-bold"><?php echo number_format($row['grand_total'], 2); ?></td>
                        </tr>
                        <?php $total_amt += $row['grand_total']; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">TOTAL REVENUE (NET):</td>
                        <td class="text-end text-primary" style="font-size: 14px;"><?php echo format_currency($total_amt); ?></td>
                    </tr>
                </tfoot>
            </table>

            <!-- Report Summary Section -->
            <div class="mt-4 p-3 border rounded bg-white">
                <h6 class="border-bottom pb-2 mb-3 fw-bold"><i class="fas fa-chart-pie me-2"></i> Report Summary & Item-wise Totals</h6>
                
                <!-- Main Metrics Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm excel-table bg-white text-center">
                        <thead class="table-secondary">
                            <tr>
                                <th style="width: 25%;">Total Invoices</th>
                                <th style="width: 25%;">Total Billed Items</th>
                                <th style="width: 25%;">Total Free Items</th>
                                <th style="width: 25%;">Total Net Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="h5 fw-bold"><?php echo number_format($total_invoices); ?></td>
                                <td class="h5 fw-bold text-primary"><?php echo number_format($total_items_qty); ?></td>
                                <td class="h5 fw-bold text-success"><?php echo number_format($total_free_qty); ?></td>
                                <td class="h5 fw-bold text-dark"><?php echo number_format($total_amt, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Product-wise Totals Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-sm excel-table bg-white">
                        <thead class="table-dark">
                            <tr>
                                <th>Product Name</th>
                                <th class="text-center" style="width: 20%;">Total Billed</th>
                                <th class="text-center" style="width: 20%;">Total Free</th>
                                <th class="text-end" style="width: 25%;">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($product_summary as $ps): ?>
                            <tr>
                                <td><strong><?php echo $ps['name']; ?></strong></td>
                                <td class="text-center"><?php echo number_format($ps['billed']); ?></td>
                                <td class="text-center text-success"><?php echo number_format($ps['free']); ?></td>
                                <td class="text-end fw-bold"><?php echo number_format($ps['amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Signature Section -->
            <div class="report-signatures mt-5 pt-5">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="border-top pt-2 mx-3">Prepared By</div>
                    </div>
                    <div class="col-4">
                        <div class="border-top pt-2 mx-3">Checked By</div>
                    </div>
                    <div class="col-4">
                        <div class="border-top pt-2 mx-3">Authorized Signature</div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-5 mb-4 small text-muted border-top pt-3 mx-4">
                Powered by <strong>sohojweb</strong>
            </div>

            <?php elseif ($report_type == 'stock'): ?>
            <table class="table table-bordered excel-table mb-0">
                <thead>
                    <tr>
                        <th># ID</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Product Name</th>
                        <th class="text-center">Quantity</th>
                        <th>Entry By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_qty = 0;
                    foreach ($data as $row): 
                    ?>
                        <tr>
                            <td>#<?php echo $row['id']; ?></td>
                            <td><?php echo date('d-m-Y H:i', strtotime($row['created_at'])); ?></td>
                            <td>
                                <span class="badge <?php echo $row['source'] == 'Entry' ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $row['source']; ?>
                                </span>
                            </td>
                            <td><?php echo $row['cat_name']; ?></td>
                            <td><strong><?php echo $row['product_name']; ?></strong></td>
                            <td class="text-center fw-bold <?php echo $row['quantity'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo $row['quantity']; ?>
                            </td>
                            <td><?php echo $row['creator_name']; ?></td>
                        </tr>
                        <?php $total_qty += $row['quantity']; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-light fw-bold">
                    <tr>
                        <td colspan="5" class="text-end">NET QUANTITY CHANGE:</td>
                        <td class="text-center text-primary"><?php echo $total_qty; ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <?php elseif ($report_type == 'ledger'): ?>
                <?php if (!$customer_id): ?>
                    <div class="p-5 text-center text-muted">Please select a customer to view ledger.</div>
                <?php else: ?>
                    <div class="p-3 bg-white border-bottom">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Customer: <strong><?php echo $data['info']['name']; ?></strong></h6>
                                <p class="small mb-0">Type: <?php echo $data['info']['type']; ?> | Phone: <?php echo $data['info']['phone']; ?></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <h4 class="mb-0">Current Balance: <span class="text-primary"><?php echo format_currency($data['info']['balance']); ?></span></h4>
                            </div>
                        </div>
                    </div>
                    <table class="table table-bordered excel-table mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th class="text-end">Credit (+)</th>
                                <th class="text-end">Debit (-)</th>
                                <th class="text-end">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $running_bal = $data['opening_bal'];
                            ?>
                            <tr class="table-light italic">
                                <td><?php echo date('d-m-Y', strtotime($start_date)); ?></td>
                                <td><em>Balance Brought Forward</em></td>
                                <td colspan="2"></td>
                                <td class="text-end fw-bold"><?php echo number_format($running_bal, 2); ?></td>
                            </tr>
                            <?php
                            foreach ($data['transactions'] as $t): 
                                if ($t['type'] == 'Credit') $running_bal += $t['amount'];
                                else $running_bal -= $t['amount'];
                            ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($t['created_at'])); ?></td>
                                    <td><?php echo $t['description']; ?></td>
                                    <td class="text-end text-success"><?php echo $t['type'] == 'Credit' ? number_format($t['amount'], 2) : '-'; ?></td>
                                    <td class="text-end text-danger"><?php echo $t['type'] == 'Debit' ? number_format($t['amount'], 2) : '-'; ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($running_bal, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Signature Section -->
                    <div class="report-signatures mt-5 pt-5">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="border-top pt-2 mx-3">Prepared By</div>
                            </div>
                            <div class="col-4">
                                <div class="border-top pt-2 mx-3">Checked By</div>
                            </div>
                            <div class="col-4">
                                <div class="border-top pt-2 mx-3">Authorized Signature</div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-5 mb-4 small text-muted border-top pt-3 mx-4">
                        Powered by <strong>sohojweb</strong>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyComprehensiveWhatsApp(btn) {
    const type   = '<?php echo ucfirst($report_type); ?>';
    const period = '<?php echo $start_date; ?> to <?php echo $end_date; ?>';
    const text   = tableToWhatsApp('*' + type + ' Report — ' + period + '*', '#comp-table');
    if (text) copyText(text, btn);
    else alert('No data to copy.');
}
</script>
<?php require_once '../../templates/footer.php'; ?>
