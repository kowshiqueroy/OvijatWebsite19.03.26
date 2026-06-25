<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Consolidated Result Sheet';
$breadcrumbs = ['Reports' => 'index.php', 'Results' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['reports.view']);

$pdo        = db();
$session_id = int_param('session_id', (int)setting('current_session_id', 0), $_GET);
$exam_id    = int_param('exam_id', 0, $_GET);
$class_id   = int_param('class_id', 0, $_GET);
$section_id = int_param('section_id', 0, $_GET);
$print_mode = isset($_GET['print']);

$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$exams    = $session_id
    ? $pdo->query("SELECT id, exam_name FROM exams WHERE session_id=$session_id ORDER BY start_date, id")->fetchAll()
    : [];
$classes  = $exam_id
    ? $pdo->query("SELECT c.id, c.class_name FROM exam_class_map ecm JOIN classes c ON c.id=ecm.class_id WHERE ecm.exam_id=$exam_id ORDER BY c.display_order")->fetchAll()
    : [];
$sections = $class_id
    ? $pdo->prepare('SELECT id, section_name FROM sections WHERE class_id=:c AND status=1 ORDER BY section_name')
    : null;
if ($sections) { $sections->execute([':c' => $class_id]); $sections = $sections->fetchAll(); } else $sections = [];

// Build result data
$students = [];
$subjects = [];
$results  = [];

if ($exam_id && $class_id && $section_id) {
    // Subjects for this exam + class
    $subStmt = $pdo->prepare(
        'SELECT esc.*, s.subject_name
         FROM exam_subject_config esc
         JOIN subjects s ON s.id=esc.subject_id
         WHERE esc.exam_id=:eid AND esc.class_id=:cls
         ORDER BY s.subject_name'
    );
    $subStmt->execute([':eid' => $exam_id, ':cls' => $class_id]);
    $subjects = $subStmt->fetchAll();

    // Students
    $stuStmt = $pdo->prepare(
        'SELECT se.student_id, se.roll_number, sp.first_name, sp.last_name, sp.student_id_no
         FROM student_enrollments se
         JOIN student_profiles sp ON sp.user_id = se.student_id
         JOIN academic_sessions ass ON ass.id = se.session_id
         JOIN exams ex ON ex.session_id = ass.id AND ex.id = :eid
         WHERE se.class_id=:cls AND se.section_id=:sec AND se.status="active"
         ORDER BY se.roll_number'
    );
    $stuStmt->execute([':eid' => $exam_id, ':cls' => $class_id, ':sec' => $section_id]);
    $students = $stuStmt->fetchAll();

    // All marks for exam + class
    $marksStmt = $pdo->prepare(
        'SELECT student_id, subject_id,
                COALESCE(marks_written,0)+COALESCE(marks_mcq,0)+COALESCE(marks_practical,0) as total,
                is_absent
         FROM marks_entry
         WHERE exam_id=:eid AND class_id=:cls'
    );
    $marksStmt->execute([':eid' => $exam_id, ':cls' => $class_id]);
    $marksMap = [];
    foreach ($marksStmt->fetchAll() as $m) $marksMap[$m['student_id']][$m['subject_id']] = $m;

    // Build per-student results
    // Dues checking for results
    $resDuesAllow = (int)setting('result_card_dues_allow', '1');

    // Build per-student results
    foreach ($students as $stu) {
        $sid  = $stu['student_id'];
        $hasDues = student_has_dues($sid);
        $isBlocked = ($hasDues && $resDuesAllow === 0);

        $row  = [
            'stu' => $stu, 
            'marks' => [], 
            'total' => 0, 
            'full' => 0, 
            'failed' => 0, 
            'absent' => 0,
            'has_dues' => $hasDues,
            'is_blocked' => $isBlocked
        ];

        foreach ($subjects as $sub) {
            $fullM  = $sub['full_marks_written'] + $sub['full_marks_mcq'] + $sub['full_marks_practical'];
            $passM  = $sub['pass_marks_written'] + $sub['pass_marks_mcq'] + $sub['pass_marks_practical'];
            $m      = $marksMap[$sid][$sub['subject_id']] ?? null;
            $row['full'] += $fullM;

            if ($isBlocked) {
                $row['marks'][$sub['subject_id']] = ['val' => '***', 'pass' => false];
            } elseif (!$m) {
                $row['marks'][$sub['subject_id']] = ['val' => null, 'pass' => null];
            } elseif ($m['is_absent']) {
                $row['marks'][$sub['subject_id']] = ['val' => 'AB', 'pass' => false];
                $row['absent']++;
                $row['failed']++;
            } else {
                $pass = $m['total'] >= $passM;
                $row['marks'][$sub['subject_id']] = ['val' => $m['total'], 'pass' => $pass];
                $row['total'] += $m['total'];
                if (!$pass) $row['failed']++;
            }
        }

        if ($isBlocked) {
            $row['total'] = 'HELD';
            $row['pct']   = '—';
            $row['grade'] = ['grade' => 'WITHHELD', 'gpa' => 0.00, 'label' => 'Held due to Dues'];
            $row['pass']  = false;
        } else {
            $pct          = $row['full'] > 0 ? ($row['total'] / $row['full']) * 100 : 0;
            $grade        = calculate_grade($pct);
            $row['pct']   = round($pct, 1);
            $row['grade'] = $grade;
            $row['pass']  = $row['failed'] === 0;
        }
        $results[]    = $row;
    }

    // Sort by total desc for merit position (put HELD at bottom)
    usort($results, function($a, $b) {
        if ($a['is_blocked'] && !$b['is_blocked']) return 1;
        if (!$a['is_blocked'] && $b['is_blocked']) return -1;
        if ($a['is_blocked'] && $b['is_blocked']) return 0;
        return $b['total'] <=> $a['total'];
    });
    
    $pos = 1;
    foreach ($results as &$r) { 
        $r['position'] = ($r['pass'] && !$r['is_blocked']) ? $pos++ : '—'; 
    }
    unset($r);
}

$school_name = setting('school_name', 'EMS');

// Find exam + session name for header
$examName = ''; $sessionName = '';
foreach ($exams as $e) if ($e['id'] == $exam_id) { $examName = $e['exam_name']; break; }
foreach ($sessions as $s) if ($s['id'] == $session_id) { $sessionName = $s['session_name']; break; }
$className = '';
foreach ($classes as $c) if ($c['id'] == $class_id) { $className = $c['class_name']; break; }
$sectionName = '';
foreach ($sections as $s) if ($s['id'] == $section_id) { $sectionName = $s['section_name']; break; }

// ── Print mode ────────────────────────────────────────────────────────────────
if ($print_mode && !empty($results)) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Result Sheet — <?= e($examName) ?></title>
<style>
  body { font-family: Arial, sans-serif; font-size: 9px; margin: 0; padding: 10px; }
  h2, h3 { text-align: center; margin: 3px 0; }
  .sub-header { text-align: center; margin-bottom: 8px; color: #555; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 8.5px; }
  th, td { border: 1px solid #333; padding: 2px 4px; }
  th { background: #eee; font-weight: bold; text-align: center; font-size: 8.5px; }
  td { text-align: center; }
  td.name { text-align: left; }
  .pass { background: #dcfce7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .fail { background: #fee2e2 !important; font-weight: bold; color: #b91c1c; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .ab   { background: #fef9c3 !important; color: #92400e; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .total-row { background: #1e293b !important; color: white !important; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .sig { display: flex; justify-content: space-between; margin-top: 25px; page-break-inside: avoid; }
  .sig div { text-align: center; }
  .sig .line { border-top: 1px solid #000; min-width: 140px; padding-top: 3px; font-size: 8.5px; }
  @media print { 
    body { padding: 0; } 
    @page { size: A4 landscape; margin: 6mm 8mm; } 
    .no-print { display: none !important; } 
  }
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:10px;">
  <button onclick="window.print()" style="padding:5px 14px;background:#1a56db;color:#fff;border:none;border-radius:5px;cursor:pointer;">🖨 Print</button>
  <a href="results.php?session_id=<?= $session_id ?>&exam_id=<?= $exam_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>" style="margin-left:8px;padding:5px 14px;background:#6c757d;color:#fff;border-radius:5px;text-decoration:none;">← Back</a>
</div>

<h2><?= e($school_name) ?></h2>
<h3>Result Sheet — <?= e($examName) ?> (<?= e($sessionName) ?>)</h3>
<p class="sub-header"><?= e($className) ?><?= $sectionName ? ' — Section ' . e($sectionName) : '' ?></p>

<table>
  <thead>
    <tr>
      <th rowspan="2" style="width:30px;">Pos</th>
      <th rowspan="2" style="width:30px;">Roll</th>
      <th rowspan="2" style="min-width:120px;text-align:left;">Student Name</th>
      <?php foreach ($subjects as $sub): ?>
        <th style="max-width:60px;"><?= e($sub['subject_name']) ?><br>
          <span style="font-weight:normal;">(<?= $sub['full_marks_written']+$sub['full_marks_mcq']+$sub['full_marks_practical'] ?>)</span>
        </th>
      <?php endforeach; ?>
      <th rowspan="2">Total</th>
      <th rowspan="2">%</th>
      <th rowspan="2">GPA</th>
      <th rowspan="2">Grade</th>
      <th rowspan="2">Result</th>
    </tr>
    <tr>
      <?php foreach ($subjects as $sub): ?>
        <th style="font-size:8px;font-weight:normal;">
          Pass: <?= $sub['pass_marks_written']+$sub['pass_marks_mcq']+$sub['pass_marks_practical'] ?>
        </th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($results as $r): ?>
    <tr>
      <td><?= $r['position'] ?></td>
      <td><?= $r['stu']['roll_number'] ?></td>
      <td class="name"><?= e($r['stu']['first_name'] . ' ' . $r['stu']['last_name']) ?></td>
      <?php foreach ($subjects as $sub):
        $m   = $r['marks'][$sub['subject_id']] ?? ['val' => null, 'pass' => null];
        $cls = $m['val'] === null ? '' : ($m['val'] === 'AB' ? 'ab' : ($m['pass'] ? 'pass' : 'fail'));
      ?>
        <td class="<?= $cls ?>">
          <?= $m['val'] === null ? '—' : $m['val'] ?>
        </td>
      <?php endforeach; ?>
      <td><strong><?= $r['total'] ?></strong></td>
      <td><?= $r['pct'] ?>%</td>
      <td><?= number_format($r['grade']['gpa'], 2) ?></td>
      <td><strong><?= $r['grade']['grade'] ?></strong></td>
      <td class="<?= $r['pass'] ? 'pass' : 'fail' ?>"><strong><?= $r['pass'] ? 'PASS' : 'FAIL' ?></strong></td>
    </tr>
    <?php endforeach; ?>
    <!-- Summary row -->
    <?php
    $passed   = count(array_filter($results, fn($r) => $r['pass']));
    $failed   = count($results) - $passed;
    $passRate = count($results) > 0 ? round($passed / count($results) * 100) : 0;
    ?>
    <tr class="total-row">
      <td colspan="<?= 3 + count($subjects) ?>">
        Total: <?= count($results) ?> &nbsp;|&nbsp;
        Pass: <?= $passed ?> &nbsp;|&nbsp;
        Fail: <?= $failed ?> &nbsp;|&nbsp;
        Pass Rate: <?= $passRate ?>%
      </td>
      <td colspan="5"></td>
    </tr>
  </tbody>
</table>

<div class="sig">
  <div><div class="line">Class Teacher</div></div>
  <div><div class="line">Exam Controller</div></div>
  <div><div class="line">Principal / Headmaster</div></div>
</div>
<p style="text-align:center;margin-top:15px;font-size:8px;color:#888;">
  Generated by <?= e($school_name) ?> EMS on <?= date('d M Y, H:i') ?>
</p>
</body>
</html>
    <?php
    exit;
}

// ── Normal page ───────────────────────────────────────────────────────────────
require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0">
    <i class="bi bi-file-earmark-bar-graph-fill me-2 text-primary"></i>Consolidated Result Sheet
  </h1>
  <?php if (!empty($results)): ?>
  <a href="?session_id=<?= $session_id ?>&exam_id=<?= $exam_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>&print=1"
     target="_blank" class="btn btn-primary">
    <i class="bi bi-printer me-1"></i>Print Result Sheet
  </a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label small">Session</label>
        <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sessions as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $session_id == $s['id'] ? 'selected' : '' ?>>
              <?= e($s['session_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!empty($exams)): ?>
      <div class="col-md-3">
        <label class="form-label small">Exam</label>
        <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">— Select Exam —</option>
          <?php foreach ($exams as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $exam_id == $e['id'] ? 'selected' : '' ?>>
              <?= e($e['exam_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <?php if (!empty($classes)): ?>
      <div class="col-md-2">
        <label class="form-label small">Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">— Select —</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $class_id == $c['id'] ? 'selected' : '' ?>>
              <?= e($c['class_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <?php if (!empty($sections)): ?>
      <div class="col-md-2">
        <label class="form-label small">Section</label>
        <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">— Select —</option>
          <?php foreach ($sections as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $section_id == $s['id'] ? 'selected' : '' ?>>
              <?= e($s['section_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if (!empty($results)): ?>

<!-- Summary stats -->
<?php
$passed   = count(array_filter($results, fn($r) => $r['pass']));
$failed   = count($results) - $passed;
$passRate = count($results) > 0 ? round($passed / count($results) * 100) : 0;
$avgPct   = count($results) > 0 ? round(array_sum(array_column($results, 'pct')) / count($results), 1) : 0;
$aPlus    = count(array_filter($results, fn($r) => $r['grade']['grade'] === 'A+'));
?>
<div class="row g-3 mb-3">
  <div class="col-sm-3"><div class="stat-card primary"><div class="stat-value"><?= count($results) ?></div><div class="stat-label">Total Students</div><i class="bi bi-people stat-icon"></i></div></div>
  <div class="col-sm-3"><div class="stat-card success"><div class="stat-value"><?= $passed ?></div><div class="stat-label">Passed</div><i class="bi bi-check-circle stat-icon"></i></div></div>
  <div class="col-sm-3"><div class="stat-card info"><div class="stat-value"><?= $passRate ?>%</div><div class="stat-label">Pass Rate</div><i class="bi bi-bar-chart stat-icon"></i></div></div>
  <div class="col-sm-3"><div class="stat-card warning"><div class="stat-value"><?= $aPlus ?></div><div class="stat-label">A+ Students</div><i class="bi bi-trophy stat-icon"></i></div></div>
</div>

<!-- Result table -->
<div class="card table-card">
  <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
    <span class="card-title"><?= e($examName) ?> — <?= e($className) ?><?= $sectionName ? ' / Section ' . e($sectionName) : '' ?></span>
    <small class="text-muted">Avg: <?= $avgPct ?>%</small>
  </div>
  <div class="table-responsive">
    <table class="table table-bordered table-sm mb-0" style="font-size:.82rem;">
      <thead class="table-dark">
        <tr>
          <th>Pos</th>
          <th>Roll</th>
          <th>Name</th>
          <?php foreach ($subjects as $sub): ?>
          <th class="text-center" style="max-width:70px;">
            <?= e($sub['subject_name']) ?>
            <div style="font-size:.7rem;opacity:.7;">
              /<?= $sub['full_marks_written']+$sub['full_marks_mcq']+$sub['full_marks_practical'] ?>
            </div>
          </th>
          <?php endforeach; ?>
          <th class="text-center">Total</th>
          <th class="text-center">%</th>
          <th class="text-center">GPA</th>
          <th class="text-center">Grade</th>
          <th class="text-center">Result</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $r): ?>
        <tr class="<?= !$r['pass'] ? 'table-danger' : '' ?> <?= $r['is_blocked'] ? 'opacity-75' : '' ?>">
          <td class="text-center fw-700"><?= $r['position'] ?></td>
          <td class="text-center fw-700 text-primary"><?= $r['stu']['roll_number'] ?></td>
          <td class="fw-600">
            <?= e($r['stu']['first_name'] . ' ' . $r['stu']['last_name']) ?>
            <?php if ($r['is_blocked']): ?>
              <span class="badge bg-danger ms-1" style="font-size:0.65rem;">Dues Block</span>
            <?php endif; ?>
          </td>
          <?php foreach ($subjects as $sub):
            $m   = $r['marks'][$sub['subject_id']] ?? ['val' => null, 'pass' => null];
            $bg  = $m['val'] === null ? '' : ($m['val'] === 'AB' ? 'background:#fef9c3' : ($m['val'] === '***' ? 'background:#fee2e2' : ($m['pass'] ? '' : 'background:#fee2e2')));
          ?>
            <td class="text-center" style="<?= $bg ?>">
              <?php if ($m['val'] === null): ?>
                <span class="text-muted">—</span>
              <?php elseif ($m['val'] === 'AB'): ?>
                <span class="text-warning fw-700">AB</span>
              <?php elseif ($m['val'] === '***'): ?>
                <span class="text-danger fw-700">***</span>
              <?php elseif (!$m['pass']): ?>
                <span class="text-danger fw-700"><?= $m['val'] ?></span>
              <?php else: ?>
                <?= $m['val'] ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td class="text-center fw-700"><?= $r['total'] ?></td>
          <td class="text-center"><?= $r['is_blocked'] ? '—' : $r['pct'] . '%' ?></td>
          <td class="text-center fw-700"><?= $r['is_blocked'] ? '—' : number_format($r['grade']['gpa'], 2) ?></td>
          <td class="text-center">
            <span class="badge bg-<?= $r['is_blocked'] ? 'danger' : ($r['grade']['gpa'] >= 4 ? 'success' : ($r['grade']['gpa'] >= 2 ? 'warning text-dark' : 'danger')) ?>">
              <?= $r['grade']['grade'] ?>
            </span>
          </td>
          <td class="text-center">
            <span class="badge-status badge-<?= $r['is_blocked'] ? 'rejected' : ($r['pass'] ? 'active' : 'rejected') ?>">
              <?= $r['is_blocked'] ? 'WITHHELD' : ($r['pass'] ? 'Pass' : 'Fail') ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="table-dark fw-700">
          <td colspan="3" class="text-end">Summary:</td>
          <td colspan="<?= count($subjects) ?>"></td>
          <td colspan="2" class="text-center">
            Pass <?= $passed ?> / Fail <?= $failed ?>
          </td>
          <td colspan="3" class="text-center"><?= $passRate ?>% Pass Rate</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Grade distribution -->
<div class="card mt-3">
  <div class="card-header py-3 px-4"><span class="card-title">Grade Distribution</span></div>
  <div class="card-body">
    <div class="row g-2">
      <?php
      $grades = ['A+' => 0, 'A' => 0, 'A-' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
      foreach ($results as $r) {
          $g = $r['grade']['grade'];
          if (isset($grades[$g])) $grades[$g]++;
      }
      $colors = ['A+' => 'success', 'A' => 'primary', 'A-' => 'info', 'B' => 'secondary',
                 'C' => 'warning', 'D' => 'warning', 'F' => 'danger'];
      foreach ($grades as $g => $cnt):
      ?>
      <div class="col-auto text-center">
        <div class="fw-700 fs-4 text-<?= $colors[$g] ?? 'secondary' ?>"><?= $cnt ?></div>
        <div class="badge bg-<?= $colors[$g] ?? 'secondary' ?>"><?= $g ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php elseif ($exam_id && $class_id && $section_id): ?>
<div class="card"><div class="card-body">
  <div class="empty-state">
    <i class="bi bi-clipboard2-x"></i>
    <p>No marks entered yet for this exam/class/section. Enter marks first on the
      <a href="../exams/marks.php?exam_id=<?= $exam_id ?>">Marks Entry</a> page.
    </p>
  </div>
</div></div>
<?php else: ?>
<div class="card"><div class="card-body">
  <div class="empty-state">
    <i class="bi bi-file-earmark-bar-graph"></i>
    <p>Select session, exam, class, and section to generate the result sheet.</p>
  </div>
</div></div>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
