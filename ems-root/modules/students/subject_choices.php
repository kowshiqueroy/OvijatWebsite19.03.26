<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Student Subject Choices';
$breadcrumbs = ['Students' => 'index.php', 'Subject Choices' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.edit']);

$pdo = db();
$currentSessId = (int)setting('current_session_id', 0);
$sessId  = int_param('session_id', $currentSessId, $_GET);
$classId = int_param('class_id',   0, $_GET);
$sectId  = int_param('section_id', 0, $_GET);
$tab     = $_GET['tab'] ?? 'choices';

// ── POST Handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_choice') {
        $studentId  = int_param('student_id', 0, $_POST);
        $saveSessId = int_param('session_id', 0, $_POST);
        $classId_p  = int_param('class_id',  0, $_POST);
        $sectId_p   = int_param('section_id',0, $_POST);
        $groupId    = int_param('group_id',  0, $_POST) ?: null;
        $sub3       = int_param('subject_3rd_id', 0, $_POST) ?: null;
        $sub4       = int_param('subject_4th_id', 0, $_POST) ?: null;
        $altRelig   = int_param('alt_religious_subject_id', 0, $_POST) ?: null;

        if ($studentId && $saveSessId) {
            // Fetch previous choice for history
            $prev = $pdo->prepare('SELECT * FROM student_subject_choices WHERE student_id=? AND session_id=?');
            $prev->execute([$studentId, $saveSessId]);
            $prev = $prev->fetch();

            // Upsert choice
            $pdo->prepare('INSERT INTO student_subject_choices
                           (student_id, session_id, class_id, section_id, group_id, subject_3rd_id, subject_4th_id, alt_religious_subject_id, chosen_by)
                           VALUES (?,?,?,?,?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE
                           group_id=VALUES(group_id), subject_3rd_id=VALUES(subject_3rd_id),
                           subject_4th_id=VALUES(subject_4th_id), alt_religious_subject_id=VALUES(alt_religious_subject_id),
                           chosen_by=VALUES(chosen_by)')
                ->execute([$studentId,$saveSessId,$classId_p,$sectId_p,$groupId,$sub3,$sub4,$altRelig,$_SESSION['user_id']??null]);

            // Log history for each changed field
            $histStmt = $pdo->prepare('INSERT INTO student_subject_history
                                       (student_id, session_id, change_type, old_value_id, new_value_id, old_value_label, new_value_label, changed_by, change_reason)
                                       VALUES (?,?,?,?,?,?,?,?,?)');

            $nameLookup = fn($id) => $id ? ($pdo->prepare('SELECT subject_name FROM subjects WHERE id=?')->execute([$id]) ? $pdo->query("SELECT subject_name FROM subjects WHERE id=$id")->fetchColumn() : null) : null;
            $groupLookup = fn($id) => $id ? ($pdo->query("SELECT group_name FROM groups_stream WHERE id=$id")->fetchColumn() ?: null) : null;

            if ($prev) {
                if ($prev['group_id'] != $groupId)
                    $histStmt->execute([$studentId,$saveSessId,'group',$prev['group_id'],$groupId,
                        $groupLookup($prev['group_id']),$groupLookup($groupId),$_SESSION['user_id']??null,'Changed via Subject Choices page']);
                if ($prev['subject_3rd_id'] != $sub3)
                    $histStmt->execute([$studentId,$saveSessId,'3rd_subject',$prev['subject_3rd_id'],$sub3,
                        $nameLookup($prev['subject_3rd_id']),$nameLookup($sub3),$_SESSION['user_id']??null,'Changed via Subject Choices page']);
                if ($prev['subject_4th_id'] != $sub4)
                    $histStmt->execute([$studentId,$saveSessId,'4th_subject',$prev['subject_4th_id'],$sub4,
                        $nameLookup($prev['subject_4th_id']),$nameLookup($sub4),$_SESSION['user_id']??null,'Changed via Subject Choices page']);
                if ($prev['alt_religious_subject_id'] != $altRelig)
                    $histStmt->execute([$studentId,$saveSessId,'religious_alt',$prev['alt_religious_subject_id'],$altRelig,
                        $nameLookup($prev['alt_religious_subject_id']),$nameLookup($altRelig),$_SESSION['user_id']??null,'Changed via Subject Choices page']);
            }

            flash('success', 'Subject choice saved and history logged.');
        }
        header('Location: subject_choices.php?session_id='.$saveSessId.'&class_id='.$classId_p.'&section_id='.$sectId_p.'&tab=choices');
        exit;
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────
$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions WHERE deleted_at IS NULL ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id, class_name, class_level FROM classes WHERE deleted_at IS NULL AND status=1 ORDER BY display_order, class_numeric')->fetchAll();

$sections = [];
if ($classId) {
    $s = $pdo->prepare('SELECT id, section_name FROM sections WHERE class_id=? AND deleted_at IS NULL AND status=1 ORDER BY section_name');
    $s->execute([$classId]);
    $sections = $s->fetchAll();
}

$groups       = $pdo->query('SELECT * FROM groups_stream WHERE status=1 ORDER BY group_name')->fetchAll();
$sub3Options  = $pdo->query('SELECT id, subject_name FROM subjects WHERE can_be_3rd=1 AND deleted_at IS NULL AND status=1 ORDER BY subject_name')->fetchAll();
$sub4Options  = $pdo->query('SELECT id, subject_name FROM subjects WHERE can_be_4th=1 AND deleted_at IS NULL AND status=1 ORDER BY subject_name')->fetchAll();
$altReligOpts = $pdo->query('SELECT id, subject_name FROM subjects WHERE is_religious_alt=1 AND deleted_at IS NULL AND status=1 ORDER BY subject_name')->fetchAll();

// Students with their current choices
$students = [];
if ($classId && $sectId && $sessId) {
    $sq = $pdo->prepare('
        SELECT sp.user_id AS student_id,
               CONCAT(sp.first_name," ",sp.last_name) AS student_name,
               sp.religion, sp.gender,
               se.roll_number,
               ssc.group_id, ssc.subject_3rd_id, ssc.subject_4th_id, ssc.alt_religious_subject_id,
               gs.group_name,
               s3.subject_name AS sub3_name,
               s4.subject_name AS sub4_name,
               sa.subject_name AS alt_name
        FROM student_profiles sp
        JOIN student_enrollments se ON se.student_id=sp.user_id AND se.session_id=? AND se.class_id=? AND se.section_id=? AND se.status="active"
        LEFT JOIN student_subject_choices ssc ON ssc.student_id=sp.user_id AND ssc.session_id=?
        LEFT JOIN groups_stream gs ON gs.id=ssc.group_id
        LEFT JOIN subjects s3 ON s3.id=ssc.subject_3rd_id
        LEFT JOIN subjects s4 ON s4.id=ssc.subject_4th_id
        LEFT JOIN subjects sa ON sa.id=ssc.alt_religious_subject_id
        ORDER BY se.roll_number, sp.first_name
    ');
    $sq->execute([$sessId, $classId, $sectId, $sessId]);
    $students = $sq->fetchAll();
}

// History tab data
$historyData = [];
if ($tab === 'history' && $classId && $sessId) {
    $hq = $pdo->prepare('
        SELECT ssh.*, CONCAT(sp.first_name," ",sp.last_name) AS student_name,
               CONCAT(u.full_name) AS changed_by_name
        FROM student_subject_history ssh
        JOIN student_profiles sp ON sp.user_id=ssh.student_id
        JOIN student_enrollments se ON se.student_id=ssh.student_id AND se.session_id=ssh.session_id AND se.class_id=? AND se.status="active"
        LEFT JOIN users u ON u.id=ssh.changed_by
        WHERE ssh.session_id=?
        ORDER BY ssh.changed_at DESC
        LIMIT 200
    ');
    $hq->execute([$classId, $sessId]);
    $historyData = $hq->fetchAll();
}

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h1 class="page-title mb-0"><i class="bi bi-journals me-2 text-primary"></i>Student Subject Choices</h1>
</div>

<div class="alert alert-info small mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Assign <strong>Stream / Group</strong>, <strong>3rd & 4th subject</strong>, and <strong>alternative religious subject</strong>
  for each student. All changes are logged in the History tab with full audit trail.
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end" id="filterForm">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <div class="col-md-3">
        <label class="form-label small">Session</label>
        <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sessions as $sess): ?>
            <option value="<?= $sess['id'] ?>" <?= $sessId == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small">Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Select Class —</option>
          <?php foreach ($classes as $cls): ?>
            <option value="<?= $cls['id'] ?>" <?= $classId == $cls['id'] ? 'selected' : '' ?>><?= e($cls['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($classId): ?>
      <div class="col-md-3">
        <label class="form-label small">Section</label>
        <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— All Sections —</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>" <?= $sectId == $sec['id'] ? 'selected' : '' ?>><?= e($sec['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $tab==='choices'?'active':'' ?>" href="?tab=choices&session_id=<?= $sessId ?>&class_id=<?= $classId ?>&section_id=<?= $sectId ?>">
      <i class="bi bi-list-check me-1"></i>Assign Choices
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab==='history'?'active':'' ?>" href="?tab=history&session_id=<?= $sessId ?>&class_id=<?= $classId ?>&section_id=<?= $sectId ?>">
      <i class="bi bi-clock-history me-1"></i>Change History
    </a>
  </li>
</ul>

<?php if ($tab === 'choices'): ?>

<?php if (!$classId || !$sectId): ?>
<div class="card"><div class="card-body text-center text-muted py-5">
  <i class="bi bi-funnel fs-1 d-block mb-2"></i>
  Select a class and section above to view students.
</div></div>
<?php elseif (empty($students)): ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><i class="bi bi-people"></i><p>No active students enrolled in this class/section for the selected session.</p></div>
</div></div>
<?php else: ?>
<div class="card table-card">
  <div class="card-header">
    <h6 class="card-title mb-0">
      Students — Subject Choices
      <span class="badge bg-secondary ms-2"><?= count($students) ?></span>
    </h6>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th class="ps-3">Roll</th>
          <th>Student</th>
          <th>Religion</th>
          <th>Stream / Group</th>
          <th>3rd Subject</th>
          <th>4th Subject</th>
          <th>Alt. Religion</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $st): ?>
        <tr>
          <td class="ps-3 text-muted"><?= $st['roll_number'] ?></td>
          <td class="fw-600"><?= e($st['student_name']) ?></td>
          <td><span class="badge bg-light text-dark small"><?= e($st['religion'] ?? '—') ?></span></td>
          <td><?= e($st['group_name'] ?? '—') ?></td>
          <td><?= e($st['sub3_name'] ?? '—') ?></td>
          <td><?= e($st['sub4_name'] ?? '—') ?></td>
          <td><?= e($st['alt_name'] ?? '—') ?></td>
          <td>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#choiceModal"
                    onclick="openChoice(<?= htmlspecialchars(json_encode($st), ENT_QUOTES) ?>)">
              <i class="bi bi-pencil"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'history'): ?>

<?php if (empty($historyData)): ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><i class="bi bi-clock-history"></i><p>No change history for this class/session yet.</p></div>
</div></div>
<?php else: ?>
<div class="card table-card">
  <div class="card-header"><h6 class="card-title mb-0">Change Log <span class="badge bg-secondary ms-2"><?= count($historyData) ?></span></h6></div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th class="ps-3">Date/Time</th>
          <th>Student</th>
          <th>Changed</th>
          <th>From</th>
          <th>To</th>
          <th>By</th>
          <th>Reason</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($historyData as $h): ?>
        <tr>
          <td class="ps-3 small text-muted"><?= date('d M Y H:i', strtotime($h['changed_at'])) ?></td>
          <td class="fw-600 small"><?= e($h['student_name']) ?></td>
          <td><span class="badge bg-info text-dark"><?= e(str_replace('_',' ',ucfirst($h['change_type']))) ?></span></td>
          <td class="text-danger small"><?= e($h['old_value_label'] ?? '—') ?></td>
          <td class="text-success small"><?= e($h['new_value_label'] ?? '—') ?></td>
          <td class="small"><?= e($h['changed_by_name'] ?? 'System') ?></td>
          <td class="small text-muted"><?= e($h['change_reason'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Choice Modal -->
<div class="modal fade" id="choiceModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action"     value="save_choice">
        <input type="hidden" name="student_id" id="cm_student_id">
        <input type="hidden" name="session_id" value="<?= $sessId ?>">
        <input type="hidden" name="class_id"   value="<?= $classId ?>">
        <input type="hidden" name="section_id" value="<?= $sectId ?>">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-journals me-2"></i>Subject Choices: <span id="cm_name"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Stream / Group</label>
              <select name="group_id" id="cm_group" class="form-select">
                <option value="0">— No Stream (General) —</option>
                <?php foreach ($groups as $g): ?>
                  <option value="<?= $g['id'] ?>"><?= e($g['group_name']) ?> (<?= e($g['group_code']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Science, Commerce, Arts, etc. For class 9–12.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Alternative Religious Subject</label>
              <select name="alt_religious_subject_id" id="cm_alt_relig" class="form-select">
                <option value="0">— Default (from religion) —</option>
                <?php foreach ($altReligOpts as $a): ?>
                  <option value="<?= $a['id'] ?>"><?= e($a['subject_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">3rd Subject</label>
              <select name="subject_3rd_id" id="cm_sub3" class="form-select">
                <option value="0">— None —</option>
                <?php foreach ($sub3Options as $s): ?>
                  <option value="<?= $s['id'] ?>"><?= e($s['subject_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">4th Subject</label>
              <select name="subject_4th_id" id="cm_sub4" class="form-select">
                <option value="0">— None —</option>
                <?php foreach ($sub4Options as $s): ?>
                  <option value="<?= $s['id'] ?>"><?= e($s['subject_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php if (empty($groups) && empty($sub3Options) && empty($sub4Options)): ?>
          <div class="alert alert-warning mt-3 small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No streams or optional subjects defined yet.
            Go to <a href="../academic/groups.php">Groups/Streams</a> and mark subjects as
            <strong>3rd/4th Subject</strong> in <a href="../academic/subjects.php">Subjects</a>.
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Save & Log Change
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openChoice(st) {
  document.getElementById('cm_student_id').value = st.student_id;
  document.getElementById('cm_name').textContent  = st.student_name;
  document.getElementById('cm_group').value       = st.group_id || 0;
  document.getElementById('cm_sub3').value        = st.subject_3rd_id || 0;
  document.getElementById('cm_sub4').value        = st.subject_4th_id || 0;
  document.getElementById('cm_alt_relig').value   = st.alt_religious_subject_id || 0;
  new bootstrap.Modal(document.getElementById('choiceModal')).show();
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
