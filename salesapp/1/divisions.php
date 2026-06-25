<?php
$pageTitle = 'Divisions';
include 'header.php';
if (!$is_manager) { header("Location: index.php"); exit; }

$cid = (int)$_SESSION['company_id'];
$success = $error = '';

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $status = (int)($_POST['status'] ?? 1);

    if ($name === '') { $error = 'Division name is required.'; }
    elseif (isset($_POST['add_division'])) {
        $stmt = $conn->prepare("INSERT INTO divisions (name, description, company_id, status, created_by) VALUES (?,?,?,?,?)");
        $stmt->bind_param("ssiii", $name, $desc, $cid, $status, $_SESSION['user_id']);
        $stmt->execute(); $stmt->close();
        header("Location: divisions.php?msg=created"); exit;
    } elseif (isset($_POST['update_division'])) {
        $did = (int)$_GET['edit'];
        $stmt = $conn->prepare("UPDATE divisions SET name=?,description=?,status=? WHERE id=? AND company_id=?");
        $stmt->bind_param("ssiii", $name, $desc, $status, $did, $cid);
        $stmt->execute(); $stmt->close();
        header("Location: divisions.php?msg=updated"); exit;
    }
}

/* ── Load edit ── */
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM divisions WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", (int)$_GET['edit'], $cid);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ── List (with group + SR counts) ── */
$divisions = $conn->prepare(
    "SELECT d.*,
     (SELECT COUNT(*) FROM sales_groups sg WHERE sg.division_id=d.id) AS group_count,
     (SELECT COUNT(*) FROM sales_groups sg JOIN user_group_assignments uga ON uga.group_id=sg.id AND uga.is_active=1 WHERE sg.division_id=d.id) AS sr_count
     FROM divisions d WHERE d.company_id=? ORDER BY d.id DESC"
);
$divisions->bind_param("i", $cid); $divisions->execute();
$div_res = $divisions->get_result();

if (isset($_GET['msg'])) $success = $_GET['msg'] === 'created' ? 'Division created.' : 'Division updated.';
?>

<div class="page-header">
    <div><div class="page-title">Divisions</div><div class="page-subtitle">Organizational divisions within <?= htmlspecialchars($company_name) ?></div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $edit_data ? 'Edit Division' : 'Add Division' ?></span>
        <?php if ($edit_data): ?><a href="divisions.php" class="btn btn-ghost btn-sm">Cancel</a><?php endif; ?>
    </div>
    <form method="POST" action="divisions.php<?= $edit_data ? '?edit='.(int)$_GET['edit'] : '' ?>">
        <?= csrf_field() ?>
        <div class="grid-layout md-3">
            <div class="form-group">
                <label>Division Name <span style="color:var(--danger)">*</span></label>
                <input type="text" name="name" placeholder="e.g. Dhaka North" required
                       value="<?= htmlspecialchars($edit_data['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" placeholder="Optional description"
                       value="<?= htmlspecialchars($edit_data['description'] ?? '') ?>">
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
            <button type="submit" name="<?= $edit_data ? 'update_division' : 'add_division' ?>" class="btn btn-primary">
                <i class="fa-solid <?= $edit_data ? 'fa-pen' : 'fa-plus' ?>"></i> <?= $edit_data ? 'Update' : 'Add Division' ?>
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">All Divisions</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Name</th><th>Description</th><th>Groups</th><th>SRs</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($div_res->num_rows > 0): ?>
                    <?php while ($row = $div_res->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td class="text-sm text-muted"><?= htmlspecialchars($row['description'] ?? '—') ?></td>
                        <td><span class="badge badge-blue"><?= $row['group_count'] ?></span></td>
                        <td><span class="badge badge-green"><?= $row['sr_count'] ?></span></td>
                        <td><span class="badge <?= $row['status'] ? 'badge-green' : 'badge-red' ?>"><?= $row['status'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <a href="divisions.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm btn-icon"><i class="fa-solid fa-pen"></i></a>
                            <a href="groups.php?div=<?= $row['id'] ?>" class="btn btn-info btn-sm btn-icon" title="View Groups"><i class="fa-solid fa-people-group"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No divisions yet. Add one above.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $divisions->close(); include 'footer.php'; ?>
