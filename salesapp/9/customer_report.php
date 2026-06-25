<?php
$pageTitle = 'Customer Analysis';
include 'header.php';

$f_from = $_GET['date_from'] ?? date('Y-m-01');
$f_to   = $_GET['date_to']   ?? date('Y-m-t');
$f_cid  = (int)($_GET['company_id'] ?? 0);
$f_route= (int)($_GET['route_id'] ?? 0);
$comp_q = $conn->query("SELECT id, name FROM companies ORDER BY name");
$route_q= $conn->query("SELECT id, route_name FROM routes ORDER BY route_name");

$where  = ["o.order_status=1", "o.order_date BETWEEN ? AND ?"];
$params = [$f_from, $f_to]; $types = 'ss';
if ($f_cid)   { $where[] = "o.company_id=?";  $params[] = $f_cid;   $types .= 'i'; }
if ($f_route) { $where[] = "o.route_id=?";    $params[] = $f_route; $types .= 'i'; }
$w = 'WHERE ' . implode(' AND ', $where);

$list_q = $conn->prepare(
    "SELECT s.shop_name, r.route_name,
     COUNT(DISTINCT o.id) AS orders,
     SUM(oi.quantity*oi.price) AS revenue,
     s.balance
     FROM orders o
     JOIN shops s ON s.id=o.shop_id
     JOIN routes r ON r.id=o.route_id
     LEFT JOIN order_items oi ON oi.order_id=o.id
     $w GROUP BY o.shop_id ORDER BY revenue DESC"
);
$list_q->bind_param($types, ...$params); $list_q->execute(); $rows = $list_q->get_result();
?>

<div class="page-header">
    <div><div class="page-title">Customer Analysis</div><div class="page-subtitle">Revenue by shop (customer)</div></div>
    <button onclick="window.print()" class="btn btn-ghost btn-sm print-hide"><i class="fa-solid fa-print"></i></button>
</div>

<form method="GET" style="margin-bottom:16px">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group"><label>Company</label><select name="company_id"><option value="">All</option><?php while($c=$comp_q->fetch_assoc()):?><option value="<?=$c['id']?>" <?=$f_cid==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endwhile;?></select></div>
        <div class="form-group"><label>Route</label><select name="route_id"><option value="">All</option><?php while($r=$route_q->fetch_assoc()):?><option value="<?=$r['id']?>" <?=$f_route==$r['id']?'selected':''?>><?=htmlspecialchars($r['route_name'])?></option><?php endwhile;?></select></div>
        <div class="form-group"><label>From</label><input type="date" name="date_from" id="date_from" value="<?=htmlspecialchars($f_from)?>"></div>
        <div class="form-group"><label>To</label><input type="date" name="date_to" id="date_to" value="<?=htmlspecialchars($f_to)?>"></div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Run</button>
    </div>
    <div class="date-presets" style="margin-bottom:12px">
        <button type="button" class="date-preset-btn" data-preset="month">This Month</button>
        <button type="button" class="date-preset-btn" data-preset="last_month">Last Month</button>
    </div>
</form>

<div class="card">
    <div class="card-header"><span class="card-title">Top Shops by Revenue</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Rank</th><th>Shop</th><th>Route</th><th>Orders</th><th>Balance</th><th class="text-right">Revenue (BDT)</th></tr></thead>
            <tbody>
                <?php if ($rows->num_rows > 0): $rank=0; while ($row=$rows->fetch_assoc()): $rank++; ?>
                <tr>
                    <td class="text-muted fw-600">#<?=$rank?></td>
                    <td class="fw-600 text-sm"><?=htmlspecialchars($row['shop_name'])?></td>
                    <td class="text-muted text-sm"><?=htmlspecialchars($row['route_name'])?></td>
                    <td><?=$row['orders']?></td>
                    <td class="<?=$row['balance']>0?'text-green':''?>"><?=number_format($row['balance'],0)?></td>
                    <td class="text-right fw-600"><?=number_format($row['revenue'],0)?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No data found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $list_q->close(); include 'footer.php'; ?>
