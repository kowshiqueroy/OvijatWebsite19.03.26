<?php
/**
 * Admin Coupons, Discounts & Marketing Promotions Panel - Premium Redesign
 */
restrict_to(['admin', 'manager']);

$user_role = $_SESSION['user_role'];
$success = '';
$error = '';

// Helper to format target wholesalers
function format_target_wholesalers($target_str, $pdo) {
    if (empty($target_str) || $target_str === 'all') return 'All Wholesalers';
    if (strpos($target_str, 'top_') === 0) {
        return 'Top ' . substr($target_str, 4) . ' Buyers';
    }
    // Comma-separated list of user IDs
    $ids = array_filter(array_map('intval', explode(',', $target_str)));
    if (empty($ids)) return 'None';
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT full_name, username FROM users WHERE id IN ($placeholders) AND is_deleted = 0");
    $stmt->execute($ids);
    $users = $stmt->fetchAll();
    
    $names = [];
    foreach ($users as $u) {
        $names[] = $u['full_name'] ? $u['full_name'] : $u['username'];
    }
    return 'Specific: ' . implode(', ', $names);
}

// Helper to format roles for user display
function format_roles($roles_str) {
    return str_replace('_', ' ', ucwords($roles_str));
}

// 1. Handle Promotion Additions (Promo Campaigns)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_promotion'])) {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $target_type = $_POST['target_type'] ?? 'all';
    $start_date = $_POST['start_date'] ?: null;
    $end_date = $_POST['end_date'] ?: null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $broadcast_notif = isset($_POST['broadcast_notif']) ? 1 : 0;
    $email_blast = isset($_POST['email_blast']) ? 1 : 0;

    if ($target_type === 'specific') {
        $target_wholesalers = implode(',', $_POST['specific_user_ids'] ?? []);
    } else {
        $target_wholesalers = $target_type;
    }

    if ($title && $message) {
        $pdo->beginTransaction();
        try {
            // Insert promotion
            $stmt = $pdo->prepare("INSERT INTO promotions (title, message, target_wholesalers, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $message, $target_wholesalers, $start_date, $end_date, $is_active]);
            $promo_id = $pdo->lastInsertId();

            // 1. Send Notifications
            if ($broadcast_notif) {
                // Fetch target users
                $stmt_users = $pdo->prepare("SELECT id FROM users WHERE role IN ('wholesale_user', 'executive') AND is_deleted = 0");
                $stmt_users->execute();
                $all_uids = $stmt_users->fetchAll(PDO::FETCH_COLUMN);

                $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                foreach ($all_uids as $uid) {
                    if (is_wholesaler_targeted($pdo, $uid, $target_wholesalers)) {
                        $stmt_notif->execute([$uid, "New Promotion: " . $title, substr($message, 0, 150) . "..."]);
                    }
                }
            }

            // 2. Send Email Blast
            if ($email_blast) {
                require_once 'includes/mailer.php';
                $stmt_users = $pdo->prepare("SELECT id, email, full_name FROM users WHERE role IN ('wholesale_user', 'executive') AND is_deleted = 0");
                $stmt_users->execute();
                $all_users = $stmt_users->fetchAll();

                foreach ($all_users as $u) {
                    if (is_wholesaler_targeted($pdo, $u['id'], $target_wholesalers)) {
                        $email_body = "<h3>Wholesale Special Offer: " . e($title) . "</h3>
                        <p>Hello " . e($u['full_name'] ?: 'Valued Partner') . ",</p>
                        <p>" . nl2br(e($message)) . "</p>
                        <p>Log in to your wholesale dashboard to check the offers: <a href='/bolakausa/login'>BolakaUSA Partner Portal</a></p>";
                        send_system_email($pdo, $u['email'], "Wholesale Promotion: " . $title, $email_body);
                    }
                }
            }

            $pdo->commit();
            $success = "Promotion Campaign '$title' created successfully.";
            log_action($pdo, $_SESSION['user_id'], 'Created Promotion', "ID: $promo_id, Title: $title");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to create campaign: " . $e->getMessage();
        }
    } else {
        $error = "Campaign Title and Message are required.";
    }
}

// 2. Handle Coupon Additions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $type = $_POST['type'] ?? 'percentage';
    $value = (float)($_POST['value'] ?? 0);
    $min_spend = (float)($_POST['min_spend'] ?? 0);
    $max_discount = $_POST['max_discount'] !== '' ? (float)$_POST['max_discount'] : null;
    $usage_limit = (int)($_POST['usage_limit'] ?? 1);
    $start_date = $_POST['start_date'] ?: null;
    $end_date = $_POST['end_date'] ?: null;
    $rules = trim($_POST['rules'] ?? '');
    $target_type = $_POST['target_type'] ?? 'all';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($target_type === 'specific') {
        $target_wholesalers = implode(',', $_POST['specific_user_ids'] ?? []);
    } else {
        $target_wholesalers = $target_type;
    }

    if ($code && $value > 0) {
        // Check duplicate coupon code
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM coupons WHERE code = ? AND is_deleted = 0");
        $stmt->execute([$code]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Coupon code '$code' already exists.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO coupons (code, type, value, min_spend, max_discount, usage_limit, start_date, end_date, rules, target_wholesalers, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$code, $type, $value, $min_spend, $max_discount, $usage_limit, $start_date, $end_date, $rules, $target_wholesalers, $is_active])) {
                $success = "Coupon Code '$code' added successfully.";
                log_action($pdo, $_SESSION['user_id'], 'Created Coupon', "Code: $code");
            } else {
                $error = "Failed to save coupon.";
            }
        }
    } else {
        $error = "Coupon Code and Discount Value are required.";
    }
}

// 3. Handle Discount Additions (Automatic Price Rules)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_discount'])) {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['discount_type'] ?? 'global';
    $product_id = $_POST['product_id'] ?: null;
    $percent = (float)($_POST['percent'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $rules = trim($_POST['rules'] ?? '');
    $target_type = $_POST['target_type'] ?? 'all';
    $start_date = $_POST['start_date'] ?: null;
    $end_date = $_POST['end_date'] ?: null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($target_type === 'specific') {
        $target_wholesalers = implode(',', $_POST['specific_user_ids'] ?? []);
    } else {
        $target_wholesalers = $target_type;
    }

    if ($name && ($percent > 0 || $amount > 0)) {
        $stmt = $pdo->prepare("INSERT INTO discounts (name, discount_type, product_id, percent, amount, rules, target_wholesalers, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $type, $product_id, $percent, $amount, $rules, $target_wholesalers, $start_date, $end_date, $is_active])) {
            $success = "Automatic Discount '$name' created successfully.";
            log_action($pdo, $_SESSION['user_id'], 'Created Discount', "Name: $name");
        } else {
            $error = "Failed to save discount.";
        }
    } else {
        $error = "Discount Name and at least one percent or fixed value are required.";
    }
}

// 4. Handle Deletions (Soft Deletes)
if (isset($_GET['delete_promo'])) {
    $id = (int)$_GET['delete_promo'];
    $stmt = $pdo->prepare("UPDATE promotions SET is_deleted = 1 WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Promotion deleted.";
    log_action($pdo, $_SESSION['user_id'], 'Deleted Promotion', "ID: $id");
}

if (isset($_GET['delete_coupon'])) {
    $id = (int)$_GET['delete_coupon'];
    $stmt = $pdo->prepare("UPDATE coupons SET is_deleted = 1 WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Coupon deleted.";
    log_action($pdo, $_SESSION['user_id'], 'Deleted Coupon', "ID: $id");
}

if (isset($_GET['delete_discount'])) {
    $id = (int)$_GET['delete_discount'];
    $stmt = $pdo->prepare("UPDATE discounts SET is_deleted = 1 WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Discount deleted.";
    log_action($pdo, $_SESSION['user_id'], 'Deleted Discount', "ID: $id");
}

// Fetch lists (excluding soft-deleted ones)
$promotions = $pdo->query("SELECT * FROM promotions WHERE is_deleted = 0 ORDER BY created_at DESC")->fetchAll();
$coupons = $pdo->query("SELECT * FROM coupons WHERE is_deleted = 0 ORDER BY created_at DESC")->fetchAll();
$discounts = $pdo->query("SELECT d.*, p.name as prod_name FROM discounts d LEFT JOIN products p ON d.product_id = p.id WHERE d.is_deleted = 0 ORDER BY d.is_active DESC, d.start_date DESC")->fetchAll();
$products = $pdo->query("SELECT id, name FROM products WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC")->fetchAll();
$wholesalers = $pdo->query("SELECT id, username, full_name, role FROM users WHERE role IN ('wholesale_user', 'executive') AND is_deleted = 0 ORDER BY full_name ASC, username ASC")->fetchAll();
?>


<div class="section-title">
    <i class="fas fa-tags" style="color: var(--primary);"></i>
    Coupons, Discounts & Campaigns Control Hub
</div>

<?php if ($success): ?>
    <div style="background: rgba(16, 185, 129, 0.08); color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.15);">
        <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background: rgba(244, 63, 94, 0.08); color: #991b1b; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.15);">
        <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
    </div>
<?php endif; ?>

<!-- Tab Selector -->
<div style="display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid var(--glass-border); padding-bottom: 1rem;">
    <button onclick="switchPromoTab('tab-campaigns')" id="btn-campaigns" class="btn btn-blue" style="background: var(--primary);"><i class="fas fa-bullhorn"></i> Promo Campaigns</button>
    <button onclick="switchPromoTab('tab-coupons')" id="btn-coupons" class="btn btn-blue" style="background: rgba(15,23,42,0.05); color: var(--text-muted); box-shadow: none;"><i class="fas fa-ticket-alt"></i> Coupon Codes</button>
    <button onclick="switchPromoTab('tab-discounts')" id="btn-discounts" class="btn btn-blue" style="background: rgba(15,23,42,0.05); color: var(--text-muted); box-shadow: none;"><i class="fas fa-percentage"></i> Automatic Discounts</button>
</div>

<!-- TAB 1: Promo Campaigns -->
<div id="tab-campaigns" class="promo-pane" style="display: block;">
    <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 2.5rem; align-items: flex-start; flex-wrap: wrap;">
        <!-- Left: Form -->
        <div class="card">
            <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); margin-bottom:1.5rem;"><i class="fas fa-plus-circle" style="color:var(--primary);"></i> New Marketing Offer</h3>
            <form method="POST">
                <input type="hidden" name="add_promotion" value="1">
                <div class="form-group">
                    <label>Promotion Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Summer Grain Blowout 10%" style="border-radius: 8px;">
                </div>
                <div class="form-group">
                    <label>Campaign Message *</label>
                    <textarea name="message" rows="4" required placeholder="Describe the promotion guidelines or coupon incentives..." style="border-radius: 8px;"></textarea>
                </div>
                <div style="display:grid; grid-template-columns:1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group" style="margin:0;">
                        <label>Target Wholesaler Selection</label>
                        <select name="target_type" style="border-radius:8px;" onchange="toggleSpecificWholesalers(this.value, 'campaign_specific_group')">
                            <option value="all">All Wholesalers</option>
                            <option value="top_3">Top 3 Buyers by Spend</option>
                            <option value="top_5">Top 5 Buyers by Spend</option>
                            <option value="top_10">Top 10 Buyers by Spend</option>
                            <option value="specific">Specific Wholesalers</option>
                        </select>
                    </div>
                    <div class="form-group" id="campaign_specific_group" style="margin:0; display:none;">
                        <label>Select Wholesalers (Hold Ctrl to select multiple) *</label>
                        <select name="specific_user_ids[]" multiple style="border-radius:8px; height:120px; padding:5px;">
                            <?php foreach ($wholesalers as $w): ?>
                                <?php $w_name = ($w['full_name'] ? $w['full_name'] : $w['username']) . " (" . format_roles($w['role']) . ")"; ?>
                                <option value="<?php echo $w['id']; ?>"><?php echo e($w_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                    <div class="form-group" style="margin:0;">
                        <label>Validity Start Date</label>
                        <input type="datetime-local" name="start_date" style="border-radius:8px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Validity End Date</label>
                        <input type="datetime-local" name="end_date" style="border-radius:8px;">
                    </div>
                </div>
                <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.5rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" name="broadcast_notif" value="1" checked style="width:auto;">
                        <span>Broadcast to Target User Notifications</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" name="email_blast" value="1" style="width:auto;">
                        <span>Send Email Blast Alert immediately</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" name="is_active" value="1" checked style="width:auto;">
                        <span>Activate Immediately on Catalog</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-green" style="width:100%; border-radius:8px;"><i class="fas fa-paper-plane"></i> Launch Campaign</button>
            </form>
        </div>

        <!-- Right: Active Promotions -->
        <div class="card">
            <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); margin-bottom:1.5rem;"><i class="fas fa-list-ul" style="color:var(--accent);"></i> Running Campaigns</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Details</th>
                            <th>Target Role</th>
                            <th>Validity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$promotions): ?>
                            <tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:2rem;">No campaigns launched.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($promotions as $promo): ?>
                        <tr>
                            <td>
                                <strong style="color:var(--secondary);"><?php echo e($promo['title']); ?></strong><br>
                                <span style="font-size:0.85rem; color:var(--text-muted);"><?php echo e(substr($promo['message'], 0, 75)); ?>...</span>
                            </td>
                            <td><span style="background:#e2e8f0; font-size:0.75rem; padding:4px 8px; border-radius:6px; font-weight:700; display:inline-block; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo e(format_target_wholesalers($promo['target_wholesalers'], $pdo)); ?>"><?php echo format_target_wholesalers($promo['target_wholesalers'], $pdo); ?></span></td>
                            <td>
                                <small style="font-size:0.72rem;">
                                    Start: <?php echo $promo['start_date'] ? date('M d, H:i', strtotime($promo['start_date'])) : 'Instant'; ?><br>
                                    End: <?php echo $promo['end_date'] ? date('M d, H:i', strtotime($promo['end_date'])) : 'Forever'; ?>
                                </small>
                            </td>
                            <td>
                                <span style="font-size:0.75rem; font-weight:800; color:<?php echo $promo['is_active'] ? 'var(--primary)' : 'var(--rose)'; ?>;">
                                    <?php echo $promo['is_active'] ? 'ACTIVE' : 'PAUSED'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="/bolakausa/admin/promotions?delete_promo=<?php echo $promo['id']; ?>" class="btn btn-red" style="padding:0.35rem 0.6rem; font-size:0.75rem; border-radius:6px;" onclick="return confirm('Delete this promotion campaign?')"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: Coupon Codes -->
<div id="tab-coupons" class="promo-pane" style="display: none;">
    <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 2.5rem; align-items: flex-start; flex-wrap: wrap;">
        <!-- Left: Form -->
        <div class="card">
            <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); margin-bottom:1.5rem;"><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Create Coupon Code</h3>
            <form method="POST">
                <input type="hidden" name="add_coupon" value="1">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group" style="margin:0;">
                        <label>Coupon Code *</label>
                        <input type="text" name="code" required placeholder="e.g. WELCOME10" style="border-radius: 8px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Discount Type *</label>
                        <select name="type" style="border-radius:8px;">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount ($)</option>
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group" style="margin:0;">
                        <label>Value *</label>
                        <input type="number" step="0.01" name="value" required placeholder="e.g. 10.00" style="border-radius: 8px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Max Usage Limit *</label>
                        <input type="number" name="usage_limit" value="10" required style="border-radius: 8px;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group" style="margin:0;">
                        <label>Min Order Spend ($)</label>
                        <input type="number" step="0.01" name="min_spend" value="0.00" style="border-radius: 8px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Max Discount Cap ($)</label>
                        <input type="number" step="0.01" name="max_discount" placeholder="Leave blank if none" style="border-radius: 8px;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group" style="margin:0;">
                        <label>Valid From</label>
                        <input type="datetime-local" name="start_date" style="border-radius:8px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Valid To (Expiry)</label>
                        <input type="datetime-local" name="end_date" style="border-radius:8px;">
                    </div>
                </div>
                <div class="form-group">
                    <label>Target Wholesaler Selection</label>
                    <select name="target_type" style="border-radius:8px;" onchange="toggleSpecificWholesalers(this.value, 'coupon_specific_group')">
                        <option value="all">All Wholesalers</option>
                        <option value="top_3">Top 3 Buyers by Spend</option>
                        <option value="top_5">Top 5 Buyers by Spend</option>
                        <option value="top_10">Top 10 Buyers by Spend</option>
                        <option value="specific">Specific Wholesalers</option>
                    </select>
                </div>
                <div class="form-group" id="coupon_specific_group" style="margin-top:1rem; display:none;">
                    <label>Select Wholesalers (Hold Ctrl to select multiple) *</label>
                    <select name="specific_user_ids[]" multiple style="border-radius:8px; height:120px; padding:5px;">
                        <?php foreach ($wholesalers as $w): ?>
                            <?php $w_name = ($w['full_name'] ? $w['full_name'] : $w['username']) . " (" . format_roles($w['role']) . ")"; ?>
                            <option value="<?php echo $w['id']; ?>"><?php echo e($w_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Usage rules & Guidelines (shown to user)</label>
                    <textarea name="rules" rows="2" placeholder="e.g. Apply to order subtotal for organic rice grains only." style="border-radius:8px;"></textarea>
                </div>
                <div class="form-group" style="flex-direction:row; align-items:center; gap:0.5rem; margin-bottom:1.5rem;">
                    <input type="checkbox" name="is_active" id="coupon_active" value="1" checked style="width:auto;">
                    <label for="coupon_active" style="margin:0; cursor:pointer;"><strong>Enable Coupon Code</strong></label>
                </div>
                <button type="submit" class="btn btn-green" style="width:100%; border-radius:8px;"><i class="fas fa-save"></i> Save Coupon Code</button>
            </form>
        </div>

        <!-- Right: Active Coupons -->
        <div class="card">
            <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); margin-bottom:1.5rem;"><i class="fas fa-ticket-alt" style="color:var(--accent);"></i> Available Coupon Codes</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Discount</th>
                            <th>Usage / Limit</th>
                            <th>Target Role</th>
                            <th>Validity / Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$coupons): ?>
                            <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:2rem;">No coupons configured.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($coupons as $cp): ?>
                        <tr>
                            <td><strong style="color:var(--primary); font-family: monospace; font-size:1.1rem;"><?php echo e($cp['code']); ?></strong></td>
                            <td>
                                <?php echo ($cp['type'] === 'percentage') ? $cp['value'] . '%' : '$' . number_format($cp['value'], 2); ?>
                                <?php if ($cp['min_spend'] > 0): ?>
                                    <br><small style="color:var(--text-muted);">Min Spend: $<?php echo $cp['min_spend']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $cp['used_count']; ?> / <?php echo $cp['usage_limit']; ?></td>
                            <td><span style="font-size:0.75rem; background:#f1f5f9; padding:3px 6px; border-radius:4px; font-weight:700; display:inline-block; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo e(format_target_wholesalers($cp['target_wholesalers'], $pdo)); ?>"><?php echo format_target_wholesalers($cp['target_wholesalers'], $pdo); ?></span></td>
                            <td>
                                <small style="font-size:0.72rem; display:block;">
                                    Exp: <?php echo $cp['end_date'] ? date('M d, y', strtotime($cp['end_date'])) : ($cp['expiry_date'] ? date('M d, y', strtotime($cp['expiry_date'])) : 'Never'); ?>
                                </small>
                                <span style="font-size:0.72rem; font-weight:800; color:<?php echo $cp['is_active'] ? 'var(--primary)' : 'var(--rose)'; ?>;">
                                    <?php echo $cp['is_active'] ? 'ACTIVE' : 'DISABLED'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="/bolakausa/admin/promotions?delete_coupon=<?php echo $cp['id']; ?>" class="btn btn-red" style="padding:0.35rem 0.6rem; font-size:0.75rem; border-radius:6px;" onclick="return confirm('Delete this coupon code?')"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- TAB 3: Automatic Discounts -->
<div id="tab-discounts" class="promo-pane" style="display: none;">
    <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 2.5rem; align-items: flex-start; flex-wrap: wrap;">
        <!-- Left: Form -->
        <div class="card">
            <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); margin-bottom:1.5rem;"><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Setup Price Rules</h3>
            <form method="POST">
                <input type="hidden" name="add_discount" value="1">
                <div class="form-group">
                    <label>Discount Rule Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Organic Jasmine Rice Special" style="border-radius: 8px;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group" style="margin:0;">
                        <label>Scope *</label>
                        <select name="discount_type" id="disc-scope-select" required style="border-radius:8px;" onchange="toggleDiscountProductScope()">
                            <option value="global">Global (Subtotal discount)</option>
                            <option value="product_specific">Product Specific (On Catalog Item)</option>
                        </select>
                    </div>
                    <div class="form-group" id="disc-product-field" style="margin:0; display:none;">
                        <label>Target Product *</label>
                        <select name="product_id" style="border-radius:8px;">
                            <option value="">-- Choose Product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo e($p['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group" style="margin:0;">
                        <label>Percent Discount (%)</label>
                        <input type="number" step="0.01" name="percent" value="0.00" style="border-radius: 8px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Fixed Amount Discount ($)</label>
                        <input type="number" step="0.01" name="amount" value="0.00" style="border-radius: 8px;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group" style="margin:0;">
                        <label>Valid From</label>
                        <input type="datetime-local" name="start_date" style="border-radius:8px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Valid To</label>
                        <input type="datetime-local" name="end_date" style="border-radius:8px;">
                    </div>
                </div>
                <div class="form-group">
                    <label>Target Wholesaler Selection</label>
                    <select name="target_type" style="border-radius:8px;" onchange="toggleSpecificWholesalers(this.value, 'discount_specific_group')">
                        <option value="all">All Wholesalers</option>
                        <option value="top_3">Top 3 Buyers by Spend</option>
                        <option value="top_5">Top 5 Buyers by Spend</option>
                        <option value="top_10">Top 10 Buyers by Spend</option>
                        <option value="specific">Specific Wholesalers</option>
                    </select>
                </div>
                <div class="form-group" id="discount_specific_group" style="margin-top:1rem; display:none;">
                    <label>Select Wholesalers (Hold Ctrl to select multiple) *</label>
                    <select name="specific_user_ids[]" multiple style="border-radius:8px; height:120px; padding:5px;">
                        <?php foreach ($wholesalers as $w): ?>
                            <?php $w_name = ($w['full_name'] ? $w['full_name'] : $w['username']) . " (" . format_roles($w['role']) . ")"; ?>
                            <option value="<?php echo $w['id']; ?>"><?php echo e($w_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rules & Conditions text</label>
                    <textarea name="rules" rows="2" placeholder="Describe eligibility criteria..." style="border-radius:8px;"></textarea>
                </div>
                <div class="form-group" style="flex-direction:row; align-items:center; gap:0.5rem; margin-bottom:1.5rem;">
                    <input type="checkbox" name="is_active" id="discount_active" value="1" checked style="width:auto;">
                    <label for="discount_active" style="margin:0; cursor:pointer;"><strong>Enable Automatic Discount</strong></label>
                </div>
                <button type="submit" class="btn btn-green" style="width:100%; border-radius:8px;"><i class="fas fa-save"></i> Save Price Rule</button>
            </form>
        </div>

        <!-- Right: Active Discounts -->
        <div class="card">
            <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); margin-bottom:1.5rem;"><i class="fas fa-percentage" style="color:var(--accent);"></i> Catalog Price Reductions</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Discount Name / Scope</th>
                            <th>Deduction</th>
                            <th>Target Role</th>
                            <th>Validity / Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$discounts): ?>
                            <tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:2rem;">No automatic discounts configured.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($discounts as $dc): ?>
                        <tr>
                            <td>
                                <strong style="color:var(--secondary);"><?php echo e($dc['name']); ?></strong><br>
                                <small style="color:var(--text-muted); font-size:0.75rem;">
                                    <?php echo ($dc['discount_type'] === 'global') ? 'Global Subtotal' : 'Item: ' . e($dc['prod_name']); ?>
                                </small>
                            </td>
                            <td>
                                <?php 
                                    if ($dc['percent'] > 0 && $dc['amount'] > 0) {
                                        echo $dc['percent'] . '% + $' . number_format($dc['amount'], 2);
                                    } elseif ($dc['percent'] > 0) {
                                        echo $dc['percent'] . '%';
                                    } else {
                                        echo '$' . number_format($dc['amount'], 2);
                                    }
                                ?>
                            </td>
                            <td><span style="font-size:0.75rem; background:#f1f5f9; padding:3px 6px; border-radius:4px; font-weight:700; display:inline-block; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo e(format_target_wholesalers($dc['target_wholesalers'], $pdo)); ?>"><?php echo format_target_wholesalers($dc['target_wholesalers'], $pdo); ?></span></td>
                            <td>
                                <small style="font-size:0.72rem; display:block;">
                                    Exp: <?php echo $dc['end_date'] ? date('M d, y', strtotime($dc['end_date'])) : 'Never'; ?>
                                </small>
                                <span style="font-size:0.72rem; font-weight:800; color:<?php echo $dc['is_active'] ? 'var(--primary)' : 'var(--rose)'; ?>;">
                                    <?php echo $dc['is_active'] ? 'ACTIVE' : 'DISABLED'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="/bolakausa/admin/promotions?delete_discount=<?php echo $dc['id']; ?>" class="btn btn-red" style="padding:0.35rem 0.6rem; font-size:0.75rem; border-radius:6px;" onclick="return confirm('Delete this automatic discount rule?')"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function switchPromoTab(tabId) {
    document.querySelectorAll('.promo-pane').forEach(el => el.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';
    
    // Toggle active styles on buttons
    document.getElementById('btn-campaigns').style.background = 'rgba(15,23,42,0.05)';
    document.getElementById('btn-campaigns').style.color = 'var(--text-muted)';
    document.getElementById('btn-campaigns').style.boxShadow = 'none';

    document.getElementById('btn-coupons').style.background = 'rgba(15,23,42,0.05)';
    document.getElementById('btn-coupons').style.color = 'var(--text-muted)';
    document.getElementById('btn-coupons').style.boxShadow = 'none';

    document.getElementById('btn-discounts').style.background = 'rgba(15,23,42,0.05)';
    document.getElementById('btn-discounts').style.color = 'var(--text-muted)';
    document.getElementById('btn-discounts').style.boxShadow = 'none';

    let activeBtnId = 'btn-' + tabId.replace('tab-', '');
    document.getElementById(activeBtnId).style.background = 'var(--primary)';
    document.getElementById(activeBtnId).style.color = 'white';
}

function toggleDiscountProductScope() {
    const select = document.getElementById('disc-scope-select');
    const field = document.getElementById('disc-product-field');
    const prodInput = field.querySelector('select');
    if (select.value === 'product_specific') {
        field.style.display = 'block';
        prodInput.setAttribute('required', 'required');
    } else {
        field.style.display = 'none';
        prodInput.removeAttribute('required');
    }
}

function toggleSpecificWholesalers(typeVal, groupId) {
    const group = document.getElementById(groupId);
    const select = group.querySelector('select');
    if (typeVal === 'specific') {
        group.style.display = 'block';
        select.setAttribute('required', 'required');
    } else {
        group.style.display = 'none';
        select.removeAttribute('required');
        // Deselect values
        select.selectedIndex = -1;
    }
}
</script>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
