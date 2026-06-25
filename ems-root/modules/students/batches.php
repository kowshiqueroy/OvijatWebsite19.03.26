<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Special Batches';
$breadcrumbs = ['Students' => 'index.php', 'Special Batches' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.create']);

$pdo = db();
$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_batch') {
        $id   = int_param('id', 0, $_POST);
        $name = trim($_POST['batch_name'] ?? '');
        $type = $_POST['batch_type'] ?? 'other';
        $sess = int_param('session_id', $session_id, $_POST);
        $teacher = int_param('teacher_id', 0, $_POST) ?: null;
        $start = $_POST['start_date'] ?? null;
        $end   = $_POST['end_date'] ?? null;
        $desc  = trim($_POST['description'] ?? '');
        if ($name) {
            if ($id) {
                $pdo->prepare('UPDATE special_batches SET batch_name=?,batch_type=?,teacher_id=?,start_date=?,end_date=?,description=? WHERE id=?')
                    ->execute([$name,$type,$teacher,$start,$end,$desc,$id]);
                flash('success','Batch updated.');
            } else {
                $pdo->prepare('INSERT INTO special_batches (batch_name,batch_type,session_id,teacher_id,start_date,end_date,description) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$name,$type,$sess,$teacher,$start,$end,$desc]);
                flash('success',"Batch '$name' created.");
            }
        }
    } elseif ($action === 'enroll') {
        $batch_id   = int_param('batch_id', 0, $_POST);
        $student_id = int_param('student_id', 0, $_POST);
        if ($batch_id && $student_id) {
            try {
                $pdo->prepare('INSERT IGNORE INTO batch_enrollments (batch_id,student_id,joined_at,status) VALUES (?,?,CURDATE(),"active")')
                    ->execute([$batch_id,$student_id]);
                flash('success','Student enrolled in batch.');
            } catch (Exception $e) { flash('error','Already enrolled.'); }
        }
    } elseif ($action === 'remove_member') {
        $bid = int_param('batch_id',0,$_POST);
        $sid = int_param('student_id',0,$_POST);
        $pdo->prepare("UPDATE batch_enrollments SET status='left' WHERE batch_id=? AND student_id=?")->execute([$bid,$sid]);
        flash('success','Student removed from batch.');
    } elseif ($action === 'delete_batch') {
        $pdo->prepare('DELETE FROM special_batches WHERE id=?')->execute([int_param('id',0,$_POST)]);
        flash('success','Batch deleted.');
    }
    header('Location: batches.php?session_id='.$session_id);
    exit;
}

$batches  = $pdo->prepare('SELECT sb.*, u.full_name as teacher_name, COUNT(be.id) as member_count FROM special_batches sb LEFT JOIN users u ON u.id=sb.teacher_id LEFT JOIN batch_enrollments be ON be.batch_id=sb.id AND be.status="active" WHERE sb.session_id=:sess GROUP BY sb.id ORDER BY sb.batch_name');
$batches->execute([':sess'=>$session_id]);
$batches = $batches->fetchAll();

$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$teachers = $pdo->query("SELECT sp.user_id as id, CONCAT(sp.first_name,' ',sp.last_name) as name FROM staff_profiles sp WHERE sp.status='active' ORDER BY name")->fetchAll();

$activeBatch = int_param('batch', 0, $_GET);
$batchMembers = [];
$availableStudents = [];
if ($activeBatch) {
    $bm = $pdo->prepare('SELECT be.id as enroll_id, be.student_id, be.joined_at, sp.first_name, sp.last_name, sp.student_id_no FROM batch_enrollments be JOIN student_profiles sp ON sp.user_id=be.student_id WHERE be.batch_id=:bid AND be.status="active" ORDER BY sp.first_name');
    $bm->execute([':bid'=>$activeBatch]);
    $batchMembers = $bm->fetchAll();

    $enrolled = array_column($batchMembers,'student_id');
    $avQ = "SELECT u.id, sp.first_name, sp.last_name, sp.student_id_no, c.class_name FROM users u JOIN student_profiles sp ON sp.user_id=u.id LEFT JOIN student_enrollments se ON se.student_id=u.id AND se.session_id=:sess AND se.status='active' LEFT JOIN classes c ON c.id=se.class_id WHERE u.status='active'";
    if (!empty($enrolled)) $avQ .= ' AND u.id NOT IN (' . implode(',', array_map('intval',$enrolled)) . ')';
    $avQ .= ' ORDER BY sp.first_name LIMIT 50';
    $av = $pdo->prepare($avQ);
    $av->execute([':sess'=>$session_id]);
    $availableStudents = $av->fetchAll();
}

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Special Batches</h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" onchange="location='?session_id='+this.value" style="width:auto">
      <?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#batchModal" onclick="setBatchForm(null)">
      <i class="bi bi-plus-lg me-1"></i>New Batch
    </button>
  </div>
</div>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Batches</span></div>
      <div class="list-group list-group-flush">
        <?php if (empty($batches)): ?>
          <div class="text-center text-muted py-3 small">No batches for this session</div>
        <?php else: foreach($batches as $b): ?>
        <a href="?session_id=<?= $session_id ?>&batch=<?= $b['id'] ?>"
           class="list-group-item list-group-item-action <?= $activeBatch==$b['id']?'active':'' ?>">
          <div class="d-flex justify-content-between">
            <span class="fw-600"><?= e($b['batch_name']) ?></span>
            <span class="badge bg-secondary"><?= $b['member_count'] ?></span>
          </div>
          <div class="small <?= $activeBatch==$b['id']?'text-white-50':'text-muted' ?>">
            <?= ucfirst(str_replace('_',' ',e($b['batch_type']))) ?> · <?= e($b['teacher_name']??'No teacher') ?>
          </div>
        </a>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <?php if ($activeBatch): ?>
    <?php $curBatch = null; foreach($batches as $b) if($b['id']==$activeBatch) { $curBatch=$b; break; } ?>
    <div class="card mb-3">
      <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="card-title"><?= e($curBatch['batch_name']??'Batch') ?> — Members (<?= count($batchMembers) ?>)</span>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#batchModal"
                  onclick="setBatchForm(<?= htmlspecialchars(json_encode($curBatch??[]),ENT_QUOTES) ?>)">
            <i class="bi bi-pencil me-1"></i>Edit Batch
          </button>
          <form method="POST" class="d-inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete_batch"><input type="hidden" name="id" value="<?= $activeBatch ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this batch?"><i class="bi bi-trash"></i></button>
          </form>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Student</th><th>ID</th><th>Joined</th><th></th></tr></thead>
          <tbody>
            <?php if (empty($batchMembers)): ?>
              <tr><td colspan="4"><div class="text-center text-muted py-3 small">No members yet</div></td></tr>
            <?php else: foreach($batchMembers as $m): ?>
            <tr>
              <td class="fw-600"><?= e($m['first_name'].' '.$m['last_name']) ?></td>
              <td><code><?= e($m['student_id_no']??'') ?></code></td>
              <td><?= fmt_date($m['joined_at']) ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <?= csrf_field() ?><input type="hidden" name="action" value="remove_member">
                  <input type="hidden" name="batch_id" value="<?= $activeBatch ?>">
                  <input type="hidden" name="student_id" value="<?= $m['student_id'] ?>">
                  <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:.1rem .35rem;" data-confirm="Remove?"><i class="bi bi-x"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (!empty($availableStudents)): ?>
      <div class="card-footer p-3">
        <form method="POST" class="d-flex gap-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="enroll"><input type="hidden" name="batch_id" value="<?= $activeBatch ?>">
          <select name="student_id" class="form-select form-select-sm">
            <option value="">— Add student —</option>
            <?php foreach($availableStudents as $av): ?>
              <option value="<?= $av['id'] ?>"><?= e($av['first_name'].' '.$av['last_name']) ?> (<?= e($av['class_name']??'?') ?>)</option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i></button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-people"></i><p>Select a batch to manage members.</p></div></div></div>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="batchModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_batch"><input type="hidden" name="id" id="bt_id" value="0"><input type="hidden" name="session_id" value="<?= $session_id ?>">
        <div class="modal-header"><h5 class="modal-title" id="batchModalTitle">New Batch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Batch Name *</label><input type="text" name="batch_name" id="bt_name" class="form-control" required></div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Type</label>
              <select name="batch_type" id="bt_type" class="form-select">
                <?php foreach(['scholarship'=>'Scholarship Prep','talent_pool'=>'Talent Pool','remedial'=>'Remedial','model_test'=>'Model Test','coaching'=>'Coaching','other'=>'Other'] as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-6"><label class="form-label">Teacher / Coach</label>
              <select name="teacher_id" id="bt_teacher" class="form-select">
                <option value="">— None —</option>
                <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-6"><label class="form-label">Start Date</label><input type="date" name="start_date" id="bt_start" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">End Date</label><input type="date" name="end_date" id="bt_end" class="form-control"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="bt_desc" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function setBatchForm(b) {
  document.getElementById('batchModalTitle').textContent = b && b.id ? 'Edit Batch' : 'New Batch';
  document.getElementById('bt_id').value      = b && b.id ? b.id : 0;
  document.getElementById('bt_name').value    = b ? b.batch_name : '';
  document.getElementById('bt_type').value    = b ? b.batch_type : 'other';
  document.getElementById('bt_teacher').value = b ? (b.teacher_id||'') : '';
  document.getElementById('bt_start').value   = b ? (b.start_date||'') : '';
  document.getElementById('bt_end').value     = b ? (b.end_date||'') : '';
  document.getElementById('bt_desc').value    = b ? (b.description||'') : '';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
