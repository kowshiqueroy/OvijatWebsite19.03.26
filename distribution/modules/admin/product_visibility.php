<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER]);

// Handle toggle action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        redirect('modules/admin/product_visibility.php', 'CSRF validation failed.', 'danger');
    }

    $product_id = intval($_POST['product_id']);
    $user_id    = intval($_POST['user_id']);
    $hide_ui    = isset($_POST['hide_from_ui']) ? 1 : 0;
    $hide_rpt   = isset($_POST['hide_from_reports']) ? 1 : 0;

    // Upsert
    $existing = fetch_one("SELECT id FROM product_visibility_rules WHERE product_id = ? AND user_id = ?", [$product_id, $user_id]);
    if ($existing) {
        db_query("UPDATE product_visibility_rules SET hide_from_ui = ?, hide_from_reports = ?, created_by = ? WHERE product_id = ? AND user_id = ?",
            [$hide_ui, $hide_rpt, $_SESSION['user_id'], $product_id, $user_id]);
    } else {
        db_query("INSERT INTO product_visibility_rules (product_id, user_id, hide_from_ui, hide_from_reports, created_by) VALUES (?,?,?,?,?)",
            [$product_id, $user_id, $hide_ui, $hide_rpt, $_SESSION['user_id']]);
    }

    // If both flags are off, remove the rule entirely to keep table clean
    if (!$hide_ui && !$hide_rpt) {
        db_query("DELETE FROM product_visibility_rules WHERE product_id = ? AND user_id = ?", [$product_id, $user_id]);
    }

    log_activity($_SESSION['user_id'], "Updated product visibility: Product #$product_id for User #$user_id (UI:$hide_ui, Reports:$hide_rpt)");
    redirect('modules/admin/product_visibility.php', 'Visibility rule saved.', 'success');
}

$products = fetch_all("SELECT p.*, cat.name as category_name FROM products p LEFT JOIN categories cat ON p.category_id = cat.id WHERE p.isDelete = 0 ORDER BY cat.name, p.name");
$viewers  = fetch_all("SELECT id, username, role FROM users WHERE isDelete = 0 AND role IN (?,?,?,?,?) ORDER BY role, username",
    [ROLE_VIEWER, ROLE_SR, ROLE_CUSTOMER, ROLE_MANAGER, ROLE_ACCOUNTANT]);

// Load all existing rules
$rules_raw = fetch_all("SELECT * FROM product_visibility_rules");
$rules = [];
foreach ($rules_raw as $r) {
    $rules[$r['product_id']][$r['user_id']] = $r;
}

// Filter
$filter_user = intval($_GET['user_id'] ?? 0);
$filter_prod = intval($_GET['product_id'] ?? 0);
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h3>Product Visibility Rules</h3>
        <p class="text-muted small mb-0">Control which products specific users can see in the POS/edit forms and in reports.</p>
    </div>
</div>

<!-- Filter bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small">Filter by User</label>
                <select name="user_id" class="form-select form-select-sm select2">
                    <option value="">All Users</option>
                    <?php foreach ($viewers as $v): ?>
                        <option value="<?php echo $v['id']; ?>" <?php echo $filter_user == $v['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($v['username']); ?> (<?php echo $v['role']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Filter by Product</label>
                <select name="product_id" class="form-select form-select-sm select2">
                    <option value="">All Products</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $filter_prod == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="product_visibility.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>User</th>
                        <th class="text-center">Hidden from UI</th>
                        <th class="text-center">Hidden from Reports</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $rows_shown = 0;
                foreach ($products as $p):
                    if ($filter_prod && $p['id'] != $filter_prod) continue;
                    foreach ($viewers as $v):
                        if ($filter_user && $v['id'] != $filter_user) continue;

                        $rule = $rules[$p['id']][$v['id']] ?? null;
                        $hide_ui  = $rule ? $rule['hide_from_ui'] : 0;
                        $hide_rpt = $rule ? $rule['hide_from_reports'] : 0;
                        $has_rule = $hide_ui || $hide_rpt;

                        // If not filtered and no rule exists, only show if either filter is active
                        if (!$filter_user && !$filter_prod && !$has_rule) continue;
                        $rows_shown++;
                ?>
                    <tr class="<?php echo $has_rule ? 'table-warning' : ''; ?>">
                        <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($p['category_name'] ?? '—'); ?></span></td>
                        <td>
                            <?php echo htmlspecialchars($v['username']); ?>
                            <span class="badge bg-info ms-1 small"><?php echo $v['role']; ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($hide_ui): ?>
                                <span class="badge bg-danger">Hidden</span>
                            <?php else: ?>
                                <span class="text-muted small">Visible</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($hide_rpt): ?>
                                <span class="badge bg-danger">Hidden</span>
                            <?php else: ?>
                                <span class="text-muted small">Visible</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary edit-rule-btn"
                                data-product-id="<?php echo $p['id']; ?>"
                                data-product-name="<?php echo htmlspecialchars($p['name']); ?>"
                                data-user-id="<?php echo $v['id']; ?>"
                                data-username="<?php echo htmlspecialchars($v['username']); ?>"
                                data-hide-ui="<?php echo $hide_ui; ?>"
                                data-hide-rpt="<?php echo $hide_rpt; ?>"
                                data-bs-toggle="modal" data-bs-target="#editRuleModal">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endforeach; ?>
                <?php if ($rows_shown === 0): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">
                        <?php if (!$filter_user && !$filter_prod): ?>
                            No hidden rules exist yet. Use filters to manage visibility per product or user.
                        <?php else: ?>
                            No rules found for the selected filter.
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Quick-add: when user picks a product+user combo not yet in list -->
<?php if ($filter_user || $filter_prod): ?>
<div class="mt-3">
    <button class="btn btn-outline-warning btn-sm"
        data-bs-toggle="modal" data-bs-target="#editRuleModal"
        data-product-id="<?php echo $filter_prod; ?>"
        data-product-name="<?php echo $filter_prod ? htmlspecialchars($products[array_search($filter_prod, array_column($products, 'id'))]['name'] ?? '') : ''; ?>"
        data-user-id="<?php echo $filter_user; ?>"
        data-username="<?php echo $filter_user ? htmlspecialchars($viewers[array_search($filter_user, array_column($viewers, 'id'))]['username'] ?? '') : ''; ?>"
        data-hide-ui="0" data-hide-rpt="0"
        onclick="if(!this.dataset.productId||!this.dataset.userId)this.closest('.modal')?.remove()">
        <i class="fas fa-plus me-1"></i> Add / Edit Rule for Current Filter
    </button>
</div>
<?php endif; ?>

<!-- Edit Rule Modal -->
<div class="modal fade" id="editRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <input type="hidden" name="product_id" id="rule_product_id">
            <input type="hidden" name="user_id"    id="rule_user_id">
            <div class="modal-header">
                <h5 class="modal-title">Edit Visibility Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Product: <strong id="rule_product_name"></strong><br>
                    User: <strong id="rule_username"></strong>
                </p>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="hide_from_ui" id="rule_hide_ui">
                    <label class="form-check-label" for="rule_hide_ui">
                        <strong>Hide from UI</strong> — product won't appear in POS / Edit product search
                    </label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="hide_from_reports" id="rule_hide_rpt">
                    <label class="form-check-label" for="rule_hide_rpt">
                        <strong>Hide from Reports</strong> — product excluded from stock/sales/comprehensive reports
                    </label>
                </div>
                <p class="text-muted small mt-3"><i class="fas fa-info-circle"></i> Unchecking both options removes the rule entirely (product becomes fully visible).</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Rule</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.edit-rule-btn');
    if (!btn) return;
    document.getElementById('rule_product_id').value   = btn.dataset.productId;
    document.getElementById('rule_product_name').textContent = btn.dataset.productName;
    document.getElementById('rule_user_id').value      = btn.dataset.userId;
    document.getElementById('rule_username').textContent    = btn.dataset.username;
    document.getElementById('rule_hide_ui').checked   = btn.dataset.hideUi == '1';
    document.getElementById('rule_hide_rpt').checked  = btn.dataset.hideRpt == '1';
});
</script>

<?php require_once '../../templates/footer.php'; ?>
