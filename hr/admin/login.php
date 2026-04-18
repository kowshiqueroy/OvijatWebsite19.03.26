<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if (isset($_GET['timeout'])) {
    $error = 'Your session has expired due to inactivity. Please log in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (adminLogin($username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}

$companyName = getSetting('company_name') ?? 'HR System';
$companyLogo = getSetting('company_logo') ?? '';
$companyTagline = getSetting('company_tagline') ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($companyName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --bg-dark: #0f172a;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--bg-dark) 0%, #1e293b 50%, #0f172a 100%);
            padding: 20px;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.4);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 40px 30px 50px;
            text-align: center;
            position: relative;
        }
        .login-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 40px;
            background: #fff;
            border-radius: 20px 20px 0 0;
        }
        .login-logo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        .login-logo i {
            font-size: 2rem;
            color: #fff;
        }
        .login-header h3 {
            color: #fff;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .login-header p {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            margin: 0;
        }
        .login-body {
            padding: 30px;
            position: relative;
            z-index: 1;
        }
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }
        .form-control {
            padding: 14px 16px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            z-index: 10;
        }
        .input-icon .form-control {
            padding-left: 45px;
        }
        .btn-login {
            padding: 14px;
            font-weight: 600;
            font-size: 1rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #6b7280;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .back-link a:hover {
            color: var(--primary);
        }
        @media (max-width: 480px) {
            .login-card {
                border-radius: 16px;
            }
            .login-header {
                padding: 30px 20px 40px;
            }
            .login-body {
                padding: 20px;
            }
            .login-logo {
                width: 60px;
                height: 60px;
            }
            .login-logo i {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <?php if (!empty($companyLogo)): ?>
                <div class="login-logo">
                    <img src="../uploads/<?php echo htmlspecialchars($companyLogo); ?>" style="width:40px;height:40px;object-fit:contain;border-radius:50%;">
                </div>
            <?php else: ?>
                <div class="login-logo">
                    <i class="bi bi-building"></i>
                </div>
            <?php endif; ?>
            <h3><?php echo htmlspecialchars($companyName); ?></h3>
            <p><?php echo htmlspecialchars($companyTagline) ?: 'Employee Management'; ?></p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-icon">
                        <i class="bi bi-person"></i>
                        <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-icon">
                        <i class="bi bi-lock"></i>
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Login
                </button>
            </form>
            
            <div class="back-link">
                <a href="../public/profile.php"><i class="bi bi-arrow-left me-1"></i> Back to Profile</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>