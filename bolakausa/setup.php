<?php
/**
 * One-time Setup Script
 * PIN: 5877
 */

session_start();
$setup_pin = "5877";

$step = $_SESSION['setup_step'] ?? 1;
$error = '';
$success = '';

// Logout / Reset
if (isset($_GET['reset'])) {
    session_destroy();
    header("Location: setup.php");
    exit;
}

// Handle PIN Verification
if (isset($_POST['verify_pin'])) {
    if ($_POST['pin'] === $setup_pin) {
        $_SESSION['setup_auth'] = true;
        $step = 2;
    } else {
        $error = "Invalid PIN.";
    }
}

// Ensure Auth for further steps
if ($step > 1 && !isset($_SESSION['setup_auth'])) {
    $step = 1;
}

// Handle DB Config & Setup
if (isset($_POST['run_setup']) && isset($_SESSION['setup_auth'])) {
    $host = $_POST['db_host'];
    $dbname = $_POST['db_name'];
    $user = $_POST['db_user'];
    $pass = $_POST['db_pass'];

    try {
        // 1. Test Connection
        $dsn = "mysql:host=$host;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // 2. Create Database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname` ");

        // 3. Run SQL Schema
        if (file_exists('database.sql')) {
            $sql = file_get_contents('database.sql');
            $pdo->exec($sql);
            
            // 4. Insert Default Users
            $default_users = [
                ['admin', 'admin@bolakausa.com', 'admin123', 'admin', 'active', 'System Admin'],
                ['manager', 'manager@bolakausa.com', 'manager123', 'manager', 'active', 'Operations Manager'],
                ['warehouse', 'warehouse@bolakausa.com', 'warehouse123', 'warehouse', 'active', 'Logistics Staff'],
                ['viewer', 'viewer@bolakausa.com', 'viewer123', 'viewer', 'active', 'System Auditor'],
                ['user', 'user@bolakausa.com', 'user123', 'wholesale_user', 'active', 'Sample Wholesale User']
            ];

            $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, role, status, full_name) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($default_users as $u) {
                $hashed = password_hash($u[2], PASSWORD_BCRYPT);
                $stmt->execute([$u[0], $u[1], $hashed, $u[3], $u[4], $u[5]]);
            }

            $success = "Database created, schema imported, and default users created successfully.";
        } else {
            $error = "database.sql file not found.";
        }

        // 5. Create config/database.php
        $config_content = "<?php
/**
 * Auto-generated Database Configuration
 */
\$host = '$host';
\$db   = '$dbname';
\$user = '$user';
\$pass = '$pass';
\$charset = 'utf8mb4';

\$dsn = \"mysql:host=\$host;dbname=\$db;charset=\$charset\";
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     \$pdo = new PDO(\$dsn, \$user, \$pass, \$options);
} catch (\\PDOException \$e) {
     die(\"Database Connection Failed: \" . \$e->getMessage());
}
";
        if (!is_dir('config')) mkdir('config');
        file_put_contents('config/database.php', $config_content);
        
        $step = 3;

    } catch (PDOException $e) {
        $error = "Setup Failed: " . $e->getMessage();
    }
}

$_SESSION['setup_step'] = $step;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Bolakausa - Deployment Setup</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; display: flex; justify-content: center; padding-top: 50px; }
        .setup-container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 450px; }
        .error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .success { color: #155724; background: #d4edda; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #0056b3; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-left: 5px solid #ffeeba; font-size: 0.9em; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="setup-container">
        <h2 style="text-align: center;">System Setup</h2>

        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>

        <?php if ($step == 1): ?>
            <form method="POST">
                <p>Enter Setup PIN to proceed:</p>
                <input type="password" name="pin" placeholder="Enter 4-digit PIN" required autofocus>
                <button type="submit" name="verify_pin">Verify PIN</button>
            </form>
        <?php endif; ?>

        <?php if ($step == 2): ?>
            <form method="POST">
                <h3>Database Configuration</h3>
                <p>Ensure your MySQL server is running.</p>
                <input type="text" name="db_host" value="localhost" placeholder="DB Host (e.g. localhost)" required>
                <input type="text" name="db_name" value="bolakausa_db" placeholder="Database Name" required>
                <input type="text" name="db_user" value="root" placeholder="DB Username" required>
                <input type="password" name="db_pass" placeholder="DB Password">
                
                <div class="warning">
                    Running this will create the database and import <strong>database.sql</strong>. It will also overwrite your <strong>config/database.php</strong>.
                </div>
                
                <button type="submit" name="run_setup">Install System</button>
            </form>
        <?php endif; ?>

        <?php if ($step == 3): ?>
            <div style="text-align: center;">
                <h3 style="color: green;">✔ Setup Complete!</h3>
                <p>Your database is ready and default users have been created.</p>
                
                <div style="text-align: left; background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0; font-size: 0.9em; border: 1px solid #e2e8f0;">
                    <strong>Default Credentials:</strong><br>
                    <ul style="padding-left: 20px; margin-top: 10px;">
                        <li><strong>Admin:</strong> admin / admin123</li>
                        <li><strong>Manager:</strong> manager / manager123</li>
                        <li><strong>Wholesale User:</strong> user / user123</li>
                    </ul>
                </div>
                
                <div class="warning" style="border-left-color: #dc3545; color: #721c24; background: #f8d7da;">
                    <strong>CRITICAL SECURITY STEP:</strong><br>
                    Please DELETE the <strong>setup.php</strong> file from your server immediately!
                </div>
                
                <a href="login" style="display: block; margin-top: 20px; text-decoration: none; color: #007bff;">Go to Login Page &rarr;</a>
                <br>
                <a href="setup.php?reset=1" style="font-size: 0.8em; color: #999;">Start Over</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
