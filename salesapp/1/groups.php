<?php
$pageTitle = 'Groups';
include 'header.php';
if (!$is_manager) { header("Location: index.php"); exit; }

$cid = (int)$_SESSION['company_id'];
$success = $error = '';

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $div_id  = (int)($_POST['division_id'] ?? 0);
    $lead_id = (int)($_POST['team_lead_id'] ?? 0) ?: null;
    $status  = (int)($_POST['status'] ?? 1);

    if ($name === '' || !$div_id) { $error = 'Group name and division are required.'; }
    elseif (isset($_POST['add_group'])) {
        $stmt = $conn->prepare("INSERT INTO sales_groups (name, description, division_id, company_id, team_lead_id, status, created_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("ssiiiii", $name, $desc, $div_id, $cid, $lead_id, $status, $_SESSION['user_id']);
        $stmt->execute(); $stmt->close();
        header("Location: groups.php?msg=created"); exit;
    } elseif (isset($_POST['update_group'])) {
        $gid = (int)$_GET['edit'];
        $stmt = $conn->prepare("UPDATE sales_groups SET name=?,description=?,division_id=?,team_lead_id=?,status=? WHERE id=? AND company_id=?");
        $stmt->bind_param("ssiiiiii", $name, $desc, $div_id, $lead_id, $status, $gid, $cid);
        $stmt->execute(); $stmt->close();
        header("Location: groups.php?msg=updated"); exit;
    }
}

/* ── Load edit ── */
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM sales_groups WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", (int)$_GET['edit'], $cid);
    $stmt->execute(); $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

/* ── Divisions dropdown ── */
$divs_res = $conn->prepare("SELECT id, name FROM divisions WHERE company_id=? AND status=1 ORDER BY name");
$divs_res->bind_param("i", $cid); $divs_res->execute();
$divs = $divs_res->get_result();

/* ── SRs for team lead dropdown ── */
$srs_res = $conn->prepare("SELECT id, username FROM users WHERE company_id=? AND role IN (2,3) AND status=1 ORDER BY username");
$srs_res->bind_param("i", $cid); $srs_res->execute();
$srs = $srs_res->get_result();

/* ── Filter by division ── */
$filter_div = (int)($_GET['div'] ?? 0);

/* ── Groups list ── */
$where = $filter_div ? "AND sg.division_id=$filter_div" : '';
$groups_q = $conn->prepare(
    "SELECT sg.*, d.name AS division_name, u.username AS lead_name,
     (SELECT COUNT(*) FROM user_group_assignments uga WHERE uga.group_id=sg.id AND uga.is_active=1) AS sr_count
     FROM sales_groups sg
     JOIN divisions d ON d.id=sg.division_id
     LEFT JOIN users u ON u.id=sg.team_lead_id
     WHERE sg.company_id=? $where ORDER BY d.name, sg.name"
);
$groups_q->bind_param("i", $cid); $groups_q->execute();
$groups_res = $groups_q->get_result();

if (isset($_GET['msg'])) $success = $_GET['msg'] === 'created' ? 'Group created.' : 'Group updated.';
?>

<div class="page-header">
    <div><div class="page-title">Groups</div><div class="page-subtitle">SR groups within divisions</div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $edit_data ? 'Edit Group' : 'Add Group' ?></span>
        <?php if ($edit_data): ?><a href="groups.php" class="btn btn-ghost btn-sm">Cancel</a><?php endif; ?>
    </div>
    <form method="POST" action="groups.php<?= $edit_data ? '?edit='.(int)$_GET['edit'] : '' ?>">
        <?= csrf_field() ?>
        <div class="grid-layout md-3">
            <div class="form-group">
                <label>Division <span style="color:var(--danger)">*</span></label>
                <select name="division_id" required>
                    <option value="">Select Division</option>
                    <?php $divs->data_seek(0); while ($d = $divs->fetch_assoc()): $sel = ($edit_data && $edit_data['division_id']==$d['id'])||($filter_div==$d['id']) ? 'selected':''; ?>
                        <option value="<?= $d['id'] ?>" <?= $sel ?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Group Name <span style="color:var(--danger)">*</span></label>
                <input type="text" name="name" placeholder="e.g. Team Alpha" required
                       value="<?= htmlspecialchars($edit_data['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Team Lead (SR)</label>
                <select name="team_lead_id">
                    <option value="">No Lead Assigned</option>
                    <?php $srs->data_seek(0); while ($sr = $srs->fetch_assoc()): $sel = ($edit_data && $edit_data['team_lead_id']==$sr['id']) ? 'selected':''; ?>
                        <option value="<?= $sr['id'] ?>" <?= $sel ?>><?= htmlspecialchars($sr['username']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" value="<?= htmlspecialchars($edit_data['description'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="1" <?= (!$edit_data||$edit_data['status']==1)?'selected':'' ?>>Active</option>
                    <option value="0" <?= ($edit_data&&$edit_data['status']==0)?'selected':'' ?>>Inactive</option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" name="<?= $edit_data ? 'update_group' : 'add_group' ?>" class="btn btn-primary">
                <i class="fa-solid <?= $edit_data ? 'fa-pen' : 'fa-plus' ?>"></i> <?= $edit_data ? 'Update' : 'Add Group' ?>
            </button>
        </div>
    </form>
</div>

<!-- Filter -->
<form method="GET" action="groups.php" style="margin-bottom:12px">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="min-width:180px">
            <label>Filter by Division</label>
            <select name="div">
                <option value="">All Divisions</option>
                <?php $divs->data_seek(0); while ($d = $divs->fetch_assoc()): ?>
                    <option value="<?= $d['id'] ?>" <?= $filter_div==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="groups.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
</form>

<div class="card">
    <div class="card-header"><span class="card-title">All Groups</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Group Name</th><th>Division</th><th>Team Lead</th><th>SRs</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($groups_res->num_rows > 0): ?>
                    <?php while ($row = $groups_res->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong>
                            <?php if ($row['description']): ?><div class="text-muted text-xs"><?= htmlspecialchars($row['description']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-sm"><?= htmlspecialchars($row['division_name']) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($row['lead_name'] ?? '—') ?></td>
                        <td><span class="badge badge-green"><?= $row['sr_count'] ?> SRs</span></td>
                        <td><span class="badge <?= $row['status'] ? 'badge-green' : 'badge-red' ?>"><?= $row['status'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <a href="groups.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm btn-icon"><i class="fa-solid fa-pen"></i></a>
                            <a href="sr_assignment.php?group=<?= $row['id'] ?>" class="btn btn-info btn-sm btn-icon" title="Manage SRs"><i class="fa-solid fa-user-plus"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No groups yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $groups_q->close(); include 'footer.php'; ?>
