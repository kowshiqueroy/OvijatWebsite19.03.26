<?php
$pageTitle = 'Order Returns';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$uid = (int)$_SESSION['user_id'];
$success = $error = '';

/* ── Delete return (within 3 days, own company) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_return_id'])) {
    $did  = (int)$_POST['delete_return_id'];
    $stmt = $conn->prepare(
        "SELECT id FROM order_returns WHERE id=? AND company_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)"
    );
    $stmt->bind_param("ii", $did, $cid); $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $del = $conn->prepare("DELETE FROM order_returns WHERE id=? AND company_id=?");
        $del->bind_param("ii", $did, $cid); $del->execute(); $del->close();
        $success = "Return #$did deleted.";
    } else {
        $stmt->close();
        $error = "Cannot delete: record not found or older than 3 days.";
    }
}

/* ── Submit return ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_return'])) {
    $route_id       = (int)$_POST['route_id'];
    $shop_id        = (int)$_POST['shop_id'];
    $sales_user_id  = (int)$_POST['user_id'];
    $order_ids_str  = trim($_POST['order_ids']);
    $returns        = $_POST['returns'] ?? [];

    $total_value = 0.0;

    $ins = $conn->prepare("INSERT INTO order_returns (order_ids, route_id, shop_id, user_id, company_id, created_by) VALUES (?,?,?,?,?,?)");
    $ins->bind_param("siiiii", $order_ids_str, $route_id, $shop_id, $sales_user_id, $cid, $uid);
    if ($ins->execute()) {
        $return_id = (int)$conn->insert_id; $ins->close();
        $item_stmt = $conn->prepare("INSERT INTO order_return_items (return_id, order_id, item_id, return_qty, price) VALUES (?,?,?,?,?)");
        foreach ($returns as $order_id => $items) {
            foreach ($items as $item_id => $qty) {
                $qty = (int)$qty;
                if ($qty > 0) {
                    $pq = $conn->prepare("SELECT price FROM order_items WHERE order_id=? AND item_id=? LIMIT 1");
                    $pq->bind_param("ii", $order_id, $item_id); $pq->execute();
                    $price = (float)($pq->get_result()->fetch_assoc()['price'] ?? 0); $pq->close();
                    $item_stmt->bind_param("iiiid", $return_id, $order_id, $item_id, $qty, $price);
                    $item_stmt->execute();
                    $total_value += $qty * $price;
                }
            }
        }
        $item_stmt->close();
        $upd = $conn->prepare("UPDATE order_returns SET total_return_value=? WHERE id=?");
        $upd->bind_param("di", $total_value, $return_id); $upd->execute(); $upd->close();
        $success = "Return processed. Total refund: " . number_format($total_value, 0) . " BDT";
    } else {
        $ins->close();
        $error = "Error processing return.";
    }
}

/* ── History filters ── */
$f_from   = $_GET['date_from'] ?? date('Y-m-d');
$f_to     = $_GET['date_to']   ?? date('Y-m-d');
$f_route  = (int)($_GET['route_id'] ?? 0);
$f_shop   = (int)($_GET['shop_id']  ?? 0);
$per_page = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where  = ["r.company_id=$cid"];
$params = []; $types = '';
if ($f_route) { $where[] = 'r.route_id=?'; $params[] = $f_route; $types .= 'i'; }
if ($f_shop)  { $where[] = 'r.shop_id=?';  $params[] = $f_shop;  $types .= 'i'; }
$where[] = 'r.created_at BETWEEN ? AND ?';
$params[] = $f_from . ' 00:00:00'; $params[] = $f_to . ' 23:59:59'; $types .= 'ss';
$w = 'WHERE ' . implode(' AND ', $where);

$cnt_q = $conn->prepare("SELECT COUNT(*) AS c FROM order_returns r $w");
$cnt_q->bind_param($types, ...$params); $cnt_q->execute();
$total = (int)$cnt_q->get_result()->fetch_assoc()['c']; $cnt_q->close();
$total_pages = max(1, (int)ceil($total / $per_page));

$hist_q = $conn->prepare(
    "SELECT r.*, rt.route_name, s.shop_name, u.username
     FROM order_returns r
     LEFT JOIN routes rt ON rt.id=r.route_id
     LEFT JOIN shops s ON s.id=r.shop_id
     LEFT JOIN users u ON u.id=r.user_id
     $w ORDER BY r.created_at DESC LIMIT ? OFFSET ?"
);
$lp = array_merge($params, [$per_page, $offset]); $lt = $types . 'ii';
$hist_q->bind_param($lt, ...$lp); $hist_q->execute(); $hist_rows = $hist_q->get_result();

/* ── Dropdowns ── */
$routes_q = $conn->query("SELECT id, route_name FROM routes WHERE company_id=$cid AND status=1 ORDER BY route_name");
$shops_q  = $conn->query("SELECT id, shop_name FROM shops WHERE company_id=$cid AND status=1 ORDER BY shop_name");
?>

<div class="page-header">
    <div><div class="page-title">Order Returns</div><div class="page-subtitle">Process customer order returns</div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Validate Orders -->
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-rotate-left" style="color:var(--warning)"></i> Process Return</span></div>
    <p class="text-muted text-sm mb-8">Enter confirmed order ID(s) to process a return. Multiple orders must share the same shop, route, and SR.</p>
    <form method="GET">
        <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group" style="flex:1;min-width:200px">
                <label>Order ID(s) <span style="color:var(--danger)">*</span></label>
                <input type="text" name="ref_ids" placeholder="e.g. 12, 15, 22"
                       value="<?= htmlspecialchars($_GET['ref_ids'] ?? '') ?>" required>
            </div>
            <button type="submit" class="btn btn-primary" style="align-self:flex-end">
                <i class="fa-solid fa-magnifying-glass"></i> Validate Orders
            </button>
        </div>
    </form>
</div>

<!-- Return Form (shown after validation) -->
<?php
if (isset($_GET['ref_ids']) && trim($_GET['ref_ids']) !== ''):
    $ref_ids_arr = array_values(array_unique(array_filter(array_map('intval', explode(',', $_GET['ref_ids'])))));
    if (!empty($ref_ids_arr)):
        $placeholders = implode(',', array_fill(0, count($ref_ids_arr), '?'));
        $types_v      = str_repeat('i', count($ref_ids_arr));
        $stmt         = $conn->prepare("SELECT id, route_id, shop_id, created_by FROM orders WHERE id IN ($placeholders) AND company_id=? AND order_status=1");
        $val_params   = array_merge($ref_ids_arr, [$cid]);
        $stmt->bind_param($types_v . 'i', ...$val_params); $stmt->execute();
        $val_res = $stmt->get_result(); $stmt->close();
        $valid = []; $routes_v = []; $shops_v = []; $users_v = [];
        while ($r = $val_res->fetch_assoc()) {
            $valid[] = $r['id']; $routes_v[] = $r['route_id']; $shops_v[] = $r['shop_id']; $users_v[] = $r['created_by'];
        }
        if (count($valid) !== count($ref_ids_arr)):
?>
<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> One or more Order IDs are invalid, not confirmed, or belong to a different company.</div>
<?php
        elseif (count(array_unique($routes_v)) > 1 || count(array_unique($shops_v)) > 1 || count(array_unique($users_v)) > 1):
?>
<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i> Multiple orders can only be returned together if they share the same Route, Shop, and Sales Rep.</div>
<?php
        else:
            $shared_route = $routes_v[0]; $shared_shop = $shops_v[0]; $shared_user = $users_v[0];
            $rname = $conn->query("SELECT route_name FROM routes WHERE id=$shared_route")->fetch_assoc()['route_name'] ?? '';
            $sname = $conn->query("SELECT shop_name FROM shops WHERE id=$shared_shop")->fetch_assoc()['shop_name'] ?? '';
            $uname = $conn->query("SELECT username FROM users WHERE id=$shared_user")->fetch_assoc()['username'] ?? '';
            $ids_str = implode(',', $valid);
            /* Fetch items */
            $items_stmt = $conn->prepare(
                "SELECT oi.order_id, oi.item_id, oi.quantity, i.item_name,
                 COALESCE((SELECT SUM(return_qty) FROM order_return_items ori WHERE ori.order_id=oi.order_id AND ori.item_id=oi.item_id),0) AS returned
                 FROM order_items oi JOIN items i ON i.id=oi.item_id
                 WHERE oi.order_id IN ($placeholders) ORDER BY i.item_name"
            );
            $items_stmt->bind_param($types_v, ...$ref_ids_arr); $items_stmt->execute();
            $items_res = $items_stmt->get_result();
?>
<div class="card">
    <div class="card-header">
        <span class="card-title" style="color:var(--primary)">Orders Validated</span>
        <span class="text-sm text-muted"><?= htmlspecialchars($rname) ?> &rsaquo; <?= htmlspecialchars($sname) ?> &rsaquo; <?= htmlspecialchars($uname) ?></span>
    </div>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="route_id"  value="<?= $shared_route ?>">
        <input type="hidden" name="shop_id"   value="<?= $shared_shop ?>">
        <input type="hidden" name="user_id"   value="<?= $shared_user ?>">
        <input type="hidden" name="order_ids" value="<?= htmlspecialchars($ids_str) ?>">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Order #</th><th>Item</th><th>Ordered</th><th>Returned</th><th>Return Qty</th></tr></thead>
                <tbody>
                    <?php $has = false; while ($item = $items_res->fetch_assoc()): $max = $item['quantity'] - $item['returned']; $has = true; ?>
                    <tr>
                        <td class="text-muted">#<?= $item['order_id'] ?></td>
                        <td class="fw-600 text-sm"><?= htmlspecialchars($item['item_name']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td class="text-red"><?= $item['returned'] ?></td>
                        <td>
                            <?php if ($max > 0): ?>
                                <input type="number" name="returns[<?=$item['order_id']?>][<?=$item['item_id']?>]"
                                       min="0" max="<?=$max?>" value="0"
                                       style="width:80px;padding:6px;text-align:center">
                            <?php else: ?>
                                <span class="badge badge-gray">Fully Returned</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; $items_stmt->close(); ?>
                </tbody>
            </table>
        </div>
        <?php if ($has): ?>
        <div class="form-actions">
            <button type="submit" name="submit_return" class="btn btn-warning">
                <i class="fa-solid fa-check-double"></i> Submit Return
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>
<?php
        endif;
    endif;
endif;
?>

<!-- History Filter -->
<form method="GET" action="return_orders.php">
    <div class="filter-bar">
        <div class="form-group">
            <label>Route</label>
            <select name="route_id">
                <option value="">All Routes</option>
                <?php if ($routes_q) while ($r = $routes_q->fetch_assoc()): ?>
                    <option value="<?=$r['id']?>" <?=$f_route==$r['id']?'selected':''?>><?= htmlspecialchars($r['route_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Shop</label>
            <select name="shop_id">
                <option value="">All Shops</option>
                <?php if ($shops_q) while ($s = $shops_q->fetch_assoc()): ?>
                    <option value="<?=$s['id']?>" <?=$f_shop==$s['id']?'selected':''?>><?= htmlspecialchars($s['shop_name']) ?></option>
                <?php endwhile; ?>
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
                <?php foreach ([10,25,50] as $n): ?><option value="<?=$n?>" <?=$per_page==$n?'selected':''?>><?=$n?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="return_orders.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
    <div class="date-presets" style="margin-bottom:12px">
        <button type="button" class="date-preset-btn" data-preset="today">Today</button>
        <button type="button" class="date-preset-btn" data-preset="week">This Week</button>
        <button type="button" class="date-preset-btn" data-preset="month">This Month</button>
    </div>
</form>

<!-- Return History -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Return History</span>
        <span class="badge badge-blue"><?= $total ?> records</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Return ID</th><th>Orders</th><th>Route &rsaquo; Shop</th><th>SR</th><th>Items</th><th>Refund</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($hist_rows->num_rows > 0):
                    $three_days_ago = strtotime('-3 days');
                    while ($row = $hist_rows->fetch_assoc()):
                        /* Fetch items for this return */
                        $ri_q = $conn->prepare("SELECT ri.return_qty, i.item_name FROM order_return_items ri JOIN items i ON i.id=ri.item_id WHERE ri.return_id=?");
                        $ri_q->bind_param("i", $row['id']); $ri_q->execute(); $ri_res = $ri_q->get_result();
                        $item_parts = [];
                        while ($ri = $ri_res->fetch_assoc()) { $item_parts[] = htmlspecialchars($ri['item_name']) . ' × ' . $ri['return_qty']; }
                        $ri_q->close();
                        $can_delete = (strtotime($row['created_at']) >= $three_days_ago);
                ?>
                <tr>
                    <td class="fw-600 text-muted">RET-<?= $row['id'] ?></td>
                    <td class="text-sm"><?= htmlspecialchars($row['order_ids']) ?></td>
                    <td>
                        <span class="text-muted text-xs"><?= htmlspecialchars($row['route_name']) ?></span><br>
                        <strong class="text-sm"><?= htmlspecialchars($row['shop_name']) ?></strong>
                    </td>
                    <td class="text-sm"><?= htmlspecialchars($row['username'] ?? '—') ?></td>
                    <td class="text-sm"><?= implode('<br>', $item_parts) ?: '—' ?></td>
                    <td class="fw-700 text-red">-<?= number_format($row['total_return_value'], 0) ?></td>
                    <td class="text-muted text-sm"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                    <td>
                        <?php if ($can_delete): ?>
                        <form method="POST" style="display:inline">
        <?= csrf_field() ?>
                            <input type="hidden" name="delete_return_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm btn-icon"
                                    onclick="return confirm('Delete RET-<?=$row['id']?>? This cannot be undone.')">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                        <?php else: ?>
                            <span class="badge badge-gray">Locked</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding:30px">No returns found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php $base = "return_orders.php?route_id=$f_route&shop_id=$f_shop&date_from=$f_from&date_to=$f_to&per_page=$per_page&page="; ?>
        <a href="<?=$base?>1"                   class="page-btn <?=$page==1?'disabled':''?>"><i class="fa-solid fa-angles-left"></i></a>
        <a href="<?=$base.max(1,$page-1)?>"     class="page-btn <?=$page==1?'disabled':''?>"><i class="fa-solid fa-angle-left"></i></a>
        <?php for ($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
            <a href="<?=$base.$p?>" class="page-btn <?=$p==$page?'active':''?>"><?=$p?></a>
        <?php endfor; ?>
        <a href="<?=$base.min($total_pages,$page+1)?>" class="page-btn <?=$page==$total_pages?'disabled':''?>"><i class="fa-solid fa-angle-right"></i></a>
        <a href="<?=$base.$total_pages?>"              class="page-btn <?=$page==$total_pages?'disabled':''?>"><i class="fa-solid fa-angles-right"></i></a>
    </div>
    <?php endif; ?>
</div>

<?php $hist_q->close(); include 'footer.php'; ?>
