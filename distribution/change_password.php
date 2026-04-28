<?php
require_once 'templates/header.php';
check_login();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass === $confirm_pass) {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        db_query("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?", [$hashed, $_SESSION['user_id']]);
        $_SESSION['force_password_change'] = 0;
        log_activity($_SESSION['user_id'], "Password changed.");
        redirect('index.php', 'Password updated successfully.');
    } else {
        $error = "Passwords do not match.";
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-4 mt-5">
        <div class="card shadow">
            <div class="card-body">
                <h4 class="card-title text-center mb-4">Change Password</h4>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
