<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Exam Schedule';
$breadcrumbs = ['Examinations' => 'index.php', 'Schedule' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['exams.manage']);

$pdo     = db();
$exam_id = int_param('exam_id', 0, $_GET);

if (!$exam_id) { flash('error','Select an exam first.'); redirect('index.php'); }

$exam = $pdo->prepare('SELECT e.*, ass.session_name FROM exams e JOIN academic_sessions ass ON ass.id=e.session_id WHERE e.id=:id');
$exam->execute([':id' => $exam_id]);
$exam = $exam->fetch();
if (!$exam) { flash('error','Exam not found.'); redirect('index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_class') {
        $class_id = int_param('class_id', 0, $_POST);
        if ($class_id) {
            try {
                $pdo->prepare('INSERT IGNORE INTO exam_class_map (exam_id,class_id) VALUES (?,?)')->execute([$exam_id,$class_id]);
                flash('success', 'Class added to exam.');
            } catch (Exception $e) {}
        }
    } elseif ($action === 'remove_class') {
        $class_id = int_param('class_id', 0, $_POST);
        $pdo->prepare('DELETE FROM exam_class_map WHERE exam_id=? AND class_id=?')->execute([$exam_id,$class_id]);
        $pdo->prepare('DELETE FROM exam_subject_config WHERE exam_id=? AND class_id=?')->execute([$exam_id,$class_id]);
        flash('success', 'Class removed from exam.');
    } elseif ($action === 'save_subject_config') {
        $class_id   = int_param('class_id', 0, $_POST);
        $subject_id = int_param('subject_id', 0, $_POST);
        $fm_w  = int_param('full_marks_written', 0, $_POST);
        $fm_m  = int_param('full_marks_mcq', 0, $_POST);
        $fm_p  = int_param('full_marks_practical', 0, $_POST);
        $pm_w  = int_param('pass_marks_written', 0, $_POST);
        $pm_m  = int_param('pass_marks_mcq', 0, $_POST);
        $pm_p  = int_param('pass_marks_practical', 0, $_POST);
        $e_date = $_POST['exam_date'] ?? null;
        $e_time = $_POST['exam_time'] ?? null;

        if ($class_id && $subject_id) {
            $pdo->prepare(
                'INSERT INTO exam_subject_config (exam_id,class_id,subject_id,full_marks_written,full_marks_mcq,full_marks_practical,pass_marks_written,pass_marks_mcq,pass_marks_practical,exam_date,exam_start_time)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE full_marks_written=VALUES(full_marks_written),full_marks_mcq=VALUES(full_marks_mcq),full_marks_practical=VALUES(full_marks_practical),pass_marks_written=VALUES(pass_marks_written),pass_marks_mcq=VALUES(pass_marks_mcq),pass_marks_practical=VALUES(pass_marks_practical),exam_date=VALUES(exam_date),exam_start_time=VALUES(exam_start_time)'
            )->execute([$exam_id,$class_id,$subject_id,$fm_w,$fm_m,$fm_p,$pm_w,$pm_m,$pm_p,$e_date,$e_time]);
            flash('success', 'Subject config saved.');
        }
    }
    header("Location: schedule.php?exam_id=$exam_id");
    exit;
}

$examClasses = $pdo->prepare('SELECT c.id, c.class_name FROM exam_class_map ecm JOIN classes c ON c.id=ecm.class_id WHERE ecm.exam_id=:eid ORDER BY c.display_order');
$examClasses->execute([':eid' => $exam_id]);
$examClasses = $examClasses->fetchAll();

$examClassIds = array_column($examClasses, 'id');

$subjectConfigs = [];
if (!empty($examClassIds)) {
    $esc = $pdo->query("SELECT esc.*, esc.exam_start_time AS exam_time, s.subject_name FROM exam_subject_config esc JOIN subjects s ON s.id=esc.subject_id WHERE esc.exam_id=$exam_id ORDER BY s.subject_name");
    foreach ($esc->fetchAll() as $row) $subjectConfigs[$row['class_id']][] = $row;
}

$allClasses  = $pdo->query('SELECT id, class_name FROM classes WHERE status=1 ORDER BY display_order')->fetchAll();
$allSubjects = $pdo->query('SELECT id, subject_name, has_mcq, has_practical FROM subjects WHERE status=1 ORDER BY subject_name')->fetchAll();

$activeClassId = int_param('class', reset($examClassIds) ?: 0, $_GET);

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0">
    <i class="bi bi-calendar-event me-2 text-primary"></i>Schedule — <?= e($exam['exam_name']) ?>
  </h1>
  <div class="d-flex gap-2">
    <a href="marks.php?exam_id=<?= $exam_id ?>" class="btn btn-success btn-sm"><i class="bi bi-pencil-square me-1"></i>Enter Marks</a>
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <!-- Classes panel -->
  <div class="col-md-3">
    <div class="card">
      <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="card-title">Classes</span>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach ($examClasses as $cls): ?>
        <div class="list-group-item d-flex align-items-center justify-content-between py-2">
          <a href="?exam_id=<?= $exam_id ?>&class=<?= $cls['id'] ?>"
             class="fw-600 text-decoration-none <?= $activeClassId == $cls['id'] ? 'text-primary' : '' ?>">
            <?= e($cls['class_name']) ?>
            <small class="text-muted ms-1">(<?= count($subjectConfigs[$cls['id']] ?? []) ?> subj)</small>
          </a>
          <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove_class">
            <input type="hidden" name="class_id" value="<?= $cls['id'] ?>">
            <button type="submit" class="btn btn-xs btn-outline-danger" style="padding:.1rem .35rem;font-size:.7rem;"
                    data-confirm="Remove class from this exam?"><i class="bi bi-x"></i></button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card-footer p-2">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_class">
          <div class="d-flex gap-1">
            <select name="class_id" class="form-select form-select-sm">
              <option value="">+ Add Class</option>
              <?php foreach ($allClasses as $cls):
                if (in_array($cls['id'], $examClassIds)) continue;
              ?>
                <option value="<?= $cls['id'] ?>"><?= e($cls['class_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-plus-lg"></i></button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Subject config -->
  <div class="col-md-9">
    <?php if ($activeClassId && in_array($activeClassId, $examClassIds)): ?>
    <?php
    $activeCls = null;
    foreach ($examClasses as $c) { if ((int)$c['id'] === $activeClassId) { $activeCls = $c; break; } }
    $classCfg  = $subjectConfigs[$activeClassId] ?? [];
    $cfgBySubj = [];
    foreach ($classCfg as $c) $cfgBySubj[$c['subject_id']] = $c;
    ?>
    <div class="card">
      <div class="card-header py-3 px-4">
        <span class="card-title">Subject Schedule — <?= e($activeCls['class_name'] ?? '') ?></span>
      </div>

      <?php if (!empty($classCfg)): ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 small">
          <thead><tr><th>Subject</th><th>Written</th><th>MCQ</th><th>Practical</th><th>Date</th><th>Time</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($classCfg as $cfg): ?>
            <tr>
              <td class="fw-600"><?= e($cfg['subject_name']) ?></td>
              <td><?= $cfg['full_marks_written'] ?> <small class="text-muted">(pass: <?= $cfg['pass_marks_written'] ?>)</small></td>
              <td><?= $cfg['full_marks_mcq'] ?: '—' ?></td>
              <td><?= $cfg['full_marks_practical'] ?: '—' ?></td>
              <td><?= fmt_date($cfg['exam_date']) ?></td>
              <td><?= $cfg['exam_time'] ? substr($cfg['exam_time'],0,5) : '—' ?></td>
              <td>
                <button class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:.1rem .35rem;"
                        data-bs-toggle="modal" data-bs-target="#subjectModal"
                        onclick="setSubjForm(<?= htmlspecialchars(json_encode($cfg), ENT_QUOTES) ?>, <?= $activeClassId ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <div class="card-footer p-3">
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#subjectModal"
                onclick="setSubjForm(null, <?= $activeClassId ?>)">
          <i class="bi bi-plus-lg me-1"></i>Add Subject to Schedule
        </button>
      </div>
    </div>
    <?php else: ?>
    <div class="card"><div class="card-body">
      <div class="empty-state"><i class="bi bi-calendar-week"></i>
        <p><?= empty($examClasses) ? 'Add classes to this exam first.' : 'Select a class from the left.' ?></p>
      </div>
    </div></div>
    <?php endif; ?>
  </div>
</div>

<!-- Subject Config Modal -->
<div class="modal fade" id="subjectModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_subject_config">
        <input type="hidden" name="class_id" id="sc_cls" value="<?= $activeClassId ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="subjectModalTitle">Add Subject Schedule</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Subject <span class="text-danger">*</span></label>
            <select name="subject_id" id="sc_subj" class="form-select" required>
              <option value="">— Select Subject —</option>
              <?php foreach ($allSubjects as $sub): ?>
                <option value="<?= $sub['id'] ?>"><?= e($sub['subject_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Full Marks — Written</label>
              <input type="number" name="full_marks_written" id="sc_fw" class="form-control" min="0" value="100">
            </div>
            <div class="col-md-4">
              <label class="form-label">Full Marks — MCQ</label>
              <input type="number" name="full_marks_mcq" id="sc_fm" class="form-control" min="0" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Full Marks — Practical</label>
              <input type="number" name="full_marks_practical" id="sc_fp" class="form-control" min="0" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Pass Marks — Written</label>
              <input type="number" name="pass_marks_written" id="sc_pw" class="form-control" min="0" value="33">
            </div>
            <div class="col-md-4">
              <label class="form-label">Pass Marks — MCQ</label>
              <input type="number" name="pass_marks_mcq" id="sc_pm" class="form-control" min="0" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Pass Marks — Practical</label>
              <input type="number" name="pass_marks_practical" id="sc_pp" class="form-control" min="0" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Exam Date</label>
              <input type="date" name="exam_date" id="sc_date" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Start Time</label>
              <input type="time" name="exam_time" id="sc_time" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setSubjForm(cfg, classId) {
  document.getElementById('subjectModalTitle').textContent = cfg ? 'Edit Subject Schedule' : 'Add Subject Schedule';
  document.getElementById('sc_cls').value   = classId;
  document.getElementById('sc_subj').value  = cfg ? cfg.subject_id : '';
  document.getElementById('sc_fw').value    = cfg ? cfg.full_marks_written : 100;
  document.getElementById('sc_fm').value    = cfg ? cfg.full_marks_mcq : 0;
  document.getElementById('sc_fp').value    = cfg ? cfg.full_marks_practical : 0;
  document.getElementById('sc_pw').value    = cfg ? cfg.pass_marks_written : 33;
  document.getElementById('sc_pm').value    = cfg ? cfg.pass_marks_mcq : 0;
  document.getElementById('sc_pp').value    = cfg ? cfg.pass_marks_practical : 0;
  document.getElementById('sc_date').value  = cfg ? (cfg.exam_date || '') : '';
  document.getElementById('sc_time').value  = cfg ? (cfg.exam_time || '') : '';
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
