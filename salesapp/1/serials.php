<?php
$pageTitle = 'Legacy Serials';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$uid = (int)$_SESSION['user_id'];

/* ── Mark as printed (manager only) ── */
if (isset($_GET['print_id']) && $is_manager) {
    $pid  = (int)$_GET['print_id'];
    $stmt = $conn->prepare("UPDATE serials SET printed_at=NOW() WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", $pid, $cid); $stmt->execute(); $stmt->close();

    /* Confirm all contained orders */
    $stmt = $conn->prepare("SELECT order_ids FROM serials WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", $pid, $cid); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($row) {
        foreach (array_filter(array_map('intval', explode(',', $row['order_ids']))) as $oid) {
            $upd = $conn->prepare("UPDATE orders SET order_status=1, approved_at=NOW(), approved_by=? WHERE id=? AND company_id=?");
            $upd->bind_param("iii", $uid, $oid, $cid); $upd->execute(); $upd->close();
        }
    }
    header("Location: serials.php?msg=printed"); exit;
}

/* ── Filters + pagination ── */
$f_from   = $_GET['date_from'] ?? date('Y-m-01');
$f_to     = $_GET['date_to']   ?? date('Y-m-t');
$per_page = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where  = ["s.company_id=$cid", "DATE(s.created_at) BETWEEN ? AND ?"];
$params = [$f_from, $f_to]; $types = 'ss';
$w = 'WHERE ' . implode(' AND ', $where);

$cnt_q = $conn->prepare("SELECT COUNT(*) AS c FROM serials s $w");
$cnt_q->bind_param($types, ...$params); $cnt_q->execute();
$total = (int)$cnt_q->get_result()->fetch_assoc()['c']; $cnt_q->close();
$total_pages = max(1, (int)ceil($total / $per_page));

$list_q = $conn->prepare(
    "SELECT s.*, u.username AS assigned_to, cb.username AS created_by_name
     FROM serials s
     LEFT JOIN users u ON u.id=s.user_id
     LEFT JOIN users cb ON cb.id=s.created_by
     $w ORDER BY s.id DESC LIMIT ? OFFSET ?"
);
$lp = array_merge($params, [$per_page, $offset]); $lt = $types . 'ii';
$list_q->bind_param($lt, ...$lp); $list_q->execute(); $rows = $list_q->get_result();

$success = isset($_GET['msg']) && $_GET['msg'] === 'printed' ? 'Serial marked as printed.' : '';
?>

<div class="page-header">
    <div>
        <div class="page-title">Legacy Serials</div>
        <div class="page-subtitle">Read-only view of serials from the old delivery system</div>
    </div>
    <a href="truck_loads.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-truck"></i> New Truck Loads
    </a>
</div>

<div class="alert alert-info">
    <i class="fa-solid fa-circle-info"></i>
    <strong>Legacy data.</strong> This page shows serials created before the v3.0 truck load system. For new deliveries, use <a href="truck_loads.php" style="color:var(--info)"><strong>Truck Loads</strong></a>.
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" action="serials.php">
    <div class="filter-bar">
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
                <?php foreach ([10,25,50] as $n): ?>
                    <option value="<?=$n?>" <?=$per_page==$n?'selected':''?>><?=$n?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">
            <i class="fa-solid fa-filter"></i> Filter
        </button>
        <a href="serials.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
    <div class="date-presets" style="margin-bottom:12px">
        <button type="button" class="date-preset-btn" data-preset="month">This Month</button>
        <button type="button" class="date-preset-btn" data-preset="last_month">Last Month</button>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <span class="card-title">Serials</span>
        <span class="badge badge-blue"><?= $total ?> total</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Order IDs</th>
                    <th>Assigned To</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th>Printed At</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows->num_rows > 0): ?>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted fw-600"><?= $row['id'] ?></td>
                        <td>
                            <span class="text-sm" style="font-family:monospace">
                                <?= htmlspecialchars($row['order_ids']) ?>
                            </span>
                        </td>
                        <td class="text-sm"><?= htmlspecialchars($row['assigned_to'] ?? '—') ?></td>
                        <td class="text-sm text-muted"><?= htmlspecialchars($row['created_by_name'] ?? '—') ?></td>
                        <td>
                            <span class="badge <?= $row['status'] ? 'badge-green' : 'badge-red' ?>">
                                <?= $row['status'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="text-sm">
                            <?php if ($row['printed_at']): ?>
                                <span class="badge badge-green">
                                    <?= date('d M Y', strtotime($row['printed_at'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-yellow">Not Printed</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted text-sm">
                            <?= date('d M Y', strtotime($row['created_at'])) ?>
                        </td>
                        <td>
                            <!-- Print invoice for legacy serial -->
                            <a href="invoices.php?order_ids=<?= htmlspecialchars(urlencode($row['order_ids'])) ?>"
                               target="_blank"
                               class="btn btn-dark btn-sm btn-icon" title="Print Invoice">
                                <i class="fa-solid fa-print"></i>
                            </a>
                            <?php if ($is_manager && !$row['printed_at']): ?>
                            <a href="serials.php?print_id=<?= $row['id'] ?>"
                               onclick="return confirm('Mark serial #<?= $row['id'] ?> as printed and confirm all orders?')"
                               class="btn btn-primary btn-sm btn-icon" title="Mark Printed">
                                <i class="fa-solid fa-check"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding:30px">
                            No legacy serials found for the selected period.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php $base = "serials.php?date_from=$f_from&date_to=$f_to&per_page=$per_page&page="; ?>
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
