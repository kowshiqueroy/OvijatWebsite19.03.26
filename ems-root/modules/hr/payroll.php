<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Payroll';
$breadcrumbs = ['HR & Payroll' => 'staff.php', 'Payroll' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['payroll.view']);

$pdo = db();
$session_id = int_param('session_id',(int)setting('current_session_id',0),$_GET);
$month      = int_param('month',date('n'),$_GET);
$year       = int_param('year',date('Y'),$_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('payroll.manage')) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $sess  = int_param('session_id',$session_id,$_POST);
        $mon   = int_param('month',$month,$_POST);
        $yr    = int_param('year',$year,$_POST);

        $bonus_type = $_POST['bonus_type'] ?? 'none';
        $bonus_value = (float)($_POST['bonus_value'] ?? 0);
        $bonus_label = trim($_POST['bonus_label'] ?? '');
        $bonus_target = $_POST['bonus_target'] ?? 'all';

        // Create or fetch payroll run
        $pdo->prepare('INSERT IGNORE INTO payroll_runs (session_id,month,year,status,created_by,run_type) VALUES (?,?,?,"draft",?,"regular")')->execute([$sess,$mon,$yr,current_user_id()]);
        $runStmt = $pdo->prepare('SELECT id FROM payroll_runs WHERE session_id=? AND month=? AND year=? AND run_type="regular"');
        $runStmt->execute([$sess,$mon,$yr]);
        $runId = (int)$runStmt->fetchColumn();

        // Load active staff
        $staffAll = $pdo->query("
            SELECT sp.user_id, sp.base_salary, sp.salary_type, r.role_slug 
            FROM staff_profiles sp 
            LEFT JOIN user_roles ur ON ur.user_id = sp.user_id 
            LEFT JOIN roles r ON r.id = ur.role_id 
            WHERE sp.status='active'
        ")->fetchAll();

        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $mon, $yr);
        $stmt = $pdo->prepare('
            INSERT INTO payroll_lines (payroll_run_id,staff_id,base_salary,exam_duty_allowance,advance_deduction,absence_deduction,bonus_amount,bonus_desc,net_salary) 
            VALUES (?,?,?,?,?,?,?,?,?) 
            ON DUPLICATE KEY UPDATE base_salary=VALUES(base_salary),exam_duty_allowance=VALUES(exam_duty_allowance),advance_deduction=VALUES(advance_deduction),absence_deduction=VALUES(absence_deduction),bonus_amount=VALUES(bonus_amount),bonus_desc=VALUES(bonus_desc),net_salary=VALUES(net_salary)
        ');

        foreach ($staffAll as $sp) {
            // Exam duty allowance
            $dutyQ = $pdo->prepare("SELECT COALESCE(SUM(allowance_amount),0) FROM exam_invigilators WHERE teacher_id=? AND MONTH(duty_date)=? AND YEAR(duty_date)=?");
            $dutyQ->execute([$sp['user_id'],$mon,$yr]);
            $dutyAllow = (float)$dutyQ->fetchColumn();

            // Absences (unpaid)
            $absentQ = $pdo->prepare("SELECT COUNT(*) FROM staff_attendance WHERE staff_id=? AND MONTH(attendance_date)=? AND YEAR(attendance_date)=? AND status='absent'");
            $absentQ->execute([$sp['user_id'],$mon,$yr]);
            $absents = (int)$absentQ->fetchColumn();
            $perDay  = $days_in_month > 0 ? $sp['base_salary'] / $days_in_month : 0;
            $absDeduction = round($absents * $perDay, 2);

            // Active loan installments
            $loanQ = $pdo->prepare("SELECT COALESCE(SUM(GREATEST(0, LEAST(monthly_installment, total_repayable - amount_repaid))), 0) FROM staff_loans WHERE staff_id=? AND status='active'");
            $loanQ->execute([$sp['user_id']]);
            $loanDeduction = (float)$loanQ->fetchColumn();

            // Calculate bonus
            $bonus_amount = 0.00;
            $give_bonus = false;
            if ($bonus_type !== 'none' && $bonus_value > 0) {
                if ($bonus_target === 'all') {
                    $give_bonus = true;
                } elseif ($bonus_target === 'teachers' && $sp['role_slug'] === 'teacher') {
                    $give_bonus = true;
                } elseif ($bonus_target === 'non_teachers' && $sp['role_slug'] !== 'teacher') {
                    $give_bonus = true;
                }
            }

            if ($give_bonus) {
                if ($bonus_type === 'fixed') {
                    $bonus_amount = $bonus_value;
                } elseif ($bonus_type === 'percent') {
                    $bonus_amount = round($sp['base_salary'] * ($bonus_value / 100), 2);
                }
            }

            $net = $sp['base_salary'] + $dutyAllow + $bonus_amount - $absDeduction - $loanDeduction;
            $stmt->execute([$runId,$sp['user_id'],$sp['base_salary'],$dutyAllow,$loanDeduction,$absDeduction,$bonus_amount,$bonus_amount > 0 ? $bonus_label : null,$net]);
        }
        flash('success', 'Payroll generated for '.date('F Y',mktime(0,0,0,$mon,1,$yr)).'.');
        header("Location: payroll.php?session_id=$sess&month=$mon&year=$yr");
        exit;
    } elseif ($action === 'finalize') {
        $runId = int_param('run_id',0,$_POST);
        $account_id = int_param('account_id',0,$_POST);

        if (!$account_id) {
            flash('error', 'Please select a disbursal account.');
            header('Location: payroll.php?session_id='.$session_id.'&month='.$month.'&year='.$year);
            exit;
        }
        
        $pdo->beginTransaction();
        try {
            // Fetch total net salaries
            $totStmt = $pdo->prepare("SELECT SUM(net_salary) FROM payroll_lines WHERE payroll_run_id = ?");
            $totStmt->execute([$runId]);
            $total_net = (float)$totStmt->fetchColumn();

            // Verify account balance
            $stmt = $pdo->prepare("SELECT current_balance, account_name FROM accounts WHERE id = ? FOR UPDATE");
            $stmt->execute([$account_id]);
            $acc = $stmt->fetch();

            if (!$acc) {
                throw new Exception("Selected account does not exist.");
            }
            if ($acc['current_balance'] < $total_net) {
                throw new Exception("Insufficient funds in {$acc['account_name']}. Net payroll total is " . money($total_net) . " but balance is " . money($acc['current_balance']));
            }

            $pdo->prepare("UPDATE payroll_runs SET status='finalized' WHERE id=?")->execute([$runId]);

            // Deduct loan amounts from active staff loans
            $linesStmt = $pdo->prepare("SELECT * FROM payroll_lines WHERE payroll_run_id = ?");
            $linesStmt->execute([$runId]);
            $runLines = $linesStmt->fetchAll();

            foreach ($runLines as $ln) {
                $deduction = (float)$ln['advance_deduction'];
                if ($deduction > 0) {
                    $staff_id = $ln['staff_id'];
                    $loansStmt = $pdo->prepare("SELECT * FROM staff_loans WHERE staff_id = ? AND status = 'active' ORDER BY id ASC");
                    $loansStmt->execute([$staff_id]);
                    $activeLoans = $loansStmt->fetchAll();

                    $remaining_deduction = $deduction;
                    foreach ($activeLoans as $loan) {
                        if ($remaining_deduction <= 0) break;

                        $outstanding = $loan['total_repayable'] - $loan['amount_repaid'];
                        if ($outstanding <= 0) continue;

                        $repay_amount = min($remaining_deduction, $outstanding);
                        $new_repaid = $loan['amount_repaid'] + $repay_amount;
                        $status = ($new_repaid >= $loan['total_repayable'] - 0.05) ? 'paid' : 'active';

                        $pdo->prepare('UPDATE staff_loans SET amount_repaid = ?, status = ? WHERE id = ?')
                            ->execute([$new_repaid, $status, $loan['id']]);

                        log_activity('repay_staff_loan_payroll', 'finance', $loan['id'], '', "PayrollRepaid:$repay_amount, NewTotal:$new_repaid, RunID:$runId");

                        $remaining_deduction -= $repay_amount;
                    }
                }
            }

            // Deduct from account balance
            $pdo->prepare("UPDATE accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$total_net, $account_id]);

            // Write transaction log
            $tx = $pdo->prepare("INSERT INTO account_transactions (account_id, amount, transaction_type, description, reference_table, reference_id, created_by) VALUES (?, ?, 'withdrawal', ?, 'payroll_runs', ?, ?)");
            $tx->execute([$account_id, -$total_net, "Disbursed payroll for run #$runId", 'payroll_runs', $runId, current_user_id()]);

            $pdo->commit();
            flash('success', 'Payroll finalized, disbursal account updated, and loan outstanding balances adjusted.');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('error', 'Error finalising payroll: ' . $e->getMessage());
        }
        header('Location: payroll.php?session_id='.$session_id.'&month='.$month.'&year='.$year);
        exit;
    } elseif ($action === 'update_line') {
        $lineId = int_param('line_id',0,$_POST);
        $fields = ['exam_duty_allowance','extra_class_allowance','other_additions','tax_deduction','provident_fund','advance_deduction','fine_deduction','bonus_amount','bonus_desc','notes'];
        
        $lineInfo = $pdo->query("SELECT base_salary, absence_deduction FROM payroll_lines WHERE id=$lineId")->fetch(PDO::FETCH_ASSOC);
        $base   = (float)$lineInfo['base_salary'];
        $absDeduction = (float)$lineInfo['absence_deduction'];
        
        $adds   = 0; $deds = 0;
        $vals   = [];
        foreach ($fields as $f) {
            $v = ($f==='notes' || $f==='bonus_desc') ? trim($_POST[$f]??'') : (float)($_POST[$f]??0);
            $vals[$f] = $v;
            if (in_array($f,['exam_duty_allowance','extra_class_allowance','other_additions','bonus_amount'])) $adds += (float)$v;
            if (in_array($f,['tax_deduction','provident_fund','advance_deduction','fine_deduction'])) $deds += (float)$v;
        }
        $net = $base + $adds - $deds - $absDeduction;
        $pdo->prepare('UPDATE payroll_lines SET exam_duty_allowance=?,extra_class_allowance=?,other_additions=?,tax_deduction=?,provident_fund=?,advance_deduction=?,fine_deduction=?,bonus_amount=?,bonus_desc=?,net_salary=?,notes=? WHERE id=?')
            ->execute(array_merge(array_values($vals),[$net,$lineId]));
        flash('success','Line updated.');
        header('Location: payroll.php?session_id='.$session_id.'&month='.$month.'&year='.$year);
        exit;
    } elseif ($action === 'custom_payout') {
        $sess  = int_param('session_id',$session_id,$_POST);
        $staff_id = int_param('staff_id',0,$_POST);
        $amount = (float)($_POST['amount']??0);
        $desc = trim($_POST['description']??'');
        $acc_id = int_param('account_id',0,$_POST);

        if ($staff_id && $amount > 0 && $acc_id && $desc) {
            $pdo->beginTransaction();
            try {
                // Verify account balance
                $stmt = $pdo->prepare("SELECT current_balance, account_name FROM accounts WHERE id = ? FOR UPDATE");
                $stmt->execute([$acc_id]);
                $acc = $stmt->fetch();

                if (!$acc) throw new Exception("Account does not exist.");
                if ($acc['current_balance'] < $amount) {
                    throw new Exception("Insufficient funds in {$acc['account_name']}. Balance is " . money($acc['current_balance']));
                }

                // Insert custom payroll run
                $pdo->prepare('INSERT INTO payroll_runs (session_id,month,year,status,created_by,run_type,description) VALUES (?,?,?, "finalized", ?, "custom", ?)')
                    ->execute([$sess, (int)date('n'), (int)date('Y'), current_user_id(), $desc]);
                $runId = $pdo->lastInsertId();

                // Insert payroll line
                $pdo->prepare('INSERT INTO payroll_lines (payroll_run_id,staff_id,base_salary,bonus_amount,bonus_desc,net_salary,payment_status) VALUES (?,?,0.00,?,?,?,"paid")')
                    ->execute([$runId, $staff_id, $amount, $desc, $amount]);

                // Deduct from account balance
                $pdo->prepare("UPDATE accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $acc_id]);

                // Write transaction log
                $tx = $pdo->prepare("INSERT INTO account_transactions (account_id, amount, transaction_type, description, reference_table, reference_id, created_by) VALUES (?, ?, 'withdrawal', ?, 'payroll_runs', ?, ?)");
                $tx->execute([$acc_id, -$amount, "One-time payout: $desc", 'payroll_runs', $runId, current_user_id()]);

                $pdo->commit();
                flash('success', 'One-time payout of '.money($amount).' processed successfully.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', $e->getMessage());
            }
        }
        header("Location: payroll.php?session_id=$sess");
        exit;
    }
}

$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();

// Find current payroll run
$run = null;
$lines = [];
$runStmt = $pdo->prepare('SELECT * FROM payroll_runs WHERE session_id=? AND month=? AND year=? AND run_type="regular"');
$runStmt->execute([$session_id,$month,$year]);
$run = $runStmt->fetch();

if ($run) {
    $linesStmt = $pdo->query("SELECT pl.*, CONCAT(sp.first_name,' ',sp.last_name) as staff_name, sp.designation, sp.department FROM payroll_lines pl JOIN staff_profiles sp ON sp.user_id=pl.staff_id WHERE pl.payroll_run_id={$run['id']} ORDER BY sp.department, sp.first_name");
    $lines = $linesStmt->fetchAll();
}

$accounts = $pdo->query("SELECT id, account_name, current_balance FROM accounts ORDER BY account_name")->fetchAll(PDO::FETCH_ASSOC);

$staffListForPayout = $pdo->query(
    "SELECT sp.user_id, CONCAT(sp.first_name, ' ', sp.last_name) as name, sp.designation 
     FROM staff_profiles sp 
     WHERE sp.status='active' 
     ORDER BY name"
)->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-cash-stack me-2 text-primary"></i>Payroll</h1>
  <div class="d-flex gap-2 align-items-center">
    <?php if (has_permission('payroll.manage')): ?>
      <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#customPayoutModal"><i class="bi bi-plus-circle me-1"></i>One-Time Payout</button>
    <?php endif; ?>
    <select class="form-select form-select-sm" onchange="location='?session_id='+this.value+'&month=<?= $month ?>&year=<?= $year ?>'" style="width:auto">
      <?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select form-select-sm" onchange="location='?session_id=<?= $session_id ?>&month='+this.value+'&year=<?= $year ?>'" style="width:auto">
      <?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option><?php endfor; ?>
    </select>
    <input type="number" class="form-control form-control-sm" value="<?= $year ?>" min="2020" max="2035" style="width:80px" onchange="location='?session_id=<?= $session_id ?>&month=<?= $month ?>&year='+this.value">
  </div>
</div>

<?php if (!$run): ?>
<div class="card"><div class="card-body text-center py-5">
  <i class="bi bi-cash-coin" style="font-size:3rem;color:#94a3b8;"></i>
  <h5 class="mt-3">No payroll run for <?= date('F Y',mktime(0,0,0,$month,1,$year)) ?></h5>
  <?php if(has_permission('payroll.manage')): ?>
  <form method="POST" class="mt-3 text-start mx-auto" style="max-width: 500px;">
    <?= csrf_field() ?><input type="hidden" name="action" value="generate">
    <input type="hidden" name="session_id" value="<?= $session_id ?>">
    <input type="hidden" name="month" value="<?= $month ?>">
    <input type="hidden" name="year" value="<?= $year ?>">
    
    <div class="card bg-light border-0 mb-3 text-dark">
      <div class="card-body p-3">
        <h6 class="fw-bold mb-2">Global Bonus Settings (Optional)</h6>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label small">Bonus Type</label>
            <select name="bonus_type" class="form-select form-select-sm">
              <option value="none">None</option>
              <option value="fixed">Fixed Amount (৳)</option>
              <option value="percent">Percentage of Base (%)</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Bonus Value</label>
            <input type="number" name="bonus_value" class="form-control form-control-sm" step="0.01" value="0">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Bonus Label</label>
            <input type="text" name="bonus_label" class="form-control form-control-sm" placeholder="e.g. Eid Bonus">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Target Group</label>
            <select name="bonus_target" class="form-select form-select-sm">
              <option value="all">All Active Staff</option>
              <option value="teachers">Teachers Only</option>
              <option value="non_teachers">Management/Non-Teachers</option>
            </select>
          </div>
        </div>
      </div>
    </div>
    
    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold"><i class="bi bi-lightning-fill me-1"></i>Generate Payroll</button>
  </form>
  <?php endif; ?>
</div></div>
<?php else: ?>

<!-- Payroll header -->
<div class="card mb-3">
  <div class="card-body d-flex align-items-center justify-content-between py-3">
    <div>
      <span class="fw-700 fs-5"><?= date('F Y',mktime(0,0,0,$month,1,$year)) ?> Payroll</span>
      <span class="badge-status badge-<?= $run['status']==='draft'?'draft':($run['status']==='finalized'?'approved':'active') ?> ms-2"><?= ucfirst(e($run['status'])) ?></span>
    </div>
    <div class="d-flex gap-2">
      <?php if($run['status']==='draft' && has_permission('payroll.manage')): ?>
      <form method="POST" class="d-inline-flex gap-2 align-items-center" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
        <?= csrf_field() ?><input type="hidden" name="action" value="finalize"><input type="hidden" name="run_id" value="<?= $run['id'] ?>">
        <select name="account_id" class="form-select form-select-sm" style="width: auto;" required>
          <option value="">— Disbursal Account —</option>
          <?php foreach ($accounts as $acc): ?>
            <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (<?= setting('currency_symbol','৳').number_format($acc['current_balance'],2) ?>)</option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-success btn-sm" data-confirm="Finalize this payroll? Net salary sum will be deducted from the selected account."><i class="bi bi-lock me-1"></i>Finalize & Disburse</button>
      </form>
      <?php endif; ?>
      <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Print Slip</button>
    </div>
  </div>
</div>

<!-- Summary stats -->
<?php
$totBase = array_sum(array_column($lines,'base_salary'));
$totNet  = array_sum(array_column($lines,'net_salary'));
?>
<div class="row g-3 mb-3">
  <div class="col-sm-3"><div class="stat-card primary"><div class="stat-value"><?= count($lines) ?></div><div class="stat-label">Staff</div><i class="bi bi-people stat-icon"></i></div></div>
  <div class="col-sm-3"><div class="stat-card info"><div class="stat-value"><?= money($totBase) ?></div><div class="stat-label">Total Base</div><i class="bi bi-cash stat-icon"></i></div></div>
  <div class="col-sm-3"><div class="stat-card success"><div class="stat-value"><?= money($totNet) ?></div><div class="stat-label">Total Net</div><i class="bi bi-cash-coin stat-icon"></i></div></div>
</div>

<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 small">
      <thead><tr><th>Staff</th><th>Dept</th><th>Base</th><th>+Duty</th><th>+Bonus</th><th>+Other</th><th>-Tax</th><th>-PF</th><th>-Loan Ded.</th><th>-Absence/Fine</th><th class="fw-700">Net</th><?php if($run['status']==='draft' && has_permission('payroll.manage')): ?><th>Edit</th><?php endif; ?></tr></thead>
      <tbody>
        <?php foreach($lines as $ln): ?>
        <tr>
          <td><div class="fw-600"><?= e($ln['staff_name']) ?></div><small class="text-muted"><?= e($ln['designation']??'') ?></small></td>
          <td><?= e($ln['department']??'—') ?></td>
          <td><?= money($ln['base_salary']) ?></td>
          <td class="text-success"><?= money($ln['exam_duty_allowance']) ?></td>
          <td class="text-success"><?= $ln['bonus_amount'] > 0 ? money($ln['bonus_amount'])."<br><small class='text-muted'>".e($ln['bonus_desc'])."</small>" : '—' ?></td>
          <td class="text-success"><?= money($ln['extra_class_allowance']+$ln['other_additions']) ?></td>
          <td class="text-danger"><?= money($ln['tax_deduction']) ?></td>
          <td class="text-danger"><?= money($ln['provident_fund']) ?></td>
          <td class="text-danger"><?= money($ln['advance_deduction']) ?></td>
          <td class="text-danger"><?= money($ln['absence_deduction']+$ln['fine_deduction']) ?></td>
          <td class="fw-700"><?= money($ln['net_salary']) ?></td>
          <?php if($run['status']==='draft' && has_permission('payroll.manage')): ?>
          <td>
            <button class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:.15rem .4rem;"
                    data-bs-toggle="modal" data-bs-target="#lineModal"
                    onclick="setLineForm(<?= htmlspecialchars(json_encode($ln),ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php
        $totDuty = array_sum(array_column($lines, 'exam_duty_allowance'));
        $totBonus = array_sum(array_column($lines, 'bonus_amount'));
        $totOtherAllow = array_sum(array_map(fn($ln)=>$ln['extra_class_allowance']+$ln['other_additions'], $lines));
        $totTax = array_sum(array_column($lines, 'tax_deduction'));
        $totPF = array_sum(array_column($lines, 'provident_fund'));
        $totLoan = array_sum(array_column($lines, 'advance_deduction'));
        $totAbsFine = array_sum(array_map(fn($ln)=>$ln['absence_deduction']+$ln['fine_deduction'], $lines));
        ?>
        <tr class="table-light fw-700">
          <td colspan="2">TOTAL</td>
          <td><?= money($totBase) ?></td>
          <td class="text-success"><?= money($totDuty) ?></td>
          <td class="text-success"><?= money($totBonus) ?></td>
          <td class="text-success"><?= money($totOtherAllow) ?></td>
          <td class="text-danger"><?= money($totTax) ?></td>
          <td class="text-danger"><?= money($totPF) ?></td>
          <td class="text-danger"><?= money($totLoan) ?></td>
          <td class="text-danger"><?= money($totAbsFine) ?></td>
          <td><?= money($totNet) ?></td>
          <?php if($run['status']==='draft' && has_permission('payroll.manage')): ?><td></td><?php endif; ?>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Line edit modal -->
<div class="modal fade" id="lineModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content text-dark">
      <form method="POST">
        <?= csrf_field() ?><input type="hidden" name="action" value="update_line"><input type="hidden" name="line_id" id="pl_id" value="">
        <div class="modal-header"><h5 class="modal-title">Adjust Payroll Line — <span id="pl_name"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="form-section-title mt-0">Additions & Bonuses</div>
          <div class="row g-2">
            <div class="col-md-6"><label class="form-label small">Exam Duty Allowance</label><input type="number" name="exam_duty_allowance" id="pl_edu" class="form-control form-control-sm" step="0.01" value="0"></div>
            <div class="col-md-6"><label class="form-label small">Extra Class Allowance</label><input type="number" name="extra_class_allowance" id="pl_eca" class="form-control form-control-sm" step="0.01" value="0"></div>
            <div class="col-md-6"><label class="form-label small">Other Additions</label><input type="number" name="other_additions" id="pl_oa" class="form-control form-control-sm" step="0.01" value="0"></div>
            <div class="col-md-6"><label class="form-label small">Bonus Amount</label><input type="number" name="bonus_amount" id="pl_bonus_amt" class="form-control form-control-sm" step="0.01" value="0"></div>
            <div class="col-md-12"><label class="form-label small">Bonus Description</label><input type="text" name="bonus_desc" id="pl_bonus_desc" class="form-control form-control-sm" placeholder="e.g. Performance"></div>
          </div>
          <div class="form-section-title">Deductions</div>
          <div class="row g-2">
            <div class="col-md-6"><label class="form-label small">Tax</label><input type="number" name="tax_deduction" id="pl_tax" class="form-control form-control-sm" step="0.01" value="0"></div>
            <div class="col-md-6"><label class="form-label small">Provident Fund</label><input type="number" name="provident_fund" id="pl_pf" class="form-control form-control-sm" step="0.01" value="0"></div>
            <div class="col-md-6"><label class="form-label small">Advance/Loan Deduction (Offset)</label><input type="number" name="advance_deduction" id="pl_adv" class="form-control form-control-sm" step="0.01" value="0"></div>
            <div class="col-md-6"><label class="form-label small">Fine</label><input type="number" name="fine_deduction" id="pl_fine" class="form-control form-control-sm" step="0.01" value="0"></div>
          </div>
          <div class="mt-3"><label class="form-label small">Notes</label><input type="text" name="notes" id="pl_notes" class="form-control form-control-sm"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update</button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- One-Time Payout Modal -->
<div class="modal fade" id="customPayoutModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content text-dark">
      <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="custom_payout">
        <input type="hidden" name="session_id" value="<?= $session_id ?>">
        <div class="modal-header"><h5 class="modal-title">Disburse One-Time Payout</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small">Select Staff Member *</label>
            <select name="staff_id" class="form-select form-select-sm" required>
              <option value="">— Choose Staff —</option>
              <?php foreach ($staffListForPayout as $st): ?>
                <option value="<?= $st['user_id'] ?>"><?= e($st['name']) ?> (<?= e($st['designation']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small">Payout Description / Type *</label>
            <input type="text" name="description" class="form-control form-control-sm" placeholder="e.g. Festival Incentive, Gratuity, Bonus" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small">Amount (৳) *</label>
              <input type="number" name="amount" class="form-control form-control-sm" min="1" step="any" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small">Disbursal Account *</label>
              <select name="account_id" class="form-select form-select-sm" required>
                <option value="">— Select Account —</option>
                <?php foreach ($accounts as $acc): ?>
                  <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (<?= setting('currency_symbol','৳').number_format($acc['current_balance'],2) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary btn-sm">Disburse Payout</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function setLineForm(l){
  document.getElementById('pl_id').value=l.id;
  document.getElementById('pl_name').textContent=l.staff_name;
  document.getElementById('pl_edu').value=l.exam_duty_allowance;
  document.getElementById('pl_eca').value=l.extra_class_allowance;
  document.getElementById('pl_oa').value=l.other_additions;
  document.getElementById('pl_tax').value=l.tax_deduction;
  document.getElementById('pl_pf').value=l.provident_fund;
  document.getElementById('pl_adv').value=l.advance_deduction;
  document.getElementById('pl_fine').value=l.fine_deduction;
  document.getElementById('pl_bonus_amt').value=l.bonus_amount;
  document.getElementById('pl_bonus_desc').value=l.bonus_desc||'';
  document.getElementById('pl_notes').value=l.notes||'';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
