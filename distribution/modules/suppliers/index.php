<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);

// Handle add / edit supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) redirect('modules/suppliers/index.php', 'CSRF failed.', 'danger');

    if (isset($_POST['save_supplier'])) {
        $id      = intval($_POST['id'] ?? 0);
        $name    = sanitize($_POST['name']);
        $phone   = sanitize($_POST['phone'] ?? '');
        $email   = sanitize($_POST['email'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $ob      = floatval($_POST['opening_balance'] ?? 0);

        if ($id) {
            db_query("UPDATE suppliers SET name=?,phone=?,email=?,address=? WHERE id=?", [$name,$phone,$email,$address,$id]);
            log_activity($_SESSION['user_id'], "Updated Supplier #$id: $name");
            redirect('modules/suppliers/index.php', 'Supplier updated.');
        } else {
            db_query("INSERT INTO suppliers (name,phone,email,address,opening_balance,balance) VALUES (?,?,?,?,?,?)",
                [$name,$phone,$email,$address,$ob,$ob]);
            $sid = fetch_one("SELECT LAST_INSERT_ID() as id")['id'];
            // Create AP account for the supplier
            $ap_group = fetch_one("SELECT id FROM account_groups WHERE name='Accounts Payable' AND isDelete=0 LIMIT 1");
            if ($ap_group) {
                db_query("INSERT INTO accounts (group_id,name,code,is_system,entity_type,entity_id,opening_balance,opening_balance_type) VALUES (?,?,?,1,'Supplier',?,?,?)",
                    [$ap_group['id'], "AP - $name", 'AP-'.str_pad($sid,4,'0',STR_PAD_LEFT), $sid, $ob, 'Cr']);
            }
            log_activity($_SESSION['user_id'], "Created Supplier: $name");
            redirect('modules/suppliers/index.php', 'Supplier added successfully.');
        }
    }

    if (isset($_POST['toggle_supplier'])) {
        $id = intval($_POST['id']);
        $s  = fetch_one("SELECT is_active FROM suppliers WHERE id=?", [$id]);
        db_query("UPDATE suppliers SET is_active=? WHERE id=?", [$s['is_active'] ? 0 : 1, $id]);
        redirect('modules/suppliers/index.php', 'Supplier status toggled.');
    }
}

$search = sanitize($_GET['search'] ?? '');
$sql    = "SELECT * FROM suppliers WHERE isDelete=0";
$params = [];
if ($search) { $sql .= " AND (name LIKE ? OR phone LIKE ?)"; $params = ["%$search%","%$search%"]; }
$sql .= " ORDER BY name ASC";
$suppliers = fetch_all($sql, $params);

$total_payable = fetch_one("SELECT SUM(balance) as t FROM suppliers WHERE isDelete=0")['t'] ?? 0;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3>Suppliers</h3>
        <p class="text-muted small mb-0">Total Payable: <strong class="text-danger"><?php echo format_currency($total_payable); ?></strong></p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="resetForm()">
        <i class="fa-solid fa-plus me-2"></i>Add Supplier
    </button>
</div>

<!-- Search -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name or phone…" value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-primary btn-sm">Search</button>
            <?php if ($search): ?><a href="modules/suppliers/index.php" class="btn btn-outline-secondary btn-sm">Clear</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th class="text-end">Balance</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $i => $s): ?>
                    <tr>
                        <td class="text-muted small"><?php echo $i+1; ?></td>
                        <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($s['phone']); ?></td>
                        <td><?php echo htmlspecialchars($s['email']); ?></td>
                        <td class="small"><?php echo htmlspecialchars($s['address']); ?></td>
                        <td class="text-end fw-bold <?php echo $s['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo format_currency($s['balance']); ?>
                        </td>
                        <td>
                            <?php if ($s['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary edit-sup-btn"
                                data-id="<?php echo $s['id']; ?>"
                                data-name="<?php echo htmlspecialchars($s['name']); ?>"
                                data-phone="<?php echo htmlspecialchars($s['phone']); ?>"
                                data-email="<?php echo htmlspecialchars($s['email']); ?>"
                                data-address="<?php echo htmlspecialchars($s['address']); ?>"
                                data-bs-toggle="modal" data-bs-target="#supplierModal">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" class="d-inline">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                <button type="submit" name="toggle_supplier" class="btn btn-sm <?php echo $s['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                    <i class="fa-solid fa-power-off"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No suppliers found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <input type="hidden" name="id" id="sup_id" value="0">
            <div class="modal-header"><h5 class="modal-title" id="sup_title">Add Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" id="sup_name" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="sup_phone" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="sup_email" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Opening Balance (Payable)</label><input type="number" step="0.01" name="opening_balance" id="sup_ob" class="form-control" value="0"></div>
                    <div class="col-12"><label class="form-label">Address</label><textarea name="address" id="sup_addr" class="form-control" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="save_supplier" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>
<script>
function resetForm() {
    ['sup_id','sup_name','sup_phone','sup_email','sup_addr'].forEach(id => document.getElementById(id).value = id==='sup_id'?'0':'');
    document.getElementById('sup_ob').value = '0';
    document.getElementById('sup_title').textContent = 'Add Supplier';
}
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.edit-sup-btn');
    if (!btn) return;
    document.getElementById('sup_id').value    = btn.dataset.id;
    document.getElementById('sup_name').value  = btn.dataset.name;
    document.getElementById('sup_phone').value = btn.dataset.phone;
    document.getElementById('sup_email').value = btn.dataset.email;
    document.getElementById('sup_addr').value  = btn.dataset.address;
    document.getElementById('sup_title').textContent = 'Edit Supplier';
});
</script>
<?php require_once '../../templates/footer.php'; ?>
