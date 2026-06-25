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

        // Create or fetch payroll run
        $pdo->prepare('INSERT IGNORE INTO payroll_runs (session_id,month,year,status,created_by) VALUES (?,?,?,"draft",?)')->execute([$sess,$mon,$yr,current_user_id()]);
        $runStmt = $pdo->prepare('SELECT id FROM payroll_runs WHERE session_id=? AND month=? AND year=?');
        $runStmt->execute([$sess,$mon,$yr]);
        $runId = (int)$runStmt->fetchColumn();

        // Load active staff
        $staffAll = $pdo->query("SELECT sp.user_id, sp.base_salary, sp.salary_type FROM staff_profiles sp WHERE sp.status='active'")->fetchAll();

        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $mon, $yr);
        $stmt = $pdo->prepare('INSERT INTO payroll_lines (payroll_run_id,staff_id,base_salary,exam_duty_allowance,net_salary) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE base_salary=VALUES(base_salary),exam_duty_allowance=VALUES(exam_duty_allowance),net_salary=VALUES(net_salary)');

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

            $net = $sp['base_salary'] + $dutyAllow - $absDeduction;
            $stmt->execute([$runId,$sp['user_id'],$sp['base_salary'],$dutyAllow,$net]);
        }
        flash('success', 'Payroll generated for '.date('F Y',mktime(0,0,0,$mon,1,$yr)).'.');
        header("Location: payroll.php?session_id=$sess&month=$mon&year=$yr");
        exit;
    } elseif ($action === 'finalize') {
        $runId = int_param('run_id',0,$_POST);
        $pdo->prepare("UPDATE payroll_runs SET status='finalized' WHERE id=?")->execute([$runId]);
        flash('success','Payroll finalized.');
        header('Location: payroll.php?session_id='.$session_id.'&month='.$month.'&year='.$year);
        exit;
    } elseif ($action === 'update_line') {
        $lineId = int_param('line_id',0,$_POST);
        $fields = ['exam_duty_allowance','extra_class_allowance','other_additions','tax_deduction','provident_fund','advance_deduction','fine_deduction','notes'];
        $base   = (float)$pdo->query("SELECT base_salary FROM payroll_lines WHERE id=$lineId")->fetchColumn();
        $adds   = 0; $deds = 0;
        $vals   = [];
        foreach ($fields as $f) {
            $v = $f==='notes' ? trim($_POST[$f]??'') : (float)($_POST[$f]??0);
            $vals[$f] = $v;
            if (in_array($f,['exam_duty_allowance','extra_class_allowance','other_additions'])) $adds += (float)$v;
            if (in_array($f,['tax_deduction','provident_fund','advance_deduction','fine_deduction'])) $deds += (float)$v;
        }
        $net = $base + $adds - $deds;
        $pdo->prepare('UPDATE payroll_lines SET exam_duty_allowance=?,extra_class_allowance=?,other_additions=?,tax_deduction=?,provident_fund=?,advance_deduction=?,fine_deduction=?,net_salary=?,notes=? WHERE id=?')
            ->execute(array_merge(array_values($vals),[$net,$lineId]));
        flash('success','Line updated.');
        header('Location: payroll.php?session_id='.$session_id.'&month='.$month.'&year='.$year);
        exit;
    }
}

$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();

// Find current payroll run
$run = null;
$lines = [];
$runStmt = $pdo->prepare('SELECT * FROM payroll_runs WHERE session_id=? AND month=? AND year=?');
$runStmt->execute([$session_id,$month,$year]);
$run = $runStmt->fetch();

if ($run) {
    $linesStmt = $pdo->query("SELECT pl.*, CONCAT(sp.first_name,' ',sp.last_name) as staff_name, sp.designation, sp.department FROM payroll_lines pl JOIN staff_profiles sp ON sp.user_id=pl.staff_id WHERE pl.payroll_run_id={$run['id']} ORDER BY sp.department, sp.first_name");
    $lines = $linesStmt->fetchAll();
}

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-cash-stack me-2 text-primary"></i>Payroll</h1>
  <div class="d-flex gap-2 align-items-center">
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
  <form method="POST" class="mt-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="generate">
    <input type="hidden" name="session_id" value="<?= $session_id ?>">
    <input type="hidden" name="month" value="<?= $month ?>">
    <input type="hidden" name="year" value="<?= $year ?>">
    <button type="submit" class="btn btn-primary"><i class="bi bi-lightning-fill me-1"></i>Generate Payroll</button>
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
      <form method="POST">
        <?= csrf_field() ?><input type="hidden" name="action" value="finalize"><input type="hidden" name="run_id" value="<?= $run['id'] ?>">
        <button type="submit" class="btn btn-success btn-sm" data-confirm="Finalize this payroll? Lines will be locked."><i class="bi bi-lock me-1"></i>Finalize</button>
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
      <thead><tr><th>Staff</th><th>Dept</th><th>Base</th><th>+Duty</th><th>+Other</th><th>-Tax</th><th>-PF</th><th>-Other</th><th class="fw-700">Net</th><?php if($run['status']==='draft' && has_permission('payroll.manage')): ?><th>Edit</th><?php endif; ?></tr></thead>
      <tbody>
        <?php foreach($lines as $ln): ?>
        <tr>
          <td><div class="fw-600"><?= e($ln['staff_name']) ?></div><small class="text-muted"><?= e($ln['designation']??'') ?></small></td>
          <td><?= e($ln['department']??'—') ?></td>
          <td><?= money($ln['base_salary']) ?></td>
          <td class="text-success"><?= money($ln['exam_duty_allowance']+$ln['extra_class_allowance']+$ln['other_additions']) ?></td>
          <td></td>
          <td class="text-danger"><?= money($ln['tax_deduction']) ?></td>
          <td class="text-danger"><?= money($ln['provident_fund']) ?></td>
          <td class="text-danger"><?= money($ln['advance_deduction']+$ln['fine_deduction']+$ln['absence_deduction']) ?></td>
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
        <tr class="table-light fw-700">
          <td colspan="2">TOTAL</td>
          <td><?= money($totBase) ?></td>
          <td colspan="5"></td>
          <td><?= money($totNet) ?></td>
          <?php if($run['status']==='draft'): ?><td></td><?php endif; ?>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Line edit modal -->
<div class="modal fade" id="lineModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?><input type="hidden" name="action" value="update_line"><input type="hidden" name="line_id" id="pl_id" value="">
        <div class="modal-header"><h5 class="modal-title">Adjust Payroll Line — <span id="pl_name"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="form-section-title mt-0">Additions</div>
          <div class="row g-2">
            <div class="col-md-6"><label class="form-label small">Exam Duty Allowance</label><input type="number" name="exam_duty_allowance" id="pl_edu" class="form-control form-control-sm" step="0.01" value="0"></div>
            <div class="col-md-6"><label class="form-label small">Extra Class Allowance</label><input type="number" name="extra_class_allowance" id="pl_eca" class="form-control form-control-sm" step="0.01" value="0"></div>
            <div class="col-md-6"><label class="form-label small">Other Additions</label><input type="number" name="other_additions" id="pl_oa" class="form-control form-control-sm" step="0.01" value="0"></div>
          </div>
          <div class="form-section-title">Deductions</div>
          <div class="row g-2">
            <div class="col-md-6"><label class="form-label small">Tax</label><input type="number" name="tax_deduction" id="pl_tax" class="form-control form-control-sm" step="0.01" value="0"></div>
            <div class="col-md-6"><label class="form-label small">Provident Fund</label><input type="number" name="provident_fund" id="pl_pf" class="form-control form-control-sm" step="0.01" value="0"></div>
            <div class="col-md-6"><label class="form-label small">Advance Deduction</label><input type="number" name="advance_deduction" id="pl_adv" class="form-control form-control-sm" step="0.01" value="0"></div>
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
  document.getElementById('pl_notes').value=l.notes||'';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
