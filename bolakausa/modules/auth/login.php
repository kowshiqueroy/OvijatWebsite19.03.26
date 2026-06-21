<?php
/**
 * Login Module - Premium Redesign
 */

$error = '';

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'suspended') {
        $error = "Your wholesale account has been suspended. Please contact operations support.";
    } elseif ($_GET['error'] === 'account_deleted') {
        $error = "Your account has been deleted.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $error = "Your wholesale account has been suspended. Please reach out to operations support.";
            } else {
                // Set Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_status'] = $user['status'];

                // Log Login
                log_action($pdo, $user['id'], 'User Login');

                // Redirect based on role
                if (in_array($user['role'], ['admin', 'editor'])) {
                    header('Location: ' . BASE_URL . 'admin');
                } elseif ($user['role'] === 'manager') {
                    header('Location: ' . BASE_URL . 'manager');
                } else {
                    header('Location: ' . BASE_URL . 'home');
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

<div style="max-width: 440px; margin: 5rem auto; width: 100%;">
    <div class="card" style="padding: 0; border: 1px solid var(--border-light); box-shadow: 0 20px 40px -10px rgba(15,23,42,0.15); overflow: hidden;">
        <!-- Card Header Gradient -->
        <div style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); padding: 3rem 2.25rem 2.5rem; text-align: center; color: white; position: relative;">
            <div style="width: 58px; height: 58px; background: rgba(16, 185, 129, 0.12); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; border: 1px solid rgba(16, 185, 129, 0.25);">
                <i class="fas fa-lock" style="font-size: 1.35rem; color: var(--primary);"></i>
            </div>
            <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.65rem; margin-bottom: 0.35rem; letter-spacing: -0.5px;">Partner Authentication</h2>
            <p style="color: #94a3b8; font-size: 0.825rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em;">Bolakausa Wholesale Network</p>
        </div>
        
        <!-- Form body -->
        <div style="padding: 2.25rem;">
            <?php if ($error): ?>
                <div style="background: rgba(244, 63, 94, 0.08); color: #991b1b; padding: 0.95rem 1.25rem; border-radius: 8px; margin-bottom: 1.75rem; font-size: 0.8rem; border: 1px solid rgba(244, 63, 94, 0.15); font-weight: 600;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 6px;"></i> <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label style="font-size: 0.775rem; text-transform: uppercase; letter-spacing: 0.025em; color: var(--secondary);">Partner Username</label>
                    <div style="position: relative;">
                        <i class="fas fa-user" style="position: absolute; left: 1rem; top: 0.85rem; color: var(--text-muted); font-size: 0.95rem;"></i>
                        <input type="text" name="username" placeholder="Enter username handle" required autofocus style="padding-left: 2.5rem; border-radius: 8px; font-size: 0.9rem;">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.75rem;">
                    <label style="font-size: 0.775rem; text-transform: uppercase; letter-spacing: 0.025em; color: var(--secondary);">Security Password</label>
                    <div style="position: relative;">
                        <i class="fas fa-key" style="position: absolute; left: 1rem; top: 0.85rem; color: var(--text-muted); font-size: 0.95rem;"></i>
                        <input type="password" name="password" placeholder="••••••••" required style="padding-left: 2.5rem; border-radius: 8px; font-size: 0.9rem;">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-green" style="width: 100%; justify-content: center; padding: 0.95rem; border-radius: 8px; font-size: 0.95rem; font-family: 'Plus Jakarta Sans', sans-serif; box-shadow: 0 10px 15px -3px var(--primary-glow);">
                    Authenticate <i class="fas fa-arrow-right" style="margin-left: 6px; font-size: 0.75rem;"></i>
                </button>
            </form>

            <div style="text-align: center; margin-top: 2rem; border-top: 1px solid var(--border-light); padding-top: 1.5rem;">
                <p style="font-size: 0.825rem; color: var(--text-muted); font-weight: 500;">
                    New business client? <a href="/bolakausa/register" style="color: var(--accent); font-weight: 800; text-decoration: none;">Submit Partnership Application</a>
                </p>
            </div>
        </div>
    </div>
</div>
