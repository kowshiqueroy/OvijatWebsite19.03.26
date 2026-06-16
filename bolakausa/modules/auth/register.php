<?php
/**
 * Registration Module - Modernized
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
            $error = "Username or Email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'wholesale_user', 'pending')");
            if ($stmt->execute([$username, $email, $hashed_password, $full_name, $phone])) {
                $success = "Registration successful! Your account is pending admin approval.";
            } else {
                $error = "An error occurred. Please try again.";
            }
        }
    } else {
        $error = "Username, Email, and Password are required.";
    }
}
?>

<div style="max-width: 600px; margin: 4rem auto;">
    <div class="card" style="padding: 3rem; border-top: none; position: relative; overflow: hidden;">
        <!-- Top accent line -->
        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 6px; background: linear-gradient(90deg, var(--primary), #3b82f6);"></div>
        
        <div style="text-align: center; margin-bottom: 2.5rem;">
            <div style="width: 72px; height: 72px; background: rgba(16, 185, 129, 0.1); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; border: 1px solid rgba(16, 185, 129, 0.2);">
                <i class="fas fa-handshake" style="font-size: 1.75rem; color: var(--primary);"></i>
            </div>
            <h2 style="font-weight: 900; color: var(--secondary); margin-bottom: 0.5rem; font-size: 2rem; letter-spacing: -0.5px;">Partner Application</h2>
            <p style="color: var(--text-muted); font-size: 0.9375rem; font-weight: 500;">Register for a Wholesale Partnership Account</p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(244, 63, 94, 0.1); color: var(--accent); padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 2rem; font-size: 0.875rem; border: 1px solid rgba(244, 63, 94, 0.2); font-weight: 600;">
                <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i> <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: rgba(16, 185, 129, 0.1); color: #166534; padding: 2.5rem; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.2); text-align: center;">
                <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 1.5rem; display: block; color: var(--primary);"></i>
                <div style="font-weight: 800; font-size: 1.25rem; margin-bottom: 0.75rem; color: var(--secondary);">Application Received</div>
                <p style="font-size: 0.9375rem; color: var(--text-muted); margin-bottom: 2rem; line-height: 1.6;"><?php echo e($success); ?></p>
                <a href="/bolakausa/login" class="btn btn-blue" style="width: 100%; justify-content: center; padding: 1rem; border-radius: 14px;">Return to Portal</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 700; color: var(--secondary); margin-bottom: 0.5rem; display: block;">Username *</label>
                        <input type="text" name="username" placeholder="jdoe_partner" required style="width: 100%;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 700; color: var(--secondary); margin-bottom: 0.5rem; display: block;">Business Email *</label>
                        <input type="email" name="email" placeholder="john@company.com" required style="width: 100%;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="font-weight: 700; color: var(--secondary); margin-bottom: 0.5rem; display: block;">Contact Person</label>
                    <input type="text" name="full_name" placeholder="John Doe" style="width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="font-weight: 700; color: var(--secondary); margin-bottom: 0.5rem; display: block;">Phone Number</label>
                    <input type="text" name="phone" placeholder="+1 234 567 890" style="width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 2.5rem;">
                    <label style="font-weight: 700; color: var(--secondary); margin-bottom: 0.5rem; display: block;">Security Password *</label>
                    <input type="password" name="password" placeholder="••••••••" required style="width: 100%;">
                </div>

                <button type="submit" class="btn btn-green" style="width: 100%; justify-content: center; padding: 1.15rem; border-radius: 16px; font-size: 1rem;">
                    Submit Application <i class="fas fa-paper-plane" style="margin-left: 8px; font-size: 0.8rem;"></i>
                </button>
            </form>

            <div style="text-align: center; margin-top: 2.5rem; border-top: 1px solid var(--glass-border); padding-top: 2rem;">
                <p style="font-size: 0.875rem; color: var(--text-muted); font-weight: 500;">
                    Already registered? <a href="/bolakausa/login" style="color: var(--primary); font-weight: 800; text-decoration: none;">Login Here</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
