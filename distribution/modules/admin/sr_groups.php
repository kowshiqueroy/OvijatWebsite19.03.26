<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) redirect('modules/admin/sr_groups.php', 'CSRF failed.', 'danger');

    if (isset($_POST['save_group'])) {
        $id          = intval($_POST['id'] ?? 0);
        $name        = sanitize($_POST['name']);
        $division_id = intval($_POST['division_id'] ?? 0) ?: null;
        $leader_id   = intval($_POST['leader_id'] ?? 0) ?: null;
        if ($id) {
            db_query("UPDATE sr_groups SET name=?, division_id=?, leader_id=? WHERE id=?", [$name, $division_id, $leader_id, $id]);
            log_activity($_SESSION['user_id'], "Updated SR Group #$id: $name");
            redirect('modules/admin/sr_groups.php', 'Group updated.');
        } else {
            db_query("INSERT INTO sr_groups (name, division_id, leader_id) VALUES (?,?,?)", [$name, $division_id, $leader_id]);
            log_activity($_SESSION['user_id'], "Created SR Group: $name");
            redirect('modules/admin/sr_groups.php', 'Group created.');
        }
    }

    if (isset($_POST['assign_sr'])) {
        $sr_id    = intval($_POST['sr_id']);
        $group_id = intval($_POST['group_id']) ?: null;
        $div_id   = intval($_POST['division_id_sr']) ?: null;
        db_query("UPDATE users SET group_id=?, division_id=? WHERE id=?", [$group_id, $div_id, $sr_id]);
        log_activity($_SESSION['user_id'], "Assigned User #$sr_id to Group #$group_id, Division #$div_id");
        redirect('modules/admin/sr_groups.php', 'SR assignment updated.');
    }

    if (isset($_POST['delete_group'])) {
        db_query("UPDATE sr_groups SET isDelete=1 WHERE id=?", [intval($_POST['id'])]);
        redirect('modules/admin/sr_groups.php', 'Group deleted.');
    }
}

$groups    = fetch_all("SELECT g.*, d.name as division_name, u.username as leader_name,
                        (SELECT COUNT(*) FROM users uu WHERE uu.group_id = g.id AND uu.isDelete=0) as member_count
                        FROM sr_groups g
                        LEFT JOIN sr_divisions d ON g.division_id = d.id
                        LEFT JOIN users u ON g.leader_id = u.id
                        WHERE g.isDelete=0 ORDER BY g.name");
$divisions = fetch_all("SELECT * FROM sr_divisions WHERE isDelete=0 ORDER BY name");
$sr_users  = fetch_all("SELECT u.*, d.name as division_name, g.name as group_name
                        FROM users u
                        LEFT JOIN sr_divisions d ON u.division_id = d.id
                        LEFT JOIN sr_groups g ON u.group_id = g.id
                        WHERE u.isDelete=0 AND u.role IN (?,?)
                        ORDER BY u.username", [ROLE_SR, ROLE_MANAGER]);
?>

<div class="row align-items-center mb-4">
    <div class="col"><h3><i class="fas fa-users me-2"></i>SR Groups & Assignments</h3></div>
    <div class="col-auto">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal" onclick="resetGroupForm()">
            <i class="fas fa-plus me-1"></i> Add Group
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Groups list -->
    <div class="col-md-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white">Groups</div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Group</th><th>Division</th><th>Leader</th><th class="text-center">Members</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($groups as $g): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($g['name']); ?></strong></td>
                            <td class="small"><?php echo htmlspecialchars($g['division_name'] ?? '—'); ?></td>
                            <td class="small"><?php echo htmlspecialchars($g['leader_name'] ?? '—'); ?></td>
                            <td class="text-center"><span class="badge bg-info"><?php echo $g['member_count']; ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary edit-group-btn"
                                    data-id="<?php echo $g['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($g['name']); ?>"
                                    data-division="<?php echo $g['division_id']; ?>"
                                    data-leader="<?php echo $g['leader_id']; ?>"
                                    data-bs-toggle="modal" data-bs-target="#groupModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete group?')">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                                    <button type="submit" name="delete_group" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($groups)): ?><tr><td colspan="5" class="text-center text-muted py-3">No groups yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SR Assignments -->
    <div class="col-md-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white">SR Assignments</div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>SR / Manager</th><th>Current Group</th><th>Current Division</th><th>Edit</th></tr></thead>
                    <tbody>
                        <?php foreach ($sr_users as $u): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                <span class="badge bg-secondary ms-1 small"><?php echo $u['role']; ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($u['group_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($u['division_name'] ?? '—'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning assign-sr-btn"
                                    data-user-id="<?php echo $u['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($u['username']); ?>"
                                    data-group="<?php echo $u['group_id']; ?>"
                                    data-division="<?php echo $u['division_id']; ?>"
                                    data-bs-toggle="modal" data-bs-target="#assignModal">
                                    <i class="fas fa-link"></i> Assign
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sr_users)): ?><tr><td colspan="4" class="text-center text-muted py-3">No SRs found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Group Modal -->
<div class="modal fade" id="groupModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <input type="hidden" name="id" id="grp_id" value="0">
            <div class="modal-header"><h5 class="modal-title" id="grp_modal_title">Add Group</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Group Name <span class="text-danger">*</span></label><input type="text" name="name" id="grp_name" class="form-control" required></div>
                <div class="mb-3">
                    <label class="form-label">Division</label>
                    <select name="division_id" id="grp_division" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($divisions as $d): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Group Leader (SR)</label>
                    <select name="leader_id" id="grp_leader" class="form-select select2">
                        <option value="">— None —</option>
                        <?php foreach ($sr_users as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="save_group" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<!-- Assign SR Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <input type="hidden" name="sr_id" id="asr_id">
            <div class="modal-header"><h5 class="modal-title">Assign: <span id="asr_name"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Group</label>
                    <select name="group_id" id="asr_group" class="form-select select2">
                        <option value="">— None —</option>
                        <?php foreach ($groups as $g): ?><option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Division</label>
                    <select name="division_id_sr" id="asr_div" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($divisions as $d): ?><option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="assign_sr" class="btn btn-warning">Save Assignment</button></div>
        </form>
    </div>
</div>

<script>
function resetGroupForm() {
    document.getElementById('grp_id').value = '0';
    document.getElementById('grp_name').value = '';
    document.getElementById('grp_modal_title').textContent = 'Add Group';
}
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.edit-group-btn');
    if (btn) {
        document.getElementById('grp_id').value = btn.dataset.id;
        document.getElementById('grp_name').value = btn.dataset.name;
        document.getElementById('grp_division').value = btn.dataset.division || '';
        document.getElementById('grp_leader').value = btn.dataset.leader || '';
        document.getElementById('grp_modal_title').textContent = 'Edit Group';
    }
    const abtn = e.target.closest('.assign-sr-btn');
    if (abtn) {
        document.getElementById('asr_id').value = abtn.dataset.userId;
        document.getElementById('asr_name').textContent = abtn.dataset.username;
        document.getElementById('asr_group').value = abtn.dataset.group || '';
        document.getElementById('asr_div').value = abtn.dataset.division || '';
    }
});
</script>

<?php require_once '../../templates/footer.php'; ?>
