<?php
require_once 'templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER]);

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// --- 1. Advanced Sales Analysis ---
$sales_raw = fetch_all("
    SELECT s.*, c.name as customer_name, c.type as customer_type, u.username as creator_name
    FROM sales_drafts s
    JOIN customers c ON s.customer_id = c.id
    JOIN users u ON s.created_by = u.id
    WHERE s.status = 'Confirmed' AND s.isDelete = 0 AND DATE(s.confirmed_at) BETWEEN ? AND ?
    ORDER BY s.confirmed_at DESC
", [$start_date, $end_date]);

$total_revenue = 0;
$total_discount = 0;
$total_invoices = count($sales_raw);
$customer_performance = [];
$status_distribution = [];

foreach ($sales_raw as $sale) {
    $total_revenue += $sale['grand_total'];
    $total_discount += $sale['discount'];
    
    $c_id = $sale['customer_id'];
    if (!isset($customer_performance[$c_id])) {
        $customer_performance[$c_id] = [
            'name' => $sale['customer_name'],
            'type' => $sale['customer_type'],
            'revenue' => 0,
            'count' => 0
        ];
    }
    $customer_performance[$c_id]['revenue'] += $sale['grand_total'];
    $customer_performance[$c_id]['count']++;

    $status = $sale['delivery_status'];
    $status_distribution[$status] = ($status_distribution[$status] ?? 0) + 1;
}
uasort($customer_performance, function($a, $b) { return $b['revenue'] - $a['revenue']; });

// --- 2. Advanced Inventory Analysis ---
$inventory_data = fetch_all("
    SELECT p.*, c.name as category_name,
    (SELECT SUM(billed_qty) FROM sales_items si JOIN sales_drafts sd ON si.draft_id = sd.id WHERE si.product_id = p.id AND sd.status = 'Confirmed' AND sd.isDelete = 0 AND DATE(sd.confirmed_at) BETWEEN ? AND ?) as period_sold,
    (SELECT SUM(free_qty) FROM sales_items si JOIN sales_drafts sd ON si.draft_id = sd.id WHERE si.product_id = p.id AND sd.status = 'Confirmed' AND sd.isDelete = 0 AND DATE(sd.confirmed_at) BETWEEN ? AND ?) as period_free
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.isDelete = 0
    ORDER BY (p.stock_qty <= 10) DESC, p.stock_qty ASC
", [$start_date, $end_date, $start_date, $end_date]);

// --- 5. Delivery Lifecycle Lists ---
$lifecycle_statuses = ['Pending', 'Loading', 'In Transit', 'Delivered', 'Failed', 'Returned'];
$lifecycle_data = [];
foreach ($lifecycle_statuses as $status) {
    $lifecycle_data[$status] = fetch_all("
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, u.username as creator_name
        FROM sales_drafts s
        JOIN customers c ON s.customer_id = c.id
        JOIN users u ON s.created_by = u.id
        WHERE s.delivery_status = ? AND s.status = 'Confirmed' AND s.isDelete = 0 AND DATE(s.confirmed_at) BETWEEN ? AND ?
        ORDER BY s.confirmed_at DESC
    ", [$status, $start_date, $end_date]);
}

$total_stock_value = 0;
$low_stock_count = 0;
foreach ($inventory_data as $inv) {
    $total_stock_value += ($inv['stock_qty'] * $inv['tp_rate']);
    if ($inv['stock_qty'] <= 10) $low_stock_count++;
}

// --- 3. Financial & Collection Analysis ---
$collections = fetch_all("
    SELECT t.*, c.name as customer_name
    FROM transactions t
    JOIN customers c ON t.customer_id = c.id
    WHERE t.type = 'Credit' AND t.isDelete = 0 AND DATE(t.created_at) BETWEEN ? AND ?
    ORDER BY t.created_at DESC
", [$start_date, $end_date]);

$total_collected = array_sum(array_column($collections, 'amount'));

// --- 4. Logistics & Truck Analysis ---
$trucks = fetch_all("
    SELECT tl.*, u.username as creator_name
    FROM truck_loads tl
    JOIN users u ON tl.created_by = u.id
    WHERE tl.isDelete = 0 AND DATE(tl.created_at) BETWEEN ? AND ?
    ORDER BY tl.created_at DESC
", [$start_date, $end_date]);

foreach ($trucks as $k => $t) {
    // Fetch invoices in this truck
    $invoices = fetch_all("
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.type as customer_type
        FROM truck_load_items tli
        JOIN sales_drafts s ON tli.invoice_id = s.id
        JOIN customers c ON s.customer_id = c.id
        WHERE tli.truck_load_id = ? AND tli.isDelete = 0
    ", [$t['id']]);

    $consolidated_items = [];

    foreach ($invoices as $ik => $inv) {
        // Fetch items for each invoice
        $items = fetch_all("
            SELECT si.*, p.name as product_name, p.tp_rate
            FROM sales_items si
            JOIN products p ON si.product_id = p.id
            WHERE si.draft_id = ? AND si.isDelete = 0
        ", [$inv['id']]);
        
        $invoices[$ik]['items'] = $items;

        // Consolidate for Load Sheet
        foreach ($items as $item) {
            $p_id = $item['product_id'];
            if (!isset($consolidated_items[$p_id])) {
                $consolidated_items[$p_id] = [
                    'name' => $item['product_name'],
                    'billed' => 0,
                    'free' => 0,
                    'total_qty' => 0,
                    'tp_rate' => $item['tp_rate']
                ];
            }
            $consolidated_items[$p_id]['billed'] += $item['billed_qty'];
            $consolidated_items[$p_id]['free'] += $item['free_qty'];
            $consolidated_items[$p_id]['total_qty'] += ($item['billed_qty'] + $item['free_qty']);
        }
    }
    $trucks[$k]['invoices'] = $invoices;
    $trucks[$k]['consolidated'] = $consolidated_items;
    $trucks[$k]['invoice_count'] = count($invoices);
    $trucks[$k]['load_value'] = array_sum(array_column($invoices, 'grand_total'));
}


// --- 6. DMD Executive Control Metrics: Alert, Aging, Target, Accountability, Exception ---
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$monthly_target = isset($_GET['monthly_target']) ? (float)$_GET['monthly_target'] : 0;
$achievement_percent = $monthly_target > 0 ? (($total_revenue / $monthly_target) * 100) : 0;
$target_gap = $monthly_target > 0 ? max(0, $monthly_target - $total_revenue) : 0;
$collection_gap = max(0, $total_revenue - $total_collected);
$collection_ratio = $total_revenue > 0 ? (($total_collected / $total_revenue) * 100) : 0;

$negative_stock_items = [];
$low_stock_items = [];
$total_stock_value = 0;
$low_stock_count = 0;
$negative_stock_count = 0;
foreach ($inventory_data as $inv) {
    $stock_qty = (float)($inv['stock_qty'] ?? 0);
    $tp_rate = (float)($inv['tp_rate'] ?? 0);
    $total_stock_value += max(0, $stock_qty) * $tp_rate;
    if ($stock_qty < 0) {
        $negative_stock_count++;
        $negative_stock_items[] = $inv;
    } elseif ($stock_qty <= 10) {
        $low_stock_count++;
        $low_stock_items[] = $inv;
    }
}

$pending_count = count($lifecycle_data['Pending'] ?? []);
$loading_count = count($lifecycle_data['Loading'] ?? []);
$in_transit_count = count($lifecycle_data['In Transit'] ?? []);
$delivered_count = count($lifecycle_data['Delivered'] ?? []);
$failed_count = count($lifecycle_data['Failed'] ?? []);
$returned_count = count($lifecycle_data['Returned'] ?? []);
$delivery_success_rate = $total_invoices > 0 ? (($delivered_count / $total_invoices) * 100) : 0;

// Approximate receivable aging using customer-wise FIFO allocation: collections up to end date are adjusted against oldest invoices first.
$aging_invoices = fetch_all("
    SELECT s.id, s.customer_id, c.name as customer_name, c.type as customer_type, s.grand_total, s.confirmed_at
    FROM sales_drafts s
    JOIN customers c ON s.customer_id = c.id
    WHERE s.status = 'Confirmed' AND s.isDelete = 0 AND DATE(s.confirmed_at) <= ?
    ORDER BY s.customer_id ASC, s.confirmed_at ASC
", [$end_date]);

$collections_till_end = fetch_all("
    SELECT customer_id, SUM(amount) as collected
    FROM transactions
    WHERE type = 'Credit' AND isDelete = 0 AND DATE(created_at) <= ?
    GROUP BY customer_id
", [$end_date]);
$collection_by_customer = [];
foreach ($collections_till_end as $row) {
    $collection_by_customer[$row['customer_id']] = (float)($row['collected'] ?? 0);
}

$aging_buckets = ['0-7' => 0, '8-15' => 0, '16-30' => 0, '31-60' => 0, '60+' => 0];
$receivable_customers = [];
$today_ts = strtotime($end_date);
foreach ($aging_invoices as $invoice) {
    $cid = $invoice['customer_id'];
    $invoice_amount = (float)($invoice['grand_total'] ?? 0);
    $available_collection = $collection_by_customer[$cid] ?? 0;
    $remaining = $invoice_amount;
    if ($available_collection > 0) {
        $adjusted = min($available_collection, $remaining);
        $remaining -= $adjusted;
        $collection_by_customer[$cid] -= $adjusted;
    }
    if ($remaining <= 0) continue;

    $invoice_ts = strtotime($invoice['confirmed_at']);
    $age_days = max(0, floor(($today_ts - $invoice_ts) / 86400));
    if ($age_days <= 7) $bucket = '0-7';
    elseif ($age_days <= 15) $bucket = '8-15';
    elseif ($age_days <= 30) $bucket = '16-30';
    elseif ($age_days <= 60) $bucket = '31-60';
    else $bucket = '60+';
    $aging_buckets[$bucket] += $remaining;

    if (!isset($receivable_customers[$cid])) {
        $receivable_customers[$cid] = [
            'name' => $invoice['customer_name'],
            'type' => $invoice['customer_type'],
            'total_due' => 0,
            'oldest_days' => 0,
            'buckets' => ['0-7' => 0, '8-15' => 0, '16-30' => 0, '31-60' => 0, '60+' => 0]
        ];
    }
    $receivable_customers[$cid]['total_due'] += $remaining;
    $receivable_customers[$cid]['oldest_days'] = max($receivable_customers[$cid]['oldest_days'], $age_days);
    $receivable_customers[$cid]['buckets'][$bucket] += $remaining;
}
$total_receivable = array_sum($aging_buckets);
uasort($receivable_customers, function($a, $b) { return $b['total_due'] <=> $a['total_due']; });
$top_receivable_customers = array_slice($receivable_customers, 0, 20, true);

// Accountability by creator / responsible user.
$accountability = [];
foreach ($sales_raw as $sale) {
    $creator = $sale['creator_name'] ?: 'Unknown';
    if (!isset($accountability[$creator])) {
        $accountability[$creator] = [
            'sales' => 0, 'invoices' => 0, 'delivered' => 0, 'pending' => 0, 'failed' => 0, 'returned' => 0, 'discount' => 0
        ];
    }
    $accountability[$creator]['sales'] += (float)$sale['grand_total'];
    $accountability[$creator]['discount'] += (float)$sale['discount'];
    $accountability[$creator]['invoices']++;
    $st = $sale['delivery_status'];
    if (isset($accountability[$creator][strtolower(str_replace(' ', '_', $st))])) {
        $accountability[$creator][strtolower(str_replace(' ', '_', $st))]++;
    }
}
uasort($accountability, function($a, $b) { return $b['sales'] <=> $a['sales']; });

// Exception board for DMD-level action.
$exception_board = [];
$add_exception = function($priority, $type, $message, $responsible, $amount = null, $action = 'Review') use (&$exception_board) {
    $exception_board[] = [
        'priority' => $priority,
        'type' => $type,
        'message' => $message,
        'responsible' => $responsible,
        'amount' => $amount,
        'action' => $action
    ];
};

if ($negative_stock_count > 0) {
    $add_exception('High', 'Inventory', $negative_stock_count . ' negative stock item(s) found', 'Store / Accounts / ERP Admin', null, 'Immediate stock reconciliation');
}
if ($low_stock_count > 0) {
    $add_exception('Medium', 'Inventory', $low_stock_count . ' low stock item(s) at or below reorder level', 'Store / Purchase / Production', null, 'Reorder / production planning');
}
foreach (($lifecycle_data['Pending'] ?? []) as $p) {
    $age_hours = floor((time() - strtotime($p['confirmed_at'])) / 3600);
    if ($age_hours >= 48) {
        $add_exception('High', 'Distribution', 'Invoice #' . $p['id'] . ' pending for ' . $age_hours . ' hours', 'Distribution / Sales Admin', (float)$p['grand_total'], 'Assign vehicle / explain delay');
    }
}
if ($pending_count > 0 && count($trucks) == 0) {
    $add_exception('High', 'Logistics', 'Pending invoices exist but no truck load found in selected period', 'Distribution In-charge', null, 'Vehicle allocation required');
}
if ($total_revenue > 0 && $total_collected <= 0) {
    $add_exception('High', 'Collection', 'Sales posted but no collection found in selected period', 'Accounts / Sales Admin', $total_revenue, 'Verify collection posting');
} elseif ($total_revenue > 0 && $collection_ratio < 30) {
    $add_exception('Medium', 'Collection', 'Collection ratio is below 30% against sales', 'Accounts / Sales GM', $collection_gap, 'Recovery follow-up');
}
if ($failed_count > 0 || $returned_count > 0) {
    $add_exception('Medium', 'Delivery', ($failed_count + $returned_count) . ' failed/returned delivery invoice(s)', 'Sales / Distribution / QC', null, 'Root cause analysis');
}
foreach ($sales_raw as $sale) {
    $base_amount = (float)($sale['total_amount'] ?? 0);
    $discount = (float)($sale['discount'] ?? 0);
    if ($base_amount > 0 && (($discount / $base_amount) * 100) > 5) {
        $add_exception('Medium', 'Discount', 'Invoice #' . $sale['id'] . ' has discount above 5%', 'Sales Admin / Accounts', $discount, 'Approval check');
    }
}
$priority_order = ['High' => 1, 'Medium' => 2, 'Low' => 3];
usort($exception_board, function($a, $b) use ($priority_order) {
    return ($priority_order[$a['priority']] ?? 9) <=> ($priority_order[$b['priority']] ?? 9);
});
$critical_alerts = count(array_filter($exception_board, function($x) { return $x['priority'] === 'High'; }));

?>

<style>
    :root { --primary-soft: #e7f1ff; --success-soft: #e6fcf5; --warning-soft: #fff9db; --danger-soft: #fff5f5; }
    .report-card { border-radius: 15px; border: none; transition: all 0.3s ease; }
    .report-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .nav-pills .nav-link { border-radius: 10px; padding: 12px 20px; font-weight: 600; color: #6c757d; margin-right: 10px; border: 1px solid transparent; }
    .nav-pills .nav-link.active { background-color: #0d6efd; color: white; }
    .nav-pills .nav-link:not(.active):hover { background-color: var(--primary-soft); color: #0d6efd; }
    .table thead th { background-color: #f8f9fa; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 2px solid #dee2e6; }
    .badge-soft-primary { background: var(--primary-soft); color: #0d6efd; }
    .badge-soft-success { background: var(--success-soft); color: #0ca678; }
    .badge-soft-warning { background: var(--warning-soft); color: #f08c00; }
    .badge-soft-danger { background: var(--danger-soft); color: #f03e3e; }
    .badge-soft-info { background: #e7f5ff; color: #1c7ed6; }
    .badge-soft-dark { background: #f1f3f5; color: #343a40; }
    .executive-panel { border-radius: 18px; border: 0; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06); }
    .kpi-mini { border-radius: 14px; background: #fff; border: 1px solid #eef2f7; padding: 14px 16px; height: 100%; }
    .priority-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 7px; }
    .priority-high { background: #fa5252; }
    .priority-medium { background: #fd7e14; }
    .priority-low { background: #40c057; }
    .table-danger-soft { background-color: #fff5f5 !important; }
    
    @media print {
        .no-print, .nav-pills, .filter-section { display: none !important; }
        .tab-pane { display: block !important; opacity: 1 !important; visibility: visible !important; position: static !important; }
        .card { border: 1px solid #eee !important; box-shadow: none !important; page-break-inside: avoid; }
    }
</style>

<div class="container-fluid py-4">
    <!-- Header & Filter -->
    <div class="row align-items-center mb-4 no-print">
        <div class="col-lg-6">
            <h2 class="fw-bold text-dark mb-1">Advanced Business Intelligence</h2>
            <p class="text-muted mb-0">Period: <span class="fw-bold text-primary"><?php echo date('M d, Y', strtotime($start_date)); ?></span> to <span class="fw-bold text-primary"><?php echo date('M d, Y', strtotime($end_date)); ?></span></p>
        </div>
        <div class="col-lg-6">
            <form method="GET" class="row g-2 justify-content-lg-end align-items-end filter-section">
                <div class="col-auto">
                    <label class="form-label small fw-bold">From</label>
                    <input type="date" name="start_date" class="form-control form-control-sm border-0 shadow-sm" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-bold">To</label>
                    <input type="date" name="end_date" class="form-control form-control-sm border-0 shadow-sm" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-bold">Target</label>
                    <input type="number" step="0.01" name="monthly_target" class="form-control form-control-sm border-0 shadow-sm" value="<?php echo e($monthly_target); ?>" placeholder="Monthly target">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm"><i class="fas fa-sync-alt me-1"></i> Update</button>
                    <button type="button" onclick="window.print()" class="btn btn-dark btn-sm px-3 shadow-sm"><i class="fas fa-print me-1"></i> Export PDF</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card report-card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-soft-primary text-primary"><i class="fas fa-shopping-cart"></i></div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-0 small uppercase">Total Sales</h6>
                            <h4 class="fw-bold mb-0"><?php echo format_currency($total_revenue); ?></h4>
                        </div>
                    </div>
                    <div class="small"><span class="text-success fw-bold"><?php echo $total_invoices; ?></span> Invoices Issued</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card report-card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-soft-success text-success"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-0 small uppercase">Collections</h6>
                            <h4 class="fw-bold mb-0"><?php echo format_currency($total_collected); ?></h4>
                        </div>
                    </div>
                    <div class="small"><span class="text-primary fw-bold"><?php echo count($collections); ?></span> Payments Received</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card report-card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-soft-warning text-warning"><i class="fas fa-boxes"></i></div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-0 small uppercase">Inventory Value</h6>
                            <h4 class="fw-bold mb-0"><?php echo format_currency($total_stock_value); ?></h4>
                        </div>
                    </div>
                    <div class="small"><span class="text-danger fw-bold"><?php echo $low_stock_count; ?></span> Items Low Stock</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card report-card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-soft-danger text-danger"><i class="fas fa-percentage"></i></div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-0 small uppercase">Avg Discount</h6>
                            <h4 class="fw-bold mb-0"><?php echo format_currency($total_invoices ? $total_discount / $total_invoices : 0); ?></h4>
                        </div>
                    </div>
                    <div class="small">Total Discount: <?php echo format_currency($total_discount); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- DMD Executive Control Tower -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card executive-panel">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold"><i class="fas fa-shield-alt me-2 text-primary"></i>DMD Executive Control Tower</h5>
                        <div class="small text-muted">Alert, aging, target, accountability and exception management at one glance.</div>
                    </div>
                    <span class="badge <?php echo $critical_alerts > 0 ? 'bg-danger' : 'bg-success'; ?> p-2"><?php echo $critical_alerts; ?> Critical Alert(s)</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3"><div class="kpi-mini"><div class="small text-muted fw-bold text-uppercase">Monthly Target</div><div class="h4 fw-bold mb-1"><?php echo format_currency($monthly_target); ?></div><div class="small text-muted">Set from filter box</div></div></div>
                        <div class="col-md-3"><div class="kpi-mini"><div class="small text-muted fw-bold text-uppercase">Achievement</div><div class="h4 fw-bold mb-1 <?php echo $achievement_percent >= 100 ? 'text-success' : 'text-warning'; ?>"><?php echo number_format($achievement_percent, 2); ?>%</div><div class="small text-muted">Gap: <?php echo format_currency($target_gap); ?></div></div></div>
                        <div class="col-md-3"><div class="kpi-mini"><div class="small text-muted fw-bold text-uppercase">Total Receivable</div><div class="h4 fw-bold mb-1 text-danger"><?php echo format_currency($total_receivable); ?></div><div class="small text-muted">FIFO aging estimate</div></div></div>
                        <div class="col-md-3"><div class="kpi-mini"><div class="small text-muted fw-bold text-uppercase">Delivery Success</div><div class="h4 fw-bold mb-1 text-success"><?php echo number_format($delivery_success_rate, 2); ?>%</div><div class="small text-muted">Delivered <?php echo $delivered_count; ?> of <?php echo $total_invoices; ?></div></div></div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0">Management Exception Board</h6>
                                <span class="small text-muted">Auto-prioritized</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead><tr><th>Priority</th><th>Type</th><th>Issue</th><th>Responsible</th><th class="text-end">Value</th><th>Required Action</th></tr></thead>
                                    <tbody>
                                    <?php if(empty($exception_board)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No critical exception found for this period.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach (array_slice($exception_board, 0, 12) as $ex): ?>
                                        <tr>
                                            <td><span class="priority-dot priority-<?php echo strtolower($ex['priority']); ?>"></span><span class="fw-bold"><?php echo e($ex['priority']); ?></span></td>
                                            <td><span class="badge bg-light text-dark"><?php echo e($ex['type']); ?></span></td>
                                            <td><?php echo e($ex['message']); ?></td>
                                            <td class="fw-bold"><?php echo e($ex['responsible']); ?></td>
                                            <td class="text-end"><?php echo $ex['amount'] !== null ? format_currency($ex['amount']) : '-'; ?></td>
                                            <td><?php echo e($ex['action']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <h6 class="fw-bold mb-2">Receivable Aging</h6>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th>Age</th><th class="text-end">Amount</th><th class="text-end">Share</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($aging_buckets as $age => $amount): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo e($age); ?> Days</td>
                                            <td class="text-end fw-bold"><?php echo format_currency($amount); ?></td>
                                            <td class="text-end"><?php echo $total_receivable > 0 ? number_format(($amount / $total_receivable) * 100, 1) : '0.0'; ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <h6 class="fw-bold mb-2">Top Due Customers</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th>Customer</th><th class="text-center">Oldest</th><th class="text-end">Due</th></tr></thead>
                                    <tbody>
                                    <?php if(empty($top_receivable_customers)): ?>
                                        <tr><td colspan="3" class="text-center py-3 text-muted">No due found.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach (array_slice($top_receivable_customers, 0, 8) as $rc): ?>
                                        <tr>
                                            <td><div class="fw-bold"><?php echo e($rc['name']); ?></div><div class="small text-muted"><?php echo e($rc['type']); ?></div></td>
                                            <td class="text-center"><?php echo (int)$rc['oldest_days']; ?>d</td>
                                            <td class="text-end fw-bold text-danger"><?php echo format_currency($rc['total_due']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h6 class="fw-bold mb-2">Accountability Matrix</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th>User</th><th class="text-end">Sales</th><th class="text-center">Inv</th><th class="text-center">Pending</th><th class="text-center">Failed/Returned</th><th class="text-end">Discount</th></tr></thead>
                                    <tbody>
                                    <?php if(empty($accountability)): ?>
                                        <tr><td colspan="6" class="text-center py-3 text-muted">No accountability data found.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach (array_slice($accountability, 0, 10) as $user => $ac): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo e($user); ?></td>
                                            <td class="text-end fw-bold"><?php echo format_currency($ac['sales']); ?></td>
                                            <td class="text-center"><?php echo (int)$ac['invoices']; ?></td>
                                            <td class="text-center <?php echo $ac['pending'] > 0 ? 'text-warning fw-bold' : ''; ?>"><?php echo (int)$ac['pending']; ?></td>
                                            <td class="text-center <?php echo ($ac['failed'] + $ac['returned']) > 0 ? 'text-danger fw-bold' : ''; ?>"><?php echo (int)($ac['failed'] + $ac['returned']); ?></td>
                                            <td class="text-end"><?php echo format_currency($ac['discount']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="fw-bold mb-2">Inventory Exception</h6>
                            <div class="row g-2 mb-2">
                                <div class="col-6"><div class="kpi-mini"><div class="small text-muted fw-bold">Negative Stock</div><div class="h4 text-danger fw-bold mb-0"><?php echo $negative_stock_count; ?></div></div></div>
                                <div class="col-6"><div class="kpi-mini"><div class="small text-muted fw-bold">Low Stock</div><div class="h4 text-warning fw-bold mb-0"><?php echo $low_stock_count; ?></div></div></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th>Product</th><th>Category</th><th class="text-end">Stock</th><th class="text-end">TP</th></tr></thead>
                                    <tbody>
                                    <?php $inventory_exceptions = array_merge(array_slice($negative_stock_items, 0, 5), array_slice($low_stock_items, 0, 5)); ?>
                                    <?php if(empty($inventory_exceptions)): ?>
                                        <tr><td colspan="4" class="text-center py-3 text-muted">No inventory exception found.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($inventory_exceptions as $ie): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo e($ie['name']); ?></td>
                                            <td><span class="badge bg-light text-dark"><?php echo e($ie['category_name']); ?></span></td>
                                            <td class="text-end fw-bold <?php echo $ie['stock_qty'] < 0 ? 'text-danger' : 'text-warning'; ?>"><?php echo e($ie['stock_qty']); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$ie['tp_rate'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Main Reporting Tabs -->
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-pills mb-4 no-print" id="pills-tab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="pills-sales-tab" data-bs-toggle="pill" data-bs-target="#pills-sales"><i class="fas fa-file-invoice me-2"></i>Sales</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="pills-inventory-tab" data-bs-toggle="pill" data-bs-target="#pills-inventory"><i class="fas fa-warehouse me-2"></i>Inventory</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="pills-logistics-tab" data-bs-toggle="pill" data-bs-target="#pills-logistics"><i class="fas fa-truck me-2"></i>Logistics</button>
                </li>
                <?php foreach ($lifecycle_statuses as $status): ?>
                <li class="nav-item">
                    <button class="nav-link" id="pills-<?php echo strtolower(str_replace(' ', '', $status)); ?>-tab" data-bs-toggle="pill" data-bs-target="#pills-<?php echo strtolower(str_replace(' ', '', $status)); ?>">
                        <span class="badge bg-light text-dark me-1"><?php echo count($lifecycle_data[$status]); ?></span> <?php echo $status; ?>
                    </button>
                </li>
                <?php endforeach; ?>
                <li class="nav-item">
                    <button class="nav-link" id="pills-customers-tab" data-bs-toggle="pill" data-bs-target="#pills-customers"><i class="fas fa-user-tie me-2"></i>Customers</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="pills-finance-tab" data-bs-toggle="pill" data-bs-target="#pills-finance"><i class="fas fa-coins me-2"></i>Finance</button>
                </li>
            </ul>

            <div class="tab-content" id="pills-tabContent">
                <?php foreach ($lifecycle_statuses as $status): 
                    $slug = strtolower(str_replace(' ', '', $status));
                    $data = $lifecycle_data[$status];
                ?>
                <div class="tab-pane fade" id="pills-<?php echo $slug; ?>">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold"><?php echo $status; ?> Deliveries</h5>
                            <span class="badge bg-primary"><?php echo count($data); ?> Invoices</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Inv #</th>
                                            <th>Customer</th>
                                            <th>Date</th>
                                            <th>Creator</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($data)): ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted">No <?php echo strtolower($status); ?> invoices in this period.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($data as $row): ?>
                                        <tr>
                                            <td><div class="fw-bold">#<?php echo $row['id']; ?></div></td>
                                            <td>
                                                <div class="fw-bold"><?php echo $row['customer_name']; ?></div>
                                                <div class="small text-muted"><?php echo $row['customer_phone']; ?></div>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($row['confirmed_at'])); ?></td>
                                            <td><?php echo $row['creator_name']; ?></td>
                                            <td class="text-end fw-bold"><?php echo format_currency($row['grand_total']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <!-- SALES DETAILS TAB -->
                <div class="tab-pane fade show active" id="pills-sales">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold">Full Sales Register</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date / Inv #</th>
                                            <th>Customer</th>
                                            <th>Issued By</th>
                                            <th class="text-center">Delivery Status</th>
                                            <th class="text-end">Base Amount</th>
                                            <th class="text-end">Discount</th>
                                            <th class="text-end">Grand Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sales_raw as $s): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold">#<?php echo $s['id']; ?></div>
                                                <div class="small text-muted"><?php echo date('d M Y', strtotime($s['confirmed_at'])); ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo $s['customer_name']; ?></div>
                                                <div class="badge-soft-primary badge"><?php echo $s['customer_type']; ?></div>
                                            </td>
                                            <td><?php echo $s['creator_name']; ?></td>
                                            <td class="text-center">
                                                <?php 
                                                $status_class = [
                                                    'Pending' => 'warning', 'Loading' => 'info', 'In Transit' => 'primary', 
                                                    'Delivered' => 'success', 'Failed' => 'danger', 'Returned' => 'dark'
                                                ];
                                                $sc = $status_class[$s['delivery_status']] ?? 'secondary';
                                                ?>
                                                <span class="badge badge-soft-<?php echo $sc; ?> p-2 px-3"><?php echo $s['delivery_status']; ?></span>
                                            </td>
                                            <td class="text-end"><?php echo number_format($s['total_amount'], 2); ?></td>
                                            <td class="text-end text-danger">-<?php echo number_format($s['discount'], 2); ?></td>
                                            <td class="text-end fw-bold text-primary"><?php echo number_format($s['grand_total'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CUSTOMER PERFORMANCE TAB -->
                <div class="tab-pane fade" id="pills-customers">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold">Customer Revenue Rankings</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 10%;">Rank</th>
                                            <th style="width: 30%;">Customer Name</th>
                                            <th style="width: 20%;">Client Type</th>
                                            <th style="width: 20%; text-align: center;">Invoices Count</th>
                                            <th style="width: 20%; text-align: right;">Total Contribution</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $rank = 1; foreach ($customer_performance as $cp): ?>
                                        <tr>
                                            <td class="text-center"><span class="badge bg-light text-dark rounded-circle p-2" style="width: 30px;"><?php echo $rank++; ?></span></td>
                                            <td><div class="fw-bold"><?php echo $cp['name']; ?></div></td>
                                            <td><span class="badge badge-soft-primary"><?php echo $cp['type']; ?></span></td>
                                            <td class="text-center fw-bold"><?php echo $cp['count']; ?></td>
                                            <td class="text-end fw-bold text-success"><?php echo format_currency($cp['revenue']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- INVENTORY HEALTH TAB -->
                <div class="tab-pane fade" id="pills-inventory">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold">Comprehensive Product Audit</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product Info</th>
                                            <th>Category</th>
                                            <th class="text-center">Available Stock</th>
                                            <th class="text-center text-primary">Sold (Billed)</th>
                                            <th class="text-center text-success">Promotional (Free)</th>
                                            <th class="text-end">Trade Value</th>
                                            <th class="text-end">Total Valuation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inventory_data as $i): ?>
                                        <tr class="<?php echo $i['stock_qty'] <= 10 ? 'table-danger-soft' : ''; ?>">
                                            <td>
                                                <div class="fw-bold"><?php echo $i['name']; ?></div>
                                                <?php if($i['stock_qty'] <= 10): ?>
                                                    <span class="text-danger small fw-bold"><i class="fas fa-exclamation-triangle"></i> CRITICAL STOCK</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-light text-dark"><?php echo $i['category_name']; ?></span></td>
                                            <td class="text-center fw-bold <?php echo $i['stock_qty'] <= 10 ? 'text-danger' : ''; ?>">
                                                <?php echo $i['stock_qty']; ?>
                                            </td>
                                            <td class="text-center text-primary fw-bold"><?php echo (int)$i['period_sold']; ?></td>
                                            <td class="text-center text-success fw-bold"><?php echo (int)$i['period_free']; ?></td>
                                            <td class="text-end"><?php echo number_format($i['tp_rate'], 2); ?></td>
                                            <td class="text-end fw-bold"><?php echo number_format($i['stock_qty'] * $i['tp_rate'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LOGISTICS HUB TAB -->
                <div class="tab-pane fade" id="pills-logistics">
                    <?php if(empty($trucks)): ?>
                        <div class="card border-0 shadow-sm py-5 text-center text-muted">No truck loads found for this period.</div>
                    <?php endif; ?>

                    <?php foreach ($trucks as $t): ?>
                    <div class="card shadow-sm border-0 mb-5 overflow-hidden report-card">
                        <div class="card-header bg-dark text-white py-3">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h4 class="mb-0 fw-bold"><i class="fas fa-shipping-fast me-2 text-warning"></i> MANIFEST: <?php echo $t['truck_no']; ?></h4>
                                    <div class="small opacity-75">Driver: <strong><?php echo $t['driver_name']; ?></strong> | Dispatch: <?php echo date('d M Y, H:i', strtotime($t['created_at'])); ?></div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="h3 mb-0 fw-bold text-warning"><?php echo format_currency($t['load_value']); ?></div>
                                    <div class="small opacity-75">Load Manifest Value (Trade Price Basis)</div>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <!-- Section 1: Consolidated Load Sheet (Warehouse Pick List) -->
                            <div class="mb-4">
                                <h6 class="text-uppercase fw-bold text-muted mb-3"><i class="fas fa-list-check me-2"></i> 1. Consolidated Load Sheet (Pick List)</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product Name</th>
                                                <th class="text-center" style="width: 100px;">Billed</th>
                                                <th class="text-center" style="width: 100px;">Free</th>
                                                <th class="text-center bg-soft-primary" style="width: 120px;">Total Qty</th>
                                                <th class="text-end" style="width: 150px;">Unit TP</th>
                                                <th class="text-end" style="width: 150px;">Sub-Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($t['consolidated'] as $p_item): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo $p_item['name']; ?></td>
                                                <td class="text-center"><?php echo $p_item['billed']; ?></td>
                                                <td class="text-center text-success"><?php echo $p_item['free']; ?></td>
                                                <td class="text-center fw-bold bg-soft-primary"><?php echo $p_item['total_qty']; ?></td>
                                                <td class="text-end"><?php echo number_format($p_item['tp_rate'], 2); ?></td>
                                                <td class="text-end fw-bold"><?php echo number_format($p_item['total_qty'] * $p_item['tp_rate'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <td colspan="5" class="text-end fw-bold">Calculated Total Valuation:</td>
                                                <td class="text-end fw-bold text-primary"><?php echo format_currency($t['load_value']); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Section 2: Detailed Invoice Breakdown -->
                            <h6 class="text-uppercase fw-bold text-muted mb-3"><i class="fas fa-file-invoice me-2"></i> 2. Detailed Invoice Manifest</h6>
                            <?php foreach ($t['invoices'] as $inv): ?>
                            <div class="invoice-entry border rounded-3 p-3 mb-3 shadow-sm bg-white">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="small text-muted text-uppercase fw-bold">Invoice Info</div>
                                        <div class="h5 mb-0 text-primary fw-bold">#<?php echo $inv['id']; ?></div>
                                        <div class="badge badge-soft-primary mt-1"><?php echo $inv['delivery_status']; ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="small text-muted text-uppercase fw-bold">Customer</div>
                                        <div class="fw-bold h6 mb-0"><?php echo $inv['customer_name']; ?></div>
                                        <div class="small text-muted"><i class="fas fa-user-tag me-1"></i> <?php echo $inv['customer_type']; ?> | <i class="fas fa-phone-alt me-1"></i> <?php echo $inv['customer_phone']; ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="small text-muted text-uppercase fw-bold">Notes / Instructions</div>
                                        <div class="small fw-bold text-dark italic"><?php echo $inv['general_note'] ?: '<span class="text-muted opacity-50">No notes</span>'; ?></div>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <div class="small text-muted text-uppercase fw-bold">Amount</div>
                                        <div class="h5 mb-0 fw-bold"><?php echo format_currency($inv['grand_total']); ?></div>
                                    </div>
                                </div>

                                <div class="mt-3 table-responsive">
                                    <table class="table table-sm table-borderless bg-light rounded px-2" style="font-size: 11px;">
                                        <thead>
                                            <tr class="text-muted border-bottom">
                                                <th style="width: 40%;">Item</th>
                                                <th class="text-center" style="width: 15%;">Rate</th>
                                                <th class="text-center" style="width: 10%;">Billed</th>
                                                <th class="text-center" style="width: 10%;">Free</th>
                                                <th class="text-end" style="width: 15%;">Total</th>
                                                <th style="width: 10%;">Item Note</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($inv['items'] as $item): ?>
                                            <tr>
                                                <td><i class="fas fa-caret-right me-1 text-primary"></i> <?php echo $item['product_name']; ?></td>
                                                <td class="text-center"><?php echo number_format($item['rate'], 2); ?></td>
                                                <td class="text-center fw-bold"><?php echo $item['billed_qty']; ?></td>
                                                <td class="text-center text-success fw-bold"><?php echo $item['free_qty']; ?></td>
                                                <td class="text-end fw-bold"><?php echo number_format($item['total'], 2); ?></td>
                                                <td><em class="text-muted"><?php echo $item['note']; ?></em></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="card-footer bg-light py-3 small border-0">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <i class="fas fa-user-edit me-2"></i> Prepared By: <strong><?php echo $t['creator_name']; ?></strong>
                                </div>
                                <div class="col-md-6 text-end">
                                    <i class="fas fa-comment-alt me-2"></i> Remarks: <strong><?php echo $t['remarks'] ?: 'N/A'; ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- FINANCIAL AUDIT TAB -->
                <div class="tab-pane fade" id="pills-finance">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold">Collection & Payment Ledger</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date / Time</th>
                                            <th>Customer Name</th>
                                            <th>Description</th>
                                            <th class="text-end">Amount Collected</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($collections)): ?>
                                            <tr><td colspan="4" class="text-center py-5 text-muted">No collections recorded for this period.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($collections as $c): ?>
                                        <tr>
                                            <td><?php echo date('d M Y, H:i', strtotime($c['created_at'])); ?></td>
                                            <td class="fw-bold"><?php echo $c['customer_name']; ?></td>
                                            <td class="text-muted italic"><?php echo $c['description']; ?></td>
                                            <td class="text-end fw-bold text-success" style="font-size: 16px;">+ <?php echo format_currency($c['amount']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold py-3">GRAND TOTAL COLLECTION:</td>
                                            <td class="text-end fw-bold text-dark py-3" style="font-size: 18px;"><?php echo format_currency($total_collected); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
