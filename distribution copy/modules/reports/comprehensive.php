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

$data = [];
if ($report_type == 'sales') {
    $sql = "SELECT s.*, c.name as customer_name, u.username as creator_name 
            FROM sales_drafts s 
            JOIN customers c ON s.customer_id = c.id 
            JOIN users u ON s.created_by = u.id 
            WHERE s.isDelete = 0 
            AND DATE(s.created_at) BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    
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
        $sales[$key]['items'] = fetch_all("SELECT i.*, p.name as product_name FROM sales_items i JOIN products p ON i.product_id = p.id WHERE i.draft_id = ? AND i.isDelete = 0", [$sale['id']]);
    }
    $data = $sales;
} elseif ($report_type == 'stock') {
    $sql = "SELECT se.*, p.name as product_name, u.username as creator_name, cat.name as cat_name
            FROM stock_entries se 
            JOIN products p ON se.product_id = p.id 
            JOIN categories cat ON p.category_id = cat.id
            JOIN users u ON se.user_id = u.id 
            WHERE se.isDelete = 0 
            AND DATE(se.created_at) BETWEEN ? AND ?";
    $params = [$start_date, $end_date];

    if ($search_query) {
        $sql .= " AND (p.name LIKE ? OR cat.name LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    $sql .= " ORDER BY se.created_at DESC";
    $data = fetch_all($sql, $params);
} elseif ($report_type == 'ledger') {
    if ($customer_id) {
        $customer_info = fetch_one("SELECT * FROM customers WHERE id = ?", [$customer_id]);
        $transactions = fetch_all("SELECT * FROM transactions WHERE customer_id = ? AND DATE(created_at) BETWEEN ? AND ? ORDER BY created_at ASC", [$customer_id, $start_date, $end_date]);
        $data = ['info' => $customer_info, 'transactions' => $transactions];
    }
}
?>

<style>
    .report-filter-box { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; }
    .excel-table { border: 2px solid #333; width: 100%; }
    .excel-table th { background: #e2e3e5 !important; border: 1px solid #999 !important; color: #000; font-weight: bold; text-transform: uppercase; font-size: 11px; padding: 5px; }
    .excel-table td { border: 1px solid #ccc !important; font-size: 12px; padding: 4px 6px; }
    .item-detail-row { background-color: #fcfcfc; font-size: 10px !important; }
    
    @media print {
        #sidebar-wrapper, .navbar, .btn, .alert, .no-print { display: none !important; }
        #page-content-wrapper { width: 100% !important; padding: 0 !important; }
        body { background: #fff !important; }
        .excel-table { width: 100% !important; border: 1px solid #000 !important; }
        .report-header-print { display: block !important; margin-bottom: 20px; }
    }
    .report-header-print { display: none; }
</style>

<div class="row mb-4 no-print">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3>Comprehensive Reports</h3>
        <button onclick="window.print()" class="btn btn-dark"><i class="fas fa-print me-2"></i> Print Report</button>
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
                    <option value="stock" <?php echo $report_type == 'stock' ? 'selected' : ''; ?>>Stock In Report</option>
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
            elseif($report_type == 'stock') echo 'Stock Inward Report';
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
            <table class="table table-bordered excel-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Inv #</th>
                        <th>Customer</th>
                        <th>Items Details</th>
                        <th class="text-end">Amt</th>
                        <th class="text-end">Disc</th>
                        <th class="text-end">Net Total</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_amt = 0;
                    foreach ($data as $row): 
                    ?>
                        <tr>
                            <td><?php echo date('d-m-y', strtotime($row['created_at'])); ?></td>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td><?php echo $row['customer_name']; ?></td>
                            <td class="p-0">
                                <table class="table table-sm table-borderless mb-0" style="font-size: 10px;">
                                    <?php foreach ($row['items'] as $item): ?>
                                        <tr>
                                            <td><?php echo $item['product_name']; ?></td>
                                            <td class="text-center"><?php echo $item['billed_qty']; ?> + <?php echo $item['free_qty']; ?></td>
                                            <td class="text-end"><?php echo number_format($item['total'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </td>
                            <td class="text-end"><?php echo number_format($row['total_amount'], 2); ?></td>
                            <td class="text-end text-danger"><?php echo number_format($row['discount'], 2); ?></td>
                            <td class="text-end fw-bold"><?php echo number_format($row['grand_total'], 2); ?></td>
                            <td><small><?php echo $row['creator_name']; ?></small></td>
                        </tr>
                        <?php $total_amt += $row['grand_total']; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-light fw-bold">
                    <tr>
                        <td colspan="6" class="text-end">TOTAL REVENUE:</td>
                        <td class="text-end text-primary"><?php echo format_currency($total_amt); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <?php elseif ($report_type == 'stock'): ?>
            <table class="table table-bordered excel-table mb-0">
                <thead>
                    <tr>
                        <th># ID</th>
                        <th>Date</th>
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
                            <td><?php echo $row['cat_name']; ?></td>
                            <td><strong><?php echo $row['product_name']; ?></strong></td>
                            <td class="text-center fw-bold"><?php echo $row['quantity']; ?></td>
                            <td><?php echo $row['creator_name']; ?></td>
                        </tr>
                        <?php $total_qty += $row['quantity']; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">TOTAL QUANTITY IN:</td>
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
                                <h4 class="mb-0">Balance: <span class="text-danger"><?php echo format_currency($data['info']['balance']); ?></span></h4>
                            </div>
                        </div>
                    </div>
                    <table class="table table-bordered excel-table mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th class="text-end">Debit (+)</th>
                                <th class="text-end">Credit (-)</th>
                                <th class="text-end">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $running_bal = 0; // This would ideally be calculated from opening balance
                            foreach ($data['transactions'] as $t): 
                                if ($t['type'] == 'Debit') $running_bal += $t['amount'];
                                else $running_bal -= $t['amount'];
                            ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($t['created_at'])); ?></td>
                                    <td><?php echo $t['description']; ?></td>
                                    <td class="text-end"><?php echo $t['type'] == 'Debit' ? number_format($t['amount'], 2) : '-'; ?></td>
                                    <td class="text-end text-success"><?php echo $t['type'] == 'Credit' ? number_format($t['amount'], 2) : '-'; ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($running_bal, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
