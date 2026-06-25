<?php
$pageTitle = 'My Target';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$uid = (int)$_SESSION['user_id'];

/* Month selector */
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));
$from  = sprintf('%04d-%02d-01', $year, $month);
$to    = date('Y-m-t', strtotime($from));

/* Fetch my target */
$stmt = $conn->prepare("SELECT * FROM targets WHERE company_id=? AND target_type='sr' AND target_entity_id=? AND month=? AND year=?");
$stmt->bind_param("iiii", $cid, $uid, $month, $year); $stmt->execute();
$target = $stmt->get_result()->fetch_assoc(); $stmt->close();

$target_amount = (float)($target['target_amount'] ?? 0);

/* My achieved (delivered truck loads this month) */
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(oi.quantity*oi.price),0) AS achieved
     FROM truck_loads tl
     JOIN truck_load_orders tlo ON tlo.truck_load_id=tl.id AND tlo.is_active=1
     JOIN order_items oi ON oi.order_id=tlo.order_id
     WHERE tl.assigned_sr_id=? AND tl.company_id=? AND tl.status='delivered'
       AND MONTH(tl.delivered_at)=? AND YEAR(tl.delivered_at)=?"
);
$stmt->bind_param("iiii", $uid, $cid, $month, $year); $stmt->execute();
$achieved = (float)$stmt->get_result()->fetch_assoc()['achieved']; $stmt->close();

$remaining   = max(0, $target_amount - $achieved);
$target_pct  = $target_amount > 0 ? min(110, round($achieved / $target_amount * 100)) : 0;
$bar_class   = $target_pct >= 100 ? 'gold' : ($target_pct >= 80 ? '' : ($target_pct >= 50 ? 'warn' : 'danger'));
$days_left   = max(0, (int)((strtotime($to) - time()) / 86400));

/* Monthly history (last 6 months) */
$history = [];
for ($i = 0; $i < 6; $i++) {
    $hm = (int)date('n', strtotime("-$i months"));
    $hy = (int)date('Y', strtotime("-$i months"));
    $stmt = $conn->prepare("SELECT target_amount FROM targets WHERE company_id=? AND target_type='sr' AND target_entity_id=? AND month=? AND year=?");
    $stmt->bind_param("iiii", $cid, $uid, $hm, $hy); $stmt->execute();
    $ht = (float)($stmt->get_result()->fetch_assoc()['target_amount'] ?? 0); $stmt->close();
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(oi.quantity*oi.price),0) AS a
         FROM truck_loads tl
         JOIN truck_load_orders tlo ON tlo.truck_load_id=tl.id AND tlo.is_active=1
         JOIN order_items oi ON oi.order_id=tlo.order_id
         WHERE tl.assigned_sr_id=? AND tl.company_id=? AND tl.status='delivered'
           AND MONTH(tl.delivered_at)=? AND YEAR(tl.delivered_at)=?"
    );
    $stmt->bind_param("iiii", $uid, $cid, $hm, $hy); $stmt->execute();
    $ha = (float)$stmt->get_result()->fetch_assoc()['a']; $stmt->close();
    $history[] = ['month'=>$hm,'year'=>$hy,'target'=>$ht,'achieved'=>$ha,
                  'pct'=>$ht>0?min(110,round($ha/$ht*100)):0,
                  'label'=>date('M Y', mktime(0,0,0,$hm,1,$hy))];
}
?>

<div class="page-header">
    <div><div class="page-title">My Target</div>
    <div class="page-subtitle"><?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></div></div>
    <form method="GET" style="display:flex;gap:6px;align-items:flex-end">
        <select name="month" style="padding:6px 10px;width:auto">
            <?php for ($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m==$month?'selected':''?>><?=date('M',mktime(0,0,0,$m,1))?></option><?php endfor; ?>
        </select>
        <select name="year" style="padding:6px 10px;width:auto">
            <?php for ($y=date('Y');$y>=date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option><?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Go</button>
    </form>
</div>

<?php if ($target_amount == 0): ?>
<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i> No target has been set for this month. Contact your manager.</div>
<?php endif; ?>

<!-- KPIs -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Monthly Target</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= number_format($target_amount, 0) ?></div>
        <div class="kpi-sub">BDT target</div>
    </div>
    <div class="kpi-card <?= $target_pct>=100?'':($target_pct>=50?'warning':'danger') ?>">
        <div class="kpi-label">Achieved (Deliveries)</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= number_format($achieved, 0) ?></div>
        <div class="kpi-sub"><?= $target_pct ?>% of target</div>
    </div>
    <div class="kpi-card info">
        <div class="kpi-label">Remaining</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= number_format($remaining, 0) ?></div>
        <div class="kpi-sub"><?= $days_left ?> days left</div>
    </div>
    <div class="kpi-card <?= $days_left > 0 && $remaining > 0 ? '' : 'info' ?>">
        <div class="kpi-label">Daily Pace Needed</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= $days_left > 0 && $remaining > 0 ? number_format($remaining/$days_left, 0) : '—' ?></div>
        <div class="kpi-sub">BDT/day to hit target</div>
    </div>
</div>

<!-- Progress Bar -->
<?php if ($target_amount > 0): ?>
<div class="card">
    <div class="card-header"><span class="card-title">Progress &mdash; <?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></span></div>
    <div style="margin-bottom:10px">
        <div class="flex-between mb-8">
            <span><?= number_format($achieved,0) ?> BDT achieved</span>
            <span class="fw-700 <?= $target_pct>=100?'text-green':($target_pct>=50?'text-yellow':'text-red') ?>"><?= $target_pct ?>%</span>
        </div>
        <div class="progress-bar-wrap" style="height:16px">
            <div class="progress-bar-fill <?= $bar_class ?>" style="width:<?= $target_pct ?>%"></div>
        </div>
        <div class="flex-between mt-8 text-muted text-xs">
            <span>0</span><span><?= number_format($target_amount,0) ?> BDT</span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 6-month history -->
<div class="card">
    <div class="card-header"><span class="card-title">6-Month History</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Month</th><th>Target</th><th>Achieved</th><th>%</th><th>Progress</th></tr></thead>
            <tbody>
                <?php foreach (array_reverse($history) as $h):
                    $hbar = $h['pct']>=100?'gold':($h['pct']>=80?'':($h['pct']>=50?'warn':'danger'));
                ?>
                <tr>
                    <td class="fw-600"><?= $h['label'] ?></td>
                    <td><?= $h['target'] > 0 ? number_format($h['target'],0) : '<span class="text-muted">Not set</span>' ?></td>
                    <td class="fw-600"><?= number_format($h['achieved'],0) ?></td>
                    <td class="fw-700 <?= $h['pct']>=100?'text-green':($h['pct']>=50?'text-yellow':'text-red') ?>"><?= $h['target']>0 ? $h['pct'].'%' : '—' ?></td>
                    <td style="min-width:120px">
                        <?php if ($h['target'] > 0): ?>
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill <?= $hbar ?>" style="width:<?= $h['pct'] ?>%"></div>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
