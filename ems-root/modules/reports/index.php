<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Reports';
$breadcrumbs = ['Reports' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['reports.view']);

$pdo        = db();
$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);
$sessions   = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();

// ── Financial Summary ──────────────────────────────────────────────────────
$financials = [];
try {
    $q = $pdo->prepare(
        'SELECT fc.category_name, SUM(fl.amount_due) as total_due, SUM(fl.amount_paid) as total_paid,
                SUM(fl.waiver_amount) as total_waiver,
                SUM(fl.amount_due - fl.amount_paid - fl.waiver_amount) as outstanding
         FROM fee_ledgers fl JOIN fee_categories fc ON fc.id=fl.fee_category_id
         WHERE fl.session_id=:sess GROUP BY fc.category_name ORDER BY total_due DESC'
    );
    $q->execute([':sess' => $session_id]);
    $financials = $q->fetchAll();
} catch (Exception $e) {}

// ── Student Summary by Class ───────────────────────────────────────────────
$studentsByClass = [];
try {
    $q = $pdo->prepare(
        'SELECT c.class_name, COUNT(*) as total,
                SUM(CASE WHEN se.status="active" THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN se.gender="male" THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN se.gender="female" THEN 1 ELSE 0 END) as female
         FROM student_enrollments se
         JOIN classes c ON c.id=se.class_id
         LEFT JOIN student_profiles sp ON sp.user_id=se.student_id
         WHERE se.session_id=:sess
         GROUP BY c.id ORDER BY c.display_order'
    );
    $q->execute([':sess' => $session_id]);
    $studentsByClass = $q->fetchAll();
} catch (Exception $e) {}

// ── Attendance rate (last 30 days) ──────────────────────────────────────────
$attendanceStats = [];
try {
    $q = $pdo->query(
        'SELECT status, COUNT(*) as cnt FROM student_attendance
         WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY status'
    );
    foreach ($q->fetchAll() as $row) $attendanceStats[$row['status']] = $row['cnt'];
} catch (Exception $e) {}

$totalAtt = array_sum($attendanceStats);
$attPct   = $totalAtt > 0 ? round(($attendanceStats['present'] ?? 0) / $totalAtt * 100) : 0;

// ── Monthly expense vs income ──────────────────────────────────────────────
$monthlyFinance = [];
try {
    $q = $pdo->query(
        'SELECT DATE_FORMAT(expense_date,"%b %Y") as month, SUM(amount) as expenses
         FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY DATE_FORMAT(expense_date,"%Y-%m") ORDER BY expense_date'
    );
    foreach ($q->fetchAll() as $r) $monthlyFinance[$r['month']]['expenses'] = $r['expenses'];

    $q = $pdo->query(
        'SELECT DATE_FORMAT(payment_date,"%b %Y") as month, SUM(amount) as income
         FROM fee_payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY DATE_FORMAT(payment_date,"%Y-%m") ORDER BY payment_date'
    );
    foreach ($q->fetchAll() as $r) $monthlyFinance[$r['month']]['income'] = $r['income'];
} catch (Exception $e) {}

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Reports & Analytics</h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" onchange="location='?session_id='+this.value" style="width:auto;">
      <?php foreach ($sessions as $sess): ?>
        <option value="<?= $sess['id'] ?>" <?= $session_id == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
  </div>
</div>

<!-- ── Quick Stats Row ──────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h6 class="fw-700 mb-0"><i class="bi bi-clipboard2-pulse text-primary me-2"></i>Attendance Rate (30d)</h6>
          <span class="badge bg-<?= $attPct >= 75 ? 'success' : ($attPct >= 50 ? 'warning' : 'danger') ?>"><?= $attPct ?>%</span>
        </div>
        <div class="progress mb-2" style="height:8px;">
          <div class="progress-bar bg-<?= $attPct >= 75 ? 'success' : 'warning' ?>" style="width:<?= $attPct ?>%"></div>
        </div>
        <div class="d-flex justify-content-between small text-muted">
          <span>Present: <?= $attendanceStats['present'] ?? 0 ?></span>
          <span>Absent: <?= $attendanceStats['absent'] ?? 0 ?></span>
          <span>Late: <?= $attendanceStats['late'] ?? 0 ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Quick Report Links</span></div>
      <div class="card-body">
        <div class="row g-2">
          <?php
          $links = [
            ['icon'=>'cash-coin','label'=>'Daily Cash Book','url'=>'../finance/reports.php?type=daily','perm'=>'finance.view'],
            ['icon'=>'person-badge','label'=>'Student Register','url'=>'../students/index.php','perm'=>'students.view'],
            ['icon'=>'clipboard2-check','label'=>'Exam Results','url'=>'../exams/results.php','perm'=>'marks.approve'],
            ['icon'=>'people-fill','label'=>'Staff Payroll','url'=>'../hr/payroll.php','perm'=>'payroll.view'],
            ['icon'=>'boxes','label'=>'Stock Ledger','url'=>'../inventory/stock.php','perm'=>'inventory.view'],
            ['icon'=>'activity','label'=>'Activity Log','url'=>'../setup/audit.php','perm'=>'setup.view'],
          ];
          foreach ($links as $lnk):
            if ($lnk['perm'] && !has_permission($lnk['perm'])) continue;
          ?>
          <div class="col-sm-4">
            <a href="<?= e($lnk['url']) ?>" class="btn btn-outline-primary btn-sm w-100 d-flex align-items-center gap-2">
              <i class="bi bi-<?= $lnk['icon'] ?>"></i><?= e($lnk['label']) ?>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Student Summary ──────────────────────────────────────────────────── -->
<?php if (!empty($studentsByClass)): ?>
<div class="card mb-4">
  <div class="card-header py-3 px-4"><span class="card-title">Student Summary by Class</span></div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Class</th><th>Total</th><th>Active</th><th>Male</th><th>Female</th><th>Male %</th></tr></thead>
      <tbody>
        <?php
        $grandTotal = array_sum(array_column($studentsByClass, 'total'));
        foreach ($studentsByClass as $row):
          $malePct = $row['total'] > 0 ? round($row['male']/$row['total']*100) : 0;
        ?>
        <tr>
          <td class="fw-600"><?= e($row['class_name']) ?></td>
          <td><?= $row['total'] ?></td>
          <td class="text-success fw-600"><?= $row['active'] ?></td>
          <td><?= $row['male'] ?? 0 ?></td>
          <td><?= $row['female'] ?? 0 ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="progress flex-grow-1" style="height:4px;">
                <div class="progress-bar" style="width:<?= $malePct ?>%"></div>
              </div>
              <small><?= $malePct ?>%</small>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-dark fw-700">
          <td>Total</td>
          <td><?= $grandTotal ?></td>
          <td colspan="4"></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── Financial Summary ────────────────────────────────────────────────── -->
<?php if (!empty($financials)): ?>
<div class="card mb-4">
  <div class="card-header py-3 px-4"><span class="card-title">Fee Collection Summary</span></div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Category</th><th>Total Due</th><th>Collected</th><th>Waiver</th><th>Outstanding</th><th>Collection Rate</th></tr></thead>
      <tbody>
        <?php
        $totDue = $totPaid = $totWaiver = $totOut = 0;
        foreach ($financials as $row):
          $pct = $row['total_due'] > 0 ? round($row['total_paid']/$row['total_due']*100) : 0;
          $totDue    += $row['total_due'];
          $totPaid   += $row['total_paid'];
          $totWaiver += $row['total_waiver'];
          $totOut    += $row['outstanding'];
        ?>
        <tr>
          <td class="fw-600"><?= e($row['category_name']) ?></td>
          <td><?= money($row['total_due']) ?></td>
          <td class="text-success fw-600"><?= money($row['total_paid']) ?></td>
          <td class="text-warning"><?= money($row['total_waiver']) ?></td>
          <td class="<?= $row['outstanding'] > 0 ? 'text-danger fw-600' : '' ?>"><?= money(max(0,$row['outstanding'])) ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="progress flex-grow-1" style="height:6px;">
                <div class="progress-bar bg-<?= $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning' : 'danger') ?>" style="width:<?= $pct ?>%"></div>
              </div>
              <small class="fw-600"><?= $pct ?>%</small>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-light fw-700">
          <td>Total</td>
          <td><?= money($totDue) ?></td>
          <td class="text-success"><?= money($totPaid) ?></td>
          <td class="text-warning"><?= money($totWaiver) ?></td>
          <td class="<?= $totOut > 0 ? 'text-danger' : '' ?>"><?= money(max(0,$totOut)) ?></td>
          <td><?= $totDue > 0 ? round($totPaid/$totDue*100) : 0 ?>%</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
