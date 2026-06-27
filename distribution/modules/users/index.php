<?php
require_once '../../templates/header.php';
check_login();
check_role(ROLE_ADMIN);

if (isset($_POST['add_user'])) {
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        redirect('modules/users/index.php', 'CSRF Token Validation Failed.', 'danger');
    }
    $username = sanitize($_POST['username']);
    $phone    = sanitize($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = sanitize($_POST['role']);

    db_query("INSERT INTO users (username, password, phone, role) VALUES (?, ?, ?, ?)", [$username, $password, $phone, $role]);
    log_activity($_SESSION['user_id'], "Created new user: $username");
    redirect('modules/users/index.php', 'User created successfully.');
}

// Sort: most recently active first, never-active at the bottom
$users = fetch_all("SELECT * FROM users WHERE isDelete = 0 ORDER BY ISNULL(last_active) ASC, last_active DESC, username ASC");

$role_order = [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_SR, ROLE_VIEWER, ROLE_CUSTOMER];
$role_meta  = [
    ROLE_ADMIN      => ['label' => 'Admin',       'badge' => 'bg-dark'],
    ROLE_MANAGER    => ['label' => 'Manager',      'badge' => 'bg-primary'],
    ROLE_ACCOUNTANT => ['label' => 'Accountant',   'badge' => 'bg-success'],
    ROLE_SR         => ['label' => 'Sales Rep',    'badge' => 'bg-info text-dark'],
    ROLE_VIEWER     => ['label' => 'Viewer',       'badge' => 'bg-warning text-dark'],
    ROLE_CUSTOMER   => ['label' => 'Customer',     'badge' => 'bg-secondary'],
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>User Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-user-plus me-2"></i>Add New User
    </button>
</div>

<!-- Search -->
<div class="mb-3">
    <div class="input-group" style="max-width:320px;">
        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
        <input type="text" id="user-search" class="form-control border-start-0" placeholder="Search username or phone…">
    </div>
</div>

<!-- Role tabs -->
<ul class="nav nav-tabs" id="roleTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-role="all">
            All <span class="badge bg-secondary ms-1"><?php echo count($users); ?></span>
        </button>
    </li>
    <?php foreach ($role_order as $r):
        $cnt = count(array_filter($users, fn($u) => $u['role'] === $r));
        if (!$cnt) continue;
        $meta = $role_meta[$r];
    ?>
    <li class="nav-item">
        <button class="nav-link" data-role="<?php echo htmlspecialchars($r); ?>">
            <?php echo $meta['label']; ?>
            <span class="badge <?php echo $meta['badge']; ?> ms-1"><?php echo $cnt; ?></span>
        </button>
    </li>
    <?php endforeach; ?>
</ul>

<div class="card" style="border-top-left-radius:0;border-top-right-radius:0;border-top:none;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="users-table">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Last Active</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $meta = $role_meta[$u['role']] ?? ['label' => $u['role'], 'badge' => 'bg-secondary'];
                ?>
                <tr data-role="<?php echo htmlspecialchars($u['role']); ?>"
                    data-search="<?php echo strtolower(htmlspecialchars($u['username'] . ' ' . $u['phone'])); ?>">
                    <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($u['phone']); ?></td>
                    <td><span class="badge <?php echo $meta['badge']; ?>"><?php echo $meta['label']; ?></span></td>
                    <td>
                        <?php if ($u['last_active']): ?>
                            <span title="<?php echo date('Y-m-d H:i:s', strtotime($u['last_active'])); ?>">
                                <?php echo date('d M, h:i A', strtotime($u['last_active'])); ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted small">Never</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?php echo $u['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo $u['is_active'] ? 'Active' : 'Blocked'; ?>
                        </span>
                    </td>
                    <td class="text-nowrap">
                        <a href="toggle_status.php?id=<?php echo $u['id']; ?>"
                           class="btn btn-sm <?php echo $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
                           title="<?php echo $u['is_active'] ? 'Block User' : 'Activate User'; ?>">
                            <i class="fas fa-power-off"></i>
                        </a>
                        <a href="logs.php?id=<?php echo $u['id']; ?>"
                           class="btn btn-sm btn-outline-secondary" title="Activity Logs">
                            <i class="fas fa-list"></i>
                        </a>
                        <?php if ($u['role'] === ROLE_VIEWER): ?>
                        <button class="btn btn-sm btn-outline-warning configure-view-btn"
                                data-user-id="<?php echo $u['id']; ?>"
                                data-username="<?php echo htmlspecialchars($u['username']); ?>"
                                title="Configure View Permissions"
                                data-bs-toggle="modal" data-bs-target="#viewPermModal">
                            <i class="fas fa-sliders me-1"></i>Configure View
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="no-results" class="text-center text-muted py-5 d-none">
            <i class="fas fa-user-slash fa-2x mb-2 d-block opacity-25"></i>
            No users found.
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Create System User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="<?php echo ROLE_MANAGER; ?>"><?php echo ROLE_MANAGER; ?></option>
                        <option value="<?php echo ROLE_ACCOUNTANT; ?>"><?php echo ROLE_ACCOUNTANT; ?></option>
                        <option value="<?php echo ROLE_SR; ?>"><?php echo ROLE_SR; ?></option>
                        <option value="<?php echo ROLE_VIEWER; ?>"><?php echo ROLE_VIEWER; ?></option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_user" class="btn btn-primary">Save User</button>
            </div>
        </form>
    </div>
</div>

<!-- Configure View Permissions Modal -->
<div class="modal fade" id="viewPermModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-sliders me-2"></i>Configure View — <span id="vpm-username"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="vpm-body">
                <div class="text-center py-4">
                    <div class="spinner-border text-warning"></div>
                    <p class="mt-2 text-muted small">Loading permissions…</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info text-white me-auto" id="vpm-dmd-preset"
                        title="Grant full DMD access — sees all order types and all sections">
                    <i class="fas fa-satellite-dish me-1"></i>Set DMD Preset
                </button>
                <button type="button" class="btn btn-warning" id="vpm-save-btn">
                    <i class="fas fa-save me-1"></i>Save Permissions
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    /* ── Role tabs + search ───────────────────────────────────── */
    let activeRole = 'all';
    let searchTerm = '';

    function applyFilter() {
        let visible = 0;
        document.querySelectorAll('#users-table tbody tr').forEach(function (row) {
            const matchRole   = activeRole === 'all' || row.dataset.role === activeRole;
            const matchSearch = !searchTerm || row.dataset.search.includes(searchTerm);
            const show = matchRole && matchSearch;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('no-results').classList.toggle('d-none', visible > 0);
    }

    document.querySelectorAll('#roleTabs .nav-link').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#roleTabs .nav-link').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeRole = this.dataset.role;
            applyFilter();
        });
    });

    document.getElementById('user-search').addEventListener('input', function () {
        searchTerm = this.value.toLowerCase().trim();
        applyFilter();
    });

    /* ── Configure View Permissions ───────────────────────────── */
    const CSRF = <?php echo json_encode(get_csrf_token()); ?>;
    let currentUserId = null;

    const PERMS = [
        { key: 'show_local',                   label: 'Show Local Market Products' },
        { key: 'show_export',                  label: 'Show Export Market Products' },
        { key: 'show_custom',                  label: 'Show Custom Market Products' },
        { key: 'show_sales_kpis',              label: 'Show Sales KPIs on Dashboard' },
        { key: 'show_inventory_section',       label: 'Show Inventory Section' },
        { key: 'show_delivery_section',        label: 'Show Delivery Section' },
        { key: 'show_accounts_section',        label: 'Show Accounts Section' },
        { key: 'can_see_stock_report',         label: 'Can See Stock Report' },
        { key: 'can_see_inventory_report',     label: 'Can See Inventory Report' },
        { key: 'can_see_comprehensive_report', label: 'Can See Comprehensive Report' },
        { key: 'can_see_transactions',         label: 'Can See Transactions' },
        { key: 'can_see_dmd_dashboard',        label: 'Can See DMD Dashboard' },
        { key: 'show_rates',                   label: 'Show TP/DP/Retail Rates' },
        { key: 'show_customer_balances',       label: 'Show Customer Balances' },
    ];

    function buildForm(data) {
        let html = '<div class="row g-3">';
        PERMS.forEach(function (p) {
            const checked = data[p.key] == 1 ? 'checked' : '';
            html += `
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="vpm_${p.key}" name="${p.key}" ${checked}>
                        <label class="form-check-label" for="vpm_${p.key}">${p.label}</label>
                    </div>
                </div>`;
        });
        html += '</div>';
        return html;
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.configure-view-btn');
        if (!btn) return;
        currentUserId = btn.dataset.userId;
        document.getElementById('vpm-username').textContent = btn.dataset.username;
        document.getElementById('vpm-body').innerHTML =
            '<div class="text-center py-4"><div class="spinner-border text-warning"></div></div>';

        fetch(`save_view_permissions.php?action=get&user_id=${currentUserId}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    document.getElementById('vpm-body').innerHTML = buildForm(res.data);
                } else {
                    document.getElementById('vpm-body').innerHTML =
                        `<div class="alert alert-danger">${res.message}</div>`;
                }
            })
            .catch(() => {
                document.getElementById('vpm-body').innerHTML =
                    '<div class="alert alert-danger">Failed to load permissions. Check server logs.</div>';
            });
    });

    document.getElementById('vpm-dmd-preset').addEventListener('click', function () {
        PERMS.forEach(function (p) {
            const el = document.getElementById('vpm-body').querySelector(`#vpm_${p.key}`);
            if (el) el.checked = true;
        });
    });

    document.getElementById('vpm-save-btn').addEventListener('click', function () {
        if (!currentUserId) return;

        const body = document.getElementById('vpm-body');
        const fd   = new FormData();
        fd.append('action',     'save');
        fd.append('user_id',    currentUserId);
        fd.append('csrf_token', CSRF);
        PERMS.forEach(function (p) {
            const el = body.querySelector(`#vpm_${p.key}`);
            if (el && el.checked) fd.append(p.key, '1');
        });

        this.disabled    = true;
        this.textContent = 'Saving…';

        fetch('save_view_permissions.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                this.disabled    = false;
                this.innerHTML   = '<i class="fas fa-save me-1"></i>Save Permissions';
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('viewPermModal')).hide();
                    const toast = document.createElement('div');
                    toast.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3';
                    toast.style.zIndex = 9999;
                    toast.innerHTML = `<i class="fas fa-check-circle me-2"></i>${res.message} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 4000);
                } else {
                    alert('Error: ' + res.message);
                }
            })
            .catch(() => {
                this.disabled  = false;
                this.innerHTML = '<i class="fas fa-save me-1"></i>Save Permissions';
                alert('Network error, please try again.');
            });
    });
})();
</script>

<?php require_once '../../templates/footer.php'; ?>
