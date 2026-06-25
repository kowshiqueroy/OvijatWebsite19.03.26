<?php
$pageTitle = 'Sales Report';
include 'header.php';

/* Filters */
$f_from    = $_GET['date_from']   ?? date('Y-m-01');
$f_to      = $_GET['date_to']     ?? date('Y-m-t');
$f_cid     = (int)($_GET['company_id'] ?? 0);
$f_uid     = (int)($_GET['user_id']    ?? 0);
$per_page  = max(10, min(200, (int)($_GET['per_page'] ?? 50)));
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $per_page;

/* Build WHERE */
$where  = ["o.order_status=1", "o.order_date BETWEEN ? AND ?"];
$params = [$f_from, $f_to]; $types = 'ss';
if ($f_cid) { $where[] = 'o.company_id=?'; $params[] = $f_cid; $types .= 'i'; }
if ($f_uid) { $where[] = 'o.created_by=?'; $params[] = $f_uid; $types .= 'i'; }
$w = 'WHERE ' . implode(' AND ', $where);

/* Count + Grand total */
$cnt_q = $conn->prepare("SELECT COUNT(DISTINCT o.id) AS c, COALESCE(SUM(oi.quantity*oi.price),0) AS total FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id $w");
$cnt_q->bind_param($types, ...$params); $cnt_q->execute();
$summary = $cnt_q->get_result()->fetch_assoc(); $cnt_q->close();
$total   = (int)$summary['c'];
$grand   = (float)$summary['total'];
$total_pages = max(1, (int)ceil($total / $per_page));

/* Main query */
$list_q = $conn->prepare(
    "SELECT o.id, o.order_date, o.delivery_date, o.approved_at,
            c.name AS company, u.username AS sr,
            s.shop_name, r.route_name,
            COALESCE(SUM(oi.quantity*oi.price),0) AS order_total
     FROM orders o
     JOIN companies c ON c.id=o.company_id
     LEFT JOIN users u ON u.id=o.created_by
     JOIN shops s ON s.id=o.shop_id
     JOIN routes r ON r.id=o.route_id
     LEFT JOIN order_items oi ON oi.order_id=o.id
     $w GROUP BY o.id ORDER BY o.order_date DESC LIMIT ? OFFSET ?"
);
$lp = array_merge($params, [$per_page, $offset]); $lt = $types . 'ii';
$list_q->bind_param($lt, ...$lp); $list_q->execute();
$rows = $list_q->get_result();

/* Dropdowns */
$comp_q = $conn->query("SELECT id, name FROM companies ORDER BY name");
$user_q = $conn->query("SELECT id, username FROM users WHERE role IN (2,3) AND status=1 ORDER BY username");
?>

<div class="page-header">
    <div><div class="page-title">Sales Report</div><div class="page-subtitle">Confirmed orders with totals</div></div>
    <button onclick="window.print()" class="btn btn-ghost btn-sm print-hide"><i class="fa-solid fa-print"></i> Print</button>
</div>

<!-- Filters -->
<form method="GET" action="reports.php">
    <div class="filter-bar">
        <div class="form-group">
            <label>From</label>
            <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($f_from) ?>">
        </div>
        <div class="form-group">
            <label>To</label>
            <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($f_to) ?>">
        </div>
        <div class="form-group">
            <label>Company</label>
            <select name="company_id">
                <option value="">All Companies</option>
                <?php while ($c = $comp_q->fetch_assoc()): ?>
                    <option value="<?=$c['id']?>" <?=$f_cid==$c['id']?'selected':''?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Sales Rep</label>
            <select name="user_id">
                <option value="">All SRs</option>
                <?php while ($u = $user_q->fetch_assoc()): ?>
                    <option value="<?=$u['id']?>" <?=$f_uid==$u['id']?'selected':''?>><?= htmlspecialchars($u['username']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group" style="max-width:90px">
            <label>Per Page</label>
            <select id="perPageSelect" name="per_page">
                <?php foreach ([25,50,100,200] as $n): ?><option value="<?=$n?>" <?=$per_page==$n?'selected':''?>><?=$n?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="reports.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
    <div class="date-presets" style="margin-bottom:12px">
        <span class="text-muted text-xs" style="margin-right:6px">Quick:</span>
        <button type="button" class="date-preset-btn" data-preset="today">Today</button>
        <button type="button" class="date-preset-btn" data-preset="week">This Week</button>
        <button type="button" class="date-preset-btn" data-preset="month">This Month</button>
        <button type="button" class="date-preset-btn" data-preset="last_month">Last Month</button>
    </div>
</form>

<!-- Summary KPIs -->
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);max-width:600px;margin-bottom:20px">
    <div class="kpi-card"><div class="kpi-label">Orders</div><div class="kpi-value"><?= number_format($total) ?></div></div>
    <div class="kpi-card info"><div class="kpi-label">Total Revenue</div><div class="kpi-value" style="font-size:1.3rem"><?= number_format($grand, 0) ?></div></div>
    <div class="kpi-card warning"><div class="kpi-label">Avg Order</div><div class="kpi-value" style="font-size:1.3rem"><?= $total > 0 ? number_format($grand/$total, 0) : '—' ?></div></div>
</div>

<!-- Print header -->
<div class="print-header">
    <h1><?= APP_NAME ?> &mdash; Sales Report</h1>
    <p>Period: <?= $f_from ?> to <?= $f_to ?> | Generated: <?= date('d M Y H:i') ?></p>
</div>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Order Details</span>
        <span class="badge badge-blue"><?= $total ?> records</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Date</th><th>Company</th><th>SR</th><th>Route</th><th>Shop</th><th>Delivery</th><th class="text-right">Total (BDT)</th></tr>
            </thead>
            <tbody>
                <?php if ($rows->num_rows > 0): ?>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <td class="text-sm"><?= date('d M Y', strtotime($row['order_date'])) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($row['company']) ?></td>
                        <td class="text-sm fw-600"><?= htmlspecialchars($row['sr'] ?? '—') ?></td>
                        <td class="text-sm text-muted"><?= htmlspecialchars($row['route_name']) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($row['shop_name']) ?></td>
                        <td class="text-sm"><?= date('d M Y', strtotime($row['delivery_date'])) ?></td>
                        <td class="text-right fw-600"><?= number_format($row['order_total'], 0) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding:30px">No orders found for the selected filters.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--gray-100)">
                    <td colspan="7" class="text-right fw-700">Grand Total:</td>
                    <td class="text-right fw-700" style="font-size:1rem"><?= number_format($grand, 0) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php $base = "reports.php?date_from=$f_from&date_to=$f_to&company_id=$f_cid&user_id=$f_uid&per_page=$per_page&page="; ?>
        <a href="<?=$base?>1"                   class="page-btn <?=$page==1?'disabled':''?>"><i class="fa-solid fa-angles-left"></i></a>
        <a href="<?=$base.max(1,$page-1)?>"     class="page-btn <?=$page==1?'disabled':''?>"><i class="fa-solid fa-angle-left"></i></a>
        <?php for ($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
            <a href="<?=$base.$p?>" class="page-btn <?=$p==$page?'active':''?>"><?=$p?></a>
        <?php endfor; ?>
        <a href="<?=$base.min($total_pages,$page+1)?>" class="page-btn <?=$page==$total_pages?'disabled':''?>"><i class="fa-solid fa-angle-right"></i></a>
        <a href="<?=$base.$total_pages?>"              class="page-btn <?=$page==$total_pages?'disabled':''?>"><i class="fa-solid fa-angles-right"></i></a>
        <span class="text-muted text-sm" style="margin-left:8px">Page <?=$page?> of <?=$total_pages?></span>
    </div>
    <?php endif; ?>
</div>

<?php $list_q->close(); include 'footer.php'; ?>
