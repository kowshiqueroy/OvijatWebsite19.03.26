<?php
$pageTitle = 'Product Analysis';
include 'header.php';

$f_from = $_GET['date_from'] ?? date('Y-m-01');
$f_to   = $_GET['date_to']   ?? date('Y-m-t');
$f_cid  = (int)($_GET['company_id'] ?? 0);
$comp_q = $conn->query("SELECT id, name FROM companies ORDER BY name");

$where  = ["o.order_status=1", "o.order_date BETWEEN ? AND ?"];
$params = [$f_from, $f_to]; $types = 'ss';
if ($f_cid) { $where[] = "o.company_id=?"; $params[] = $f_cid; $types .= 'i'; }
$w = 'WHERE ' . implode(' AND ', $where);

$list_q = $conn->prepare(
    "SELECT i.item_name, SUM(oi.quantity) AS total_qty,
     SUM(oi.quantity*oi.price) AS total_rev,
     COUNT(DISTINCT oi.order_id) AS order_count,
     AVG(oi.price) AS avg_price
     FROM order_items oi
     JOIN orders o ON o.id=oi.order_id
     JOIN items i ON i.id=oi.item_id
     $w GROUP BY oi.item_id ORDER BY total_rev DESC"
);
$list_q->bind_param($types, ...$params); $list_q->execute(); $rows = $list_q->get_result();
?>

<div class="page-header">
    <div><div class="page-title">Product Analysis</div><div class="page-subtitle">Revenue and volume by product</div></div>
    <button onclick="window.print()" class="btn btn-ghost btn-sm print-hide"><i class="fa-solid fa-print"></i></button>
</div>

<form method="GET" style="margin-bottom:16px">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group"><label>Company</label><select name="company_id"><option value="">All</option><?php while($c=$comp_q->fetch_assoc()):?><option value="<?=$c['id']?>" <?=$f_cid==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endwhile;?></select></div>
        <div class="form-group"><label>From</label><input type="date" name="date_from" id="date_from" value="<?=htmlspecialchars($f_from)?>"></div>
        <div class="form-group"><label>To</label><input type="date" name="date_to" id="date_to" value="<?=htmlspecialchars($f_to)?>"></div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Run</button>
        <a href="product_report.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
    <div class="date-presets" style="margin-bottom:12px">
        <button type="button" class="date-preset-btn" data-preset="month">This Month</button>
        <button type="button" class="date-preset-btn" data-preset="last_month">Last Month</button>
    </div>
</form>

<div class="card">
    <div class="card-header"><span class="card-title">Products Ranked by Revenue</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Rank</th><th>Product</th><th>Units Sold</th><th>Orders</th><th>Avg Price</th><th class="text-right">Revenue (BDT)</th></tr></thead>
            <tbody>
                <?php if ($rows->num_rows > 0): $rank = 0; while ($row = $rows->fetch_assoc()): $rank++; ?>
                <tr>
                    <td class="text-muted fw-600">#<?=$rank?></td>
                    <td class="fw-600"><?=htmlspecialchars($row['item_name'])?></td>
                    <td><?=number_format($row['total_qty'],0)?></td>
                    <td><?=number_format($row['order_count'],0)?></td>
                    <td class="text-sm"><?=number_format($row['avg_price'],2)?></td>
                    <td class="text-right fw-600"><?=number_format($row['total_rev'],0)?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No data found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $list_q->close(); include 'footer.php'; ?>
