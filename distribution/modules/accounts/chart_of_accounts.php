<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

// ── Handle Account Group Save ──────────────────────────────────────────────
if (isset($_POST['save_group'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        redirect('modules/accounts/chart_of_accounts.php', 'CSRF validation failed.', 'danger');
    }
    $name  = sanitize($_POST['group_name']);
    $ptype = sanitize($_POST['parent_type']); // Asset/Liability/Equity/Income/Expense
    $gid   = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;

    if ($gid) {
        db_query("UPDATE account_groups SET name=?, nature=? WHERE id=?", [$name, $ptype, $gid]);
        redirect('modules/accounts/chart_of_accounts.php', 'Account group updated.', 'success');
    } else {
        db_query("INSERT INTO account_groups (name, nature) VALUES (?,?)", [$name, $ptype]);
        redirect('modules/accounts/chart_of_accounts.php', 'Account group added.', 'success');
    }
}

// ── Handle Account Save ────────────────────────────────────────────────────
if (isset($_POST['save_account'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        redirect('modules/accounts/chart_of_accounts.php', 'CSRF validation failed.', 'danger');
    }
    $code     = sanitize($_POST['account_code']);
    $aname    = sanitize($_POST['account_name']);
    $group_id = intval($_POST['group_id']);
    $ob       = floatval($_POST['opening_balance'] ?? 0);
    $ob_type  = sanitize($_POST['opening_balance_type'] ?? 'Dr');
    $aid      = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;

    if ($aid) {
        db_query("UPDATE accounts SET code=?, name=?, group_id=?, opening_balance=?, opening_balance_type=? WHERE id=?",
            [$code, $aname, $group_id, $ob, $ob_type, $aid]);
        redirect('modules/accounts/chart_of_accounts.php', 'Account updated.', 'success');
    } else {
        db_query("INSERT INTO accounts (code, name, group_id, opening_balance, opening_balance_type) VALUES (?,?,?,?,?)",
            [$code, $aname, $group_id, $ob, $ob_type]);
        redirect('modules/accounts/chart_of_accounts.php', 'Account added.', 'success');
    }
}

// ── Data ────────────────────────────────────────────────────────────────────
$groups   = fetch_all("SELECT * FROM account_groups WHERE isDelete = 0 ORDER BY nature, name");
$accounts = fetch_all("SELECT a.*, ag.name AS group_name, ag.nature AS group_type FROM accounts a JOIN account_groups ag ON a.group_id = ag.id WHERE a.isDelete = 0 ORDER BY ag.nature, ag.name, a.name");

// Group accounts by group_id
$acc_by_group = [];
foreach ($accounts as $acc) {
    $acc_by_group[$acc['group_id']][] = $acc;
}

// Group by nature (enum: Assets, Liabilities, Equity, Income, Expense)
$types = ['Assets', 'Liabilities', 'Equity', 'Income', 'Expense'];
$groups_by_type = [];
foreach ($groups as $g) {
    $groups_by_type[$g['nature']][] = $g;
}

$type_colors = [
    'Assets'      => 'primary',
    'Liabilities' => 'danger',
    'Equity'      => 'warning',
    'Income'      => 'success',
    'Expense'     => 'secondary',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-sitemap me-2 text-primary"></i>Chart of Accounts</h4>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGroupModal">
            <i class="fas fa-folder-plus me-1"></i> Add Group
        </button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAccountModal">
            <i class="fas fa-plus me-1"></i> Add Account
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <?php foreach ($types as $type): ?>
    <?php
        $type_accounts = array_filter($accounts, fn($a) => $a['group_type'] === $type);
        $total_bal = 0;
        foreach ($type_accounts as $ta) {
            $bal = get_account_balance($ta['id']);
            $total_bal += $bal['balance'];
        }
    ?>
    <div class="col">
        <div class="card border-<?php echo $type_colors[$type]; ?> shadow-sm h-100">
            <div class="card-body py-3 text-center">
                <div class="small text-muted"><?php echo $type; ?></div>
                <div class="fw-bold fs-6 text-<?php echo $type_colors[$type]; ?>"><?php echo format_currency($total_bal); ?></div>
                <div class="small text-muted"><?php echo count($type_accounts); ?> accounts</div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Accordion Tree View -->
<div class="accordion" id="coaAccordion">
<?php foreach ($types as $typeKey): ?>
<?php if (empty($groups_by_type[$typeKey])) continue; ?>
<div class="card shadow-sm mb-3 border-0">
    <div class="card-header bg-<?php echo $type_colors[$typeKey]; ?> bg-opacity-10 border-start border-4 border-<?php echo $type_colors[$typeKey]; ?> py-3 d-flex justify-content-between align-items-center"
         data-bs-toggle="collapse" data-bs-target="#type_<?php echo $typeKey; ?>" style="cursor:pointer">
        <h6 class="mb-0 fw-bold text-<?php echo $type_colors[$typeKey]; ?>">
            <i class="fas fa-chevron-down me-2"></i><?php echo strtoupper($typeKey); ?>S
        </h6>
        <span class="badge bg-<?php echo $type_colors[$typeKey]; ?>"><?php echo count($groups_by_type[$typeKey]); ?> groups</span>
    </div>
    <div class="collapse show" id="type_<?php echo $typeKey; ?>">
        <div class="card-body p-0">
        <?php foreach ($groups_by_type[$typeKey] as $grp): ?>
        <div class="border-bottom">
            <!-- Group Row -->
            <div class="d-flex justify-content-between align-items-center px-4 py-2 bg-light">
                <div class="fw-semibold text-dark">
                    <i class="fas fa-folder text-warning me-2"></i><?php echo htmlspecialchars($grp['name']); ?>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border"><?php echo count($acc_by_group[$grp['id']] ?? []); ?> accounts</span>
                    <button class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2"
                            onclick="editGroup(<?php echo $grp['id']; ?>, '<?php echo htmlspecialchars(addslashes($grp['name'])); ?>', '<?php echo $grp['nature']; ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            <!-- Accounts Table -->
            <?php if (!empty($acc_by_group[$grp['id']])): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-5">Code</th>
                            <th>Account Name</th>
                            <th>Opening Balance</th>
                            <th class="text-end">Current Balance</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($acc_by_group[$grp['id']] as $acc): ?>
                    <?php $bal = get_account_balance($acc['id']); ?>
                    <tr>
                        <td class="ps-5 text-muted small"><?php echo htmlspecialchars($acc['code'] ?? '—'); ?></td>
                        <td>
                            <i class="fas fa-file-invoice text-primary me-2 small"></i>
                            <?php echo htmlspecialchars($acc['name']); ?>
                        </td>
                        <td class="small text-muted">
                            <?php echo format_currency($acc['opening_balance'] ?? 0); ?>
                            <span class="badge bg-light text-dark ms-1"><?php echo $acc['opening_balance_type'] ?? 'Dr'; ?></span>
                        </td>
                        <td class="text-end fw-semibold <?php echo $bal['balance'] > 0 ? 'text-success' : 'text-muted'; ?>">
                            <?php echo format_currency($bal['balance']); ?>
                            <small class="text-muted"><?php echo $bal['type']; ?></small>
                        </td>
                        <td class="text-center">
                            <a href="ledger_account.php?account_id=<?php echo $acc['id']; ?>" class="btn btn-xs btn-outline-info btn-sm py-0 px-2" title="View Ledger">
                                <i class="fas fa-book"></i>
                            </a>
                            <button class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2" title="Edit"
                                    onclick="editAccount(<?php echo $acc['id']; ?>, '<?php echo htmlspecialchars(addslashes($acc['code'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($acc['name'])); ?>', <?php echo $acc['group_id']; ?>, <?php echo floatval($acc['opening_balance'] ?? 0); ?>, '<?php echo $acc['opening_balance_type'] ?? 'Dr'; ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-muted small px-5 py-2 fst-italic">No accounts in this group.</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Add/Edit Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <input type="hidden" name="group_id" id="grp_edit_id" value="">
            <div class="modal-header bg-light">
                <h5 class="modal-title"><i class="fas fa-folder-plus me-2"></i><span id="grpModalTitle">Add Account Group</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Group Name <span class="text-danger">*</span></label>
                    <input type="text" name="group_name" id="grp_name" class="form-control" required placeholder="e.g. Cash & Bank">
                </div>
                <div class="mb-3">
                    <label class="form-label">Category Type <span class="text-danger">*</span></label>
                    <select name="parent_type" id="grp_type" class="form-select" required>
                        <?php foreach ($types as $t): ?>
                        <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_group" class="btn btn-primary">Save Group</button>
            </div>
        </form>
    </div>
</div>

<!-- Add/Edit Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <input type="hidden" name="account_id" id="acc_edit_id" value="">
            <div class="modal-header bg-light">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i><span id="accModalTitle">Add Account</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label">Account Code</label>
                        <input type="text" name="account_code" id="acc_code" class="form-control" placeholder="e.g. 1001">
                    </div>
                    <div class="col-8">
                        <label class="form-label">Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" id="acc_name" class="form-control" required placeholder="e.g. Cash in Hand">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Account Group <span class="text-danger">*</span></label>
                    <select name="group_id" id="acc_group" class="form-select select2" required>
                        <option value="">-- Select Group --</option>
                        <?php foreach ($types as $t): ?>
                        <?php if (!empty($groups_by_type[$t])): ?>
                        <optgroup label="── <?php echo $t; ?>s ──">
                            <?php foreach ($groups_by_type[$t] as $g): ?>
                            <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-8">
                        <label class="form-label">Opening Balance</label>
                        <input type="number" step="0.01" name="opening_balance" id="acc_ob" class="form-control" value="0.00">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Type</label>
                        <select name="opening_balance_type" id="acc_ob_type" class="form-select">
                            <option value="Dr">Dr (Debit)</option>
                            <option value="Cr">Cr (Credit)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_account" class="btn btn-primary">Save Account</button>
            </div>
        </form>
    </div>
</div>

<script>
function editGroup(id, name, type) {
    document.getElementById('grp_edit_id').value = id;
    document.getElementById('grp_name').value = name;
    document.getElementById('grp_type').value = type;
    document.getElementById('grpModalTitle').textContent = 'Edit Account Group';
    new bootstrap.Modal(document.getElementById('addGroupModal')).show();
}

function editAccount(id, code, name, groupId, ob, obType) {
    document.getElementById('acc_edit_id').value = id;
    document.getElementById('acc_code').value = code;
    document.getElementById('acc_name').value = name;
    document.getElementById('acc_group').value = groupId;
    document.getElementById('acc_ob').value = ob;
    document.getElementById('acc_ob_type').value = obType;
    document.getElementById('accModalTitle').textContent = 'Edit Account';
    new bootstrap.Modal(document.getElementById('addAccountModal')).show();
}

// Reinit select2 when modal opens
document.getElementById('addAccountModal').addEventListener('show.bs.modal', function() {
    if (typeof $ !== 'undefined') {
        $(this).find('.select2').select2({ dropdownParent: $(this) });
    }
});
document.getElementById('addGroupModal').addEventListener('show.bs.modal', function() {
    document.getElementById('grp_edit_id').value = '';
    document.getElementById('grp_name').value = '';
    document.getElementById('grpModalTitle').textContent = 'Add Account Group';
});
document.getElementById('addAccountModal').addEventListener('show.bs.modal', function(e) {
    if (!e.relatedTarget) return; // opened by editAccount()
    document.getElementById('acc_edit_id').value = '';
    document.getElementById('acc_code').value = '';
    document.getElementById('acc_name').value = '';
    document.getElementById('acc_group').value = '';
    document.getElementById('acc_ob').value = '0.00';
    document.getElementById('acc_ob_type').value = 'Dr';
    document.getElementById('accModalTitle').textContent = 'Add Account';
});
</script>

<?php require_once '../../templates/footer.php'; ?>
