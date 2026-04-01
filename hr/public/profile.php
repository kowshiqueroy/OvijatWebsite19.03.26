<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$isLoggedIn = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);

$companyName = getSetting('company_name') ?? 'Company';
$companyLogo = getSetting('company_logo') ?? '';
$companyTagline = getSetting('company_tagline') ?? '';

$employee = null;
$searchId = $_GET['id'] ?? '';
$error = '';

if (!empty($searchId)) {
    $employee = getEmployeeByPublicId($searchId);
    if (!$employee) {
        $error = 'Employee not found';
    }
}

$pfBalance = 0;
$salaryHistory = [];
$pfHistory = [];
if ($employee) {
    $pfBalance = calculatePFBalance($employee['id']);
}
if ($employee && $isLoggedIn) {
    $salaryHistory = getSalarySheets($employee['id'], null, []);
    $conn = getDBConnection();
    $tableCheck = $conn->query("SHOW TABLES LIKE 'pf_transactions'");
    if ($tableCheck->num_rows > 0) {
        $stmt = $conn->prepare("SELECT * FROM pf_transactions WHERE employee_id = ? ORDER BY transaction_date DESC LIMIT 10");
        $stmt->bind_param("i", $employee['id']);
        $stmt->execute();
        $pfResult = $stmt->get_result();
        while ($row = $pfResult->fetch_assoc()) {
            $pfHistory[] = $row;
        }
        $stmt->close();
    }
}

$pageTitle = $employee ? $employee['emp_name'] : 'Employee Profile';
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
        :root { --primary: #2563eb; --primary-dark: #1d4ed8; --secondary: #64748b; --success: #10b981; --light: #f8fafc; --dark: #1e293b; }
        body { min-height: 100vh; background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%); padding: 15px; font-family: 'Segoe UI', system-ui, sans-serif; }
        .brand-bar { text-align: center; padding: 20px 0; margin-bottom: 20px; }
        .brand-bar img { height: 50px; width: auto; }
        .brand-bar h3 { color: #fff; margin: 10px 0 0; font-weight: 600; font-size: 1.4rem; }
        .brand-bar p { color: rgba(255,255,255,0.6); margin: 5px 0 0; font-size: 0.85rem; }
        .search-wrapper { max-width: 400px; margin: 0 auto 25px; position: relative; }
        .search-wrapper .form-control { border: none; border-radius: 50px; padding: 14px 20px 14px 50px; box-shadow: 0 4px 25px rgba(0,0,0,0.3); font-size: 0.95rem; }
        .search-wrapper .search-icon { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: var(--secondary); }
        .profile-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); overflow: hidden; max-width: 210mm; margin: 0 auto; }
        .profile-header { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); padding: 25px; text-align: center; }
        .profile-img { width: 90px; height: 90px; border-radius: 50%; border: 3px solid #fff; object-fit: cover; box-shadow: 0 4px 15px rgba(0,0,0,0.2); background: #fff; }
        .profile-name { color: #fff; font-size: 1.4rem; font-weight: 700; margin-top: 10px; }
        .profile-id { color: rgba(255,255,255,0.85); font-size: 0.85rem; background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 20px; display: inline-block; margin-top: 5px; }
        .profile-body { padding: 20px; }
        .pf-box { background: linear-gradient(135deg, var(--success) 0%, #059669 100%); border-radius: 12px; padding: 18px; color: #fff; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
        .pf-box .pf-label { font-size: 0.8rem; opacity: 0.9; }
        .pf-box .pf-amount { font-size: 1.8rem; font-weight: 700; }
        .pf-box .pf-rate { font-size: 0.75rem; opacity: 0.8; }
        .info-section { margin-bottom: 15px; }
        .info-section h6 { color: var(--primary); font-weight: 600; font-size: 0.9rem; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #e2e8f0; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
        .info-item { padding: 3px 0; }
        .info-label { font-size: 0.7rem; color: var(--secondary); text-transform: uppercase; }
        .info-value { font-size: 0.85rem; color: var(--dark); font-weight: 500; }
        .salary-table { font-size: 0.8rem; margin-top: 10px; }
        .salary-table th { background: var(--light); font-weight: 600; font-size: 0.7rem; padding: 8px 5px; }
        .salary-table td { padding: 6px 5px; }
        .back-btn { position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; text-decoration: none; box-shadow: 0 4px 15px rgba(37,99,235,0.4); transition: transform 0.2s; }
        .back-btn:hover { transform: scale(1.1); }
        @media (max-width: 576px) { .profile-card { border-radius: 12px; } .profile-header { padding: 20px 15px; } .profile-img { width: 70px; height: 70px; } .profile-name { font-size: 1.2rem; } .info-grid { grid-template-columns: 1fr 1fr; } }
        @media print { @page { size: A4; margin: 0.5cm; } body { background: #fff !important; padding: 0 !important; } .profile-card { box-shadow: none; max-width: 100%; border: 1px solid #ddd; } .no-print, .back-btn, .brand-bar { display: none !important; } .info-grid { grid-template-columns: repeat(3, 1fr); } }
    </style>
</head>
<body>
    <div class="brand-bar">
        <?php if (!empty($companyLogo)): ?>
            <img src="../uploads/<?php echo htmlspecialchars($companyLogo); ?>" alt="Logo">
        <?php endif; ?>
        <h3><?php echo htmlspecialchars($companyName); ?></h3>
        <?php if (!empty($companyTagline)): ?>
            <p><?php echo htmlspecialchars($companyTagline); ?></p>
        <?php endif; ?>
    </div>

    <?php if (!$employee): ?>
        <div class="search-wrapper">
            <i class="bi bi-search search-icon"></i>
            <form method="GET" action="">
                <input type="text" name="id" class="form-control" placeholder="Search by Employee ID (e.g. HQ-IT-0001)" value="<?php echo htmlspecialchars($searchId); ?>">
            </form>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger text-center mx-auto" style="max-width: 350px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
    <?php else: ?>
        <div class="profile-card">
            <div class="profile-header">
                <?php if ($employee['photo']): ?>
                    <img src="../uploads/photos/<?php echo htmlspecialchars($employee['photo']); ?>" class="profile-img">
                <?php elseif (!empty($companyLogo)): ?>
                    <img src="../uploads/<?php echo htmlspecialchars($companyLogo); ?>" class="profile-img">
                <?php else: ?>
                    <div class="profile-img" style="display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:2rem;color:#ccc;"><i class="bi bi-person"></i></div>
                <?php endif; ?>
                <h2 class="profile-name"><?php echo htmlspecialchars($employee['emp_name']); ?></h2>
                <span class="profile-id"><?php echo generateEmployeeID($employee['id'], $employee['office_code'], $employee['dept_code']); ?></span>
                <span class="badge bg-light text-dark ms-2"><?php echo $employee['status']; ?></span>
                <?php if ($isLoggedIn): ?><span class="badge bg-warning ms-1">Admin</span><?php endif; ?>
            </div>
            <div class="profile-body">
                <div class="pf-box">
                    <div>
                        <div class="pf-label">Accumulated PF</div>
                        <div class="pf-amount"><?php echo formatCurrency($pfBalance); ?></div>
                    </div>
                    <div class="text-end">
                        <div class="pf-rate"><?php echo $employee['pf_percentage']; ?>% contribution</div>
                        <i class="bi bi-piggy-bank fs-2" style="opacity: 0.5;"></i>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="info-section">
                            <h6><i class="bi bi-briefcase me-2"></i>Job Details</h6>
                            <div class="info-grid">
                                <div class="info-item"><div class="info-label">Office</div><div class="info-value"><?php echo htmlspecialchars($employee['office_name']); ?></div></div>
                                <div class="info-item"><div class="info-label">Department</div><div class="info-value"><?php echo htmlspecialchars($employee['department']); ?></div></div>
                                <div class="info-item"><div class="info-label">Position</div><div class="info-value"><?php echo htmlspecialchars($employee['position']); ?></div></div>
                                <div class="info-item"><div class="info-label">Type</div><div class="info-value"><?php echo $employee['employee_type']; ?></div></div>
                                <div class="info-item"><div class="info-label">Joined</div><div class="info-value"><?php echo $employee['joining_date'] ? date('d M Y', strtotime($employee['joining_date'])) : '-'; ?></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-section">
                            <h6><i class="bi bi-person me-2"></i>Personal</h6>
                            <div class="info-grid">
                                <div class="info-item"><div class="info-label">Age</div><div class="info-value"><?php echo calculateAge($employee['dob']); ?> yrs</div></div>
                                <div class="info-item"><div class="info-label">DOB</div><div class="info-value"><?php echo $employee['dob'] ? ($isLoggedIn ? date('d M Y', strtotime($employee['dob'])) : date('Y', strtotime($employee['dob']))) : '-'; ?></div></div>
                                <?php if ($isLoggedIn): ?>
                                <div class="info-item"><div class="info-label">NID</div><div class="info-value"><?php echo htmlspecialchars($employee['nid'] ?? '-'); ?></div></div>
                                <div class="info-item"><div class="info-label">Blood</div><div class="info-value"><?php echo htmlspecialchars($employee['blood_group'] ?? '-'); ?></div></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($isLoggedIn): ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="info-section"><h6><i class="bi bi-bank me-2"></i>Bank Info</h6>
                            <div class="info-grid">
                                <div class="info-item"><div class="info-label">Bank</div><div class="info-value"><?php echo htmlspecialchars($employee['bank_name'] ?? '-'); ?></div></div>
                                <div class="info-item"><div class="info-label">Account</div><div class="info-value"><?php echo htmlspecialchars($employee['bank_account'] ?? '-'); ?></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-section"><h6><i class="bi bi-cash me-2"></i>Salary</h6>
                            <div class="info-grid">
                                <div class="info-item"><div class="info-label">Basic</div><div class="info-value"><?php echo formatCurrency($employee['basic_salary']); ?></div></div>
                                <div class="info-item"><div class="info-label">PF Rate</div><div class="info-value"><?php echo $employee['pf_percentage']; ?>%</div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($isLoggedIn && !empty($salaryHistory)): ?>
                <div class="info-section">
                    <h6><i class="bi bi-clock-history me-2"></i>Salary (All)</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered salary-table">
                            <thead><tr><th>Month</th><th>W</th><th>P</th><th>L</th><th>A</th><th>Gross</th><th>PF</th><th>Net</th></tr></thead>
                            <tbody>
                                <?php foreach ($salaryHistory as $salary): ?>
                                <tr>
                                    <td><?php echo date('M y', strtotime($salary['month'].'-01')); ?></td>
                                    <td><?php echo $salary['working_days']; ?></td>
                                    <td><?php echo $salary['present_days']; ?></td>
                                    <td><?php echo $salary['leave_days']; ?></td>
                                    <td><?php echo $salary['absent_days']; ?></td>
                                    <td><?php echo formatCurrency($salary['gross_salary']); ?></td>
                                    <td><?php echo formatCurrency($salary['pf_deduction']); ?></td>
                                    <td class="fw-bold"><?php echo formatCurrency($salary['net_payable']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($isLoggedIn && !empty($pfHistory)): ?>
                <div class="info-section">
                    <h6><i class="bi bi-graph-up me-2"></i>PF Transactions</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered salary-table">
                            <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Desc</th></tr></thead>
                            <tbody>
                                <?php foreach ($pfHistory as $pf): ?>
                                <tr>
                                    <td><?php echo date('d M', strtotime($pf['transaction_date'])); ?></td>
                                    <td><span class="badge bg-<?php echo $pf['type']==='credit'?'success':'danger'; ?>"><?php echo $pf['type']; ?></span></td>
                                    <td><?php echo formatCurrency($pf['amount']); ?></td>
                                    <td><?php echo htmlspecialchars($pf['description'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <div class="text-center mt-3 no-print">
                    <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer me-1"></i> Print</button>
                </div>
            </div>
        </div>
        <a href="?" class="back-btn no-print"><i class="bi bi-arrow-left"></i></a>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>