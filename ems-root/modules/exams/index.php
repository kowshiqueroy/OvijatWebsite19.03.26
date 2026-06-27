<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Examinations';
$breadcrumbs = ['Examinations' => null, 'Exam List' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['exams.view']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save' && has_permission('exams.manage')) {
        $id         = int_param('id', 0, $_POST);
        $session_id = int_param('session_id', 0, $_POST);
        $exam_name  = trim($_POST['exam_name'] ?? '');
        $exam_type  = $_POST['exam_type'] ?? 'other';
        $scope      = $_POST['scope'] ?? 'all_classes';
        $start_date = $_POST['start_date'] ?: null;
        $end_date   = $_POST['end_date']   ?: null;
        $status     = $_POST['status'] ?? 'draft';
        $classIds   = array_map('intval', (array)($_POST['class_ids'] ?? []));

        if ($exam_name && $session_id) {
            if ($id) {
                $pdo->prepare('UPDATE exams SET session_id=?,exam_name=?,exam_type=?,scope=?,start_date=?,end_date=?,status=? WHERE id=?')
                    ->execute([$session_id,$exam_name,$exam_type,$scope,$start_date,$end_date,$status,$id]);
                // Refresh class map if selective
                if ($scope === 'selective') {
                    $pdo->prepare('DELETE FROM exam_class_map WHERE exam_id=?')->execute([$id]);
                    $ins = $pdo->prepare('INSERT IGNORE INTO exam_class_map (exam_id,class_id) VALUES (?,?)');
                    foreach ($classIds as $cid) if ($cid) $ins->execute([$id,$cid]);
                }
                flash('success', 'Exam updated.');
            } else {
                $pdo->prepare('INSERT INTO exams (session_id,exam_name,exam_type,scope,start_date,end_date,status) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$session_id,$exam_name,$exam_type,$scope,$start_date,$end_date,$status]);
                $newId = (int)$pdo->lastInsertId();
                // Class mapping
                if ($scope === 'all_classes') {
                    // Map all classes in the session
                    $allClasses = $pdo->prepare('SELECT DISTINCT class_id FROM class_subjects WHERE session_id=?');
                    $allClasses->execute([$session_id]);
                    $ins = $pdo->prepare('INSERT IGNORE INTO exam_class_map (exam_id,class_id) VALUES (?,?)');
                    foreach ($allClasses->fetchAll(PDO::FETCH_COLUMN) as $cid) $ins->execute([$newId,$cid]);
                } else {
                    $ins = $pdo->prepare('INSERT IGNORE INTO exam_class_map (exam_id,class_id) VALUES (?,?)');
                    foreach ($classIds as $cid) if ($cid) $ins->execute([$newId,$cid]);
                }
                flash('success', "Exam '$exam_name' created.");
            }
        }
    } elseif ($action === 'delete' && has_permission('exams.manage')) {
        $id  = int_param('id', 0, $_POST);
        $exam = $pdo->prepare('SELECT status FROM exams WHERE id=?');
        $exam->execute([$id]);
        $examStatus = $exam->fetchColumn();
        if ($examStatus === 'results_published') {
            flash('error', 'Cannot delete an exam with published results.');
        } elseif ($examStatus === 'draft') {
            $pdo->prepare('UPDATE exams SET deleted_at=NOW(), deleted_by=? WHERE id=?')
                ->execute([$_SESSION['user_id']??null, $id]);
            flash('success', 'Exam moved to deleted items.');
        } else {
            flash('error', 'Only draft exams can be deleted. Change status to draft first.');
        }

    } elseif ($action === 'auto_routine' && has_permission('exams.manage')) {
        // Auto-generate exam routine slots
        $examId    = int_param('exam_id', 0, $_POST);
        $startDate = $_POST['routine_start_date'] ?? date('Y-m-d');
        $startTime = $_POST['routine_start_time'] ?? '10:00';
        $duration  = int_param('routine_duration', 180, $_POST); // minutes per subject
        $gapMins   = int_param('routine_gap', 0, $_POST);       // gap between subjects (same day)

        // Get all exam subject configs
        $configs = $pdo->prepare('SELECT DISTINCT esc.class_id, esc.subject_id FROM exam_subject_config esc WHERE esc.exam_id=?');
        $configs->execute([$examId]);
        $configs = $configs->fetchAll();

        // Simple sequential: one subject per day, all classes together
        $ins = $pdo->prepare('INSERT INTO exam_routine (exam_id, class_id, subject_id, exam_date, start_time, end_time)
                               VALUES (?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE exam_date=VALUES(exam_date), start_time=VALUES(start_time), end_time=VALUES(end_time)');
        $dt = new DateTime($startDate);
        $perDay = max(1, int_param('subjects_per_day', 1, $_POST));
        $daySubjectCount = 0;
        $startT = new DateTime($startDate . ' ' . $startTime);
        $prevSubjectId = null;

        // Group by subject_id (same subject on same day for all classes)
        $bySubject = [];
        foreach ($configs as $c) $bySubject[$c['subject_id']][] = $c['class_id'];

        $slotDate   = clone $dt;
        $slotCount  = 0;
        foreach ($bySubject as $subjectId => $classIds) {
            $slotStr = $slotDate->format('Y-m-d');
            $sTime   = $startTime;
            $eTime   = date('H:i', strtotime($sTime . ' +' . $duration . ' minutes'));
            foreach ($classIds as $classId) {
                $ins->execute([$examId, $classId, $subjectId, $slotStr, $sTime, $eTime]);
            }
            $daySubjectCount++;
            if ($daySubjectCount >= $perDay) {
                $slotDate->modify('+1 day');
                $daySubjectCount = 0;
            }
            $slotCount++;
        }
        flash('success', "Auto-generated exam routine: $slotCount slot(s).");
    }
    header('Location: index.php');
    exit;
}

$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);
$exams      = $pdo->prepare(
    'SELECT e.*, COUNT(DISTINCT ecm.class_id) AS class_count,
            COUNT(DISTINCT esc.id) AS subject_count,
            COUNT(DISTINCT er.id) AS routine_count
     FROM exams e
     LEFT JOIN exam_class_map ecm ON ecm.exam_id=e.id
     LEFT JOIN exam_subject_config esc ON esc.exam_id=e.id
     LEFT JOIN exam_routine er ON er.exam_id=e.id
     WHERE e.session_id=:sess AND e.deleted_at IS NULL
     GROUP BY e.id
     ORDER BY e.start_date DESC, e.id DESC'
);
$exams->execute([':sess' => $session_id]);
$exams = $exams->fetchAll();

$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions WHERE deleted_at IS NULL ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id, class_name, class_level FROM classes WHERE deleted_at IS NULL AND status=1 ORDER BY display_order, class_numeric')->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-clipboard2-check-fill me-2 text-primary"></i>Examinations</h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" onchange="location='?session_id='+this.value" style="width:auto;">
      <?php foreach ($sessions as $sess): ?>
        <option value="<?= $sess['id'] ?>" <?= $session_id == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (has_permission('exams.manage')): ?>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#examModal" onclick="setExamForm(null)">
        <i class="bi bi-plus-lg me-1"></i>New Exam
      </button>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($exams)): ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><i class="bi bi-clipboard2-x"></i><p>No exams yet for this session.</p></div>
</div></div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($exams as $exam):
    $statusColors = ['draft'=>'draft','scheduled'=>'pending','ongoing'=>'warning','results_published'=>'active'];
    $statusColor  = $statusColors[$exam['status']] ?? 'draft';
  ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-2">
          <div>
            <h6 class="fw-700 mb-0"><?= e($exam['exam_name']) ?></h6>
            <span class="text-muted small text-capitalize"><?= e(str_replace('_',' ',$exam['exam_type'])) ?></span>
          </div>
          <span class="badge-status badge-<?= $statusColor ?>"><?= ucfirst(str_replace('_',' ',$exam['status'])) ?></span>
        </div>

        <div class="d-flex gap-3 mb-3 small text-muted">
          <span><i class="bi bi-calendar me-1"></i><?= fmt_date($exam['start_date']) ?> – <?= fmt_date($exam['end_date']) ?></span>
        </div>

        <div class="d-flex flex-wrap gap-3 mb-3 small text-muted">
          <span><i class="bi bi-building me-1"></i><?= $exam['class_count'] ?> class(es)</span>
          <span><i class="bi bi-book me-1"></i><?= $exam['subject_count'] ?> subjects</span>
          <span><i class="bi bi-calendar3 me-1"></i><?= $exam['routine_count'] ?> routine slots</span>
          <span class="badge bg-<?= $exam['scope']==='selective'?'warning text-dark':'light text-dark' ?>"><?= $exam['scope']==='selective'?'Selective':'All Classes' ?></span>
        </div>

        <div class="d-flex gap-2 flex-wrap">
          <a href="schedule.php?exam_id=<?= $exam['id'] ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-calendar-event me-1"></i>Routine
          </a>
          <?php if (has_permission('marks.enter') && $exam['status'] !== 'results_published'): ?>
          <a href="marks.php?exam_id=<?= $exam['id'] ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-pencil-square me-1"></i>Marks
          </a>
          <?php elseif ($exam['status'] === 'results_published'): ?>
          <span class="btn btn-sm btn-secondary disabled" title="Results published — marks locked">
            <i class="bi bi-lock me-1"></i>Marks Locked
          </span>
          <?php endif; ?>
          <?php if (has_permission('marks.approve')): ?>
          <a href="results.php?exam_id=<?= $exam['id'] ?>" class="btn btn-sm btn-outline-info">
            <i class="bi bi-bar-chart me-1"></i>Results
          </a>
          <?php endif; ?>
          <?php if ($exam['routine_count'] == 0 && has_permission('exams.manage')): ?>
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#autoRoutineModal"
                  onclick="document.getElementById('ar_exam_id').value=<?= $exam['id'] ?>">
            <i class="bi bi-magic me-1"></i>Auto Routine
          </button>
          <?php endif; ?>
          <?php if (has_permission('exams.manage')): ?>
          <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#examModal"
                  onclick="setExamForm(<?= htmlspecialchars(json_encode($exam), ENT_QUOTES) ?>)">
            <i class="bi bi-pencil"></i>
          </button>
          <?php if ($exam['status'] === 'draft'): ?>
          <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $exam['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"
                    data-soft-delete="<?= e($exam['exam_name']) ?>"
                    data-soft-delete-warn="All marks, seat plans and subject configs for this exam will also be hidden."
                    data-form-id="delex<?= $exam['id'] ?>"
                    id="delex<?= $exam['id'] ?>">
              <i class="bi bi-trash"></i>
            </button>
          </form>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Exam Modal -->
<div class="modal fade" id="examModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="ex_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="examModalTitle">New Exam</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Session <span class="text-danger">*</span></label>
            <select name="session_id" id="ex_sess" class="form-select">
              <?php foreach ($sessions as $sess): ?>
                <option value="<?= $sess['id'] ?>" <?= $session_id == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Exam Name <span class="text-danger">*</span></label>
            <input type="text" name="exam_name" id="ex_name" class="form-control" placeholder="e.g. Half-Yearly Exam 2026" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Exam Type</label>
            <select name="exam_type" id="ex_type" class="form-select">
              <option value="terminal">Terminal</option>
              <option value="monthly">Monthly Test</option>
              <option value="midterm">Midterm</option>
              <option value="annual">Annual</option>
              <option value="final">Final</option>
              <option value="model_test">Model Test</option>
              <option value="scholarship">Scholarship Screening</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Start Date</label>
              <input type="date" name="start_date" id="ex_start" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label">End Date</label>
              <input type="date" name="end_date" id="ex_end" class="form-control">
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Scope</label>
            <select name="scope" id="ex_scope" class="form-select" onchange="toggleClassPicker(this.value)">
              <option value="all_classes">All Classes in Session</option>
              <option value="selective">Selective Classes Only</option>
            </select>
          </div>
          <div class="mt-3" id="ex_class_picker" style="display:none;">
            <label class="form-label">Select Classes <span class="text-danger">*</span></label>
            <div class="border rounded p-2" style="max-height:200px;overflow-y:auto;">
              <?php foreach ($classes as $cls): ?>
              <div class="form-check">
                <input type="checkbox" class="form-check-input ex-cls-check" name="class_ids[]" value="<?= $cls['id'] ?>" id="excls<?= $cls['id'] ?>">
                <label class="form-check-label small" for="excls<?= $cls['id'] ?>"><?= e($cls['class_name']) ?></label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Status</label>
            <select name="status" id="ex_status" class="form-select">
              <option value="draft">Draft</option>
              <option value="scheduled">Scheduled</option>
              <option value="ongoing">Ongoing</option>
              <option value="results_published">Results Published</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Exam</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Auto Routine Modal -->
<div class="modal fade" id="autoRoutineModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action"  value="auto_routine">
        <input type="hidden" name="exam_id" id="ar_exam_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-magic me-2"></i>Auto-Generate Exam Routine</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            Generates exam slots sequentially starting from the given date.
            One subject per day (or multiple if you set subjects per day).
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Start Date</label>
              <input type="date" name="routine_start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Start Time</label>
              <input type="time" name="routine_start_time" class="form-control" value="10:00">
            </div>
            <div class="col-md-6">
              <label class="form-label">Duration per Subject (minutes)</label>
              <input type="number" name="routine_duration" class="form-control" value="180" min="30" max="480">
            </div>
            <div class="col-md-6">
              <label class="form-label">Subjects per Day</label>
              <input type="number" name="subjects_per_day" class="form-control" value="1" min="1" max="4">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-magic me-1"></i>Generate Routine
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setExamForm(ex) {
  document.getElementById('examModalTitle').textContent = ex ? 'Edit Exam' : 'New Exam';
  document.getElementById('ex_id').value     = ex ? ex.id : 0;
  document.getElementById('ex_name').value   = ex ? ex.exam_name : '';
  document.getElementById('ex_type').value   = ex ? ex.exam_type : 'terminal';
  document.getElementById('ex_start').value  = ex ? (ex.start_date || '') : '';
  document.getElementById('ex_end').value    = ex ? (ex.end_date || '') : '';
  document.getElementById('ex_status').value = ex ? ex.status : 'draft';
  document.getElementById('ex_scope').value  = ex ? (ex.scope || 'all_classes') : 'all_classes';
  if (ex) document.getElementById('ex_sess').value = ex.session_id;
  toggleClassPicker(document.getElementById('ex_scope').value);
  // Uncheck all class checkboxes
  document.querySelectorAll('.ex-cls-check').forEach(cb => cb.checked = false);
}
function toggleClassPicker(scope) {
  const el = document.getElementById('ex_class_picker');
  if (el) el.style.display = scope === 'selective' ? '' : 'none';
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
