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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --bg-gradient: linear-gradient(135deg, #0d6efd 0%, #002d72 100%);
        }
        body { 
            background: #f4f7f6;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
        }
        .login-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            transition: transform 0.3s ease;
        }
        .login-header {
            background: var(--bg-gradient);
            padding: 40px 20px;
            text-align: center;
            color: #fff;
        }
        .login-header img {
            max-width: 100px;
            height: auto;
            margin-bottom: 15px;
            filter: drop-shadow(0 5px 15px rgba(0,0,0,0.2));
        }
        .login-header h3 {
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .login-header p {
            font-size: 0.9rem;
            opacity: 0.8;
            margin: 0;
        }
        .login-body {
            padding: 40px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e1e1e1;
            background-color: #f9f9f9;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
            background-color: #fff;
        }
        .btn-login {
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: var(--bg-gradient);
            border: none;
            margin-top: 10px;
            transition: opacity 0.3s ease;
        }
        .btn-login:hover {
            opacity: 0.9;
        }
        .input-group-text {
            background-color: #f9f9f9;
            border-radius: 10px 0 0 10px;
            border-right: none;
            color: #999;
        }
        .form-control {
            border-left: none;
        }
        .footer-text {
            text-align: center;
            margin-top: 25px;
            font-size: 0.8rem;
            color: #888;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <?php if ($company['logo_url']): ?>
            <img src="<?php echo $company['logo_url']; ?>" alt="Logo">
        <?php else: ?>
            <div class="mb-3">
                <i class="fas fa-truck-loading fa-3x"></i>
            </div>
        <?php endif; ?>
        <h3><?php echo $company['name']; ?></h3>
        <p>Distribution Management System</p>
    </div>
    
    <div class="login-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger border-0 shadow-sm small">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php csrf_field(); ?>
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Enter your username" required autofocus>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-login shadow">
                Sign In <i class="fas fa-arrow-right ms-2"></i>
            </button>
        </form>

        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> <?php echo $company['name']; ?> | Powered by <strong>sohojweb</strong>
        </div>
    </div>
</div>

</body>
</html>
