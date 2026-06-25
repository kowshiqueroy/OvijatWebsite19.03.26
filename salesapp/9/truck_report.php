<?php
$pageTitle = 'Truck Performance';
include 'header.php';

$f_from  = $_GET['date_from'] ?? date('Y-m-01');
$f_to    = $_GET['date_to']   ?? date('Y-m-t');
$f_cid   = (int)($_GET['company_id'] ?? 0);
$per_page= max(10, min(200, (int)($_GET['per_page'] ?? 50)));
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $per_page;

$where  = ["tl.created_at BETWEEN ? AND ?"];
$params = [$f_from.' 00:00:00', $f_to.' 23:59:59']; $types = 'ss';
if ($f_cid) { $where[] = 'tl.company_id=?'; $params[] = $f_cid; $types .= 'i'; }
$w = 'WHERE ' . implode(' AND ', $where);

/* Summary by status */
$status_q = $conn->query(
    "SELECT status, COUNT(*) AS c, COALESCE(SUM(total_value),0) AS val
     FROM truck_loads tl $w GROUP BY status"
    ? "SELECT status, COUNT(*) AS c, COALESCE(SUM(total_value),0) AS val FROM truck_loads tl $w GROUP BY status" : ""
);
$status_sum = [];
$status_q2 = $conn->prepare("SELECT status, COUNT(*) AS c, COALESCE(SUM(total_value),0) AS val FROM truck_loads tl $w GROUP BY status");
$status_q2->bind_param($types, ...$params); $status_q2->execute(); $sq = $status_q2->get_result();
while ($r = $sq->fetch_assoc()) $status_sum[$r['status']] = $r;
$status_q2->close();

/* SR performance */
$sr_q = $conn->prepare(
    "SELECT u.username, COUNT(*) AS loads, SUM(tl.total_orders) AS orders,
     COALESCE(SUM(CASE WHEN tl.status='delivered' THEN tl.total_value ELSE 0 END),0) AS delivered_val,
     COALESCE(SUM(tl.total_value),0) AS total_val,
     COUNT(CASE WHEN tl.status='delivered' THEN 1 END) AS delivered_count,
     COUNT(CASE WHEN tl.status='cancelled' THEN 1 END) AS cancelled_count
     FROM truck_loads tl JOIN users u ON u.id=tl.assigned_sr_id
     $w GROUP BY tl.assigned_sr_id ORDER BY delivered_val DESC LIMIT ? OFFSET ?"
);
$lp = array_merge($params, [$per_page, $offset]); $lt = $types . 'ii';
$sr_q->bind_param($lt, ...$lp); $sr_q->execute(); $rows = $sr_q->get_result();

$cnt_q = $conn->prepare("SELECT COUNT(DISTINCT assigned_sr_id) AS c FROM truck_loads tl $w");
$cnt_q->bind_param($types, ...$params); $cnt_q->execute();
$total = (int)$cnt_q->get_result()->fetch_assoc()['c']; $cnt_q->close();
$total_pages = max(1, (int)ceil($total / $per_page));

$comp_q = $conn->query("SELECT id, name FROM companies ORDER BY name");
$status_badge = ['delivered'=>'badge-green','cancelled'=>'badge-red','in_transit'=>'badge-purple','submitted'=>'badge-blue'];
?>

<div class="page-header">
    <div><div class="page-title">Truck Performance</div><div class="page-subtitle">Delivery success rates by SR</div></div>
    <button onclick="window.print()" class="btn btn-ghost btn-sm print-hide"><i class="fa-solid fa-print"></i></button>
</div>

<form method="GET">
    <div class="filter-bar">
        <div class="form-group"><label>Company</label>
            <select name="company_id">
                <option value="">All</option>
                <?php while ($c = $comp_q->fetch_assoc()): ?><option value="<?=$c['id']?>" <?=$f_cid==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endwhile; ?>
            </select>
        </div>
        <div class="form-group"><label>From</label><input type="date" name="date_from" id="date_from" value="<?=htmlspecialchars($f_from)?>"></div>
        <div class="form-group"><label>To</label><input type="date" name="date_to" id="date_to" value="<?=htmlspecialchars($f_to)?>"></div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Run</button>
        <a href="truck_report.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
    <div class="date-presets" style="margin-bottom:12px">
        <button type="button" class="date-preset-btn" data-preset="month">This Month</button>
        <button type="button" class="date-preset-btn" data-preset="last_month">Last Month</button>
    </div>
</form>

<!-- Status Summary -->
<div class="pipeline-grid mb-20">
<?php
$pl = ['draft'=>'Draft','submitted'=>'Submitted','approved'=>'Approved','loading'=>'Loading',
       'ready'=>'Ready','in_transit'=>'In Transit','delivered'=>'Delivered','cancelled'=>'Cancelled'];
$pc = ['draft'=>'var(--gray-500)','submitted'=>'var(--info)','approved'=>'#0f766e',
       'loading'=>'var(--warning)','ready'=>'#c2410c','in_transit'=>'#6d28d9',
       'delivered'=>'var(--primary)','cancelled'=>'var(--danger)'];
foreach ($pl as $s => $lbl): $sc = $status_sum[$s] ?? ['c'=>0,'val'=>0]; ?>
<div class="pipeline-col">
    <div class="pipeline-col-status" style="color:<?=$pc[$s]?>"><?=$lbl?></div>
    <div class="pipeline-col-count"><?=$sc['c']?></div>
    <div class="pipeline-col-label"><?=number_format($sc['val'],0)?> BDT</div>
</div>
<?php endforeach; ?>
</div>

<!-- SR Table -->
<div class="card">
    <div class="card-header"><span class="card-title">SR Delivery Performance</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>SR</th><th>Loads</th><th>Orders</th><th>Delivered</th><th>Cancelled</th><th>Delivery Rate</th><th class="text-right">Delivered Value</th></tr></thead>
            <tbody>
                <?php if ($rows->num_rows > 0): ?>
                    <?php while ($row = $rows->fetch_assoc()):
                        $rate = $row['loads'] > 0 ? round($row['delivered_count']/$row['loads']*100) : 0;
                    ?>
                    <tr>
                        <td class="fw-600"><?=htmlspecialchars($row['username'])?></td>
                        <td><?=$row['loads']?></td>
                        <td><?=$row['orders']?></td>
                        <td class="text-green fw-600"><?=$row['delivered_count']?></td>
                        <td class="text-red"><?=$row['cancelled_count']?></td>
                        <td>
                            <div class="flex" style="gap:8px">
                                <div class="progress-bar-wrap" style="flex:1">
                                    <div class="progress-bar-fill <?=$rate>=80?'':($rate>=50?'warn':'danger')?>" style="width:<?=$rate?>%"></div>
                                </div>
                                <span class="text-sm fw-600"><?=$rate?>%</span>
                            </div>
                        </td>
                        <td class="text-right fw-600"><?=number_format($row['delivered_val'],0)?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No data found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $sr_q->close(); include 'footer.php'; ?>
