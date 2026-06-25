<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Student Fee Dues';
$breadcrumbs = ['Finance' => 'ledger.php', 'Student Dues' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
// Teachers (routine.view) + Finance staff can access
require_auth(); // login required
// Either finance.view (accountants) OR students.view (teachers) can access
if (!has_permission('finance.view') && !has_permission('students.view')) {
    http_response_code(403);
    include EMS_ROOT . '/includes/403.php';
    exit;
}

$pdo        = db();
$session_id = int_param('session_id', (int)setting('current_session_id', 0), $_GET);
$class_id   = int_param('class_id',   0, $_GET);
$section_id = int_param('section_id', 0, $_GET);
$fee_cat_id = int_param('fee_cat_id', 0, $_GET);
$period     = $_GET['period'] ?? 'session';   // session | thismonth | custom
$from_date  = $_GET['from'] ?? date('Y-m-01');
$to_date    = $_GET['to']   ?? date('Y-m-d');
$print_mode = !empty($_GET['print']);

$sessions      = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes       = $pdo->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY display_order,class_name')->fetchAll();
$feeCategories = $pdo->query('SELECT id,category_name FROM fee_categories WHERE status=1 ORDER BY category_name')->fetchAll();

$sections = [];
if ($class_id) {
    $s = $pdo->prepare('SELECT id,section_name FROM sections WHERE class_id=? AND status=1 ORDER BY section_name');
    $s->execute([$class_id]);
    $sections = $s->fetchAll();
}

// ── Build the dues query ──────────────────────────────────────────────────────
$dueStudents = [];
$totalDue = $totalPaid = $totalOutstanding = 0;
$classBreakdown = [];

$whJoin  = 'se.session_id=:sess AND se.status="active"';
$whPrams = [':sess' => $session_id];
if ($class_id)   { $whJoin .= ' AND se.class_id=:cls';   $whPrams[':cls'] = $class_id; }
if ($section_id) { $whJoin .= ' AND se.section_id=:sec'; $whPrams[':sec'] = $section_id; }

$ledWhere = 'fl.session_id=:sess2 AND (fl.amount_due - fl.amount_paid - fl.waiver_amount) > 0.01';
$ledPrams = [':sess2' => $session_id];
if ($fee_cat_id) { $ledWhere .= ' AND fl.fee_category_id=:cat'; $ledPrams[':cat'] = $fee_cat_id; }

// Period filter on due_date
if ($period === 'thismonth') {
    $ledWhere .= ' AND fl.due_date BETWEEN :from AND :to';
    $ledPrams[':from'] = date('Y-m-01');
    $ledPrams[':to']   = date('Y-m-t');
} elseif ($period === 'custom') {
    $ledWhere .= ' AND fl.due_date BETWEEN :from AND :to';
    $ledPrams[':from'] = $from_date;
    $ledPrams[':to']   = $to_date;
}

$sql = "
    SELECT u.id AS student_id,
           u.full_name,
           sp.student_id_no,
           sp.guardian_phone,
           se.roll_number,
           c.class_name,
           sec.section_name,
           SUM(fl.amount_due)  AS total_due,
           SUM(fl.amount_paid) AS total_paid,
           SUM(fl.waiver_amount) AS total_waiver,
           SUM(fl.amount_due - fl.amount_paid - fl.waiver_amount) AS outstanding,
           GROUP_CONCAT(
             CONCAT(fc.category_name, IFNULL(CONCAT(' ', DATE_FORMAT(STR_TO_DATE(CONCAT(fl.year,'-',fl.month,'-01'),'%Y-%m-%d'),'%b %Y')),''))
             ORDER BY fl.due_date SEPARATOR ' | '
           ) AS due_items
    FROM student_enrollments se
    JOIN users u ON u.id = se.student_id
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    LEFT JOIN sections sec ON sec.id = se.section_id
    LEFT JOIN classes c ON c.id = se.class_id
    JOIN fee_ledgers fl ON fl.student_id = se.student_id AND $ledWhere
    JOIN fee_categories fc ON fc.id = fl.fee_category_id
    WHERE $whJoin
    GROUP BY u.id, u.full_name, sp.student_id_no, sp.guardian_phone, se.roll_number, c.class_name, sec.section_name
    HAVING outstanding > 0.01
    ORDER BY c.class_name, sec.section_name, se.roll_number
";

$merged = array_merge($whPrams, $ledPrams);
$stmt   = $pdo->prepare($sql);
$stmt->execute($merged);
$dueStudents = $stmt->fetchAll();

foreach ($dueStudents as $d) {
    $totalDue         += $d['total_due'];
    $totalPaid        += $d['total_paid'];
    $totalOutstanding += $d['outstanding'];
    $classKey = $d['class_name'] . ' — ' . $d['section_name'];
    if (!isset($classBreakdown[$classKey])) {
        $classBreakdown[$classKey] = ['count' => 0, 'outstanding' => 0];
    }
    $classBreakdown[$classKey]['count']++;
    $classBreakdown[$classKey]['outstanding'] += $d['outstanding'];
}

// Fee-wise breakdown for the whole class/session
$feeBreakdown = [];
if ($class_id || !$class_id) {
    $fbWhere = 'fl.session_id=?';
    $fbPrams = [$session_id];
    if ($class_id) {
        $fbWhere .= ' AND se.class_id=?';
        $fbPrams[] = $class_id;
    }
    if ($fee_cat_id) { $fbWhere .= ' AND fl.fee_category_id=?'; $fbPrams[] = $fee_cat_id; }

    $fb = $pdo->prepare("
        SELECT fc.category_name, fc.category_type,
               SUM(fl.amount_due) AS total_due,
               SUM(fl.amount_paid) AS total_paid,
               SUM(fl.waiver_amount) AS total_waiver,
               SUM(fl.amount_due - fl.amount_paid - fl.waiver_amount) AS outstanding,
               COUNT(DISTINCT fl.student_id) AS student_count,
               COUNT(CASE WHEN fl.status='paid' THEN 1 END) AS paid_count,
               COUNT(fl.id) AS total_count
        FROM fee_ledgers fl
        JOIN fee_categories fc ON fc.id = fl.fee_category_id
        JOIN student_enrollments se ON se.student_id = fl.student_id AND se.session_id = fl.session_id AND se.status='active'
        WHERE $fbWhere
        GROUP BY fc.id, fc.category_name, fc.category_type
        ORDER BY outstanding DESC
    ");
    $fb->execute($fbPrams);
    $feeBreakdown = $fb->fetchAll();
}

$schoolName = setting('school_name', 'EMS Bangladesh');

if ($print_mode) {
    // Printable view
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Due List</title>
    <style>
      body{font-family:"Segoe UI",Arial,sans-serif;margin:15mm;font-size:12px;color:#1e293b;}
      h2{text-align:center;margin:0;font-size:16px;}
      .subtitle{text-align:center;color:#64748b;margin-bottom:15px;font-size:11px;}
      table{width:100%;border-collapse:collapse;margin-top:10px;}
      th{background:#1e293b;color:#fff;padding:5px 8px;text-align:left;font-size:11px;}
      td{padding:4px 8px;border-bottom:1px solid #e2e8f0;font-size:11px;}
      tr:nth-child(even){background:#f8fafc;}
      .text-end{text-align:right;}
      .total-row{background:#0f172a!important;color:#fff;font-weight:700;}
      @media print{@page{margin:10mm;} button{display:none;}}
    </style></head><body>
    <div style="text-align:right;margin-bottom:8px;"><button onclick="window.print()" style="padding:5px 12px;background:#1a56db;color:#fff;border:none;border-radius:4px;cursor:pointer;">Print</button></div>
    <h2>' . e($schoolName) . '</h2>
    <div class="subtitle">Student Due Fee Report &nbsp;|&nbsp; Session: ' . e(array_column($sessions,'session_name','id')[$session_id] ?? '') . ' &nbsp;|&nbsp; Generated: ' . date('d M Y H:i') . '</div>
    <table><thead><tr><th>#</th><th>Student</th><th>ID No</th><th>Class</th><th>Roll</th><th>Phone</th><th>Due Items</th><th class="text-end">Outstanding</th></tr></thead><tbody>';
    $i = 1;
    foreach ($dueStudents as $d) {
        echo '<tr><td>' . $i++ . '</td><td>' . e($d['full_name']) . '</td><td>' . e($d['student_id_no'] ?? '—') . '</td><td>' . e($d['class_name'] . ' ' . $d['section_name']) . '</td><td>' . $d['roll_number'] . '</td><td>' . e($d['guardian_phone'] ?? '—') . '</td><td style="font-size:10px;">' . e($d['due_items']) . '</td><td class="text-end" style="color:#dc2626;font-weight:700;">৳ ' . number_format($d['outstanding'],2) . '</td></tr>';
    }
    echo '<tr class="total-row"><td colspan="7">TOTAL OUTSTANDING (' . count($dueStudents) . ' students)</td><td class="text-end">৳ ' . number_format($totalOutstanding, 2) . '</td></tr>';
    echo '</tbody></table></body></html>';
    exit;
}

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Student Fee Dues</h1>
  <div class="d-flex gap-2">
    <?php if (!empty($dueStudents)): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['print'=>1])) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-printer me-1"></i>Print List
      </a>
      <a href="master_collection.php?session_id=<?= $session_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>&fee_cat_id=<?= $fee_cat_id ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-table me-1"></i>Open Collection Grid
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Filters -->
<div class="card mb-4 border-0 shadow-sm">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label small fw-600">Session</label>
        <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sessions as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-600">Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— All Classes —</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $class_id==$c['id']?'selected':'' ?>><?= e($c['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-600">Section</label>
        <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— All Sections —</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>" <?= $section_id==$sec['id']?'selected':'' ?>><?= e($sec['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-600">Fee Type</label>
        <select name="fee_cat_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">— All Fees —</option>
          <?php foreach ($feeCategories as $fc): ?>
            <option value="<?= $fc['id'] ?>" <?= $fee_cat_id==$fc['id']?'selected':'' ?>><?= e($fc['category_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-600">Period</label>
        <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="session" <?= $period==='session'?'selected':'' ?>>Full Session</option>
          <option value="thismonth" <?= $period==='thismonth'?'selected':'' ?>>This Month</option>
          <option value="custom" <?= $period==='custom'?'selected':'' ?>>Custom Range</option>
        </select>
      </div>
      <?php if ($period === 'custom'): ?>
      <div class="col-md-2">
        <label class="form-label small fw-600">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from_date) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-600">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to_date) ?>">
        <button type="submit" class="btn btn-primary btn-sm mt-1 w-100"><i class="bi bi-search"></i></button>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if (empty($dueStudents)): ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><i class="bi bi-check-circle text-success" style="font-size:3rem;"></i>
    <p class="text-success fw-600 mt-2">No outstanding dues found for the selected filters!</p>
  </div>
</div></div>

<?php else: ?>

<!-- Summary stats -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;">
      <div class="stat-value" style="color:#fff"><?= count($dueStudents) ?></div>
      <div class="stat-label" style="color:rgba(255,255,255,.8)">Students with Dues</div>
      <i class="bi bi-person-exclamation stat-icon" style="color:rgba(255,255,255,.25)"></i>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;">
      <div class="stat-value" style="color:#fff"><?= money($totalOutstanding) ?></div>
      <div class="stat-label" style="color:rgba(255,255,255,.8)">Total Outstanding</div>
      <i class="bi bi-cash-coin stat-icon" style="color:rgba(255,255,255,.25)"></i>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-value"><?= money($totalPaid) ?></div>
      <div class="stat-label">Already Collected</div>
      <i class="bi bi-check-circle stat-icon"></i>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <?php $collectRate = $totalDue > 0 ? round($totalPaid / $totalDue * 100) : 0; ?>
      <div class="stat-value"><?= $collectRate ?>%</div>
      <div class="stat-label">Collection Rate</div>
      <i class="bi bi-bar-chart stat-icon"></i>
      <div class="progress mt-2" style="height:6px;">
        <div class="progress-bar bg-success" style="width:<?= $collectRate ?>%"></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">

  <!-- Fee-wise breakdown -->
  <div class="col-md-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-header py-3 px-4 bg-light"><span class="card-title">Fee-Type Breakdown</span></div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead><tr><th>Fee Type</th><th class="text-end">Outstanding</th><th class="text-center">Rate</th></tr></thead>
          <tbody>
            <?php foreach ($feeBreakdown as $fb):
              $rate = $fb['total_count'] > 0 ? round($fb['paid_count'] / $fb['total_count'] * 100) : 0;
            ?>
            <tr>
              <td style="font-size:.8rem;">
                <?php if ($fee_cat_id == $fb['fee_category_id'] ?? 0): ?>
                  <strong><?= e($fb['category_name']) ?></strong>
                <?php else: ?>
                  <?= e($fb['category_name']) ?>
                <?php endif; ?>
              </td>
              <td class="text-end text-danger fw-600" style="font-size:.8rem;"><?= money($fb['outstanding']) ?></td>
              <td class="text-center" style="font-size:.75rem;">
                <div class="progress" style="height:5px;min-width:60px;"><div class="progress-bar bg-success" style="width:<?= $rate ?>%"></div></div>
                <span class="text-muted"><?= $rate ?>%</span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Class-wise breakdown -->
  <div class="col-md-8">
    <div class="card shadow-sm border-0">
      <div class="card-header py-3 px-4 bg-light"><span class="card-title">Class-wise Outstanding</span></div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead><tr><th>Class / Section</th><th>Students w/ Dues</th><th class="text-end">Total Outstanding</th><th>Progress</th></tr></thead>
          <tbody>
            <?php $maxOs = max(array_column($classBreakdown, 'outstanding')) ?: 1; ?>
            <?php foreach ($classBreakdown as $cls => $cdata): ?>
            <tr>
              <td class="fw-600 small"><?= e($cls) ?></td>
              <td class="small"><?= $cdata['count'] ?></td>
              <td class="text-end text-danger fw-600 small"><?= money($cdata['outstanding']) ?></td>
              <td style="min-width:100px;">
                <div class="progress" style="height:5px;">
                  <div class="progress-bar bg-danger" style="width:<?= round($cdata['outstanding']/$maxOs*100) ?>%"></div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Student list -->
    <div class="card shadow-sm border-0 mt-3">
      <div class="card-header py-3 px-4 bg-light d-flex align-items-center justify-content-between">
        <span class="card-title">Students with Outstanding Dues</span>
        <span class="badge bg-danger"><?= count($dueStudents) ?> students</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.82rem;">
          <thead>
            <tr>
              <th>Roll</th>
              <th>Student</th>
              <th>Class</th>
              <th>Phone</th>
              <th>Due Items</th>
              <th class="text-end">Outstanding</th>
              <?php if (has_permission('fees.collect')): ?>
              <th class="text-center">Action</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dueStudents as $d): ?>
            <tr>
              <td class="text-muted"><?= $d['roll_number'] ?></td>
              <td>
                <div class="fw-600"><?= e($d['full_name']) ?></div>
                <small class="text-muted"><?= e($d['student_id_no'] ?? '—') ?></small>
              </td>
              <td><?= e($d['class_name']) ?> <?= e($d['section_name']) ?></td>
              <td>
                <?php if ($d['guardian_phone']): ?>
                  <a href="tel:<?= e($d['guardian_phone']) ?>" class="text-decoration-none">
                    <i class="bi bi-telephone me-1 text-primary"></i><?= e($d['guardian_phone']) ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td style="max-width:200px;white-space:normal;font-size:.72rem;color:#64748b;"><?= e($d['due_items']) ?></td>
              <td class="text-end text-danger fw-700"><?= money($d['outstanding']) ?></td>
              <?php if (has_permission('fees.collect')): ?>
              <td class="text-center">
                <a href="collect.php?student_id=<?= $d['student_id'] ?>&session_id=<?= $session_id ?>"
                   class="btn btn-xs btn-success" title="Collect Now">
                  <i class="bi bi-cash-coin me-1"></i>Collect
                </a>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <tr class="table-dark fw-700">
              <td colspan="5">TOTAL (<?= count($dueStudents) ?> students)</td>
              <td class="text-end text-warning"><?= money($totalOutstanding) ?></td>
              <?php if (has_permission('fees.collect')): ?><td></td><?php endif; ?>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
