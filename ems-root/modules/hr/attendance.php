<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Staff Attendance';
$breadcrumbs = ['HR & Payroll' => 'staff.php', 'Attendance' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['attendance.mark']);

$pdo  = db();
$date = $_GET['date'] ?? date('Y-m-d');
$dept = trim($_GET['dept'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'save') {
    csrf_check();
    $att_date = $_POST['att_date'] ?? date('Y-m-d');
    $statuses = $_POST['status'] ?? [];
    $stmt = $pdo->prepare('INSERT INTO staff_attendance (staff_id,attendance_date,status,marked_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),marked_by=VALUES(marked_by)');
    foreach ($statuses as $uid => $st) $stmt->execute([$uid,$att_date,$st,current_user_id()]);
    flash('success', count($statuses).' records saved for '.fmt_date($att_date).'.');
    header("Location: attendance.php?date=$att_date&dept=".urlencode($dept));
    exit;
}

$where  = ['sp.status="active"'];
$params = [];
if ($dept) { $where[] = 'sp.department=:dept'; $params[':dept']=$dept; }
$whereStr = implode(' AND ',$where);

$staff = $pdo->prepare("SELECT sp.user_id, CONCAT(sp.first_name,' ',sp.last_name) as name, sp.designation, sp.department, sa.status as att_status FROM staff_profiles sp LEFT JOIN staff_attendance sa ON sa.staff_id=sp.user_id AND sa.attendance_date=:d WHERE $whereStr ORDER BY sp.department, sp.first_name");
$params[':d'] = $date;
$staff->execute($params);
$staff = $staff->fetchAll();

$depts = $pdo->query('SELECT DISTINCT department FROM staff_profiles WHERE department IS NOT NULL AND department!="" AND status="active" ORDER BY department')->fetchAll(PDO::FETCH_COLUMN);

// Last 7-day summary
$summary = $pdo->query("SELECT attendance_date, status, COUNT(*) as cnt FROM staff_attendance WHERE attendance_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY) GROUP BY attendance_date,status ORDER BY attendance_date DESC")->fetchAll();
$daySum = [];
foreach ($summary as $r) $daySum[$r['attendance_date']][$r['status']] = $r['cnt'];

require_once EMS_ROOT . '/includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-calendar-check-fill me-2 text-primary"></i>Staff Attendance</h1>
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3"><label class="form-label small">Date</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>" onchange="this.form.submit()"></div>
      <div class="col-md-3"><label class="form-label small">Department</label>
        <select name="dept" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Departments</option>
          <?php foreach($depts as $d): ?><option value="<?= e($d) ?>" <?= $dept===$d?'selected':'' ?>><?= e($d) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-auto d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-success" onclick="setAll('present')">All Present</button>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="setAll('absent')">All Absent</button>
      </div>
    </form>
  </div>
</div>
<div class="row g-3">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Staff — <?= fmt_date($date) ?> <span class="badge bg-secondary"><?= count($staff) ?></span></span></div>
      <form method="POST">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="att_date" value="<?= e($date) ?>">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Dept</th><th>P</th><th>A</th><th>L</th><th>HD</th><th>OL</th></tr></thead>
            <tbody>
              <?php foreach($staff as $i=>$st):
                $cur = $st['att_status'] ?? 'present'; ?>
              <tr>
                <td><?= $i+1 ?></td>
                <td><div class="fw-600"><?= e($st['name']) ?></div><small class="text-muted"><?= e($st['designation']??'') ?></small></td>
                <td><?= e($st['department']??'—') ?></td>
                <?php foreach(['present'=>'success','absent'=>'danger','late'=>'warning','half_day'=>'info','on_leave'=>'secondary'] as $s=>$c): ?>
                <td class="text-center"><input type="radio" class="form-check-input att-radio" name="status[<?= $st['user_id'] ?>]" value="<?= $s ?>" <?= $cur===$s?'checked':'' ?>></td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer py-3 px-4">
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Attendance</button>
        </div>
      </form>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">7-Day Summary</span></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Date</th><th class="text-success">P</th><th class="text-danger">A</th><th class="text-warning">L</th></tr></thead>
          <tbody>
            <?php foreach($daySum as $d=>$row): ?>
            <tr class="<?= $d===$date?'table-primary':'' ?>">
              <td><?= fmt_date($d,'d M') ?></td>
              <td class="text-success fw-600"><?= $row['present']??0 ?></td>
              <td class="text-danger"><?= $row['absent']??0 ?></td>
              <td class="text-warning"><?= ($row['late']??0)+($row['half_day']??0) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($daySum)): ?><tr><td colspan="4" class="text-muted text-center small">No data</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>
function setAll(s){document.querySelectorAll('.att-radio[value="'+s+'"]').forEach(r=>r.checked=true);}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
