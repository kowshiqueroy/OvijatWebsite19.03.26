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
        $start_date = $_POST['start_date'] ?? '';
        $end_date   = $_POST['end_date'] ?? '';
        $status     = $_POST['status'] ?? 'draft';

        if ($exam_name && $session_id) {
            if ($id) {
                $pdo->prepare('UPDATE exams SET session_id=?,exam_name=?,exam_type=?,start_date=?,end_date=?,status=? WHERE id=?')
                    ->execute([$session_id,$exam_name,$exam_type,$start_date,$end_date,$status,$id]);
                flash('success', 'Exam updated.');
            } else {
                $pdo->prepare('INSERT INTO exams (session_id,exam_name,exam_type,start_date,end_date,status) VALUES (?,?,?,?,?,?)')
                    ->execute([$session_id,$exam_name,$exam_type,$start_date ?: null,$end_date ?: null,$status]);
                flash('success', "Exam '$exam_name' created.");
            }
        }
    } elseif ($action === 'delete' && has_permission('exams.manage')) {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('DELETE FROM exams WHERE id=:id AND status="draft"')->execute([':id' => $id]);
        flash('success', 'Exam deleted.');
    }
    header('Location: index.php');
    exit;
}

$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);
$exams      = $pdo->prepare(
    'SELECT e.*, COUNT(DISTINCT ecm.class_id) as class_count,
            COUNT(DISTINCT esc.id) as subject_count
     FROM exams e
     LEFT JOIN exam_class_map ecm ON ecm.exam_id=e.id
     LEFT JOIN exam_subject_config esc ON esc.exam_id=e.id
     WHERE e.session_id=:sess
     GROUP BY e.id
     ORDER BY e.start_date DESC, e.id DESC'
);
$exams->execute([':sess' => $session_id]);
$exams = $exams->fetchAll();

$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();

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

        <div class="d-flex gap-2 text-muted small mb-3">
          <span class="me-2"><i class="bi bi-building me-1"></i><?= $exam['class_count'] ?> Classes</span>
          <span><i class="bi bi-book me-1"></i><?= $exam['subject_count'] ?> Subjects</span>
        </div>

        <div class="d-flex gap-2 flex-wrap">
          <a href="schedule.php?exam_id=<?= $exam['id'] ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-calendar-event me-1"></i>Schedule
          </a>
          <?php if (has_permission('marks.enter')): ?>
          <a href="marks.php?exam_id=<?= $exam['id'] ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-pencil-square me-1"></i>Marks
          </a>
          <?php endif; ?>
          <?php if (has_permission('marks.approve') && in_array($exam['status'], ['ongoing','results_published'])): ?>
          <a href="results.php?exam_id=<?= $exam['id'] ?>" class="btn btn-sm btn-outline-info">
            <i class="bi bi-bar-chart me-1"></i>Results
          </a>
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
                    data-confirm="Delete exam '<?= e($exam['exam_name']) ?>'?">
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
          <button type="submit" class="btn btn-primary">Save Exam</button>
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
  if (ex) document.getElementById('ex_sess').value = ex.session_id;
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
