<?php
/**
 * Partner Account Settings & Addresses Management
 */
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . 'login');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Profile Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
        die("Invalid CSRF token.");
    }
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!$full_name) {
        $error = "Full name is required.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$full_name, $phone, $user_id])) {
            $success = "Profile updated successfully.";
            log_action($pdo, $user_id, 'Updated Profile Info');
        } else {
            $error = "Failed to update profile details.";
        }
    }
}

// Handle Add Address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_address'])) {
    $address_line = trim($_POST['address_line'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    if ($address_line && $city && !empty($_POST['location_id'])) {
        $location_id = (int)$_POST['location_id'];
        // If default is selected, reset other default addresses first
        if ($is_default) {
            $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$user_id]);
        } else {
            // If it's the first address, make it default automatically
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
            $stmt->execute([$user_id]);
            if ($stmt->fetchColumn() == 0) {
                $is_default = 1;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, address_line, city, location_id, is_default) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $address_line, $city, $location_id, $is_default])) {
            $success = "New shipping address added successfully.";
            log_action($pdo, $user_id, 'Added Shipping Address', null, "$address_line, $city");
        } else {
            $error = "Failed to add shipping address.";
        }
    } else {
        $error = "Address line, city, and US Delivery State are required.";
    }
}

// Handle Delete Address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_address'])) {
    if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
        die("Invalid CSRF token.");
    }
    $addr_id = (int)$_POST['delete_address'];
    
    $stmt = $pdo->prepare("SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$addr_id, $user_id]);
    $addr = $stmt->fetch();
    
    if ($addr) {
        $was_default = $addr['is_default'];
        
        $stmt = $pdo->prepare("UPDATE user_addresses SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$addr_id]);
        log_action($pdo, $user_id, 'Deleted Shipping Address', "ID: $addr_id");
        $success = "Address deleted successfully.";
        
        if ($was_default) {
            $stmt = $pdo->prepare("SELECT id FROM user_addresses WHERE user_id = ? AND is_deleted = 0 LIMIT 1");
            $stmt->execute([$user_id]);
            $next = $stmt->fetch();
            if ($next) {
                $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ?");
                $stmt->execute([$next['id']]);
            }
        }
    }
}

// Handle Set Default Address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_default'])) {
    if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
        die("Invalid CSRF token.");
    }
    $addr_id = (int)$_POST['set_default'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$addr_id, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ?");
        $stmt->execute([$addr_id]);
        
        $success = "Default address updated.";
        log_action($pdo, $user_id, 'Set Default Address', null, "ID: $addr_id");
    }
}

// Fetch Profile Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch addresses with location info
$stmt = $pdo->prepare("SELECT a.*, l.name as location_name, l.tax_percent FROM user_addresses a LEFT JOIN locations l ON a.location_id = l.id WHERE a.user_id = ? AND a.is_deleted = 0 ORDER BY a.is_default DESC, a.id DESC");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll();

// Fetch available locations for the dropdown
$locs = $pdo->prepare("SELECT id, name FROM locations WHERE is_deleted = 0 ORDER BY name ASC");
$locs->execute();
$locations = $locs->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-user-circle"></i> Profile & Address Settings
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

<div class="grid-stack-mobile" style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 2.5rem; align-items: flex-start; flex-wrap: wrap;">
    <!-- Left: Profile Details -->
    <div>
        <div class="card" style="padding: 2.25rem;">
            <div style="text-align: center; margin-bottom: 2rem; border-bottom: 1px solid var(--border-light); padding-bottom: 1.5rem;">
                <div style="width: 72px; height: 72px; background: rgba(99, 102, 241, 0.08); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2rem; color: var(--accent); border: 1px solid rgba(99,102,241,0.15);">
                    <i class="fas fa-building"></i>
                </div>
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 0.25rem;">
                    <?php echo e($user['full_name'] ?: $user['username']); ?>
                </h3>
                <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 800; padding: 3px 8px; border-radius: 4px; background: rgba(16, 185, 129, 0.08); color: var(--primary); border: 1px solid rgba(16,185,129,0.12);">
                    <?php echo e(str_replace('_', ' ', $user['role'])); ?> Account
                </span>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="update_profile" value="1">
                
                <div class="form-group">
                    <label>Username (Read-Only)</label>
                    <input type="text" value="<?php echo e($user['username']); ?>" disabled style="background: #f1f5f9; color: var(--text-muted); cursor: not-allowed; border-color: #e2e8f0;">
                </div>

                <div class="form-group">
                    <label>Email Address (Read-Only)</label>
                    <input type="email" value="<?php echo e($user['email']); ?>" disabled style="background: #f1f5f9; color: var(--text-muted); cursor: not-allowed; border-color: #e2e8f0;">
                </div>

                <div class="form-group">
                    <label>Primary Contact Full Name</label>
                    <input type="text" name="full_name" value="<?php echo e($user['full_name']); ?>" placeholder="Enter contact person name" required>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label>Direct Telephone / Mobile</label>
                    <input type="text" name="phone" value="<?php echo e($user['phone']); ?>" placeholder="Enter phone number">
                </div>

                <button type="submit" class="btn btn-green" style="width: 100%; border-radius: 8px;">
                    <i class="fas fa-save"></i> Save Profile Details
                </button>
            </form>
        </div>
    </div>

    <!-- Right: Shipping Addresses -->
    <div>
        <div class="card" style="padding: 2.25rem; margin-bottom: 2.5rem;">
            <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-truck" style="color: var(--primary);"></i> Registered Shipping Locations
            </h3>
            
            <?php if (!$addresses): ?>
                <div style="background: rgba(15,23,42,0.02); color: var(--text-muted); padding: 2rem; border-radius: 12px; text-align: center; border: 1px dashed var(--border-light); font-size: 0.9rem; margin-bottom: 1.5rem;">
                    No shipping addresses registered. Please add a location below to enable checkout.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
                    <?php foreach ($addresses as $addr): ?>
                        <div style="border: 1px solid var(--border-light); padding: 1.25rem; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; background: white; transition: all 0.2s;">
                            <div>
                                <strong style="color: var(--secondary); display: block; font-size: 0.95rem;"><?php echo e($addr['address_line']); ?></strong>
                                <span style="color: var(--text-muted); font-size: 0.85rem; display: block; margin-top: 0.15rem;"><?php echo e($addr['city']); ?></span>
                                <?php if ($addr['location_name']): ?>
                                    <span style="display: inline-block; font-size: 0.7rem; font-weight: 700; color: var(--accent); margin-top: 0.25rem;">
                                        <i class="fas fa-map-pin"></i> <?php echo e($addr['location_name']); ?> (Tax: <?php echo $addr['tax_percent']; ?>%)
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-block; font-size: 0.65rem; font-weight: 700; color: var(--rose); margin-top: 0.25rem;">
                                        <i class="fas fa-exclamation-triangle"></i> Missing US Delivery State — delete and re-add
                                    </span>
                                <?php endif; ?>
                                <?php if ($addr['is_default']): ?>
                                    <span style="display: inline-block; background: rgba(16,185,129,0.08); color: var(--primary); font-size: 0.65rem; font-weight: 800; padding: 1px 6px; border-radius: 4px; text-transform: uppercase; margin-top: 0.5rem; border: 1px solid rgba(16,185,129,0.12);">Default Address</span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <?php if (!$addr['is_default']): ?>
                                    <form method="POST" style="display:inline;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="set_default" value="<?php echo $addr['id']; ?>">
                                        <button type="submit" class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px;">Set Default</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this shipping location?')">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="delete_address" value="<?php echo $addr['id']; ?>">
                                    <button type="submit" class="btn btn-red" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px;"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div style="border-top: 1px solid var(--border-light); padding-top: 1.5rem;">
                <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.05rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.25rem;">Add New Address</h4>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="add_address" value="1">
                    
                    <div class="form-group">
                        <label>Street Address *</label>
                        <input type="text" name="address_line" placeholder="e.g. 100 Main St, Suite 400" required>
                    </div>

                    <div class="form-group">
                        <label>City & State / Zip *</label>
                        <input type="text" name="city" placeholder="e.g. New York, NY 10001" required>
                    </div>

                    <div class="form-group">
                        <label>US Delivery State *</label>
                        <select name="location_id" style="border-radius: 8px;" required>
                            <option value="">-- Select delivery zone --</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo $loc['id']; ?>"><?php echo e($loc['name']); ?> (Tax: <?php echo $loc['tax_percent']; ?>%)</option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--rose); font-size: 0.7rem; display: block; margin-top: 0.25rem;">Required — used for delivery fee & tax calculation.</small>
                    </div>

                    <div class="form-group" style="flex-direction: row; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                        <input type="checkbox" name="is_default" id="is_default" value="1" style="width: auto;">
                        <label for="is_default" style="margin: 0; cursor: pointer;">Set as default shipping address</label>
                    </div>

                    <button type="submit" class="btn btn-blue" style="width: 100%; border-radius: 8px;">
                        <i class="fas fa-plus"></i> Add Address
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<p style="margin-top: 1rem;"><a href="/bolakausa/home" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Shop</a></p>
