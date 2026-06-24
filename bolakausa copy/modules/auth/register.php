<?php
/**
 * Registration Module - Premium Redesign
 */

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($username && $email && $password) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "Username or Email already registered in our system.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'wholesale_user', 'pending')");
            if ($stmt->execute([$username, $email, $hashed_password, $full_name, $phone])) {
                $success = "Application successfully submitted. Our operations team will verify your credentials and activate your account shortly.";
            } else {
                $error = "An unexpected error occurred. Please try again.";
            }
        }
    } else {
        $error = "Username, Email, and Password fields are strictly required.";
    }
}
?>

<div style="max-width: 550px; margin: 4.5rem auto; width: 100%;">
    <div class="card" style="padding: 0; border: 1px solid var(--border-light); box-shadow: 0 20px 40px -10px rgba(15,23,42,0.15); overflow: hidden;">
        <!-- Card Header Gradient -->
        <div style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); padding: 3rem 2.25rem 2.5rem; text-align: center; color: white; position: relative;">
            <div style="width: 58px; height: 58px; background: rgba(16, 185, 129, 0.12); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; border: 1px solid rgba(16, 185, 129, 0.25);">
                <i class="fas fa-handshake" style="font-size: 1.35rem; color: var(--primary);"></i>
            </div>
            <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.65rem; margin-bottom: 0.35rem; letter-spacing: -0.5px;">Wholesale Registration</h2>
            <p style="color: #94a3b8; font-size: 0.825rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em;">Apply for a B2B Partnership Account</p>
        </div>
        
        <!-- Form body -->
        <div style="padding: 2.25rem;">
            <?php if ($error): ?>
                <div style="background: rgba(244, 63, 94, 0.08); color: #991b1b; padding: 0.95rem 1.25rem; border-radius: 8px; margin-bottom: 1.75rem; font-size: 0.8rem; border: 1px solid rgba(244, 63, 94, 0.15); font-weight: 600;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 6px;"></i> <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="text-align: center; padding: 1.5rem 0;">
                    <i class="fas fa-check-circle" style="font-size: 3.5rem; color: var(--primary); margin-bottom: 1.25rem; display: block; filter: drop-shadow(0 0 10px var(--primary-glow));"></i>
                    <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 0.5rem;">Application Logged</h3>
                    <p style="font-size: 0.875rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 2rem;">
                        <?php echo e($success); ?>
                    </p>
                    <a href="/bolakausa/login" class="btn btn-green" style="width: 100%; justify-content: center; padding: 0.95rem; border-radius: 8px;">
                        Return to Partner Portal
                    </a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.5rem;">
                        <div class="form-group">
                            <label style="font-size: 0.775rem; text-transform: uppercase; letter-spacing: 0.025em; color: var(--secondary);">Partner Username *</label>
                            <input type="text" name="username" placeholder="e.g. jdoe_grocery" required style="border-radius: 8px; font-size: 0.9rem;">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.775rem; text-transform: uppercase; letter-spacing: 0.025em; color: var(--secondary);">Corporate Email *</label>
                            <input type="email" name="email" placeholder="e.g. jdoe@company.com" required style="border-radius: 8px; font-size: 0.9rem;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 0.775rem; text-transform: uppercase; letter-spacing: 0.025em; color: var(--secondary);">Primary Contact Person Name</label>
                        <input type="text" name="full_name" placeholder="e.g. John Doe" style="border-radius: 8px; font-size: 0.9rem;">
                    </div>

                    <div class="form-group">
                        <label style="font-size: 0.775rem; text-transform: uppercase; letter-spacing: 0.025em; color: var(--secondary);">Direct Telephone / Mobile</label>
                        <input type="text" name="phone" placeholder="e.g. +1 (555) 019-2834" style="border-radius: 8px; font-size: 0.9rem;">
                    </div>

                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label style="font-size: 0.775rem; text-transform: uppercase; letter-spacing: 0.025em; color: var(--secondary);">Security Password *</label>
                        <input type="password" name="password" placeholder="••••••••" required style="border-radius: 8px; font-size: 0.9rem;">
                    </div>

                    <button type="submit" class="btn btn-green" style="width: 100%; justify-content: center; padding: 0.95rem; border-radius: 8px; font-size: 0.95rem; font-family: 'Plus Jakarta Sans', sans-serif; box-shadow: 0 10px 15px -3px var(--primary-glow);">
                        Submit Application <i class="fas fa-paper-plane" style="margin-left: 6px; font-size: 0.75rem;"></i>
                    </button>
                </form>

                <div style="text-align: center; margin-top: 2rem; border-top: 1px solid var(--border-light); padding-top: 1.5rem;">
                    <p style="font-size: 0.825rem; color: var(--text-muted); font-weight: 500;">
                        Already have a partner account? <a href="/bolakausa/login" style="color: var(--accent); font-weight: 800; text-decoration: none;">Login Here</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
