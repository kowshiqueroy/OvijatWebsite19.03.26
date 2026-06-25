<?php
$pageTitle = 'Reports';
include 'header.php';
if (!$is_manager) { header("Location: index.php"); exit; }

$cid = (int)$_SESSION['company_id'];

/* ── Filters ── */
$f_type   = $_GET['report'] ?? 'sales';
$f_from   = $_GET['date_from'] ?? date('Y-m-01');
$f_to     = $_GET['date_to']   ?? date('Y-m-t');
$f_sr     = (int)($_GET['sr_id']   ?? 0);
$f_route  = (int)($_GET['route_id']?? 0);
$f_div    = (int)($_GET['div_id']  ?? 0);
$f_grp    = (int)($_GET['grp_id']  ?? 0);
$per_page = max(10, min(200, (int)($_GET['per_page'] ?? 50)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

/* ── Dropdowns ── */
$srs_q    = $conn->query("SELECT id, username FROM users WHERE company_id=$cid AND role IN (2,3) AND status=1 ORDER BY username");
$routes_q = $conn->query("SELECT id, route_name FROM routes WHERE company_id=$cid AND status=1 ORDER BY route_name");
$divs_q   = $conn->query("SELECT id, name FROM divisions WHERE company_id=$cid AND status=1 ORDER BY name");
$grps_q   = $conn->query("SELECT sg.id, CONCAT(d.name,' › ',sg.name) AS label FROM sales_groups sg JOIN divisions d ON d.id=sg.division_id WHERE sg.company_id=$cid AND sg.status=1 ORDER BY d.name, sg.name");

/* ── Run the selected report ── */
$rows = null; $total = 0; $grand = 0; $total_pages = 1;

if ($f_type === 'sales') {
    /* === Sales Report === */
    $where  = ["o.company_id=$cid", "o.order_status=1", "o.order_date BETWEEN ? AND ?"];
    $params = [$f_from, $f_to]; $types = 'ss';
    if ($f_sr)    { $where[] = 'o.created_by=?';  $params[] = $f_sr;    $types .= 'i'; }
    if ($f_route) { $where[] = 'o.route_id=?';    $params[] = $f_route; $types .= 'i'; }
    $w = 'WHERE ' . implode(' AND ', $where);

    $cnt_q = $conn->prepare("SELECT COUNT(DISTINCT o.id) AS c, COALESCE(SUM(oi.quantity*oi.price),0) AS t FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id $w");
    $cnt_q->bind_param($types, ...$params); $cnt_q->execute();
    $sum = $cnt_q->get_result()->fetch_assoc(); $cnt_q->close();
    $total = (int)$sum['c']; $grand = (float)$sum['t'];
    $total_pages = max(1, (int)ceil($total / $per_page));

    $list_q = $conn->prepare(
        "SELECT o.id, o.order_date, o.delivery_date, u.username AS sr, s.shop_name, r.route_name,
         COALESCE(SUM(oi.quantity*oi.price),0) AS order_total
         FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id
         JOIN users u ON u.id=o.created_by JOIN shops s ON s.id=o.shop_id JOIN routes r ON r.id=o.route_id
         $w GROUP BY o.id ORDER BY o.order_date DESC LIMIT ? OFFSET ?"
    );
    $lp = array_merge($params, [$per_page, $offset]); $lt = $types . 'ii';
    $list_q->bind_param($lt, ...$lp); $list_q->execute(); $rows = $list_q->get_result();

} elseif ($f_type === 'delivery') {
    /* === Delivery / Truck Report === */
    $where  = ["tl.company_id=$cid", "tl.created_at BETWEEN ? AND ?"];
    $params = [$f_from.' 00:00:00', $f_to.' 23:59:59']; $types = 'ss';
    if ($f_sr)  { $where[] = 'tl.assigned_sr_id=?'; $params[] = $f_sr; $types .= 'i'; }
    $w = 'WHERE ' . implode(' AND ', $where);

    $cnt_q = $conn->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(total_value),0) AS t FROM truck_loads tl $w");
    $cnt_q->bind_param($types, ...$params); $cnt_q->execute();
    $sum = $cnt_q->get_result()->fetch_assoc(); $cnt_q->close();
    $total = (int)$sum['c']; $grand = (float)$sum['t'];
    $total_pages = max(1, (int)ceil($total / $per_page));

    $list_q = $conn->prepare(
        "SELECT tl.id, tl.load_name, tl.status, tl.total_orders, tl.total_value, tl.created_at,
         u.username AS sr_name
         FROM truck_loads tl JOIN users u ON u.id=tl.assigned_sr_id
         $w ORDER BY tl.created_at DESC LIMIT ? OFFSET ?"
    );
    $lp = array_merge($params, [$per_page, $offset]); $lt = $types . 'ii';
    $list_q->bind_param($lt, ...$lp); $list_q->execute(); $rows = $list_q->get_result();

} elseif ($f_type === 'target') {
    /* === Target Fulfillment === */
    $month = (int)date('n', strtotime($f_from));
    $year  = (int)date('Y', strtotime($f_from));

    $list_q = $conn->prepare(
        "SELECT u.username, t.target_amount, t.month, t.year,
         COALESCE((
           SELECT SUM(oi.quantity*oi.price)
           FROM truck_loads tl
           JOIN truck_load_orders tlo ON tlo.truck_load_id=tl.id AND tlo.is_active=1
           JOIN order_items oi ON oi.order_id=tlo.order_id
           WHERE tl.assigned_sr_id=u.id AND tl.company_id=? AND tl.status='delivered'
             AND MONTH(tl.delivered_at)=t.month AND YEAR(tl.delivered_at)=t.year
         ),0) AS achieved
         FROM targets t JOIN users u ON u.id=t.target_entity_id
         WHERE t.company_id=? AND t.target_type='sr' AND t.month=? AND t.year=?
         ORDER BY achieved/NULLIF(t.target_amount,0) DESC"
    );
    $list_q->bind_param("iiii", $cid, $cid, $month, $year); $list_q->execute();
    $rows = $list_q->get_result(); $total = $rows->num_rows;
}

$status_badge = ['draft'=>'badge-gray','submitted'=>'badge-blue','approved'=>'badge-teal',
    'loading'=>'badge-yellow','ready'=>'badge-orange','in_transit'=>'badge-purple',
    'delivered'=>'badge-green','cancelled'=>'badge-red','returned'=>'badge-brown'];
?>

<div class="page-header">
    <div><div class="page-title">Reports</div><div class="page-subtitle"><?= htmlspecialchars($company_name) ?></div></div>
    <button onclick="window.print()" class="btn btn-ghost btn-sm print-hide"><i class="fa-solid fa-print"></i> Print</button>
</div>

<!-- Report Type Tabs -->
<div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
    <?php foreach (['sales'=>'Sales Orders','delivery'=>'Truck Deliveries','target'=>'Target Fulfillment'] as $key=>$lbl): ?>
        <a href="reports.php?report=<?=$key?>&date_from=<?=$f_from?>&date_to=<?=$f_to?>"
           class="date-preset-btn <?= $f_type===$key?'active':'' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<form method="GET" action="reports.php">
    <input type="hidden" name="report" value="<?= htmlspecialchars($f_type) ?>">
    <div class="filter-bar">
        <div class="form-group">
            <label>From</label>
            <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($f_from) ?>">
        </div>
        <div class="form-group">
            <label>To</label>
            <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($f_to) ?>">
        </div>
        <?php if ($f_type !== 'target'): ?>
        <div class="form-group">
            <label>Sales Rep</label>
            <select name="sr_id">
                <option value="">All SRs</option>
                <?php if ($srs_q) while ($u = $srs_q->fetch_assoc()): ?>
                    <option value="<?=$u['id']?>" <?=$f_sr==$u['id']?'selected':''?>><?= htmlspecialchars($u['username']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($f_type === 'sales'): ?>
        <div class="form-group">
            <label>Route</label>
            <select name="route_id">
                <option value="">All Routes</option>
                <?php if ($routes_q) while ($r = $routes_q->fetch_assoc()): ?>
                    <option value="<?=$r['id']?>" <?=$f_route==$r['id']?'selected':''?>><?= htmlspecialchars($r['route_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group" style="max-width:90px">
            <label>Per Page</label>
            <select id="perPageSelect" name="per_page">
                <?php foreach ([25,50,100,200] as $n): ?><option value="<?=$n?>" <?=$per_page==$n?'selected':''?>><?=$n?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Run</button>
        <a href="reports.php?report=<?=$f_type?>" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
    <div class="date-presets" style="margin-bottom:12px">
        <button type="button" class="date-preset-btn" data-preset="today">Today</button>
        <button type="button" class="date-preset-btn" data-preset="week">This Week</button>
        <button type="button" class="date-preset-btn" data-preset="month">This Month</button>
        <button type="button" class="date-preset-btn" data-preset="last_month">Last Month</button>
    </div>
</form>

<!-- Summary KPIs -->
<?php if ($f_type !== 'target'): ?>
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
    <div class="kpi-card" style="flex:1;min-width:120px;padding:14px">
        <div class="kpi-label">Records</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= number_format($total) ?></div>
    </div>
    <div class="kpi-card info" style="flex:1;min-width:120px;padding:14px">
        <div class="kpi-label">Total Value</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= number_format($grand, 0) ?></div>
        <div class="kpi-sub">BDT</div>
    </div>
</div>
<?php endif; ?>

<!-- Print header -->
<div class="print-header">
    <h1><?= APP_NAME ?> &mdash; <?= ['sales'=>'Sales Report','delivery'=>'Delivery Report','target'=>'Target Report'][$f_type] ?></h1>
    <p>Period: <?= $f_from ?> to <?= $f_to ?> | Generated: <?= date('d M Y H:i') ?></p>
</div>

<!-- Results Table -->
<div class="card">
    <div class="card-header"><span class="card-title"><?= ['sales'=>'Order Details','delivery'=>'Truck Load Details','target'=>'Target Fulfillment'][$f_type] ?></span></div>
    <div class="table-wrap">

    <?php if ($f_type === 'sales' && $rows): ?>
        <table>
            <thead><tr><th>#</th><th>Date</th><th>SR</th><th>Route</th><th>Shop</th><th>Delivery</th><th class="text-right">Total</th></tr></thead>
            <tbody>
                <?php while ($row = $rows->fetch_assoc()): ?>
                <tr>
                    <td class="text-muted"><?=$row['id']?></td>
                    <td class="text-sm"><?=date('d M Y',strtotime($row['order_date']))?></td>
                    <td class="text-sm fw-600"><?=htmlspecialchars($row['sr']??'—')?></td>
                    <td class="text-sm text-muted"><?=htmlspecialchars($row['route_name'])?></td>
                    <td class="text-sm"><?=htmlspecialchars($row['shop_name'])?></td>
                    <td class="text-sm"><?=date('d M Y',strtotime($row['delivery_date']))?></td>
                    <td class="text-right fw-600"><?=number_format($row['order_total'],0)?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot><tr style="background:var(--gray-100)"><td colspan="6" class="text-right fw-700">Grand Total:</td><td class="text-right fw-700"><?=number_format($grand,0)?></td></tr></tfoot>
        </table>

    <?php elseif ($f_type === 'delivery' && $rows): ?>
        <table>
            <thead><tr><th>Load</th><th>SR</th><th>Status</th><th>Orders</th><th class="text-right">Value</th><th>Created</th></tr></thead>
            <tbody>
                <?php while ($row = $rows->fetch_assoc()): ?>
                <tr>
                    <td class="fw-600"><a href="truck_loads.php?view=<?=$row['id']?>" style="color:var(--primary)"><?=htmlspecialchars($row['load_name'])?></a></td>
                    <td class="text-sm"><?=htmlspecialchars($row['sr_name'])?></td>
                    <td><span class="badge <?=$status_badge[$row['status']]?>"><?=ucfirst(str_replace('_',' ',$row['status']))?></span></td>
                    <td><?=$row['total_orders']?></td>
                    <td class="text-right fw-600"><?=number_format($row['total_value'],0)?></td>
                    <td class="text-muted text-sm"><?=date('d M Y',strtotime($row['created_at']))?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot><tr style="background:var(--gray-100)"><td colspan="4" class="text-right fw-700">Total:</td><td class="text-right fw-700"><?=number_format($grand,0)?></td><td></td></tr></tfoot>
        </table>

    <?php elseif ($f_type === 'target' && $rows): ?>
        <table>
            <thead><tr><th>SR</th><th>Month</th><th>Target (BDT)</th><th>Achieved</th><th>%</th><th>Progress</th></tr></thead>
            <tbody>
                <?php while ($row = $rows->fetch_assoc()):
                    $pct = $row['target_amount'] > 0 ? min(110, round($row['achieved']/$row['target_amount']*100)) : 0;
                    $bar = $pct>=100?'gold':($pct>=80?'':($pct>=50?'warn':'danger'));
                ?>
                <tr>
                    <td class="fw-600"><?=htmlspecialchars($row['username'])?></td>
                    <td class="text-sm"><?=date('F Y',mktime(0,0,0,$row['month'],1,$row['year']))?></td>
                    <td><?=number_format($row['target_amount'],0)?></td>
                    <td class="fw-600 <?=$pct>=100?'text-green':($pct>=50?'text-yellow':'text-red')?>"><?=number_format($row['achieved'],0)?></td>
                    <td class="fw-700"><?=$pct?>%</td>
                    <td style="min-width:100px"><div class="progress-bar-wrap"><div class="progress-bar-fill <?=$bar?>" style="width:<?=$pct?>%"></div></div></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php else: ?>
        <div class="text-center text-muted" style="padding:40px">No data found.</div>
    <?php endif; ?>

    </div>

    <?php if ($total_pages > 1 && $f_type !== 'target'): ?>
    <div class="pagination" style="padding:16px">
        <?php $base = "reports.php?report=$f_type&date_from=$f_from&date_to=$f_to&sr_id=$f_sr&route_id=$f_route&per_page=$per_page&page="; ?>
        <a href="<?=$base?>1"                   class="page-btn <?=$page==1?'disabled':''?>"><i class="fa-solid fa-angles-left"></i></a>
        <a href="<?=$base.max(1,$page-1)?>"     class="page-btn <?=$page==1?'disabled':''?>"><i class="fa-solid fa-angle-left"></i></a>
        <?php for ($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
            <a href="<?=$base.$p?>" class="page-btn <?=$p==$page?'active':''?>"><?=$p?></a>
        <?php endfor; ?>
        <a href="<?=$base.min($total_pages,$page+1)?>" class="page-btn <?=$page==$total_pages?'disabled':''?>"><i class="fa-solid fa-angle-right"></i></a>
        <a href="<?=$base.$total_pages?>"              class="page-btn <?=$page==$total_pages?'disabled':''?>"><i class="fa-solid fa-angles-right"></i></a>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($list_q)) $list_q->close(); include 'footer.php'; ?>
