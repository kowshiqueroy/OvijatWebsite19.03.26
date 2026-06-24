<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$company = get_company_settings();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        die("CSRF Token Validation Failed.");
    }
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    $user = fetch_one("SELECT * FROM users WHERE username = ?", [$username]);

    if ($user && password_verify($password, $user['password'])) {
        if ($user['is_active'] == 0 || $user['isDelete'] == 1) {
            $error = "Account is inactive or deleted.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['force_password_change'] = $user['force_password_change'];

            db_query("UPDATE users SET last_active = NOW() WHERE id = ?", [$user['id']]);
            log_activity($user['id'], "User logged in.");

            redirect('index.php');
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo $company['name']; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *{box-sizing:border-box;}
        body {
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            padding: 16px;
        }
        .login-wrap {
            width: 100%;
            max-width: 420px;
        }
        .login-brand {
            text-align: center;
            margin-bottom: 24px;
        }
        .login-brand-icon {
            width: 56px; height: 56px;
            background: linear-gradient(135deg,#6366f1,#8b5cf6);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 22px;
            margin-bottom: 12px;
            box-shadow: 0 8px 24px rgba(99,102,241,.35);
        }
        .login-brand h2 {
            font-size: 20px;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 2px;
        }
        .login-brand p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            padding: 32px 28px;
            border: 1px solid #e2e8f0;
        }
        .form-label {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            margin-bottom: 5px;
        }
        .input-group-text {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #94a3b8;
        }
        .form-control {
            border-color: #e2e8f0;
            background: #f8fafc;
            font-size: 14px;
            padding: 10px 12px;
            border-radius: 0 8px 8px 0 !important;
        }
        .input-group .input-group-text { border-radius: 8px 0 0 8px; }
        .form-control:focus {
            background: #fff;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }
        .btn-login {
            background: linear-gradient(135deg,#6366f1,#8b5cf6);
            border: none;
            border-radius: 10px !important;
            padding: 12px;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: .03em;
            color: #fff;
            transition: opacity .2s;
            width: 100%;
        }
        .btn-login:hover { opacity: .88; color: #fff; }
        .footer-text {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #94a3b8;
        }
        .alert { border-radius: 10px !important; font-size: 13px; }
    </style>
</head>
<body>

<div class="login-wrap">
    <div class="login-brand">
        <?php if (!empty($company['logo_url'])): ?>
            <?php $logo_path = filter_var($company['logo_url'], FILTER_VALIDATE_URL) || str_starts_with($company['logo_url'], 'data:') ? $company['logo_url'] : BASE_URL . ltrim($company['logo_url'], '/'); ?>
            <img src="<?php echo $logo_path; ?>" alt="Logo" style="height:52px;margin-bottom:10px;border-radius:10px;">
        <?php else: ?>
            <div class="login-brand-icon">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
        <?php endif; ?>
        <h2><?php echo htmlspecialchars($company['name']); ?></h2>
        <p>Distribution Management System</p>
    </div>

    <div class="login-card">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php csrf_field(); ?>
            <div class="mb-4">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus autocomplete="username">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
                </div>
            </div>
            <button type="submit" class="btn btn-login">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
            </button>
        </form>

        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($company['name']); ?> &mdash; Powered by <strong>sohojweb</strong>
        </div>
    </div>
</div>

</body>
</html>
