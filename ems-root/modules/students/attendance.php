<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Student Attendance';
$breadcrumbs = ['Students' => 'index.php', 'Attendance' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['attendance.mark']);

$pdo = db();

$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);
$class_id   = int_param('class_id', 0, $_GET);
$section_id = int_param('section_id', 0, $_GET);
$date       = $_GET['date'] ?? date('Y-m-d');

// Handle POST — save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    csrf_check();
    $att_date   = $_POST['att_date'] ?? date('Y-m-d');
    $cls        = int_param('class_id', 0, $_POST);
    $sec        = int_param('section_id', 0, $_POST);
    $sess       = int_param('session_id', 0, $_POST);
    $statuses   = $_POST['status'] ?? [];

    $stmt = $pdo->prepare(
        'INSERT INTO student_attendance (student_id, session_id, class_id, section_id, attendance_date, status, marked_by)
         VALUES (?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE status=VALUES(status), marked_by=VALUES(marked_by)'
    );

    $saved = 0;
    foreach ($statuses as $studentId => $status) {
        $stmt->execute([$studentId, $sess, $cls, $sec, $att_date, $status, current_user_id()]);
        $saved++;
    }
    log_activity('mark_attendance', 'students', 0, '', "Date:$att_date, Count:$saved");
    flash('success', "Attendance saved for $saved students on " . fmt_date($att_date) . ".");
    header("Location: attendance.php?session_id=$sess&class_id=$cls&section_id=$sec&date=$att_date");
    exit;
}

// Load students
$students = [];
$existing = [];
if ($class_id && $section_id && $session_id) {
    $stu = $pdo->prepare(
        'SELECT se.student_id, se.roll_number, sp.first_name, sp.last_name, sp.photo
         FROM student_enrollments se
         JOIN student_profiles sp ON sp.user_id=se.student_id
         WHERE se.class_id=:cls AND se.section_id=:sec AND se.session_id=:sess AND se.status="active"
         ORDER BY se.roll_number'
    );
    $stu->execute([':cls'=>$class_id,':sec'=>$section_id,':sess'=>$session_id]);
    $students = $stu->fetchAll();

    // Load existing attendance for this date
    $ex = $pdo->prepare('SELECT student_id, status FROM student_attendance WHERE class_id=:cls AND section_id=:sec AND attendance_date=:d');
    $ex->execute([':cls'=>$class_id,':sec'=>$section_id,':d'=>$date]);
    foreach ($ex->fetchAll() as $r) $existing[$r['student_id']] = $r['status'];
}

// Attendance summary for last 7 days (for mini calendar)
$summary = [];
if ($class_id && $section_id) {
    $sm = $pdo->prepare(
        'SELECT attendance_date, status, COUNT(*) as cnt
         FROM student_attendance
         WHERE class_id=:cls AND section_id=:sec AND attendance_date >= DATE_SUB(:d, INTERVAL 7 DAY)
         GROUP BY attendance_date, status ORDER BY attendance_date DESC'
    );
    $sm->execute([':cls'=>$class_id,':sec'=>$section_id,':d'=>$date]);
    foreach ($sm->fetchAll() as $r) $summary[$r['attendance_date']][$r['status']] = $r['cnt'];
}

$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id, class_name FROM classes WHERE status=1 ORDER BY display_order')->fetchAll();
$sections = $class_id
    ? $pdo->prepare('SELECT id, section_name FROM sections WHERE class_id=:c AND status=1 ORDER BY section_name')
    : null;
if ($sections) { $sections->execute([':c'=>$class_id]); $sections = $sections->fetchAll(); }
else $sections = [];

require_once EMS_ROOT . '/includes/header.php';
?>

<h1 class="page-title"><i class="bi bi-calendar-check-fill me-2 text-primary"></i>Student Attendance</h1>

<!-- Filter bar -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label small">Session</label>
        <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sessions as $sess): ?>
            <option value="<?= $sess['id'] ?>" <?= $session_id == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">— Select —</option>
          <?php foreach ($classes as $cls): ?>
            <option value="<?= $cls['id'] ?>" <?= $class_id == $cls['id'] ? 'selected' : '' ?>><?= e($cls['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!empty($sections)): ?>
      <div class="col-md-2">
        <label class="form-label small">Section</label>
        <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">— Select —</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>" <?= $section_id == $sec['id'] ? 'selected' : '' ?>><?= e($sec['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-2">
        <label class="form-label small">Date</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>" onchange="this.form.submit()">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3">
  <!-- Attendance sheet -->
  <div class="col-md-8">
    <?php if (!empty($students)): ?>
    <div class="card">
      <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="card-title">Attendance — <?= fmt_date($date) ?></span>
        <div class="d-flex align-items-center gap-3">
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="absenteesOnlyMode" onchange="toggleMode()">
            <label class="form-check-label small fw-bold" for="absenteesOnlyMode">Absentees Only Mode</label>
          </div>
          <div class="d-flex gap-1" id="bulk-att-buttons">
            <button type="button" class="btn btn-xs btn-outline-success" onclick="setAll('present')">All Present</button>
            <button type="button" class="btn btn-xs btn-outline-danger" onclick="setAll('absent')">All Absent</button>
          </div>
        </div>
      </div>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="att_date" value="<?= e($date) ?>">
        <input type="hidden" name="session_id" value="<?= $session_id ?>">
        <input type="hidden" name="class_id" value="<?= $class_id ?>">
        <input type="hidden" name="section_id" value="<?= $section_id ?>">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th style="width:60px">Roll</th>
                <th>Name</th>
                <th class="normal-att-header">P</th>
                <th class="normal-att-header">A</th>
                <th class="normal-att-header">L</th>
                <th class="normal-att-header">E</th>
                <th class="absent-only-header d-none" style="width:100px;">Is Absent?</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $stu):
                $cur = $existing[$stu['student_id']] ?? 'present';
              ?>
              <tr>
                <td class="fw-700 text-center"><?= $stu['roll_number'] ?></td>
                <td class="fw-600"><?= e($stu['first_name'] . ' ' . $stu['last_name']) ?></td>
                <?php foreach (['present'=>'success','absent'=>'danger','late'=>'warning','excused'=>'info'] as $st => $color): ?>
                <td class="text-center normal-att-col">
                  <input type="radio" class="form-check-input att-radio" style="cursor:pointer;"
                         name="status[<?= $stu['student_id'] ?>]" value="<?= $st ?>"
                         <?= $cur === $st ? 'checked' : '' ?>>
                </td>
                <?php endforeach; ?>
                <td class="text-center absent-only-col d-none">
                  <input type="checkbox" class="form-check-input absent-only-chk" style="cursor:pointer;"
                         id="absent_chk_<?= $stu['student_id'] ?>"
                         onchange="syncRadio(<?= $stu['student_id'] ?>, this.checked)"
                         <?= $cur === 'absent' ? 'checked' : '' ?>>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer d-flex gap-2 py-3 px-4">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Save Attendance (<?= count($students) ?> students)
          </button>
        </div>
      </form>
    </div>
    <?php else: ?>
    <div class="card"><div class="card-body">
      <div class="empty-state"><i class="bi bi-calendar-x"></i>
        <p><?= ($class_id && $section_id) ? 'No students enrolled in this class/section.' : 'Select class and section to mark attendance.' ?></p>
      </div>
    </div></div>
    <?php endif; ?>
  </div>

  <!-- Recent summary -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Last 7 Days Summary</span></div>
      <div class="card-body p-0">
        <?php if (empty($summary)): ?>
          <div class="text-muted text-center py-3 small">No attendance data yet</div>
        <?php else: ?>
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Date</th><th class="text-success">P</th><th class="text-danger">A</th><th class="text-warning">L</th></tr></thead>
          <tbody>
            <?php foreach ($summary as $d => $row): ?>
            <tr class="<?= $d === $date ? 'table-primary' : '' ?>">
              <td><?= fmt_date($d, 'd M') ?></td>
              <td class="text-success fw-600"><?= $row['present'] ?? 0 ?></td>
              <td class="text-danger"><?= $row['absent'] ?? 0 ?></td>
              <td class="text-warning"><?= $row['late'] ?? 0 ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function toggleMode() {
  const isAbsentMode = document.getElementById('absenteesOnlyMode').checked;
  
  // Toggle headers
  document.querySelectorAll('.normal-att-header').forEach(el => el.classList.toggle('d-none', isAbsentMode));
  document.querySelectorAll('.absent-only-header').forEach(el => el.classList.toggle('d-none', !isAbsentMode));
  
  // Toggle body columns
  document.querySelectorAll('.normal-att-col').forEach(el => el.classList.toggle('d-none', isAbsentMode));
  document.querySelectorAll('.absent-only-col').forEach(el => el.classList.toggle('d-none', !isAbsentMode));
  
  // Toggle bulk buttons
  document.getElementById('bulk-att-buttons').classList.toggle('d-none', isAbsentMode);
}

function syncRadio(studentId, isChecked) {
  const val = isChecked ? 'absent' : 'present';
  const radio = document.querySelector(`input[name="status[${studentId}]"][value="${val}"]`);
  if (radio) radio.checked = true;
}

function setAll(status) {
  document.querySelectorAll(`.att-radio[value="${status}"]`).forEach(r => r.checked = true);
  document.querySelectorAll('.absent-only-chk').forEach(chk => {
    chk.checked = (status === 'absent');
  });
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
