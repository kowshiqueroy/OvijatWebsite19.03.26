<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Roll Number Adjustment';
$breadcrumbs = ['Students' => 'index.php', 'Roll Adjustment' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.edit']);

$pdo        = db();
$session_id = int_param('session_id', (int)setting('current_session_id', 0), $_GET);
$class_id   = int_param('class_id',   0, $_GET);
$section_id = int_param('section_id', 0, $_GET);

// ── Save roll adjustments ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_rolls') {
    csrf_check();
    $rolls = $_POST['roll'] ?? []; // [enrollment_id => new_roll]
    if (empty($rolls)) {
        flash('error', 'No roll numbers submitted.');
        header("Location: roll_adjust.php?session_id=$session_id&class_id=$class_id&section_id=$section_id");
        exit;
    }

    // Check for duplicates within submission
    $newRolls = array_map('intval', $rolls);
    if (count($newRolls) !== count(array_unique($newRolls))) {
        flash('error', 'Duplicate roll numbers detected. Each student must have a unique roll number.');
        header("Location: roll_adjust.php?session_id=$session_id&class_id=$class_id&section_id=$section_id");
        exit;
    }

    $pdo->beginTransaction();
    try {
        $getStmt = $pdo->prepare('SELECT student_id, roll_number FROM student_enrollments WHERE id=? AND session_id=? AND class_id=? AND section_id=?');
        $updStmt = $pdo->prepare('UPDATE student_enrollments SET roll_number=? WHERE id=?');
        $logStmt = $pdo->prepare('INSERT INTO roll_change_logs (student_id,session_id,class_id,section_id,old_roll,new_roll,changed_by,reason) VALUES (?,?,?,?,?,?,?,?)');

        $reason  = trim($_POST['reason'] ?? 'Manual adjustment');
        $changed = 0;

        foreach ($rolls as $enroll_id => $new_roll) {
            $new_roll = (int)$new_roll;
            if ($new_roll <= 0) continue;

            $getStmt->execute([(int)$enroll_id, $session_id, $class_id, $section_id]);
            $row = $getStmt->fetch();
            if (!$row || $row['roll_number'] == $new_roll) continue;

            $updStmt->execute([$new_roll, (int)$enroll_id]);
            $logStmt->execute([$row['student_id'], $session_id, $class_id, $section_id, $row['roll_number'], $new_roll, current_user_id(), $reason]);
            $changed++;
        }

        $pdo->commit();
        log_activity('roll_number_adjusted', 'students', $class_id, '', "Changed $changed rolls in class $class_id section $section_id");
        flash('success', "$changed roll number(s) updated. All exam marks and attendance records are preserved.");
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Failed: ' . $e->getMessage());
    }
    header("Location: roll_adjust.php?session_id=$session_id&class_id=$class_id&section_id=$section_id");
    exit;
}

$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY display_order,class_name')->fetchAll();

$sections = [];
if ($class_id) {
    $s = $pdo->prepare('SELECT id,section_name FROM sections WHERE class_id=? AND status=1 ORDER BY section_name');
    $s->execute([$class_id]);
    $sections = $s->fetchAll();
}

// Load enrolled students with their current roll numbers
$students = [];
if ($class_id && $section_id && $session_id) {
    $st = $pdo->prepare(
        'SELECT se.id AS enroll_id, se.student_id, se.roll_number,
                sp.first_name, sp.last_name, sp.student_id_no,
                (SELECT COUNT(*) FROM marks_entry WHERE student_id=se.student_id) AS mark_count,
                (SELECT COUNT(*) FROM student_attendance WHERE student_id=se.student_id AND session_id=se.session_id) AS att_count
         FROM student_enrollments se
         JOIN student_profiles sp ON sp.user_id=se.student_id
         WHERE se.session_id=? AND se.class_id=? AND se.section_id=? AND se.status="active"
         ORDER BY se.roll_number'
    );
    $st->execute([$session_id, $class_id, $section_id]);
    $students = $st->fetchAll();
}

// Load change history
$history = [];
if ($class_id && $section_id && $session_id) {
    $h = $pdo->prepare(
        'SELECT rcl.*, sp.first_name, sp.last_name, u.full_name AS changed_by_name
         FROM roll_change_logs rcl
         JOIN student_profiles sp ON sp.user_id=rcl.student_id
         JOIN users u ON u.id=rcl.changed_by
         WHERE rcl.session_id=? AND rcl.class_id=? AND rcl.section_id=?
         ORDER BY rcl.changed_at DESC LIMIT 50'
    );
    $h->execute([$session_id, $class_id, $section_id]);
    $history = $h->fetchAll();
}

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-sort-numeric-up me-2 text-primary"></i>Roll Number Adjustment</h1>
  <?php if (!empty($students)): ?>
    <div class="d-flex gap-2">
      <a href="index.php?session_id=<?= $session_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Student List
      </a>
    </div>
  <?php endif; ?>
</div>

<div class="alert alert-warning d-flex gap-2 mb-3">
  <i class="bi bi-info-circle-fill fs-5"></i>
  <div>Roll numbers can be safely changed in any session. <strong>All exam marks, attendance, and fee records remain linked to the student — not the roll number.</strong> A change log is kept for audit purposes.</div>
</div>

<!-- Filters -->
<div class="card mb-4 border-0 shadow-sm">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end" data-no-protect>
      <div class="col-md-3">
        <label class="form-label small fw-600">Session</label>
        <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sessions as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-600">Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Select Class —</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $class_id==$c['id']?'selected':'' ?>><?= e($c['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-600">Section</label>
        <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Select Section —</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>" <?= $section_id==$sec['id']?'selected':'' ?>><?= e($sec['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if (!$class_id || !$section_id): ?>
<div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-sort-numeric-up"></i><p>Select a class and section to adjust roll numbers.</p></div></div></div>

<?php elseif (empty($students)): ?>
<div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-person-x"></i><p>No active students found in this class/section for the selected session.</p></div></div></div>

<?php else: ?>
<div class="row g-3">
  <div class="col-md-8">
    <form method="POST" id="rollForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_rolls">
      <div class="card shadow-sm border-0">
        <div class="card-header py-3 px-4 bg-light d-flex align-items-center justify-content-between">
          <span class="card-title">Adjust Roll Numbers — <?= count($students) ?> students</span>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="autoFillRolls()">
              <i class="bi bi-123 me-1"></i>Auto-fill 1…N
            </button>
            <button type="submit" class="btn btn-xs btn-primary" onclick="return confirmSave()">
              <i class="bi bi-save me-1"></i>Save Changes
            </button>
          </div>
        </div>
        <div class="card-body border-bottom py-2">
          <label class="form-label small fw-600">Reason for Change <span class="text-danger">*</span></label>
          <input type="text" name="reason" class="form-control form-control-sm" required
                 placeholder="e.g. Merit reorder, Transfer student insertion, Clerical correction" value="Manual adjustment">
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0" id="rollTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Student Name</th>
                <th>ID No</th>
                <th class="text-center">Current Roll</th>
                <th class="text-center">New Roll</th>
                <th class="text-center text-muted small">Marks/Att</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $i => $stu): ?>
              <tr id="row-<?= $stu['enroll_id'] ?>">
                <td class="text-muted small"><?= $i+1 ?></td>
                <td>
                  <div class="fw-600 small"><?= e($stu['first_name'] . ' ' . $stu['last_name']) ?></div>
                </td>
                <td><code style="font-size:.7rem"><?= e($stu['student_id_no'] ?? '—') ?></code></td>
                <td class="text-center text-muted fw-600"><?= $stu['roll_number'] ?></td>
                <td class="text-center">
                  <input type="number"
                         name="roll[<?= $stu['enroll_id'] ?>]"
                         class="form-control form-control-sm text-center roll-input"
                         value="<?= $stu['roll_number'] ?>"
                         min="1" max="9999"
                         style="width:70px;margin:auto"
                         oninput="checkDuplicates()"
                         data-orig="<?= $stu['roll_number'] ?>">
                </td>
                <td class="text-center text-muted" style="font-size:.72rem;">
                  <?= $stu['mark_count'] ?> / <?= $stu['att_count'] ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer py-2 d-flex gap-2 align-items-center">
          <button type="submit" class="btn btn-primary btn-sm" onclick="return confirmSave()">
            <i class="bi bi-save me-1"></i>Save Roll Changes
          </button>
          <small class="text-muted"><i class="bi bi-shield-check me-1 text-success"></i>Exam marks, attendance and fees are preserved and remain linked to the student.</small>
        </div>
      </div>
    </form>
  </div>

  <div class="col-md-4">
    <!-- Change History -->
    <div class="card shadow-sm border-0">
      <div class="card-header py-3 px-4 bg-light"><span class="card-title">Change History</span></div>
      <div style="max-height:400px;overflow-y:auto;">
        <?php if (empty($history)): ?>
          <div class="p-4 text-center text-muted small">No roll number changes recorded yet.</div>
        <?php else: foreach ($history as $h): ?>
          <div class="px-3 py-2 border-bottom">
            <div class="d-flex justify-content-between">
              <span class="fw-600 small"><?= e($h['first_name'].' '.$h['last_name']) ?></span>
              <span class="text-danger fw-700 small"><?= $h['old_roll'] ?> → <?= $h['new_roll'] ?></span>
            </div>
            <div class="text-muted" style="font-size:.7rem;">
              <?= fmt_date($h['changed_at'], 'd M Y H:i') ?> by <?= e($h['changed_by_name']) ?>
              <?= $h['reason'] ? ' — '.e(substr($h['reason'],0,50)) : '' ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function autoFillRolls() {
  const inputs = document.querySelectorAll('.roll-input');
  inputs.forEach((inp, i) => { inp.value = i + 1; });
  checkDuplicates();
}

function checkDuplicates() {
  const inputs = [...document.querySelectorAll('.roll-input')];
  const vals   = inputs.map(i => parseInt(i.value) || 0);
  const counts = {};
  vals.forEach(v => { counts[v] = (counts[v] || 0) + 1; });
  let hasDup = false;
  inputs.forEach((inp, i) => {
    const isDup = counts[vals[i]] > 1;
    inp.classList.toggle('is-invalid', isDup);
    if (isDup) hasDup = true;
  });
  const btn = document.querySelector('button[type=submit]');
  if (btn) btn.disabled = hasDup;
  return !hasDup;
}

function confirmSave() {
  if (!checkDuplicates()) {
    EMS.showError('Duplicate roll numbers detected. Each student must have a unique roll number.');
    return false;
  }
  const inputs  = [...document.querySelectorAll('.roll-input')];
  const changed = inputs.filter(i => parseInt(i.value) !== parseInt(i.dataset.orig));
  if (!changed.length) { EMS.showError('No roll numbers were changed.', null, 3000); return false; }
  return confirm(`Confirm: Update ${changed.length} roll number(s)?\nThis is logged and cannot be automatically undone.`);
}
</script>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
