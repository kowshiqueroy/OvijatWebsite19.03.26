<?php
/**
 * modules/settings/config.php
 */
include '../../includes/header.php';
requireRole('Admin');

$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$rows = $stmt->fetchAll();
$current_settings = [];
foreach ($rows as $row) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-cogs me-2"></i> System Configuration</h5>
            </div>
            <div class="card-body">
                <form id="settingsForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Application Name</label>
                        <input type="text" name="settings[app_name]" class="form-control" value="<?php echo $current_settings['app_name'] ?? ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Company Name</label>
                        <input type="text" name="settings[company_name]" class="form-control" value="<?php echo $current_settings['company_name'] ?? ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Company Address</label>
                        <textarea name="settings[company_address]" class="form-control" rows="2"><?php echo $current_settings['company_address'] ?? ''; ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Phone</label>
                            <input type="text" name="settings[company_phone]" class="form-control" value="<?php echo $current_settings['company_phone'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" name="settings[company_email]" class="form-control" value="<?php echo $current_settings['company_email'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Logo URL</label>
                            <input type="text" name="settings[company_logo]" class="form-control" value="<?php echo $current_settings['company_logo'] ?? ''; ?>" placeholder="/inv/uploads/logo.png">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Currency Symbol (e.g. BDT, $, ₹)</label>
                        <input type="text" name="settings[currency]" class="form-control" value="<?php echo $current_settings['currency'] ?? ''; ?>">
                    </div>

                    <div class="text-end border-top pt-3">
                        <button type="submit" class="btn btn-primary px-5">Update Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#settingsForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/settings.php?action=update', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                alert(res.message);
                location.reload();
            } else {
                alert(res.message);
            }
        }, 'json');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
