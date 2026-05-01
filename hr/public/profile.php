<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$isLoggedIn = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);

$companyName = getSetting('company_name') ?? 'Company';
$companyLogo = getSetting('company_logo') ?? '';
$companyTagline = getSetting('company_tagline') ?? '';

// Create profile_locks table if not exists
$conn = getDBConnection();
$conn->query("CREATE TABLE IF NOT EXISTS profile_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL UNIQUE,
    pin VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$error = '';
$searchQuery = trim($_GET['q'] ?? '');
$selectedEmpId = $_GET['emp_id'] ?? null;
$matchingEmployees = [];
$employee = null;
$pinSet = false;
$currentPin = null;
$pinVerified = false;

// Handle search
if (!empty($searchQuery)) {
    $employee = getEmployeeByPublicId($searchQuery);
    if (!$employee) {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE nid = ? AND status = 'Active'");
        $stmt->bind_param("s", $searchQuery);
        $stmt->execute();
        $nidResult = $stmt->get_result();
        if ($nidRow = $nidResult->fetch_assoc()) {
            $employee = $nidRow;
        } else {
            $stmt = $conn->prepare("SELECT * FROM employees WHERE emp_name LIKE ? AND status = 'Active'");
            $likeQuery = "%$searchQuery%";
            $stmt->bind_param("s", $likeQuery);
            $stmt->execute();
            $nameResult = $stmt->get_result();
            $matchingEmployees = $nameResult->fetch_all(MYSQLI_ASSOC);
            if (count($matchingEmployees) === 1) {
                $employee = $matchingEmployees[0];
                $matchingEmployees = [];
            } elseif (count($matchingEmployees) > 1) {
                // Show list of matching employees
            } else {
                $error = 'Employee not found';
            }
        }
    }
}

if ($selectedEmpId && is_numeric($selectedEmpId)) {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ? AND status = 'Active'");
    $stmt->bind_param("i", $selectedEmpId);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    if ($employee) {
        $matchingEmployees = [];
    } else {
        $error = 'Employee not found';
    }
}

if ($employee) {
    $stmt = $conn->prepare("SELECT pin FROM profile_locks WHERE employee_id = ?");
    $stmt->bind_param("i", $employee['id']);
    $stmt->execute();
    $pinResult = $stmt->get_result();
    if ($pinRow = $pinResult->fetch_assoc()) {
        $currentPin = $pinRow['pin'];
        $pinSet = true;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $enteredPin = $_POST['pin'] ?? '';
        $newPin = $_POST['new_pin'] ?? '';

        if ($action === 'set_pin' && !$pinSet) {
            if (preg_match('/^\d{4,6}$/', $enteredPin)) {
                $stmt = $conn->prepare("INSERT INTO profile_locks (employee_id, pin) VALUES (?, ?)");
                $stmt->bind_param("is", $employee['id'], $enteredPin);
                if ($stmt->execute()) {
                    $currentPin = $enteredPin;
                    $pinSet = true;
                    $pinVerified = true;
                } else {
                    $error = 'Failed to set PIN: ' . $conn->error;
                }
            } else {
                $error = 'PIN must be 4-6 digits';
            }
        } elseif ($action === 'unlock' && $pinSet) {
            if ($enteredPin === $currentPin) {
                $pinVerified = true;
            } else {
                $error = 'Invalid PIN';
            }
        } elseif ($action === 'reset_pin' && $isLoggedIn) {
            if (preg_match('/^\d{4,6}$/', $newPin)) {
                if ($pinSet) {
                    $stmt = $conn->prepare("UPDATE profile_locks SET pin = ? WHERE employee_id = ?");
                    $stmt->bind_param("si", $newPin, $employee['id']);
                } else {
                    $stmt = $conn->prepare("INSERT INTO profile_locks (employee_id, pin) VALUES (?, ?)");
                    $stmt->bind_param("is", $employee['id'], $newPin);
                }
                if ($stmt->execute()) {
                    $currentPin = $newPin;
                    $pinSet = true;
                    $error = 'PIN reset successfully';
                } else {
                    $error = 'Failed to reset PIN: ' . $conn->error;
                }
            } else {
                $error = 'New PIN must be 4-6 digits';
            }
        }
    }
}

$pfBalance = 0;
$salaryHistory = [];
$bonusHistory = [];
$loanHistory = [];
$pfHistory = [];
$empHistory = [];
if ($employee && $pinVerified) {
    $pfBalance = calculatePFBalance($employee['id']);
    $salaryHistory = getSalarySheets($employee['id'], null, []);

    // Get bonus history
    $stmt = $conn->prepare("SELECT * FROM bonus_sheets WHERE employee_id = ? ORDER BY month DESC");
    $stmt->bind_param("i", $employee['id']);
    $stmt->execute();
    $bonusResult = $stmt->get_result();
    $bonusHistory = $bonusResult->fetch_all(MYSQLI_ASSOC);

    // Get loan history
    $stmt = $conn->prepare("SELECT * FROM loan_transactions WHERE employee_id = ? ORDER BY transaction_date DESC");
    $stmt->bind_param("i", $employee['id']);
    $stmt->execute();
    $loanResult = $stmt->get_result();
    $loanHistory = $loanResult->fetch_all(MYSQLI_ASSOC);

    // Get PF transactions
    $tableCheck = $conn->query("SHOW TABLES LIKE 'pf_transactions'");
    if ($tableCheck->num_rows > 0) {
        $stmt = $conn->prepare("SELECT * FROM pf_transactions WHERE employee_id = ? ORDER BY transaction_date DESC LIMIT 10");
        $stmt->bind_param("i", $employee['id']);
        $stmt->execute();
        $pfResult = $stmt->get_result();
        $pfHistory = $pfResult->fetch_all(MYSQLI_ASSOC);
    }

    $empHistory = getEmploymentHistory($employee['id']);
}

$pageTitle = $employee && $pinVerified ? $employee['emp_name'] : 'Employee Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo htmlspecialchars($companyName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --success: #06d6a0;
            --warning: #ffd166;
            --danger: #ef476f;
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --gray: #6c757d;
        }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 10px;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #333;
        }
        .header-bar { text-align: center; margin-bottom: 20px; }
        .header-bar img { height: 45px; width: auto; }
        .header-bar h3 { color: #fff; margin: 10px 0 0; font-weight: 600; font-size: 1.3rem; }
        .header-bar p { color: rgba(255,255,255,0.7); margin: 5px 0 10px; font-size: 0.85rem; }

        .search-wrapper { max-width: 500px; margin: 0 auto 25px; }
        .search-wrapper .input-group { box-shadow: 0 4px 20px rgba(0,0,0,0.3); border-radius: 50px; overflow: hidden; }
        .search-wrapper .form-control {
            border: none; border-radius: 50px 0 0 50px; padding: 14px 20px 14px 45px;
            font-size: 0.95rem; background: #fff;
        }
        .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--gray); z-index: 10; }
        .search-wrapper .btn { border-radius: 0 50px 50px 0; padding: 14px 25px; background: var(--primary); border: none; }

        .profile-container { max-width: 800px; margin: 0 auto; }

        .profile-header-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 20px; padding: 30px 20px; text-align: center; color: #fff;
            box-shadow: 0 10px 40px rgba(67,97,238,0.3); margin-bottom: 20px;
        }
        .profile-img {
            width: 100px; height: 100px; border-radius: 50%; border: 4px solid rgba(255,255,255,0.3);
            object-fit: cover; box-shadow: 0 4px 15px rgba(0,0,0,0.2); background: #fff;
        }
        .profile-name { font-size: 1.5rem; font-weight: 700; margin: 15px 0 5px; }
        .profile-meta { font-size: 0.85rem; opacity: 0.9; }

        .stat-card {
            background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 15px; transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .stat-label { font-size: 0.75rem; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--dark); }

        .section-card {
            background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 15px; overflow: hidden;
        }
        .section-header {
            padding: 15px 20px; background: var(--light); border-bottom: 1px solid #eee;
            font-weight: 600; color: var(--dark); display: flex; align-items: center; gap: 10px;
        }
        .section-body { padding: 20px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-size: 0.8rem; color: var(--gray); }
        .info-value { font-size: 0.9rem; font-weight: 500; color: var(--dark); }

        .table-responsive { font-size: 0.85rem; }
        .table th { background: var(--light); font-weight: 600; font-size: 0.75rem; padding: 10px 8px; }
        .table td { padding: 10px 8px; vertical-align: middle; }

        .unlock-card {
            background: #fff; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 400px; margin: 50px auto; padding: 30px;
        }
        .pin-input { letter-spacing: 8px; font-size: 1.5rem; text-align: center; font-weight: 600; }

        .badge-status { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; }

        @media (max-width: 576px) {
            body { padding: 5px; }
            .profile-header-card { padding: 20px 15px; border-radius: 16px; }
            .profile-img { width: 80px; height: 80px; }
            .profile-name { font-size: 1.2rem; }
            .stat-card { padding: 15px; }
            .stat-value { font-size: 1.2rem; }
            .section-body { padding: 15px; }
            .info-row { flex-direction: column; gap: 2px; }
            .table { font-size: 0.75rem; }
        }

        @media print {
            body { background: #fff !important; padding: 0 !important; }
            .no-print, .header-bar, .header-actions, .search-wrapper, .unlock-card { display: none !important; }
            .profile-container { max-width: 100%; }
            .profile-header-card, .stat-card, .section-card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <?php if (!empty($companyLogo)): ?>
            <img src="../uploads/<?php echo htmlspecialchars($companyLogo); ?>" alt="Logo">
        <?php endif; ?>
        <h3><?php echo htmlspecialchars($companyName); ?></h3>
        <?php if (!empty($companyTagline)): ?>
            <p><?php echo htmlspecialchars($companyTagline); ?></p>
        <?php endif; ?>
    </div>

    <div class="header-actions text-center mb-3 no-print">
        <?php if ($isLoggedIn): ?>
            <a href="../admin/dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3"><i class="bi bi-speedometer2 me-1"></i> Admin</a>
        <?php else: ?>
            <a href="../admin/login.php" class="btn btn-light btn-sm rounded-pill px-3"><i class="bi bi-box-arrow-in-right me-1"></i> Login</a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show mx-auto" style="max-width: 500px;" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!$employee || ($employee && !$pinVerified)): ?>
        <div class="search-wrapper no-print">
            <form method="GET" action="">
                <div class="input-group">
                    <div class="position-relative flex-grow-1">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" name="q" class="form-control" placeholder="Search by Name, ID, or NID..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
        <?php if (!$searchQuery && !$employee): ?>
            <p class="text-center text-white-50">Search for an employee to view their profile</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($matchingEmployees)): ?>
        <div class="profile-container no-print">
            <div class="section-card">
                <div class="section-header"><i class="bi bi-people-fill text-primary"></i> Multiple Employees Found</div>
                <div class="section-body">
                    <div class="list-group">
                        <?php foreach ($matchingEmployees as $emp): ?>
                            <a href="?q=<?php echo urlencode($searchQuery); ?>&emp_id=<?php echo $emp['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($emp['emp_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($emp['office_name'] . ' - ' . $emp['department']); ?></small>
                                </div>
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($employee && !$pinVerified): ?>
        <div class="unlock-card no-print">
            <div class="text-center mb-4">
                <div class="mb-3">
                    <?php if ($employee['photo']): ?>
                        <img src="../uploads/photos/<?php echo htmlspecialchars($employee['photo']); ?>" class="profile-img">
                    <?php else: ?>
                        <div class="profile-img mx-auto d-flex align-items-center justify-content-center" style="background: #e9ecef; color: #999;"><i class="bi bi-person" style="font-size: 2.5rem;"></i></div>
                    <?php endif; ?>
                </div>
                <h4><?php echo htmlspecialchars($employee['emp_name']); ?></h4>
                <p class="text-muted"><?php echo generateEmployeeID($employee['id'], $employee['office_code'], $employee['dept_code']); ?></p>
            </div>

            <?php if (!$pinSet): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="set_pin">
                    <div class="mb-3">
                        <label class="form-label">Set a 4-6 digit PIN</label>
                        <input type="password" name="pin" class="form-control form-control-lg pin-input" maxlength="6" pattern="\d{4,6}" placeholder="••••" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill">Set PIN & Unlock</button>
                </form>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="unlock">
                    <div class="mb-3">
                        <label class="form-label">Enter PIN to Unlock</label>
                        <input type="password" name="pin" class="form-control form-control-lg pin-input" maxlength="6" pattern="\d{4,6}" placeholder="••••" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill">Unlock Profile</button>
                </form>
            <?php endif; ?>

            <?php if ($isLoggedIn): ?>
                <hr class="my-4">
                <h6 class="text-center text-muted mb-3">Admin: Reset PIN</h6>
                <form method="POST">
                    <input type="hidden" name="action" value="reset_pin">
                    <div class="input-group">
                        <input type="password" name="new_pin" class="form-control" placeholder="New PIN (4-6 digits)" pattern="\d{4,6}" required>
                        <button type="submit" class="btn btn-warning"><i class="bi bi-key"></i> Reset</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($employee && $pinVerified): ?>
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header-card">
                <?php if ($employee['photo']): ?>
                    <img src="../uploads/photos/<?php echo htmlspecialchars($employee['photo']); ?>" class="profile-img">
                <?php elseif (!empty($companyLogo)): ?>
                    <img src="../uploads/<?php echo htmlspecialchars($companyLogo); ?>" class="profile-img">
                <?php else: ?>
                    <div class="profile-img mx-auto d-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.2);"><i class="bi bi-person" style="font-size: 2.5rem; color: #fff;"></i></div>
                <?php endif; ?>
                <h2 class="profile-name"><?php echo htmlspecialchars($employee['emp_name']); ?></h2>
                <div class="profile-meta">
                    <span class="badge bg-light text-dark me-2"><?php echo generateEmployeeID($employee['id'], $employee['office_code'], $employee['dept_code']); ?></span>
                    <span class="badge bg-<?php echo $employee['status'] === 'Active' ? 'success' : 'danger'; ?>"><?php echo $employee['status']; ?></span>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon mx-auto mb-2" style="background: rgba(6,214,160,0.1); color: var(--success);"><i class="bi bi-piggy-bank"></i></div>
                        <div class="stat-label">PF Balance</div>
                        <div class="stat-value"><?php echo formatCurrency($pfBalance); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon mx-auto mb-2" style="background: rgba(67,97,238,0.1); color: var(--primary);"><i class="bi bi-currency-dollar"></i></div>
                        <div class="stat-label">Basic Salary</div>
                        <div class="stat-value"><?php echo formatCurrency($employee['basic_salary']); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon mx-auto mb-2" style="background: rgba(255,209,102,0.1); color: var(--warning);"><i class="bi bi-percent"></i></div>
                        <div class="stat-label">PF Rate</div>
                        <div class="stat-value"><?php echo $employee['pf_percentage']; ?>%</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon mx-auto mb-2" style="background: rgba(239,71,111,0.1); color: var(--danger);"><i class="bi bi-calendar-check"></i></div>
                        <div class="stat-label">Age</div>
                        <div class="stat-value"><?php echo calculateAge($employee['dob']); ?>y</div>
                    </div>
                </div>
            </div>

            <!-- Job & Personal Details -->
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="section-card h-100">
                        <div class="section-header"><i class="bi bi-briefcase text-primary"></i> Job Details</div>
                        <div class="section-body">
                            <div class="info-row"><span class="info-label">Office</span><span class="info-value"><?php echo htmlspecialchars($employee['office_name']); ?></span></div>
                            <div class="info-row"><span class="info-label">Department</span><span class="info-value"><?php echo htmlspecialchars($employee['department']); ?></span></div>
                            <div class="info-row"><span class="info-label">Position</span><span class="info-value"><?php echo htmlspecialchars($employee['position']); ?></span></div>
                            <div class="info-row"><span class="info-label">Unit</span><span class="info-value"><?php echo htmlspecialchars($employee['unit'] ?? '-'); ?></span></div>
                            <div class="info-row"><span class="info-label">Joined</span><span class="info-value"><?php echo $employee['joining_date'] ? date('d M Y', strtotime($employee['joining_date'])) : '-'; ?></span></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="section-card h-100">
                        <div class="section-header"><i class="bi bi-person text-success"></i> Personal Details</div>
                        <div class="section-body">
                            <div class="info-row"><span class="info-label">Date of Birth</span><span class="info-value"><?php echo $employee['dob'] ? date('d M Y', strtotime($employee['dob'])) : '-'; ?></span></div>
                            <div class="info-row"><span class="info-label">Blood Group</span><span class="info-value"><?php echo htmlspecialchars($employee['blood_group'] ?? '-'); ?></span></div>
                            <div class="info-row"><span class="info-label">NID</span><span class="info-value"><?php echo htmlspecialchars($employee['nid'] ?? '-'); ?></span></div>
                            <div class="info-row"><span class="info-label">Official Phone</span><span class="info-value"><?php echo htmlspecialchars($employee['official_phone'] ?? '-'); ?></span></div>
                            <div class="info-row"><span class="info-label">Personal Phone</span><span class="info-value"><?php echo htmlspecialchars($employee['personal_phone'] ?? '-'); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank Info -->
            <div class="section-card mb-3">
                <div class="section-header"><i class="bi bi-bank text-warning"></i> Bank Information</div>
                <div class="section-body">
                    <div class="row">
                        <div class="col-6"><div class="info-row"><span class="info-label">Bank Name</span><span class="info-value"><?php echo htmlspecialchars($employee['bank_name'] ?? '-'); ?></span></div></div>
                        <div class="col-6"><div class="info-row"><span class="info-label">Account No</span><span class="info-value"><?php echo htmlspecialchars($employee['bank_account'] ?? '-'); ?></span></div></div>
                    </div>
                </div>
            </div>

            <!-- Salary History -->
            <?php if (!empty($salaryHistory)): ?>
            <div class="section-card mb-3">
                <div class="section-header"><i class="bi bi-cash-stack text-success"></i> Salary History</div>
                <div class="section-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Month</th><th>Days</th><th>Gross</th><th>PF</th><th>Net</th></tr></thead>
                            <tbody>
                                <?php foreach ($salaryHistory as $salary): ?>
                                <tr>
                                    <td><?php echo date('M Y', strtotime($salary['month'].'-01')); ?></td>
                                    <td><span class="badge bg-info"><?php echo $salary['present_days']; ?>P</span> <span class="badge bg-warning"><?php echo $salary['leave_days']; ?>L</span></td>
                                    <td class="fw-bold"><?php echo formatCurrency($salary['gross_salary']); ?></td>
                                    <td class="text-danger"><?php echo formatCurrency($salary['pf_deduction']); ?></td>
                                    <td class="fw-bold text-success"><?php echo formatCurrency($salary['net_payable']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Bonus History -->
            <?php if (!empty($bonusHistory)): ?>
            <div class="section-card mb-3">
                <div class="section-header"><i class="bi bi-gift text-warning"></i> Bonus History</div>
                <div class="section-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Month</th><th>Type</th><th>Amount</th><th>Remarks</th></tr></thead>
                            <tbody>
                                <?php foreach ($bonusHistory as $bonus): ?>
                                <tr>
                                    <td><?php echo date('M Y', strtotime($bonus['month'].'-01')); ?></td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($bonus['bonus_type']); ?></span></td>
                                    <td class="fw-bold text-success"><?php echo formatCurrency($bonus['bonus_amount']); ?></td>
                                    <td><small><?php echo htmlspecialchars($bonus['remarks'] ?? '-'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Loan History -->
            <?php if (!empty($loanHistory)): ?>
            <div class="section-card mb-3">
                <div class="section-header"><i class="bi bi-credit-card text-danger"></i> Loan History</div>
                <div class="section-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Balance</th><th>Remarks</th></tr></thead>
                            <tbody>
                                <?php
                                $runningBalance = 0;
                                foreach ($loanHistory as $loan):
                                    if ($loan['type'] === 'debit') {
                                        $runningBalance += $loan['amount'];
                                    } else {
                                        $runningBalance -= $loan['amount'];
                                    }
                                ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($loan['transaction_date'])); ?></td>
                                    <td><span class="badge bg-<?php echo $loan['type'] === 'debit' ? 'danger' : 'success'; ?>"><?php echo ucfirst($loan['type']); ?></span></td>
                                    <td class="fw-bold"><?php echo formatCurrency($loan['amount']); ?></td>
                                    <td class="text-<?php echo $runningBalance > 0 ? 'danger' : 'success'; ?>"><?php echo formatCurrency($runningBalance); ?></td>
                                    <td><small><?php echo htmlspecialchars($loan['remarks'] ?? '-'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Employment History -->
            <?php if (!empty($empHistory)): ?>
            <div class="section-card mb-3">
                <div class="section-header"><i class="bi bi-clock-history text-info"></i> Employment History</div>
                <div class="section-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Event</th><th>Date</th><th>Remarks</th></tr></thead>
                            <tbody>
                                <?php foreach ($empHistory as $h): ?>
                                <tr>
                                    <td><span class="badge bg-<?php echo $h['event_type'] === 'Joined' ? 'primary' : ($h['event_type'] === 'Rejoined' ? 'success' : 'danger'); ?>"><?php echo $h['event_type']; ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($h['event_date'])); ?></td>
                                    <td><small><?php echo htmlspecialchars($h['remarks']); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- PF Transactions -->
            <?php if (!empty($pfHistory)): ?>
            <div class="section-card mb-3">
                <div class="section-header"><i class="bi bi-graph-up text-success"></i> PF Transactions</div>
                <div class="section-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Description</th></tr></thead>
                            <tbody>
                                <?php foreach ($pfHistory as $pf): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($pf['transaction_date'])); ?></td>
                                    <td><span class="badge bg-<?php echo $pf['type']==='credit'?'success':'danger'; ?>"><?php echo ucfirst($pf['type']); ?></span></td>
                                    <td class="fw-bold"><?php echo formatCurrency($pf['amount']); ?></td>
                                    <td><small><?php echo htmlspecialchars($pf['description'] ?? '-'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="text-center my-4 no-print">
                <button onclick="window.print()" class="btn btn-primary rounded-pill px-4"><i class="bi bi-printer me-2"></i> Print Profile</button>
                <a href="?" class="btn btn-outline-light rounded-pill px-4 ms-2"><i class="bi bi-arrow-left me-2"></i> Back to Search</a>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>