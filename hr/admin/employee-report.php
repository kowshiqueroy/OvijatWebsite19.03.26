<?php
define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle   = 'Employee Service Report';
$currentPage = 'employee-report';

$conn = getDBConnection();

$companyName = getSetting('company_name') ?? 'HR Management';
$companyTagline = getSetting('company_tagline') ?? '';
$companyAddress = getSetting('company_address') ?? '';
$companyPhone = getSetting('company_phone') ?? '';
$companyLogo = getSetting('company_logo') ?? '';

// ── Load active employees ─────────────────────────────────────────────────────
$allEmployees = getAllEmployees(['status' => 'Active']);

// ── Defaults ──────────────────────────────────────────────────────────────────
$defaultFrom = date('Y-m', strtotime('-5 months'));
$defaultTo   = date('Y-m');

$submitted = isset($_GET['emp_ids']);
$empIds    = [];
$fromMonth = $_GET['from_month'] ?? $defaultFrom;
$toMonth   = $_GET['to_month']   ?? $defaultTo;

if ($submitted && !empty($_GET['emp_ids'])) {
    foreach ((array)$_GET['emp_ids'] as $id) {
        $id = (int)$id;
        if ($id > 0) $empIds[] = $id;
    }
}

$reportData = [];
if ($submitted && !empty($empIds)) {
    foreach ($empIds as $eid) {
        $emp = getEmployeeById($eid);
        if (!$emp) continue;

        // 1. Salaries
        $salaries = [];
        $stmt = $conn->prepare("SELECT * FROM salary_sheets WHERE employee_id = ? AND month >= ? AND month <= ? ORDER BY month DESC");
        $stmt->bind_param("iss", $eid, $fromMonth, $toMonth);
        $stmt->execute();
        $res = $stmt->get_result();
        while($r = $res->fetch_assoc()) $salaries[$r['month']] = $r;
        $stmt->close();

        // 2. Bonuses
        $bonuses = [];
        $bonusList = [];
        if (tableExists('bonus_sheets')) {
            $stmt = $conn->prepare("SELECT * FROM bonus_sheets WHERE employee_id = ? AND month >= ? AND month <= ? ORDER BY month DESC");
            $stmt->bind_param("iss", $eid, $fromMonth, $toMonth);
            $stmt->execute();
            $res = $stmt->get_result();
            while($r = $res->fetch_assoc()) {
                $bonuses[$r['month']][] = $r;
                $bonusList[] = $r;
            }
            $stmt->close();
        }

        // 3. Loans
        $loan = ['debited' => 0, 'repaid' => 0, 'balance' => 0, 'list' => []];
        if (tableExists('loan_transactions')) {
            $stmt = $conn->prepare("SELECT SUM(CASE WHEN type='debit' THEN amount ELSE 0 END) as d, SUM(CASE WHEN type='credit' THEN amount ELSE 0 END) as r FROM loan_transactions WHERE employee_id = ?");
            $stmt->bind_param("i", $eid);
            $stmt->execute();
            $lSum = $stmt->get_result()->fetch_assoc();
            $loan['debited'] = (float)$lSum['d'];
            $loan['repaid'] = (float)$lSum['r'];
            $loan['balance'] = $loan['debited'] - $loan['repaid'];
            $stmt->close();

            $stmt = $conn->prepare("SELECT * FROM loan_transactions WHERE employee_id = ? ORDER BY transaction_date DESC LIMIT 10");
            $stmt->bind_param("i", $eid);
            $stmt->execute();
            $res = $stmt->get_result();
            while($r = $res->fetch_assoc()) $loan['list'][] = $r;
            $stmt->close();
        }

        // 4. PF
        $pf = ['salary' => 0, 'manual' => 0, 'balance' => 0, 'list' => []];
        $stmt = $conn->prepare("SELECT SUM(pf_deduction) as s FROM salary_sheets WHERE employee_id = ? AND confirmed = 1");
        $stmt->bind_param("i", $eid);
        $stmt->execute();
        $pf['salary'] = (float)$stmt->get_result()->fetch_assoc()['s'];
        $stmt->close();

        if (tableExists('pf_transactions')) {
            $stmt = $conn->prepare("SELECT * FROM pf_transactions WHERE employee_id = ? ORDER BY transaction_date DESC LIMIT 10");
            $stmt->bind_param("i", $eid);
            $stmt->execute();
            $res = $stmt->get_result();
            while($r = $res->fetch_assoc()) {
                $pf['list'][] = $r;
                $pf['manual'] += ($r['type'] === 'credit' ? (float)$r['amount'] : -(float)$r['amount']);
            }
            $stmt->close();
        }
        $pf['balance'] = $pf['salary'] + $pf['manual'];

        // 5. History
        $history = [];
        if (tableExists('employment_history')) {
            $stmt = $conn->prepare("SELECT * FROM employment_history WHERE employee_id = ? ORDER BY event_date DESC");
            $stmt->bind_param("i", $eid);
            $stmt->execute();
            $res = $stmt->get_result();
            while($r = $res->fetch_assoc()) $history[] = $r;
            $stmt->close();
        }

        $allMonths = array_unique(array_merge(array_keys($salaries), array_keys($bonuses)));
        rsort($allMonths);

        $reportData[$eid] = [
            'info' => $emp,
            'months' => $allMonths,
            'salaries' => $salaries,
            'bonuses' => $bonuses,
            'bonus_list' => $bonusList,
            'loan' => $loan,
            'pf' => $pf,
            'history' => $history
        ];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Modern UI Styles */
:root { --report-border: #e2e8f0; --report-bg: #f8fafc; --report-text: #1e293b; --report-accent: #3b82f6; }
.report-card { background: white; border: 1px solid var(--report-border); border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
.section-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; border-bottom: 2px solid var(--report-accent); display: inline-block; margin-bottom: 15px; padding-bottom: 2px; }
.stat-box { background: var(--report-bg); border-radius: 8px; padding: 12px; border: 1px solid var(--report-border); }
.stat-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; }
.stat-value { font-size: 1rem; font-weight: 700; color: var(--report-text); }
.stat-value.danger { color: #ef4444; }
.stat-value.success { color: #10b981; }

.modern-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.modern-table th { background: #f1f5f9; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 0.7rem; padding: 10px 8px; text-align: center; border-bottom: 2px solid var(--report-border); }
.modern-table td { padding: 10px 8px; border-bottom: 1px solid var(--report-border); vertical-align: middle; }
.modern-table tr:last-child td { border-bottom: none; }
.modern-table .text-end { text-align: right; }
.modern-table .text-center { text-align: center; }

.history-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; }
.badge-joined { background: #dcfce7; color: #15803d; }
.badge-resigned { background: #fef9c3; color: #854d0e; }
.badge-terminated { background: #fee2e2; color: #b91c1c; }
.badge-rejoined { background: #dbeafe; color: #1d4ed8; }

.print-only { display: none; }

@media print {
    @page { size: A4 portrait; margin: 10mm; }
    
    /* Hide ALL UI elements including sidebar and headers from templates */
    .sidebar, .navbar, .no-print, .page-header, .card.no-print, footer { 
        display: none !important; 
    }
    
    .print-only { display: block !important; }
    
    /* Reset main content layout for print */
    .main-content { 
        margin-left: 0 !important; 
        padding: 0 !important; 
        width: 100% !important; 
    }
    
    body { 
        background: white !important; 
        font-family: 'Inter', 'Segoe UI', sans-serif; 
        color: black; 
    }
    
    .report-card { 
        box-shadow: none !important; 
        border: none !important; 
        padding: 0 !important; 
        margin: 0 !important; 
        border-radius: 0 !important;
        page-break-after: always;
    }
    
    .report-card:last-child {
        page-break-after: auto;
    }

    .section-title { 
        border-bottom: 1px solid #000; 
        color: black; 
        margin-top: 10px; 
        margin-bottom: 8px;
    }
    
    .stat-box { 
        border: 1px solid #ddd; 
        background: #fff; 
        padding: 8px;
    }
    
    .modern-table th { 
        background: #eee !important; 
        border-bottom: 1px solid #000; 
        -webkit-print-color-adjust: exact; 
        font-size: 8px;
        padding: 4px;
    }
    
    .modern-table td { 
        border-bottom: 1px solid #eee; 
        font-size: 8px;
        padding: 4px;
    }
    
    .report-header-print { 
        text-align: center; 
        margin-bottom: 15px; 
        border-bottom: 2px solid #000; 
        padding-bottom: 8px; 
    }

    /* Prevent sections from breaking awkwardly */
    .row, .table-responsive, .stat-box {
        break-inside: avoid;
    }
}
</style>

<div class="page-header d-flex justify-content-between align-items-center no-print">
    <div>
        <h4 class="mb-1"><i class="bi bi-person-lines-fill me-2"></i>Service Reports</h4>
        <small class="text-muted">Dynamic employee history & financial summary</small>
    </div>
    <?php if (!empty($reportData)): ?>
        <button onclick="window.print()" class="btn btn-primary shadow-sm"><i class="bi bi-printer me-2"></i>Print All</button>
    <?php endif; ?>
</div>

<!-- Filter Form -->
<div class="card mb-4 no-print border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold">1. Select Employee(s)</label>
                    <input type="text" id="empSearch" class="form-control form-control-sm mb-2" placeholder="Start typing name...">
                    <select name="emp_ids[]" id="empSelect" class="form-select shadow-none" multiple style="height: 120px;" required>
                        <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo in_array($emp['id'], $empIds) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['emp_name']); ?> (<?php echo $emp['office_code']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">2. Date Range</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">From</span>
                        <input type="month" name="from_month" class="form-control" value="<?php echo $fromMonth; ?>">
                    </div>
                    <div class="input-group input-group-sm mt-2">
                        <span class="input-group-text">To</span>
                        <input type="month" name="to_month" class="form-control" value="<?php echo $toMonth; ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark w-100 btn-sm py-2"><i class="bi bi-funnel me-2"></i>Generate</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($submitted && !empty($reportData)): ?>
    <?php foreach ($reportData as $eid => $data): ?>
    <div class="report-card <?php echo count($reportData) > 1 ? 'page-break' : ''; ?>">
        
        <!-- Print Header -->
        <div class="print-only report-header-print">
            <?php if (!empty($companyLogo)): ?>
                <img src="../uploads/<?php echo htmlspecialchars($companyLogo); ?>" style="height:40px; margin-bottom:5px;">
            <?php endif; ?>
            <h3 class="mb-0"><?php echo htmlspecialchars($companyName); ?></h3>
            <p class="small mb-0"><?php echo htmlspecialchars($companyAddress); ?></p>
            <h5 class="mt-3 text-decoration-underline">EMPLOYEE SERVICE & FINANCIAL REPORT</h5>
        </div>

        <!-- Basic Info Banner -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h4 class="mb-0 fw-bold"><?php echo htmlspecialchars($data['info']['emp_name']); ?></h4>
                <div class="text-muted small">
                    <span class="badge bg-dark me-2"><?php echo generateEmployeeID($data['info']['id'], $data['info']['office_code'], $data['info']['dept_code']); ?></span>
                    <?php echo htmlspecialchars($data['info']['position']); ?> | <?php echo htmlspecialchars($data['info']['department']); ?>
                </div>
            </div>
            <div class="text-end no-print">
                <div class="small text-muted">Reporting Period</div>
                <div class="fw-bold"><?php echo date('M Y', strtotime($fromMonth.'-01')); ?> - <?php echo date('M Y', strtotime($toMonth.'-01')); ?></div>
            </div>
        </div>

        <!-- 1. Salary & Attendance -->
        <div class="section-title">Salary & Attendance History</div>
        <div class="table-responsive mb-4">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>W/P/L</th>
                        <th class="text-end">Basic</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">PF Ded</th>
                        <th class="text-end">Bonus</th>
                        <th class="text-end">Net Pay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totals = ['basic'=>0, 'gross'=>0, 'pf'=>0, 'bonus'=>0, 'net'=>0];
                    foreach ($data['months'] as $m): 
                        $s = $data['salaries'][$m] ?? null;
                        $mBonus = array_sum(array_column($data['bonuses'][$m] ?? [], 'bonus_amount'));
                        if ($s) {
                            $totals['basic'] += $s['basic_salary'];
                            $totals['gross'] += $s['gross_salary'];
                            $totals['pf']    += $s['pf_deduction'];
                            $totals['net']   += $s['net_payable'];
                        }
                        $totals['bonus'] += $mBonus;
                    ?>
                    <tr>
                        <td class="text-center fw-bold"><?php echo date('M Y', strtotime($m.'-01')); ?></td>
                        <td class="text-center"><?php echo $s ? "{$s['working_days']} / {$s['present_days']} / {$s['leave_days']}" : '-'; ?></td>
                        <td class="text-end"><?php echo $s ? number_format($s['basic_salary'],0) : '-'; ?></td>
                        <td class="text-end"><?php echo $s ? number_format($s['gross_salary'],0) : '-'; ?></td>
                        <td class="text-end text-danger"><?php echo $s ? number_format($s['pf_deduction'],0) : '-'; ?></td>
                        <td class="text-end text-success"><?php echo $mBonus > 0 ? number_format($mBonus,0) : '-'; ?></td>
                        <td class="text-end fw-bold"><?php echo number_format(($s ? $s['net_payable'] : 0) + $mBonus, 0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="fw-bold" style="border-top: 2px solid #000;">
                    <tr>
                        <td colspan="2">PERIOD TOTALS</td>
                        <td class="text-end"><?php echo number_format($totals['basic'],0); ?></td>
                        <td class="text-end"><?php echo number_format($totals['gross'],0); ?></td>
                        <td class="text-end"><?php echo number_format($totals['pf'],0); ?></td>
                        <td class="text-end"><?php echo number_format($totals['bonus'],0); ?></td>
                        <td class="text-end"><?php echo number_format($totals['net'] + $totals['bonus'], 0); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- 2. Financial Breakdowns -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="section-title">Loan Summary</div>
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-label">Total Given</div>
                            <div class="stat-value"><?php echo number_format($data['loan']['debited'],0); ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-label">Total Repaid</div>
                            <div class="stat-value success"><?php echo number_format($data['loan']['repaid'],0); ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-label">Outstanding</div>
                            <div class="stat-value danger"><?php echo number_format($data['loan']['balance'],0); ?></div>
                        </div>
                    </div>
                </div>
                <?php if(!empty($data['loan']['list'])): ?>
                <table class="modern-table" style="font-size: 0.7rem;">
                    <thead><tr><th>Date</th><th>Type</th><th>Amt</th><th>Note</th></tr></thead>
                    <tbody>
                        <?php foreach(array_slice($data['loan']['list'], 0, 5) as $l): ?>
                        <tr>
                            <td><?php echo date('d M y', strtotime($l['transaction_date'])); ?></td>
                            <td><?php echo $l['type']; ?></td>
                            <td class="text-end"><?php echo number_format($l['amount'], 0); ?></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($l['description']); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <div class="col-md-6">
                <div class="section-title">Provident Fund Summary</div>
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-label">From Salary</div>
                            <div class="stat-value"><?php echo number_format($data['pf']['salary'],0); ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-label">Manual/Other</div>
                            <div class="stat-value"><?php echo number_format($data['pf']['manual'],0); ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-label">Net Balance</div>
                            <div class="stat-value success"><?php echo number_format($data['pf']['balance'],0); ?></div>
                        </div>
                    </div>
                </div>
                <?php if(!empty($data['pf']['list'])): ?>
                <table class="modern-table" style="font-size: 0.7rem;">
                    <thead><tr><th>Date</th><th>Type</th><th>Amt</th><th>Note</th></tr></thead>
                    <tbody>
                        <?php foreach(array_slice($data['pf']['list'], 0, 5) as $p): ?>
                        <tr>
                            <td><?php echo date('d M y', strtotime($p['transaction_date'])); ?></td>
                            <td><?php echo $p['type']; ?></td>
                            <td class="text-end"><?php echo number_format($p['amount'], 0); ?></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($p['description']); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. Service History & Bonuses -->
        <div class="row g-3">
            <div class="col-md-5">
                <div class="section-title">Service History</div>
                <?php if (!empty($data['history'])): ?>
                <table class="modern-table">
                    <thead><tr><th>Date</th><th>Event</th><th>Note</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['history'] as $h): ?>
                        <tr>
                            <td><?php echo date('d M y', strtotime($h['event_date'])); ?></td>
                            <td><span class="history-badge badge-<?php echo strtolower($h['event_type']); ?>"><?php echo $h['event_type']; ?></span></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($h['remarks']); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-muted small">No history records found.</p>
                <?php endif; ?>
            </div>
            
            <div class="col-md-7">
                <div class="section-title">Recent Bonuses</div>
                <?php if (!empty($data['bonus_list'])): ?>
                <table class="modern-table">
                    <thead><tr><th>Month</th><th>Type</th><th>Amt</th><th>Description</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($data['bonus_list'],0,5) as $b): ?>
                        <tr>
                            <td><?php echo date('M Y', strtotime($b['month'].'-01')); ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($b['bonus_type']); ?></td>
                            <td class="text-end"><?php echo number_format($b['bonus_amount'], 0); ?></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($b['description'] ?? ''); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-muted small">No bonus records in this period.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Signatures -->
        <div class="print-only mt-5 pt-4">
            <div class="d-flex justify-content-between text-center">
                <div style="width: 150px; border-top: 1px solid #000; font-size: 10px; padding-top: 5px;">Report Prepared By</div>
                <div style="width: 150px; border-top: 1px solid #000; font-size: 10px; padding-top: 5px;">Accounts In-charge</div>
                <div style="width: 150px; border-top: 1px solid #000; font-size: 10px; padding-top: 5px;">Approved By (MD)</div>
            </div>
        </div>

    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-file-earmark-person fs-1 text-muted opacity-25 d-block mb-3"></i>
            <h5 class="text-muted">Select an employee and date range to see the report</h5>
        </div>
    </div>
<?php endif; ?>

<script>
document.getElementById('empSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    Array.from(document.getElementById('empSelect').options).forEach(opt => {
        opt.style.display = opt.text.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>