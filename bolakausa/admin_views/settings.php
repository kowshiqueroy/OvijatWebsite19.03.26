<?php
/**
 * Global Settings Management - Modernized
 */
restrict_to(['admin']);

$success = '';

// Ensure essential keys exist
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
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_from' => 'noreply@bolakausa.com'
];

foreach ($essential_keys as $key => $default_val) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
    $check->execute([$key]);
    if ($check->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        $stmt->execute([$key, $default_val, "Managed by admin"]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    foreach ($_POST['settings'] as $key => $value) {
        $stmt->execute([$value, $key]);
    }
    $success = "Settings updated successfully.";
    log_action($pdo, $_SESSION['user_id'], "Updated Global Settings");
}

$settings = $pdo->query("SELECT * FROM settings ORDER BY setting_key ASC")->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-cog"></i> Website & Company Settings
</div>

<?php if ($success): ?>
    <div style="background: #f0fdf4; color: #166534; padding: 1rem; border-radius: 8px; border: 1px solid #bbf7d0; margin-bottom: 2rem;">
        <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <?php foreach ($settings as $s): ?>
                <div class="form-group">
                    <label style="text-transform: capitalize;"><?php echo str_replace('_', ' ', e($s['setting_key'])); ?></label>
                    <input type="text" name="settings[<?php echo $s['setting_key']; ?>]" value="<?php echo e($s['setting_value']); ?>" placeholder="Enter <?php echo str_replace('_', ' ', e($s['setting_key'])); ?>">
                    <small style="color: var(--text-muted); font-size: 0.75rem;"><?php echo e($s['description']); ?></small>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-light); display: flex; gap: 1rem;">
            <button type="submit" name="save_settings" class="btn btn-green">
                <i class="fas fa-save"></i> Save All Changes
            </button>
            <a href="/bolakausa/admin" class="btn btn-blue">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </form>
</div>
