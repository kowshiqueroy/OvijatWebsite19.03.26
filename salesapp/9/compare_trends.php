<?php
$pageTitle = 'Compare Trends';
include 'header.php';

/* ── Filters ── */
$interval   = $_GET['interval']   ?? 'month';
$f_from     = $_GET['date_from']  ?? date('Y-m-01', strtotime('-5 months'));
$f_to       = $_GET['date_to']    ?? date('Y-m-t');
$f_cid      = (int)($_GET['company_id'] ?? 0);

/* Date format for SQL */
$sql_fmt = match($interval) { 'day'=>"'%Y-%m-%d'", 'year'=>"'%Y'", default=>"'%Y-%m'" };
$php_fmt = match($interval) { 'day'=>'d M Y', 'year'=>'Y', default=>'M Y' };

/* WHERE */
$where = ["o.order_status=1"];
if ($f_cid) $where[] = "o.company_id=$f_cid";
$where[] = "o.order_date BETWEEN '$f_from' AND '$f_to'";
$w = 'WHERE ' . implode(' AND ', $where);

/* Trend data */
$trend_q = $conn->query(
    "SELECT DATE_FORMAT(o.order_date, $sql_fmt) AS period,
     COALESCE(SUM(oi.quantity*oi.price),0) AS rev
     FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id $w
     GROUP BY period ORDER BY period ASC"
);
$trend_labels = []; $trend_data = [];
while ($r = $trend_q->fetch_assoc()) {
    $d = ($interval==='month') ? date($php_fmt, strtotime($r['period'].'-01')) : date($php_fmt, strtotime($r['period'].($interval==='year'?'-01-01':'')));
    $trend_labels[] = $d; $trend_data[] = (float)$r['rev'];
}

/* Top 5 SRs */
$top_srs = $conn->query(
    "SELECT u.username, COALESCE(SUM(oi.quantity*oi.price),0) AS rev, COUNT(DISTINCT o.id) AS orders
     FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id JOIN users u ON u.id=o.created_by
     $w GROUP BY o.created_by ORDER BY rev DESC LIMIT 5"
);

/* Top 5 products */
$top_items = $conn->query(
    "SELECT i.item_name, SUM(oi.quantity) AS qty, SUM(oi.quantity*oi.price) AS rev
     FROM order_items oi JOIN orders o ON o.id=oi.order_id JOIN items i ON i.id=oi.item_id
     $w GROUP BY oi.item_id ORDER BY rev DESC LIMIT 5"
);

/* Top 5 routes */
$top_routes = $conn->query(
    "SELECT r.route_name, COALESCE(SUM(oi.quantity*oi.price),0) AS rev, COUNT(DISTINCT o.id) AS orders
     FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id JOIN routes r ON r.id=o.route_id
     $w GROUP BY o.route_id ORDER BY rev DESC LIMIT 5"
);

/* Top 5 shops */
$top_shops = $conn->query(
    "SELECT s.shop_name, COALESCE(SUM(oi.quantity*oi.price),0) AS rev
     FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id JOIN shops s ON s.id=o.shop_id
     $w GROUP BY o.shop_id ORDER BY rev DESC LIMIT 5"
);

$comp_q = $conn->query("SELECT id, name FROM companies ORDER BY name");
?>

<div class="page-header">
    <div><div class="page-title">Trend Analytics</div><div class="page-subtitle">Sales trends and top performer comparisons</div></div>
    <button onclick="window.print()" class="btn btn-ghost btn-sm print-hide"><i class="fa-solid fa-print"></i></button>
</div>

<form method="GET" style="margin-bottom:16px">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group"><label>Interval</label>
            <select name="interval">
                <option value="day"   <?=$interval==='day'?'selected':''?>>Daily</option>
                <option value="month" <?=$interval==='month'?'selected':''?>>Monthly</option>
                <option value="year"  <?=$interval==='year'?'selected':''?>>Yearly</option>
            </select>
        </div>
        <div class="form-group"><label>From</label><input type="date" name="date_from" id="date_from" value="<?=htmlspecialchars($f_from)?>"></div>
        <div class="form-group"><label>To</label><input type="date" name="date_to" id="date_to" value="<?=htmlspecialchars($f_to)?>"></div>
        <div class="form-group"><label>Company</label>
            <select name="company_id"><option value="">All</option>
                <?php while($c=$comp_q->fetch_assoc()):?><option value="<?=$c['id']?>" <?=$f_cid==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-rotate"></i> Update</button>
        <a href="compare_trends.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
    <div class="date-presets" style="margin-bottom:12px">
        <button type="button" class="date-preset-btn" data-preset="month">This Month</button>
        <button type="button" class="date-preset-btn" data-preset="last_month">Last Month</button>
    </div>
</form>

<!-- Revenue Trend Chart -->
<div class="card mb-20">
    <div class="card-header"><span class="card-title">Revenue Trend (<?= ucfirst($interval) ?>)</span></div>
    <div style="height:280px"><canvas id="trendChart"></canvas></div>
</div>

<div class="grid-layout md-2">
    <!-- Top SRs -->
    <div class="card">
        <div class="card-header"><span class="card-title">Top Sales Reps</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>SR</th><th>Orders</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                    <?php if ($top_srs && $top_srs->num_rows > 0): while ($r=$top_srs->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-600"><?=htmlspecialchars($r['username'])?></td>
                        <td><?=$r['orders']?></td>
                        <td class="text-right fw-600"><?=number_format($r['rev'],0)?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="3" class="text-center text-muted" style="padding:20px">No data.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Products -->
    <div class="card">
        <div class="card-header"><span class="card-title">Top Products</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Product</th><th>Units</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                    <?php if ($top_items && $top_items->num_rows > 0): while ($r=$top_items->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-600 text-sm"><?=htmlspecialchars($r['item_name'])?></td>
                        <td><?=number_format($r['qty'],0)?></td>
                        <td class="text-right fw-600"><?=number_format($r['rev'],0)?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="3" class="text-center text-muted" style="padding:20px">No data.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Routes -->
    <div class="card">
        <div class="card-header"><span class="card-title">Top Routes</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Route</th><th>Orders</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                    <?php if ($top_routes && $top_routes->num_rows > 0): while ($r=$top_routes->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-600"><?=htmlspecialchars($r['route_name'])?></td>
                        <td><?=$r['orders']?></td>
                        <td class="text-right fw-600"><?=number_format($r['rev'],0)?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="3" class="text-center text-muted" style="padding:20px">No data.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Shops -->
    <div class="card">
        <div class="card-header"><span class="card-title">Top Shops</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Shop</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                    <?php if ($top_shops && $top_shops->num_rows > 0): while ($r=$top_shops->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-600 text-sm"><?=htmlspecialchars($r['shop_name'])?></td>
                        <td class="text-right fw-600"><?=number_format($r['rev'],0)?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="2" class="text-center text-muted" style="padding:20px">No data.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trend_labels) ?>,
        datasets: [{
            label: 'Revenue (BDT)',
            data: <?= json_encode($trend_data) ?>,
            borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)',
            fill: true, tension: 0.3, pointRadius: 3, borderWidth: 2
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } },
        plugins: { legend: { display: false } }
    }
});
</script>

<?php include 'footer.php'; ?>
