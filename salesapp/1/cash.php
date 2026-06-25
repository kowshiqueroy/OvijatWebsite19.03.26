<?php
$pageTitle = 'Cash Collections';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$uid = (int)$_SESSION['user_id'];
$success = $error = '';

/* ── Approve (manager only) ── */
if (isset($_GET['approve_id'])) {
    if (!$is_manager) { header("Location: cash.php"); exit; }
    $aid = (int)$_GET['approve_id'];

    /* Fetch amount + shop before approving */
    $stmt = $conn->prepare("SELECT amount, shop_id, approved_at FROM cash_collections WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", $aid, $cid); $stmt->execute();
    $cc = $stmt->get_result()->fetch_assoc(); $stmt->close();

    if ($cc && $cc['approved_at'] === null) {
        /* Approve */
        $stmt = $conn->prepare("UPDATE cash_collections SET status=1, approved_at=NOW(), approved_by=? WHERE id=? AND company_id=?");
        $stmt->bind_param("iii", $uid, $aid, $cid); $stmt->execute(); $stmt->close();
        /* Adjust shop balance */
        $stmt = $conn->prepare("UPDATE shops SET balance=balance+? WHERE id=? AND company_id=?");
        $stmt->bind_param("dii", $cc['amount'], $cc['shop_id'], $cid); $stmt->execute(); $stmt->close();
        header("Location: cash.php?msg=approved"); exit;
    }
    header("Location: cash.php?msg=already_approved"); exit;
}

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_cash'])) {
        $shop_id  = (int)$_POST['shop_id'];
        $route_id = (int)$_POST['route_id'];
        $amount   = (float)$_POST['amount'];
        $remarks  = trim($_POST['remarks'] ?? '');
        $status   = 1;

        if (!$shop_id || !$route_id || $amount <= 0) {
            $error = 'Route, shop and amount are required.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO cash_collections (shop_id, route_id, amount, collected_by, collection_date, status, company_id, remarks)
                 VALUES (?,?,?,?,NOW(),?,?,?)"
            );
            $stmt->bind_param("iidiiis", $shop_id, $route_id, $amount, $uid, $status, $cid, $remarks);
            $stmt->execute(); $stmt->close();
            header("Location: cash.php?msg=created"); exit;
        }
    }

    if (isset($_POST['update_cash'])) {
        $ccid     = (int)$_GET['edit'];
        $shop_id  = (int)$_POST['shop_id'];
        $route_id = (int)$_POST['route_id'];
        $amount   = (float)$_POST['amount'];
        $remarks  = trim($_POST['remarks'] ?? '');

        /* Block edit if already approved */
        $chk = $conn->prepare("SELECT approved_at FROM cash_collections WHERE id=? AND company_id=?");
        $chk->bind_param("ii", $ccid, $cid); $chk->execute();
        $rec = $chk->get_result()->fetch_assoc(); $chk->close();
        if ($rec && $rec['approved_at'] !== null) {
            header("Location: cash.php?msg=already_approved"); exit;
        }

        $stmt = $conn->prepare("UPDATE cash_collections SET shop_id=?,route_id=?,amount=?,remarks=? WHERE id=? AND company_id=?");
        $stmt->bind_param("iidsii", $shop_id, $route_id, $amount, $remarks, $ccid, $cid);
        $stmt->execute(); $stmt->close();
        header("Location: cash.php?msg=updated"); exit;
    }
}

/* ── Load edit ── */
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM cash_collections WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", (int)$_GET['edit'], $cid); $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

/* ── Routes ── */
$routes_q = $conn->query("SELECT id, route_name FROM routes WHERE company_id=$cid AND status=1 ORDER BY route_name");

/* ── Filters ── */
$f_route   = (int)($_GET['route_id'] ?? 0);
$f_from    = $_GET['date_from'] ?? date('Y-m-01');
$f_to      = $_GET['date_to']   ?? date('Y-m-t');
$f_appr    = $_GET['approved']  ?? '';
$per_page  = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $per_page;

$where  = ["cc.company_id=$cid"];
$params = []; $types = '';
/* SRs see only their own */
if (!$is_manager) { $where[] = "cc.collected_by=$uid"; }
if ($f_route) { $where[] = 'cc.route_id=?'; $params[] = $f_route; $types .= 'i'; }
if ($f_appr === '1') $where[] = 'cc.approved_at IS NOT NULL';
if ($f_appr === '0') $where[] = 'cc.approved_at IS NULL';
$where[] = 'cc.collection_date BETWEEN ? AND ?';
$params[] = $f_from . ' 00:00:00'; $params[] = $f_to . ' 23:59:59'; $types .= 'ss';
$w = 'WHERE ' . implode(' AND ', $where);

$cnt_q = $conn->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(cc.amount),0) AS total FROM cash_collections cc $w");
$cnt_q->bind_param($types, ...$params); $cnt_q->execute();
$summary = $cnt_q->get_result()->fetch_assoc(); $cnt_q->close();
$total   = (int)$summary['c'];
$grand   = (float)$summary['total'];
$total_pages = max(1, (int)ceil($total / $per_page));

$list_q = $conn->prepare(
    "SELECT cc.*, s.shop_name, r.route_name, u.username AS collector_name
     FROM cash_collections cc
     JOIN shops s ON s.id=cc.shop_id
     JOIN routes r ON r.id=cc.route_id
     LEFT JOIN users u ON u.id=cc.collected_by
     $w ORDER BY cc.id DESC LIMIT ? OFFSET ?"
);
$lp = array_merge($params, [$per_page, $offset]); $lt = $types . 'ii';
$list_q->bind_param($lt, ...$lp); $list_q->execute(); $rows = $list_q->get_result();

$msgs = ['created'=>'Cash collection recorded.','updated'=>'Cash collection updated.',
         'approved'=>'Cash collection approved.','already_approved'=>'Already approved — cannot edit.'];
if (isset($_GET['msg'])) $success = $msgs[$_GET['msg']] ?? '';
?>

<div class="page-header">
    <div><div class="page-title">Cash Collections</div><div class="page-subtitle">Record and approve shop cash payments</div></div>
    <button class="btn btn-primary btn-sm" id="toggleForm">
        <i class="fa-solid fa-plus"></i> <?= $edit_data ? 'Editing #'.$edit_data['id'] : 'New Collection' ?>
    </button>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Form -->
<div class="card" id="cashForm" <?= $edit_data ? '' : 'style="display:none"' ?>>
    <div class="card-header">
        <span class="card-title"><?= $edit_data ? 'Edit Collection #'.$edit_data['id'] : 'New Cash Collection' ?></span>
        <?php if ($edit_data): ?><a href="cash.php" class="btn btn-ghost btn-sm">Cancel Edit</a><?php endif; ?>
    </div>
    <form method="POST" action="cash.php<?= $edit_data ? '?edit='.$edit_data['id'] : '' ?>">
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
                        <option value="<?=$edit_data['shop_id']?>" selected><?= htmlspecialchars($sv['shop_name'] ?? '') ?></option>
                    <?php else: ?>
                        <option value="">Select Shop</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Amount (BDT) <span style="color:var(--danger)">*</span></label>
                <input type="number" name="amount" step="0.01" min="0.01" required
                       value="<?= htmlspecialchars($edit_data['amount'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <input type="text" name="remarks" placeholder="Optional note"
                       value="<?= htmlspecialchars($edit_data['remarks'] ?? '') ?>">
            </div>
        </div>
        <div class="form-actions">
            <?php if ($edit_data): ?>
                <button type="submit" name="update_cash" class="btn btn-warning"><i class="fa-solid fa-pen"></i> Update</button>
            <?php else: ?>
                <button type="submit" name="add_cash" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Record Collection</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Filter -->
<form method="GET" action="cash.php">
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
            <label>Approval</label>
            <select name="approved">
                <option value="">All</option>
                <option value="0" <?=$f_appr==='0'?'selected':''?>>Pending</option>
                <option value="1" <?=$f_appr==='1'?'selected':''?>>Approved</option>
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
        <a href="cash.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
    <div class="date-presets" style="margin-bottom:12px">
        <span class="text-muted text-xs" style="margin-right:6px">Quick:</span>
        <button type="button" class="date-preset-btn" data-preset="today">Today</button>
        <button type="button" class="date-preset-btn" data-preset="week">This Week</button>
        <button type="button" class="date-preset-btn" data-preset="month">This Month</button>
    </div>
</form>

<!-- Summary KPIs -->
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
    <div class="kpi-card" style="flex:1;min-width:140px;padding:14px 16px">
        <div class="kpi-label">Records</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= number_format($total) ?></div>
    </div>
    <div class="kpi-card info" style="flex:1;min-width:140px;padding:14px 16px">
        <div class="kpi-label">Total Amount</div>
        <div class="kpi-value" style="font-size:1.4rem"><?= number_format($grand, 0) ?></div>
        <div class="kpi-sub">BDT</div>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Cash Collections</span>
        <button onclick="window.print()" class="btn btn-ghost btn-sm btn-icon print-hide"><i class="fa-solid fa-print"></i></button>
    </div>
    <div class="print-header"><h1><?= APP_NAME ?> &mdash; Cash Collections</h1><p>Period: <?=$f_from?> to <?=$f_to?> | <?= date('d M Y H:i') ?></p></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <?php if ($is_manager): ?><th>Collector</th><?php endif; ?>
                    <th>Route &rsaquo; Shop</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Approval</th>
                    <th>Remarks</th>
                    <th class="print-hide">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows->num_rows > 0): ?>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <?php if ($is_manager): ?><td class="text-sm"><?= htmlspecialchars($row['collector_name']) ?></td><?php endif; ?>
                        <td>
                            <span class="text-muted text-xs"><?= htmlspecialchars($row['route_name']) ?></span><br>
                            <strong class="text-sm"><?= htmlspecialchars($row['shop_name']) ?></strong>
                        </td>
                        <td class="fw-700 text-green"><?= number_format($row['amount'], 0) ?></td>
                        <td class="text-sm"><?= date('d M Y', strtotime($row['collection_date'])) ?></td>
                        <td>
                            <?php if ($row['approved_at']): ?>
                                <span class="badge badge-green">Approved</span>
                            <?php elseif ($is_manager): ?>
                                <a href="cash.php?approve_id=<?= $row['id'] ?>"
                                   onclick="return confirm('Approve this cash collection?')"
                                   class="badge badge-yellow" style="cursor:pointer;text-decoration:none">
                                   Pending &mdash; Approve?
                                </a>
                            <?php else: ?>
                                <span class="badge badge-yellow">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm text-muted"><?= htmlspecialchars($row['remarks'] ?? '—') ?></td>
                        <td class="print-hide">
                            <?php if (!$row['approved_at']): ?>
                            <a href="cash.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm btn-icon"><i class="fa-solid fa-pen"></i></a>
                            <?php else: ?>
                            <span class="text-muted text-xs">Locked</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="<?= $is_manager ? 8 : 7 ?>" class="text-center text-muted" style="padding:30px">No cash collections found.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--gray-100)">
                    <td colspan="<?= $is_manager ? 3 : 2 ?>" class="text-right fw-700">Total:</td>
                    <td class="fw-700 text-green"><?= number_format($grand, 0) ?></td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php $base = "cash.php?route_id=$f_route&date_from=$f_from&date_to=$f_to&approved=$f_appr&per_page=$per_page&page="; ?>
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
document.getElementById('toggleForm').addEventListener('click', function() {
    var f = document.getElementById('cashForm');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
});
$(document).ready(function() {
    $('#route_id').select2({ width:'100%', placeholder:'Select Route' });
    $('#shop_id').select2({ width:'100%', placeholder:'Select Shop' });
});
document.getElementById('route_id').addEventListener('change', function() {
    var rid = this.value;
    $('#shop_id').empty().append('<option value="">Loading...</option>');
    $.get('get_shops_by_route_id.php', { route_id: rid }, function(html) {
        $('#shop_id').html(html).trigger('change');
    });
});
</script>

<?php $list_q->close(); include 'footer.php'; ?>
