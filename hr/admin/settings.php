<?php
/**
 * Settings Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Settings';
$currentPage = 'settings';

$settings = getAllSettings();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = __DIR__ . '/../uploads/';
    
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $tempFile = $_FILES['company_logo']['tmp_name'];
        $newFileName = 'company_logo.' . pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
        $targetPath = $uploadDir . $newFileName;
        
        if (resizeAndCompressImage($tempFile, $targetPath, 300, 100, 80)) {
            saveSetting('company_logo', $newFileName);
        }
    }
    
    $settingsToSave = [
        'company_name' => sanitize($_POST['company_name']),
        'company_tagline' => sanitize($_POST['company_tagline']),
        'company_address' => sanitize($_POST['company_address']),
        'company_phone' => sanitize($_POST['company_phone']),
        'company_email' => sanitize($_POST['company_email']),
        'company_website' => sanitize($_POST['company_website']),
        'default_pf_percentage' => sanitize($_POST['default_pf_percentage']),
        'default_working_days' => sanitize($_POST['default_working_days']),
        'currency_symbol' => sanitize($_POST['currency_symbol']),
        'currency_code' => sanitize($_POST['currency_code'])
    ];
    
    saveSettings($settingsToSave);
    $settings = getAllSettings();
    $message = 'Settings saved successfully!';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">Settings</h4>
        <small class="text-muted">Manage company information and defaults</small>
    </div>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $messageType); ?>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-building me-2"></i>Company Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tagline</label>
                        <input type="text" name="company_tagline" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['company_tagline'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="company_address" class="form-control" rows="2"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="company_phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="company_email" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="text" name="company_website" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['company_website'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Company Logo</label>
                        <input type="file" name="company_logo" class="form-control" accept="image/*">
                        <?php if (!empty($settings['company_logo'])): ?>
                            <div class="mt-2">
                                <img src="../uploads/<?php echo htmlspecialchars($settings['company_logo']); ?>" 
                                     alt="Logo" style="max-height: 60px;" class="img-thumbnail">
                                <small class="text-muted ms-2">Current logo</small>
                            </div>
                        <?php endif; ?>
                        <small class="text-muted">Recommended size: 300x100px</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Default Settings</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Default PF Percentage (%)</label>
                        <input type="number" name="default_pf_percentage" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['default_pf_percentage'] ?? '5.00'); ?>" 
                               step="0.01" min="0" max="100">
                        <small class="text-muted">Default provident fund percentage for new employees</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default Working Days</label>
                        <input type="number" name="default_working_days" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['default_working_days'] ?? '26'); ?>" 
                               min="1" max="31">
                        <small class="text-muted">Default working days per month for salary calculation</small>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-cash me-2"></i>Currency Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? '$'); ?>" 
                                   maxlength="5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Currency Code</label>
                            <input type="text" name="currency_code" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['currency_code'] ?? 'USD'); ?>" 
                                   maxlength="10">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4 border-primary">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i> Save Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
