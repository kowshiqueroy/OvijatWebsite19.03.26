<?php
require_once 'config.php';
$error_msg = "";
$username_val = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_verify()) { $error_msg = "Invalid request. Please try again."; goto render; }
    $username = htmlspecialchars($_POST['username']);
    $password = $_POST['password'];
    $username_val = $username;

    $stmt = $conn->prepare("SELECT id, username, password, status, company_id, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            if ($row['status'] == 0) {
                $error_msg = "Your account is inactive. Please contact the administrator.";
                $stmt->close();
                goto render;
            }
            $last_login = date('Y-m-d H:i:s');
            $stmt->close();
            $stmt = $conn->prepare("UPDATE users SET last_login = ? WHERE id = ?");
            $stmt->bind_param("si", $last_login, $row['id']);
            $stmt->execute();
            $stmt->close();
            $_SESSION['role']       = $row['role'];
            $_SESSION['company_id'] = $row['company_id'];
            $_SESSION['status']     = $row['status'];
            $_SESSION['user_id']    = $row['id'];
            $_SESSION['username']   = $row['username'];
            header("Location: " . $row['role']);
            exit;
        } else {
            $error_msg = "Invalid password.";
        }
    } else {
        $error_msg = "Invalid username.";
    }
    $stmt->close();
}

if (isset($_GET['error'])) {
    $success_msg = htmlspecialchars($_GET['error']);
}

render:
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login &mdash; <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:      #10b981;
            --primary-dark: #059669;
            --warning:      #f59e0b;
            --danger:       #ef4444;
            --dark:         #1f2937;
            --bg-gradient:  linear-gradient(135deg, #e0f7fa 0%, #fffde7 50%, #fee2e2 100%);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            margin: 20px;
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 24px;
            padding: 40px 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            animation: floatUp 0.6s ease-out forwards;
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--warning), var(--danger));
        }
        @keyframes floatUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .brand-header { text-align: center; margin-bottom: 30px; }
        .logo-icon {
            font-size: 2.5rem;
            color: var(--warning);
            margin-bottom: 10px;
            display: inline-block;
            filter: drop-shadow(0 4px 6px rgba(245,158,11,0.3));
        }
        .brand-name { font-size: 1.8rem; font-weight: 700; letter-spacing: -0.5px; }
        .input-group { position: relative; margin-bottom: 20px; }
        .input-icon {
            position: absolute;
            left: 15px; top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            transition: 0.3s;
        }
        .form-control {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 2px solid transparent;
            background: rgba(255,255,255,0.6);
            border-radius: 12px;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s;
            font-family: inherit;
        }
        .form-control:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16,185,129,0.15);
        }
        .form-control:focus + .input-icon { color: var(--primary); }
        .toggle-password {
            position: absolute;
            right: 15px; top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ca3af;
        }
        .toggle-password:hover { color: var(--dark); }
        .btn-login {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 5px 15px rgba(16,185,129,0.3);
            margin-top: 10px;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,185,129,0.4); }
        .btn-login:active { transform: translateY(0); }
        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            animation: shake 0.4s ease-in-out;
        }
        .alert-error  { background: rgba(239,68,68,0.1);  color: var(--danger);       border: 1px solid rgba(239,68,68,0.2); }
        .alert-success{ background: rgba(16,185,129,0.1); color: var(--primary-dark); border: 1px solid rgba(16,185,129,0.2); }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25%     { transform: translateX(-5px); }
            75%     { transform: translateX(5px); }
        }
        .card-footer {
            margin-top: 30px;
            text-align: center;
            border-top: 1px solid rgba(0,0,0,0.05);
            padding-top: 20px;
        }
        .dev-details { font-size: 0.75rem; color: #666; line-height: 1.5; }
        .dev-details strong { color: var(--dark); }
        .dev-tag {
            display: inline-block;
            margin-top: 5px;
            background: var(--dark);
            color: #fff;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="brand-header">
        <i class="fa-solid fa-building logo-icon"></i>
        <div class="brand-name"><?= APP_NAME ?> <span style="color:var(--primary)">App</span></div>
        <div style="font-size:0.9rem;color:#666;margin-top:5px;">Enterprise Information System</div>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-check-circle"></i> <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
        <?= csrf_field() ?>
        <div class="input-group">
            <input type="text" name="username" class="form-control" placeholder="Username"
                   value="<?= htmlspecialchars($username_val) ?>" required autocomplete="username">
            <i class="fa-solid fa-user input-icon"></i>
        </div>
        <div class="input-group">
            <input type="password" name="password" id="password" class="form-control"
                   placeholder="Password" required autocomplete="current-password">
            <i class="fa-solid fa-lock input-icon"></i>
            <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
        </div>
        <button type="submit" class="btn-login">
            LOG IN <i class="fa-solid fa-arrow-right-to-bracket" style="margin-left:5px;"></i>
        </button>
        <div style="text-align:center;margin-top:15px;">
            <a href="?error=Contact 01632950179" style="font-size:0.8rem;color:#f82626;text-decoration:none;">Need Help?</a>
        </div>
    </form>

    <div class="card-footer">
        <div class="dev-details" style="margin-bottom:10px;">
            <a href="privacy.php" style="color:#666;text-decoration:none;font-size:0.8rem;">Privacy Policy</a>
            &nbsp;|&nbsp;
            <span style="color:#666;font-size:0.8rem;">Internal Use Only</span>
        </div>
        <div class="dev-details">
            Designed &amp; Developed by<br>
            <strong><?= DEVELOPER_NAME ?></strong>
        </div>
        <div class="dev-tag">V <?= VERSION_NAME ?></div>
    </div>
</div>

<script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const pwd = document.getElementById('password');
        pwd.type = pwd.type === 'password' ? 'text' : 'password';
        this.classList.toggle('fa-eye-slash');
        this.classList.toggle('fa-eye');
    });
</script>
</body>
</html>
