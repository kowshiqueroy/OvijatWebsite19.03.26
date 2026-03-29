<?php
/**
 * Public Employee Profile Page
 * Core PHP Employee Management System
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$employee = null;
$searchId = $_GET['id'] ?? '';
$error = '';

if (!empty($searchId)) {
    $employee = getEmployeeByPublicId($searchId);
    if (!$employee) {
        $error = 'Employee not found';
    }
} else {
    $conn = getDBConnection();
    $result = $conn->query("SELECT id, emp_name, office_code, dept_code FROM employees WHERE status = 'Active' ORDER BY emp_name LIMIT 50");
    $employeeList = [];
    while ($row = $result->fetch_assoc()) {
        $employeeList[] = $row;
    }
}

$pfBalance = 0;
$salaryHistory = [];
if ($employee) {
    $pfBalance = calculatePFBalance($employee['id']);
    $salaryHistory = getSalarySheets($employee['id'], null, []);
    $salaryHistory = array_slice($salaryHistory, 0, 6);
}

$pageTitle = $employee ? $employee['emp_name'] : 'Employee Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f3460;
        }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, var(--accent) 100%);
            padding: 40px 20px;
        }
        .profile-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 900px;
            margin: 0 auto;
        }
        .profile-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 40px;
            text-align: center;
            position: relative;
        }
        .profile-img {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            border: 5px solid #fff;
            object-fit: cover;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            background: #fff;
        }
        .profile-name {
            color: #fff;
            font-size: 1.8rem;
            font-weight: 700;
            margin-top: 15px;
            margin-bottom: 5px;
        }
        .profile-id {
            color: rgba(255,255,255,0.8);
            font-size: 1rem;
            background: rgba(255,255,255,0.15);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
        }
        .profile-body {
            padding: 30px;
        }
        .info-section {
            margin-bottom: 25px;
        }
        .info-section h5 {
            color: var(--primary);
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .info-item {
            padding: 10px 0;
        }
        .info-label {
            font-size: 0.8rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .info-value {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }
        .pf-card {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            border-radius: 15px;
            padding: 25px;
            color: #fff;
            margin-top: 20px;
        }
        .pf-amount {
            font-size: 2.5rem;
            font-weight: 700;
        }
        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-active { background: #d1e7dd; color: #0f5132; }
        .status-inactive { background: #f8d7da; color: #842029; }
        .search-box {
            max-width: 500px;
            margin: 0 auto 30px;
        }
        .search-box .form-control {
            border-radius: 30px;
            padding: 15px 20px;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .employee-list {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 15px;
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        .employee-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 10px;
            transition: background 0.2s;
            text-decoration: none;
            color: #333;
        }
        .employee-item:hover {
            background: #f8f9fa;
        }
        .employee-item img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }
        .salary-table {
            font-size: 0.9rem;
        }
        .salary-table th {
            background: #f8f9fa;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .profile-card {
                box-shadow: none;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$employee): ?>
            <div class="search-box text-center mb-4">
                <h3 class="text-white mb-4">Employee Profile</h3>
                <form method="GET" action="">
                    <div class="input-group">
                        <input type="text" name="id" class="form-control form-control-lg" 
                               placeholder="Search by Employee ID (e.g., HQ-IT-0001) or numeric ID"
                               value="<?php echo htmlspecialchars($searchId); ?>">
                        <button type="submit" class="btn btn-light btn-lg">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger text-center mx-auto" style="max-width: 500px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($searchId) && !empty($employeeList)): ?>
                <div class="employee-list">
                    <h6 class="mb-3 text-muted">Select an Employee</h6>
                    <?php foreach ($employeeList as $emp): ?>
                        <a href="?id=<?php echo $emp['id']; ?>" class="employee-item">
                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 40px; height: 40px;">
                                <i class="bi bi-person text-white"></i>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($emp['emp_name']); ?></strong>
                                <br><small class="text-muted">
                                    <?php echo generateEmployeeID($emp['id'], $emp['office_code'], $emp['dept_code']); ?>
                                </small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="profile-card">
                <div class="profile-header">
                    <?php if ($employee['photo']): ?>
                        <img src="../uploads/photos/<?php echo htmlspecialchars($employee['photo']); ?>" 
                             alt="" class="profile-img">
                    <?php else: ?>
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160' fill='%23ccc'%3E%3Crect width='100%25' height='100%25'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' fill='%23aaa' font-size='40'%3E%3C/text%3E%3C/svg%3E" 
                             class="profile-img">
                    <?php endif; ?>
                    
                    <h1 class="profile-name"><?php echo htmlspecialchars($employee['emp_name']); ?></h1>
                    <span class="profile-id">
                        <?php echo generateEmployeeID($employee['id'], $employee['office_code'], $employee['dept_code']); ?>
                    </span>
                    <span class="status-badge status-<?php echo strtolower($employee['status']); ?> ms-2">
                        <?php echo $employee['status']; ?>
                    </span>
                </div>
                
                <div class="profile-body">
                    <div class="pf-card">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="info-label" style="color: rgba(255,255,255,0.8);">Accumulated Provident Fund</div>
                                <div class="pf-amount">$<?php echo formatCurrency($pfBalance); ?></div>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <i class="bi bi-piggy-bank fs-1" style="opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-4 mt-2">
                        <div class="col-md-6">
                            <div class="info-section">
                                <h5><i class="bi bi-briefcase me-2"></i>Job Information</h5>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-label">Office</div>
                                        <div class="info-value"><?php echo htmlspecialchars($employee['office_name']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Department</div>
                                        <div class="info-value"><?php echo htmlspecialchars($employee['department']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Unit</div>
                                        <div class="info-value"><?php echo htmlspecialchars($employee['unit'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Position</div>
                                        <div class="info-value"><?php echo htmlspecialchars($employee['position']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Employee Type</div>
                                        <div class="info-value"><?php echo $employee['employee_type']; ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Joining Date</div>
                                        <div class="info-value">
                                            <?php echo $employee['joining_date'] ? date('d M Y', strtotime($employee['joining_date'])) : 'N/A'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-section">
                                <h5><i class="bi bi-person me-2"></i>Personal Details</h5>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-label">NID Number</div>
                                        <div class="info-value"><?php echo htmlspecialchars($employee['nid'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Date of Birth</div>
                                        <div class="info-value">
                                            <?php echo $employee['dob'] ? date('d M Y', strtotime($employee['dob'])) : 'N/A'; ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Age</div>
                                        <div class="info-value"><?php echo calculateAge($employee['dob']); ?> years</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Blood Group</div>
                                        <div class="info-value"><?php echo htmlspecialchars($employee['blood_group'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Sex</div>
                                        <div class="info-value"><?php echo htmlspecialchars($employee['sex'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="info-section">
                                <h5><i class="bi bi-bank me-2"></i>Bank Details</h5>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-label">Bank Name</div>
                                        <div class="info-value"><?php echo htmlspecialchars($employee['bank_name'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Account Number</div>
                                        <div class="info-value"><?php echo htmlspecialchars($employee['bank_account'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-section">
                                <h5><i class="bi bi-cash me-2"></i>Salary Information</h5>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-label">Basic Salary</div>
                                        <div class="info-value">$<?php echo formatCurrency($employee['basic_salary']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">PF Contribution</div>
                                        <div class="info-value"><?php echo $employee['pf_percentage']; ?>%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($salaryHistory)): ?>
                        <div class="info-section mt-4">
                            <h5><i class="bi bi-clock-history me-2"></i>Recent Salary History</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered salary-table">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Working Days</th>
                                            <th>Present</th>
                                            <th>Leave</th>
                                            <th>Gross</th>
                                            <th>PF Ded.</th>
                                            <th>Net Payable</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($salaryHistory as $salary): ?>
                                            <tr>
                                                <td><?php echo date('M Y', strtotime($salary['month'] . '-01')); ?></td>
                                                <td><?php echo $salary['working_days']; ?></td>
                                                <td><?php echo $salary['present_days']; ?></td>
                                                <td><?php echo $salary['leave_days']; ?></td>
                                                <td>$<?php echo formatCurrency($salary['gross_salary']); ?></td>
                                                <td>$<?php echo formatCurrency($salary['pf_deduction']); ?></td>
                                                <td class="fw-bold">$<?php echo formatCurrency($salary['net_payable']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4 no-print">
                        <a href="?" class="btn btn-outline-primary">
                            <i class="bi bi-search me-1"></i> Search Another Employee
                        </a>
                        <button onclick="window.print()" class="btn btn-primary ms-2">
                            <i class="bi bi-printer me-1"></i> Print Profile
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="text-center mt-4 text-white-50">
            <small>&copy; <?php echo date('Y'); ?> HR Management System</small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
