<?php
/**
 * Admin User Management & Role Delegation Module
 */
restrict_to(['admin', 'manager', 'editor', 'viewer']);

$user_role = $_SESSION['user_role'];
$error = '';
$success = '';

$role_ranks = [
    'admin' => 7,
    'manager' => 6,
    'editor' => 5,
    'viewer' => 4,
    'warehouse' => 3,
    'executive' => 2,
    'wholesale_user' => 1
];

// Handle Status Changes via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
        die("Invalid CSRF token.");
    }
    $target_id = (int)$_POST['id'];
    $action = $_POST['action'];
    $new_status = ($action === 'approve') ? 'active' : (($action === 'suspend') ? 'suspended' : null);

    if ($new_status) {
        $stmt = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
        $stmt->execute([$target_id]);
        $target = $stmt->fetch();

        if ($target) {
            $target_role = $target['role'];
            $can_modify = false;
            
            if ($user_role === 'admin' && $target_id !== $_SESSION['user_id']) {
                $can_modify = true;
            } elseif (in_array($user_role, ['manager', 'editor']) && $role_ranks[$user_role] > $role_ranks[$target_role]) {
                $can_modify = true;
            }

            if ($can_modify) {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                if ($stmt->execute([$new_status, $target_id])) {
                    $success = "User status of @{$target['username']} updated to $new_status.";
                    log_action($pdo, $_SESSION['user_id'], "User Status Changed to $new_status", "User ID: $target_id, Role: $target_role");
                    
                    $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                    $stmt_notif->execute([$target_id, "Account Status Updated", "Your account status has been updated to: $new_status."]);
                }
            } else {
                $error = "Access Denied: You do not have permission to modify role '$target_role'.";
            }
        }
    }
}

// Handle User Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $new_username = trim($_POST['username'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $new_role = $_POST['role'] ?? '';
    $new_status = $_POST['status'] ?? 'active';
    $new_name = trim($_POST['full_name'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');

    if ($new_username && $new_email && $new_password && $new_role) {
        $allowed_to_create = false;
        if ($role_ranks[$user_role] >= $role_ranks[$new_role]) {
            $allowed_to_create = true;
        }

        if ($allowed_to_create) {
            // Check duplicates
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$new_username, $new_email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Username or Email address already exists.";
            } else {
                $hashed_pass = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status, full_name, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$new_username, $new_email, $hashed_pass, $new_role, $new_status, $new_name, $new_phone])) {
                    $new_uid = $pdo->lastInsertId();
                    $success = "User account @$new_username ($new_role) successfully created.";
                    log_action($pdo, $_SESSION['user_id'], "User Created", "Username: $new_username, Role: $new_role");
                    
                    // Add notification
                    $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                    $stmt_notif->execute([$new_uid, "Welcome to BolakaUSA", "Your new partner account has been created successfully."]);
                } else {
                    $error = "Failed to write user account to database.";
                }
            }
        } else {
            $error = "Access Denied: You do not have permission to delegate role '$new_role'.";
        }
    } else {
        $error = "All fields marked with * are required.";
    }
}

// Date Range Filter
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$show_all = isset($_GET['show_all']);

// Query parameters
$query_all = "SELECT * FROM users";
$query_pending = "SELECT * FROM users WHERE status = 'pending'";
$params = [];

if (!$show_all) {
    $query_all .= " WHERE DATE(created_at) BETWEEN ? AND ?";
    $query_pending .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

$query_all .= " ORDER BY role ASC, username ASC";
$query_pending .= " ORDER BY created_at DESC";

$stmt_all = $pdo->prepare($query_all);
$stmt_all->execute($params);
$all_users = $stmt_all->fetchAll();

$stmt_pending = $pdo->prepare($query_pending);
$stmt_pending->execute($params);
$pending_users = $stmt_pending->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-users-cog" style="color: var(--primary);"></i>
    User Verification & Delegation
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

<!-- Tab selector -->
<div style="display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid var(--glass-border); padding-bottom: 1rem;">
    <button onclick="switchUserTab('tab-users-list')" id="btn-users-list" class="btn btn-blue" style="background: var(--primary);"><i class="fas fa-users"></i> Accounts Directory</button>
    <button onclick="switchUserTab('tab-pending')" id="btn-pending" class="btn btn-blue" style="background: rgba(15,23,42,0.05); color: var(--text-muted); box-shadow: none;"><i class="fas fa-user-clock"></i> Pending Approvals (<?php echo count($pending_users); ?>)</button>
    <?php if ($user_role !== 'viewer'): ?>
        <button onclick="switchUserTab('tab-create')" id="btn-create" class="btn btn-blue" style="background: rgba(15,23,42,0.05); color: var(--text-muted); box-shadow: none;"><i class="fas fa-user-plus"></i> Add Account</button>
    <?php endif; ?>
</div>

<!-- Date filter -->
<div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <form method="GET" style="display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="url" value="admin/users">
        <div class="form-group" style="margin: 0; flex: 1; min-width: 180px;">
            <label style="font-weight: 800; font-size: 0.8rem; margin-bottom: 0.35rem; color: var(--secondary);">From Date</label>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>" style="padding: 0.5rem; border-radius: 8px; font-size: 0.85rem; border: 1px solid #cbd5e1;">
        </div>
        <div class="form-group" style="margin: 0; flex: 1; min-width: 180px;">
            <label style="font-weight: 800; font-size: 0.8rem; margin-bottom: 0.35rem; color: var(--secondary);">To Date</label>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>" style="padding: 0.5rem; border-radius: 8px; font-size: 0.85rem; border: 1px solid #cbd5e1;">
        </div>
        <div style="display: flex; gap: 0.5rem; margin-top: auto;">
            <button type="submit" class="btn btn-blue" style="padding: 0.6rem 1.25rem;"><i class="fas fa-filter"></i> Filter</button>
            <a href="/bolakausa/admin/users?show_all=1" class="btn btn-outline" style="padding: 0.6rem 1.25rem; font-weight: 700; border-radius: 8px; text-decoration: none;">Show All</a>
        </div>
    </form>
</div>

<!-- Tab: Accounts List -->
<div id="tab-users-list" class="tab-pane" style="display: block;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User / Contact</th>
                    <th>Email</th>
                    <th>Role Badge</th>
                    <th>Status State</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$all_users): ?>
                    <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 3rem;">No accounts logged in this date range. <a href="/bolakausa/admin/users?show_all=1" style="color:var(--primary); font-weight:700;">Show All History</a></td></tr>
                <?php endif; ?>
                <?php foreach ($all_users as $u): ?>
                <tr>
                    <td style="font-weight: 800; color: var(--text-muted);">#<?php echo $u['id']; ?></td>
                    <td>
                        <strong style="color: var(--secondary); font-size: 0.95rem;"><?php echo e($u['full_name'] ?: 'No Name Listed'); ?></strong><br>
                        <small style="color: var(--text-muted);">@<?php echo e($u['username']); ?> &bull; <?php echo e($u['phone'] ?: 'No Phone'); ?></small>
                    </td>
                    <td><?php echo e($u['email']); ?></td>
                    <td>
                        <span style="background: #e2e8f0; color: #334155; font-size: 0.75rem; padding: 4px 10px; border-radius: 8px; font-weight: 800; text-transform: uppercase;">
                            <?php echo e($u['role']); ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                            $status_bg = 'rgba(15,23,42,0.1)'; $status_color = 'var(--secondary)';
                            if ($u['status'] === 'active') { $status_bg = 'rgba(16,185,129,0.1)'; $status_color = 'var(--primary)'; }
                            if ($u['status'] === 'pending') { $status_bg = 'rgba(245,158,11,0.1)'; $status_color = '#f59e0b'; }
                            if ($u['status'] === 'suspended') { $status_bg = 'rgba(244,63,94,0.1)'; $status_color = 'var(--rose)'; }
                        ?>
                        <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; text-transform: uppercase;">
                            <?php echo $u['status']; ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <?php 
                            $can_modify = false;
                            if ($user_role === 'admin' && $u['id'] !== $_SESSION['user_id']) {
                                $can_modify = true;
                            } elseif (in_array($user_role, ['manager', 'editor']) && $role_ranks[$user_role] > $role_ranks[$u['role']]) {
                                $can_modify = true;
                            }
                            ?>
                            
                            <?php if ($can_modify): ?>
                                <?php if ($u['status'] !== 'active'): ?>
                                    <form method="POST" style="display:inline;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-green" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 6px;"><i class="fas fa-check"></i> Activate</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($u['status'] !== 'suspended'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Suspend user @<?php echo e($u['username']); ?>?')">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="suspend">
                                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-red" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 6px;"><i class="fas fa-ban"></i> Suspend</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="font-size:0.75rem; color:var(--text-muted); font-style:italic;">No Actions Allowed</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Tab: Pending Approvals -->
<div id="tab-pending" class="tab-pane" style="display: none;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$pending_users): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 3rem;">No pending approvals in this date range.</td></tr>
                <?php endif; ?>
                <?php foreach ($pending_users as $u): ?>
                <tr>
                    <td><strong style="color: var(--secondary);"><?php echo e($u['full_name']); ?></strong><br><small style="color: var(--text-muted);">@<?php echo e($u['username']); ?> (<?php echo $u['role']; ?>)</small></td>
                    <td><?php echo e($u['email']); ?></td>
                    <td><?php echo e($u['phone']); ?></td>
                    <td><small><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($u['created_at'])); ?></small></td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <?php 
                            $can_modify = false;
                            if ($user_role === 'admin') {
                                $can_modify = true;
                            } elseif (in_array($user_role, ['manager', 'editor']) && $role_ranks[$user_role] > $role_ranks[$u['role']]) {
                                $can_modify = true;
                            }
                            ?>
                            <?php if ($can_modify): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve user?')">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-green" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 6px;"><i class="fas fa-check"></i> Approve</button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Reject user?')">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="suspend">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-red" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 6px;"><i class="fas fa-times"></i> Reject</button>
                                </form>
                            <?php else: ?>
                                <span style="font-size:0.75rem; color:var(--text-muted); font-style:italic;">No Actions Allowed</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Tab: Add User Form -->
<div id="tab-create" class="tab-pane" style="display: none;">
    <div class="card" style="max-width: 600px; margin: 0 auto; padding: 2rem;">
        <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight: 800; color:var(--secondary); margin-bottom: 1.5rem;"><i class="fas fa-user-plus" style="color:var(--primary);"></i> Register New Partner / Staff Account</h3>
        <form method="POST">
            <input type="hidden" name="create_user" value="1">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label>Username *</label>
                    <input type="text" name="username" required placeholder="e.g. johndoe" style="border-radius: 8px;">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Email Address *</label>
                    <input type="email" name="email" required placeholder="e.g. john@example.com" style="border-radius: 8px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label>Password *</label>
                    <input type="password" name="password" required placeholder="e.g. ••••••••" style="border-radius: 8px;">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Role Privilege *</label>
                    <select name="role" required style="border-radius: 8px;">
                        <?php foreach ($role_ranks as $r => $rank): ?>
                            <?php if ($role_ranks[$user_role] >= $rank): ?>
                                <option value="<?php echo $r; ?>" <?php echo $r === 'wholesale_user' ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $r)); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label>Full Contact Name</label>
                    <input type="text" name="full_name" placeholder="e.g. John Doe" style="border-radius: 8px;">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Direct Telephone</label>
                    <input type="text" name="phone" placeholder="e.g. +1 555-5555" style="border-radius: 8px;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>Status</label>
                <select name="status" style="border-radius: 8px;">
                    <option value="active" selected>Active / Approved</option>
                    <option value="pending">Pending Verification</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>

            <button type="submit" class="btn btn-green" style="width: 100%; border-radius: 8px; padding: 0.75rem;"><i class="fas fa-save"></i> Save Account Details</button>
        </form>
    </div>
</div>

<script>
function switchUserTab(tabId) {
    document.querySelectorAll('.tab-pane').forEach(el => el.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';
    
    // Toggle active styles on buttons
    document.getElementById('btn-users-list').style.background = 'rgba(15,23,42,0.05)';
    document.getElementById('btn-users-list').style.color = 'var(--text-muted)';
    document.getElementById('btn-users-list').style.boxShadow = 'none';

    document.getElementById('btn-pending').style.background = 'rgba(15,23,42,0.05)';
    document.getElementById('btn-pending').style.color = 'var(--text-muted)';
    document.getElementById('btn-pending').style.boxShadow = 'none';

    if (document.getElementById('btn-create')) {
        document.getElementById('btn-create').style.background = 'rgba(15,23,42,0.05)';
        document.getElementById('btn-create').style.color = 'var(--text-muted)';
        document.getElementById('btn-create').style.boxShadow = 'none';
    }

    let activeBtnId = 'btn-' + tabId.replace('tab-', '');
    document.getElementById(activeBtnId).style.background = 'var(--primary)';
    document.getElementById(activeBtnId).style.color = 'white';
}
</script>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
