<?php
/**
 * Demo Data Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Demo Data';
$currentPage = 'demo';

$message = '';
$messageType = 'success';
$demoAdded = 0;

if (isset($_POST['add_demo'])) {
    $conn = getDBConnection();
    
    $offices = [
        ['name' => 'Head Office', 'code' => 'HQ'],
        ['name' => 'Factory', 'code' => 'FAC'],
        ['name' => 'Branch North', 'code' => 'BN']
    ];
    
    $departments = [
        ['name' => 'Information Technology', 'code' => 'IT'],
        ['name' => 'Human Resources', 'code' => 'HR'],
        ['name' => 'Finance', 'code' => 'FIN'],
        ['name' => 'Marketing', 'code' => 'MKT'],
        ['name' => 'Production', 'code' => 'PROD'],
        ['name' => 'Sales', 'code' => 'SAL']
    ];
    
    $positions = [
        'Software Engineer', 'Senior Engineer', 'Manager', 'Assistant Manager',
        'HR Manager', 'Accountant', 'Marketing Executive', 'Sales Representative',
        'Production Worker', 'Quality Inspector', 'Intern'
    ];
    
    $banks = ['City Bank', 'Standard Chartered', 'National Bank', 'Mercantile Bank', 'Workers Bank'];
    $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
    $sexes = ['Male', 'Female'];
    $types = ['Staff', 'Worker', 'Intern'];
    
    $demoEmployees = [
        ['name' => 'John Smith', 'basic' => 55000, 'type' => 'Staff'],
        ['name' => 'Sarah Johnson', 'basic' => 48000, 'type' => 'Staff'],
        ['name' => 'Michael Brown', 'basic' => 42000, 'type' => 'Staff'],
        ['name' => 'Emily Davis', 'basic' => 38000, 'type' => 'Staff'],
        ['name' => 'David Wilson', 'basic' => 35000, 'type' => 'Staff'],
        ['name' => 'Jessica Martinez', 'basic' => 28000, 'type' => 'Worker'],
        ['name' => 'Robert Taylor', 'basic' => 26000, 'type' => 'Worker'],
        ['name' => 'Amanda Anderson', 'basic' => 24000, 'type' => 'Worker'],
        ['name' => 'Christopher Thomas', 'basic' => 22000, 'type' => 'Worker'],
        ['name' => 'Ashley Jackson', 'basic' => 15000, 'type' => 'Intern'],
        ['name' => 'Daniel White', 'basic' => 62000, 'type' => 'Staff'],
        ['name' => 'Jennifer Harris', 'basic' => 52000, 'type' => 'Staff'],
        ['name' => 'Matthew Clark', 'basic' => 45000, 'type' => 'Staff'],
        ['name' => 'Stephanie Lewis', 'basic' => 40000, 'type' => 'Staff'],
        ['name' => 'Andrew Walker', 'basic' => 32000, 'type' => 'Worker']
    ];
    
    $pfPercentages = ['5.00', '5.00', '5.00', '10.00', '8.00'];
    
    $successCount = 0;
    $skipCount = 0;
    
    foreach ($demoEmployees as $index => $emp) {
        $office = $offices[array_rand($offices)];
        $dept = $departments[array_rand($departments)];
        
        $result = $conn->query("SELECT id FROM employees WHERE emp_name = '" . $conn->real_escape_string($emp['name']) . "' LIMIT 1");
        if ($result->num_rows > 0) {
            $skipCount++;
            continue;
        }
        
        $dob = date('Y-m-d', strtotime('-' . rand(22, 55) . ' years'));
        $joiningDate = date('Y-m-d', strtotime('-' . rand(1, 60) . ' months'));
        
        $sql = "INSERT INTO employees (
            office_name, office_code, department, dept_code,
            unit, position, emp_name, nid, dob,
            blood_group, sex, bank_name, bank_account,
            basic_salary, pf_percentage, employee_type,
            joining_date, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $nid = 'NID' . rand(100000000, 999999999);
        $bankAccount = (string)rand(1000000000, 9999999999);
        $bloodGroup = $bloodGroups[array_rand($bloodGroups)];
        $sex = $sexes[array_rand($sexes)];
        $position = $positions[array_rand($positions)];
        $bank = $banks[array_rand($banks)];
        $pf = $pfPercentages[array_rand($pfPercentages)];
        $unit = (string)(rand(1, 3) . rand(1, 9) . rand(0, 9));
        $status = 'Active';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssssssdsiss",
            $office['name'], $office['code'], $dept['name'], $dept['code'],
            $unit, $position, $emp['name'], $nid, $dob,
            $bloodGroup, $sex, $bank, $bankAccount,
            $emp['basic'], $pf, $emp['type'],
            $joiningDate, $status
        );
        
        if ($stmt->execute()) {
            $successCount++;
            $empId = $conn->insert_id;
            
            $months = ['2025-01', '2025-02', '2025-03'];
            foreach ($months as $month) {
                $workingDays = 26;
                $present = rand(20, 26);
                $leave = rand(0, 4);
                $absent = $workingDays - $present - $leave;
                
                $calc = calculateSalary($emp['basic'], $workingDays, $present, $leave, (float)$pf);
                
                $checkSql = "SELECT id FROM salary_sheets WHERE employee_id = ? AND month = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("is", $empId, $month);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows == 0) {
                    $basicSalary = $emp['basic'];
                    $pfDed = $calc['pf_deduction'];
                    $grossSalary = $calc['gross_salary'];
                    $netPayable = $calc['net_payable'];
                    
                    $salaryStmt = $conn->prepare("INSERT INTO salary_sheets (
                        employee_id, month, working_days, present_days, absent_days, leave_days,
                        basic_salary, pf_percentage, pf_deduction, gross_salary, net_payable
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $salaryStmt->bind_param("isiiidddddi",
                        $empId, $month, $workingDays, $present, $absent, $leave,
                        $basicSalary, $pf, $pfDed, $grossSalary, $netPayable
                    );
                    $salaryStmt->execute();
                    $salaryStmt->close();
                }
                $checkStmt->close();
            }
        }
        $stmt->close();
    }
    
    $message = "Demo data added: $successCount employees with salary records ($skipCount skipped - already exist)";
    $messageType = $successCount > 0 ? 'success' : 'warning';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">Demo Data</h4>
        <small class="text-muted">Add sample employees and salary data for testing</small>
    </div>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $messageType); ?>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-database-add me-2"></i>Add Demo Data</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    This will add sample employees with realistic data including:
                </p>
                <ul class="text-muted">
                    <li>15 sample employees across different offices and departments</li>
                    <li>Random but realistic personal information (DOB, blood group, etc.)</li>
                    <li>Bank details for each employee</li>
                    <li>Salary records for the last 3 months (Jan-Mar 2025)</li>
                    <li>Provident Fund calculations</li>
                </ul>
                
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> Employees with existing names will be skipped to prevent duplicates.
                </div>
                
                <form method="POST" action="">
                    <button type="submit" name="add_demo" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-plus-circle me-2"></i> Add Demo Data
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Demo Data Overview</h5>
            </div>
            <div class="card-body">
                <h6>Offices</h6>
                <div class="mb-3">
                    <span class="badge bg-primary me-1">Head Office (HQ)</span>
                    <span class="badge bg-success me-1">Factory (FAC)</span>
                    <span class="badge bg-info">Branch North (BN)</span>
                </div>
                
                <h6>Departments</h6>
                <div class="mb-3">
                    <span class="badge bg-secondary me-1">IT</span>
                    <span class="badge bg-secondary me-1">HR</span>
                    <span class="badge bg-secondary me-1">Finance</span>
                    <span class="badge bg-secondary me-1">Marketing</span>
                    <span class="badge bg-secondary me-1">Production</span>
                    <span class="badge bg-secondary">Sales</span>
                </div>
                
                <h6>Employee Types</h6>
                <div class="mb-3">
                    <span class="badge bg-primary me-1">Staff (10)</span>
                    <span class="badge bg-warning me-1">Worker (4)</span>
                    <span class="badge bg-info">Intern (1)</span>
                </div>
                
                <h6>Salary Periods</h6>
                <div>
                    <span class="badge bg-success me-1">January 2025</span>
                    <span class="badge bg-success me-1">February 2025</span>
                    <span class="badge bg-success">March 2025</span>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Quick Links</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="employees.php" class="btn btn-outline-primary">
                        <i class="bi bi-people me-2"></i> View Employees
                    </a>
                    <a href="salary-list.php" class="btn btn-outline-success">
                        <i class="bi bi-currency-dollar me-2"></i> View Salary Sheets
                    </a>
                    <a href="settings.php" class="btn btn-outline-secondary">
                        <i class="bi bi-gear me-2"></i> Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
