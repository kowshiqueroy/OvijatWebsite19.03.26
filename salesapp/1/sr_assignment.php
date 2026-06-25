<?php
$pageTitle = 'SR Assignment';
include 'header.php';
if (!$is_manager) { header("Location: index.php"); exit; }

$cid = (int)$_SESSION['company_id'];
$success = $error = '';

/* ── Assign SR to group ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_sr'])) {
    $sr_id   = (int)$_POST['sr_id'];
    $grp_id  = (int)$_POST['group_id'];

    if (!$sr_id || !$grp_id) {
        $error = 'Select both SR and group.';
    } else {
        /* Deactivate any previous assignment for this SR */
        $stmt = $conn->prepare("UPDATE user_group_assignments SET is_active=0 WHERE user_id=? AND company_id=?");
        $stmt->bind_param("ii", $sr_id, $cid); $stmt->execute(); $stmt->close();

        /* Check if this exact combo already existed */
        $stmt = $conn->prepare("SELECT id FROM user_group_assignments WHERE user_id=? AND group_id=? AND company_id=?");
        $stmt->bind_param("iii", $sr_id, $grp_id, $cid); $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $stmt = $conn->prepare("UPDATE user_group_assignments SET is_active=1, assigned_at=NOW(), assigned_by=? WHERE user_id=? AND group_id=? AND company_id=?");
            $stmt->bind_param("iiii", $_SESSION['user_id'], $sr_id, $grp_id, $cid);
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO user_group_assignments (user_id,group_id,company_id,assigned_by) VALUES (?,?,?,?)");
            $stmt->bind_param("iiii", $sr_id, $grp_id, $cid, $_SESSION['user_id']);
        }
        $stmt->execute(); $stmt->close();
        header("Location: sr_assignment.php?msg=assigned"); exit;
    }
}

/* ── Remove assignment ── */
if (isset($_GET['remove'])) {
    $rid = (int)$_GET['remove'];
    $stmt = $conn->prepare("UPDATE user_group_assignments SET is_active=0 WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", $rid, $cid); $stmt->execute(); $stmt->close();
    header("Location: sr_assignment.php?msg=removed"); exit;
}

/* ── Filter ── */
$filter_group = (int)($_GET['group'] ?? 0);
$filter_div   = (int)($_GET['div'] ?? 0);

/* ── Dropdowns ── */
$srs_res = $conn->prepare("SELECT id, username FROM users WHERE company_id=? AND role IN (2,3) AND status=1 ORDER BY username");
$srs_res->bind_param("i", $cid); $srs_res->execute(); $srs = $srs_res->get_result();

$divs_res = $conn->prepare("SELECT id, name FROM divisions WHERE company_id=? AND status=1 ORDER BY name");
$divs_res->bind_param("i", $cid); $divs_res->execute(); $divs = $divs_res->get_result();

$grps_res = $conn->prepare("SELECT sg.id, sg.name, d.name AS div_name FROM sales_groups sg JOIN divisions d ON d.id=sg.division_id WHERE sg.company_id=? AND sg.status=1 ORDER BY d.name, sg.name");
$grps_res->bind_param("i", $cid); $grps_res->execute(); $grps = $grps_res->get_result();

/* ── Assignment list ── */
$where  = $filter_group ? "AND uga.group_id=$filter_group" : ($filter_div ? "AND d.id=$filter_div" : '');
$assign_q = $conn->prepare(
    "SELECT uga.id, u.username, sg.name AS group_name, d.name AS div_name, uga.assigned_at
     FROM user_group_assignments uga
     JOIN users u ON u.id=uga.user_id
     JOIN sales_groups sg ON sg.id=uga.group_id
     JOIN divisions d ON d.id=sg.division_id
     WHERE uga.company_id=? AND uga.is_active=1 $where
     ORDER BY d.name, sg.name, u.username"
);
$assign_q->bind_param("i", $cid); $assign_q->execute(); $assign_res = $assign_q->get_result();

/* ── Unassigned SRs ── */
$unassigned_q = $conn->prepare(
    "SELECT id, username FROM users
     WHERE company_id=? AND role IN (2,3) AND status=1
       AND id NOT IN (SELECT user_id FROM user_group_assignments WHERE company_id=? AND is_active=1)
     ORDER BY username"
);
$unassigned_q->bind_param("ii", $cid, $cid); $unassigned_q->execute(); $unassigned_res = $unassigned_q->get_result();

if (isset($_GET['msg'])) $success = $_GET['msg']==='assigned' ? 'SR assigned successfully.' : 'Assignment removed.';
?>

<div class="page-header">
    <div><div class="page-title">SR Assignment</div><div class="page-subtitle">Assign Sales Reps to groups</div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($unassigned_res->num_rows > 0): ?>
<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i>
    <strong><?= $unassigned_res->num_rows ?> SR(s) not assigned</strong> to any group yet.
</div>
<?php endif; ?>

<!-- Assign Form -->
<div class="card">
    <div class="card-header"><span class="card-title">Assign SR to Group</span></div>
    <form method="POST">
        <?= csrf_field() ?>
        <div class="grid-layout md-3">
            <div class="form-group">
                <label>Sales Rep <span style="color:var(--danger)">*</span></label>
                <select name="sr_id" id="sr_id" required>
                    <option value="">Select SR</option>
                    <?php $srs->data_seek(0); while ($sr = $srs->fetch_assoc()): ?>
                        <option value="<?= $sr['id'] ?>"><?= htmlspecialchars($sr['username']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Group <span style="color:var(--danger)">*</span></label>
                <select name="group_id" id="group_id" required>
                    <option value="">Select Group</option>
                    <?php $grps->data_seek(0); while ($g = $grps->fetch_assoc()): $sel = ($filter_group==$g['id'])?'selected':''; ?>
                        <option value="<?= $g['id'] ?>" <?= $sel ?>><?= htmlspecialchars($g['div_name'].' &rsaquo; '.$g['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" name="assign_sr" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Assign</button>
        </div>
        <p class="text-muted text-xs mt-8">If the SR is already in another group, they will be moved to the new group.</p>
    </form>
</div>

<!-- Filter -->
<form method="GET" style="margin-bottom:12px">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group" style="min-width:160px">
            <label>Division</label>
            <select name="div">
                <option value="">All Divisions</option>
                <?php $divs->data_seek(0); while ($d = $divs->fetch_assoc()): ?>
                    <option value="<?= $d['id'] ?>" <?= $filter_div==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group" style="min-width:160px">
            <label>Group</label>
            <select name="group">
                <option value="">All Groups</option>
                <?php $grps->data_seek(0); while ($g = $grps->fetch_assoc()): ?>
                    <option value="<?= $g['id'] ?>" <?= $filter_group==$g['id']?'selected':'' ?>><?= htmlspecialchars($g['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="sr_assignment.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
</form>

<!-- Current Assignments -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Current Assignments</span>
        <span class="badge badge-blue"><?= $assign_res->num_rows ?> assigned</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>SR Username</th><th>Division</th><th>Group</th><th>Assigned At</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($assign_res->num_rows > 0): ?>
                    <?php while ($row = $assign_res->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
                        <td class="text-sm"><?= htmlspecialchars($row['div_name']) ?></td>
                        <td><span class="badge badge-blue"><?= htmlspecialchars($row['group_name']) ?></span></td>
                        <td class="text-muted text-sm"><?= date('d M Y', strtotime($row['assigned_at'])) ?></td>
                        <td>
                            <a href="sr_assignment.php?remove=<?= $row['id'] ?>"
                               onclick="return confirm('Remove this SR from their group?')"
                               class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-xmark"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding:30px">No assignments yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $assign_q->close(); $unassigned_q->close(); include 'footer.php'; ?>
