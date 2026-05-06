<?php
require_once '../../templates/header.php';
check_login();
check_role(ROLE_ADMIN);

if (isset($_POST['update_settings'])) {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $email = sanitize($_POST['email']);
    $address = sanitize($_POST['address']);
    $logo = sanitize($_POST['logo_url']);

    db_query("UPDATE company_settings SET name = ?, phone = ?, email = ?, address = ?, logo_url = ? WHERE id = 1", 
             [$name, $phone, $email, $address, $logo]);
    
    log_activity($_SESSION['user_id'], "Updated company settings.");
    redirect('modules/admin/settings.php', 'Settings updated successfully.');
}

$settings = get_company_settings();
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Company Profile Settings</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo $settings['name']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Logo URL</label>
                            <input type="text" name="logo_url" class="form-control" value="<?php echo $settings['logo_url']; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo $settings['phone']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Official Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo $settings['email']; ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Business Address</label>
                        <textarea name="address" class="form-control" rows="3"><?php echo $settings['address']; ?></textarea>
                    </div>
                    <hr>
                    <div class="text-end">
                        <button type="submit" name="update_settings" class="btn btn-primary px-5">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
