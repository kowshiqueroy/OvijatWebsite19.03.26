<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) redirect('modules/admin/divisions.php', 'CSRF failed.', 'danger');

    if (isset($_POST['save_division'])) {
        $id     = intval($_POST['id'] ?? 0);
        $name   = sanitize($_POST['name']);
        $code   = sanitize($_POST['code'] ?? '');
        $region = sanitize($_POST['region'] ?? '');
        if ($id) {
            db_query("UPDATE sr_divisions SET name=?, code=?, region=? WHERE id=?", [$name, $code, $region, $id]);
            log_activity($_SESSION['user_id'], "Updated Division #$id: $name");
            redirect('modules/admin/divisions.php', 'Division updated.');
        } else {
            db_query("INSERT INTO sr_divisions (name, code, region) VALUES (?,?,?)", [$name, $code, $region]);
            log_activity($_SESSION['user_id'], "Created Division: $name");
            redirect('modules/admin/divisions.php', 'Division created.');
        }
    }

    if (isset($_POST['delete_division'])) {
        $id = intval($_POST['id']);
        db_query("UPDATE sr_divisions SET isDelete=1 WHERE id=?", [$id]);
        redirect('modules/admin/divisions.php', 'Division deleted.');
    }
}

$divisions = fetch_all("SELECT d.*, (SELECT COUNT(*) FROM users u WHERE u.division_id = d.id AND u.isDelete=0) as sr_count FROM sr_divisions d WHERE d.isDelete=0 ORDER BY d.name");
$edit = isset($_GET['edit']) ? fetch_one("SELECT * FROM sr_divisions WHERE id=?", [intval($_GET['edit'])]) : null;
?>

<div class="row align-items-center mb-4">
    <div class="col"><h3><i class="fas fa-map me-2"></i>Divisions / Territories</h3></div>
    <div class="col-auto">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#divModal"
            onclick="resetForm()"><i class="fas fa-plus me-1"></i> Add Division</button>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr><th>Name</th><th>Code</th><th>Region</th><th class="text-center">SRs</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($divisions as $d): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($d['name']); ?></strong></td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($d['code']); ?></span></td>
                    <td><?php echo htmlspecialchars($d['region']); ?></td>
                    <td class="text-center"><span class="badge bg-info"><?php echo $d['sr_count']; ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary edit-div-btn"
                            data-id="<?php echo $d['id']; ?>"
                            data-name="<?php echo htmlspecialchars($d['name']); ?>"
                            data-code="<?php echo htmlspecialchars($d['code']); ?>"
                            data-region="<?php echo htmlspecialchars($d['region']); ?>"
                            data-bs-toggle="modal" data-bs-target="#divModal">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this division?')">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                            <button type="submit" name="delete_division" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($divisions)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">No divisions yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="divModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <input type="hidden" name="id" id="div_id" value="0">
            <div class="modal-header"><h5 class="modal-title" id="div_modal_title">Add Division</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" id="div_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Code</label><input type="text" name="code" id="div_code" class="form-control" placeholder="e.g. DIV-NTH"></div>
                <div class="mb-3"><label class="form-label">Region</label><input type="text" name="region" id="div_region" class="form-control" placeholder="e.g. North Zone"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="save_division" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('div_id').value = '0';
    document.getElementById('div_name').value = '';
    document.getElementById('div_code').value = '';
    document.getElementById('div_region').value = '';
    document.getElementById('div_modal_title').textContent = 'Add Division';
}
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.edit-div-btn');
    if (!btn) return;
    document.getElementById('div_id').value = btn.dataset.id;
    document.getElementById('div_name').value = btn.dataset.name;
    document.getElementById('div_code').value = btn.dataset.code;
    document.getElementById('div_region').value = btn.dataset.region;
    document.getElementById('div_modal_title').textContent = 'Edit Division';
});
</script>

<?php require_once '../../templates/footer.php'; ?>
