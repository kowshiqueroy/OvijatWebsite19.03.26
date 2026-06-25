<?php
$pageTitle = 'Routes';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$success = $error = '';

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = strtoupper(trim($_POST['route_name'] ?? ''));
    $status = (int)($_POST['status'] ?? 1);

    if ($name === '') { $error = 'Route name is required.'; }
    elseif (!preg_match('/^[A-Z]+(?:\s[A-Z]+)*$/', $name)) {
        $error = 'Route name must be UPPERCASE letters only (e.g. NORTH DHAKA).';
    } elseif (isset($_POST['add_route'])) {
        $chk = $conn->prepare("SELECT id FROM routes WHERE route_name=? AND company_id=?");
        $chk->bind_param("si", $name, $cid); $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) { $error = "Route '$name' already exists."; }
        else {
            $chk->close();
            $stmt = $conn->prepare("INSERT INTO routes (route_name, status, company_id) VALUES (?,?,?)");
            $stmt->bind_param("sii", $name, $status, $cid);
            $stmt->execute(); $stmt->close();
            header("Location: routes.php?msg=created"); exit;
        }
        $chk->close();
    } elseif (isset($_POST['update_route'])) {
        $rid = (int)$_GET['edit'];
        $chk = $conn->prepare("SELECT id FROM routes WHERE route_name=? AND company_id=? AND id!=?");
        $chk->bind_param("sii", $name, $cid, $rid); $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) { $error = "Route '$name' already exists."; }
        else {
            $chk->close();
            $stmt = $conn->prepare("UPDATE routes SET route_name=?,status=? WHERE id=? AND company_id=?");
            $stmt->bind_param("siii", $name, $status, $rid, $cid);
            $stmt->execute(); $stmt->close();
            header("Location: routes.php?msg=updated"); exit;
        }
        $chk->close();
    }
}

/* ── Load edit ── */
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM routes WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", (int)$_GET['edit'], $cid); $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

/* ── Pagination + filter ── */
$f_status  = $_GET['status'] ?? '';
$per_page  = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $per_page;

$where = ["company_id=$cid"];
if ($f_status !== '') $where[] = "status=" . ($f_status == '1' ? 1 : 0);
$w = 'WHERE ' . implode(' AND ', $where);

$total       = (int)$conn->query("SELECT COUNT(*) AS c FROM routes $w")->fetch_assoc()['c'];
$total_pages = max(1, (int)ceil($total / $per_page));
$rows        = $conn->query("SELECT r.*, (SELECT COUNT(*) FROM shops s WHERE s.route_id=r.id) AS shop_count FROM routes r $w ORDER BY r.route_name ASC LIMIT $per_page OFFSET $offset");

if (isset($_GET['msg'])) $success = $_GET['msg'] === 'created' ? 'Route created.' : 'Route updated.';
?>

<div class="page-header">
    <div><div class="page-title">Routes</div><div class="page-subtitle">Sales territory routes</div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $edit_data ? 'Edit Route' : 'Add Route' ?></span>
        <?php if ($edit_data): ?><a href="routes.php" class="btn btn-ghost btn-sm">Cancel</a><?php endif; ?>
    </div>
    <form method="POST" action="routes.php<?= $edit_data ? '?edit='.(int)$_GET['edit'] : '' ?>">
        <?= csrf_field() ?>
        <div class="grid-layout md-3">
            <div class="form-group">
                <label>Route Name <span style="color:var(--danger)">*</span></label>
                <input type="text" name="route_name" id="route_name" placeholder="e.g. NORTH DHAKA"
                       value="<?= htmlspecialchars($edit_data['route_name'] ?? '') ?>" required
                       style="text-transform:uppercase">
                <div class="text-muted text-xs mt-4">UPPERCASE letters only</div>
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
            <button type="submit" name="<?= $edit_data ? 'update_route' : 'add_route' ?>" class="btn btn-primary">
                <i class="fa-solid <?= $edit_data ? 'fa-pen' : 'fa-plus' ?>"></i> <?= $edit_data ? 'Update Route' : 'Add Route' ?>
            </button>
        </div>
    </form>
</div>

<!-- Filter -->
<form method="GET" style="margin-bottom:12px">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
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
        <a href="routes.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <span class="card-title">All Routes</span>
        <span class="badge badge-blue"><?= $total ?> total</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Route Name</th><th>Shops</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($rows && $rows->num_rows > 0): ?>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['route_name']) ?></strong></td>
                        <td><span class="badge badge-blue"><?= $row['shop_count'] ?></span></td>
                        <td><span class="badge <?= $row['status'] ? 'badge-green' : 'badge-red' ?>"><?= $row['status'] ? 'Active' : 'Inactive' ?></span></td>
                        <td><a href="routes.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm btn-icon"><i class="fa-solid fa-pen"></i></a></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding:30px">No routes found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php $base = "routes.php?status=$f_status&per_page=$per_page&page="; ?>
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
document.getElementById('route_name').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z\s]/g,'').replace(/\s+/g,' ');
});
</script>

<?php include 'footer.php'; ?>
