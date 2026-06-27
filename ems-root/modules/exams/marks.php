<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Marks Entry';
$breadcrumbs = ['Examinations' => 'index.php', 'Mark Entry' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['marks.enter']);

$pdo     = db();
$errors  = [];
$saved   = 0;

$exam_id    = int_param('exam_id', 0, $_GET);
$class_id   = int_param('class_id', 0, $_GET);
$section_id = int_param('section_id', 0, $_GET);
$subject_id = int_param('subject_id', 0, $_GET);

// Load exam info first — needed to check published status before save
$exam = null;
if ($exam_id) {
    $s = $pdo->prepare('SELECT e.*, ass.session_name FROM exams e JOIN academic_sessions ass ON ass.id=e.session_id WHERE e.id=:id AND e.deleted_at IS NULL');
    $s->execute([':id' => $exam_id]);
    $exam = $s->fetch();
}

// Block all access for results_published exams
if ($exam && $exam['status'] === 'results_published') {
    flash('warning', 'Mark entry is locked for "' . $exam['exam_name'] . '" because results have been published.');
    header('Location: index.php');
    exit;
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_marks') {
    csrf_check();
    // Double-check exam is not published
    if ($exam && $exam['status'] === 'results_published') {
        flash('error', 'Cannot save marks — results for this exam are already published.');
        header('Location: marks.php?exam_id=' . $exam_id);
        exit;
    }
    $marksData = $_POST['marks'] ?? [];
    $stmt = $pdo->prepare(
        'INSERT INTO marks_entry
         (exam_id,student_id,class_id,section_id,subject_id,marks_written,marks_mcq,marks_practical,is_absent,entered_by)
         VALUES (?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
         marks_written=VALUES(marks_written), marks_mcq=VALUES(marks_mcq),
         marks_practical=VALUES(marks_practical), is_absent=VALUES(is_absent), entered_by=VALUES(entered_by)'
    );
    foreach ($marksData as $studentId => $mData) {
        $isAbsent = isset($mData['absent']) ? 1 : 0;
        $stmt->execute([
            $exam_id, $studentId, $class_id, $section_id, $subject_id,
            $isAbsent ? null : ($mData['written'] !== '' ? (float)$mData['written'] : null),
            $isAbsent ? null : ($mData['mcq']     !== '' ? (float)$mData['mcq']     : null),
            $isAbsent ? null : ($mData['prac']    !== '' ? (float)$mData['prac']    : null),
            $isAbsent,
            current_user_id(),
        ]);
        $saved++;
    }
    log_activity('marks_entered', 'exams', $exam_id);
    flash('success', "$saved student marks saved.");
    header("Location: marks.php?exam_id=$exam_id&class_id=$class_id&section_id=$section_id&subject_id=$subject_id");
    exit;
}

// Classes for this exam
$examClasses = [];
if ($exam_id) {
    $ec = $pdo->prepare('SELECT DISTINCT c.id, c.class_name FROM exam_class_map ecm JOIN classes c ON c.id=ecm.class_id WHERE ecm.exam_id=:eid ORDER BY c.display_order');
    $ec->execute([':eid' => $exam_id]);
    $examClasses = $ec->fetchAll();
}

// Sections for class
$sections = [];
if ($class_id) {
    $sec = $pdo->prepare('SELECT id, section_name FROM sections WHERE class_id=:c AND status=1 ORDER BY section_name');
    $sec->execute([':c' => $class_id]);
    $sections = $sec->fetchAll();
}

// Subjects for exam+class
$subjects = [];
if ($exam_id && $class_id) {
    $subj = $pdo->prepare('SELECT esc.id as config_id, s.id, s.subject_name, s.has_mcq, s.has_practical,
                            esc.full_marks_written, esc.full_marks_mcq, esc.full_marks_practical,
                            esc.pass_marks_written, esc.pass_marks_mcq, esc.pass_marks_practical
                            FROM exam_subject_config esc JOIN subjects s ON s.id=esc.subject_id
                            WHERE esc.exam_id=:eid AND esc.class_id=:cid ORDER BY s.subject_name');
    $subj->execute([':eid' => $exam_id, ':cid' => $class_id]);
    $subjects = $subj->fetchAll();
}

$user_id = current_user_id();
$is_admin = has_role('super_admin', 'admin');
if (!$is_admin && !empty($subjects)) {
    $entry_level = setting('academic_marks_entry_level', 'all');
    if ($entry_level === 'assigned') {
        $allowed_stmt = $pdo->prepare("SELECT DISTINCT subject_id FROM routine_slots WHERE teacher_id = ? AND class_id = ? AND section_id = ? AND status = 1");
        $allowed_stmt->execute([$user_id, $class_id, $section_id]);
        $allowed_subs = $allowed_stmt->fetchAll(PDO::FETCH_COLUMN);
        $subjects = array_filter($subjects, fn($s) => in_array($s['id'], $allowed_subs));
    } elseif ($entry_level === 'expertise') {
        $allowed_stmt = $pdo->prepare("SELECT subject_id FROM teacher_subjects WHERE teacher_id = ?");
        $allowed_stmt->execute([$user_id]);
        $allowed_subs = $allowed_stmt->fetchAll(PDO::FETCH_COLUMN);
        $subjects = array_filter($subjects, fn($s) => in_array($s['id'], $allowed_subs));
    }
}

// Subject config
$subjectConfig = null;
if ($subject_id && !empty($subjects)) {
    foreach ($subjects as $sub) { if ((int)$sub['id'] === $subject_id) { $subjectConfig = $sub; break; } }
}
if ($subject_id && !$subjectConfig) {
    flash('error', 'Access Denied: You are not permitted to enter marks for this subject.');
    $subject_id = 0;
}

// Students with existing marks
$students = [];
$existingMarks = [];
if ($exam_id && $class_id && $section_id && $subject_id) {
    $stu = $pdo->prepare(
        'SELECT se.student_id, se.roll_number, sp.first_name, sp.last_name
         FROM student_enrollments se
         JOIN student_profiles sp ON sp.user_id=se.student_id
         JOIN academic_sessions ass ON ass.id=se.session_id
         JOIN exams ex ON ex.session_id=ass.id AND ex.id=:eid
         WHERE se.class_id=:cls AND se.section_id=:sec AND se.status="active"
         ORDER BY se.roll_number'
    );
    $stu->execute([':eid' => $exam_id, ':cls' => $class_id, ':sec' => $section_id]);
    $students = $stu->fetchAll();

    $me = $pdo->prepare('SELECT student_id, marks_written, marks_mcq, marks_practical, is_absent FROM marks_entry WHERE exam_id=:eid AND subject_id=:sid AND class_id=:cls');
    $me->execute([':eid' => $exam_id, ':sid' => $subject_id, ':cls' => $class_id]);
    foreach ($me->fetchAll() as $m) $existingMarks[$m['student_id']] = $m;
}

require_once EMS_ROOT . '/includes/header.php';
?>

<h1 class="page-title"><i class="bi bi-pencil-square me-2 text-primary"></i>Mark Entry
  <?php if ($exam): ?><small>— <?= e($exam['exam_name']) ?></small><?php endif; ?>
</h1>

<!-- Filter bar -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
      <div class="col-md-2">
        <label class="form-label small">Exam</label>
        <?php
        $allExams = $pdo->query('SELECT id, exam_name FROM exams ORDER BY id DESC LIMIT 20')->fetchAll();
        ?>
        <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Select Exam —</option>
          <?php foreach ($allExams as $ex): ?>
            <option value="<?= $ex['id'] ?>" <?= $exam_id == $ex['id'] ? 'selected' : '' ?>><?= e($ex['exam_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($exam_id && !empty($examClasses)): ?>
      <div class="col-md-2">
        <label class="form-label small">Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.class_id.value=this.value;this.form.section_id.value=0;this.form.subject_id.value=0;this.form.submit()">
          <option value="0">— Select —</option>
          <?php foreach ($examClasses as $cls): ?>
            <option value="<?= $cls['id'] ?>" <?= $class_id == $cls['id'] ? 'selected' : '' ?>><?= e($cls['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <?php if ($class_id && !empty($sections)): ?>
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
      <?php if ($class_id && !empty($subjects)): ?>
      <div class="col-md-3">
        <label class="form-label small">Subject</label>
        <select name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">— Select Subject —</option>
          <?php foreach ($subjects as $sub): ?>
            <option value="<?= $sub['id'] ?>" <?= $subject_id == $sub['id'] ? 'selected' : '' ?>><?= e($sub['subject_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
      </div>
    </form>
  </div>
</div>

<!-- Marks grid -->
<?php if (!empty($students) && $subjectConfig): ?>
<div class="card">
  <div class="card-header py-3 px-4">
    <div class="d-flex align-items-center justify-content-between">
      <span class="card-title"><?= e($subjectConfig['subject_name']) ?> — Marks Entry</span>
      <div class="small text-muted">
        <?php if ($subjectConfig['full_marks_written']): ?>Written: <strong><?= $subjectConfig['full_marks_written'] ?></strong>&nbsp;<?php endif; ?>
        <?php if ($subjectConfig['full_marks_mcq']): ?>MCQ: <strong><?= $subjectConfig['full_marks_mcq'] ?></strong>&nbsp;<?php endif; ?>
        <?php if ($subjectConfig['full_marks_practical']): ?>Practical: <strong><?= $subjectConfig['full_marks_practical'] ?></strong><?php endif; ?>
      </div>
    </div>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_marks">
    <div class="table-responsive">
      <table class="table table-bordered mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:60px">Roll</th>
            <th>Student Name</th>
            <?php if ($subjectConfig['full_marks_written']): ?><th>Written (<?= $subjectConfig['full_marks_written'] ?>)</th><?php endif; ?>
            <?php if ($subjectConfig['full_marks_mcq']): ?><th>MCQ (<?= $subjectConfig['full_marks_mcq'] ?>)</th><?php endif; ?>
            <?php if ($subjectConfig['full_marks_practical']): ?><th>Practical (<?= $subjectConfig['full_marks_practical'] ?>)</th><?php endif; ?>
            <th>Total</th>
            <th>Absent</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $stu):
            $em = $existingMarks[$stu['student_id']] ?? [];
            $absent = ($em['is_absent'] ?? 0) == 1;
            $total  = ($em['marks_written'] ?? 0) + ($em['marks_mcq'] ?? 0) + ($em['marks_practical'] ?? 0);
          ?>
          <tr>
            <td class="fw-700 text-center"><?= $stu['roll_number'] ?></td>
            <td class="fw-600"><?= e($stu['first_name'] . ' ' . $stu['last_name']) ?></td>
            <?php if ($subjectConfig['full_marks_written']): ?>
            <td><input type="number" name="marks[<?= $stu['student_id'] ?>][written]" class="form-control form-control-sm mark-input"
                       value="<?= $absent ? '' : ($em['marks_written'] ?? '') ?>"
                       max="<?= $subjectConfig['full_marks_written'] ?>" min="0" step="0.5"
                       style="width:75px;" <?= $absent ? 'disabled' : '' ?>></td>
            <?php endif; ?>
            <?php if ($subjectConfig['full_marks_mcq']): ?>
            <td><input type="number" name="marks[<?= $stu['student_id'] ?>][mcq]" class="form-control form-control-sm mark-input"
                       value="<?= $absent ? '' : ($em['marks_mcq'] ?? '') ?>"
                       max="<?= $subjectConfig['full_marks_mcq'] ?>" min="0" step="0.5"
                       style="width:75px;" <?= $absent ? 'disabled' : '' ?>></td>
            <?php endif; ?>
            <?php if ($subjectConfig['full_marks_practical']): ?>
            <td><input type="number" name="marks[<?= $stu['student_id'] ?>][prac]" class="form-control form-control-sm mark-input"
                       value="<?= $absent ? '' : ($em['marks_practical'] ?? '') ?>"
                       max="<?= $subjectConfig['full_marks_practical'] ?>" min="0" step="0.5"
                       style="width:75px;" <?= $absent ? 'disabled' : '' ?>></td>
            <?php endif; ?>
            <td class="fw-700 text-center total-cell"><?= $absent ? 'AB' : ($total > 0 ? number_format($total, 1) : '—') ?></td>
            <td class="text-center">
              <input type="checkbox" name="marks[<?= $stu['student_id'] ?>][absent]" value="1"
                     class="form-check-input absent-cb" <?= $absent ? 'checked' : '' ?>
                     data-row="<?= $stu['student_id'] ?>">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex gap-2 py-3 px-4">
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save All Marks</button>
      <a href="index.php?exam_id=<?= $exam_id ?>" class="btn btn-outline-secondary">Back to Exam</a>
    </div>
  </form>
</div>

<script>
// Toggle row disabled state when absent checkbox clicked
document.querySelectorAll('.absent-cb').forEach(cb => {
  cb.addEventListener('change', function() {
    const row = this.closest('tr');
    row.querySelectorAll('.mark-input').forEach(inp => {
      inp.disabled = this.checked;
      if (this.checked) inp.value = '';
    });
    row.querySelector('.total-cell').textContent = this.checked ? 'AB' : '—';
  });
});
// Auto-sum total as marks are entered
document.querySelectorAll('.mark-input').forEach(inp => {
  inp.addEventListener('input', function() {
    const row  = this.closest('tr');
    const vals = Array.from(row.querySelectorAll('.mark-input')).map(i => parseFloat(i.value) || 0);
    const tot  = vals.reduce((a, b) => a + b, 0);
    row.querySelector('.total-cell').textContent = tot > 0 ? tot.toFixed(1) : '—';
  });
});
</script>
<?php elseif ($exam_id): ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><i class="bi bi-pencil"></i><p>Select Class, Section, and Subject above to begin entering marks.</p>
    <?php if (empty($subjects) && $class_id): ?>
      <p class="small text-warning">No subject configuration found. <a href="schedule.php?exam_id=<?= $exam_id ?>">Set up exam schedule →</a></p>
    <?php endif; ?>
  </div>
</div></div>
<?php else: ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><i class="bi bi-clipboard2"></i><p>Select an exam to begin.</p></div>
</div></div>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
