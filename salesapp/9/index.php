<?php
$pageTitle = 'Dashboard';
include 'header.php';

/* Company filter */
$sel_cid = (int)($_GET['company_id'] ?? 0);
$where_cid = $sel_cid ? "AND o.company_id=$sel_cid" : '';
$w_cid     = $sel_cid ? "WHERE company_id=$sel_cid" : '';

/* KPIs */
$kpi_q = $conn->query(
    "SELECT COUNT(DISTINCT o.id) AS orders,
            COALESCE(SUM(oi.quantity*oi.price),0) AS revenue,
            COUNT(DISTINCT o.shop_id) AS shops
     FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id
     WHERE o.order_status=1 $where_cid"
);
$kpi = $kpi_q->fetch_assoc();
$avg_order = $kpi['orders'] > 0 ? $kpi['revenue'] / $kpi['orders'] : 0;

/* Truck stats */
$tl_q = $conn->query(
    "SELECT status, COUNT(*) AS c FROM truck_loads " .
    ($sel_cid ? "WHERE company_id=$sel_cid" : "") . " GROUP BY status"
);
$tl_stats = []; while ($r = $tl_q->fetch_assoc()) $tl_stats[$r['status']] = $r['c'];

/* 30-day trend */
$trend_q = $conn->query(
    "SELECT DATE(o.created_at) AS d, COALESCE(SUM(oi.quantity*oi.price),0) AS rev
     FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id
     WHERE o.order_status=1 $where_cid AND o.created_at >= DATE_SUB(NOW(),INTERVAL 30 DAY)
     GROUP BY DATE(o.created_at) ORDER BY d ASC"
);
$trend_labels = []; $trend_data = [];
while ($r = $trend_q->fetch_assoc()) { $trend_labels[] = date('d M', strtotime($r['d'])); $trend_data[] = (float)$r['rev']; }

/* Top 5 products */
$prod_q = $conn->query(
    "SELECT i.item_name, SUM(oi.quantity*oi.price) AS rev
     FROM order_items oi JOIN orders o ON o.id=oi.order_id JOIN items i ON i.id=oi.item_id
     WHERE o.order_status=1 $where_cid GROUP BY oi.item_id ORDER BY rev DESC LIMIT 5"
);
$prod_labels = []; $prod_data = [];
while ($r = $prod_q->fetch_assoc()) { $prod_labels[] = $r['item_name']; $prod_data[] = (float)$r['rev']; }

/* Top 5 SRs */
$sr_q = $conn->query(
    "SELECT u.username, SUM(oi.quantity*oi.price) AS rev, COUNT(DISTINCT o.id) AS orders
     FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id JOIN users u ON u.id=o.created_by
     WHERE o.order_status=1 $where_cid GROUP BY o.created_by ORDER BY rev DESC LIMIT 5"
);
$sr_labels = []; $sr_data = [];
while ($r = $sr_q->fetch_assoc()) { $sr_labels[] = $r['username']; $sr_data[] = (float)$r['rev']; }

/* Top 5 routes */
$route_q = $conn->query(
    "SELECT r.route_name, SUM(oi.quantity*oi.price) AS rev
     FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id JOIN routes r ON r.id=o.route_id
     WHERE o.order_status=1 $where_cid GROUP BY o.route_id ORDER BY rev DESC LIMIT 5"
);
$route_labels = []; $route_data = [];
while ($r = $route_q->fetch_assoc()) { $route_labels[] = $r['route_name']; $route_data[] = (float)$r['rev']; }

/* Companies list for filter */
$comp_q = $conn->query("SELECT id, name FROM companies ORDER BY name ASC");
?>

<div class="page-header">
    <div><div class="page-title">Analytics Dashboard</div><div class="page-subtitle">System-wide sales and delivery performance</div></div>
    <form method="GET" style="display:flex;gap:6px;align-items:flex-end">
        <div>
            <label style="font-size:0.75rem;color:var(--gray-500)">Company</label>
            <select name="company_id" onchange="this.form.submit()" style="padding:6px 10px;width:auto;min-width:180px">
                <option value="">All Companies</option>
                <?php while ($c = $comp_q->fetch_assoc()): ?>
                    <option value="<?=$c['id']?>" <?=$sel_cid==$c['id']?'selected':''?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </form>
</div>

<!-- KPIs -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Total Revenue</div>
        <div class="kpi-value" style="font-size:1.5rem"><?= number_format($kpi['revenue'], 0) ?></div>
        <div class="kpi-sub">BDT (confirmed orders)</div>
    </div>
    <div class="kpi-card info">
        <div class="kpi-label">Confirmed Orders</div>
        <div class="kpi-value"><?= number_format($kpi['orders']) ?></div>
        <div class="kpi-sub">&nbsp;</div>
    </div>
    <div class="kpi-card warning">
        <div class="kpi-label">Active Shops</div>
        <div class="kpi-value"><?= number_format($kpi['shops']) ?></div>
        <div class="kpi-sub">with confirmed orders</div>
    </div>
    <div class="kpi-card danger">
        <div class="kpi-label">Avg Order Value</div>
        <div class="kpi-value" style="font-size:1.5rem"><?= number_format($avg_order, 0) ?></div>
        <div class="kpi-sub">BDT per order</div>
    </div>
</div>

<!-- Truck Load Summary -->
<div class="card mb-20">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-truck" style="color:var(--primary)"></i> Truck Load Overview</span></div>
    <div class="pipeline-grid">
    <?php
    $pl = ['draft'=>'Draft','submitted'=>'Submitted','approved'=>'Approved','loading'=>'Loading',
           'ready'=>'Ready','in_transit'=>'In Transit','delivered'=>'Delivered','cancelled'=>'Cancelled'];
    $pc = ['draft'=>'var(--gray-500)','submitted'=>'var(--info)','approved'=>'#0f766e','loading'=>'var(--warning)',
           'ready'=>'#c2410c','in_transit'=>'#6d28d9','delivered'=>'var(--primary)','cancelled'=>'var(--danger)'];
    foreach ($pl as $s => $lbl):
    ?>
        <div class="pipeline-col">
            <div class="pipeline-col-status" style="color:<?=$pc[$s]?>"><?=$lbl?></div>
            <div class="pipeline-col-count"><?= $tl_stats[$s] ?? 0 ?></div>
            <div class="pipeline-col-label">loads</div>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<!-- Charts -->
<div class="grid-layout md-2">
    <div class="card" style="grid-column:1/-1">
        <div class="card-header"><span class="card-title">30-Day Revenue Trend</span></div>
        <div style="height:280px"><canvas id="trendChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Top 5 Products</span></div>
        <div style="height:260px"><canvas id="prodChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Top 5 Sales Reps</span></div>
        <div style="height:260px"><canvas id="srChart"></canvas></div>
    </div>
    <div class="card" style="grid-column:1/-1">
        <div class="card-header"><span class="card-title">Top 5 Routes by Revenue</span></div>
        <div style="height:220px"><canvas id="routeChart"></canvas></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const colors = ['#10b981','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#06b6d4'];

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trend_labels) ?>,
        datasets: [{ label: 'Revenue (BDT)', data: <?= json_encode($trend_data) ?>,
            borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)',
            fill: true, tension: 0.3, pointRadius: 3, borderWidth: 2 }]
    },
    options: { responsive: true, maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('prodChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($prod_labels) ?>,
        datasets: [{ data: <?= json_encode($prod_data) ?>, backgroundColor: colors }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

new Chart(document.getElementById('srChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($sr_labels) ?>,
        datasets: [{ label: 'Revenue (BDT)', data: <?= json_encode($sr_data) ?>, backgroundColor: '#3b82f6' }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('routeChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($route_labels) ?>,
        datasets: [{ label: 'Revenue (BDT)', data: <?= json_encode($route_data) ?>, backgroundColor: '#f59e0b' }]
    },
    options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y',
        plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});
</script>

<?php include 'footer.php'; ?>
