<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Student Promotions';
$breadcrumbs = ['Students' => 'index.php', 'Promotions' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.promote']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'promote') {
        $from_session = int_param('from_session_id', 0, $_POST);
        $to_session   = int_param('to_session_id', 0, $_POST);
        $from_class   = int_param('from_class_id', 0, $_POST);
        $to_class     = int_param('to_class_id', 0, $_POST);
        $from_section = int_param('from_section_id', 0, $_POST);
        $to_section   = int_param('to_section_id', 0, $_POST);
        $student_ids  = array_map('intval', (array)($_POST['student_ids'] ?? []));

        if (!$from_session || !$to_session || !$from_class || !$to_class || !$from_section || !$to_section) {
            flash('error', 'All fields are required.');
        } elseif (empty($student_ids)) {
            flash('error', 'No students selected.');
        } else {
            try {
                $pdo->beginTransaction();

                // Get max roll in target class/section/session
                $maxRoll = (int)$pdo->prepare('SELECT COALESCE(MAX(roll_number),0) FROM student_enrollments WHERE session_id=? AND class_id=? AND section_id=?')
                    ->execute([$to_session,$to_class,$to_section]) ? 0 : 0;
                $mr = $pdo->prepare('SELECT COALESCE(MAX(roll_number),0) FROM student_enrollments WHERE session_id=? AND class_id=? AND section_id=?');
                $mr->execute([$to_session,$to_class,$to_section]);
                $maxRoll = (int)$mr->fetchColumn();

                $insertStmt = $pdo->prepare(
                    'INSERT IGNORE INTO student_enrollments (student_id,session_id,class_id,section_id,roll_number,status)
                     VALUES (?,?,?,?,?,"active")'
                );

                $promoted = 0;
                foreach ($student_ids as $sid) {
                    $insertStmt->execute([$sid,$to_session,$to_class,$to_section,++$maxRoll]);
                    $promoted++;
                }

                $pdo->commit();
                log_activity('promote_students', 'students', 0, '', "Count:$promoted to class:$to_class session:$to_session");
                flash('success', "$promoted students promoted successfully.");
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', 'Error: ' . $e->getMessage());
            }
        }
        header('Location: promote.php');
        exit;
    }
}

$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id, class_name FROM classes WHERE status=1 ORDER BY display_order, class_numeric')->fetchAll();

$from_session = int_param('from_session_id', (int)setting('current_session_id',0), $_GET);
$from_class   = int_param('from_class_id', 0, $_GET);
$from_section = int_param('from_section_id', 0, $_GET);

$students = [];
if ($from_class && $from_section && $from_session) {
    $stu = $pdo->prepare(
        'SELECT se.student_id, se.roll_number, sp.first_name, sp.last_name, sp.student_id_no
         FROM student_enrollments se JOIN student_profiles sp ON sp.user_id=se.student_id
         WHERE se.class_id=:cls AND se.section_id=:sec AND se.session_id=:sess AND se.status="active"
         ORDER BY se.roll_number'
    );
    $stu->execute([':cls'=>$from_class,':sec'=>$from_section,':sess'=>$from_session]);
    $students = $stu->fetchAll();
}

$sections = [];
if ($from_class) {
    $sec = $pdo->prepare('SELECT id, section_name FROM sections WHERE class_id=:c AND status=1 ORDER BY section_name');
    $sec->execute([':c'=>$from_class]);
    $sections = $sec->fetchAll();
}

require_once EMS_ROOT . '/includes/header.php';
?>

<h1 class="page-title"><i class="bi bi-arrow-up-circle-fill me-2 text-primary"></i>Student Promotions</h1>

<div class="alert alert-info d-flex gap-2 mb-3">
  <i class="bi bi-info-circle-fill"></i>
  <div>Select students from a <strong>source</strong> class/session, then choose the <strong>target</strong> class/session to promote them into. Existing enrollments in the target are skipped automatically.</div>
</div>

<div class="row g-3">
  <!-- Source selector -->
  <div class="col-md-5">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Source (Promote FROM)</span></div>
      <div class="card-body">
        <form method="GET" class="row g-2">
          <div class="col-12">
            <label class="form-label small">Session</label>
            <select name="from_session_id" class="form-select form-select-sm" onchange="this.form.submit()">
              <?php foreach ($sessions as $sess): ?>
                <option value="<?= $sess['id'] ?>" <?= $from_session == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small">Class</label>
            <select name="from_class_id" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="0">— Select —</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?= $cls['id'] ?>" <?= $from_class == $cls['id'] ? 'selected' : '' ?>><?= e($cls['class_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small">Section</label>
            <select name="from_section_id" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="0">— Select —</option>
              <?php foreach ($sections as $sec): ?>
                <option value="<?= $sec['id'] ?>" <?= $from_section == $sec['id'] ? 'selected' : '' ?>><?= e($sec['section_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Target selector + student list -->
  <div class="col-md-7">
    <?php if (!empty($students)): ?>
    <div class="card">
      <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="card-title"><?= count($students) ?> Students — Promote To</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll()">Toggle All</button>
      </div>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="promote">
        <input type="hidden" name="from_session_id" value="<?= $from_session ?>">
        <input type="hidden" name="from_class_id" value="<?= $from_class ?>">
        <input type="hidden" name="from_section_id" value="<?= $from_section ?>">

        <div class="card-body border-bottom">
          <div class="form-section-title mt-0">Target Session & Class</div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label small">To Session</label>
              <select name="to_session_id" class="form-select form-select-sm" required>
                <?php foreach ($sessions as $sess): ?>
                  <option value="<?= $sess['id'] ?>"><?= e($sess['session_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small">To Class</label>
              <select name="to_class_id" class="form-select form-select-sm" required id="to_class_sel"
                      onchange="loadToSections(this.value)">
                <option value="">— Select —</option>
                <?php foreach ($classes as $cls): ?>
                  <option value="<?= $cls['id'] ?>"><?= e($cls['class_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small">To Section</label>
              <select name="to_section_id" class="form-select form-select-sm" required id="to_section_sel">
                <option value="">— Select class first —</option>
              </select>
            </div>
          </div>
        </div>

        <div class="table-responsive" style="max-height:350px;overflow-y:auto;">
          <table class="table table-sm mb-0">
            <thead style="position:sticky;top:0;background:#f8fafc;z-index:1;">
              <tr><th style="width:40px"><input type="checkbox" id="chk_all" onchange="toggleAll(this.checked)"></th><th>Roll</th><th>Name</th><th>ID</th></tr>
            </thead>
            <tbody>
              <?php foreach ($students as $stu): ?>
              <tr>
                <td><input type="checkbox" class="stu-cb" name="student_ids[]" value="<?= $stu['student_id'] ?>" checked></td>
                <td class="fw-700"><?= $stu['roll_number'] ?></td>
                <td class="fw-600"><?= e($stu['first_name'] . ' ' . $stu['last_name']) ?></td>
                <td><code><?= e($stu['student_id_no'] ?? '') ?></code></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="card-footer d-flex gap-2 py-3 px-4">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-arrow-up-circle me-1"></i>Promote Selected Students
          </button>
        </div>
      </form>
    </div>
    <?php else: ?>
    <div class="card"><div class="card-body">
      <div class="empty-state"><i class="bi bi-people"></i>
        <p>Select source class and section to see students.</p>
      </div>
    </div></div>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleAll(checked) {
  const state = checked ?? !document.querySelector('.stu-cb')?.checked;
  document.querySelectorAll('.stu-cb').forEach(cb => cb.checked = state);
  document.getElementById('chk_all').checked = state;
}
function loadToSections(classId) {
  const sel = document.getElementById('to_section_sel');
  if (!classId) { sel.innerHTML = '<option value="">— Select class first —</option>'; return; }
  sel.innerHTML = '<option>Loading…</option>';
  fetch(`../academic/ajax.php?action=sections&class_id=${classId}`)
    .then(r => r.json())
    .then(data => {
      sel.innerHTML = '<option value="">— Select —</option>';
      data.forEach(s => { sel.innerHTML += `<option value="${s.id}">${s.section_name}</option>`; });
    });
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
