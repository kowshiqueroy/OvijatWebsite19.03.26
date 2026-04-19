<?php
/**
 * Database Setup Page
 * Core PHP Employee Management System
 * Interactive setup with progress display and reset functionality
 */

require_once __DIR__ . '/db.php';

session_start();

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'] ?? '';
}

$message = '';
$messageType = 'success';
$steps = [];
$dbStatus = [];
$canConnect = false;

try {
    $conn = getDBConnection();
    
    // If connection failed, try to create the database first
    if (!$conn) {
        $tempConn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        if (!$tempConn->connect_error) {
            $tempConn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
            $tempConn->close();
            $conn = getDBConnection();
        }
    }

    if ($conn) {
        $canConnect = true;
        $result = $conn->query("SHOW TABLES");
        $existingTables = [];
        while ($row = $result->fetch_array()) {
            $existingTables[] = $row[0];
        }
        
        $dbStatus['tables'] = $existingTables;
        $dbStatus['table_count'] = count($existingTables);
        
        if (in_array('admin', $existingTables)) {
            $result = $conn->query("SELECT COUNT(*) as cnt FROM admin");
            $dbStatus['admin_count'] = $result->fetch_assoc()['cnt'];
        }
        
        if (in_array('employees', $existingTables)) {
            $result = $conn->query("SELECT COUNT(*) as cnt FROM employees");
            $dbStatus['employee_count'] = $result->fetch_assoc()['cnt'];
        }
        
        if (in_array('salary_sheets', $existingTables)) {
            $result = $conn->query("SELECT COUNT(*) as cnt FROM salary_sheets");
            $dbStatus['salary_count'] = $result->fetch_assoc()['cnt'];
        }
        
        if (in_array('settings', $existingTables)) {
            $result = $conn->query("SELECT COUNT(*) as cnt FROM settings");
            $dbStatus['settings_count'] = $result->fetch_assoc()['cnt'];
        }
    } else {
        $dbStatus['error'] = "Unable to connect to database server. Please check your credentials in db.php.";
        $canConnect = false;
    }
} catch (Exception $e) {
    $dbStatus['error'] = $e->getMessage();
    $canConnect = false;
}

function runSetup($reset = false) {
    global $steps, $conn;
    $steps = [];
    
    if (!$conn) return ['status' => 'danger', 'message' => 'No database connection'];

    try {
        $conn->begin_transaction();
        
        if ($reset) {
            $steps[] = ['status' => 'info', 'message' => 'Dropping existing tables...'];
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            $conn->query("DROP TABLE IF EXISTS activity_logs");
            $conn->query("DROP TABLE IF EXISTS bonus_sheets");
            $conn->query("DROP TABLE IF EXISTS loan_transactions");
            $conn->query("DROP TABLE IF EXISTS pf_transactions");
            $conn->query("DROP TABLE IF EXISTS salary_sheets");
            $conn->query("DROP TABLE IF EXISTS employees");
            $conn->query("DROP TABLE IF EXISTS settings");
            $conn->query("DROP TABLE IF EXISTS admin");
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            $steps[] = ['status' => 'success', 'message' => 'Existing tables dropped'];
        }
        
        $steps[] = ['status' => 'info', 'message' => 'Creating admin table...'];
        $sql = "
        CREATE TABLE IF NOT EXISTS admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($sql)) throw new Exception($conn->error);
        $steps[] = ['status' => 'success', 'message' => 'Admin table created successfully'];
        
        $steps[] = ['status' => 'info', 'message' => 'Creating employees table...'];
        $sql = "
        CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            office_name VARCHAR(100) NOT NULL,
            office_code VARCHAR(10) NOT NULL,
            department VARCHAR(100) NOT NULL,
            dept_code VARCHAR(10) NOT NULL,
            unit VARCHAR(100),
            position VARCHAR(100) NOT NULL,
            emp_name VARCHAR(150) NOT NULL,
            official_phone VARCHAR(20),
            personal_phone VARCHAR(20),
            nid VARCHAR(50) UNIQUE,
            dob DATE,
            blood_group VARCHAR(5),
            sex VARCHAR(20),
            bank_name VARCHAR(100),
            bank_account VARCHAR(50),
            basic_salary DECIMAL(12,2) DEFAULT 0,
            pf_percentage DECIMAL(5,2) DEFAULT 5.00,
            joining_date DATE,
            photo VARCHAR(255),
            status ENUM('Active', 'Inactive', 'Resigned', 'Terminated') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_office (office_name),
            INDEX idx_office_code (office_code),
            INDEX idx_dept_code (dept_code),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($sql)) throw new Exception($conn->error);
        $steps[] = ['status' => 'success', 'message' => 'Employees table created successfully'];
        
        $steps[] = ['status' => 'info', 'message' => 'Creating salary_sheets table...'];
        $sql = "
        CREATE TABLE IF NOT EXISTS salary_sheets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            month VARCHAR(7) NOT NULL,
            working_days INT NOT NULL,
            present_days INT NOT NULL,
            absent_days INT DEFAULT 0,
            leave_days INT DEFAULT 0,
            basic_salary DECIMAL(12,2) NOT NULL,
            pf_percentage DECIMAL(5,2) DEFAULT 5.00,
            pf_deduction DECIMAL(12,2) DEFAULT 0,
            bonus DECIMAL(12,2) DEFAULT 0,
            gross_salary DECIMAL(12,2) DEFAULT 0,
            net_payable DECIMAL(12,2) DEFAULT 0,
            confirmed TINYINT(1) DEFAULT 0,
            confirmed_by INT DEFAULT NULL,
            confirmed_at DATETIME DEFAULT NULL,
            unconfirmed_by INT DEFAULT NULL,
            unconfirmed_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (confirmed_by) REFERENCES admin(id) ON DELETE SET NULL,
            FOREIGN KEY (unconfirmed_by) REFERENCES admin(id) ON DELETE SET NULL,
            UNIQUE KEY unique_employee_month (employee_id, month),
            INDEX idx_month (month),
            INDEX idx_confirmed (confirmed)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($sql)) throw new Exception($conn->error);
        $steps[] = ['status' => 'success', 'message' => 'Salary sheets table created successfully'];

        $steps[] = ['status' => 'info', 'message' => 'Creating pf_transactions table...'];
        $sql = "
        CREATE TABLE IF NOT EXISTS pf_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            salary_sheet_id INT DEFAULT NULL,
            transaction_date DATE NOT NULL,
            type ENUM('credit','debit') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            description VARCHAR(255),
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (salary_sheet_id) REFERENCES salary_sheets(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES admin(id) ON DELETE SET NULL,
            INDEX idx_pf_employee (employee_id),
            INDEX idx_pf_salary (salary_sheet_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($sql)) throw new Exception($conn->error);
        $steps[] = ['status' => 'success', 'message' => 'PF transactions table created successfully'];

        $steps[] = ['status' => 'info', 'message' => 'Creating loan_transactions table...'];
        $sql = "
        CREATE TABLE IF NOT EXISTS loan_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            salary_sheet_id INT DEFAULT NULL,
            transaction_date DATE NOT NULL,
            type ENUM('credit','debit') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            description VARCHAR(255),
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (salary_sheet_id) REFERENCES salary_sheets(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES admin(id) ON DELETE SET NULL,
            INDEX idx_loan_employee (employee_id),
            INDEX idx_loan_salary (salary_sheet_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($sql)) throw new Exception($conn->error);
        $steps[] = ['status' => 'success', 'message' => 'Loan transactions table created successfully'];

        $steps[] = ['status' => 'info', 'message' => 'Creating bonus_sheets table...'];
        $sql = "
        CREATE TABLE IF NOT EXISTS bonus_sheets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            month VARCHAR(7) NOT NULL,
            basic_salary DECIMAL(12,2) DEFAULT 0,
            bonus_pct DECIMAL(5,2) DEFAULT 0,
            bonus_amount DECIMAL(12,2) DEFAULT 0,
            bonus_type VARCHAR(50) DEFAULT 'Festival',
            description VARCHAR(255) DEFAULT '',
            confirmed TINYINT(1) DEFAULT 0,
            confirmed_by INT DEFAULT NULL,
            confirmed_at DATETIME DEFAULT NULL,
            unconfirmed_by INT DEFAULT NULL,
            unconfirmed_at DATETIME DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_emp_month (employee_id, month),
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (confirmed_by) REFERENCES admin(id) ON DELETE SET NULL,
            FOREIGN KEY (unconfirmed_by) REFERENCES admin(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES admin(id) ON DELETE SET NULL,
            INDEX idx_bonus_employee (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($sql)) throw new Exception($conn->error);
        $steps[] = ['status' => 'success', 'message' => 'Bonus sheets table created successfully'];
        
        $steps[] = ['status' => 'info', 'message' => 'Creating settings table...'];
        $sql = "
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($sql)) throw new Exception($conn->error);
        $steps[] = ['status' => 'success', 'message' => 'Settings table created successfully'];
        
        $steps[] = ['status' => 'info', 'message' => 'Creating activity_logs table...'];
        $sql = "
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(100) NOT NULL,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT,
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES admin(id) ON DELETE SET NULL,
            INDEX idx_user (user_id),
            INDEX idx_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($sql)) throw new Exception($conn->error);
        $steps[] = ['status' => 'success', 'message' => 'Activity logs table created successfully'];
        
        // Default settings
        $defaults = [
            'company_name' => 'My Company',
            'company_tagline' => 'Employee Management System',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'company_website' => '',
            'company_logo' => '',
            'default_pf_percentage' => '5.00',
            'default_working_days' => '26',
            'currency_symbol' => '৳',
            'currency_code' => 'BDT',
            'photo_prefix' => 'Photo_'
        ];
        foreach ($defaults as $key => $value) {
            $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
            $stmt->close();
        }
        
        // Default admin
        $result = $conn->query("SELECT COUNT(*) as cnt FROM admin");
        if ($result->fetch_assoc()['cnt'] == 0) {
            $username = 'admin';
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $password);
            $stmt->execute();
            $stmt->close();
            $steps[] = ['status' => 'success', 'message' => 'Default admin user created (admin / admin123)'];
        }
        
        $conn->commit();
        $steps[] = ['status' => 'success', 'message' => 'Database setup completed successfully!'];
        return ['status' => 'success', 'message' => 'Setup completed!'];
        
    } catch (Exception $e) {
        if ($conn) $conn->rollback();
        $steps[] = ['status' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        return ['status' => 'danger', 'message' => $e->getMessage()];
    }
}

generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reset = isset($_POST['reset']) && $_POST['reset'] === '1';
    $result = runSetup($reset);
    $message = $result['message'];
    $messageType = $result['status'];
    
    if ($canConnect) {
        $result = $conn->query("SHOW TABLES");
        $existingTables = [];
        while ($row = $result->fetch_array()) {
            $existingTables[] = $row[0];
        }
        $dbStatus['tables'] = $existingTables;
        $dbStatus['table_count'] = count($existingTables);
        
        if (in_array('employees', $existingTables)) {
            $result = $conn->query("SELECT COUNT(*) as cnt FROM employees");
            $dbStatus['employee_count'] = $result->fetch_assoc()['cnt'];
        }
        if (in_array('admin', $existingTables)) {
            $result = $conn->query("SELECT COUNT(*) as cnt FROM admin");
            $dbStatus['admin_count'] = $result->fetch_assoc()['cnt'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Setup - HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #1a1a2e; --secondary: #16213e; }
        body { min-height: 100vh; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); padding: 40px 20px; }
        .setup-card { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 700px; margin: 0 auto; overflow: hidden; }
        .setup-header { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); padding: 30px; text-align: center; }
        .setup-body { padding: 30px; }
        .step-item { padding: 10px 15px; border-radius: 8px; margin-bottom: 8px; display: flex; align-items: center; }
        .step-item i { margin-right: 12px; }
        .step-info { background: #e7f1ff; color: #0d6efd; }
        .step-success { background: #d1e7dd; color: #198754; }
        .step-danger { background: #f8d7da; color: #dc3545; }
        .db-status { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .db-status-item { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #eee; }
        .table-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; margin: 2px; }
        .table-badge.exists { background: #d1e7dd; color: #0f5132; }
        .table-badge.missing { background: #f8d7da; color: #842029; }
    </style>
</head>
<body>
    <div class="setup-card">
        <div class="setup-header">
            <i class="bi bi-database-gear text-white" style="font-size: 3rem;"></i>
            <h3 class="text-white mt-3">HR System Setup</h3>
        </div>
        <div class="setup-body">
            <?php if (!$canConnect): ?>
                <div class="alert alert-danger">
                    <strong>Connection Failed!</strong><br><?php echo htmlspecialchars($dbStatus['error']); ?>
                </div>
            <?php else: ?>
                <div class="db-status">
                    <h6><i class="bi bi-server me-2"></i>Status</h6>
                    <div class="db-status-item"><span>Connection</span><span class="text-success">Connected</span></div>
                    <div class="db-status-item"><span>Tables</span><span><?php echo $dbStatus['table_count']; ?></span></div>
                    <div class="db-status-item"><span>Table Health</span>
                        <span>
                            <?php $tables = ['admin', 'employees', 'salary_sheets', 'pf_transactions', 'loan_transactions', 'bonus_sheets', 'settings']; ?>
                            <?php foreach ($tables as $t): ?>
                                <span class="table-badge <?php echo in_array($t, $dbStatus['tables'] ?? []) ? 'exists' : 'missing'; ?>"><?php echo $t; ?></span>
                            <?php endforeach; ?>
                        </span>
                    </div>
                </div>

                <?php foreach ($steps as $step): ?>
                    <div class="step-item step-<?php echo $step['status']; ?>">
                        <i class="bi bi-<?php echo $step['status'] === 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
                        <?php echo htmlspecialchars($step['message']); ?>
                    </div>
                <?php endforeach; ?>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> mt-3"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <form method="POST" class="mt-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <button type="submit" class="btn btn-primary btn-lg w-100">Run Setup</button>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="reset" name="reset" value="1">
                                <label class="form-check-label" for="reset">Reset DB</label>
                            </div>
                        </div>
                    </div>
                </form>

                <?php if ($dbStatus['table_count'] >= 5): ?>
                    <div class="d-grid gap-2 mt-4">
                        <a href="../admin/login.php" class="btn btn-success btn-lg">Go to Login</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.getElementById('reset')?.addEventListener('change', function() {
            if (this.checked && !confirm('DELETE ALL DATA?')) this.checked = false;
        });
    </script>
</body>
</html>