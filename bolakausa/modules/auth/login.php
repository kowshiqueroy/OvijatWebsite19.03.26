<?php
/**
 * Login Module - Modernized
 */

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $error = "Your account has been suspended. Please contact support.";
            } else {
                // Set Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_status'] = $user['status'];

                // Log Login
                require_once 'includes/auth_helper.php';
                log_action($pdo, $user['id'], 'User Login');

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: /bolakausa/admin');
                } elseif ($user['role'] === 'manager') {
                    header('Location: /bolakausa/manager');
                } else {
                    header('Location: /bolakausa/home');
                }
                exit;
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<div style="max-width: 480px; margin: 6rem auto;">
    <div class="card" style="padding: 3rem; border-top: none; position: relative; overflow: hidden;">
        <!-- Top accent line -->
        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 6px; background: linear-gradient(90deg, var(--primary), #3b82f6);"></div>
        
        <div style="text-align: center; margin-bottom: 2.5rem;">
            <div style="width: 72px; height: 72px; background: rgba(16, 185, 129, 0.1); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; border: 1px solid rgba(16, 185, 129, 0.2);">
                <i class="fas fa-shield-alt" style="font-size: 1.75rem; color: var(--primary);"></i>
            </div>
            <h2 style="font-weight: 900; color: var(--secondary); margin-bottom: 0.5rem; font-size: 2rem; letter-spacing: -0.5px;">Portal Access</h2>
            <p style="color: var(--text-muted); font-size: 0.9375rem; font-weight: 500;">Authorized <?php echo e(get_setting($pdo, 'company_name', 'Bolakausa')); ?> Partners Only</p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(244, 63, 94, 0.1); color: var(--accent); padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 2rem; font-size: 0.875rem; border: 1px solid rgba(244, 63, 94, 0.2); font-weight: 600;">
                <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i> <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label style="font-weight: 700; color: var(--secondary); margin-bottom: 0.75rem;">Identity</label>
                <div style="position: relative;">
                    <i class="fas fa-user-circle" style="position: absolute; left: 1.25rem; top: 1.15rem; color: var(--text-muted); font-size: 1.1rem;"></i>
                    <input type="text" name="username" placeholder="partner_handle" required autofocus style="padding-left: 3.25rem; width: 100%;">
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 2rem;">
                <label style="font-weight: 700; color: var(--secondary); margin-bottom: 0.75rem;">Access Key</label>
                <div style="position: relative;">
                    <i class="fas fa-fingerprint" style="position: absolute; left: 1.25rem; top: 1.15rem; color: var(--text-muted); font-size: 1.1rem;"></i>
                    <input type="password" name="password" placeholder="••••••••" required style="padding-left: 3.25rem; width: 100%;">
                </div>
            </div>
            
            <button type="submit" class="btn btn-green" style="width: 100%; justify-content: center; padding: 1.15rem; border-radius: 16px; font-size: 1rem;">
                Authenticate <i class="fas fa-chevron-right" style="margin-left: 8px; font-size: 0.8rem;"></i>
            </button>
        </form>

        <div style="text-align: center; margin-top: 2.5rem; border-top: 1px solid var(--glass-border); padding-top: 2rem;">
            <p style="font-size: 0.875rem; color: var(--text-muted); font-weight: 500;">
                New wholesale partner? <a href="/bolakausa/register" style="color: var(--primary); font-weight: 800; text-decoration: none;">Apply for Account</a>
            </p>
        </div>
    </div>
</div>
