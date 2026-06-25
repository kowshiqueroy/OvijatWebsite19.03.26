<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Enrollment Manager';
$breadcrumbs = ['Students' => 'index.php', 'Enroll' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.create']);

$pdo = db();
$session_id = int_param('session_id',(int)setting('current_session_id',0),$_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'enroll') {
        $student_id = int_param('student_id',0,$_POST);
        $sess       = int_param('session_id',$session_id,$_POST);
        $cls        = int_param('class_id',0,$_POST);
        $sec        = int_param('section_id',0,$_POST);
        $grp        = int_param('group_id',0,$_POST)?:null;
        $roll       = int_param('roll_number',0,$_POST);

        if ($student_id && $sess && $cls && $sec) {
            // Auto roll if 0
            if (!$roll) {
                $mr = $pdo->prepare('SELECT COALESCE(MAX(roll_number),0)+1 FROM student_enrollments WHERE session_id=? AND class_id=? AND section_id=?');
                $mr->execute([$sess,$cls,$sec]);
                $roll = (int)$mr->fetchColumn();
            }
            try {
                $pdo->prepare('INSERT INTO student_enrollments (student_id,session_id,class_id,section_id,group_id,roll_number,status) VALUES (?,?,?,?,?,?,"active")')
                    ->execute([$student_id,$sess,$cls,$sec,$grp,$roll]);
                flash('success',"Student enrolled with roll $roll.");
            } catch (Exception $e) {
                flash('error','Already enrolled in this session.');
            }
        }
    } elseif ($action === 'withdraw') {
        $enroll_id = int_param('id',0,$_POST);
        $pdo->prepare("UPDATE student_enrollments SET status='opt_out' WHERE id=?")->execute([$enroll_id]);
        flash('success','Enrollment withdrawn.');
    }
    header('Location: enroll.php?session_id='.$session_id);
    exit;
}

$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY display_order,class_numeric')->fetchAll();
$groups   = $pdo->query('SELECT id,group_name FROM groups_stream ORDER BY group_name')->fetchAll();

$searchQ = trim($_GET['q']??'');
$students = [];
if ($searchQ) {
    $sr = $pdo->prepare("SELECT u.id, u.full_name, sp.student_id_no FROM users u JOIN student_profiles sp ON sp.user_id=u.id WHERE (sp.first_name LIKE :q OR sp.last_name LIKE :q OR sp.student_id_no LIKE :q) AND u.status='active' LIMIT 20");
    $sr->execute([':q'=>"%$searchQ%"]);
    $students = $sr->fetchAll();
}

// Recent enrollments
$recent = $pdo->prepare("SELECT se.*, sp.first_name, sp.last_name, sp.student_id_no, c.class_name, sec.section_name FROM student_enrollments se JOIN student_profiles sp ON sp.user_id=se.student_id JOIN classes c ON c.id=se.class_id JOIN sections sec ON sec.id=se.section_id WHERE se.session_id=:sess AND se.status='active' ORDER BY se.id DESC LIMIT 15");
$recent->execute([':sess'=>$session_id]);
$recent = $recent->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-person-check-fill me-2 text-primary"></i>Enrollment Manager</h1>
  <select class="form-select form-select-sm" onchange="location='?session_id='+this.value" style="width:auto">
    <?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?>
  </select>
</div>
<div class="row g-3">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Enroll a Student</span></div>
      <div class="card-body">
        <form method="GET" class="mb-3">
          <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Search student by name or ID…" value="<?= e($searchQ) ?>"><input type="hidden" name="session_id" value="<?= $session_id ?>">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
          </div>
        </form>
        <?php if(!empty($students)): ?>
        <div class="list-group list-group-flush mb-3">
          <?php foreach($students as $stu): ?>
          <div class="list-group-item py-2 d-flex align-items-center justify-content-between">
            <div><span class="fw-600"><?= e($stu['full_name']) ?></span><br><small class="text-muted"><?= e($stu['student_id_no']??'') ?></small></div>
            <button class="btn btn-sm btn-success" onclick="setEnrollForm(<?= $stu['id'] ?>, '<?= e($stu['full_name']) ?>')"><i class="bi bi-plus-lg"></i></button>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div id="enroll-form" style="display:none;">
          <div class="alert alert-info small py-2 mb-3"><strong id="enroll_name"></strong> will be enrolled below.</div>
          <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="action" value="enroll"><input type="hidden" name="student_id" id="enroll_sid" value=""><input type="hidden" name="session_id" value="<?= $session_id ?>">
            <div class="row g-2">
              <div class="col-6"><label class="form-label small">Class *</label><select name="class_id" id="enroll_cls" class="form-select form-select-sm" onchange="loadSections(this.value)" required><option value="">—</option><?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['class_name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-6"><label class="form-label small">Section *</label><select name="section_id" id="enroll_sec" class="form-select form-select-sm" required><option value="">— pick class —</option></select></div>
              <div class="col-6"><label class="form-label small">Group</label><select name="group_id" class="form-select form-select-sm"><option value="">— None —</option><?php foreach($groups as $g): ?><option value="<?= $g['id'] ?>"><?= e($g['group_name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-6"><label class="form-label small">Roll (0=auto)</label><input type="number" name="roll_number" class="form-control form-control-sm" value="0" min="0"></div>
            </div>
            <button type="submit" class="btn btn-success w-100 mt-2"><i class="bi bi-person-check me-1"></i>Enroll</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card table-card">
      <div class="card-header py-3 px-4"><span class="card-title">Recent Enrollments (<?= e(($sessions[array_search($session_id,array_column($sessions,'id'))]['session_name']??$session_id)) ?>)</span></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Roll</th><th>Student</th><th>Class</th><th>Section</th><th></th></tr></thead>
          <tbody>
            <?php if(empty($recent)): ?><tr><td colspan="5" class="text-center text-muted small py-2">No enrollments yet this session</td></tr><?php endif; ?>
            <?php foreach($recent as $en): ?>
            <tr>
              <td class="fw-700"><?= $en['roll_number'] ?></td>
              <td class="fw-600"><?= e($en['first_name'].' '.$en['last_name']) ?><br><small class="text-muted"><?= e($en['student_id_no']??'') ?></small></td>
              <td><?= e($en['class_name']) ?></td>
              <td><?= e($en['section_name']) ?></td>
              <td><form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="withdraw"><input type="hidden" name="id" value="<?= $en['id'] ?>"><button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.65rem;padding:.1rem .3rem;" data-confirm="Withdraw this enrollment?"><i class="bi bi-x-lg"></i></button></form></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>
function setEnrollForm(id, name) {
  document.getElementById('enroll_sid').value = id;
  document.getElementById('enroll_name').textContent = name;
  document.getElementById('enroll-form').style.display = 'block';
}
function loadSections(classId) {
  const sel = document.getElementById('enroll_sec');
  if (!classId) { sel.innerHTML='<option>— pick class —</option>'; return; }
  sel.innerHTML='<option>Loading…</option>';
  fetch(`../academic/ajax.php?action=sections&class_id=${classId}`)
    .then(r=>r.json()).then(data=>{
      sel.innerHTML='<option value="">— Select —</option>';
      data.forEach(s=>{sel.innerHTML+=`<option value="${s.id}">${s.section_name}</option>`;});
    });
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
