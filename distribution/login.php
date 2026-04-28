<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .login-container { max-width: 400px; margin-top: 100px; }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="card shadow">
            <div class="card-body p-5">
                <h3 class="text-center mb-4"><?php echo APP_NAME; ?></h3>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
