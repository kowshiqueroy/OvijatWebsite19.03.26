<?php
$pageTitle = 'Cash Collections';
include 'header.php';

$f_from = $_GET['date_from'] ?? date('Y-m-01');
$f_to   = $_GET['date_to']   ?? date('Y-m-t');
$f_cid  = (int)($_GET['company_id'] ?? 0);
$f_appr = $_GET['approved'] ?? '';
$comp_q = $conn->query("SELECT id, name FROM companies ORDER BY name");

$where  = ["cc.collection_date BETWEEN ? AND ?"];
$params = [$f_from.' 00:00:00', $f_to.' 23:59:59']; $types = 'ss';
if ($f_cid)     { $where[] = 'cc.company_id=?'; $params[] = $f_cid; $types .= 'i'; }
if ($f_appr==='1') $where[] = 'cc.approved_at IS NOT NULL';
if ($f_appr==='0') $where[] = 'cc.approved_at IS NULL';
$w = 'WHERE ' . implode(' AND ', $where);

/* Summary */
$sum_q = $conn->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS total, COALESCE(SUM(CASE WHEN approved_at IS NOT NULL THEN amount ELSE 0 END),0) AS approved, COALESCE(SUM(CASE WHEN approved_at IS NULL THEN amount ELSE 0 END),0) AS pending FROM cash_collections cc $w");
$sum_q->bind_param($types, ...$params); $sum_q->execute(); $sum = $sum_q->get_result()->fetch_assoc(); $sum_q->close();

/* By SR */
$list_q = $conn->prepare(
    "SELECT u.username, COUNT(*) AS records,
     SUM(cc.amount) AS total,
     SUM(CASE WHEN cc.approved_at IS NOT NULL THEN cc.amount ELSE 0 END) AS approved,
     SUM(CASE WHEN cc.approved_at IS NULL THEN cc.amount ELSE 0 END) AS pending
     FROM cash_collections cc LEFT JOIN users u ON u.id=cc.collected_by
     $w GROUP BY cc.collected_by ORDER BY total DESC"
);
$list_q->bind_param($types, ...$params); $list_q->execute(); $rows = $list_q->get_result();
?>

<div class="page-header">
    <div><div class="page-title">Cash Collections</div><div class="page-subtitle">Collection summary by SR</div></div>
    <button onclick="window.print()" class="btn btn-ghost btn-sm print-hide"><i class="fa-solid fa-print"></i></button>
</div>

<form method="GET" style="margin-bottom:16px">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group"><label>Company</label><select name="company_id"><option value="">All</option><?php while($c=$comp_q->fetch_assoc()):?><option value="<?=$c['id']?>" <?=$f_cid==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endwhile;?></select></div>
        <div class="form-group"><label>Approval</label><select name="approved"><option value="">All</option><option value="1" <?=$f_appr==='1'?'selected':''?>>Approved</option><option value="0" <?=$f_appr==='0'?'selected':''?>>Pending</option></select></div>
        <div class="form-group"><label>From</label><input type="date" name="date_from" id="date_from" value="<?=htmlspecialchars($f_from)?>"></div>
        <div class="form-group"><label>To</label><input type="date" name="date_to" id="date_to" value="<?=htmlspecialchars($f_to)?>"></div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Run</button>
    </div>
    <div class="date-presets" style="margin-bottom:12px">
        <button type="button" class="date-preset-btn" data-preset="month">This Month</button>
        <button type="button" class="date-preset-btn" data-preset="last_month">Last Month</button>
    </div>
</form>

<!-- Summary KPIs -->
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);max-width:540px;margin-bottom:20px">
    <div class="kpi-card"><div class="kpi-label">Total</div><div class="kpi-value" style="font-size:1.3rem"><?=number_format($sum['total'],0)?></div><div class="kpi-sub"><?=$sum['c']?> records</div></div>
    <div class="kpi-card"><div class="kpi-label">Approved</div><div class="kpi-value" style="font-size:1.3rem;color:var(--primary)"><?=number_format($sum['approved'],0)?></div></div>
    <div class="kpi-card warning"><div class="kpi-label">Pending</div><div class="kpi-value" style="font-size:1.3rem"><?=number_format($sum['pending'],0)?></div></div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Collections by SR</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>SR</th><th>Records</th><th>Total (BDT)</th><th>Approved</th><th>Pending</th></tr></thead>
            <tbody>
                <?php if ($rows->num_rows > 0): while ($row=$rows->fetch_assoc()): ?>
                <tr>
                    <td class="fw-600"><?=htmlspecialchars($row['username']??'Unknown')?></td>
                    <td><?=$row['records']?></td>
                    <td class="fw-600"><?=number_format($row['total'],0)?></td>
                    <td class="text-green"><?=number_format($row['approved'],0)?></td>
                    <td class="text-yellow"><?=number_format($row['pending'],0)?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:30px">No data found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $list_q->close(); include 'footer.php'; ?>
