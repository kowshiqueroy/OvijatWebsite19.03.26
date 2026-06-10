<?php
require_once 'templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER]);

/**
 * MASTER INTELLIGENCE HUB - ULTIMATE OWNER EDITION
 */
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$monthly_target = (float)($_GET['monthly_target'] ?? 0);

// Helper for short money
if (!function_exists('fmt_money_short')) {
    function fmt_money_short($amount) {
        $abs = abs((float)$amount);
        if ($abs >= 10000000) return '৳' . number_format($amount / 10000000, 2) . ' Cr';
        if ($abs >= 100000) return '৳' . number_format($amount / 100000, 2) . ' L';
        if ($abs >= 1000) return '৳' . number_format($amount / 1000, 1) . 'K';
        return '৳' . number_format($amount, 0);
    }
}

// ---------------------------------------------------------
// DATA ENGINE
// ---------------------------------------------------------

// 1. Sales & Rep Performance
$sales_raw = fetch_all("
    SELECT SKIP_ISDELETE_FILTER s.*, c.name as customer_name, c.phone as customer_phone, c.type as customer_type, u_c.username as creator_name
    FROM sales_drafts s JOIN customers c ON s.customer_id = c.id JOIN users u_c ON s.created_by = u_c.id
    WHERE s.status = 'Confirmed' AND s.isDelete = 0 AND DATE(s.confirmed_at) BETWEEN ? AND ?
    ORDER BY s.confirmed_at DESC
", [$start_date, $end_date]);

$total_revenue = 0; $daily_sales = []; $cust_map = []; $status_counts = [];
$lifecycle_data = ['Pending'=>[], 'Loading'=>[], 'In Transit'=>[], 'Delivered'=>[], 'Failed'=>[], 'Returned'=>[]];
$pending_over_48 = 0; $rep_perf = [];

foreach ($sales_raw as $s) {
    $rev = (float)$s['grand_total']; $total_revenue += $rev;
    $day = date('Y-m-d', strtotime($s['confirmed_at']));
    if($day) $daily_sales[$day] = ($daily_sales[$day] ?? 0) + $rev;
    
    $st = $s['delivery_status'] ?: 'Pending';
    $status_counts[$st] = ($status_counts[$st] ?? 0) + 1;
    if(isset($lifecycle_data[$st])) $lifecycle_data[$st][] = $s;
    if($st == 'Pending' && floor((time() - strtotime($s['confirmed_at']))/3600) >= 48) $pending_over_48++;
    
    $rep = $s['creator_name'] ?: 'System';
    if(!isset($rep_perf[$rep])) $rep_perf[$rep] = ['sales'=>0, 'orders'=>0, 'returns'=>0];
    $rep_perf[$rep]['sales'] += $rev; $rep_perf[$rep]['orders']++;
    if($st == 'Returned') $rep_perf[$rep]['returns']++;

    if(!isset($cust_map[$s['customer_id']])) $cust_map[$s['customer_id']] = ['name'=>$s['customer_name'], 'val'=>0, 'count'=>0];
    $cust_map[$s['customer_id']]['val'] += $rev; $cust_map[$s['customer_id']]['count']++;
}
uasort($cust_map, fn($a,$b) => $b['val'] <=> $a['val']);
uasort($rep_perf, fn($a,$b) => $b['sales'] <=> $a['sales']);

// 2. Inventory & Velocity Intelligence
$inventory_raw = fetch_all("
    SELECT SKIP_ISDELETE_FILTER p.*, cat.name as cat_name,
    (SELECT SUM(si.billed_qty) FROM sales_items si JOIN sales_drafts sd ON si.draft_id = sd.id WHERE si.product_id = p.id AND sd.status = 'Confirmed' AND sd.isDelete = 0 AND si.isDelete = 0 AND DATE(sd.confirmed_at) BETWEEN ? AND ?) as period_sold_qty,
    (SELECT SUM(si.total) FROM sales_items si JOIN sales_drafts sd ON si.draft_id = sd.id WHERE si.product_id = p.id AND sd.status = 'Confirmed' AND sd.isDelete = 0 AND si.isDelete = 0 AND DATE(sd.confirmed_at) BETWEEN ? AND ?) as period_rev
    FROM products p JOIN categories cat ON p.category_id = cat.id WHERE p.isDelete = 0
    ORDER BY (p.stock_qty <= 0) DESC, (p.stock_qty <= 10) DESC, p.stock_qty ASC
", [$start_date, $end_date, $start_date, $end_date]);

$stock_val = 0; $low_stock_count = 0; $neg_stock = 0; $category_perf = [];
$fast_moving = []; $slow_moving = []; $idle_stock = [];

// Thresholds for velocity
$all_sold_qtys = array_filter(array_column($inventory_raw, 'period_sold_qty'));
$avg_velocity = !empty($all_sold_qtys) ? array_sum($all_sold_qtys)/count($all_sold_qtys) : 0;

foreach ($inventory_raw as $p) {
    $sq = (float)$p['stock_qty']; $tp = (float)$p['tp_rate']; $sold = (float)$p['period_sold_qty'];
    $stock_val += (max(0, $sq) * $tp);
    if($sq <= 10) $low_stock_count++; if($sq < 0) $neg_stock++;
    
    if($sold == 0) $idle_stock[] = $p;
    elseif($sold >= $avg_velocity * 1.5) $fast_moving[] = $p;
    elseif($sold <= $avg_velocity * 0.5) $slow_moving[] = $p;

    $cat = $p['cat_name'];
    if(!isset($category_perf[$cat])) $category_perf[$cat] = ['rev'=>0, 'val'=>0];
    $category_perf[$cat]['rev'] += (float)$p['period_rev'];
    $category_perf[$cat]['val'] += (max(0, $sq) * $tp);
}
uasort($category_perf, fn($a,$b) => $b['rev'] <=> $a['rev']);

// 3. Finance & Market Depth
$collections = fetch_all("SELECT SKIP_ISDELETE_FILTER t.*, c.name FROM transactions t JOIN customers c ON t.customer_id = c.id WHERE t.type = 'Credit' AND t.isDelete = 0 AND DATE(t.created_at) BETWEEN ? AND ?", [$start_date, $end_date]);
$total_col = array_sum(array_column($collections, 'amount'));
$daily_col = []; foreach($collections as $c) { $d = date('Y-m-d', strtotime($c['created_at'])); $daily_col[$d] = ($daily_col[$d]??0) + (float)$c['amount']; }

$market_debt = fetch_all("SELECT name, phone, type, balance FROM customers WHERE balance > 0 AND isDelete = 0 ORDER BY balance DESC");
$total_receivable = array_sum(array_column($market_debt, 'balance'));

// 4. Logistics
$trucks = fetch_all("SELECT SKIP_ISDELETE_FILTER tl.*, u.username FROM truck_loads tl JOIN users u ON tl.created_by = u.id WHERE tl.isDelete = 0 AND DATE(tl.created_at) BETWEEN ? AND ? ORDER BY tl.created_at DESC", [$start_date, $end_date]);
foreach ($trucks as $k => $t) {
    $t_invs = fetch_all("SELECT SKIP_ISDELETE_FILTER s.*, c.name as cust_name FROM truck_load_items tli JOIN sales_drafts s ON tli.invoice_id = s.id JOIN customers c ON s.customer_id = c.id WHERE tli.truck_load_id = ? AND tli.isDelete = 0 AND s.isDelete = 0", [$t['id']]);
    $consolidated = [];
    foreach ($t_invs as $ik => $inv) {
        $items = fetch_all("SELECT SKIP_ISDELETE_FILTER si.*, p.name FROM sales_items si JOIN products p ON si.product_id = p.id WHERE si.draft_id = ? AND si.isDelete = 0", [$inv['id']]);
        $t_invs[$ik]['items'] = $items;
        foreach ($items as $it) { $pid=$it['product_id']; if(!isset($consolidated[$pid])) $consolidated[$pid]=['name'=>$it['name'], 'qty'=>0]; $consolidated[$pid]['qty'] += ((int)$it['billed_qty']+(int)$it['free_qty']); }
    }
    $trucks[$k]['invoices'] = $t_invs; $trucks[$k]['summary'] = $consolidated; $trucks[$k]['load_val'] = array_sum(array_column($t_invs, 'grand_total'));
}

// ---------------------------------------------------------
// SMART LOGIC: HEALTH & WHATSAPP
// ---------------------------------------------------------
$achievement_pct = ($monthly_target > 0) ? round(($total_revenue / $monthly_target) * 100, 1) : null;
$col_ratio = ($total_revenue > 0) ? ($total_col / $total_revenue) : 1;
$dept_health = [
    'Sales' => ($achievement_pct !== null) ? (($achievement_pct < 50) ? 'danger' : (($achievement_pct < 80) ? 'warning' : 'success')) : ($total_revenue > 0 ? 'success' : 'warning'),
    'Finance' => ($col_ratio < 0.4) ? 'danger' : (($col_ratio < 0.7) ? 'warning' : 'success'),
    'Logistics' => ($pending_over_48 > 0) ? 'danger' : 'success',
    'Stock' => ($neg_stock > 0 || $low_stock_count > 5) ? 'danger' : 'success'
];

$whatsapp_text = "*OVIJAT DMD AUDIT*: " . date('d M') . "\n" .
    "Rev: " . fmt_money_short($total_revenue) . " (" . ($achievement_pct ? $achievement_pct."%" : "No Target") . ")\n" .
    "Coll: " . fmt_money_short($total_col) . " (" . round($col_ratio*100,1) . "%)\n" .
    "Receivable: " . fmt_money_short($total_receivable) . "\n" .
    "Low Stock: " . $low_stock_count . " items\n" .
    "Delayed Inv: " . $pending_over_48 . "\n" .
    "Top Rep: " . (count($rep_perf) > 0 ? key($rep_perf) : "N/A");

// Chart Prep
$chart_labels = []; $chart_sales = []; $chart_cols = [];
$period = new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), (new DateTime($end_date))->modify('+1 day'));
foreach($period as $dt) { $d = $dt->format('Y-m-d'); $chart_labels[] = $dt->format('d M'); $chart_sales[] = (float)($daily_sales[$d]??0); $chart_cols[] = (float)($daily_col[$d]??0); }
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    :root { --accent: #2563eb; --surface: #ffffff; --bg: #f8fafc; --danger: #ef4444; --warning: #f59e0b; --success: #22c55e; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; color: #1e293b; }
    .executive-card { border-radius: 16px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); background: white; }
    .hero-panel { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; border-radius: 20px; padding: 25px; box-shadow: 0 10px 25px rgba(15,23,42,0.15); }
    .kpi-tile { background: white; border-radius: 16px; padding: 20px; border-left: 5px solid var(--accent); height: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .health-dot { width: 14px; height: 14px; border-radius: 50%; display: inline-block; position: relative; }
    .dot-danger { background-color: var(--danger); box-shadow: 0 0 10px var(--danger); }
    .dot-warning { background-color: var(--warning); box-shadow: 0 0 10px var(--warning); }
    .dot-success { background-color: var(--success); box-shadow: 0 0 10px var(--success); }
    .nav-tabs-custom { background: white; padding: 6px; border-radius: 12px; display: inline-flex; gap: 4px; flex-wrap: wrap; border: 1px solid #e2e8f0; }
    .nav-tabs-custom .nav-link { border: none; border-radius: 8px; padding: 8px 16px; font-weight: 700; color: #64748b; font-size: 13px; }
    .nav-tabs-custom .nav-link.active { background: var(--accent); color: white; box-shadow: 0 4px 10px rgba(37,99,235,0.2); }
    .truck-header { background: #1e293b; color: white; padding: 15px 25px; border-radius: 16px 16px 0 0; }
    .table-danger-soft { background-color: #fef2f2 !important; }
    .chart-container-h { height: 320px; position: relative; }
    @media print { .no-print { display: none !important; } .tab-pane { display: block !important; opacity: 1 !important; position: static !important; } }
</style>

<div class="container-fluid py-3">
    <!-- MASTER HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 no-print gap-3">
        <div><h3 class="fw-bold text-dark mb-0">DMD Intelligence Console</h3><p class="text-muted small mb-0">Unified Strategic & Operational Data Hub</p></div>
        <form method="GET" class="d-flex gap-2 bg-white p-2 rounded-3 shadow-sm border">
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
            <input type="number" name="monthly_target" class="form-control form-control-sm" style="width:110px" value="<?php echo $monthly_target; ?>" placeholder="Target">
            <button type="submit" class="btn btn-primary btn-sm px-3">Sync Insights</button>
            <button type="button" onclick="window.print()" class="btn btn-dark btn-sm"><i class="fas fa-print"></i></button>
        </form>
    </div>

    <!-- COMMAND TOWER ROW -->
    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card hero-panel h-100">
                <div class="d-flex justify-content-between mb-3">
                    <div><h5 class="fw-bold mb-0 text-uppercase small opacity-75">Achievement</h5><div class="h2 fw-bold mb-0 mt-1"><?php echo $achievement_pct !== null ? $achievement_pct.'%' : 'NO TARGET'; ?></div></div>
                    <i class="fas fa-rocket fa-2x opacity-25"></i>
                </div>
                <div class="h3 fw-bold mb-1"><?php echo fmt_money_short($total_revenue); ?></div>
                <div class="progress mb-4" style="height: 10px; background: rgba(255,255,255,0.1)"><div class="progress-bar bg-primary shadow-sm" style="width:<?php echo $achievement_pct !== null ? min(100,(float)$achievement_pct) : '0'; ?>%"></div></div>
                
                <h6 class="fw-bold small mb-3 text-uppercase opacity-75 border-bottom border-secondary pb-2">Department Health Matrix</h6>
                <div class="row g-2 mb-4">
                    <?php foreach($dept_health as $name => $status): ?>
                        <div class="col-6"><div class="p-2 border border-secondary rounded d-flex align-items-center justify-content-between bg-dark bg-opacity-25 shadow-sm"><span class="small fw-bold"><?php echo $name; ?></span><span class="health-dot dot-<?php echo $status; ?>"></span></div></div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-success btn-sm w-100 fw-bold shadow-sm py-2" onclick="copyWA()"><i class="fab fa-whatsapp me-2"></i>COPY AUDIT REPORT</button>
                <textarea id="waSnap" class="visually-hidden"><?php echo $whatsapp_text; ?></textarea>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="row g-3">
                <div class="col-md-4"><div class="kpi-tile"><h6>Liquidity (Cash)</h6><h3 class="fw-bold mb-0 text-success"><?php echo format_currency($total_col); ?></h3><span class="small text-muted fw-bold"><?php echo round($col_ratio*100,1); ?>% Efficiency</span></div></div>
                <div class="col-md-4"><div class="kpi-tile" style="border-color:#f59e0b"><h6>Stock Asset</h6><h3 class="fw-bold mb-0"><?php echo format_currency($stock_val); ?></h3><span class="small text-muted fw-bold"><?php echo $low_stock_count; ?> Products Low</span></div></div>
                <div class="col-md-4"><div class="kpi-tile" style="border-color:#dc2626"><h6>Outstanding</h6><h3 class="fw-bold mb-0 text-danger"><?php echo fmt_money_short($total_receivable); ?></h3><span class="small text-muted fw-bold">Market Debt (Total)</span></div></div>
                <div class="col-12"><div class="card shadow-sm border-0"><div class="card-header bg-white py-3 fw-bold border-0 d-flex justify-content-between align-items-center"><span><i class="fas fa-bolt text-warning me-2"></i>Action Board</span><span class="badge bg-danger rounded-pill px-3"><?php echo count($lifecycle_data['Pending']); ?> PENDING DISPATCH</span></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr class="table-light"><th>Dept</th><th>Issue</th><th>Responsible</th><th>Action</th></tr></thead><tbody><?php if($pending_over_48 > 0) echo '<tr><td><span class="badge bg-danger">Logistics</span></td><td class="fw-bold">'.$pending_over_48.' Invoices delayed > 48h</td><td>Dispatch</td><td><span class="badge bg-primary">Assign Truck</span></td></tr>'; ?> <?php if($neg_stock > 0) echo '<tr><td><span class="badge bg-danger">Warehouse</span></td><td class="fw-bold">'.$neg_stock.' Items Negative Stock</td><td>Store</td><td><span class="badge bg-primary">Audit</span></td></tr>'; ?></tbody></table></div></div></div></div>
            </div>
        </div>
    </div>

    <!-- MAIN HUB TABS -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print flex-wrap gap-3">
        <ul class="nav nav-pills nav-tabs-custom shadow-sm" id="hubTabs">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-velocity"><i class="fas fa-tachometer-alt me-1"></i>Velocity Hub</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-inv"><i class="fas fa-boxes me-1"></i>Inventory Audit</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-debt"><i class="fas fa-hand-holding-usd me-1"></i>Market Debt</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-log"><i class="fas fa-truck-moving me-1"></i>Logistics detail</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-rep"><i class="fas fa-user-tie me-1"></i>Rep Perf.</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-sales"><i class="fas fa-list me-1"></i>Full Register</button></li>
        </ul>
        <input type="text" id="megaSearch" class="form-control search-ctrl shadow-sm border-0" placeholder="Instant Search Any Data Row...">
    </div>

    <div class="tab-content">
        <!-- VELOCITY HUB -->
        <div class="tab-pane fade show active" id="tab-velocity">
            <div class="row g-4">
                <div class="col-md-4"><div class="card h-100 shadow-sm border-0 border-top border-success border-4"><div class="card-header bg-white py-3 fw-bold">FAST MOVING (HOT ITEMS)</div><div class="card-body p-0"><table class="table table-sm table-hover mb-0"><thead><tr><th>Product</th><th class="text-end">Sold Val</th></tr></thead><tbody><?php foreach(array_slice($fast_moving,0,12) as $f): ?><tr><td class="ps-3 fw-bold"><?php echo $f['name']; ?></td><td class="text-end pe-3 fw-bold text-success"><?php echo fmt_money_short($f['period_rev']); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
                <div class="col-md-4"><div class="card h-100 shadow-sm border-0 border-top border-warning border-4"><div class="card-header bg-white py-3 fw-bold">SLOW MOVING (LOW VOLUME)</div><div class="card-body p-0"><table class="table table-sm table-hover mb-0"><thead><tr><th>Product</th><th class="text-end">Sold Val</th></tr></thead><tbody><?php foreach(array_slice($slow_moving,0,12) as $s): ?><tr><td class="ps-3"><?php echo $s['name']; ?></td><td class="text-end pe-3 fw-bold text-warning"><?php echo fmt_money_short($s['period_rev']); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
                <div class="col-md-4"><div class="card h-100 shadow-sm border-0 border-top border-danger border-4"><div class="card-header bg-white py-3 fw-bold">IDLE STOCK (DEAD CAPITAL)</div><div class="card-body p-0"><table class="table table-sm table-hover mb-0"><thead><tr><th>Product</th><th class="text-end">Asset Val</th></tr></thead><tbody><?php foreach(array_slice($idle_stock,0,12) as $i): ?><tr><td class="ps-3"><?php echo $i['name']; ?></td><td class="text-end pe-3 fw-bold text-danger"><?php echo fmt_money_short($i['stock_qty']*$i['tp_rate']); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
            </div>
        </div>

        <!-- INVENTORY AUDIT (LOW STOCK FIRST) -->
        <div class="tab-pane fade" id="tab-inv">
            <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-dark"><tr><th>Product Name</th><th>Category</th><th class="text-center">Stock</th><th class="text-end">Asset Value</th></tr></thead><tbody>
                <?php foreach($inventory_raw as $i): ?><tr class="report-row <?php echo ($i['stock_qty']<=10)?'table-danger-soft':''; ?>"><td><strong class="<?php echo ($i['stock_qty']<=10)?'text-danger':''; ?>"><?php echo $i['name']; ?></strong><?php if($i['stock_qty']<=0) echo ' <span class="badge bg-danger">OUT</span>'; elseif($i['stock_qty']<=10) echo ' <span class="badge bg-warning text-dark">LOW</span>'; ?></td><td><?php echo $i['cat_name']; ?></td><td class="text-center fw-bold h5 mb-0"><?php echo (int)$i['stock_qty']; ?></td><td class="text-end fw-bold text-primary"><?php echo format_currency($i['stock_qty']*$i['tp_rate']); ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div></div>
        </div>

        <!-- MARKET DEBT (OUTSTANDING) -->
        <div class="tab-pane fade" id="tab-debt">
            <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-dark"><tr><th>Customer Name</th><th>Contact</th><th>Type</th><th class="text-end">Total Due</th></tr></thead><tbody>
                <?php foreach($market_debt as $d): ?><tr class="report-row"><td><strong><?php echo $d['name']; ?></strong></td><td><?php echo $d['phone']; ?></td><td><span class="badge bg-light text-dark"><?php echo $d['type']; ?></span></td><td class="text-end fw-bold text-danger h5 mb-0"><?php echo format_currency($d['balance']); ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div></div>
        </div>

        <!-- LOGISTICS DETAIL -->
        <div class="tab-pane fade" id="tab-log">
            <?php foreach($trucks as $t): ?>
            <div class="truck-card report-row shadow-sm border-0 mb-4 overflow-hidden">
                <div class="truck-header d-flex justify-content-between align-items-center">
                    <div><h5 class="mb-0 fw-bold text-uppercase"><i class="fas fa-shipping-fast text-warning me-2"></i><?php echo $t['truck_no']; ?></h5><span class="small opacity-75"><?php echo $t['driver_name']; ?> | <?php echo date('d M, H:i', strtotime($t['created_at'])); ?></span></div>
                    <div class="text-end text-warning fw-bold h4 mb-0"><?php echo format_currency($t['load_val']); ?></div>
                </div>
                <div class="card-body p-4"><div class="row g-4"><div class="col-lg-4 border-end">
                    <h6 class="small fw-bold text-muted text-uppercase mb-3">Warehouse Loading List</h6>
                    <table class="table table-sm table-bordered table-hover" style="font-size:12px;"><tbody><?php foreach($t['summary'] as $p): ?><tr><td><?php echo $p['name']; ?></td><td class="text-center fw-bold bg-light"><?php echo $p['qty']; ?></td></tr><?php endforeach; ?></tbody></table>
                </div><div class="col-lg-8">
                    <?php foreach($t['invoices'] as $inv): ?><div class="invoice-card border shadow-sm">
                        <div class="d-flex justify-content-between fw-bold mb-2"><span>#<?php echo $inv['id']; ?> - <?php echo $inv['cust_name']; ?></span><span class="badge bg-white text-primary border px-3"><?php echo format_currency($inv['grand_total']); ?></span></div>
                        <div class="ps-3 border-start" style="font-size:11px;"><?php foreach($inv['items'] as $it): ?><div class="d-flex justify-content-between text-muted border-bottom py-1"><span><?php echo $it['name']; ?> x <?php echo (int)$it['billed_qty']+(int)$it['free_qty']; ?></span><span class="fw-bold">৳<?php echo number_format($it['total'],2); ?></span></div><?php endforeach; ?></div>
                    </div><?php endforeach; ?>
                </div></div></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- REP PERFORMANCE -->
        <div class="tab-pane fade" id="tab-rep">
            <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-dark"><tr><th>Executive / Rep Name</th><th class="text-center">Total Orders</th><th class="text-center">Returns</th><th class="text-end">Confirmed Sales</th></tr></thead><tbody>
                <?php foreach($rep_perf as $name => $data): ?><tr class="report-row"><td><strong><?php echo $name; ?></strong></td><td class="text-center fw-bold"><?php echo $data['orders']; ?></td><td class="text-center fw-bold text-danger"><?php echo $data['returns']; ?></td><td class="text-end fw-bold text-success h5 mb-0"><?php echo format_currency($data['sales']); ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div></div>
        </div>

        <!-- FULL REGISTER -->
        <div class="tab-pane fade" id="tab-sales">
            <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-dark"><tr><th>Inv #</th><th>Date</th><th>Customer</th><th>Issuer</th><th class="text-end">Amount</th></tr></thead><tbody>
                <?php foreach($sales_raw as $s): ?><tr class="report-row"><td>#<?php echo $s['id']; ?></td><td><?php echo date('d M', strtotime($s['confirmed_at'])); ?></td><td><strong><?php echo $s['customer_name']; ?></strong></td><td><?php echo $s['creator_name']; ?></td><td class="text-end fw-bold"><?php echo format_currency($s['grand_total']); ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div></div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    new Chart(document.getElementById('trendChart'), { type: 'line', data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [ { label: 'Revenue', data: <?php echo json_encode($chart_sales); ?>, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.1)', fill: true, tension: 0.4 }, { label: 'Collections', data: <?php echo json_encode($chart_cols); ?>, borderColor: '#16a34a', tension: 0.4 } ] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });
    new Chart(document.getElementById('statusChart'), { type: 'doughnut', data: { labels: <?php echo json_encode(array_keys($status_counts)); ?>, datasets: [{ data: <?php echo json_encode(array_values($status_counts)); ?>, backgroundColor: ['#eab308', '#3b82f6', '#8b5cf6', '#22c55e', '#ef4444', '#64748b'] }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom' } } } });
    document.getElementById('megaSearch').addEventListener('keyup', function() { let v = this.value.toLowerCase(); document.querySelectorAll('.report-row').forEach(r => { r.style.display = r.innerText.toLowerCase().includes(v) ? '' : 'none'; }); });
});
function copyWA() { const el = document.getElementById('waSnap'); el.select(); document.execCommand('copy'); alert('Executive Audit Report Copied to Clipboard!'); }
</script>
<?php require_once 'templates/footer.php'; ?>
