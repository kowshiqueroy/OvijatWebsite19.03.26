<?php
/**
 * Database Setup Page
 * Core PHP Employee Management System
 * Interactive setup with progress display and reset functionality
 */

require_once __DIR__ . '/db.php';

$message = '';
$messageType = 'success';
$steps = [];
$dbStatus = [];
$canConnect = false;

try {
    $conn = getDBConnection();
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
    
} catch (Exception $e) {
    $dbStatus['error'] = $e->getMessage();
    $canConnect = false;
}

function runSetup($reset = false) {
    global $steps, $conn;
    $steps = [];
    
    try {
        if ($reset) {
            $steps[] = ['status' => 'info', 'message' => 'Dropping existing tables...'];
            $conn->query("DROP TABLE IF EXISTS bonuses");
            $conn->query("DROP TABLE IF EXISTS loan_transactions");
            $conn->query("DROP TABLE IF EXISTS pf_transactions");
            $conn->query("DROP TABLE IF EXISTS salary_sheets");
            $conn->query("DROP TABLE IF EXISTS employees");
            $conn->query("DROP TABLE IF EXISTS settings");
            $conn->query("DROP TABLE IF EXISTS admin");
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
        
        if (!$conn->query($sql)) {
            throw new Exception("Error creating admin table: " . $conn->error);
        }
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
            nid VARCHAR(50) UNIQUE,
            dob DATE,
            blood_group VARCHAR(5),
            sex VARCHAR(20),
            bank_name VARCHAR(100),
            bank_account VARCHAR(50),
            basic_salary DECIMAL(12,2) DEFAULT 0,
            pf_percentage DECIMAL(5,2) DEFAULT 5.00,
            employee_type ENUM('Staff', 'Worker', 'Intern', 'Contextual', 'Others') DEFAULT 'Staff',
            joining_date DATE,
            photo VARCHAR(255),
            status ENUM('Active', 'Inactive', 'Resigned', 'Terminated') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_office (office_name),
            INDEX idx_department (department),
            INDEX idx_status (status),
            INDEX idx_employee_type (employee_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        if (!$conn->query($sql)) {
            throw new Exception("Error creating employees table: " . $conn->error);
        }
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
        
        if (!$conn->query($sql)) {
            throw new Exception("Error creating salary_sheets table: " . $conn->error);
        }
        $steps[] = ['status' => 'success', 'message' => 'Salary sheets table created successfully'];

        $steps[] = ['status' => 'info', 'message' => 'Creating pf_transactions table...'];
        $sql = "
        CREATE TABLE IF NOT EXISTS pf_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            transaction_date DATE NOT NULL,
            type ENUM('credit','debit') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            description VARCHAR(255),
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES admin(id) ON DELETE SET NULL,
            INDEX idx_pf_employee (employee_id),
            INDEX idx_pf_date (transaction_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($sql)) {
            throw new Exception("Error creating pf_transactions table: " . $conn->error);
        }
        $steps[] = ['status' => 'success', 'message' => 'PF transactions table created successfully'];

        $steps[] = ['status' => 'info', 'message' => 'Creating loan_transactions table...'];
        $sql = "
        CREATE TABLE IF NOT EXISTS loan_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            transaction_date DATE NOT NULL,
            type ENUM('debit','credit') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            description VARCHAR(255),
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES admin(id) ON DELETE SET NULL,
            INDEX idx_loan_employee (employee_id),
            INDEX idx_loan_date (transaction_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($sql)) {
            throw new Exception("Error creating loan_transactions table: " . $conn->error);
        }
        $steps[] = ['status' => 'success', 'message' => 'Loan transactions table created successfully'];

        $steps[] = ['status' => 'info', 'message' => 'Creating bonuses table...'];
        $sql = "
        CREATE TABLE IF NOT EXISTS bonuses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            month VARCHAR(7) NOT NULL,
            bonus_type VARCHAR(50) DEFAULT 'Festival',
            amount DECIMAL(12,2) NOT NULL,
            description VARCHAR(255),
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES admin(id) ON DELETE SET NULL,
            INDEX idx_bonus_employee (employee_id),
            INDEX idx_bonus_month (month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($sql)) {
            throw new Exception("Error creating bonuses table: " . $conn->error);
        }
        $steps[] = ['status' => 'success', 'message' => 'Bonuses table created successfully'];

        $result = $conn->query("SHOW COLUMNS FROM salary_sheets LIKE 'confirmed'");
        if ($result->num_rows == 0) {
            $steps[] = ['status' => 'info', 'message' => 'Adding confirmation fields to salary_sheets...'];
            $conn->query("ALTER TABLE salary_sheets ADD COLUMN confirmed TINYINT(1) DEFAULT 0");
            $conn->query("ALTER TABLE salary_sheets ADD COLUMN confirmed_by INT DEFAULT NULL");
            $conn->query("ALTER TABLE salary_sheets ADD COLUMN confirmed_at DATETIME DEFAULT NULL");
            $conn->query("ALTER TABLE salary_sheets ADD COLUMN unconfirmed_by INT DEFAULT NULL");
            $conn->query("ALTER TABLE salary_sheets ADD COLUMN unconfirmed_at DATETIME DEFAULT NULL");
            $conn->query("ALTER TABLE salary_sheets ADD INDEX idx_confirmed (confirmed)");
            $steps[] = ['status' => 'success', 'message' => 'Confirmation fields added successfully'];
        }
        
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
        
        if (!$conn->query($sql)) {
            throw new Exception("Error creating settings table: " . $conn->error);
        }
        $steps[] = ['status' => 'success', 'message' => 'Settings table created successfully'];
        
        $result = $conn->query("SELECT COUNT(*) as cnt FROM settings WHERE setting_key = 'company_name'");
        $row = $result->fetch_assoc();
        
        if ($row['cnt'] == 0) {
            $steps[] = ['status' => 'info', 'message' => 'Inserting default settings...'];
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
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->bind_param("ss", $key, $value);
                $stmt->execute();
                $stmt->close();
            }
            $steps[] = ['status' => 'success', 'message' => 'Default settings inserted'];
        }
        
        $result = $conn->query("SELECT COUNT(*) as cnt FROM admin");
        $row = $result->fetch_assoc();
        
        if ($row['cnt'] == 0) {
            $steps[] = ['status' => 'info', 'message' => 'Creating default admin user...'];
            $username = 'admin';
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $password);
            $stmt->execute();
            $stmt->close();
            $steps[] = ['status' => 'success', 'message' => 'Default admin user created (admin / admin123)'];
        } else {
            $steps[] = ['status' => 'info', 'message' => 'Admin user already exists, skipping...'];
        }
        
        $steps[] = ['status' => 'success', 'message' => 'Database setup completed successfully!'];
        return ['status' => 'success', 'message' => 'Setup completed!'];
        
    } catch (Exception $e) {
        $steps[] = ['status' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        return ['status' => 'danger', 'message' => $e->getMessage()];
    }
}

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
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
        }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 40px 20px;
        }
        .setup-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 700px;
            margin: 0 auto;
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 30px;
            text-align: center;
        }
        .setup-header i {
            font-size: 3rem;
            color: #fff;
        }
        .setup-body {
            padding: 30px;
        }
        .step-item {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        .step-item i {
            margin-right: 12px;
            font-size: 1.2rem;
        }
        .step-info { background: #e7f1ff; }
        .step-info i { color: #0d6efd; }
        .step-success { background: #d1e7dd; }
        .step-success i { color: #198754; }
        .step-danger { background: #f8d7da; }
        .step-danger i { color: #dc3545; }
        .step-warning { background: #fff3cd; }
        .step-warning i { color: #ffc107; }
        .db-status {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .db-status-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .db-status-item:last-child {
            border-bottom: none;
        }
        .table-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin: 2px;
        }
        .table-badge.exists { background: #d1e7dd; color: #0f5132; }
        .table-badge.missing { background: #f8d7da; color: #842029; }
    </style>
</head>
<body>
    <div class="setup-card">
        <div class="setup-header">
            <i class="bi bi-database-gear"></i>
            <h3 class="text-white mt-3 mb-1">HR System Setup</h3>
            <small class="text-white-50">Database Configuration</small>
        </div>
        
        <div class="setup-body">
            <?php if (!$canConnect): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Database Connection Failed!</strong><br>
                    <?php echo htmlspecialchars($dbStatus['error'] ?? 'Unable to connect to database'); ?>
                </div>
                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle me-2"></i>To fix this:</h6>
                    <ol class="mb-0">
                        <li>Make sure XAMPP MySQL is running</li>
                        <li>Create a database named <code>hr_system</code> in phpMyAdmin</li>
                        <li>Or update <code>config/db.php</code> with your database credentials</li>
                    </ol>
                </div>
            <?php else: ?>
                <div class="db-status">
                    <h6 class="mb-3"><i class="bi bi-server me-2"></i>Current Database Status</h6>
                    
                    <div class="db-status-item">
                        <span><i class="bi bi-check-circle text-success me-2"></i>Connection</span>
                        <span class="text-success">Connected</span>
                    </div>
                    
                    <div class="db-status-item">
                        <span><i class="bi bi-database me-2"></i>Tables</span>
                        <span><?php echo $dbStatus['table_count'] ?? 0; ?> tables</span>
                    </div>
                    
                    <?php if (isset($dbStatus['admin_count'])): ?>
                    <div class="db-status-item">
                        <span><i class="bi bi-person-badge me-2"></i>Admin Users</span>
                        <span><?php echo $dbStatus['admin_count']; ?> user(s)</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($dbStatus['employee_count'])): ?>
                    <div class="db-status-item">
                        <span><i class="bi bi-people me-2"></i>Employees</span>
                        <span><?php echo $dbStatus['employee_count']; ?> records</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($dbStatus['salary_count'])): ?>
                    <div class="db-status-item">
                        <span><i class="bi bi-currency-dollar me-2"></i>Salary Records</span>
                        <span><?php echo $dbStatus['salary_count']; ?> records</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="db-status-item">
                        <span><i class="bi bi-table me-2"></i>Table Status</span>
                        <span>
                            <?php $tables = ['admin', 'employees', 'salary_sheets', 'pf_transactions', 'loan_transactions', 'bonuses', 'settings']; ?>
                            <?php foreach ($tables as $table): ?>
                                <?php if (in_array($table, $dbStatus['tables'] ?? [])): ?>
                                    <span class="table-badge exists"><?php echo $table; ?> ✓</span>
                                <?php else: ?>
                                    <span class="table-badge missing"><?php echo $table; ?> ✗</span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($steps)): ?>
                    <h6 class="mb-3"><i class="bi bi-list-task me-2"></i>Setup Progress</h6>
                    <?php foreach ($steps as $step): ?>
                        <div class="step-item step-<?php echo $step['status']; ?>">
                            <?php if ($step['status'] === 'info'): ?>
                                <i class="bi bi-info-circle"></i>
                            <?php elseif ($step['status'] === 'success'): ?>
                                <i class="bi bi-check-circle"></i>
                            <?php elseif ($step['status'] === 'danger'): ?>
                                <i class="bi bi-x-circle"></i>
                            <?php else: ?>
                                <i class="bi bi-exclamation-circle"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($step['message']); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> mt-3">
                        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="mt-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <button type="submit" name="setup" value="1" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-play-fill me-2"></i>
                                <?php echo ($dbStatus['table_count'] ?? 0) > 0 ? 'Re-run Setup' : 'Run Setup'; ?>
                            </button>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="reset" name="reset" value="1">
                                <label class="form-check-label" for="reset">
                                    <i class="bi bi-arrow-repeat me-1"></i> Reset Database
                                </label>
                            </div>
                            <small class="text-muted">Warning: This will delete all existing data!</small>
                        </div>
                    </div>
                </form>
                
                <?php if (($dbStatus['table_count'] ?? 0) >= 3): ?>
                    <hr class="my-4">
                    <div class="d-grid gap-2">
                        <a href="../admin/login.php" class="btn btn-success btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Go to Admin Login
                        </a>
                        <a href="../public/profile.php" class="btn btn-outline-primary">
                            <i class="bi bi-globe me-2"></i> View Public Profile Page
                        </a>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <div class="mt-4 p-3 bg-light rounded">
                <h6><i class="bi bi-key me-2"></i>Default Admin Credentials</h6>
                <div class="row">
                    <div class="col-6">
                        <strong>Username:</strong> admin
                    </div>
                    <div class="col-6">
                        <strong>Password:</strong> admin123
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('reset').addEventListener('change', function() {
            if (this.checked) {
                if (!confirm('WARNING: This will DELETE all existing data!\n\nAre you sure you want to reset the database?')) {
                    this.checked = false;
                }
            }
        });
    </script>
</body>
</html>
