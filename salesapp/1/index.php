<?php
$pageTitle = 'Dashboard';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$uid = (int)$_SESSION['user_id'];

/* ════════════════════════════════════════════════════
   MANAGER DASHBOARD
════════════════════════════════════════════════════ */
if ($is_manager):

/* ── Month filter ── */
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));
$from  = sprintf('%04d-%02d-01', $year, $month);
$to    = date('Y-m-t', strtotime($from));

/* ── KPIs ── */
$stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) AS orders, COALESCE(SUM(oi.quantity*oi.price),0) AS revenue
    FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id
    WHERE o.company_id=? AND o.order_status=1 AND o.order_date BETWEEN ? AND ?");
$stmt->bind_param("iss", $cid, $from, $to);
$stmt->execute();
$kpi = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM truck_loads WHERE company_id=? AND status='submitted'");
$stmt->bind_param("i", $cid); $stmt->execute();
$pending_approvals = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(DISTINCT created_by) AS c FROM orders WHERE company_id=? AND order_date BETWEEN ? AND ?");
$stmt->bind_param("iss", $cid, $from, $to); $stmt->execute();
$active_srs = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM truck_loads WHERE company_id=? AND status='in_transit'");
$stmt->bind_param("i", $cid); $stmt->execute();
$in_transit = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();

/* ── Truck pipeline counts ── */
$pipeline_statuses = ['draft','submitted','approved','loading','ready','in_transit','delivered'];
$pipeline = [];
foreach ($pipeline_statuses as $s) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM truck_loads WHERE company_id=? AND status=?");
    $stmt->bind_param("is", $cid, $s); $stmt->execute();
    $pipeline[$s] = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();
}

/* ── SR target fulfillment (top 10) ── */
$target_rows = $conn->prepare(
    "SELECT u.username, t.target_amount,
     COALESCE((
       SELECT SUM(oi.quantity*oi.price)
       FROM truck_loads tl
       JOIN truck_load_orders tlo ON tlo.truck_load_id=tl.id AND tlo.is_active=1
       JOIN order_items oi ON oi.order_id=tlo.order_id
       WHERE tl.assigned_sr_id=u.id AND tl.company_id=?
         AND tl.status='delivered'
         AND MONTH(tl.delivered_at)=? AND YEAR(tl.delivered_at)=?
     ),0) AS achieved
     FROM targets t
     JOIN users u ON u.id=t.target_entity_id
     WHERE t.company_id=? AND t.target_type='sr' AND t.month=? AND t.year=?
     ORDER BY achieved/NULLIF(t.target_amount,0) DESC LIMIT 10"
);
$target_rows->bind_param("iiiii" . "i", $cid, $month, $year, $cid, $month, $year);
$target_rows->execute();
$targets_res = $target_rows->get_result();

/* ── Recent truck loads ── */
$recent_loads = $conn->prepare(
    "SELECT tl.id, tl.load_name, tl.status, tl.total_orders, tl.total_value,
            tl.created_at, u.username AS sr_name
     FROM truck_loads tl
     JOIN users u ON u.id=tl.assigned_sr_id
     WHERE tl.company_id=? ORDER BY tl.created_at DESC LIMIT 8"
);
$recent_loads->bind_param("i", $cid); $recent_loads->execute();
$loads_res = $recent_loads->get_result();

$status_badge = [
    'draft'=>'badge-gray','submitted'=>'badge-blue','approved'=>'badge-teal',
    'loading'=>'badge-yellow','ready'=>'badge-orange','in_transit'=>'badge-purple',
    'delivered'=>'badge-green','cancelled'=>'badge-red','returned'=>'badge-brown'
];
?>

<div class="page-header">
    <div>
        <div class="page-title">Manager Dashboard</div>
        <div class="page-subtitle"><?= date('F Y', strtotime($from)) ?> &mdash; <?= htmlspecialchars($company_name) ?></div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <form method="GET" style="display:flex;gap:8px;align-items:flex-end">
            <div>
                <label style="font-size:0.75rem;color:var(--gray-500)">Month</label>
                <select name="month" style="width:auto;padding:6px 10px">
                    <?php for ($m=1;$m<=12;$m++): ?>
                        <option value="<?=$m?>" <?=$m==$month?'selected':''?>><?=date('M',mktime(0,0,0,$m,1))?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--gray-500)">Year</label>
                <select name="year" style="width:auto;padding:6px 10px">
                    <?php for ($y=date('Y');$y>=date('Y')-3;$y--): ?>
                        <option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Go</button>
        </form>
        <?php if ($pending_approvals > 0): ?>
            <a href="truck_loads.php?status=submitted" class="btn btn-danger btn-sm">
                <i class="fa-solid fa-bell"></i> <?= $pending_approvals ?> Pending Approval
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPIs -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Revenue (Month)</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= number_format($kpi['revenue'], 0) ?></div>
        <div class="kpi-sub">from <?= number_format($kpi['orders']) ?> confirmed orders</div>
    </div>
    <div class="kpi-card warning">
        <div class="kpi-label">Pending Approvals</div>
        <div class="kpi-value"><?= $pending_approvals ?></div>
        <div class="kpi-sub"><a href="truck_loads.php?status=submitted" style="color:var(--warning)">Review &rarr;</a></div>
    </div>
    <div class="kpi-card info">
        <div class="kpi-label">In Transit Now</div>
        <div class="kpi-value"><?= $in_transit ?></div>
        <div class="kpi-sub"><a href="truck_loads.php?status=in_transit" style="color:var(--info)">Track &rarr;</a></div>
    </div>
    <div class="kpi-card danger">
        <div class="kpi-label">SR Requests</div>
        <div class="kpi-value"><?= $pending_requests ?></div>
        <div class="kpi-sub"><a href="sr_requests.php" style="color:var(--danger)">Review &rarr;</a></div>
    </div>
</div>

<!-- Truck Pipeline -->
<div class="card mb-20">
    <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-truck" style="color:var(--primary)"></i> Truck Load Pipeline</span>
        <a href="truck_loads.php" class="btn btn-ghost btn-sm">All Loads</a>
    </div>
    <div class="pipeline-grid">
        <?php
        $pl_labels = ['draft'=>'Draft','submitted'=>'Submitted','approved'=>'Approved',
                      'loading'=>'Loading','ready'=>'Ready','in_transit'=>'In Transit','delivered'=>'Delivered'];
        $pl_colors = ['draft'=>'var(--gray-500)','submitted'=>'var(--info)','approved'=>'#0f766e',
                      'loading'=>'var(--warning)','ready'=>'#c2410c','in_transit'=>'#6d28d9','delivered'=>'var(--primary)'];
        foreach ($pl_labels as $s => $lbl):
        ?>
        <a href="truck_loads.php?status=<?= $s ?>" class="pipeline-col" style="text-decoration:none">
            <div class="pipeline-col-status" style="color:<?= $pl_colors[$s] ?>"><?= $lbl ?></div>
            <div class="pipeline-col-count"><?= $pipeline[$s] ?></div>
            <div class="pipeline-col-label">loads</div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid-layout md-2">
    <!-- Target Fulfillment -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-bullseye" style="color:var(--warning)"></i> Target Fulfillment</span>
            <a href="targets.php" class="btn btn-ghost btn-sm">Manage</a>
        </div>
        <?php if ($targets_res->num_rows > 0): ?>
            <?php while ($tr = $targets_res->fetch_assoc()):
                $pct = $tr['target_amount'] > 0 ? min(110, round($tr['achieved']/$tr['target_amount']*100)) : 0;
                $bar_class = $pct >= 100 ? 'gold' : ($pct >= 80 ? '' : ($pct >= 50 ? 'warn' : 'danger'));
            ?>
            <div style="margin-bottom:14px">
                <div class="flex-between mb-4">
                    <span class="fw-600 text-sm"><?= htmlspecialchars($tr['username']) ?></span>
                    <span class="text-sm <?= $pct>=100?'text-green':($pct>=50?'text-yellow':'text-red') ?>"><?= $pct ?>%</span>
                </div>
                <div class="progress-bar-wrap">
                    <div class="progress-bar-fill <?= $bar_class ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="text-muted text-xs mt-4"><?= number_format($tr['achieved']) ?> / <?= number_format($tr['target_amount']) ?></div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-muted text-sm" style="padding:20px 0">No targets set for this month. <a href="targets.php">Set targets &rarr;</a></div>
        <?php endif; ?>
    </div>

    <!-- Recent Truck Loads -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-list" style="color:var(--info)"></i> Recent Loads</span>
            <a href="truck_loads.php" class="btn btn-ghost btn-sm">All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Load</th><th>SR</th><th>Status</th><th>Value</th></tr></thead>
                <tbody>
                    <?php if ($loads_res->num_rows > 0): ?>
                        <?php while ($ld = $loads_res->fetch_assoc()): ?>
                        <tr>
                            <td><a href="truck_loads.php?view=<?= $ld['id'] ?>" style="color:var(--primary);font-weight:600"><?= htmlspecialchars($ld['load_name']) ?></a>
                                <div class="text-muted text-xs"><?= $ld['total_orders'] ?> orders</div>
                            </td>
                            <td class="text-sm"><?= htmlspecialchars($ld['sr_name']) ?></td>
                            <td><span class="badge ts-<?= $ld['status'] ?>"><?= ucfirst(str_replace('_',' ',$ld['status'])) ?></span></td>
                            <td class="text-sm fw-600"><?= number_format($ld['total_value'],0) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted" style="padding:20px">No truck loads yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $target_rows->close(); $recent_loads->close();

/* ════════════════════════════════════════════════════
   SR DASHBOARD
════════════════════════════════════════════════════ */
else:

$month = (int)date('n'); $year = (int)date('Y');
$from  = date('Y-m-01'); $to = date('Y-m-t');

/* KPIs */
$stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) AS orders, COALESCE(SUM(oi.quantity*oi.price),0) AS revenue
    FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id
    WHERE o.company_id=? AND o.created_by=? AND o.order_status=1 AND o.order_date BETWEEN ? AND ?");
$stmt->bind_param("iiss", $cid, $uid, $from, $to); $stmt->execute();
$kpi = $stmt->get_result()->fetch_assoc(); $stmt->close();

/* Today's orders */
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE company_id=? AND created_by=? AND order_date=?");
$stmt->bind_param("iis", $cid, $uid, $today); $stmt->execute();
$today_orders = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();

/* My pending truck loads */
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM truck_loads WHERE company_id=? AND assigned_sr_id=? AND status NOT IN ('delivered','cancelled','returned')");
$stmt->bind_param("ii", $cid, $uid); $stmt->execute();
$active_loads = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();

/* My target this month */
$stmt = $conn->prepare("SELECT target_amount FROM targets WHERE company_id=? AND target_type='sr' AND target_entity_id=? AND month=? AND year=?");
$stmt->bind_param("iiii", $cid, $uid, $month, $year); $stmt->execute();
$target_row = $stmt->get_result()->fetch_assoc(); $stmt->close();
$target_amount = (float)($target_row['target_amount'] ?? 0);

/* My achieved (delivered truck loads) */
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
$target_pct = $target_amount > 0 ? min(110, round($achieved/$target_amount*100)) : 0;

/* My recent truck loads */
$my_loads = $conn->prepare(
    "SELECT tl.id, tl.load_name, tl.status, tl.total_orders, tl.total_value, tl.created_at
     FROM truck_loads tl
     WHERE tl.company_id=? AND tl.assigned_sr_id=?
     ORDER BY tl.created_at DESC LIMIT 6"
);
$my_loads->bind_param("ii", $cid, $uid); $my_loads->execute();
$my_loads_res = $my_loads->get_result();

/* Recent orders */
$my_orders = $conn->prepare(
    "SELECT o.id, o.order_date, o.delivery_date, o.order_status,
            s.shop_name, r.route_name,
            COALESCE(SUM(oi.quantity*oi.price),0) AS total
     FROM orders o
     JOIN shops s ON s.id=o.shop_id
     JOIN routes r ON r.id=o.route_id
     LEFT JOIN order_items oi ON oi.order_id=o.id
     WHERE o.company_id=? AND o.created_by=?
     GROUP BY o.id ORDER BY o.created_at DESC LIMIT 6"
);
$my_orders->bind_param("ii", $cid, $uid); $my_orders->execute();
$my_orders_res = $my_orders->get_result();

$status_badge = [
    'draft'=>'badge-gray','submitted'=>'badge-blue','approved'=>'badge-teal',
    'loading'=>'badge-yellow','ready'=>'badge-orange','in_transit'=>'badge-purple',
    'delivered'=>'badge-green','cancelled'=>'badge-red','returned'=>'badge-brown'
];
$bar_class = $target_pct >= 100 ? 'gold' : ($target_pct >= 80 ? '' : ($target_pct >= 50 ? 'warn' : 'danger'));
?>

<div class="page-header">
    <div>
        <div class="page-title">My Dashboard</div>
        <div class="page-subtitle"><?= date('F Y') ?> &mdash; <?= htmlspecialchars($company_name) ?></div>
    </div>
    <div style="display:flex;gap:8px">
        <a href="orders.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New Order</a>
        <a href="truck_loads.php" class="btn btn-dark btn-sm"><i class="fa-solid fa-truck"></i> Truck Loads</a>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Revenue (Month)</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= number_format($kpi['revenue'],0) ?></div>
        <div class="kpi-sub"><?= $kpi['orders'] ?> confirmed orders</div>
    </div>
    <div class="kpi-card info">
        <div class="kpi-label">Today's Orders</div>
        <div class="kpi-value"><?= $today_orders ?></div>
        <div class="kpi-sub"><a href="orders.php?date_from=<?=$today?>&date_to=<?=$today?>" style="color:var(--info)">View &rarr;</a></div>
    </div>
    <div class="kpi-card warning">
        <div class="kpi-label">Active Truck Loads</div>
        <div class="kpi-value"><?= $active_loads ?></div>
        <div class="kpi-sub"><a href="truck_loads.php" style="color:var(--warning)">View &rarr;</a></div>
    </div>
    <div class="kpi-card <?= $target_pct >= 80 ? '' : ($target_pct >= 50 ? 'warning' : 'danger') ?>">
        <div class="kpi-label">Target Fulfillment</div>
        <div class="kpi-value"><?= $target_pct ?>%</div>
        <div class="kpi-sub"><?= number_format($achieved,0) ?> / <?= number_format($target_amount,0) ?></div>
    </div>
</div>

<!-- Target Progress Bar -->
<?php if ($target_amount > 0): ?>
<div class="card mb-20">
    <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-bullseye" style="color:var(--warning)"></i> My <?= date('F Y') ?> Target</span>
        <a href="my_target.php" class="btn btn-ghost btn-sm">Details</a>
    </div>
    <div style="margin-bottom:8px">
        <div class="flex-between mb-4">
            <span class="text-sm">Progress: <strong><?= number_format($achieved,0) ?></strong> of <strong><?= number_format($target_amount,0) ?></strong></span>
            <span class="fw-700 <?= $target_pct>=100?'text-green':($target_pct>=50?'text-yellow':'text-red') ?>"><?= $target_pct ?>%</span>
        </div>
        <div class="progress-bar-wrap" style="height:12px">
            <div class="progress-bar-fill <?= $bar_class ?>" style="width:<?= $target_pct ?>%"></div>
        </div>
        <?php
        $days_left = (int)((strtotime($to) - time()) / 86400);
        $remaining = max(0, $target_amount - $achieved);
        if ($days_left > 0 && $remaining > 0): ?>
            <div class="text-muted text-xs mt-4"><?= $days_left ?> days left &mdash; pace needed: <?= number_format($remaining/$days_left, 0) ?>/day</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="grid-layout md-2">
    <!-- My Truck Loads -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-truck" style="color:var(--primary)"></i> My Truck Loads</span>
            <a href="truck_loads.php" class="btn btn-ghost btn-sm">All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Load</th><th>Status</th><th>Orders</th><th>Value</th></tr></thead>
                <tbody>
                    <?php if ($my_loads_res->num_rows > 0): ?>
                        <?php while ($ld = $my_loads_res->fetch_assoc()): ?>
                        <tr>
                            <td><a href="truck_loads.php?view=<?= $ld['id'] ?>" style="color:var(--primary);font-weight:600"><?= htmlspecialchars($ld['load_name']) ?></a></td>
                            <td><span class="badge ts-<?= $ld['status'] ?>"><?= ucfirst(str_replace('_',' ',$ld['status'])) ?></span></td>
                            <td><?= $ld['total_orders'] ?></td>
                            <td class="text-sm"><?= number_format($ld['total_value'],0) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted" style="padding:20px">No truck loads yet. <a href="truck_loads.php">Create one &rarr;</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- My Recent Orders -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-clipboard-list" style="color:var(--info)"></i> Recent Orders</span>
            <a href="orders.php" class="btn btn-ghost btn-sm">All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Shop</th><th>Date</th><th>Status</th><th>Value</th></tr></thead>
                <tbody>
                    <?php if ($my_orders_res->num_rows > 0): ?>
                        <?php while ($ord = $my_orders_res->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="text-sm fw-600"><?= htmlspecialchars($ord['shop_name']) ?></span>
                                <div class="text-muted text-xs"><?= htmlspecialchars($ord['route_name']) ?></div>
                            </td>
                            <td class="text-sm"><?= date('d M', strtotime($ord['order_date'])) ?></td>
                            <td><span class="badge <?= $ord['order_status'] ? 'badge-green' : 'badge-yellow' ?>"><?= $ord['order_status'] ? 'Confirmed' : 'Draft' ?></span></td>
                            <td class="text-sm"><?= number_format($ord['total'],0) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted" style="padding:20px">No orders yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $my_loads->close(); $my_orders->close();
endif; ?>

<?php include 'footer.php'; ?>
