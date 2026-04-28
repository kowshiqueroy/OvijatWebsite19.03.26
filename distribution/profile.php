<?php
require_once 'templates/header.php';
check_login();

$user_id = $_SESSION['user_id'];
$user = fetch_one("SELECT * FROM users WHERE id = ?", [$user_id]);

$customer = null;
if ($user['role'] == ROLE_CUSTOMER) {
    $customer = fetch_one("SELECT * FROM customers WHERE user_id = ?", [$user_id]);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = sanitize($_POST['phone']);
    $name = sanitize($_POST['name'] ?? '');
    $address = sanitize($_POST['address'] ?? '');

    $conn = get_db_connection();
    $conn->begin_transaction();

    try {
        // Update Users Table
        db_query("UPDATE users SET phone = ? WHERE id = ?", [$phone, $user_id]);

        // Update Customers Table if applicable
        if ($user['role'] == ROLE_CUSTOMER) {
            db_query("UPDATE customers SET name = ?, phone = ?, address = ? WHERE user_id = ?", [$name, $phone, $address, $user_id]);
        }

        $conn->commit();
        log_activity($user_id, "Updated profile information.");
        redirect('profile.php', 'Profile updated successfully.');
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error updating profile: " . $e->getMessage();
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Profile Information</h5>
            </div>
            <div class="card-body p-4">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?php echo $user['username']; ?>" readonly disabled>
                        <small class="text-muted">Username cannot be changed.</small>
                    </div>

                    <?php if ($user['role'] == ROLE_CUSTOMER): ?>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo $customer['name']; ?>" required>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $user['phone']; ?>" required>
                    </div>

                    <?php if ($user['role'] == ROLE_CUSTOMER): ?>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3"><?php echo $customer['address']; ?></textarea>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">User Role</label>
                        <input type="text" class="form-control" value="<?php echo $user['role']; ?>" readonly disabled>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="change_password.php" class="btn btn-outline-warning">Change Password</a>
                        <button type="submit" class="btn btn-primary px-4">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
