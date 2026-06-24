<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);

$id      = intval($_GET['id'] ?? 0);
$product = fetch_one("SELECT * FROM products WHERE id = ? AND isDelete = 0", [$id]);
if (!$product) redirect('modules/products/index.php', 'Product not found.', 'danger');

$categories = fetch_all("SELECT id, name FROM categories WHERE isDelete = 0 ORDER BY name ASC");

// ── Handle product update ────────────────────────────────────────
if (isset($_POST['update_product'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        redirect("modules/products/edit.php?id=$id", 'CSRF failed.', 'danger');
    }
    $name       = sanitize($_POST['name']);
    $cat_id     = intval($_POST['category_id']);
    $tp         = floatval($_POST['tp_rate']);
    $dp         = floatval($_POST['dp_rate']);
    $retail     = floatval($_POST['retail_rate']);
    $is_active  = isset($_POST['is_active']) ? 1 : 0;
    $market_type = in_array($_POST['market_type'] ?? '', ['Local','Export','Custom']) ? $_POST['market_type'] : 'Local';
    $unit        = sanitize($_POST['unit'] ?? 'pcs');
    $sku         = sanitize($_POST['sku'] ?? '');
    $threshold   = intval($_POST['low_stock_threshold'] ?? 10);

    db_query("UPDATE products SET name=?, category_id=?, tp_rate=?, dp_rate=?, retail_rate=?, is_active=?, market_type=?, unit=?, sku=?, low_stock_threshold=? WHERE id=?",
        [$name, $cat_id, $tp, $dp, $retail, $is_active, $market_type, $unit, $sku, $threshold, $id]);

    log_activity($_SESSION['user_id'], "Updated product #$id: $name");
    redirect("modules/products/edit.php?id=$id", 'Product updated successfully.');
}

// ── Handle visibility rule save ──────────────────────────────────
if (isset($_POST['save_visibility'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        redirect("modules/products/edit.php?id=$id", 'CSRF failed.', 'danger');
    }

    $target_type = $_POST['target_type'] ?? 'user'; // user | role | group | all
    $target_id   = intval($_POST['target_id'] ?? 0);
    $hide_ui     = isset($_POST['hide_from_ui']) ? 1 : 0;
    $hide_rpt    = isset($_POST['hide_from_reports']) ? 1 : 0;

    // Resolve users affected
    $affected_users = [];
    if ($target_type === 'all') {
        $rows = fetch_all("SELECT id FROM users WHERE isDelete=0 AND role != ?", [ROLE_ADMIN]);
        $affected_users = array_column($rows, 'id');
    } elseif ($target_type === 'role') {
        $role_map = [
            'viewer' => ROLE_VIEWER, 'sr' => ROLE_SR,
            'customer' => ROLE_CUSTOMER, 'manager' => ROLE_MANAGER, 'accountant' => ROLE_ACCOUNTANT,
        ];
        $role_val = $role_map[$_POST['role_value'] ?? ''] ?? null;
        if ($role_val) {
            $rows = fetch_all("SELECT id FROM users WHERE isDelete=0 AND role=?", [$role_val]);
            $affected_users = array_column($rows, 'id');
        }
    } elseif ($target_type === 'group') {
        $rows = fetch_all("SELECT id FROM users WHERE isDelete=0 AND group_id=?", [$target_id]);
        $affected_users = array_column($rows, 'id');
    } elseif ($target_type === 'user' && $target_id) {
        $affected_users = [$target_id];
    }

    foreach ($affected_users as $uid) {
        if (!$hide_ui && !$hide_rpt) {
            // Remove rule entirely
            db_query("DELETE FROM product_visibility_rules WHERE product_id=? AND user_id=?", [$id, $uid]);
        } else {
            $ex = fetch_one("SELECT id FROM product_visibility_rules WHERE product_id=? AND user_id=?", [$id, $uid]);
            if ($ex) {
                db_query("UPDATE product_visibility_rules SET hide_from_ui=?, hide_from_reports=?, created_by=? WHERE product_id=? AND user_id=?",
                    [$hide_ui, $hide_rpt, $_SESSION['user_id'], $id, $uid]);
            } else {
                db_query("INSERT INTO product_visibility_rules (product_id,user_id,hide_from_ui,hide_from_reports,created_by) VALUES (?,?,?,?,?)",
                    [$id, $uid, $hide_ui, $hide_rpt, $_SESSION['user_id']]);
            }
        }
    }

    $count = count($affected_users);
    log_activity($_SESSION['user_id'], "Updated visibility for Product #$id — $count user(s) affected");
    redirect("modules/products/edit.php?id=$id", "Visibility updated for $count user(s).");
}

// ── Handle individual user rule toggle ───────────────────────────
if (isset($_POST['toggle_user_rule'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        redirect("modules/products/edit.php?id=$id", 'CSRF failed.', 'danger');
    }
    $uid      = intval($_POST['uid']);
    $hide_ui  = intval($_POST['hide_ui'] ?? 0);
    $hide_rpt = intval($_POST['hide_rpt'] ?? 0);

    if (!$hide_ui && !$hide_rpt) {
        db_query("DELETE FROM product_visibility_rules WHERE product_id=? AND user_id=?", [$id, $uid]);
    } else {
        $ex = fetch_one("SELECT id FROM product_visibility_rules WHERE product_id=? AND user_id=?", [$id, $uid]);
        if ($ex) {
            db_query("UPDATE product_visibility_rules SET hide_from_ui=?, hide_from_reports=?, created_by=? WHERE product_id=? AND user_id=?",
                [$hide_ui, $hide_rpt, $_SESSION['user_id'], $id, $uid]);
        } else {
            db_query("INSERT INTO product_visibility_rules (product_id,user_id,hide_from_ui,hide_from_reports,created_by) VALUES (?,?,?,?,?)",
                [$id, $uid, $hide_ui, $hide_rpt, $_SESSION['user_id']]);
        }
    }
    redirect("modules/products/edit.php?id=$id", 'Visibility rule saved.');
}

// ── Load visibility data ─────────────────────────────────────────
// All non-admin users with their current rule for this product
$all_users = fetch_all(
    "SELECT u.id, u.username, u.role, u.is_active,
            g.name as group_name, d.name as division_name,
            pvr.hide_from_ui, pvr.hide_from_reports
     FROM users u
     LEFT JOIN sr_groups g ON u.group_id = g.id
     LEFT JOIN sr_divisions d ON u.division_id = d.id
     LEFT JOIN product_visibility_rules pvr ON pvr.product_id = ? AND pvr.user_id = u.id AND pvr.isDelete = 0
     WHERE u.isDelete = 0 AND u.role != ?
     ORDER BY u.role, u.username",
    [$id, ROLE_ADMIN]
);

$sr_groups  = fetch_all("SELECT id, name FROM sr_groups WHERE isDelete=0 ORDER BY name");
$hidden_count = count(array_filter($all_users, fn($u) => $u['hide_from_ui'] || $u['hide_from_reports']));

require_once '../../templates/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i></a>
    <div>
        <h3 class="mb-0">Edit Product</h3>
        <p class="text-muted small mb-0">#<?php echo $id; ?> — <?php echo htmlspecialchars($product['name']); ?></p>
    </div>
</div>

<div class="row g-4">

    <!-- ── Left: Product details form ───────────────────────────── -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-box me-2 text-accent"></i>Product Details</div>
            <div class="card-body">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $product['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Market Type</label>
                            <select name="market_type" class="form-select">
                                <?php foreach (['Local','Export','Custom'] as $mt): ?>
                                    <option value="<?php echo $mt; ?>" <?php echo ($product['market_type'] ?? 'Local') === $mt ? 'selected' : ''; ?>><?php echo $mt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-4">
                            <label class="form-label">TP Rate</label>
                            <input type="number" step="0.01" name="tp_rate" class="form-control" value="<?php echo $product['tp_rate']; ?>" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">DP Rate</label>
                            <input type="number" step="0.01" name="dp_rate" class="form-control" value="<?php echo $product['dp_rate']; ?>" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Retail Rate</label>
                            <input type="number" step="0.01" name="retail_rate" class="form-control" value="<?php echo $product['retail_rate']; ?>" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label">Unit</label>
                            <input type="text" name="unit" class="form-control" value="<?php echo htmlspecialchars($product['unit'] ?? 'pcs'); ?>" placeholder="pcs / kg / box">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" class="form-control" value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>" placeholder="Optional">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Low Stock Alert</label>
                            <input type="number" name="low_stock_threshold" class="form-control" value="<?php echo $product['low_stock_threshold'] ?? 10; ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="activeCheck" <?php echo $product['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="activeCheck">Product Active (visible in POS)</label>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" name="update_product" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Right: Visibility control ────────────────────────────── -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-eye-slash me-2 text-accent"></i>Visibility Control</span>
                <?php if ($hidden_count > 0): ?>
                    <span class="badge bg-warning text-dark"><?php echo $hidden_count; ?> restricted</span>
                <?php else: ?>
                    <span class="badge bg-success">Visible to all</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <p class="text-muted small">Control which users see this product in the POS/edit forms and in reports.</p>

                <!-- Bulk apply form -->
                <form method="POST" class="border rounded p-3 mb-3" style="background:#f8fafc;">
                    <?php csrf_field(); ?>
                    <div class="fw-bold small text-uppercase text-muted mb-2">Bulk Apply</div>
                    <div class="row g-2 mb-2">
                        <div class="col-12">
                            <label class="form-label small">Apply To</label>
                            <select name="target_type" id="target_type" class="form-select form-select-sm" onchange="updateTargetUI()">
                                <option value="user">Specific User</option>
                                <option value="role">By Role</option>
                                <option value="group">By SR Group</option>
                                <option value="all">All Non-Admin Users</option>
                            </select>
                        </div>
                        <div class="col-12" id="target_user_row">
                            <label class="form-label small">User</label>
                            <select name="target_id" class="form-select form-select-sm select2">
                                <option value="">— Select User —</option>
                                <?php foreach ($all_users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>">
                                        <?php echo htmlspecialchars($u['username']); ?> (<?php echo $u['role']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-none" id="target_role_row">
                            <label class="form-label small">Role</label>
                            <select name="role_value" class="form-select form-select-sm">
                                <option value="viewer">Viewer</option>
                                <option value="sr">Sales Representative</option>
                                <option value="customer">Customer</option>
                                <option value="manager">Manager</option>
                                <option value="accountant">Accountant</option>
                            </select>
                        </div>
                        <div class="col-12 d-none" id="target_group_row">
                            <label class="form-label small">SR Group</label>
                            <select name="target_id" class="form-select form-select-sm">
                                <option value="">— Select Group —</option>
                                <?php foreach ($sr_groups as $g): ?>
                                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="hide_from_ui" id="bulk_hide_ui" checked>
                            <label class="form-check-label small" for="bulk_hide_ui">Hide from POS/UI</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="hide_from_reports" id="bulk_hide_rpt" checked>
                            <label class="form-check-label small" for="bulk_hide_rpt">Hide from Reports</label>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="save_visibility" class="btn btn-sm btn-warning flex-grow-1">
                            <i class="fa-solid fa-eye-slash me-1"></i>Apply Restriction
                        </button>
                        <button type="submit" name="save_visibility" value="1" onclick="document.getElementById('bulk_hide_ui').checked=false;document.getElementById('bulk_hide_rpt').checked=false;" class="btn btn-sm btn-success flex-grow-1">
                            <i class="fa-solid fa-eye me-1"></i>Allow Access
                        </button>
                    </div>
                </form>

                <!-- Per-user current rules table -->
                <?php
                $restricted = array_filter($all_users, fn($u) => $u['hide_from_ui'] || $u['hide_from_reports']);
                ?>
                <?php if (!empty($restricted)): ?>
                <div class="fw-bold small text-uppercase text-muted mb-2">Current Restrictions</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                        <thead class="table-light"><tr><th>User</th><th>Role</th><th class="text-center">POS</th><th class="text-center">Reports</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($restricted as $u): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                                <td class="small text-muted"><?php echo $u['role']; ?></td>
                                <td class="text-center"><?php echo $u['hide_from_ui'] ? '<span class="badge bg-danger">Hidden</span>' : '<span class="text-success small">✓</span>'; ?></td>
                                <td class="text-center"><?php echo $u['hide_from_reports'] ? '<span class="badge bg-danger">Hidden</span>' : '<span class="text-success small">✓</span>'; ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="uid" value="<?php echo $u['id']; ?>">
                                        <input type="hidden" name="hide_ui" value="0">
                                        <input type="hidden" name="hide_rpt" value="0">
                                        <button type="submit" name="toggle_user_rule" class="btn btn-xs btn-outline-success" style="font-size:11px;padding:1px 6px;" title="Remove restriction (allow)">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="text-center text-muted small py-3">
                        <i class="fa-solid fa-eye fa-lg mb-2 d-block text-success"></i>
                        No restrictions set — product visible to all users.
                    </div>
                <?php endif; ?>

                <!-- All users quick-set -->
                <details class="mt-3">
                    <summary class="small text-muted cursor-pointer" style="cursor:pointer;">All users (<?php echo count($all_users); ?> total) — click to expand</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered mb-0" style="font-size:11px;">
                            <thead class="table-light"><tr><th>User</th><th>Role</th><th>Group</th><th class="text-center">POS</th><th class="text-center">Report</th></tr></thead>
                            <tbody>
                                <?php foreach ($all_users as $u): ?>
                                <tr class="<?php echo ($u['hide_from_ui'] || $u['hide_from_reports']) ? 'table-warning' : ''; ?>">
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td class="small"><?php echo $u['role']; ?></td>
                                    <td class="small"><?php echo htmlspecialchars($u['group_name'] ?? '—'); ?></td>
                                    <td class="text-center"><?php echo $u['hide_from_ui'] ? '🚫' : '✅'; ?></td>
                                    <td class="text-center"><?php echo $u['hide_from_reports'] ? '🚫' : '✅'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    </div>

</div>

<script>
function updateTargetUI() {
    const val = document.getElementById('target_type').value;
    document.getElementById('target_user_row').classList.toggle('d-none', val !== 'user');
    document.getElementById('target_role_row').classList.toggle('d-none', val !== 'role');
    document.getElementById('target_group_row').classList.toggle('d-none', val !== 'group');
}
</script>

<?php require_once '../../templates/footer.php'; ?>
