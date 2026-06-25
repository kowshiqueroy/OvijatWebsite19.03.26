<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Staff Profile';
$breadcrumbs = ['HR & Payroll' => 'staff.php', 'Profile' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['hr.view']);

$pdo = db();
$id  = int_param('id', 0, $_GET);
if (!$id) { flash('error', 'Invalid ID.'); redirect('staff.php'); }

$stmt = $pdo->prepare(
    'SELECT sp.*, u.username, u.email as u_email, u.status as u_status,
            GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ", ") as roles
     FROM staff_profiles sp
     JOIN users u ON u.id=sp.user_id
     LEFT JOIN user_roles ur ON ur.user_id=u.id
     LEFT JOIN roles r ON r.id=ur.role_id
     WHERE sp.user_id=:id
     GROUP BY sp.id'
);
$stmt->execute([':id' => $id]);
$staff = $stmt->fetch();
if (!$staff) { flash('error', 'Staff not found.'); redirect('staff.php'); }

// Leave summary
$leaves = $pdo->query("SELECT lt.leave_name, COUNT(la.id) as total, SUM(la.total_days) as days FROM leave_applications la JOIN leave_types lt ON lt.id=la.leave_type_id WHERE la.staff_id=$id AND la.status='approved' GROUP BY lt.id ORDER BY lt.leave_name")->fetchAll();

// Attendance last 30 days
$att = $pdo->query("SELECT status, COUNT(*) as cnt FROM staff_attendance WHERE staff_id=$id AND attendance_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY status")->fetchAll();
$attStats = ['present'=>0,'absent'=>0,'late'=>0,'half_day'=>0,'on_leave'=>0];
foreach ($att as $a) $attStats[$a['status']] = (int)$a['cnt'];
$attTotal = array_sum($attStats);
$attPct   = $attTotal > 0 ? round($attStats['present']/$attTotal*100) : 0;

// Recent payroll
$payroll = $pdo->query("SELECT pl.net_salary, pl.base_salary, pr.month, pr.year FROM payroll_lines pl JOIN payroll_runs pr ON pr.id=pl.payroll_run_id WHERE pl.staff_id=$id ORDER BY pr.year DESC, pr.month DESC LIMIT 3")->fetchAll();

// Performance logs
$perfLogs = $pdo->query("SELECT pl.*, u.full_name as logged_by_name FROM performance_logs pl JOIN users u ON u.id=pl.logged_by WHERE pl.staff_id=$id ORDER BY pl.log_date DESC LIMIT 5")->fetchAll();

$page_title = e($staff['first_name'] . ' ' . $staff['last_name']);
require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-person-workspace me-2 text-primary"></i>Staff Profile</h1>
  <div class="d-flex gap-2">
    <?php if(has_permission('hr.manage')): ?>
      <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
    <?php endif; ?>
    <a href="staff.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>
<div class="row g-3">
  <!-- Left column -->
  <div class="col-md-3">
    <div class="card text-center mb-3">
      <div class="card-body py-4">
        <div class="mx-auto mb-3 rounded-circle overflow-hidden border" style="width:90px;height:90px;background:#e2e8f0;">
          <?php if($staff['photo'] && file_exists(UPLOAD_AVATARS.$staff['photo'])): ?>
            <img src="../../uploads/avatars/<?= e($staff['photo']) ?>" style="width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#94a3b8;"><?= strtoupper(substr($staff['first_name'],0,1)) ?></div>
          <?php endif; ?>
        </div>
        <h5 class="fw-700 mb-0"><?= e($staff['first_name'].' '.$staff['last_name']) ?></h5>
        <p class="text-muted small mb-1"><?= e($staff['designation']??'—') ?></p>
        <p class="text-muted small mb-2"><?= e($staff['department']??'—') ?></p>
        <span class="badge bg-<?= $staff['u_status']==='active'?'success':'danger' ?>"><?= ucfirst(e($staff['u_status'])) ?></span>
        <br><code class="small mt-1 d-block"><?= e($staff['employee_id']??'—') ?></code>
      </div>
    </div>
    <div class="card mb-3">
      <div class="card-header py-3 px-4"><span class="card-title small">Attendance (30d)</span></div>
      <div class="card-body py-3">
        <div class="text-center mb-2"><span class="fw-700" style="font-size:1.5rem;"><?= $attPct ?>%</span></div>
        <div class="progress mb-2" style="height:6px;"><div class="progress-bar bg-<?= $attPct>=75?'success':($attPct>=50?'warning':'danger') ?>" style="width:<?= $attPct ?>%"></div></div>
        <div class="row g-1 text-center small">
          <div class="col-6"><span class="text-success fw-600"><?= $attStats['present'] ?></span><br><span class="text-muted">Present</span></div>
          <div class="col-6"><span class="text-danger fw-600"><?= $attStats['absent'] ?></span><br><span class="text-muted">Absent</span></div>
          <div class="col-6"><span class="text-warning fw-600"><?= $attStats['late'] ?></span><br><span class="text-muted">Late</span></div>
          <div class="col-6"><span class="text-info fw-600"><?= $attStats['on_leave'] ?></span><br><span class="text-muted">On Leave</span></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right column -->
  <div class="col-md-9">
    <div class="card mb-3">
      <div class="card-header py-3 px-4"><span class="card-title">Personal & Employment Information</span></div>
      <div class="card-body">
        <div class="row g-2 small">
          <?php $fields = [
            'NID No'        => $staff['nid_no'],
            'Date of Birth' => fmt_date($staff['dob']),
            'Gender'        => ucfirst($staff['gender']??'—'),
            'Religion'      => $staff['religion'],
            'Blood Group'   => $staff['blood_group'],
            'Phone'         => $staff['phone'],
            'Email'         => $staff['email'],
            'Address'       => $staff['address'],
            'Joining Date'  => fmt_date($staff['joining_date']),
            'Contract'      => ucwords(str_replace('_',' ',$staff['contract_type']??'')),
            'Salary Type'   => ucwords(str_replace('_',' ',$staff['salary_type']??'')),
            'Base Salary'   => money($staff['base_salary']),
            'Bank'          => ($staff['bank_name']??'').' '.(($staff['bank_account']??'')?'('.$staff['bank_account'].')':''),
            'Username'      => $staff['username'],
            'Roles'         => $staff['roles'],
          ];
          foreach($fields as $lbl => $val): if(!$val) continue; ?>
          <div class="col-md-6"><div class="d-flex gap-2"><span class="text-muted" style="min-width:110px;"><?= e($lbl) ?>:</span><span class="fw-600"><?= e($val) ?></span></div></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php if(!empty($leaves)): ?>
    <div class="card mb-3">
      <div class="card-header py-3 px-4"><span class="card-title">Leave Summary</span></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Leave Type</th><th>Applications</th><th>Total Days</th></tr></thead>
          <tbody><?php foreach($leaves as $l): ?><tr><td><?= e($l['leave_name']) ?></td><td><?= $l['total'] ?></td><td class="fw-600"><?= $l['days'] ?></td></tr><?php endforeach; ?></tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if(!empty($payroll)): ?>
    <div class="card mb-3">
      <div class="card-header py-3 px-4"><span class="card-title">Recent Payroll</span></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Period</th><th>Base</th><th>Net</th></tr></thead>
          <tbody><?php foreach($payroll as $p): ?><tr><td><?= date('F Y',mktime(0,0,0,$p['month'],1,$p['year'])) ?></td><td><?= money($p['base_salary']) ?></td><td class="fw-700"><?= money($p['net_salary']) ?></td></tr><?php endforeach; ?></tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if(!empty($perfLogs)): ?>
    <div class="card">
      <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="card-title">Performance Log</span>
        <?php if(has_permission('hr.manage')): ?><a href="performance.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">View All</a><?php endif; ?>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach($perfLogs as $pl): $typeColor=['evaluation'=>'primary','disciplinary'=>'danger','commendation'=>'success','warning'=>'warning'][$pl['log_type']]??'secondary'; ?>
        <div class="list-group-item py-2">
          <div class="d-flex align-items-start justify-content-between">
            <div>
              <span class="badge bg-<?= $typeColor ?> me-2"><?= ucfirst(e($pl['log_type'])) ?></span>
              <span class="small fw-600"><?= fmt_date($pl['log_date']) ?> — by <?= e($pl['logged_by_name']) ?></span>
              <p class="mb-0 small text-muted mt-1"><?= e(substr($pl['description'],0,100)) ?><?= strlen($pl['description'])>100?'…':'' ?></p>
            </div>
            <?php if($pl['is_confidential']): ?><span class="badge bg-dark ms-2">Conf.</span><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
