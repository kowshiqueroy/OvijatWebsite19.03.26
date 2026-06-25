<?php
$pageTitle = 'Target Fulfillment';
include 'header.php';

$f_month = (int)($_GET['month'] ?? date('n'));
$f_year  = (int)($_GET['year']  ?? date('Y'));
$f_cid   = (int)($_GET['company_id'] ?? 0);

$comp_q  = $conn->query("SELECT id, name FROM companies ORDER BY name");
$cid_sql = $f_cid ? "AND t.company_id=$f_cid" : '';

$rows = $conn->query(
    "SELECT t.*, u.username,
     c.name AS company_name,
     COALESCE((
       SELECT SUM(oi.quantity*oi.price)
       FROM truck_loads tl
       JOIN truck_load_orders tlo ON tlo.truck_load_id=tl.id AND tlo.is_active=1
       JOIN order_items oi ON oi.order_id=tlo.order_id
       WHERE tl.assigned_sr_id=t.target_entity_id AND tl.status='delivered'
         AND MONTH(tl.delivered_at)=$f_month AND YEAR(tl.delivered_at)=$f_year
         AND tl.company_id=t.company_id
     ),0) AS achieved
     FROM targets t
     JOIN users u ON u.id=t.target_entity_id
     JOIN companies c ON c.id=t.company_id
     WHERE t.target_type='sr' AND t.month=$f_month AND t.year=$f_year $cid_sql
     ORDER BY achieved/NULLIF(t.target_amount,0) DESC"
);
?>

<div class="page-header">
    <div><div class="page-title">Target Fulfillment</div>
    <div class="page-subtitle"><?=date('F Y',mktime(0,0,0,$f_month,1,$f_year))?></div></div>
    <button onclick="window.print()" class="btn btn-ghost btn-sm print-hide"><i class="fa-solid fa-print"></i></button>
</div>

<form method="GET" style="margin-bottom:16px">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group"><label>Company</label>
            <select name="company_id">
                <option value="">All</option>
                <?php while ($c = $comp_q->fetch_assoc()): ?><option value="<?=$c['id']?>" <?=$f_cid==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endwhile; ?>
            </select>
        </div>
        <div class="form-group"><label>Month</label>
            <select name="month">
                <?php for ($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m==$f_month?'selected':''?>><?=date('F',mktime(0,0,0,$m,1))?></option><?php endfor; ?>
            </select>
        </div>
        <div class="form-group"><label>Year</label>
            <select name="year">
                <?php for ($y=date('Y');$y>=date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$y==$f_year?'selected':''?>><?=$y?></option><?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Run</button>
    </div>
</form>

<div class="card">
    <div class="card-header"><span class="card-title">SR Target Fulfillment</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Company</th><th>SR</th><th>Target (BDT)</th><th>Achieved</th><th>Remaining</th><th>%</th><th>Progress</th></tr></thead>
            <tbody>
                <?php if ($rows && $rows->num_rows > 0): ?>
                    <?php while ($row = $rows->fetch_assoc()):
                        $pct = $row['target_amount'] > 0 ? min(110, round($row['achieved']/$row['target_amount']*100)) : 0;
                        $bar = $pct>=100?'gold':($pct>=80?'':($pct>=50?'warn':'danger'));
                        $rem = max(0, $row['target_amount'] - $row['achieved']);
                    ?>
                    <tr>
                        <td class="text-sm text-muted"><?=htmlspecialchars($row['company_name'])?></td>
                        <td class="fw-600"><?=htmlspecialchars($row['username'])?></td>
                        <td><?=number_format($row['target_amount'],0)?></td>
                        <td class="fw-600 <?=$pct>=100?'text-green':($pct>=50?'text-yellow':'text-red')?>"><?=number_format($row['achieved'],0)?></td>
                        <td class="text-muted"><?=number_format($rem,0)?></td>
                        <td class="fw-700 <?=$pct>=100?'text-green':($pct>=50?'text-yellow':'text-red')?>"><?=$pct?>%</td>
                        <td style="min-width:120px"><div class="progress-bar-wrap"><div class="progress-bar-fill <?=$bar?>" style="width:<?=$pct?>%"></div></div></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No targets set for this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
