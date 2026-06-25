<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Staff';
$breadcrumbs = ['HR & Payroll' => null, 'Staff List' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['hr.view']);

$pdo    = db();
$search = trim($_GET['q'] ?? '');
$dept   = trim($_GET['dept'] ?? '');
$type   = trim($_GET['type'] ?? '');
$page   = max(1, int_param('page', 1, $_GET));

$where  = ['sp.status = "active"'];
$params = [];
if ($search) { $where[] = '(sp.first_name LIKE :q OR sp.last_name LIKE :q OR sp.employee_id LIKE :q OR sp.designation LIKE :q)'; $params[':q'] = "%$search%"; }
if ($dept)   { $where[] = 'sp.department = :dept'; $params[':dept'] = $dept; }
if ($type)   { $where[] = 'sp.contract_type = :type'; $params[':type'] = $type; }

$whereStr = implode(' AND ', $where);
$cntStmt  = $pdo->prepare("SELECT COUNT(*) FROM staff_profiles sp WHERE $whereStr");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pg    = paginate($total, $page);

$staff = $pdo->prepare(
    "SELECT sp.*, u.username, u.email
     FROM staff_profiles sp
     JOIN users u ON u.id = sp.user_id
     WHERE $whereStr
     ORDER BY sp.department, sp.first_name
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$staff->execute($params);
$staff = $staff->fetchAll();

$departments = $pdo->query('SELECT DISTINCT department FROM staff_profiles WHERE department IS NOT NULL AND department != "" ORDER BY department')->fetchAll(PDO::FETCH_COLUMN);

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Staff</h1>
  <?php if (has_permission('hr.manage')): ?>
    <a href="create.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Add Staff</a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small">Search</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Name, Employee ID, Designation…" value="<?= e($search) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small">Department</label>
        <select name="dept" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Contract</label>
        <select name="type" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach (['permanent'=>'Permanent','contractual'=>'Contractual','part_time'=>'Part Time','guest_lecturer'=>'Guest Lecturer'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
        <a href="staff.php" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card table-card">
  <div class="card-header d-flex align-items-center py-3 px-4">
    <span class="card-title">Staff Members <span class="badge bg-secondary"><?= $total ?></span></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" id="data-table">
      <thead>
        <tr><th>#</th><th>Name</th><th>Employee ID</th><th>Designation</th><th>Department</th><th>Contract</th><th>Joining</th><th>Salary</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($staff)): ?>
          <tr><td colspan="9"><div class="empty-state"><i class="bi bi-person-badge"></i><p>No staff found</p></div></td></tr>
        <?php else: foreach ($staff as $i => $st): ?>
        <tr>
          <td><?= $pg['offset'] + $i + 1 ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="topbar-avatar" style="width:30px;height:30px;font-size:.75rem;">
                <?= strtoupper(substr($st['first_name'],0,1)) ?>
              </div>
              <div>
                <div class="fw-600"><?= e($st['first_name'] . ' ' . $st['last_name']) ?></div>
                <small class="text-muted"><?= e($st['email'] ?? '') ?></small>
              </div>
            </div>
          </td>
          <td><code><?= e($st['employee_id'] ?? '—') ?></code></td>
          <td><?= e($st['designation'] ?? '—') ?></td>
          <td><?= e($st['department'] ?? '—') ?></td>
          <td><span class="badge bg-light text-dark text-capitalize"><?= e(str_replace('_',' ',$st['contract_type'])) ?></span></td>
          <td><?= fmt_date($st['joining_date']) ?></td>
          <td class="fw-600"><?= money($st['base_salary']) ?></td>
          <td>
            <div class="table-actions">
              <a href="view.php?id=<?= $st['user_id'] ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
              <?php if (has_permission('hr.manage')): ?>
              <a href="edit.php?id=<?= $st['user_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <?php endif; ?>
              <?php if (has_permission('payroll.view')): ?>
              <a href="payroll.php?staff_id=<?= $st['user_id'] ?>" class="btn btn-sm btn-outline-success" title="Payroll"><i class="bi bi-cash-stack"></i></a>
              <?php endif; ?>
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
          <a class="page-link" href="?q=<?= urlencode($search) ?>&dept=<?= urlencode($dept) ?>&type=<?= urlencode($type) ?>&page=<?= $p ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
