<?php
/**
 * Global Settings & Locations Management
 */
restrict_to(['admin', 'manager']);

$user_role = $_SESSION['user_role'];
$is_admin = ($user_role === 'admin');

$success = '';
$error = '';

// Ensure essential settings exist
$essential_keys = [
    'company_name' => 'Bolakausa Wholesale',
    'company_logo_url' => '',
    'company_email' => 'help@bolakausa.com',
    'company_phone' => '+1 234 567 890',
    'company_address' => '123 Wholesale St, Food City',
    'min_order_value' => '100.00',
    'payment_cod_enabled' => '1',
    'payment_bank_enabled' => '1',
    'payment_paylater_enabled' => '1',
    'payment_stripe_enabled' => '0',
    'bank_name' => 'Chase Bank',
    'bank_account_number' => '1234567890',
    'bank_routing_number' => '987654321',
    'bank_transfer_instructions' => 'Please include your Order ID in wire description.',
    'stripe_public_key' => 'pk_test_...',
    'stripe_secret_key' => 'sk_test_...',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_from' => 'noreply@bolakausa.com',
    'tax_on_shipping' => '0',
    'wallet_overdraft_limit' => '1000.00',
    'default_tax_rate' => '0.00',
    'currency_symbol' => '$',
    'system_timezone' => 'America/New_York'
];

$existing = $pdo->query("SELECT setting_key FROM settings")->fetchAll(PDO::FETCH_COLUMN);
$insert = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
foreach ($essential_keys as $key => $default_val) {
    if (!in_array($key, $existing)) {
        $insert->execute([$key, $default_val, "System setting variable"]);
    }
}

// Define sensitive settings keys (only admins can modify, managers read-only)
$sensitive_keys = [
    'min_order_value', 'payment_bank_enabled', 'payment_stripe_enabled', 'payment_cod_enabled', 'payment_paylater_enabled',
    'bank_name', 'bank_account_number', 'bank_routing_number', 'bank_transfer_instructions',
    'stripe_public_key', 'stripe_secret_key', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from',
    'wallet_overdraft_limit', 'default_tax_rate', 'currency_symbol', 'system_timezone', 'tax_on_shipping'
];

// Handle Global Settings Saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    
    // Process text settings
    if (isset($_POST['settings']) && is_array($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            // Block managers from saving sensitive settings
            if (in_array($key, $sensitive_keys) && !$is_admin) {
                continue;
            }
            $stmt->execute([$value, $key]);
        }
    }
    
    // Process Logo File Upload if any
    if (isset($_FILES['company_logo_file']) && $_FILES['company_logo_file']['error'] === UPLOAD_ERR_OK) {
        if ($is_admin) {
            $file_tmp = $_FILES['company_logo_file']['tmp_name'];
            $file_name = $_FILES['company_logo_file']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
            if (in_array($ext, $allowed_exts)) {
                $upload_dir = 'public/uploads/brand/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = 'logo_' . time() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $stmt->execute([$upload_path, 'company_logo_url']);
                    $success = "System configurations and logo uploaded successfully.";
                } else {
                    $error = "Failed to save uploaded logo file.";
                }
            } else {
                $error = "Invalid logo file type. Allowed: " . implode(', ', $allowed_exts);
            }
        } else {
            $error = "Managers are not authorized to update company logo.";
        }
    } else {
        $success = "System configurations updated successfully.";
    }
    
    log_action($pdo, $_SESSION['user_id'], "Updated Global Settings");
    // Reload configurations
    $settings = $pdo->query("SELECT * FROM settings ORDER BY setting_key ASC")->fetchAll();
}

// Handle Location: Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_location'])) {
    $name = trim($_POST['loc_name'] ?? '');
    $tax = (float)($_POST['loc_tax'] ?? 0);
    $base = (float)($_POST['loc_base'] ?? 0);
    $weight = (float)($_POST['loc_weight'] ?? 0);

    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO locations (name, tax_percent, base_delivery_charge, per_unit_weight_charge) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $tax, $base, $weight])) {
            $success = "US State delivery location '$name' added successfully.";
            log_action($pdo, $_SESSION['user_id'], 'Created Location', null, $name);
        }
    } else {
        $error = "Location State name is required.";
    }
}

// Handle Location: Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_location'])) {
    $loc_id = (int)$_POST['location_id'];
    $name = trim($_POST['loc_name'] ?? '');
    $tax = (float)($_POST['loc_tax'] ?? 0);
    $base = (float)($_POST['loc_base'] ?? 0);
    $weight = (float)($_POST['loc_weight'] ?? 0);

    if ($loc_id && $name) {
        $stmt = $pdo->prepare("UPDATE locations SET name = ?, tax_percent = ?, base_delivery_charge = ?, per_unit_weight_charge = ? WHERE id = ?");
        if ($stmt->execute([$name, $tax, $base, $weight, $loc_id])) {
            $success = "Location '$name' updated successfully.";
            log_action($pdo, $_SESSION['user_id'], 'Updated Location', null, "ID: $loc_id, Name: $name");
        }
    } else {
        $error = "Location State name is required.";
    }
}

// Handle Location: Delete
if (isset($_GET['delete_location'])) {
    $loc_id = (int)$_GET['delete_location'];
    // Check if location is referenced by users first to prevent foreign key errors
    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE location_id = ?");
    $check->execute([$loc_id]);
    if ($check->fetchColumn() > 0) {
        $error = "Cannot delete state location: It is currently linked to active users.";
    } else {
        $stmt = $pdo->prepare("UPDATE locations SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$loc_id]);
        $success = "Location deleted successfully.";
        log_action($pdo, $_SESSION['user_id'], 'Deleted Location', null, "ID: $loc_id");
    }
}

// Fetch current configurations
$settings = $pdo->query("SELECT * FROM settings ORDER BY setting_key ASC")->fetchAll();
$locations = $pdo->query("SELECT * FROM locations WHERE is_deleted = 0 ORDER BY name ASC")->fetchAll();

// Map settings for quick retrieval
$settings_map = [];
foreach ($settings as $s) {
    $settings_map[$s['setting_key']] = $s['setting_value'];
}

$timezones = [
    'America/New_York' => 'Eastern Time (US & Canada)',
    'America/Chicago' => 'Central Time (US & Canada)',
    'America/Denver' => 'Mountain Time (US & Canada)',
    'America/Los_Angeles' => 'Pacific Time (US & Canada)',
    'America/Anchorage' => 'Alaska Time',
    'Pacific/Honolulu' => 'Hawaii Standard Time',
    'UTC' => 'Coordinated Universal Time (UTC)',
    'Europe/London' => 'London / Greenwich Mean Time',
    'Europe/Paris' => 'Paris / Central European Time',
    'Asia/Dhaka' => 'Dhaka / Bangladesh Standard Time',
    'Asia/Kolkata' => 'Kolkata / India Standard Time',
    'Asia/Tokyo' => 'Tokyo / Japan Standard Time'
];

if (!empty($settings_map['system_timezone']) && !array_key_exists($settings_map['system_timezone'], $timezones)) {
    $timezones[$settings_map['system_timezone']] = $settings_map['system_timezone'];
}
?>

<style>
.settings-container {
    display: flex;
    gap: 2rem;
    align-items: flex-start;
    margin-top: 1.5rem;
}
@media (max-width: 992px) {
    .settings-container {
        flex-direction: column;
    }
    .settings-tabs-sidebar {
        width: 100% !important;
        flex-direction: row !important;
        overflow-x: auto;
        white-space: nowrap;
    }
}
.settings-tabs-sidebar {
    flex: 0 0 260px;
    background: var(--glass-bg);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-md);
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    box-shadow: var(--glass-shadow);
}
.tab-btn {
    background: transparent;
    border: none;
    text-align: left;
    padding: 0.85rem 1.25rem;
    border-radius: var(--radius-md);
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 0.85rem;
    font-size: 0.9rem;
    width: 100%;
}
.tab-btn i {
    font-size: 1.1rem;
    width: 24px;
    text-align: center;
    transition: transform 0.3s;
}
.tab-btn:hover {
    background: rgba(15, 23, 42, 0.03);
    color: var(--secondary);
}
.tab-btn:hover i {
    transform: scale(1.1);
}
.tab-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    box-shadow: 0 4px 20px var(--primary-glow);
}
.settings-content-area {
    flex: 1;
    min-width: 0;
}
.settings-card {
    background: white;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 2.25rem;
    box-shadow: var(--shadow-sm);
    margin-bottom: 2rem;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}
.full-width {
    grid-column: 1 / -1;
}
.password-toggle-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}
.password-toggle-btn {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    transition: color 0.2s;
    z-index: 10;
}
.password-toggle-btn:hover {
    color: var(--secondary);
}
/* Toggle Switch Style */
.switch-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8fafc;
    border: 1px solid var(--border-light);
    padding: 1rem 1.25rem;
    border-radius: var(--radius-md);
    transition: all 0.2s;
}
.switch-wrapper:hover {
    border-color: rgba(16, 185, 129, 0.25);
    background: rgba(16, 185, 129, 0.01);
}
.switch-label-desc {
    display: flex;
    flex-direction: column;
    gap: 2px;
    max-width: 75%;
}
.switch-title {
    font-weight: 700;
    color: var(--secondary);
    font-size: 0.925rem;
    text-transform: capitalize;
}
.switch-subtitle {
    font-size: 0.775rem;
    color: var(--text-muted);
}
.switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 24px;
    flex-shrink: 0;
}
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e1;
    transition: .3s;
    border-radius: 24px;
}
.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}
input:checked + .slider {
    background-color: var(--primary);
}
input:checked + .slider:before {
    transform: translateX(22px);
}
/* File Upload */
.logo-upload-zone {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    padding: 1.25rem;
    border-radius: var(--radius-md);
    margin-top: 0.5rem;
}
.logo-preview-img {
    max-height: 64px;
    max-width: 180px;
    object-fit: contain;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    background: white;
    padding: 6px;
}
.restricted-badge {
    background: rgba(245,158,11,0.06);
    border: 1px solid rgba(245,158,11,0.15);
    color: #b45309;
    padding: 1rem;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
</style>

<div class="section-title">
    <i class="fas fa-sliders-h"></i> Corporate Settings Dashboard
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

<div class="settings-container">
    <!-- Sidebar Navigation -->
    <div class="settings-tabs-sidebar">
        <button onclick="switchSetTab('set-branding')" id="btn-set-branding" class="tab-btn active">
            <i class="fas fa-building"></i> Branding & Profile
        </button>
        <button onclick="switchSetTab('set-rules')" id="btn-set-rules" class="tab-btn">
            <i class="fas fa-sliders-h"></i> System Rules
        </button>
        <button onclick="switchSetTab('set-gateways')" id="btn-set-gateways" class="tab-btn">
            <i class="fas fa-credit-card"></i> Payment Gateways
        </button>
        <button onclick="switchSetTab('set-smtp')" id="btn-set-smtp" class="tab-btn">
            <i class="fas fa-paper-plane"></i> SMTP Config
        </button>
        <button onclick="switchSetTab('set-locations')" id="btn-set-locations" class="tab-btn">
            <i class="fas fa-map-marked-alt"></i> USA Logistics Matrix
        </button>
    </div>

    <!-- Content Area -->
    <div class="settings-content-area">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="save_settings" value="1">

            <!-- TAB 1: BRANDING & PROFILE -->
            <div id="set-branding" class="set-tab-content" style="display: block;">
                <div class="settings-card">
                    <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem;">🏢 Branding & Profile Configuration</h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Company Name</label>
                            <input type="text" name="settings[company_name]" value="<?php echo e($settings_map['company_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Company Email</label>
                            <input type="email" name="settings[company_email]" value="<?php echo e($settings_map['company_email']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Company Phone</label>
                            <input type="text" name="settings[company_phone]" value="<?php echo e($settings_map['company_phone']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Logo File Upload (PNG/JPG/SVG)</label>
                            <div class="logo-upload-zone">
                                <?php
                                $logo_src = !empty($settings_map['company_logo_url']) ? BASE_URL . $settings_map['company_logo_url'] : BASE_URL . 'public/images/logo/logoofbolakausa.png';
                                ?>
                                <img src="<?php echo $logo_src; ?>" class="logo-preview-img" alt="Logo Preview">
                                <div style="flex: 1;">
                                    <input type="file" name="company_logo_file" id="logo_file_input" style="border: none; padding: 0;" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 5px;">Upload a crisp brand asset to synchronize across sidebars and headers instantly.</p>
                                </div>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label>Alternative Logo URL (Dynamic Fallback)</label>
                            <input type="text" name="settings[company_logo_url]" value="<?php echo e($settings_map['company_logo_url']); ?>" placeholder="e.g. public/images/logo/custom_logo.png" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group full-width">
                            <label>Company Address</label>
                            <input type="text" name="settings[company_address]" value="<?php echo e($settings_map['company_address']); ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-green" style="margin-top: 2rem; border-radius: 8px;">Save Settings</button>
                </div>
            </div>

            <!-- TAB 2: SYSTEM RULES -->
            <div id="set-rules" class="set-tab-content" style="display: none;">
                <div class="settings-card">
                    <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem;">⚙️ Wholesaler Trading Rules</h3>
                    
                    <?php if (!$is_admin): ?>
                        <div class="restricted-badge">
                            <i class="fas fa-lock"></i> Managers are restricted from modifying trading rules, debt allowances, or timezones.
                        </div>
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Minimum Order Value ($)</label>
                            <input type="number" step="0.01" name="settings[min_order_value]" value="<?php echo e($settings_map['min_order_value']); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Wallet Overdraft Limit (Max Debt Allowance, $)</label>
                            <input type="number" step="0.01" name="settings[wallet_overdraft_limit]" value="<?php echo e($settings_map['wallet_overdraft_limit']); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Default Tax Rate (%)</label>
                            <input type="number" step="0.01" name="settings[default_tax_rate]" value="<?php echo e($settings_map['default_tax_rate']); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Currency Symbol</label>
                            <input type="text" name="settings[currency_symbol]" value="<?php echo e($settings_map['currency_symbol'] ?? '$'); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>System Timezone</label>
                            <select name="settings[system_timezone]" <?php echo !$is_admin ? 'disabled' : ''; ?> style="width: 100%; border-radius: 8px; padding: 0.75rem; border: 1px solid var(--border-light); font-family: inherit; font-size: 0.9rem;">
                                <?php foreach ($timezones as $tz_val => $tz_label): ?>
                                    <option value="<?php echo e($tz_val); ?>" <?php echo ($settings_map['system_timezone'] === $tz_val) ? 'selected' : ''; ?>>
                                        <?php echo e($tz_val); ?> - <?php echo e($tz_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tax On Shipping Fee</label>
                            <div class="switch-wrapper">
                                <div class="switch-label-desc">
                                    <span class="switch-title">Tax On Delivery</span>
                                    <span class="switch-subtitle">Apply state tax percentage to shipping charges</span>
                                </div>
                                <label class="switch">
                                    <input type="hidden" name="settings[tax_on_shipping]" value="0">
                                    <input type="checkbox" name="settings[tax_on_shipping]" value="1" <?php echo ($settings_map['tax_on_shipping'] === '1') ? 'checked' : ''; ?> <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <?php if ($is_admin): ?>
                        <button type="submit" class="btn btn-green" style="margin-top: 2rem; border-radius: 8px;">Save Settings</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 3: PAYMENT GATEWAYS -->
            <div id="set-gateways" class="set-tab-content" style="display: none;">
                <div class="settings-card">
                    <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem;">💳 B2B Checkout Gateways</h3>
                    
                    <?php if (!$is_admin): ?>
                        <div class="restricted-badge">
                            <i class="fas fa-lock"></i> Managers are restricted from modifying payment methods or API credentials.
                        </div>
                    <?php endif; ?>

                    <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; margin-bottom: 1rem; color: var(--secondary); border-bottom: 1px solid var(--border-light); padding-bottom: 0.5rem;">Active Checkout Gateways</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                        <div class="switch-wrapper">
                            <div class="switch-label-desc">
                                <span class="switch-title">Cash On Delivery (COD)</span>
                                <span class="switch-subtitle">Enable wholesalers to check out and pay in cash upon delivery</span>
                            </div>
                            <label class="switch">
                                <input type="hidden" name="settings[payment_cod_enabled]" value="0">
                                <input type="checkbox" name="settings[payment_cod_enabled]" value="1" <?php echo ($settings_map['payment_cod_enabled'] === '1') ? 'checked' : ''; ?> <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="switch-wrapper">
                            <div class="switch-label-desc">
                                <span class="switch-title">Pay Later (Terms / Invoices)</span>
                                <span class="switch-subtitle">Charge wholesale credit ledger and pay outstanding invoice balances later</span>
                            </div>
                            <label class="switch">
                                <input type="hidden" name="settings[payment_paylater_enabled]" value="0">
                                <input type="checkbox" name="settings[payment_paylater_enabled]" value="1" <?php echo ($settings_map['payment_paylater_enabled'] === '1') ? 'checked' : ''; ?> <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="switch-wrapper">
                            <div class="switch-label-desc">
                                <span class="switch-title">Stripe Card Gateway</span>
                                <span class="switch-subtitle">Allow instant credit card processing via Stripe Checkout</span>
                            </div>
                            <label class="switch">
                                <input type="hidden" name="settings[payment_stripe_enabled]" value="0">
                                <input type="checkbox" name="settings[payment_stripe_enabled]" value="1" <?php echo ($settings_map['payment_stripe_enabled'] === '1') ? 'checked' : ''; ?> <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="switch-wrapper">
                            <div class="switch-label-desc">
                                <span class="switch-title">Bank Wire / Transfer</span>
                                <span class="switch-subtitle">Allow wholesalers to submit routing info and transaction references</span>
                            </div>
                            <label class="switch">
                                <input type="hidden" name="settings[payment_bank_enabled]" value="0">
                                <input type="checkbox" name="settings[payment_bank_enabled]" value="1" <?php echo ($settings_map['payment_bank_enabled'] === '1') ? 'checked' : ''; ?> <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; margin-bottom: 1rem; color: var(--secondary); border-bottom: 1px solid var(--border-light); padding-bottom: 0.5rem;">Stripe API Parameters</h4>
                    <div class="form-grid" style="margin-bottom: 2rem;">
                        <div class="form-group">
                            <label>Stripe Public Key</label>
                            <input type="text" name="settings[stripe_public_key]" value="<?php echo e($settings_map['stripe_public_key']); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Stripe Secret Key</label>
                            <div class="password-toggle-wrapper">
                                <input type="password" id="stripe_secret_key" name="settings[stripe_secret_key]" value="<?php echo e($settings_map['stripe_secret_key']); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?> style="width: 100%; padding-right: 2.5rem;">
                                <button type="button" onclick="togglePasswordVisibility('stripe_secret_key')" class="password-toggle-btn">
                                    <i class="far fa-eye" id="stripe_secret_key_icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; margin-bottom: 1rem; color: var(--secondary); border-bottom: 1px solid var(--border-light); padding-bottom: 0.5rem;">Corporate Bank Details</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Bank Name</label>
                            <input type="text" name="settings[bank_name]" value="<?php echo e($settings_map['bank_name']); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Account Number</label>
                            <input type="text" name="settings[bank_account_number]" value="<?php echo e($settings_map['bank_account_number']); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Routing Number</label>
                            <input type="text" name="settings[bank_routing_number]" value="<?php echo e($settings_map['bank_routing_number']); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group full-width">
                            <label>Bank Wire Instructions</label>
                            <textarea name="settings[bank_transfer_instructions]" rows="3" <?php echo !$is_admin ? 'disabled' : ''; ?> style="width: 100%; border-radius: 8px; border: 1px solid var(--border-light); padding: 0.75rem; font-family: inherit; font-size: 0.9rem; resize: vertical;"><?php echo e($settings_map['bank_transfer_instructions']); ?></textarea>
                        </div>
                    </div>

                    <?php if ($is_admin): ?>
                        <button type="submit" class="btn btn-green" style="margin-top: 2rem; border-radius: 8px;">Save Settings</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 4: SMTP CONFIGURATION -->
            <div id="set-smtp" class="set-tab-content" style="display: none;">
                <div class="settings-card">
                    <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem;">✉️ SMTP Mail Configuration</h3>
                    
                    <?php if (!$is_admin): ?>
                        <div class="restricted-badge">
                            <i class="fas fa-lock"></i> Managers are restricted from modifying mail server parameters.
                        </div>
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>SMTP Host</label>
                            <input type="text" name="settings[smtp_host]" value="<?php echo e($settings_map['smtp_host']); ?>" placeholder="e.g. smtp.mailtrap.io" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>SMTP Port</label>
                            <input type="text" name="settings[smtp_port]" value="<?php echo e($settings_map['smtp_port']); ?>" placeholder="e.g. 587" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>SMTP Username / User</label>
                            <input type="text" name="settings[smtp_user]" value="<?php echo e($settings_map['smtp_user']); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>SMTP Password</label>
                            <div class="password-toggle-wrapper">
                                <input type="password" id="smtp_pass" name="settings[smtp_pass]" value="<?php echo e($settings_map['smtp_pass']); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?> style="width: 100%; padding-right: 2.5rem;">
                                <button type="button" onclick="togglePasswordVisibility('smtp_pass')" class="password-toggle-btn">
                                    <i class="far fa-eye" id="smtp_pass_icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label>SMTP From Email</label>
                            <input type="email" name="settings[smtp_from]" value="<?php echo e($settings_map['smtp_from']); ?>" placeholder="e.g. noreply@bolakausa.com" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                        </div>
                    </div>

                    <?php if ($is_admin): ?>
                        <button type="submit" class="btn btn-green" style="margin-top: 2rem; border-radius: 8px;">Save Settings</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- TAB 5: USA LOGISTICS MATRIX -->
        <div id="set-locations" class="set-tab-content" style="display: none;">
            <div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: flex-start; flex-wrap: wrap;">
                <!-- Left: State Matrix List -->
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>US State / Location</th>
                                <th>Tax Percent</th>
                                <th>Base Shipping Fee</th>
                                <th>Per KG Shipping Fee</th>
                                <th style="text-align: right; width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$locations): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No delivery locations found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($locations as $loc): ?>
                            <tr>
                                <td><strong style="color: var(--secondary);"><?php echo e($loc['name']); ?></strong></td>
                                <td><strong style="color: var(--primary);"><?php echo $loc['tax_percent']; ?>%</strong></td>
                                <td>$<?php echo number_format($loc['base_delivery_charge'], 2); ?></td>
                                <td>$<?php echo number_format($loc['per_unit_weight_charge'], 2); ?> / kg</td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <button onclick="triggerEditLocation(<?php echo $loc['id']; ?>, '<?php echo e($loc['name']); ?>', <?php echo $loc['tax_percent']; ?>, <?php echo $loc['base_delivery_charge']; ?>, <?php echo $loc['per_unit_weight_charge']; ?>)" class="btn btn-blue" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px;">Edit</button>
                                        <a href="/bolakausa/admin/settings?delete_location=<?php echo $loc['id']; ?>" onclick="return confirm('Remove location state?')" class="btn btn-red" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px;"><i class="fas fa-trash-alt"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Right: Location Management Forms -->
                <div>
                    <!-- Add State -->
                    <div class="settings-card" id="location-add-card" style="padding: 1.75rem;">
                        <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.25rem;">Add US Delivery State</h3>
                        <form method="POST">
                            <input type="hidden" name="add_location" value="1">
                            <div class="form-group">
                                <label>US State Name *</label>
                                <input type="text" name="loc_name" placeholder="e.g. California" required>
                            </div>
                            <div class="form-group">
                                <label>State Tax Rate (%) *</label>
                                <input type="number" step="0.01" name="loc_tax" value="0.00" required>
                            </div>
                            <div class="form-group">
                                <label>Base Delivery Fee ($) *</label>
                                <input type="number" step="0.01" name="loc_base" value="0.00" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label>Per KG Weight Delivery Charge ($) *</label>
                                <input type="number" step="0.01" name="loc_weight" value="0.00" required>
                            </div>
                            <button type="submit" class="btn btn-green" style="width: 100%; border-radius: 8px;">
                                <i class="fas fa-plus"></i> Add State Zone
                            </button>
                        </form>
                    </div>

                    <!-- Edit State (Hidden by default) -->
                    <div class="settings-card" id="location-edit-card" style="padding: 1.75rem; display: none; border-color: var(--accent);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                            <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--secondary); margin: 0;">Edit US State Settings</h3>
                            <button onclick="closeEditLocation()" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 4px;"><i class="fas fa-times"></i></button>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="edit_location" value="1">
                            <input type="hidden" name="location_id" id="edit-loc-id">
                            <div class="form-group">
                                <label>US State Name *</label>
                                <input type="text" name="loc_name" id="edit-loc-name" required>
                            </div>
                            <div class="form-group">
                                <label>State Tax Rate (%) *</label>
                                <input type="number" step="0.01" name="loc_tax" id="edit-loc-tax" required>
                            </div>
                            <div class="form-group">
                                <label>Base Delivery Fee ($) *</label>
                                <input type="number" step="0.01" name="loc_base" id="edit-loc-base" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label>Per KG Weight Delivery Charge ($) *</label>
                                <input type="number" step="0.01" name="loc_weight" id="edit-loc-weight" required>
                            </div>
                            <button type="submit" class="btn btn-blue" style="width: 100%; border-radius: 8px;">
                                <i class="fas fa-save"></i> Save State Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function switchSetTab(tabId) {
    document.querySelectorAll('.set-tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(tabId).style.display = 'block';
    document.getElementById('btn-' + tabId).classList.add('active');
}

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(inputId + '_icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function triggerEditLocation(id, name, tax, base, weight) {
    document.getElementById('location-add-card').style.display = 'none';
    
    const editCard = document.getElementById('location-edit-card');
    editCard.style.display = 'block';
    
    document.getElementById('edit-loc-id').value = id;
    document.getElementById('edit-loc-name').value = name;
    document.getElementById('edit-loc-tax').value = tax;
    document.getElementById('edit-loc-base').value = base;
    document.getElementById('edit-loc-weight').value = weight;
    
    editCard.scrollIntoView({ behavior: 'smooth' });
}

function closeEditLocation() {
    document.getElementById('location-edit-card').style.display = 'none';
    document.getElementById('location-add-card').style.display = 'block';
}
</script>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
