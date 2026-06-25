<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Performance Log';
$breadcrumbs = ['HR & Payroll' => 'staff.php', 'Performance' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['hr.manage']);

$pdo = db();
$id  = int_param('id', 0, $_GET);
if (!$id) { flash('error','Invalid ID.'); redirect('staff.php'); }

$staffStmt = $pdo->prepare('SELECT CONCAT(sp.first_name," ",sp.last_name) as name FROM staff_profiles sp WHERE sp.user_id=:id');
$staffStmt->execute([':id'=>$id]);
$staffName = $staffStmt->fetchColumn();
if (!$staffName) { flash('error','Staff not found.'); redirect('staff.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action']??'';
    if ($action === 'save') {
        $eid      = int_param('id',0,$_POST);
        $type     = $_POST['log_type']??'evaluation';
        $desc     = trim($_POST['description']??'');
        $taken    = trim($_POST['action_taken']??'');
        $date     = $_POST['log_date']??date('Y-m-d');
        $conf     = isset($_POST['is_confidential'])?1:0;
        if ($desc) {
            if ($eid) {
                $pdo->prepare('UPDATE performance_logs SET log_type=?,description=?,action_taken=?,log_date=?,is_confidential=? WHERE id=?')->execute([$type,$desc,$taken,$date,$conf,$eid]);
                flash('success','Log updated.');
            } else {
                $pdo->prepare('INSERT INTO performance_logs (staff_id,log_type,description,action_taken,logged_by,log_date,is_confidential) VALUES (?,?,?,?,?,?,?)')->execute([$id,$type,$desc,$taken,current_user_id(),$date,$conf]);
                flash('success','Log entry added.');
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM performance_logs WHERE id=?')->execute([int_param('id',0,$_POST)]);
        flash('success','Entry deleted.');
    }
    header("Location: performance.php?id=$id");
    exit;
}

$logs = $pdo->query("SELECT pl.*, u.full_name as logged_by_name FROM performance_logs pl JOIN users u ON u.id=pl.logged_by WHERE pl.staff_id=$id ORDER BY pl.log_date DESC")->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Performance Log — <?= e($staffName) ?></h1>
  <div class="d-flex gap-2">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#perfModal" onclick="setPerfForm(null)"><i class="bi bi-plus-lg me-1"></i>Add Entry</button>
    <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Profile</a>
  </div>
</div>
<?php if(empty($logs)): ?>
<div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-journal"></i><p>No log entries yet.</p></div></div></div>
<?php else: ?>
<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Action Taken</th><th>By</th><th></th></tr></thead>
      <tbody>
        <?php $typeColor=['evaluation'=>'primary','disciplinary'=>'danger','commendation'=>'success','warning'=>'warning'];
        foreach($logs as $log): ?>
        <tr>
          <td><?= fmt_date($log['log_date']) ?></td>
          <td><span class="badge bg-<?= $typeColor[$log['log_type']]??'secondary' ?>"><?= ucfirst(e($log['log_type'])) ?></span><?php if($log['is_confidential']): ?><span class="badge bg-dark ms-1 small">Conf.</span><?php endif; ?></td>
          <td class="fw-600" style="max-width:250px;"><?= e(substr($log['description'],0,100)) ?><?= strlen($log['description'])>100?'…':'' ?></td>
          <td class="text-muted small"><?= e($log['action_taken']??'—') ?></td>
          <td><?= e($log['logged_by_name']) ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#perfModal" onclick="setPerfForm(<?= htmlspecialchars(json_encode($log),ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
              <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $log['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="bi bi-trash"></i></button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="perfModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="pf_id" value="0">
        <div class="modal-header"><h5 class="modal-title" id="perfModalTitle">Add Log Entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Type</label><select name="log_type" id="pf_type" class="form-select"><?php foreach(['evaluation'=>'Evaluation','disciplinary'=>'Disciplinary','commendation'=>'Commendation','warning'=>'Warning'] as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="log_date" id="pf_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-12"><label class="form-label">Description *</label><textarea name="description" id="pf_desc" class="form-control" rows="3" required></textarea></div>
            <div class="col-12"><label class="form-label">Action Taken</label><textarea name="action_taken" id="pf_action" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" name="is_confidential" id="pf_conf" value="1"><label class="form-check-label" for="pf_conf"><strong>Confidential</strong> — hidden from non-admin roles</label></div></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Entry</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function setPerfForm(p){
  document.getElementById('perfModalTitle').textContent=p?'Edit Entry':'Add Log Entry';
  document.getElementById('pf_id').value=p?p.id:0;
  document.getElementById('pf_type').value=p?p.log_type:'evaluation';
  document.getElementById('pf_date').value=p?p.log_date:'<?= date('Y-m-d') ?>';
  document.getElementById('pf_desc').value=p?p.description:'';
  document.getElementById('pf_action').value=p?(p.action_taken||''):'';
  document.getElementById('pf_conf').checked=p&&p.is_confidential==1;
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
