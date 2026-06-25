<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Financial Reports';
$breadcrumbs = ['Finance' => null, 'Reports' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['finance.view']);

$pdo        = db();
$session_id = int_param('session_id',(int)setting('current_session_id',0),$_GET);
$type       = $_GET['type'] ?? 'daily';
$date_from  = $_GET['from'] ?? date('Y-m-01');
$date_to    = $_GET['to']   ?? date('Y-m-d');
$sessions   = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();

// ── Daily Cash Book ───────────────────────────────────────────────────────────
$dailyData = [];
if ($type === 'daily') {
    $stmt = $pdo->prepare("SELECT fp.payment_date as date, fc.category_name, SUM(fp.amount) as total FROM fee_payments fp JOIN fee_ledgers fl ON fl.id=fp.ledger_id JOIN fee_categories fc ON fc.id=fl.fee_category_id WHERE fp.payment_date BETWEEN :f AND :t GROUP BY fp.payment_date, fc.category_name ORDER BY fp.payment_date DESC");
    $stmt->execute([':f'=>$date_from,':t'=>$date_to]);
    foreach ($stmt->fetchAll() as $r) $dailyData[$r['date']][$r['category_name']] = $r['total'];

    $expStmt = $pdo->prepare("SELECT expense_date as date, ec.category_name, SUM(amount) as total FROM expenses e JOIN expense_categories ec ON ec.id=e.expense_category_id WHERE expense_date BETWEEN :f AND :t AND status='approved' GROUP BY expense_date, ec.category_name ORDER BY expense_date DESC");
    $expStmt->execute([':f'=>$date_from,':t'=>$date_to]);
    foreach ($expStmt->fetchAll() as $r) $dailyData[$r['date']]['_exp_'.$r['category_name']] = $r['total'];
    krsort($dailyData);
}

// ── Monthly Summary ───────────────────────────────────────────────────────────
$monthlyFee = $monthlyExp = [];
if ($type === 'monthly') {
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(payment_date,'%Y-%m') as mon, SUM(amount) as total FROM fee_payments WHERE YEAR(payment_date) = :yr GROUP BY mon ORDER BY mon");
    $yr = $_GET['year'] ?? date('Y');
    $stmt->execute([':yr'=>$yr]);
    foreach ($stmt->fetchAll() as $r) $monthlyFee[$r['mon']] = $r['total'];

    $stmt2 = $pdo->prepare("SELECT DATE_FORMAT(expense_date,'%Y-%m') as mon, SUM(amount) as total FROM expenses WHERE YEAR(expense_date)=:yr AND status='approved' GROUP BY mon ORDER BY mon");
    $stmt2->execute([':yr'=>$yr]);
    foreach ($stmt2->fetchAll() as $r) $monthlyExp[$r['mon']] = $r['total'];
}

// ── General Ledger ───────────────────────────────────────────────────────────
$ledgerEntries = [];
if ($type === 'ledger') {
    // 1. Fee Payments (Debit)
    $feeStmt = $pdo->prepare("
        SELECT fp.payment_date AS date, 
               CONCAT('Fee Payment — ', u.full_name, ' (Receipt #', fp.receipt_number, ')') AS description, 
               fp.amount AS amount, 
               'debit' AS entry_type, 
               fc.category_name AS category
        FROM fee_payments fp
        JOIN users u ON u.id = fp.student_id
        JOIN fee_ledgers fl ON fl.id = fp.ledger_id
        JOIN fee_categories fc ON fc.id = fl.fee_category_id
        WHERE fp.payment_date BETWEEN :from AND :to
    ");
    $feeStmt->execute([':from' => $date_from, ':to' => $date_to]);
    foreach ($feeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ledgerEntries[] = $row;
    }

    // 2. Non-Fee Incomes (Debit) (includes manual loan repayments)
    $incStmt = $pdo->prepare("
        SELECT i.income_date AS date, 
               CONCAT('Income: ', ic.category_name, ' — ', i.description) AS description, 
               i.amount AS amount, 
               'debit' AS entry_type, 
               ic.category_name AS category
        FROM incomes i
        JOIN income_categories ic ON ic.id = i.income_category_id
        WHERE i.income_date BETWEEN :from AND :to
    ");
    $incStmt->execute([':from' => $date_from, ':to' => $date_to]);
    foreach ($incStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ledgerEntries[] = $row;
    }

    // 3. Payroll-Deducted Loan Repayments (Debit)
    $payLoanStmt = $pdo->prepare("
        SELECT DATE(pr.created_at) AS date,
               CONCAT('Payroll Loan Deduction — ', u.full_name, ' (', DATE_FORMAT(pr.created_at, '%b %Y'), ')') AS description,
               pl.advance_deduction AS amount,
               'debit' AS entry_type,
               'Loan Repayment' AS category
        FROM payroll_lines pl
        JOIN payroll_runs pr ON pr.id = pl.payroll_run_id
        JOIN users u ON u.id = pl.staff_id
        WHERE pr.status = 'finalized' AND pl.advance_deduction > 0 AND DATE(pr.created_at) BETWEEN :from AND :to
    ");
    $payLoanStmt->execute([':from' => $date_from, ':to' => $date_to]);
    foreach ($payLoanStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ledgerEntries[] = $row;
    }

    // 4. Institutional Expenses (Credit)
    $expStmt = $pdo->prepare("
        SELECT e.expense_date AS date, 
               CONCAT('Expense: ', ec.category_name, ' — ', e.description) AS description, 
               e.amount AS amount, 
               'credit' AS entry_type, 
               ec.category_name AS category
        FROM expenses e
        JOIN expense_categories ec ON ec.id = e.expense_category_id
        WHERE e.status = 'approved' AND e.expense_date BETWEEN :from AND :to
    ");
    $expStmt->execute([':from' => $date_from, ':to' => $date_to]);
    foreach ($expStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ledgerEntries[] = $row;
    }

    // 5. Loan Disbursements (Credit)
    $loanStmt = $pdo->prepare("
        SELECT sl.disbursed_date AS date, 
               CONCAT('Loan Disbursed — ', u.full_name, ' (Installment: ', sl.monthly_installment, '/mo)') AS description, 
               sl.loan_amount AS amount, 
               'credit' AS entry_type, 
               'Loan Disbursement' AS category
        FROM staff_loans sl
        JOIN users u ON u.id = sl.staff_id
        WHERE sl.disbursed_date BETWEEN :from AND :to
    ");
    $loanStmt->execute([':from' => $date_from, ':to' => $date_to]);
    foreach ($loanStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ledgerEntries[] = $row;
    }

    // 6. Payroll Salaries Disbursed (Credit)
    $payDisbStmt = $pdo->prepare("
        SELECT DATE(pr.created_at) AS date,
               CONCAT('Salary Disbursed — ', u.full_name, ' (', DATE_FORMAT(pr.created_at, '%b %Y'), ')') AS description,
               (pl.net_salary + pl.advance_deduction) AS amount,
               'credit' AS entry_type,
               'Staff Payroll' AS category
        FROM payroll_lines pl
        JOIN payroll_runs pr ON pr.id = pl.payroll_run_id
        JOIN users u ON u.id = pl.staff_id
        WHERE pr.status = 'finalized' AND DATE(pr.created_at) BETWEEN :from AND :to
    ");
    $payDisbStmt->execute([':from' => $date_from, ':to' => $date_to]);
    foreach ($payDisbStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ledgerEntries[] = $row;
    }

    // 7. Custom Voucher Payments (Credit)
    $customPayStmt = $pdo->prepare("
        SELECT cp.payment_date AS date, 
               CONCAT('Custom Voucher Payment #', cp.id, ' — To: ', cp.payee_name, ' (', cp.payee_role, ') [', cp.payment_method, '] — ', cp.notes) AS description, 
               cp.amount AS amount, 
               'credit' AS entry_type, 
               'Custom Voucher' AS category
        FROM custom_payments cp
        WHERE cp.payment_date BETWEEN :from AND :to
    ");
    $customPayStmt->execute([':from' => $date_from, ':to' => $date_to]);
    foreach ($customPayStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ledgerEntries[] = $row;
    }

    // Sort all entries by date ascending (oldest first)
    usort($ledgerEntries, function($a, $b) {
        return strcmp($a['date'], $b['date']);
    });

    $accountsList = $pdo->query("SELECT * FROM accounts ORDER BY account_name")->fetchAll(PDO::FETCH_ASSOC);
}

// ── Outstanding Dues ──────────────────────────────────────────────────────────
$outstanding = [];
if ($type === 'outstanding') {
    $os = $pdo->prepare("SELECT c.class_name, COUNT(DISTINCT fl.student_id) as students, SUM(fl.amount_due - fl.amount_paid - fl.waiver_amount) as outstanding FROM fee_ledgers fl JOIN student_enrollments se ON se.student_id=fl.student_id AND se.session_id=fl.session_id AND se.status='active' JOIN classes c ON c.id=se.class_id WHERE fl.session_id=:sess AND fl.status != 'paid' GROUP BY c.id ORDER BY outstanding DESC");
    $os->execute([':sess'=>$session_id]);
    $outstanding = $os->fetchAll();
}

// ── Class-wise Collection Performance ─────────────────────────────────────────
$classPerf = [];
if ($type === 'class_perf') {
    $fee_cat_filter = int_param('fee_cat_id', 0, $_GET);
    $feeCategories  = $pdo->query('SELECT id,category_name FROM fee_categories WHERE status=1 ORDER BY category_name')->fetchAll();
    $tillDate       = $_GET['till_date'] ?? date('Y-m-d');

    $catWhere = $fee_cat_filter ? 'AND fl.fee_category_id = :cat' : '';
    $catPrams = $fee_cat_filter ? [':sess'=>$session_id,':till'=>$tillDate,':cat'=>$fee_cat_filter]
                                : [':sess'=>$session_id,':till'=>$tillDate];

    $stmt = $pdo->prepare("
        SELECT c.id AS class_id, c.class_name,
               sec.section_name,
               COUNT(DISTINCT se.student_id) AS enrolled,
               COUNT(DISTINCT CASE WHEN fl.status='paid' THEN fl.student_id END) AS fully_paid_students,
               COALESCE(SUM(fl.amount_due),0) AS total_due,
               COALESCE(SUM(fl.amount_paid),0) AS total_paid,
               COALESCE(SUM(fl.waiver_amount),0) AS total_waiver,
               COALESCE(SUM(fl.amount_due - fl.amount_paid - fl.waiver_amount),0) AS outstanding,
               COUNT(DISTINCT CASE WHEN (fl.amount_due - fl.amount_paid - fl.waiver_amount) > 0.01 THEN fl.student_id END) AS defaulters
        FROM student_enrollments se
        JOIN classes c ON c.id = se.class_id
        LEFT JOIN sections sec ON sec.id = se.section_id
        LEFT JOIN fee_ledgers fl ON fl.student_id = se.student_id
            AND fl.session_id = :sess
            AND fl.due_date <= :till
            $catWhere
        WHERE se.session_id = :sess AND se.status = 'active'
        GROUP BY c.id, c.class_name, sec.section_name
        ORDER BY c.display_order, c.class_name, sec.section_name
    ");
    $stmt->execute($catPrams);
    $classPerf = $stmt->fetchAll();
}

// ── Fee-type Breakdown ────────────────────────────────────────────────────────
$feeTypeBreakdown = [];
$feeTypeMonthly   = [];
if ($type === 'fee_breakdown') {
    $tillDate = $_GET['till_date'] ?? date('Y-m-d');
    $class_filter = int_param('class_id', 0, $_GET);
    $allClasses   = $pdo->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY display_order,class_name')->fetchAll();

    $bWhere  = 'fl.session_id=:sess AND fl.due_date <= :till';
    $bPrams  = [':sess'=>$session_id,':till'=>$tillDate];
    if ($class_filter) { $bWhere .= ' AND se.class_id=:cls'; $bPrams[':cls'] = $class_filter; }

    $stmt = $pdo->prepare("
        SELECT fc.id, fc.category_name, fc.category_type,
               COALESCE(SUM(fl.amount_due),0)  AS total_due,
               COALESCE(SUM(fl.amount_paid),0) AS total_paid,
               COALESCE(SUM(fl.waiver_amount),0) AS total_waiver,
               COALESCE(SUM(fl.amount_due - fl.amount_paid - fl.waiver_amount),0) AS outstanding,
               COUNT(DISTINCT fl.student_id) AS student_count,
               COUNT(DISTINCT CASE WHEN fl.status='paid' THEN fl.student_id END) AS paid_count
        FROM fee_ledgers fl
        JOIN fee_categories fc ON fc.id = fl.fee_category_id
        JOIN student_enrollments se ON se.student_id = fl.student_id AND se.session_id = fl.session_id AND se.status='active'
        WHERE $bWhere
        GROUP BY fc.id, fc.category_name, fc.category_type
        ORDER BY outstanding DESC
    ");
    $stmt->execute($bPrams);
    $feeTypeBreakdown = $stmt->fetchAll();

    // Monthly trend for each fee type
    $mStmt = $pdo->prepare("
        SELECT fl.month, fl.year, fc.category_name,
               SUM(fl.amount_due) AS due, SUM(fl.amount_paid) AS paid
        FROM fee_ledgers fl
        JOIN fee_categories fc ON fc.id = fl.fee_category_id
        JOIN student_enrollments se ON se.student_id = fl.student_id AND se.session_id = fl.session_id AND se.status='active'
        WHERE fl.session_id=:sess AND fl.month IS NOT NULL
        GROUP BY fl.year, fl.month, fc.category_name
        ORDER BY fl.year, fl.month, fc.category_name
    ");
    $mStmt->execute([':sess'=>$session_id]);
    foreach ($mStmt->fetchAll() as $r) {
        $mk = sprintf('%04d-%02d', $r['year'], $r['month']);
        $feeTypeMonthly[$mk][$r['category_name']] = $r;
    }
}

require_once EMS_ROOT . '/includes/header.php';
$yr = $_GET['year'] ?? date('Y');
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-file-earmark-bar-graph-fill me-2 text-primary"></i>Financial Reports</h1>
  <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
</div>

<!-- Report type tabs -->
<ul class="nav nav-tabs mb-3 flex-wrap">
  <?php foreach([
    'daily'        => '<i class="bi bi-journal-text me-1"></i>Daily Cash Book',
    'monthly'      => '<i class="bi bi-calendar3 me-1"></i>Monthly Summary',
    'ledger'       => '<i class="bi bi-file-earmark-spreadsheet me-1"></i>General Ledger',
    'outstanding'  => '<i class="bi bi-exclamation-circle me-1"></i>Outstanding Dues',
    'class_perf'   => '<i class="bi bi-bar-chart-line me-1"></i>Class Performance',
    'fee_breakdown'=> '<i class="bi bi-pie-chart me-1"></i>Fee-Type Breakdown',
  ] as $k=>$v): ?>
  <li class="nav-item"><a class="nav-link <?= $type===$k?'active':'' ?>" href="?session_id=<?= $session_id ?>&type=<?= $k ?>&from=<?= urlencode($date_from) ?>&to=<?= urlencode($date_to) ?>&year=<?= $yr ?>"><?= $v ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if($type==='daily'): ?>
<!-- Date range filter -->
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="type" value="daily"><input type="hidden" name="session_id" value="<?= $session_id ?>">
    <div class="col-md-3"><label class="form-label small">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= e($date_from) ?>"></div>
    <div class="col-md-3"><label class="form-label small">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= e($date_to) ?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button></div>
  </form>
</div></div>

<?php if(empty($dailyData)): ?>
  <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>No transactions in this date range.</p></div></div></div>
<?php else: ?>
<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-bordered mb-0 small">
      <thead class="table-dark"><tr><th>Date</th><th>Category</th><th>Type</th><th>Amount</th></tr></thead>
      <tbody>
        <?php $grandIncome=$grandExp=0; foreach($dailyData as $date=>$cats): ?>
        <?php $dayInc=$dayExp=0; foreach($cats as $cat=>$amt): $isExp=str_starts_with($cat,'_exp_'); ?>
        <tr>
          <td><?= fmt_date($date,'d M Y') ?></td>
          <td><?= e(str_replace('_exp_','',$cat)) ?></td>
          <td><span class="badge bg-<?= $isExp?'danger':'success' ?>"><?= $isExp?'Expense':'Income' ?></span></td>
          <td class="fw-600 <?= $isExp?'text-danger':'text-success' ?>"><?= money($amt) ?></td>
        </tr>
        <?php if($isExp) $dayExp+=$amt; else $dayInc+=$amt; endforeach;
        $grandIncome+=$dayInc; $grandExp+=$dayExp; ?>
        <tr class="table-light">
          <td colspan="2" class="fw-700"><?= fmt_date($date,'d M') ?> Net</td>
          <td><span class="badge bg-<?= ($dayInc-$dayExp)>=0?'success':'danger' ?>">Net</span></td>
          <td class="fw-700 <?= ($dayInc-$dayExp)>=0?'text-success':'text-danger' ?>"><?= money($dayInc-$dayExp) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-dark fw-700">
          <td colspan="2">TOTAL (<?= fmt_date($date_from,'d M') ?> – <?= fmt_date($date_to,'d M Y') ?>)</td>
          <td>Net</td>
          <td><?= money($grandIncome-$grandExp) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php elseif($type==='monthly'): ?>
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="type" value="monthly"><input type="hidden" name="session_id" value="<?= $session_id ?>">
    <div class="col-md-3"><label class="form-label small">Year</label><input type="number" name="year" class="form-control form-control-sm" value="<?= $yr ?>" min="2020" max="2040"></div>
    <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm">Go</button></div>
  </form>
</div></div>
<div class="card table-card"><div class="table-responsive">
  <table class="table table-hover mb-0">
    <thead><tr><th>Month</th><th class="text-success">Fee Income</th><th class="text-danger">Expenses</th><th>Net</th><th>Balance Bar</th></tr></thead>
    <tbody>
      <?php $totFee=$totExp=0; for($m=1;$m<=12;$m++): $mk=sprintf('%04d-%02d',$yr,$m); $fee=$monthlyFee[$mk]??0; $exp=$monthlyExp[$mk]??0; $net=$fee-$exp; $totFee+=$fee;$totExp+=$exp; ?>
      <tr>
        <td class="fw-600"><?= date('F',mktime(0,0,0,$m,1)) ?></td>
        <td class="text-success fw-600"><?= money($fee) ?></td>
        <td class="text-danger"><?= money($exp) ?></td>
        <td class="fw-700 <?= $net>=0?'text-success':'text-danger' ?>"><?= money($net) ?></td>
        <td style="min-width:150px;">
          <?php if($fee>0): $pct=min(100,round($exp/$fee*100)); ?>
          <div class="progress" style="height:6px"><div class="progress-bar bg-danger" style="width:<?= $pct ?>%"></div></div>
          <div class="text-muted small"><?= $pct ?>% spent</div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endfor; ?>
      <tr class="table-dark fw-700"><td>TOTAL <?= $yr ?></td><td class="text-success"><?= money($totFee) ?></td><td class="text-danger"><?= money($totExp) ?></td><td class="<?= ($totFee-$totExp)>=0?'text-success':'text-danger' ?>"><?= money($totFee-$totExp) ?></td><td></td></tr>
    </tbody>
  </table>
</div></div>

<?php elseif($type==='ledger'): ?>
<!-- Date range filter -->
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="type" value="ledger"><input type="hidden" name="session_id" value="<?= $session_id ?>">
    <div class="col-md-3"><label class="form-label small">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= e($date_from) ?>"></div>
    <div class="col-md-3"><label class="form-label small">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= e($date_to) ?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Filter</button></div>
  </form>
</div></div>

<?php if(!empty($accountsList)): ?>
  <div class="row g-2 mb-3">
    <?php foreach($accountsList as $acc): 
      $icon = ($acc['account_type'] === 'cash') ? 'cash-coin text-success' : (($acc['account_type'] === 'bank') ? 'bank text-primary' : 'phone-fill text-danger');
    ?>
      <div class="col-md-4">
        <div class="card border-0 p-3 shadow-sm" style="background: #1e293b; color: white; border-radius: 8px;">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <span class="text-white-50 small text-uppercase" style="font-size: 0.7rem;"><?= e($acc['account_type']) ?></span>
              <h6 class="fw-bold mb-0 mt-0" style="font-size: 0.95rem;"><?= e($acc['account_name']) ?></h6>
            </div>
            <i class="bi bi-<?= $icon ?> fs-5"></i>
          </div>
          <div class="mt-2 fw-bold text-success" style="font-size: 1.15rem;">
            <?= setting('currency_symbol', '৳') . number_format($acc['current_balance'], 2) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if(empty($ledgerEntries)): ?>
  <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>No transactions in the selected date range.</p></div></div></div>
<?php else: ?>
  <?php
  $totDebit = 0;
  $totCredit = 0;
  foreach ($ledgerEntries as $entry) {
      if ($entry['entry_type'] === 'debit') {
          $totDebit += $entry['amount'];
      } else {
          $totCredit += $entry['amount'];
      }
  }
  ?>
  <div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="stat-card primary" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none;"><div class="stat-value" style="color: white;"><?= money($totDebit) ?></div><div class="stat-label" style="color: rgba(255,255,255,0.85);">Total Debits (Cash In)</div><i class="bi bi-arrow-down-left-circle stat-icon" style="color: rgba(255,255,255,0.25);"></i></div></div>
    <div class="col-sm-4"><div class="stat-card primary" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none;"><div class="stat-value" style="color: white;"><?= money($totCredit) ?></div><div class="stat-label" style="color: rgba(255,255,255,0.85);">Total Credits (Cash Out)</div><i class="bi bi-arrow-up-right-circle stat-icon" style="color: rgba(255,255,255,0.25);"></i></div></div>
    <div class="col-sm-4"><div class="stat-card primary" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; border: none;"><div class="stat-value" style="color: white;"><?= money($totDebit - $totCredit) ?></div><div class="stat-label" style="color: rgba(255,255,255,0.85);">Net Ledger Balance</div><i class="bi bi-bank stat-icon" style="color: rgba(255,255,255,0.25);"></i></div></div>
  </div>

  <div class="card table-card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-dark">
          <tr>
            <th>Date</th>
            <th>Category</th>
            <th>Description</th>
            <th class="text-end">Debit (Inflow)</th>
            <th class="text-end">Credit (Outflow)</th>
            <th class="text-end">Running Balance</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $runningBal = 0;
          foreach($ledgerEntries as $entry): 
              if ($entry['entry_type'] === 'debit') {
                  $runningBal += $entry['amount'];
                  $debitStr = money($entry['amount']);
                  $creditStr = '—';
              } else {
                  $runningBal -= $entry['amount'];
                  $debitStr = '—';
                  $creditStr = money($entry['amount']);
              }
          ?>
          <tr>
            <td><?= fmt_date($entry['date'],'d M Y') ?></td>
            <td><span class="badge bg-light text-dark border"><?= e($entry['category']) ?></span></td>
            <td><?= e($entry['description']) ?></td>
            <td class="text-end text-success fw-600"><?= $debitStr ?></td>
            <td class="text-end text-danger"><?= $creditStr ?></td>
            <td class="text-end fw-700 <?= $runningBal >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($runningBal) ?></td>
          </tr>
          <?php endforeach; ?>
          <tr class="table-dark fw-700">
            <td colspan="3">CLOSING SUMMARY</td>
            <td class="text-end text-success"><?= money($totDebit) ?></td>
            <td class="text-end text-danger"><?= money($totCredit) ?></td>
            <td class="text-end <?= ($totDebit - $totCredit) >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($totDebit - $totCredit) ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php elseif($type==='outstanding'): ?>
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2">
    <input type="hidden" name="type" value="outstanding">
    <div class="col-md-3"><label class="form-label small">Session</label>
      <select name="session_id" class="form-select form-select-sm">
        <?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm">Go</button></div>
  </form>
</div></div>
<div class="card table-card"><div class="table-responsive">
  <table class="table table-hover mb-0">
    <thead><tr><th>Class</th><th>Students with Dues</th><th>Total Outstanding</th><th>Bar</th><th></th></tr></thead>
    <tbody>
      <?php if(empty($outstanding)): ?>
        <tr><td colspan="5"><div class="empty-state"><i class="bi bi-check-circle text-success"></i><p>No outstanding dues!</p></div></td></tr>
      <?php else:
        $maxOs = max(array_column($outstanding,'outstanding')) ?: 1;
        $totOs = 0;
        foreach($outstanding as $o): $totOs+=$o['outstanding']; ?>
        <tr>
          <td class="fw-600"><?= e($o['class_name']) ?></td>
          <td><?= $o['students'] ?></td>
          <td class="fw-700 text-danger"><?= money($o['outstanding']) ?></td>
          <td style="min-width:150px;">
            <div class="progress" style="height:6px"><div class="progress-bar bg-danger" style="width:<?= round($o['outstanding']/$maxOs*100) ?>%"></div></div>
          </td>
          <td><a href="../finance/student_dues.php?session_id=<?= $session_id ?>" class="btn btn-xs btn-outline-warning">View List</a></td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-dark fw-700"><td colspan="2">TOTAL OUTSTANDING</td><td class="text-danger"><?= money($totOs) ?></td><td></td><td></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div></div>

<?php elseif($type==='class_perf'): ?>
<?php $fee_cat_filter = int_param('fee_cat_id',0,$_GET); $tillDate = $_GET['till_date'] ?? date('Y-m-d'); ?>
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="type" value="class_perf">
    <div class="col-md-2"><label class="form-label small">Session</label>
      <select name="session_id" class="form-select form-select-sm">
        <?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label small">Fee Category</label>
      <select name="fee_cat_id" class="form-select form-select-sm">
        <option value="0">— All Fees —</option>
        <?php foreach($feeCategories as $fc): ?><option value="<?= $fc['id'] ?>" <?= $fee_cat_filter==$fc['id']?'selected':'' ?>><?= e($fc['category_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label small">Due On or Before</label>
      <input type="date" name="till_date" class="form-control form-control-sm" value="<?= e($tillDate) ?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
      <a href="student_dues.php?session_id=<?= $session_id ?>" class="btn btn-outline-warning btn-sm ms-1"><i class="bi bi-people me-1"></i>Student Due List</a>
      <a href="master_collection.php?session_id=<?= $session_id ?>" class="btn btn-outline-primary btn-sm ms-1"><i class="bi bi-table me-1"></i>Collection Grid</a>
    </div>
  </form>
</div></div>

<?php if(empty($classPerf)): ?>
  <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-bar-chart"></i><p>No data for this selection.</p></div></div></div>
<?php else:
  $grandEnrolled=$grandFullyPaid=$grandDue=$grandPaid=$grandOs=0;
  foreach($classPerf as $r){ $grandEnrolled+=$r['enrolled'];$grandFullyPaid+=$r['fully_paid_students'];$grandDue+=$r['total_due'];$grandPaid+=$r['total_paid'];$grandOs+=$r['outstanding']; }
  $grandRate = $grandDue > 0 ? round($grandPaid/$grandDue*100) : 0;
?>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="stat-card"><div class="stat-value"><?= $grandEnrolled ?></div><div class="stat-label">Total Enrolled</div><i class="bi bi-people stat-icon"></i></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;"><div class="stat-value" style="color:#fff"><?= money($grandPaid) ?></div><div class="stat-label" style="color:rgba(255,255,255,.8)">Total Collected</div><i class="bi bi-check-circle stat-icon" style="color:rgba(255,255,255,.25)"></i></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;"><div class="stat-value" style="color:#fff"><?= money($grandOs) ?></div><div class="stat-label" style="color:rgba(255,255,255,.8)">Total Outstanding</div><i class="bi bi-exclamation-circle stat-icon" style="color:rgba(255,255,255,.25)"></i></div></div>
  <div class="col-md-3"><div class="stat-card"><div class="stat-value"><?= $grandRate ?>%</div><div class="stat-label">Overall Collection Rate</div><i class="bi bi-bar-chart stat-icon"></i><div class="progress mt-2" style="height:6px;"><div class="progress-bar bg-success" style="width:<?= $grandRate ?>%"></div></div></div></div>
</div>

<div class="card table-card"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-dark">
      <tr>
        <th>Class</th><th>Section</th><th class="text-center">Enrolled</th>
        <th class="text-end">Total Billed</th><th class="text-end">Collected</th>
        <th class="text-end">Outstanding</th><th class="text-end">Waiver</th>
        <th class="text-center">Defaulters</th><th style="min-width:120px;">Collection Rate</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($classPerf as $r):
        $rate = $r['total_due'] > 0 ? round($r['total_paid']/$r['total_due']*100) : 0;
        $rateClass = $rate >= 90 ? 'bg-success' : ($rate >= 60 ? 'bg-warning' : 'bg-danger');
      ?>
      <tr>
        <td class="fw-600"><?= e($r['class_name']) ?></td>
        <td><?= e($r['section_name'] ?? '—') ?></td>
        <td class="text-center"><?= $r['enrolled'] ?></td>
        <td class="text-end"><?= money($r['total_due']) ?></td>
        <td class="text-end text-success fw-600"><?= money($r['total_paid']) ?></td>
        <td class="text-end <?= $r['outstanding']>0?'text-danger fw-700':'text-success' ?>"><?= money($r['outstanding']) ?></td>
        <td class="text-end text-muted"><?= money($r['total_waiver']) ?></td>
        <td class="text-center <?= $r['defaulters']>0?'text-danger fw-600':'' ?>"><?= $r['defaulters'] ?></td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="progress flex-grow-1" style="height:8px;">
              <div class="progress-bar <?= $rateClass ?>" style="width:<?= $rate ?>%"></div>
            </div>
            <span class="small fw-600 <?= $rate>=90?'text-success':($rate>=60?'text-warning':'text-danger') ?>"><?= $rate ?>%</span>
          </div>
        </td>
        <td>
          <a href="student_dues.php?session_id=<?= $session_id ?>&class_id=<?= $r['class_id'] ?>"
             class="btn btn-xs btn-outline-warning" title="View due students">
            <i class="bi bi-person-exclamation"></i>
          </a>
          <a href="master_collection.php?session_id=<?= $session_id ?>&class_id=<?= $r['class_id'] ?>"
             class="btn btn-xs btn-outline-primary ms-1" title="Open collection grid">
            <i class="bi bi-table"></i>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr class="table-dark fw-700">
        <td colspan="2">TOTALS</td>
        <td class="text-center"><?= $grandEnrolled ?></td>
        <td class="text-end"><?= money($grandDue) ?></td>
        <td class="text-end text-success"><?= money($grandPaid) ?></td>
        <td class="text-end text-warning"><?= money($grandOs) ?></td>
        <td colspan="3"></td><td></td>
      </tr>
    </tbody>
  </table>
</div></div>
<?php endif; ?>

<?php elseif($type==='fee_breakdown'): ?>
<?php $tillDate = $_GET['till_date'] ?? date('Y-m-d'); $class_filter = int_param('class_id',0,$_GET); ?>
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="type" value="fee_breakdown">
    <div class="col-md-2"><label class="form-label small">Session</label>
      <select name="session_id" class="form-select form-select-sm">
        <?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label small">Class</label>
      <select name="class_id" class="form-select form-select-sm">
        <option value="0">— All Classes —</option>
        <?php foreach($allClasses as $c): ?><option value="<?= $c['id'] ?>" <?= $class_filter==$c['id']?'selected':'' ?>><?= e($c['class_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label small">Due On or Before</label>
      <input type="date" name="till_date" class="form-control form-control-sm" value="<?= e($tillDate) ?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button></div>
  </form>
</div></div>

<?php if(empty($feeTypeBreakdown)): ?>
  <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-pie-chart"></i><p>No fee data found.</p></div></div></div>
<?php else: ?>
<div class="row g-3 mb-4">
  <?php
  $fbTotDue=$fbTotPaid=$fbTotOs=0;
  foreach($feeTypeBreakdown as $fb){$fbTotDue+=$fb['total_due'];$fbTotPaid+=$fb['total_paid'];$fbTotOs+=$fb['outstanding'];}
  ?>
  <div class="col-md-4"><div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;"><div class="stat-value" style="color:#fff"><?= money($fbTotPaid) ?></div><div class="stat-label" style="color:rgba(255,255,255,.8)">Total Collected</div><i class="bi bi-check-circle stat-icon" style="color:rgba(255,255,255,.25)"></i></div></div>
  <div class="col-md-4"><div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;"><div class="stat-value" style="color:#fff"><?= money($fbTotOs) ?></div><div class="stat-label" style="color:rgba(255,255,255,.8)">Total Outstanding</div><i class="bi bi-exclamation-circle stat-icon" style="color:rgba(255,255,255,.25)"></i></div></div>
  <div class="col-md-4"><div class="stat-card"><div class="stat-value"><?= $fbTotDue>0?round($fbTotPaid/$fbTotDue*100):0 ?>%</div><div class="stat-label">Overall Rate</div><i class="bi bi-bar-chart stat-icon"></i></div></div>
</div>

<div class="card table-card mb-4"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-dark">
      <tr><th>Fee Type</th><th>Category</th><th class="text-end">Billed</th><th class="text-end">Collected</th><th class="text-end">Outstanding</th><th class="text-center">Students</th><th style="min-width:130px;">Collection Rate</th></tr>
    </thead>
    <tbody>
      <?php foreach($feeTypeBreakdown as $fb):
        $rate = $fb['total_due']>0 ? round($fb['total_paid']/$fb['total_due']*100) : 0;
        $rateClass = $rate>=90?'bg-success':($rate>=60?'bg-warning':'bg-danger');
      ?>
      <tr>
        <td class="fw-600"><?= e($fb['category_name']) ?></td>
        <td><span class="badge bg-light text-dark border" style="font-size:.7rem;"><?= ucfirst(e($fb['category_type'])) ?></span></td>
        <td class="text-end"><?= money($fb['total_due']) ?></td>
        <td class="text-end text-success fw-600"><?= money($fb['total_paid']) ?></td>
        <td class="text-end <?= $fb['outstanding']>0?'text-danger fw-700':'text-success' ?>"><?= money($fb['outstanding']) ?></td>
        <td class="text-center small"><?= $fb['paid_count'] ?>/<?= $fb['student_count'] ?> paid</td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="progress flex-grow-1" style="height:8px;"><div class="progress-bar <?= $rateClass ?>" style="width:<?= $rate ?>%"></div></div>
            <span class="small fw-600 <?= $rate>=90?'text-success':($rate>=60?'text-warning':'text-danger') ?>"><?= $rate ?>%</span>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr class="table-dark fw-700">
        <td colspan="2">TOTALS</td>
        <td class="text-end"><?= money($fbTotDue) ?></td>
        <td class="text-end text-success"><?= money($fbTotPaid) ?></td>
        <td class="text-end text-warning"><?= money($fbTotOs) ?></td>
        <td colspan="2"></td>
      </tr>
    </tbody>
  </table>
</div></div>

<?php if(!empty($feeTypeMonthly)): ?>
<div class="card table-card"><div class="card-header py-3 px-4 bg-light"><span class="card-title">Monthly Fee Collection Trend</span></div><div class="table-responsive">
  <table class="table table-sm table-bordered mb-0 small">
    <thead class="table-dark">
      <tr>
        <th>Month</th>
        <?php $allCats=array_unique(array_merge(...array_map(fn($m)=>array_keys($m),$feeTypeMonthly))); sort($allCats); foreach($allCats as $cat): ?><th class="text-end"><?= e($cat) ?></th><?php endforeach; ?>
        <th class="text-end">Month Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($feeTypeMonthly as $mk => $cats):
        $monthTotal=0;
      ?>
      <tr>
        <td class="fw-600"><?= date('M Y', strtotime($mk.'-01')) ?></td>
        <?php foreach($allCats as $cat):
          $r = $cats[$cat] ?? null;
          $rate2 = $r && $r['due']>0 ? round($r['paid']/$r['due']*100) : 0;
        ?>
        <td class="text-end <?= $r&&$r['paid']>0?'text-success':'' ?>">
          <?php if($r): $monthTotal+=$r['paid']; ?>
            <div><?= money($r['paid']) ?></div>
            <div class="text-muted" style="font-size:.65rem;"><?= $rate2 ?>% of <?= money($r['due']) ?></div>
          <?php else: ?>—<?php endif; ?>
        </td>
        <?php endforeach; ?>
        <td class="text-end fw-700"><?= money($monthTotal) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div></div>
<?php endif; ?>
<?php endif; ?>

<?php endif; ?>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
