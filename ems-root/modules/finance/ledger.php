<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Fee Ledger';
$breadcrumbs = ['Finance' => null, 'Fee Ledger' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['finance.view']);

$pdo        = db();
$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);
$class_id   = int_param('class_id', 0, $_GET);
$section_id = int_param('section_id', 0, $_GET);
$status_f   = $_GET['status'] ?? '';
$search     = trim($_GET['q'] ?? '');
$page       = max(1, int_param('page', 1, $_GET));

// Build query
$where  = ['fl.session_id = :sess'];
$params = [':sess' => $session_id];
if ($class_id)   { $where[] = 'se.class_id = :cls';    $params[':cls']  = $class_id; }
if ($section_id) { $where[] = 'se.section_id = :sec';  $params[':sec']  = $section_id; }
if ($status_f)   { $where[] = 'fl.status = :st';       $params[':st']   = $status_f; }
if ($search)     { $where[] = '(sp.first_name LIKE :q OR sp.last_name LIKE :q OR sp.student_id_no LIKE :q)'; $params[':q'] = "%$search%"; }

$whereStr = implode(' AND ', $where);

$cntStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT fl.student_id)
     FROM fee_ledgers fl
     LEFT JOIN student_enrollments se ON se.student_id=fl.student_id AND se.session_id=fl.session_id AND se.status='active'
     LEFT JOIN student_profiles sp ON sp.user_id=fl.student_id
     WHERE $whereStr"
);
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pg    = paginate($total, $page);

$ledger = $pdo->prepare(
    "SELECT fl.student_id, sp.first_name, sp.last_name, sp.student_id_no, sp.guardian_phone,
            c.class_name, sec.section_name, se.roll_number,
            SUM(fl.amount_due) as total_due,
            SUM(fl.amount_paid) as total_paid,
            SUM(fl.waiver_amount) as total_waiver,
            SUM(fl.amount_due - fl.amount_paid - fl.waiver_amount) as balance,
            SUM(CASE WHEN fl.status='unpaid' THEN 1 ELSE 0 END) as unpaid_count
     FROM fee_ledgers fl
     LEFT JOIN student_enrollments se ON se.student_id=fl.student_id AND se.session_id=fl.session_id AND se.status='active'
     LEFT JOIN student_profiles sp ON sp.user_id=fl.student_id
     LEFT JOIN classes c ON c.id=se.class_id
     LEFT JOIN sections sec ON sec.id=se.section_id
     WHERE $whereStr
     GROUP BY fl.student_id
     ORDER BY balance DESC, sp.last_name
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$ledger->execute($params);
$rows = $ledger->fetchAll();

// Totals
$totStmt = $pdo->prepare(
    "SELECT SUM(fl.amount_due) as total_due, SUM(fl.amount_paid) as total_paid, SUM(fl.waiver_amount) as total_waiver
     FROM fee_ledgers fl
     LEFT JOIN student_enrollments se ON se.student_id=fl.student_id AND se.session_id=fl.session_id AND se.status='active'
     LEFT JOIN student_profiles sp ON sp.user_id=fl.student_id
     WHERE $whereStr"
);
$totStmt->execute($params);
$totals = $totStmt->fetch();

$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id, class_name FROM classes WHERE status=1 ORDER BY display_order')->fetchAll();
$sections = $class_id
    ? $pdo->prepare('SELECT id, section_name FROM sections WHERE class_id=:c ORDER BY section_name')
    : null;
if ($sections) { $sections->execute([':c'=>$class_id]); $sections = $sections->fetchAll(); }
else $sections = [];

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-journal-bookmark me-2 text-primary"></i>Fee Ledger</h1>
  <div class="d-flex gap-2">
    <a href="structures.php?session_id=<?= $session_id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-table me-1"></i>Fee Structures</a>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Print</button>
  </div>
</div>

<!-- Summary cards -->
<?php if ($totals): $bal = max(0, ($totals['total_due'] ?? 0) - ($totals['total_paid'] ?? 0) - ($totals['total_waiver'] ?? 0)); ?>
<div class="row g-3 mb-3">
  <div class="col-sm-3"><div class="stat-card primary"><div class="stat-value"><?= money($totals['total_due'] ?? 0) ?></div><div class="stat-label">Total Due</div><i class="bi bi-cash-coin stat-icon"></i></div></div>
  <div class="col-sm-3"><div class="stat-card success"><div class="stat-value"><?= money($totals['total_paid'] ?? 0) ?></div><div class="stat-label">Collected</div><i class="bi bi-check-circle stat-icon"></i></div></div>
  <div class="col-sm-3"><div class="stat-card warning"><div class="stat-value"><?= money($totals['total_waiver'] ?? 0) ?></div><div class="stat-label">Waiver</div><i class="bi bi-tag stat-icon"></i></div></div>
  <div class="col-sm-3"><div class="stat-card danger"><div class="stat-value"><?= money($bal) ?></div><div class="stat-label">Outstanding</div><i class="bi bi-exclamation-triangle stat-icon"></i></div></div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label small">Session</label>
        <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sessions as $sess): ?>
            <option value="<?= $sess['id'] ?>" <?= $session_id == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">All Classes</option>
          <?php foreach ($classes as $cls): ?>
            <option value="<?= $cls['id'] ?>" <?= $class_id == $cls['id'] ? 'selected' : '' ?>><?= e($cls['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!empty($sections)): ?>
      <div class="col-md-2">
        <label class="form-label small">Section</label>
        <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">All</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>" <?= $section_id == $sec['id'] ? 'selected' : '' ?>><?= e($sec['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-2">
        <label class="form-label small">Status</label>
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All</option>
          <option value="unpaid" <?= $status_f === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
          <option value="partial" <?= $status_f === 'partial' ? 'selected' : '' ?>>Partial</option>
          <option value="paid" <?= $status_f === 'paid' ? 'selected' : '' ?>>Paid</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Search</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Name, ID…" value="<?= e($search) ?>">
      </div>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
        <a href="ledger.php" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card table-card">
  <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
    <span class="card-title">Students <span class="badge bg-secondary"><?= $total ?></span></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Roll</th><th>Student</th><th>Class</th><th>Due</th><th>Paid</th><th>Waiver</th><th>Balance</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8"><div class="empty-state"><i class="bi bi-journal"></i><p>No fee records found. Generate ledgers from Fee Structures page.</p></div></td></tr>
        <?php else: foreach ($rows as $row):
          $balance = max(0, $row['total_due'] - $row['total_paid'] - $row['total_waiver']);
        ?>
        <tr>
          <td class="fw-700"><?= e($row['roll_number'] ?? '—') ?></td>
          <td>
            <div class="fw-600"><?= e(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?></div>
            <small class="text-muted"><?= e($row['student_id_no'] ?? '') ?></small>
          </td>
          <td><?= e($row['class_name'] ?? '?') ?> – <?= e($row['section_name'] ?? '?') ?></td>
          <td><?= money($row['total_due']) ?></td>
          <td class="text-success fw-600"><?= money($row['total_paid']) ?></td>
          <td class="text-warning"><?= money($row['total_waiver']) ?></td>
          <td class="<?= $balance > 0 ? 'text-danger fw-700' : 'text-success fw-600' ?>"><?= money($balance) ?></td>
          <td>
            <div class="table-actions">
              <a href="collect.php?student_id=<?= $row['student_id'] ?>&session_id=<?= $session_id ?>"
                 class="btn btn-sm btn-outline-success" title="Collect">
                <i class="bi bi-cash"></i>
              </a>
              <a href="../students/view.php?id=<?= $row['student_id'] ?>"
                 class="btn btn-sm btn-outline-info" title="Profile">
                <i class="bi bi-person"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pg['total_pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center justify-content-between py-2 px-4">
    <small class="text-muted">Showing <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'],$total) ?> of <?= $total ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($p = 1; $p <= $pg['total_pages']; $p++): ?>
        <li class="page-item <?= $p === $pg['page'] ? 'active' : '' ?>">
          <a class="page-link" href="?session_id=<?= $session_id ?>&class_id=<?= $class_id ?>&status=<?= urlencode($status_f) ?>&q=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
