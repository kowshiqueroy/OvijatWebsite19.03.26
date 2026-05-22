<?php
/**
 * Registration Page
 * Embedded within a modern login/register hybrid UI.
 */
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/AuthManager.php';

$auth = new AuthManager();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'retail';
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $phone = $_POST['phone'] ?? '';

        if (empty($email) || empty($password) || empty($first_name)) {
            $error = 'Please fill in all required fields.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $profileData = ['first_name' => $first_name, 'last_name' => $last_name, 'phone' => $phone];
            
            try {
                if ($role === 'wholesale') {
                    $auth->registerWholesale($email, $password, $profileData);
                    $success = 'Wholesale application submitted for approval!';
                } else {
                    $auth->registerRetail($email, $password, $profileData);
                    $success = 'Account created! You can now sign in.';
                }
            } catch (Exception $e) { $error = $e->getMessage(); }
        }
    }
}

$pageTitle = 'Join the Harvest';
include 'templates/header.php';
?>

<div style="max-width: 500px; margin: 4rem auto;">
    <div style="background: white; padding: 3rem; border-radius: 30px; box-shadow: var(--card-shadow); border: 1px solid var(--border);">
        <h1 style="font-weight: 800; color: var(--primary); text-align: center; margin-bottom: 2rem;">Create Account</h1>
        
        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" style="text-align:center;">
                <?php echo $success; ?><br><br>
                <a href="login" class="btn-harvest" style="display:inline-block; text-decoration:none;">Go to Login</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" required></div>
                    <div class="form-group"><label>Last Name</label><input type="text" name="last_name"></div>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required placeholder="name@example.com">
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone">
                </div>

                <div class="form-group">
                    <label>Account Type</label>
                    <select name="role" style="background: var(--bg);">
                        <option value="retail">Retail Customer</option>
                        <option value="wholesale">Wholesale Partner (Pending Approval)</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group"><label>Password *</label><input type="password" name="password" required></div>
                    <div class="form-group"><label>Confirm *</label><input type="password" name="confirm_password" required></div>
                </div>

                <button type="submit" class="btn-harvest" style="width: 100%; margin-top: 1.5rem; padding: 1.2rem;">Start Harvesting</button>
            </form>

            <div style="text-align: center; margin-top: 2rem; font-weight: 700; font-size: 0.9rem; opacity: 0.6;">
                Already a member? <a href="login" style="color: var(--primary); text-decoration: none;">Login here</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
