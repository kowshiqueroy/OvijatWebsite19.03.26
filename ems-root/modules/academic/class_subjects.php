<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Class Subject Mapping';
$breadcrumbs = ['Academic' => null, 'Class Subjects' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['academic.manage']);

$pdo        = db();
$session_id = int_param('session_id',(int)setting('current_session_id',0),$_GET);
$class_id   = int_param('class_id',0,$_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action']??'';
    if ($action === 'save') {
        $id   = int_param('id',0,$_POST);
        $sess = int_param('session_id',$session_id,$_POST);
        $cls  = int_param('class_id',0,$_POST);
        $subj = int_param('subject_id',0,$_POST);
        $grp  = int_param('group_id',0,$_POST)?:null;
        $fmw  = int_param('full_marks_written',0,$_POST);
        $fmm  = int_param('full_marks_mcq',0,$_POST);
        $fmp  = int_param('full_marks_practical',0,$_POST);
        $pmw  = int_param('pass_marks_written',0,$_POST);
        $pmm  = int_param('pass_marks_mcq',0,$_POST);
        $pmp  = int_param('pass_marks_practical',0,$_POST);
        if ($sess && $cls && $subj) {
            try {
                if ($id) {
                    $pdo->prepare('UPDATE class_subjects SET full_marks_written=?,full_marks_mcq=?,full_marks_practical=?,pass_marks_written=?,pass_marks_mcq=?,pass_marks_practical=? WHERE id=?')
                        ->execute([$fmw,$fmm,$fmp,$pmw,$pmm,$pmp,$id]);
                } else {
                    $pdo->prepare('INSERT INTO class_subjects (class_id,session_id,subject_id,group_id,full_marks_written,full_marks_mcq,full_marks_practical,pass_marks_written,pass_marks_mcq,pass_marks_practical) VALUES (?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$cls,$sess,$subj,$grp,$fmw,$fmm,$fmp,$pmw,$pmm,$pmp]);
                }
                flash('success','Subject mapping saved.');
            } catch (Exception $e) { flash('error','Already mapped.'); }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM class_subjects WHERE id=?')->execute([int_param('id',0,$_POST)]);
        flash('success','Mapping removed.');
    }
    header("Location: class_subjects.php?session_id=$session_id&class_id=$class_id");
    exit;
}

$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY display_order,class_numeric')->fetchAll();
$subjects = $pdo->query('SELECT id,subject_name,has_mcq,has_practical FROM subjects WHERE status=1 ORDER BY subject_name')->fetchAll();
$groups   = $pdo->query('SELECT id,group_name FROM groups_stream ORDER BY group_name')->fetchAll();

$mappings = [];
if ($class_id && $session_id) {
    $m = $pdo->prepare('SELECT cs.*,s.subject_name,s.has_mcq,s.has_practical,g.group_name FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id LEFT JOIN groups_stream g ON g.id=cs.group_id WHERE cs.class_id=:c AND cs.session_id=:s ORDER BY s.subject_name');
    $m->execute([':c'=>$class_id,':s'=>$session_id]);
    $mappings = $m->fetchAll();
}

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-journal-check me-2 text-primary"></i>Class Subject Mapping</h1>
  <?php if($class_id && $session_id): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#csModal" onclick="setCSForm(null)"><i class="bi bi-plus-lg me-1"></i>Add Subject</button>
  <?php endif; ?>
</div>
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label small">Session</label><select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()"><?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label small">Class</label><select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="0">— Select Class —</option><?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $class_id==$c['id']?'selected':'' ?>><?= e($c['class_name']) ?></option><?php endforeach; ?></select></div>
  </form>
</div></div>

<?php if($class_id): ?>
<div class="card table-card">
  <div class="card-header py-3 px-4"><span class="card-title">Mapped Subjects <span class="badge bg-secondary"><?= count($mappings) ?></span></span></div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Subject</th><th>Group</th><th>Written</th><th>MCQ</th><th>Practical</th><th>Pass (W/M/P)</th><th></th></tr></thead>
      <tbody>
        <?php if(empty($mappings)): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-journal-x"></i><p>No subjects mapped. Add subjects for this class and session.</p></div></td></tr><?php endif; ?>
        <?php foreach($mappings as $m): ?>
        <tr>
          <td class="fw-600"><?= e($m['subject_name']) ?></td>
          <td><?= e($m['group_name']??'—') ?></td>
          <td><?= $m['full_marks_written'] ?></td>
          <td><?= $m['full_marks_mcq'] ?: '—' ?></td>
          <td><?= $m['full_marks_practical'] ?: '—' ?></td>
          <td class="text-muted small"><?= $m['pass_marks_written'] ?> / <?= $m['pass_marks_mcq'] ?: '—' ?> / <?= $m['pass_marks_practical'] ?: '—' ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#csModal" onclick="setCSForm(<?= htmlspecialchars(json_encode($m),ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
              <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Remove?"><i class="bi bi-trash"></i></button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-journal"></i><p>Select a session and class to manage subject mappings.</p></div></div></div>
<?php endif; ?>

<div class="modal fade" id="csModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="cs_id" value="0"><input type="hidden" name="session_id" value="<?= $session_id ?>"><input type="hidden" name="class_id" value="<?= $class_id ?>">
        <div class="modal-header"><h5 class="modal-title" id="csModalTitle">Map Subject</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Subject *</label><select name="subject_id" id="cs_subj" class="form-select" required><option value="">—</option><?php foreach($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['subject_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Group (optional)</label><select name="group_id" id="cs_grp" class="form-select"><option value="">— All Groups —</option><?php foreach($groups as $g): ?><option value="<?= $g['id'] ?>"><?= e($g['group_name']) ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="form-section-title">Full Marks</div>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Written</label><input type="number" name="full_marks_written" id="cs_fw" class="form-control" value="100" min="0"></div>
            <div class="col-md-4"><label class="form-label">MCQ</label><input type="number" name="full_marks_mcq" id="cs_fm" class="form-control" value="0" min="0"></div>
            <div class="col-md-4"><label class="form-label">Practical</label><input type="number" name="full_marks_practical" id="cs_fp" class="form-control" value="0" min="0"></div>
          </div>
          <div class="form-section-title">Pass Marks</div>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Written</label><input type="number" name="pass_marks_written" id="cs_pw" class="form-control" value="33" min="0"></div>
            <div class="col-md-4"><label class="form-label">MCQ</label><input type="number" name="pass_marks_mcq" id="cs_pm" class="form-control" value="0" min="0"></div>
            <div class="col-md-4"><label class="form-label">Practical</label><input type="number" name="pass_marks_practical" id="cs_pp" class="form-control" value="0" min="0"></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function setCSForm(m){
  document.getElementById('csModalTitle').textContent=m?'Edit Mapping':'Map Subject';
  document.getElementById('cs_id').value=m?m.id:0;
  document.getElementById('cs_subj').value=m?m.subject_id:'';
  document.getElementById('cs_grp').value=m?(m.group_id||''):'';
  document.getElementById('cs_fw').value=m?m.full_marks_written:100;
  document.getElementById('cs_fm').value=m?m.full_marks_mcq:0;
  document.getElementById('cs_fp').value=m?m.full_marks_practical:0;
  document.getElementById('cs_pw').value=m?m.pass_marks_written:33;
  document.getElementById('cs_pm').value=m?m.pass_marks_mcq:0;
  document.getElementById('cs_pp').value=m?m.pass_marks_practical:0;
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
