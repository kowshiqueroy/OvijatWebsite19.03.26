<?php
/**
 * Login Page
 * Modern, Amber-accented authentication.
 */
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/AuthManager.php';

$auth = new AuthManager();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please refresh.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            $result = $auth->login($email, $password);
            if ($result['success']) {
                $returnUrl = $_GET['return'] ?? 'account';
                if ($result['user']['role'] === 'admin') {
                    header("Location: admin_stealth_zone/dashboard.php");
                } else {
                    header("Location: " . $returnUrl);
                }
                exit;
            } else { $error = $result['message']; }
        }
    }
}

$pageTitle = 'Welcome Back';
include 'templates/header.php';
?>

<div style="max-width: 450px; margin: 6rem auto;">
    <div style="background: white; padding: 3rem; border-radius: 30px; box-shadow: var(--card-shadow); border: 1px solid var(--border);">
        <h1 style="font-weight: 800; color: var(--primary); text-align: center; margin-bottom: 2rem;">Welcome Back</h1>
        
        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="name@example.com">
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn-harvest" style="width: 100%; padding: 1.2rem;">Sign In</button>
        </form>

        <div style="text-align: center; margin-top: 2rem; font-weight: 700; font-size: 0.9rem; opacity: 0.6;">
            New to Sobjiwali? <a href="register" style="color: var(--primary); text-decoration: none;">Create an account</a>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
