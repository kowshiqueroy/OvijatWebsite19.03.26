<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Incidents & Disciplinary';
$breadcrumbs = ['Communication' => null, 'Incidents' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['incidents.manage']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id       = int_param('id',0,$_POST);
        $type     = $_POST['incident_type']??'other';
        $subject  = int_param('subject_id',0,$_POST);
        $desc     = trim($_POST['description']??'');
        $taken    = trim($_POST['action_taken']??'');
        $date     = $_POST['incident_date']??date('Y-m-d');
        $conf     = isset($_POST['is_confidential'])?1:0;
        $status   = $_POST['status']??'open';

        if ($subject && $desc) {
            if ($id) {
                $pdo->prepare('UPDATE incidents SET incident_type=?,description=?,action_taken=?,incident_date=?,is_confidential=?,status=? WHERE id=?')
                    ->execute([$type,$desc,$taken,$date,$conf,$status,$id]);
                flash('success','Incident updated.');
            } else {
                $pdo->prepare('INSERT INTO incidents (incident_type,subject_id,description,action_taken,incident_date,logged_by,is_confidential,status) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$type,$subject,$desc,$taken,$date,current_user_id(),$conf,$status]);
                flash('success','Incident logged.');
            }
        }
    } elseif ($action === 'resolve') {
        $id = int_param('id',0,$_POST);
        $pdo->prepare("UPDATE incidents SET status='resolved' WHERE id=?")->execute([$id]);
        flash('success','Marked as resolved.');
    }
    header('Location: incidents.php');
    exit;
}

$status_f = $_GET['status'] ?? 'open';
$type_f   = $_GET['type'] ?? '';
$where    = $status_f === 'all' ? '1=1' : "i.status='$status_f'";
if ($type_f) $where .= " AND i.incident_type='".addslashes($type_f)."'";

$incidents = $pdo->query("SELECT i.*, u.full_name as subject_name, lb.full_name as logged_by_name FROM incidents i JOIN users u ON u.id=i.subject_id JOIN users lb ON lb.id=i.logged_by WHERE $where ORDER BY i.created_at DESC LIMIT 50")->fetchAll();

$counts = [];
foreach(['open','resolved','dismissed'] as $st) $counts[$st]=(int)$pdo->query("SELECT COUNT(*) FROM incidents WHERE status='$st'")->fetchColumn();

$allUsers = $pdo->query("SELECT u.id, u.full_name FROM users u WHERE u.status='active' ORDER BY u.full_name LIMIT 200")->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-shield-exclamation me-2 text-primary"></i>Incidents & Disciplinary</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#incidentModal" onclick="setIncForm(null)"><i class="bi bi-plus-lg me-1"></i>Log Incident</button>
</div>
<ul class="nav nav-tabs mb-3">
  <?php foreach(['open'=>'Open','resolved'=>'Resolved','dismissed'=>'Dismissed','all'=>'All'] as $k=>$v): ?>
  <li class="nav-item"><a class="nav-link <?= $status_f===$k?'active':'' ?>" href="?status=<?= $k ?>"><?= $v ?><?php if(isset($counts[$k])): ?><span class="badge bg-<?= $k==='open'?'danger':($k==='resolved'?'success':'secondary') ?> ms-1"><?= $counts[$k] ?></span><?php endif; ?></a></li>
  <?php endforeach; ?>
</ul>
<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Date</th><th>Type</th><th>Subject</th><th>Description</th><th>Action Taken</th><th>Logged By</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if(empty($incidents)): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-shield-check"></i><p>No <?= $status_f !=='all'?$status_f:'' ?> incidents</p></div></td></tr><?php endif; ?>
        <?php foreach($incidents as $inc): ?>
        <tr>
          <td><?= fmt_date($inc['incident_date']) ?></td>
          <td><span class="badge bg-<?= $inc['incident_type']==='student_discipline'?'warning text-dark':($inc['incident_type']==='staff_discipline'?'danger':'secondary') ?>"><?= ucfirst(str_replace('_',' ',e($inc['incident_type']))) ?></span><?php if($inc['is_confidential']): ?><span class="badge bg-dark ms-1">Conf.</span><?php endif; ?></td>
          <td class="fw-600"><?= e($inc['subject_name']) ?></td>
          <td style="max-width:200px;"><?= e(substr($inc['description'],0,80)) ?><?= strlen($inc['description'])>80?'…':'' ?></td>
          <td><?= e($inc['action_taken']?substr($inc['action_taken'],0,60).'…':'—') ?></td>
          <td><?= e($inc['logged_by_name']) ?></td>
          <td><span class="badge-status badge-<?= $inc['status']==='open'?'rejected':($inc['status']==='resolved'?'active':'draft') ?>"><?= ucfirst(e($inc['status'])) ?></span></td>
          <td>
            <div class="d-flex gap-1">
              <button class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:.15rem .4rem;" data-bs-toggle="modal" data-bs-target="#incidentModal" onclick="setIncForm(<?= htmlspecialchars(json_encode($inc),ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
              <?php if($inc['status']==='open'): ?>
              <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="resolve"><input type="hidden" name="id" value="<?= $inc['id'] ?>"><button type="submit" class="btn btn-xs btn-success" style="font-size:.7rem;padding:.15rem .4rem;"><i class="bi bi-check-lg"></i></button></form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="incidentModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="inc_id" value="0">
        <div class="modal-header"><h5 class="modal-title" id="incModalTitle">Log Incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Type</label><select name="incident_type" id="inc_type" class="form-select"><?php foreach(['student_discipline'=>'Student Discipline','staff_discipline'=>'Staff Discipline','complaint'=>'Complaint','other'=>'Other'] as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Subject (Person) *</label><select name="subject_id" id="inc_subj" class="form-select" required><option value="">— Select —</option><?php foreach($allUsers as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Incident Date</label><input type="date" name="incident_date" id="inc_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="inc_status" class="form-select"><option value="open">Open</option><option value="resolved">Resolved</option><option value="dismissed">Dismissed</option></select></div>
            <div class="col-12"><label class="form-label">Description *</label><textarea name="description" id="inc_desc" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label">Action Taken</label><textarea name="action_taken" id="inc_action" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" name="is_confidential" id="inc_conf" value="1"><label class="form-check-label" for="inc_conf"><strong>Confidential</strong> — visible only to admin</label></div></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Incident</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function setIncForm(i){
  document.getElementById('incModalTitle').textContent=i?'Edit Incident':'Log Incident';
  document.getElementById('inc_id').value=i?i.id:0;
  document.getElementById('inc_type').value=i?i.incident_type:'student_discipline';
  document.getElementById('inc_subj').value=i?i.subject_id:'';
  document.getElementById('inc_date').value=i?i.incident_date:'<?= date('Y-m-d') ?>';
  document.getElementById('inc_status').value=i?i.status:'open';
  document.getElementById('inc_desc').value=i?i.description:'';
  document.getElementById('inc_action').value=i?(i.action_taken||''):'';
  document.getElementById('inc_conf').checked=i&&i.is_confidential==1;
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
