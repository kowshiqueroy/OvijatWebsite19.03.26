<?php
$pageTitle = 'Surveys';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$uid = (int)$_SESSION['user_id'];
$success = $error = '';

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name    = trim($_POST['survey_name']    ?? '');
    $type    = trim($_POST['survey_type']    ?? '');
    $address = trim($_POST['survey_address'] ?? '');
    $phone   = trim($_POST['survey_phone']   ?? '');
    $route   = (int)($_POST['route_id']      ?? 0);
    $status  = (int)($_POST['status']        ?? 1);

    if ($name === '' || $type === '' || $address === '' || $phone === '' || !$route) {
        $error = 'All fields are required.';
    } elseif (isset($_POST['add_survey'])) {
        /* Duplicate check */
        $chk = $conn->prepare("SELECT id FROM surveys WHERE survey_name=? AND survey_type=? AND company_id=?");
        $chk->bind_param("ssi", $name, $type, $cid); $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) { $error = "A survey with this name and type already exists."; }
        else {
            $chk->close();
            $stmt = $conn->prepare("INSERT INTO surveys (survey_name, survey_type, survey_address, survey_phone, route_id, user_id, status, company_id) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssiii" . "i", $name, $type, $address, $phone, $route, $uid, $status, $cid);
            $stmt->execute(); $stmt->close();
            header("Location: survey.php?msg=created"); exit;
        }
        $chk->close();
    } elseif (isset($_POST['update_survey'])) {
        $sid = (int)$_GET['edit'];
        $chk = $conn->prepare("SELECT id FROM surveys WHERE survey_name=? AND survey_type=? AND company_id=? AND id!=?");
        $chk->bind_param("ssii", $name, $type, $cid, $sid); $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) { $error = "A survey with this name and type already exists."; }
        else {
            $chk->close();
            $stmt = $conn->prepare("UPDATE surveys SET survey_name=?,survey_type=?,survey_address=?,survey_phone=?,route_id=?,status=? WHERE id=? AND company_id=?");
            $stmt->bind_param("ssssiiii", $name, $type, $address, $phone, $route, $status, $sid, $cid);
            $stmt->execute(); $stmt->close();
            header("Location: survey.php?msg=updated"); exit;
        }
        $chk->close();
    }
}

/* ── Load edit ── */
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM surveys WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", (int)$_GET['edit'], $cid); $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

/* ── Routes dropdown ── */
$routes_q = $conn->query("SELECT id, route_name FROM routes WHERE company_id=$cid AND status=1 ORDER BY route_name");

/* ── Pagination + filter ── */
$f_route  = (int)($_GET['route_id'] ?? 0);
$f_type   = trim($_GET['type'] ?? '');
$per_page = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where  = ["s.company_id=$cid"];
$params = []; $types = '';
if ($f_route) { $where[] = 's.route_id=?'; $params[] = $f_route; $types .= 'i'; }
if ($f_type)  { $where[] = 's.survey_type LIKE ?'; $params[] = "%$f_type%"; $types .= 's'; }
$w = 'WHERE ' . implode(' AND ', $where);

$cnt_q = $conn->prepare("SELECT COUNT(*) AS c FROM surveys s $w");
if ($types) $cnt_q->bind_param($types, ...$params);
$cnt_q->execute(); $total = (int)$cnt_q->get_result()->fetch_assoc()['c']; $cnt_q->close();
$total_pages = max(1, (int)ceil($total / $per_page));

$list_q = $conn->prepare(
    "SELECT s.*, r.route_name FROM surveys s LEFT JOIN routes r ON r.id=s.route_id
     $w ORDER BY s.id DESC LIMIT ? OFFSET ?"
);
$lp = array_merge($params, [$per_page, $offset]); $lt = $types . 'ii';
$list_q->bind_param($lt, ...$lp); $list_q->execute(); $rows = $list_q->get_result();

if (isset($_GET['msg'])) $success = $_GET['msg'] === 'created' ? 'Survey created.' : 'Survey updated.';
?>

<div class="page-header">
    <div><div class="page-title">Surveys</div><div class="page-subtitle">Market survey records</div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Form -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $edit_data ? 'Edit Survey' : 'Add Survey' ?></span>
        <?php if ($edit_data): ?><a href="survey.php" class="btn btn-ghost btn-sm">Cancel</a><?php endif; ?>
    </div>
    <form method="POST" action="survey.php<?= $edit_data ? '?edit='.(int)$_GET['edit'] : '' ?>">
        <?= csrf_field() ?>
        <div class="grid-layout md-3">
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
                <label>Survey Type <span style="color:var(--danger)">*</span></label>
                <input type="text" name="survey_type" placeholder="e.g. Retail, Wholesale" required
                       value="<?= htmlspecialchars($edit_data['survey_type'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Survey Name <span style="color:var(--danger)">*</span></label>
                <input type="text" name="survey_name" placeholder="Name of surveyed entity" required
                       value="<?= htmlspecialchars($edit_data['survey_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Address <span style="color:var(--danger)">*</span></label>
                <input type="text" name="survey_address" placeholder="Full address" required
                       value="<?= htmlspecialchars($edit_data['survey_address'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Phone <span style="color:var(--danger)">*</span></label>
                <input type="text" name="survey_phone" placeholder="017XXXXXXXX" required
                       value="<?= htmlspecialchars($edit_data['survey_phone'] ?? '') ?>">
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
            <button type="submit" name="<?= $edit_data ? 'update_survey' : 'add_survey' ?>" class="btn btn-primary">
                <i class="fa-solid <?= $edit_data ? 'fa-pen' : 'fa-plus' ?>"></i>
                <?= $edit_data ? 'Update Survey' : 'Add Survey' ?>
            </button>
        </div>
    </form>
</div>

<!-- Filter -->
<form method="GET" style="margin-bottom:12px">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="min-width:160px">
            <label>Route</label>
            <select name="route_id">
                <option value="">All Routes</option>
                <?php if ($routes_q) { $routes_q->data_seek(0); while ($r = $routes_q->fetch_assoc()): ?>
                    <option value="<?=$r['id']?>" <?=$f_route==$r['id']?'selected':''?>><?= htmlspecialchars($r['route_name']) ?></option>
                <?php endwhile; } ?>
            </select>
        </div>
        <div class="form-group" style="min-width:140px">
            <label>Type</label>
            <input type="text" name="type" placeholder="Filter by type..." value="<?= htmlspecialchars($f_type) ?>">
        </div>
        <div class="form-group" style="max-width:90px">
            <label>Per Page</label>
            <select id="perPageSelect" name="per_page">
                <?php foreach ([10,25,50] as $n): ?><option value="<?=$n?>" <?=$per_page==$n?'selected':''?>><?=$n?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="survey.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
</form>

<!-- Survey List -->
<div class="card">
    <div class="card-header">
        <span class="card-title">All Surveys</span>
        <span class="badge badge-blue"><?= $total ?> total</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Type</th><th>Name</th><th>Address</th><th>Phone</th><th>Route</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($rows->num_rows > 0): ?>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <td><span class="badge badge-blue"><?= htmlspecialchars($row['survey_type']) ?></span></td>
                        <td class="fw-600 text-sm"><?= htmlspecialchars($row['survey_name']) ?></td>
                        <td class="text-sm text-muted"><?= htmlspecialchars($row['survey_address']) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($row['survey_phone']) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($row['route_name'] ?? '—') ?></td>
                        <td><span class="badge <?= $row['status'] ? 'badge-green' : 'badge-red' ?>"><?= $row['status'] ? 'Active' : 'Inactive' ?></span></td>
                        <td><a href="survey.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm btn-icon"><i class="fa-solid fa-pen"></i></a></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding:30px">No surveys found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php $base = "survey.php?route_id=$f_route&type=".urlencode($f_type)."&per_page=$per_page&page="; ?>
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
$(document).ready(function() { $('#route_id').select2({ width:'100%', placeholder:'Select Route' }); });
</script>

<?php $list_q->close(); include 'footer.php'; ?>
