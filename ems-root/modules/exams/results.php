<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Exam Results';
$breadcrumbs = ['Examinations' => 'index.php', 'Results' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['marks.approve']);

$pdo     = db();
$exam_id = int_param('exam_id', 0, $_GET);
$class_id   = int_param('class_id', 0, $_GET);
$section_id = int_param('section_id', 0, $_GET);

// Publish/unpublish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish') {
    csrf_check();
    $eid = int_param('exam_id', 0, $_POST);
    $pdo->prepare("UPDATE exams SET status='results_published' WHERE id=?")->execute([$eid]);
    flash('success', 'Results published.');
    header("Location: results.php?exam_id=$eid&class_id=$class_id&section_id=$section_id");
    exit;
}

$exam = null;
if ($exam_id) {
    $s = $pdo->prepare('SELECT e.*, ass.session_name FROM exams e JOIN academic_sessions ass ON ass.id=e.session_id WHERE e.id=:id');
    $s->execute([':id' => $exam_id]);
    $exam = $s->fetch();
}

$examClasses = [];
if ($exam_id) {
    $ec = $pdo->prepare('SELECT c.id, c.class_name FROM exam_class_map ecm JOIN classes c ON c.id=ecm.class_id WHERE ecm.exam_id=:eid ORDER BY c.display_order');
    $ec->execute([':eid' => $exam_id]);
    $examClasses = $ec->fetchAll();
}

$sections = [];
if ($class_id) {
    $sec = $pdo->prepare('SELECT id, section_name FROM sections WHERE class_id=:c AND status=1 ORDER BY section_name');
    $sec->execute([':c' => $class_id]);
    $sections = $sec->fetchAll();
}

// Build result tabulation
$results   = [];
$subjects  = [];
if ($exam_id && $class_id && $section_id) {
    // Get subjects for this exam+class
    $subj = $pdo->prepare('SELECT esc.*, s.subject_name FROM exam_subject_config esc JOIN subjects s ON s.id=esc.subject_id WHERE esc.exam_id=:eid AND esc.class_id=:cls ORDER BY s.subject_name');
    $subj->execute([':eid'=>$exam_id,':cls'=>$class_id]);
    $subjects = $subj->fetchAll();

    // Get students
    $stu = $pdo->prepare(
        'SELECT se.student_id, se.roll_number, sp.first_name, sp.last_name
         FROM student_enrollments se JOIN student_profiles sp ON sp.user_id=se.student_id
         JOIN academic_sessions ass ON ass.id=se.session_id
         JOIN exams ex ON ex.session_id=ass.id AND ex.id=:eid
         WHERE se.class_id=:cls AND se.section_id=:sec AND se.status="active"
         ORDER BY se.roll_number'
    );
    $stu->execute([':eid'=>$exam_id,':cls'=>$class_id,':sec'=>$section_id]);
    $students = $stu->fetchAll();

    // Get all marks for this exam+class
    $marks = $pdo->prepare('SELECT student_id, subject_id, marks_written, marks_mcq, marks_practical, is_absent FROM marks_entry WHERE exam_id=:eid AND class_id=:cls');
    $marks->execute([':eid'=>$exam_id,':cls'=>$class_id]);
    $marksByStudent = [];
    foreach ($marks->fetchAll() as $m) $marksByStudent[$m['student_id']][$m['subject_id']] = $m;

    foreach ($students as $stu) {
        $sid = $stu['student_id'];
        $row = ['student' => $stu, 'subjects' => [], 'total' => 0, 'full_total' => 0, 'failed' => 0, 'absent' => 0];

        foreach ($subjects as $sub) {
            $subjId   = $sub['subject_id'];
            $m        = $marksByStudent[$sid][$subjId] ?? null;
            $fullMarks = $sub['full_marks_written'] + $sub['full_marks_mcq'] + $sub['full_marks_practical'];
            $passMarks = $sub['pass_marks_written'] + $sub['pass_marks_mcq'] + $sub['pass_marks_practical'];

            if (!$m) {
                $row['subjects'][$subjId] = ['marks' => null, 'status' => 'not_entered', 'full' => $fullMarks];
                $row['full_total'] += $fullMarks;
                continue;
            }

            if ($m['is_absent']) {
                $row['subjects'][$subjId] = ['marks' => 'AB', 'status' => 'absent', 'full' => $fullMarks];
                $row['absent']++;
                $row['failed']++;
            } else {
                $total = ($m['marks_written'] ?? 0) + ($m['marks_mcq'] ?? 0) + ($m['marks_practical'] ?? 0);
                $pass  = $total >= $passMarks;
                $row['subjects'][$subjId] = ['marks' => $total, 'status' => $pass ? 'pass' : 'fail', 'full' => $fullMarks];
                $row['total']       += $total;
                $row['full_total']  += $fullMarks;
                if (!$pass) $row['failed']++;
            }
        }

        $pct   = $row['full_total'] > 0 ? ($row['total'] / $row['full_total']) * 100 : 0;
        $grade = calculate_grade($pct);
        if ($row['failed'] > 0) {
            $grade = ['grade' => 'F', 'gpa' => 0.00, 'label' => 'Fail'];
        }
        $row['percentage'] = $pct;
        $row['grade']      = $grade;
        $row['passed']     = $row['failed'] === 0;
        $results[] = $row;
    }

    // Sort by total desc (merit list)
    usort($results, fn($a, $b) => $b['total'] <=> $a['total']);
    // Assign position
    $pos = 1;
    foreach ($results as &$r) { $r['position'] = $r['passed'] ? $pos++ : '—'; }
    unset($r);
}

$allExams = $pdo->query('SELECT id, exam_name FROM exams ORDER BY id DESC LIMIT 20')->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0">
    <i class="bi bi-bar-chart-fill me-2 text-primary"></i>Results
    <?php if ($exam): ?><small>— <?= e($exam['exam_name']) ?></small><?php endif; ?>
  </h1>
  <div class="d-flex gap-2">
    <?php if ($exam && $exam['status'] !== 'results_published'): ?>
    <form method="POST" class="d-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="publish">
      <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
      <button type="submit" class="btn btn-success btn-sm"
              data-confirm="Publish results? Students will be able to view them.">
        <i class="bi bi-megaphone-fill me-1"></i>Publish Results
      </button>
    </form>
    <?php elseif ($exam && $exam['status'] === 'results_published'): ?>
    <span class="badge bg-success fs-6">Results Published</span>
    <?php endif; ?>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Print</button>
  </div>
</div>

<!-- Filter bar -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small">Exam</label>
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
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Select —</option>
          <?php foreach ($examClasses as $cls): ?>
            <option value="<?= $cls['id'] ?>" <?= $class_id == $cls['id'] ? 'selected' : '' ?>><?= e($cls['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($class_id && !empty($sections)): ?>
      <div class="col-md-2">
        <label class="form-label small">Section</label>
        <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Select —</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>" <?= $section_id == $sec['id'] ? 'selected' : '' ?>><?= e($sec['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if (!empty($results)): ?>
<!-- Result summary -->
<?php
$totalStudents = count($results);
$passed = count(array_filter($results, fn($r) => $r['passed']));
$failed = $totalStudents - $passed;
$passRate = $totalStudents > 0 ? round($passed/$totalStudents*100) : 0;
?>
<div class="row g-3 mb-3">
  <div class="col-sm-3"><div class="stat-card primary"><div class="stat-value"><?= $totalStudents ?></div><div class="stat-label">Total Students</div><i class="bi bi-people stat-icon"></i></div></div>
  <div class="col-sm-3"><div class="stat-card success"><div class="stat-value"><?= $passed ?></div><div class="stat-label">Passed</div><i class="bi bi-check-circle stat-icon"></i></div></div>
  <div class="col-sm-3"><div class="stat-card danger"><div class="stat-value"><?= $failed ?></div><div class="stat-label">Failed</div><i class="bi bi-x-circle stat-icon"></i></div></div>
  <div class="col-sm-3"><div class="stat-card info"><div class="stat-value"><?= $passRate ?>%</div><div class="stat-label">Pass Rate</div><i class="bi bi-bar-chart stat-icon"></i></div></div>
</div>

<!-- Result table -->
<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-bordered table-sm mb-0" style="font-size:.82rem;">
      <thead class="table-dark">
        <tr>
          <th rowspan="2" class="text-center">Pos</th>
          <th rowspan="2">Roll</th>
          <th rowspan="2">Student</th>
          <?php foreach ($subjects as $sub): ?>
            <th class="text-center" style="max-width:80px;"><?= e($sub['subject_name']) ?><br><small>(<?= $sub['full_marks_written']+$sub['full_marks_mcq']+$sub['full_marks_practical'] ?>)</small></th>
          <?php endforeach; ?>
          <th rowspan="2" class="text-center">Total</th>
          <th rowspan="2" class="text-center">%</th>
          <th rowspan="2" class="text-center">Grade</th>
          <th rowspan="2" class="text-center">Result</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $r): ?>
        <tr class="<?= !$r['passed'] ? 'table-danger' : '' ?>">
          <td class="text-center fw-700"><?= $r['position'] ?></td>
          <td class="fw-700 text-center"><?= $r['student']['roll_number'] ?></td>
          <td class="fw-600"><?= e($r['student']['first_name'] . ' ' . $r['student']['last_name']) ?></td>
          <?php foreach ($subjects as $sub):
            $sm = $r['subjects'][$sub['subject_id']] ?? null;
            $val = $sm ? $sm['marks'] : '?';
            $cls = '';
            if ($sm && $sm['status'] === 'fail') $cls = 'text-danger fw-700';
            elseif ($sm && $sm['status'] === 'pass') $cls = 'text-success';
          ?>
            <td class="text-center <?= $cls ?>"><?= $val === null ? '<small class="text-muted">—</small>' : $val ?></td>
          <?php endforeach; ?>
          <td class="text-center fw-700"><?= $r['total'] ?></td>
          <td class="text-center"><?= number_format($r['percentage'], 1) ?>%</td>
          <td class="text-center">
            <span class="badge bg-<?= $r['grade']['gpa'] >= 4 ? 'success' : ($r['grade']['gpa'] >= 2 ? 'warning' : 'danger') ?>">
              <?= $r['grade']['grade'] ?>
            </span>
          </td>
          <td class="text-center">
            <span class="badge-status badge-<?= $r['passed'] ? 'active' : 'rejected' ?>">
              <?= $r['passed'] ? 'Pass' : 'Fail' ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($exam_id): ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><i class="bi bi-bar-chart"></i>
    <p>Select Class and Section to view results. Make sure marks are entered for at least one subject.</p>
  </div>
</div></div>
<?php else: ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><i class="bi bi-clipboard2-data"></i><p>Select an exam to view results.</p></div>
</div></div>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
