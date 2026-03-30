<?php
include_once 'config.php'; // Include your database connection and configuration file
$msg = "Please sign in to continue";
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            session_start();
            $_SESSION['user_id'] = $row['id'];
            header("Location: index.php");
            exit;
        } else {
            $msg = "Invalid password.";
        }
    } else {
        $msg = "Invalid username.";
    }
    $stmt->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $company_name; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        body {
            background: linear-gradient(to right, #141E30, rgb(59, 167, 59));
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #cbdaf0ff;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background-color: #1C1C1C;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }
        .login-card {
            width: 100%;
            padding: 20px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            font-size: 28px;
            color: #f0f2f5;
        }
        .login-form {
            width: 100%;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            transition: border-color 0.3s;
        }
        .login-button {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            background-color: rgb(255, 230, 0);
            border: none;
            transition: background-color 0.3s;
        }
        .login-button:hover {
            background-color: #0056b3;
        }
        @media (max-width: 600px) {
            .login-container {
                width: 100%;
                max-width: unset;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><?php echo $company_name; ?></h1>
                <p><?php echo $msg; ?></p>
            </div>
            <form class="login-form" method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="login-button">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>
