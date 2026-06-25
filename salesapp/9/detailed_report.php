<?php
$pageTitle = 'Detailed Net Sales';
include 'header.php';

/* ── Filters ── */
$f_from   = $_GET['date_from']  ?? date('Y-m-01');
$f_to     = $_GET['date_to']    ?? date('Y-m-t');
$f_cid    = (int)($_GET['company_id'] ?? 0);
$f_sr     = (int)($_GET['sr_id']      ?? 0);
$f_route  = (int)($_GET['route_id']   ?? 0);
$f_shop   = (int)($_GET['shop_id']    ?? 0);
$f_item   = (int)($_GET['item_id']    ?? 0);
$f_group  = $_GET['group_by']   ?? 'none';
$generate = isset($_GET['run']);

/* ── Dropdowns ── */
$comp_q  = $conn->query("SELECT id, name FROM companies ORDER BY name");
$routes_q= $conn->query("SELECT id, route_name FROM routes WHERE status=1 ORDER BY route_name");
$shops_q = $conn->query("SELECT id, shop_name FROM shops WHERE status=1 ORDER BY shop_name LIMIT 300");
$items_q = $conn->query("SELECT id, item_name FROM items WHERE status=1 ORDER BY item_name");
$srs_q   = $conn->query("SELECT id, username FROM users WHERE role IN (2,3) AND status=1 ORDER BY username");
?>

<div class="page-header">
    <div><div class="page-title">Detailed Net Sales</div><div class="page-subtitle">Itemized orders with returns deducted, grouped by choice</div></div>
    <?php if ($generate): ?><button onclick="window.print()" class="btn btn-ghost btn-sm print-hide"><i class="fa-solid fa-print"></i></button><?php endif; ?>
</div>

<form method="GET" action="detailed_report.php">
    <div class="filter-bar">
        <div class="form-group"><label>Company</label><select name="company_id"><option value="">All</option><?php while($c=$comp_q->fetch_assoc()):?><option value="<?=$c['id']?>" <?=$f_cid==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endwhile;?></select></div>
        <div class="form-group"><label>SR</label><select name="sr_id"><option value="">All</option><?php while($u=$srs_q->fetch_assoc()):?><option value="<?=$u['id']?>" <?=$f_sr==$u['id']?'selected':''?>><?=htmlspecialchars($u['username'])?></option><?php endwhile;?></select></div>
        <div class="form-group"><label>Route</label><select name="route_id"><option value="">All</option><?php while($r=$routes_q->fetch_assoc()):?><option value="<?=$r['id']?>" <?=$f_route==$r['id']?'selected':''?>><?=htmlspecialchars($r['route_name'])?></option><?php endwhile;?></select></div>
        <div class="form-group"><label>Product</label><select name="item_id"><option value="">All</option><?php while($it=$items_q->fetch_assoc()):?><option value="<?=$it['id']?>" <?=$f_item==$it['id']?'selected':''?>><?=htmlspecialchars($it['item_name'])?></option><?php endwhile;?></select></div>
        <div class="form-group"><label>From</label><input type="date" name="date_from" id="date_from" value="<?=htmlspecialchars($f_from)?>"></div>
        <div class="form-group"><label>To</label><input type="date" name="date_to" id="date_to" value="<?=htmlspecialchars($f_to)?>"></div>
        <div class="form-group"><label>Group By</label>
            <select name="group_by">
                <option value="none"    <?=$f_group==='none'?'selected':''?>>None</option>
                <option value="date"    <?=$f_group==='date'?'selected':''?>>Date</option>
                <option value="route"   <?=$f_group==='route'?'selected':''?>>Route</option>
                <option value="shop"    <?=$f_group==='shop'?'selected':''?>>Shop</option>
                <option value="user"    <?=$f_group==='user'?'selected':''?>>Sales Rep</option>
                <option value="company" <?=$f_group==='company'?'selected':''?>>Company</option>
            </select>
        </div>
        <button type="submit" name="run" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-play"></i> Run</button>
        <a href="detailed_report.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
    <div class="date-presets" style="margin-bottom:12px">
        <button type="button" class="date-preset-btn" data-preset="month">This Month</button>
        <button type="button" class="date-preset-btn" data-preset="last_month">Last Month</button>
        <button type="button" class="date-preset-btn" data-preset="week">This Week</button>
    </div>
</form>

<?php if (!$generate): ?>
<div class="card text-center" style="padding:40px"><div class="text-muted"><i class="fa-solid fa-chart-bar" style="font-size:2rem;margin-bottom:12px;display:block"></i>Set filters and click <strong>Run</strong>.</div></div>
<?php else:

$where  = ["o.order_status=1", "o.order_date BETWEEN ? AND ?"];
$params = [$f_from, $f_to]; $types = 'ss';
if ($f_cid)   { $where[] = "o.company_id=$f_cid"; }
if ($f_sr)    { $where[] = "o.created_by=$f_sr"; }
if ($f_route) { $where[] = "o.route_id=$f_route"; }
if ($f_shop)  { $where[] = "o.shop_id=$f_shop"; }
if ($f_item)  { $where[] = "oi.item_id=$f_item"; }

$order_sql = ['none'=>'o.id DESC','date'=>'DATE(o.order_date) DESC, o.id DESC',
              'route'=>'r.route_name ASC, o.id DESC','shop'=>'s.shop_name ASC, o.id DESC',
              'user'=>'u.username ASC, o.id DESC','company'=>'c.name ASC, o.id DESC'];
$order_by  = $order_sql[$f_group] ?? 'o.id DESC';
$w = 'WHERE ' . implode(' AND ', $where);

$q = $conn->prepare(
    "SELECT o.id AS oid, DATE(o.order_date) AS order_date,
            c.name AS company_name, u.username, r.route_name, s.shop_name, i.item_name,
            oi.quantity AS gross_qty, oi.price,
            (oi.quantity * oi.price) AS gross_total,
            COALESCE((SELECT SUM(ori.return_qty) FROM order_return_items ori WHERE ori.order_id=o.id AND ori.item_id=oi.item_id),0) AS return_qty
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id=o.id
     LEFT JOIN items i ON i.id=oi.item_id
     LEFT JOIN companies c ON c.id=o.company_id
     LEFT JOIN users u ON u.id=o.created_by
     LEFT JOIN routes r ON r.id=o.route_id
     LEFT JOIN shops s ON s.id=o.shop_id
     $w ORDER BY $order_by"
);
$q->bind_param($types, ...$params); $q->execute(); $res = $q->get_result();

$report = []; $g_gross = $g_ret = $g_net = 0;
while ($row = $res->fetch_assoc()) {
    $key = match($f_group) {
        'date'    => date('d F Y', strtotime($row['order_date'])),
        'route'   => $row['route_name']   ?? 'N/A',
        'shop'    => $row['shop_name']    ?? 'N/A',
        'user'    => $row['username']     ?? 'N/A',
        'company' => $row['company_name'] ?? 'N/A',
        default   => 'all'
    };
    $oid = $row['oid'];
    if (!isset($report[$key][$oid])) {
        $report[$key][$oid] = ['date'=>$row['order_date'],'company'=>$row['company_name'],
            'user'=>$row['username'],'route'=>$row['route_name'],'shop'=>$row['shop_name'],
            'items'=>[],'gross'=>0,'ret'=>0,'net'=>0];
    }
    if ($row['item_name']) {
        $ret_t = $row['return_qty'] * $row['price'];
        $net_t = $row['gross_total'] - $ret_t;
        $report[$key][$oid]['items'][] = ['name'=>$row['item_name'],'gqty'=>$row['gross_qty'],
            'rqty'=>$row['return_qty'],'price'=>$row['price'],'gross'=>$row['gross_total'],'ret'=>$ret_t,'net'=>$net_t];
        $report[$key][$oid]['gross']+=$row['gross_total'];
        $report[$key][$oid]['ret']+=$ret_t; $report[$key][$oid]['net']+=$net_t;
        $g_gross+=$row['gross_total']; $g_ret+=$ret_t; $g_net+=$net_t;
    }
}
$q->close();
?>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);max-width:540px;margin-bottom:20px">
    <div class="kpi-card info"><div class="kpi-label">Gross</div><div class="kpi-value" style="font-size:1.3rem"><?=number_format($g_gross,0)?></div></div>
    <div class="kpi-card danger"><div class="kpi-label">Returns</div><div class="kpi-value" style="font-size:1.3rem"><?=number_format($g_ret,0)?></div></div>
    <div class="kpi-card"><div class="kpi-label">Net</div><div class="kpi-value" style="font-size:1.3rem"><?=number_format($g_net,0)?></div></div>
</div>

<div class="print-header">
    <h1><?=APP_NAME?> &mdash; Detailed Net Sales Report</h1>
    <p>Period: <?=$f_from?> to <?=$f_to?> | Generated: <?=date('d M Y H:i')?></p>
</div>

<?php if (!empty($report)): ?>
<div class="card">
    <div class="card-header"><span class="card-title">Itemized Transactions</span></div>
    <?php foreach ($report as $group_name => $orders):
        $sg=$sr=$sn=0; foreach ($orders as $o){$sg+=$o['gross'];$sr+=$o['ret'];$sn+=$o['net'];}
    ?>
        <?php if ($f_group !== 'none'): ?>
        <div style="background:var(--gray-100);padding:10px 16px;border-bottom:2px solid var(--border);display:flex;justify-content:space-between">
            <span class="fw-700"><?=htmlspecialchars($group_name)?></span>
            <span class="text-sm">Gross: <?=number_format($sg,0)?> | -Ret: <?=number_format($sr,0)?> | <strong>Net: <?=number_format($sn,0)?></strong></span>
        </div>
        <?php endif; ?>
        <div class="table-wrap"><table style="font-size:0.82rem">
            <thead><tr><th>Order</th><th>Company</th><th>SR</th><th>Shop / Route</th><th>Item</th><th class="text-right">Gross</th><th class="text-right text-red">Return</th><th class="text-right text-green">Net</th></tr></thead>
            <tbody>
                <?php foreach ($orders as $oid => $ord): if (empty($ord['items'])) continue; $first=true; foreach ($ord['items'] as $item): ?>
                <tr>
                    <?php if ($first): ?>
                    <td rowspan="<?=count($ord['items'])?>" style="vertical-align:top"><a href="#" style="color:var(--primary);font-weight:700">#<?=$oid?></a><div class="text-muted text-xs"><?=date('d M',strtotime($ord['date']))?></div></td>
                    <td rowspan="<?=count($ord['items'])?>" style="vertical-align:top" class="text-xs"><?=htmlspecialchars($ord['company']??'—')?></td>
                    <td rowspan="<?=count($ord['items'])?>" style="vertical-align:top" class="text-sm"><?=htmlspecialchars($ord['user']??'—')?></td>
                    <td rowspan="<?=count($ord['items'])?>" style="vertical-align:top"><span class="text-sm fw-600"><?=htmlspecialchars($ord['shop']??'—')?></span><div class="text-muted text-xs"><?=htmlspecialchars($ord['route']??'—')?></div></td>
                    <?php endif; ?>
                    <td><?=htmlspecialchars($item['name'])?><div class="text-muted text-xs"><?=$item['gqty']?>-<?=$item['rqty']?>=<?=$item['gqty']-$item['rqty']?> × <?=number_format($item['price'],2)?></div></td>
                    <td class="text-right"><?=number_format($item['gross'],0)?></td>
                    <td class="text-right text-red">-<?=number_format($item['ret'],0)?></td>
                    <td class="text-right fw-600 text-green"><?=number_format($item['net'],0)?></td>
                </tr>
                <?php $first=false; endforeach; endforeach; ?>
            </tbody>
        </table></div>
    <?php endforeach; ?>
    <div style="background:var(--dark);color:#fff;padding:14px 18px;display:flex;justify-content:space-between;border-radius:0 0 var(--card-radius) var(--card-radius)">
        <span class="fw-700">Grand Total</span>
        <span>Gross: <?=number_format($g_gross,0)?> | -Ret: <?=number_format($g_ret,0)?> | <strong style="font-size:1.1rem">Net: <?=number_format($g_net,0)?></strong></span>
    </div>
</div>
<?php else: ?>
<div class="card text-center" style="padding:40px"><div class="text-muted">No confirmed orders found for the selected filters.</div></div>
<?php endif;
endif; ?>

<?php include 'footer.php'; ?>
