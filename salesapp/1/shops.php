<?php
$pageTitle = 'Shops';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$uid = (int)$_SESSION['user_id'];
$success = $error = '';

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = strtoupper(trim($_POST['shop_name'] ?? ''));
    $route_id = (int)$_POST['route_id'];
    $status   = (int)($_POST['status'] ?? 1);

    if ($name === '' || !$route_id) { $error = 'Shop name and route are required.'; }
    elseif (isset($_POST['add_shop'])) {
        $chk = $conn->prepare("SELECT id FROM shops WHERE shop_name=? AND company_id=?");
        $chk->bind_param("si", $name, $cid); $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) { $error = "Shop '$name' already exists."; }
        else {
            $chk->close();
            $stmt = $conn->prepare("INSERT INTO shops (shop_name, route_id, user_id, status, company_id) VALUES (?,?,?,?,?)");
            $stmt->bind_param("siiii", $name, $route_id, $uid, $status, $cid);
            $stmt->execute(); $stmt->close();
            header("Location: shops.php?msg=created"); exit;
        }
        $chk->close();
    } elseif (isset($_POST['update_shop'])) {
        $sid = (int)$_GET['edit'];
        $chk = $conn->prepare("SELECT id FROM shops WHERE shop_name=? AND company_id=? AND id!=?");
        $chk->bind_param("sii", $name, $cid, $sid); $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) { $error = "Shop '$name' already exists."; }
        else {
            $chk->close();
            $stmt = $conn->prepare("UPDATE shops SET shop_name=?,route_id=?,status=? WHERE id=? AND company_id=?");
            $stmt->bind_param("siiii", $name, $route_id, $status, $sid, $cid);
            $stmt->execute(); $stmt->close();
            header("Location: shops.php?msg=updated"); exit;
        }
        $chk->close();
    }
}

/* ── Load edit ── */
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM shops WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", (int)$_GET['edit'], $cid); $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

/* ── Routes for form ── */
$routes_q = $conn->query("SELECT id, route_name FROM routes WHERE company_id=$cid AND status=1 ORDER BY route_name");

/* ── Pagination + filter ── */
$f_route  = (int)($_GET['route_id'] ?? 0);
$f_status = $_GET['status'] ?? '';
$f_search = trim($_GET['search'] ?? '');
$per_page = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where  = ["s.company_id=$cid"];
$params = []; $types = '';
if ($f_route)    { $where[] = 's.route_id=?';    $params[] = $f_route;    $types .= 'i'; }
if ($f_status !== '') { $where[] = 's.status=?'; $params[] = (int)$f_status; $types .= 'i'; }
if ($f_search !== '') { $where[] = 's.shop_name LIKE ?'; $params[] = "%$f_search%"; $types .= 's'; }
$w = 'WHERE ' . implode(' AND ', $where);

$count_q = $conn->prepare("SELECT COUNT(*) AS c FROM shops s $w");
if ($types) $count_q->bind_param($types, ...$params);
$count_q->execute(); $total = (int)$count_q->get_result()->fetch_assoc()['c']; $count_q->close();
$total_pages = max(1, (int)ceil($total / $per_page));

$list_q = $conn->prepare(
    "SELECT s.*, r.route_name,
     COALESCE((SELECT SUM(oi.quantity*oi.price) FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.shop_id=s.id AND o.order_status=1),0) AS total_orders
     FROM shops s JOIN routes r ON r.id=s.route_id $w ORDER BY r.route_name, s.shop_name LIMIT ? OFFSET ?"
);
$lp = array_merge($params, [$per_page, $offset]); $lt = $types . 'ii';
$list_q->bind_param($lt, ...$lp); $list_q->execute(); $rows = $list_q->get_result();

if (isset($_GET['msg'])) $success = $_GET['msg'] === 'created' ? 'Shop created.' : 'Shop updated.';
?>

<div class="page-header">
    <div><div class="page-title">Shops</div><div class="page-subtitle">Customer shop accounts</div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $edit_data ? 'Edit Shop' : 'Add Shop' ?></span>
        <?php if ($edit_data): ?><a href="shops.php" class="btn btn-ghost btn-sm">Cancel</a><?php endif; ?>
    </div>
    <form method="POST" action="shops.php<?= $edit_data ? '?edit='.(int)$_GET['edit'] : '' ?>">
        <?= csrf_field() ?>
        <div class="grid-layout md-3">
            <div class="form-group">
                <label>Shop Details <span style="color:var(--danger)">*</span></label>
                <input type="text" name="shop_name" id="shop_name" required
                       placeholder="SHOP NAME, ADDRESS 01765236683"
                       value="<?= htmlspecialchars($edit_data['shop_name'] ?? '') ?>"
                       style="text-transform:uppercase">
                <div class="text-muted text-xs mt-4">Format: NAME, ADDRESS 01XXXXXXXXX</div>
            </div>
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
                <label>Status</label>
                <select name="status">
                    <option value="1" <?= (!$edit_data || $edit_data['status']==1)?'selected':'' ?>>Active</option>
                    <option value="0" <?= ($edit_data && $edit_data['status']==0)?'selected':'' ?>>Inactive</option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" name="<?= $edit_data ? 'update_shop' : 'add_shop' ?>" class="btn btn-primary">
                <i class="fa-solid <?= $edit_data ? 'fa-pen' : 'fa-plus' ?>"></i> <?= $edit_data ? 'Update Shop' : 'Add Shop' ?>
            </button>
        </div>
    </form>
</div>

<!-- Filter -->
<form method="GET" style="margin-bottom:12px">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="flex:1;min-width:180px">
            <label>Search</label>
            <input type="text" name="search" placeholder="Shop name..." value="<?= htmlspecialchars($f_search) ?>">
        </div>
        <div class="form-group" style="min-width:160px">
            <label>Route</label>
            <select name="route_id">
                <option value="">All Routes</option>
                <?php if ($routes_q) { $routes_q->data_seek(0); while ($r = $routes_q->fetch_assoc()): ?>
                    <option value="<?=$r['id']?>" <?=$f_route==$r['id']?'selected':''?>><?= htmlspecialchars($r['route_name']) ?></option>
                <?php endwhile; } ?>
            </select>
        </div>
        <div class="form-group" style="min-width:120px">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="1" <?=$f_status==='1'?'selected':''?>>Active</option>
                <option value="0" <?=$f_status==='0'?'selected':''?>>Inactive</option>
            </select>
        </div>
        <div class="form-group" style="max-width:90px">
            <label>Per Page</label>
            <select id="perPageSelect" name="per_page">
                <?php foreach ([10,25,50,100] as $n): ?><option value="<?=$n?>" <?=$per_page==$n?'selected':''?>><?=$n?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="shops.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <span class="card-title">All Shops</span>
        <span class="badge badge-blue"><?= $total ?> total</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Shop Name</th><th>Route</th><th>Balance</th><th>Orders</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($rows->num_rows > 0): ?>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <td>
                            <strong class="text-sm"><?= htmlspecialchars($row['shop_name']) ?></strong>
                        </td>
                        <td class="text-muted text-sm"><?= htmlspecialchars($row['route_name']) ?></td>
                        <td class="fw-600 <?= $row['balance'] > 0 ? 'text-green' : ($row['balance'] < 0 ? 'text-red' : '') ?>"><?= number_format($row['balance'], 0) ?></td>
                        <td class="text-sm"><?= number_format($row['total_orders'], 0) ?></td>
                        <td><span class="badge <?= $row['status'] ? 'badge-green' : 'badge-red' ?>"><?= $row['status'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <a href="shops.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm btn-icon"><i class="fa-solid fa-pen"></i></a>
                            <a href="orders.php?route_id=<?= $row['route_id'] ?>" class="btn btn-info btn-sm btn-icon" title="Orders"><i class="fa-solid fa-clipboard-list"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No shops found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php $base = "shops.php?search=".urlencode($f_search)."&route_id=$f_route&status=$f_status&per_page=$per_page&page="; ?>
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

<script>
document.getElementById('shop_name').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9,\s]/g,'').replace(/\s+/g,' ');
});
$(document).ready(function() { $('#route_id').select2({ width:'100%', placeholder:'Select Route' }); });
</script>

<?php $list_q->close(); include 'footer.php'; ?>
