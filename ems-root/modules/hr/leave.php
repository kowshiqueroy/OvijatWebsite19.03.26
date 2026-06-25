<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Leave Management';
$breadcrumbs = ['HR & Payroll' => 'staff.php', 'Leave' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['hr.view']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'apply') {
        $leave_type = int_param('leave_type_id',0,$_POST);
        $from       = $_POST['from_date'] ?? '';
        $to         = $_POST['to_date'] ?? '';
        $reason     = trim($_POST['reason'] ?? '');
        $staff_id   = int_param('staff_id',0,$_POST) ?: current_user_id();
        if ($leave_type && $from && $to) {
            $days = max(1,(int)((strtotime($to)-strtotime($from))/86400)+1);
            $pdo->prepare('INSERT INTO leave_applications (staff_id,leave_type_id,from_date,to_date,total_days,reason,status) VALUES (?,?,?,?,?,?,"pending")')
                ->execute([$staff_id,$leave_type,$from,$to,$days,$reason]);
            flash('success',"Leave application submitted ($days days).");
        }
    } elseif (in_array($action,['approve','reject']) && has_permission('leave.approve')) {
        $id = int_param('id',0,$_POST);
        $status = $action==='approve'?'approved':'rejected';
        $pdo->prepare('UPDATE leave_applications SET status=?,approved_by=? WHERE id=?')->execute([$status,current_user_id(),$id]);
        flash('success',"Application $status.");
    } elseif ($action === 'cancel') {
        $id = int_param('id',0,$_POST);
        $pdo->prepare("UPDATE leave_applications SET status='cancelled' WHERE id=? AND staff_id=?")->execute([$id,current_user_id()]);
        flash('success','Application cancelled.');
    }
    header('Location: leave.php?tab='.($_POST['tab']??'pending'));
    exit;
}

$tab    = $_GET['tab'] ?? 'pending';
$validTabs = ['pending','approved','rejected','all'];
if (!in_array($tab,$validTabs)) $tab='pending';

$where  = $tab==='all' ? '1=1' : "la.status='$tab'";
$leaves = $pdo->query("SELECT la.*, lt.leave_name, CONCAT(sp.first_name,' ',sp.last_name) as staff_name, sp.department, ap.full_name as approved_by_name FROM leave_applications la JOIN leave_types lt ON lt.id=la.leave_type_id JOIN staff_profiles sp ON sp.user_id=la.staff_id LEFT JOIN users ap ON ap.id=la.approved_by WHERE $where ORDER BY la.applied_at DESC LIMIT 50")->fetchAll();

$counts = [];
foreach(['pending','approved','rejected'] as $st) {
    $counts[$st]=(int)$pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status='$st'")->fetchColumn();
}

$leaveTypes = $pdo->query('SELECT id,leave_name,annual_quota FROM leave_types ORDER BY leave_name')->fetchAll();
$staffList  = $pdo->query("SELECT sp.user_id as id, CONCAT(sp.first_name,' ',sp.last_name) as name FROM staff_profiles sp WHERE sp.status='active' ORDER BY name")->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-calendar-x-fill me-2 text-primary"></i>Leave Management</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaveModal"><i class="bi bi-plus-lg me-1"></i>Apply Leave</button>
</div>

<ul class="nav nav-tabs mb-3">
  <?php foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $k=>$v): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab===$k?'active':'' ?>" href="?tab=<?= $k ?>">
      <?= $v ?><?php if(isset($counts[$k])): ?><span class="badge bg-<?= $k==='pending'?'warning text-dark':($k==='approved'?'success':'danger') ?> ms-1"><?= $counts[$k] ?></span><?php endif; ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Staff</th><th>Dept</th><th>Leave Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Applied</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(empty($leaves)): ?>
          <tr><td colspan="9"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>No <?= $tab !== 'all' ? $tab : '' ?> leave applications</p></div></td></tr>
        <?php else: foreach($leaves as $la): ?>
        <tr>
          <td class="fw-600"><?= e($la['staff_name']) ?></td>
          <td><?= e($la['department']??'—') ?></td>
          <td><?= e($la['leave_name']) ?></td>
          <td><?= fmt_date($la['from_date']) ?></td>
          <td><?= fmt_date($la['to_date']) ?></td>
          <td class="fw-700"><?= $la['total_days'] ?></td>
          <td><span class="badge-status badge-<?= $la['status']==='approved'?'active':($la['status']==='pending'?'pending':'rejected') ?>"><?= ucfirst(e($la['status'])) ?></span></td>
          <td><?= fmt_date($la['applied_at'],'d M Y') ?></td>
          <td>
            <div class="d-flex gap-1">
              <?php if($la['status']==='pending' && has_permission('leave.approve')): ?>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $la['id'] ?>"><input type="hidden" name="tab" value="<?= $tab ?>">
                <button type="submit" class="btn btn-xs btn-success" style="font-size:.72rem;padding:.2rem .5rem;"><i class="bi bi-check-lg"></i></button>
              </form>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= $la['id'] ?>"><input type="hidden" name="tab" value="<?= $tab ?>">
                <button type="submit" class="btn btn-xs btn-danger" style="font-size:.72rem;padding:.2rem .5rem;"><i class="bi bi-x-lg"></i></button>
              </form>
              <?php endif; ?>
              <?php if($la['status']==='pending' && $la['staff_id']==current_user_id()): ?>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $la['id'] ?>">
                <button type="submit" class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.2rem .5rem;" data-confirm="Cancel this application?">Cancel</button>
              </form>
              <?php endif; ?>
              <?php if($la['approved_by_name']): ?>
              <span class="small text-muted">by <?= e($la['approved_by_name']) ?></span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Apply modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?><input type="hidden" name="action" value="apply">
        <div class="modal-header"><h5 class="modal-title">Apply for Leave</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <?php if(has_permission('hr.manage')): ?>
          <div class="mb-3"><label class="form-label">Staff Member</label>
            <select name="staff_id" class="form-select">
              <option value="<?= current_user_id() ?>">— Self —</option>
              <?php foreach($staffList as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
            </select></div>
          <?php endif; ?>
          <div class="mb-3"><label class="form-label">Leave Type *</label>
            <select name="leave_type_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach($leaveTypes as $lt): ?><option value="<?= $lt['id'] ?>"><?= e($lt['leave_name']) ?> (Quota: <?= $lt['annual_quota'] ?> days)</option><?php endforeach; ?>
            </select></div>
          <div class="row g-3">
            <div class="col-6"><label class="form-label">From *</label><input type="date" name="from_date" class="form-control" required></div>
            <div class="col-6"><label class="form-label">To *</label><input type="date" name="to_date" class="form-control" required></div>
          </div>
          <div class="mt-3"><label class="form-label">Reason</label><textarea name="reason" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Submit Application</button></div>
      </form>
    </div>
  </div>
</div>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
