<?php
$pageTitle = 'Orders';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$uid = (int)$_SESSION['user_id'];

/* ── Approve ── */
if (isset($_GET['approve_id'])) {
    $aid  = (int)$_GET['approve_id'];
    $stmt = $conn->prepare("UPDATE orders SET order_status=1, approved_at=NOW(), approved_by=? WHERE id=? AND company_id=?");
    $stmt->bind_param("iii", $uid, $aid, $cid);
    $stmt->execute(); $stmt->close();
    header("Location: orders.php?msg=approved"); exit;
}

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_order'])) {
        $shop_id       = (int)$_POST['shop_id'];
        $route_id      = (int)$_POST['route_id'];
        $order_date    = $_POST['order_date'];
        $delivery_date = $_POST['delivery_date'];
        $order_status  = (int)$_POST['order_status'];
        $status        = 1;
        $remarks       = trim($_POST['remarks'] ?? '');

        $stmt = $conn->prepare(
            "INSERT INTO orders (shop_id, route_id, order_date, delivery_date, order_status, created_by, status, company_id, remarks)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param("iissiiiis", $shop_id, $route_id, $order_date, $delivery_date, $order_status, $uid, $status, $cid, $remarks);
        $stmt->execute();
        $order_id = (int)$conn->insert_id;
        $stmt->close();
        header("Location: order_item.php?order_id=$order_id"); exit;
    }

    if (isset($_POST['update_order'])) {
        $oid = (int)$_GET['edit'];
        /* Block edit if already approved */
        $chk = $conn->prepare("SELECT order_status FROM orders WHERE id=? AND company_id=?");
        $chk->bind_param("ii", $oid, $cid); $chk->execute();
        $cur = $chk->get_result()->fetch_assoc(); $chk->close();
        if ($cur && $cur['order_status'] == 1 && !$is_manager) {
            header("Location: orders.php?msg=already_approved"); exit;
        }
        $shop_id       = (int)$_POST['shop_id'];
        $route_id      = (int)$_POST['route_id'];
        $order_date    = $_POST['order_date'];
        $delivery_date = $_POST['delivery_date'];
        $remarks       = trim($_POST['remarks'] ?? '');

        $stmt = $conn->prepare(
            "UPDATE orders SET shop_id=?,route_id=?,order_date=?,delivery_date=?,remarks=?,updated_by=?,updated_at=NOW()
             WHERE id=? AND company_id=?"
        );
        $stmt->bind_param("iisssiii", $shop_id, $route_id, $order_date, $delivery_date, $remarks, $uid, $oid, $cid);
        $stmt->execute(); $stmt->close();
        header("Location: orders.php?msg=updated"); exit;
    }
}

/* ── Load edit data ── */
$edit_data = null;
if (isset($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", $eid, $cid); $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

/* ── Routes for form ── */
$routes_q = $conn->query("SELECT id, route_name FROM routes WHERE status=1 AND company_id=$cid ORDER BY route_name");

/* ── Filters + pagination ── */
$f_route    = (int)($_GET['route_id'] ?? 0);
$f_shop     = (int)($_GET['shop_id'] ?? 0);
$f_status_o = $_GET['order_status'] ?? '';
$f_from     = $_GET['date_from'] ?? date('Y-m-01');
$f_to       = $_GET['date_to']   ?? date('Y-m-t');
$per_page   = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $per_page;

$where  = ["o.company_id=$cid"];
$params = []; $types = '';
if ($f_route)    { $where[] = 'o.route_id=?';    $params[] = $f_route;    $types .= 'i'; }
if ($f_shop)     { $where[] = 'o.shop_id=?';     $params[] = $f_shop;     $types .= 'i'; }
if ($f_status_o !== '') { $where[] = 'o.order_status=?'; $params[] = (int)$f_status_o; $types .= 'i'; }
$where[] = 'o.order_date BETWEEN ? AND ?'; $params[] = $f_from; $params[] = $f_to; $types .= 'ss';
/* SRs only see their own orders */
if (!$is_manager) { $where[] = 'o.created_by=?'; $params[] = $uid; $types .= 'i'; }
$w = 'WHERE ' . implode(' AND ', $where);

$count_q = $conn->prepare("SELECT COUNT(*) AS c FROM orders o $w");
if ($types) $count_q->bind_param($types, ...$params);
$count_q->execute(); $total = (int)$count_q->get_result()->fetch_assoc()['c']; $count_q->close();
$total_pages = max(1, (int)ceil($total / $per_page));

$list_q = $conn->prepare(
    "SELECT o.id, o.order_date, o.delivery_date, o.order_status, o.status, o.remarks, o.approved_at,
            s.shop_name, r.route_name, u.username AS created_by_name,
            COALESCE(SUM(oi.quantity*oi.price),0) AS total
     FROM orders o
     JOIN shops s ON s.id=o.shop_id
     JOIN routes r ON r.id=o.route_id
     LEFT JOIN users u ON u.id=o.created_by
     LEFT JOIN order_items oi ON oi.order_id=o.id
     $w GROUP BY o.id ORDER BY o.created_at DESC LIMIT ? OFFSET ?"
);
$list_params = array_merge($params, [$per_page, $offset]);
$list_types  = $types . 'ii';
$list_q->bind_param($list_types, ...$list_params);
$list_q->execute(); $orders_res = $list_q->get_result();

/* Messages */
$msgs = ['approved'=>'Order approved.','updated'=>'Order updated.','already_approved'=>'Order is already approved.'];
$success = $msgs[$_GET['msg'] ?? ''] ?? '';
?>

<div class="page-header">
    <div><div class="page-title">Orders</div><div class="page-subtitle">Create and manage sales orders</div></div>
    <button class="btn btn-primary btn-sm" id="toggleForm">
        <i class="fa-solid fa-plus"></i> <?= $edit_data ? 'Editing Order #'.$edit_data['id'] : 'New Order' ?>
    </button>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Order Form -->
<div class="card" id="orderForm" <?= $edit_data ? '' : 'style="display:none"' ?>>
    <div class="card-header">
        <span class="card-title"><?= $edit_data ? 'Edit Order #'.$edit_data['id'] : 'New Order' ?></span>
        <?php if ($edit_data): ?><a href="orders.php" class="btn btn-ghost btn-sm">Cancel Edit</a><?php endif; ?>
    </div>
    <form method="POST" action="orders.php<?= $edit_data ? '?edit='.$edit_data['id'] : '' ?>">
        <?= csrf_field() ?>
        <div class="grid-layout md-2">
            <div class="form-group">
                <label>Route <span style="color:var(--danger)">*</span></label>
                <select name="route_id" id="route_id" required>
                    <option value="">Select Route</option>
                    <?php if ($routes_q) while ($r = $routes_q->fetch_assoc()): $sel = ($edit_data && $edit_data['route_id']==$r['id'])?'selected':''; ?>
                        <option value="<?=$r['id']?>" <?=$sel?>><?= htmlspecialchars($r['route_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Shop <span style="color:var(--danger)">*</span></label>
                <select name="shop_id" id="shop_id" required>
                    <?php if ($edit_data): ?>
                        <?php $sq = $conn->prepare("SELECT id, shop_name FROM shops WHERE id=? LIMIT 1"); $sq->bind_param("i",$edit_data['shop_id']); $sq->execute(); $sv = $sq->get_result()->fetch_assoc(); $sq->close(); ?>
                        <option value="<?= $edit_data['shop_id'] ?>" selected><?= htmlspecialchars($sv['shop_name'] ?? '') ?></option>
                    <?php else: ?>
                        <option value="">Select Shop</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Order Date <span style="color:var(--danger)">*</span></label>
                <input type="date" name="order_date" value="<?= htmlspecialchars($edit_data['order_date'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>Delivery Date <span style="color:var(--danger)">*</span></label>
                <input type="date" name="delivery_date" value="<?= htmlspecialchars($edit_data['delivery_date'] ?? date('Y-m-d', strtotime('+1 day'))) ?>" required>
            </div>
            <?php if ($is_manager || !$edit_data): ?>
            <div class="form-group">
                <label>Order Status</label>
                <select name="order_status">
                    <option value="0" <?= (!$edit_data || $edit_data['order_status']==0)?'selected':'' ?>>Draft</option>
                    <option value="1" <?= ($edit_data && $edit_data['order_status']==1)?'selected':'' ?>>Confirmed</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>Remarks</label>
                <input type="text" name="remarks" placeholder="Optional notes" value="<?= htmlspecialchars($edit_data['remarks'] ?? '') ?>">
            </div>
        </div>
        <div class="form-actions">
            <?php if ($edit_data): ?>
                <button type="submit" name="update_order" class="btn btn-warning"><i class="fa-solid fa-pen"></i> Update Order</button>
            <?php else: ?>
                <button type="submit" name="add_order" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create Order &amp; Add Items</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Filters -->
<form method="GET" action="orders.php" id="filterForm">
    <div class="filter-bar">
        <div class="form-group">
            <label>Route</label>
            <select name="route_id">
                <option value="">All Routes</option>
                <?php if ($routes_q) { $routes_q->data_seek(0); while ($r = $routes_q->fetch_assoc()): ?>
                    <option value="<?=$r['id']?>" <?=$f_route==$r['id']?'selected':''?>><?= htmlspecialchars($r['route_name']) ?></option>
                <?php endwhile; } ?>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="order_status">
                <option value="">All</option>
                <option value="0" <?=$f_status_o==='0'?'selected':''?>>Draft</option>
                <option value="1" <?=$f_status_o==='1'?'selected':''?>>Confirmed</option>
            </select>
        </div>
        <div class="form-group">
            <label>From</label>
            <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($f_from) ?>">
        </div>
        <div class="form-group">
            <label>To</label>
            <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($f_to) ?>">
        </div>
        <div class="form-group" style="max-width:90px">
            <label>Per Page</label>
            <select id="perPageSelect" name="per_page">
                <?php foreach ([10,25,50,100] as $n): ?><option value="<?=$n?>" <?=$per_page==$n?'selected':''?>><?=$n?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="orders.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
    <div class="date-presets" style="margin-bottom:12px">
        <span class="text-muted text-xs" style="margin-right:6px">Quick:</span>
        <button type="button" class="date-preset-btn" data-preset="today">Today</button>
        <button type="button" class="date-preset-btn" data-preset="week">This Week</button>
        <button type="button" class="date-preset-btn" data-preset="month">This Month</button>
        <button type="button" class="date-preset-btn" data-preset="last_month">Last Month</button>
    </div>
</form>

<!-- Orders Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Orders</span>
        <div style="display:flex;gap:8px;align-items:center">
            <span class="badge badge-blue"><?= $total ?> total</span>
            <button onclick="window.print()" class="btn btn-ghost btn-sm btn-icon print-hide"><i class="fa-solid fa-print"></i></button>
        </div>
    </div>

    <!-- Print header -->
    <div class="print-header">
        <h1><?= APP_NAME ?> &mdash; Order Report</h1>
        <p>Generated: <?= date('d M Y H:i') ?> | By: <?= htmlspecialchars($_SESSION['username']) ?></p>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Route &rsaquo; Shop</th>
                    <?php if ($is_manager): ?><th>SR</th><?php endif; ?>
                    <th>Order Date</th>
                    <th>Delivery</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($orders_res->num_rows > 0): ?>
                    <?php while ($ord = $orders_res->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?= $ord['id'] ?></td>
                        <td>
                            <span class="text-muted text-xs"><?= htmlspecialchars($ord['route_name']) ?></span><br>
                            <strong class="text-sm"><?= htmlspecialchars($ord['shop_name']) ?></strong>
                            <?php if ($ord['remarks']): ?><div class="text-muted text-xs"><?= htmlspecialchars($ord['remarks']) ?></div><?php endif; ?>
                        </td>
                        <?php if ($is_manager): ?><td class="text-sm"><?= htmlspecialchars($ord['created_by_name']) ?></td><?php endif; ?>
                        <td class="text-sm"><?= $ord['order_date'] ?></td>
                        <td class="text-sm"><?= $ord['delivery_date'] ?></td>
                        <td class="fw-600"><?= number_format($ord['total'], 0) ?></td>
                        <td>
                            <?php if ($ord['order_status'] == 1): ?>
                                <span class="badge badge-green">Confirmed</span>
                            <?php else: ?>
                                <?php if ($is_manager): ?>
                                    <a href="orders.php?approve_id=<?= $ord['id'] ?>" class="badge badge-yellow" style="cursor:pointer;text-decoration:none"
                                       onclick="return confirm('Approve this order?')">Draft &mdash; Approve?</a>
                                <?php else: ?>
                                    <span class="badge badge-yellow">Draft</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="order_item.php?order_id=<?= $ord['id'] ?>" class="btn btn-info btn-sm btn-icon" title="Items"><i class="fa-solid fa-list"></i></a>
                            <?php if ($ord['order_status'] == 0 || $is_manager): ?>
                            <a href="orders.php?edit=<?= $ord['id'] ?>" class="btn btn-warning btn-sm btn-icon" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="<?= $is_manager ? 8 : 7 ?>" class="text-center text-muted" style="padding:30px">No orders found for the selected filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php $base = "orders.php?route_id=$f_route&order_status=$f_status_o&date_from=$f_from&date_to=$f_to&per_page=$per_page&page="; ?>
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

<script>
document.getElementById('toggleForm').addEventListener('click', function () {
    var form = document.getElementById('orderForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
});

$(document).ready(function () {
    $('#route_id').select2({ width: '100%', placeholder: 'Select Route' });
    $('#shop_id').select2({ width: '100%', placeholder: 'Select Shop' });
});

function getShopsByRouteId(route_id) {
    $('#shop_id').empty().append('<option value="">Loading...</option>');
    $.get('get_shops_by_route_id.php', { route_id: route_id }, function (html) {
        $('#shop_id').html(html).trigger('change');
    });
}

document.getElementById('route_id').addEventListener('change', function () {
    getShopsByRouteId(this.value);
});
</script>

<?php $list_q->close(); include 'footer.php'; ?>
