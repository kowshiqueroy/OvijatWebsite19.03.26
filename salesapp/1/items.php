<?php
$pageTitle = 'Items';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$success = $error = '';

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = strtoupper(trim($_POST['item_name'] ?? ''));
    $price  = (float)str_replace(',', '', $_POST['price'] ?? 0);
    $status = (int)($_POST['status'] ?? 1);

    if ($name === '') { $error = 'Item name is required.'; }
    elseif ($price <= 0) { $error = 'Price must be greater than 0.'; }
    elseif (isset($_POST['add_item'])) {
        $chk = $conn->prepare("SELECT id FROM items WHERE item_name=? AND company_id=?");
        $chk->bind_param("si", $name, $cid); $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) { $error = "Item '$name' already exists."; }
        else {
            $chk->close();
            $stmt = $conn->prepare("INSERT INTO items (item_name, price, status, company_id) VALUES (?,?,?,?)");
            $stmt->bind_param("sdii", $name, $price, $status, $cid);
            $stmt->execute(); $stmt->close();
            header("Location: items.php?msg=created"); exit;
        }
        $chk->close();
    } elseif (isset($_POST['update_item'])) {
        $iid = (int)$_GET['edit'];
        $chk = $conn->prepare("SELECT id FROM items WHERE item_name=? AND company_id=? AND id!=?");
        $chk->bind_param("sii", $name, $cid, $iid); $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) { $error = "Item '$name' already exists."; }
        else {
            $chk->close();
            $stmt = $conn->prepare("UPDATE items SET item_name=?,price=?,status=? WHERE id=? AND company_id=?");
            $stmt->bind_param("sdiii", $name, $price, $status, $iid, $cid);
            $stmt->execute(); $stmt->close();
            header("Location: items.php?msg=updated"); exit;
        }
        $chk->close();
    }
}

/* ── Load edit ── */
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM items WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", (int)$_GET['edit'], $cid); $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

/* ── Pagination + filter ── */
$f_status = $_GET['status'] ?? '';
$f_search = trim($_GET['search'] ?? '');
$per_page = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where  = ["company_id=$cid"];
$params = []; $types = '';
if ($f_status !== '') { $where[] = 'status=?'; $params[] = (int)$f_status; $types .= 'i'; }
if ($f_search !== '') { $where[] = 'item_name LIKE ?'; $params[] = "%$f_search%"; $types .= 's'; }
$w = 'WHERE ' . implode(' AND ', $where);

$count_q = $conn->prepare("SELECT COUNT(*) AS c FROM items $w");
if ($types) $count_q->bind_param($types, ...$params);
$count_q->execute(); $total = (int)$count_q->get_result()->fetch_assoc()['c']; $count_q->close();
$total_pages = max(1, (int)ceil($total / $per_page));

$list_q = $conn->prepare("SELECT * FROM items $w ORDER BY item_name ASC LIMIT ? OFFSET ?");
$lp = array_merge($params, [$per_page, $offset]); $lt = $types . 'ii';
$list_q->bind_param($lt, ...$lp); $list_q->execute(); $rows = $list_q->get_result();

if (isset($_GET['msg'])) $success = $_GET['msg'] === 'created' ? 'Item created.' : 'Item updated.';
?>

<div class="page-header">
    <div><div class="page-title">Items</div><div class="page-subtitle">Product catalogue</div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $edit_data ? 'Edit Item' : 'Add Item' ?></span>
        <?php if ($edit_data): ?><a href="items.php" class="btn btn-ghost btn-sm">Cancel</a><?php endif; ?>
    </div>
    <form method="POST" action="items.php<?= $edit_data ? '?edit='.(int)$_GET['edit'] : '' ?>">
        <?= csrf_field() ?>
        <div class="grid-layout md-3">
            <div class="form-group">
                <label>Item Name <span style="color:var(--danger)">*</span></label>
                <input type="text" name="item_name" id="item_name" required
                       placeholder="e.g. JIRASAIL 50KG"
                       value="<?= htmlspecialchars($edit_data['item_name'] ?? '') ?>"
                       style="text-transform:uppercase">
                <div class="text-muted text-xs mt-4">Format: NAME QUANTITY+UNIT (e.g. RICE 25KG, OIL 1L)</div>
            </div>
            <div class="form-group">
                <label>Price (BDT) <span style="color:var(--danger)">*</span></label>
                <input type="number" name="price" step="0.01" min="0.01" placeholder="0.00" required
                       value="<?= htmlspecialchars($edit_data['price'] ?? '') ?>">
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
            <button type="submit" name="<?= $edit_data ? 'update_item' : 'add_item' ?>" class="btn btn-primary">
                <i class="fa-solid <?= $edit_data ? 'fa-pen' : 'fa-plus' ?>"></i> <?= $edit_data ? 'Update Item' : 'Add Item' ?>
            </button>
        </div>
    </form>
</div>

<!-- Filter -->
<form method="GET" style="margin-bottom:12px">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="flex:1;min-width:180px">
            <label>Search</label>
            <input type="text" name="search" placeholder="Item name..." value="<?= htmlspecialchars($f_search) ?>">
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
        <a href="items.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <span class="card-title">All Items</span>
        <span class="badge badge-blue"><?= $total ?> total</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item Name</th><th>Price (BDT)</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($rows->num_rows > 0): ?>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['item_name']) ?></strong></td>
                        <td class="fw-600"><?= number_format($row['price'], 2) ?></td>
                        <td><span class="badge <?= $row['status'] ? 'badge-green' : 'badge-red' ?>"><?= $row['status'] ? 'Active' : 'Inactive' ?></span></td>
                        <td><a href="items.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm btn-icon"><i class="fa-solid fa-pen"></i></a></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding:30px">No items found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php $base = "items.php?search=".urlencode($f_search)."&status=$f_status&per_page=$per_page&page="; ?>
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
document.getElementById('item_name').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9\s]/g,'').replace(/\s+/g,' ');
});
</script>

<?php $list_q->close(); include 'footer.php'; ?>
