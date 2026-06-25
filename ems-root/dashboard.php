<?php
define('EMS_ROOT', __DIR__);
$page_title  = 'Dashboard';
$breadcrumbs = [];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth();

$pdo        = db();
$session_id = (int)setting('current_session_id', 0);

// ── Fetch dashboard stats ──────────────────────────────────────────────────
$stats = [];

// Students
try {
    $q = $session_id
        ? $pdo->prepare('SELECT COUNT(*) FROM student_enrollments WHERE session_id=:s AND status="active"')
        : null;
    if ($q) { $q->execute([':s' => $session_id]); $stats['students'] = (int)$q->fetchColumn(); }
    else $stats['students'] = (int)$pdo->query('SELECT COUNT(*) FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.role_slug="student"')->fetchColumn();
} catch (Exception $e) { $stats['students'] = 0; }

// Staff
try {
    $stats['staff'] = (int)$pdo->query('SELECT COUNT(*) FROM staff_profiles WHERE status="active"')->fetchColumn();
} catch (Exception $e) { $stats['staff'] = 0; }

// Today's fee collections
try {
    $q = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE payment_date=CURDATE()');
    $q->execute();
    $stats['today_collection'] = (float)$q->fetchColumn();
} catch (Exception $e) { $stats['today_collection'] = 0; }

// Pending fee waivers
try {
    $stats['pending_waivers'] = (int)$pdo->query('SELECT COUNT(*) FROM waivers WHERE status="pending"')->fetchColumn();
} catch (Exception $e) { $stats['pending_waivers'] = 0; }

// Pending leave applications
try {
    $stats['pending_leave'] = (int)$pdo->query('SELECT COUNT(*) FROM leave_applications WHERE status="pending"')->fetchColumn();
} catch (Exception $e) { $stats['pending_leave'] = 0; }

// Low-stock consumables
try {
    $stats['low_stock'] = (int)$pdo->query('SELECT COUNT(*) FROM consumables WHERE current_stock <= min_threshold')->fetchColumn();
} catch (Exception $e) { $stats['low_stock'] = 0; }

// Monthly fee collection (last 6 months)
$monthly_data = [];
try {
    $q = $pdo->query("SELECT DATE_FORMAT(payment_date,'%b %Y') as month, MONTH(payment_date) as mn, YEAR(payment_date) as yr, SUM(amount) as total
                      FROM fee_payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                      GROUP BY yr, mn ORDER BY yr, mn");
    $monthly_data = $q->fetchAll();
} catch (Exception $e) {}

// Recent fee payments
$recent_payments = [];
try {
    $q = $pdo->query("SELECT fp.id, fp.amount, fp.payment_date, fp.receipt_number, fp.payment_method,
                             u.full_name, fc.category_name
                      FROM fee_payments fp
                      JOIN users u ON u.id = fp.student_id
                      JOIN fee_ledgers fl ON fl.id = fp.ledger_id
                      JOIN fee_categories fc ON fc.id = fl.fee_category_id
                      ORDER BY fp.id DESC LIMIT 8");
    $recent_payments = $q->fetchAll();
} catch (Exception $e) {}

// Upcoming exams
$upcoming_exams = [];
try {
    $q = $pdo->query("SELECT exam_name, start_date, end_date, status FROM exams
                      WHERE start_date >= CURDATE() AND status IN ('draft','scheduled')
                      ORDER BY start_date LIMIT 5");
    $upcoming_exams = $q->fetchAll();
} catch (Exception $e) {}

// Recent activity
$recent_logs = [];
try {
    $q = $pdo->query("SELECT al.action, al.module, al.created_at, u.full_name
                      FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id
                      ORDER BY al.id DESC LIMIT 8");
    $recent_logs = $q->fetchAll();
} catch (Exception $e) {}

$chart_labels = json_encode(array_column($monthly_data, 'month'));
$chart_values = json_encode(array_column($monthly_data, 'total'));

// Fetch latest active broadcasted notice
$noticeBanner = null;
try {
    $nbStmt = $pdo->query("SELECT * FROM notices WHERE is_broadcast=1 AND publish_date <= CURDATE() ORDER BY id DESC LIMIT 1");
    $noticeBanner = $nbStmt->fetch();
} catch (Exception $e) {}

require_once EMS_ROOT . '/includes/header.php';
?>

<h1 class="page-title">
  Dashboard <small>Overview — <?= e(setting('school_name', 'EMS')) ?></small>
</h1>

<?php if ($noticeBanner): ?>
<div class="alert alert-info border-primary d-flex align-items-center gap-3 p-3 mb-4 shadow-sm" role="alert" style="border-left: 5px solid var(--ems-primary) !important;">
  <i class="bi bi-megaphone-fill text-primary fs-4"></i>
  <div>
    <div class="fw-bold small text-primary mb-1">📢 ANNOUNCEMENT: <?= e($noticeBanner['title']) ?> (<?= fmt_date($noticeBanner['publish_date']) ?>)</div>
    <div class="text-sm text-dark"><?= e(substr($noticeBanner['content'], 0, 200)) ?><?= strlen($noticeBanner['content']) > 200 ? '...' : '' ?></div>
    <a href="modules/communication/notices.php" class="alert-link small mt-1 d-inline-block">Read Full Notice →</a>
  </div>
</div>
<?php endif; ?>

<!-- ── Stat Cards ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card primary h-100">
      <div class="stat-value"><?= number_format($stats['students']) ?></div>
      <div class="stat-label">Active Students</div>
      <i class="bi bi-person-badge-fill stat-icon"></i>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card success h-100">
      <div class="stat-value"><?= number_format($stats['staff']) ?></div>
      <div class="stat-label">Active Staff</div>
      <i class="bi bi-people-fill stat-icon"></i>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card info h-100">
      <div class="stat-value"><?= money($stats['today_collection']) ?></div>
      <div class="stat-label">Today's Collection</div>
      <i class="bi bi-cash-coin stat-icon"></i>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card warning h-100">
      <div class="stat-value"><?= $stats['pending_waivers'] ?></div>
      <div class="stat-label">Pending Waivers</div>
      <i class="bi bi-hourglass-split stat-icon"></i>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card purple h-100">
      <div class="stat-value"><?= $stats['pending_leave'] ?></div>
      <div class="stat-label">Leave Requests</div>
      <i class="bi bi-calendar-x stat-icon"></i>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="stat-card danger h-100">
      <div class="stat-value"><?= $stats['low_stock'] ?></div>
      <div class="stat-label">Low Stock Items</div>
      <i class="bi bi-boxes stat-icon"></i>
    </div>
  </div>
</div>

<!-- ── Charts + Recent Payments ────────────────────────────── -->
<div class="row g-3 mb-4">
  <!-- Monthly collection chart -->
  <div class="col-md-7">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span class="card-title">Monthly Fee Collection</span>
        <a href="modules/finance/reports.php" class="btn btn-sm btn-outline-primary">View Report</a>
      </div>
      <div class="card-body p-3">
        <canvas id="feeChart" style="max-height:220px;"></canvas>
        <?php if (empty($monthly_data)): ?>
          <div class="empty-state py-3"><i class="bi bi-bar-chart"></i><p>No collection data yet</p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Quick actions -->
  <div class="col-md-5">
    <div class="card h-100">
      <div class="card-header py-3 px-4">
        <span class="card-title">Quick Actions</span>
      </div>
      <div class="card-body">
        <div class="row g-2">
          <?php $qa = [
            ['icon'=>'person-plus-fill',  'label'=>'New Admission', 'url'=>'modules/students/create.php',   'color'=>'primary',  'perm'=>'students.create'],
            ['icon'=>'cash-stack',         'label'=>'Collect Fee',   'url'=>'modules/finance/collect.php',   'color'=>'success',  'perm'=>'fees.collect'],
            ['icon'=>'clipboard2-plus',    'label'=>'New Exam',      'url'=>'modules/exams/index.php',       'color'=>'info',     'perm'=>'exams.manage'],
            ['icon'=>'person-workspace',   'label'=>'Add Staff',     'url'=>'modules/hr/create.php',         'color'=>'warning',  'perm'=>'hr.manage'],
            ['icon'=>'chat-left-dots-fill','label'=>'Send SMS',      'url'=>'modules/communication/sms.php', 'color'=>'purple',   'perm'=>'sms.send'],
            ['icon'=>'file-earmark-text',  'label'=>'Reports',       'url'=>'modules/reports/index.php',     'color'=>'danger',   'perm'=>'reports.view'],
          ]; foreach ($qa as $a): if (!$a['perm'] || has_permission($a['perm'])): ?>
          <div class="col-6">
            <a href="<?= e($a['url']) ?>" class="btn btn-<?= $a['color'] ?> w-100 d-flex align-items-center gap-2 py-2">
              <i class="bi bi-<?= $a['icon'] ?>"></i> <?= e($a['label']) ?>
            </a>
          </div>
          <?php endif; endforeach; ?>
        </div>

        <?php if (!empty($upcoming_exams)): ?>
        <div class="mt-3">
          <div class="form-section-title mt-0">Upcoming Exams</div>
          <ul class="list-unstyled mb-0">
            <?php foreach ($upcoming_exams as $ex): ?>
            <li class="d-flex align-items-center justify-content-between py-1 border-bottom">
              <div>
                <div class="fw-600 small"><?= e($ex['exam_name']) ?></div>
                <div class="text-muted" style="font-size:.75rem;"><?= fmt_date($ex['start_date']) ?></div>
              </div>
              <span class="badge-status badge-<?= e($ex['status']) ?>"><?= ucfirst(e($ex['status'])) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Recent Payments + Activity Log ──────────────────────── -->
<div class="row g-3">
  <?php if (has_permission('finance.view') && !empty($recent_payments)): ?>
  <div class="col-md-7">
    <div class="card table-card">
      <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span class="card-title">Recent Fee Payments</span>
        <a href="modules/finance/ledger.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="data-table">
          <thead>
            <tr>
              <th>Student</th><th>Category</th><th>Amount</th><th>Method</th><th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_payments as $p): ?>
            <tr>
              <td class="fw-600"><?= e($p['full_name']) ?></td>
              <td><?= e($p['category_name']) ?></td>
              <td class="fw-600"><?= money($p['amount']) ?></td>
              <td><span class="badge bg-light text-dark text-capitalize"><?= e(str_replace('_',' ',$p['payment_method'])) ?></span></td>
              <td><?= fmt_date($p['payment_date'], 'd M') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Activity log -->
  <div class="col-md-<?= (has_permission('finance.view') && !empty($recent_payments)) ? '5' : '12' ?>">
    <div class="card h-100">
      <div class="card-header py-3 px-4">
        <span class="card-title">Recent Activity</span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recent_logs)): ?>
          <div class="empty-state"><i class="bi bi-clock-history"></i><p>No activity yet</p></div>
        <?php else: ?>
          <ul class="list-unstyled mb-0">
            <?php foreach ($recent_logs as $log): ?>
            <li class="d-flex align-items-start gap-3 p-3 border-bottom">
              <div class="rounded-circle bg-light d-grid place-items-center flex-shrink-0" style="width:34px;height:34px;place-items:center;">
                <i class="bi bi-clock text-muted small"></i>
              </div>
              <div>
                <div class="fw-600 small"><?= e(ucwords(str_replace('_',' ',$log['action']))) ?></div>
                <div class="text-muted" style="font-size:.75rem;">
                  <?= e($log['full_name'] ?? 'System') ?> &bull; <?= e($log['module'] ?? '') ?> &bull;
                  <?= fmt_date($log['created_at'], 'd M, H:i') ?>
                </div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($monthly_data)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('feeChart'), {
  type: 'bar',
  data: {
    labels: <?= $chart_labels ?>,
    datasets: [{
      label: 'Collection (৳)',
      data: <?= $chart_values ?>,
      backgroundColor: 'rgba(26,86,219,.75)',
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => '৳' + v.toLocaleString() } } }
  }
});
</script>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
