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

// ── Outstanding Dues ──────────────────────────────────────────────────────────
$outstanding = [];
if ($type === 'outstanding') {
    $os = $pdo->prepare("SELECT c.class_name, COUNT(DISTINCT fl.student_id) as students, SUM(fl.amount_due - fl.amount_paid - fl.waiver_amount) as outstanding FROM fee_ledgers fl JOIN student_enrollments se ON se.student_id=fl.student_id AND se.session_id=fl.session_id AND se.status='active' JOIN classes c ON c.id=se.class_id WHERE fl.session_id=:sess AND fl.status != 'paid' GROUP BY c.id ORDER BY outstanding DESC");
    $os->execute([':sess'=>$session_id]);
    $outstanding = $os->fetchAll();
}

require_once EMS_ROOT . '/includes/header.php';
$yr = $_GET['year'] ?? date('Y');
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-file-earmark-bar-graph-fill me-2 text-primary"></i>Financial Reports</h1>
  <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
</div>

<!-- Report type tabs -->
<ul class="nav nav-tabs mb-3">
  <?php foreach(['daily'=>'Daily Cash Book','monthly'=>'Monthly Summary','outstanding'=>'Outstanding Dues'] as $k=>$v): ?>
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
    <thead><tr><th>Class</th><th>Students with Dues</th><th>Total Outstanding</th><th>Bar</th></tr></thead>
    <tbody>
      <?php if(empty($outstanding)): ?>
        <tr><td colspan="4"><div class="empty-state"><i class="bi bi-check-circle text-success"></i><p>No outstanding dues!</p></div></td></tr>
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
        </tr>
        <?php endforeach; ?>
        <tr class="table-dark fw-700"><td colspan="2">TOTAL OUTSTANDING</td><td class="text-danger"><?= money($totOs) ?></td><td></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div></div>
<?php endif; ?>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
