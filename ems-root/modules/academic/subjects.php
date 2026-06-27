<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Subjects';
$breadcrumbs = ['Academic' => null, 'Subjects' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['academic.manage']);

$pdo = db();
$activeTab = $_GET['tab'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id              = int_param('id', 0, $_POST);
        $name            = trim($_POST['subject_name'] ?? '');
        $code            = trim($_POST['subject_code'] ?? '') ?: null;
        $type            = $_POST['subject_type'] ?? 'core';
        $levelCodes      = implode(',', array_filter((array)($_POST['level_codes'] ?? [])));
        $isGroup         = isset($_POST['is_group_subject']) ? 1 : 0;
        $isReligious     = isset($_POST['is_religious'])     ? 1 : 0;
        $isReligiousAlt  = isset($_POST['is_religious_alt']) ? 1 : 0;
        $altForId        = int_param('alt_religious_for_id', 0, $_POST) ?: null;
        $can3rd          = isset($_POST['can_be_3rd'])   ? 1 : 0;
        $can4th          = isset($_POST['can_be_4th'])   ? 1 : 0;
        $hasPractical    = isset($_POST['has_practical']) ? 1 : 0;
        $hasMcq          = isset($_POST['has_mcq'])       ? 1 : 0;

        if ($name) {
            if ($id) {
                $pdo->prepare('UPDATE subjects SET subject_name=?,subject_code=?,subject_type=?,level_codes=?,
                               is_group_subject=?,is_religious=?,is_religious_alt=?,alt_religious_for_id=?,
                               can_be_3rd=?,can_be_4th=?,has_practical=?,has_mcq=? WHERE id=?')
                    ->execute([$name,$code,$type,$levelCodes,$isGroup,$isReligious,$isReligiousAlt,
                               $altForId,$can3rd,$can4th,$hasPractical,$hasMcq,$id]);
                flash('success', 'Subject updated.');
            } else {
                $pdo->prepare('INSERT INTO subjects (subject_name,subject_code,subject_type,level_codes,
                               is_group_subject,is_religious,is_religious_alt,alt_religious_for_id,
                               can_be_3rd,can_be_4th,has_practical,has_mcq) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$name,$code,$type,$levelCodes,$isGroup,$isReligious,$isReligiousAlt,
                               $altForId,$can3rd,$can4th,$hasPractical,$hasMcq]);
                flash('success', "Subject '$name' added.");
            }
        }
    } elseif ($action === 'delete') {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('UPDATE subjects SET deleted_at=NOW(), deleted_by=? WHERE id=?')
            ->execute([$_SESSION['user_id']??null, $id]);
        flash('success', 'Subject moved to deleted items.');

    } elseif ($action === 'assign_alt_religion') {
        // Assign a student to an alternative religious subject
        $studentId  = int_param('student_id', 0, $_POST);
        $sessionId  = int_param('session_id', 0, $_POST);
        $subjectId  = int_param('alt_subject_id', 0, $_POST) ?: null;

        if ($studentId && $sessionId) {
            $pdo->prepare('INSERT INTO student_subject_choices (student_id, session_id, class_id, section_id, alt_religious_subject_id, chosen_by)
                           SELECT se.student_id, se.session_id, se.class_id, se.section_id, :sub, :by
                           FROM student_enrollments se
                           WHERE se.student_id=:sid AND se.session_id=:sess AND se.status="active"
                           ON DUPLICATE KEY UPDATE alt_religious_subject_id=VALUES(alt_religious_subject_id), chosen_by=VALUES(chosen_by)')
                ->execute([':sub'=>$subjectId, ':by'=>$_SESSION['user_id']??null, ':sid'=>$studentId, ':sess'=>$sessionId]);
            flash('success', 'Religious subject assignment updated.');
        }
    }
    header('Location: subjects.php?tab=' . urlencode($activeTab));
    exit;
}

// ── Data ──────────────────────────────────────────────────────────────────────
$subjects        = $pdo->query('SELECT * FROM subjects WHERE deleted_at IS NULL AND status=1 ORDER BY subject_type, subject_name')->fetchAll();
$religiousSubjects = array_filter($subjects, fn($s) => $s['is_religious']);
$levels          = $pdo->query('SELECT * FROM institute_levels WHERE status=1 ORDER BY display_order')->fetchAll();
$currentSessId   = (int)setting('current_session_id', 0);

// For alt religion assignment tab
$religiousAltList = [];
if ($activeTab === 'religion') {
    $religiousAltList = $pdo->query('SELECT * FROM subjects WHERE is_religious_alt=1 AND deleted_at IS NULL AND status=1 ORDER BY subject_name')->fetchAll();
}

require_once EMS_ROOT . '/includes/header.php';

// Group subjects by level codes for display
$subjectsByLevel = [];
foreach ($subjects as $sub) {
    $codes = $sub['level_codes'] ? explode(',', $sub['level_codes']) : ['all'];
    foreach ($codes as $c) {
        $c = trim($c);
        $subjectsByLevel[$c][] = $sub;
    }
}
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h1 class="page-title mb-0"><i class="bi bi-journal-bookmark-fill me-2 text-primary"></i>Subjects</h1>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#subjectModal" onclick="setForm(null)">
    <i class="bi bi-plus-lg me-1"></i>Add Subject
  </button>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $activeTab==='list'?'active':'' ?>" href="?tab=list">
      <i class="bi bi-list-ul me-1"></i>All Subjects
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $activeTab==='by_level'?'active':'' ?>" href="?tab=by_level">
      <i class="bi bi-layers me-1"></i>By Level
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $activeTab==='religion'?'active':'' ?>" href="?tab=religion">
      <i class="bi bi-star me-1"></i>Religious Subject Assignment
    </a>
  </li>
</ul>

<?php if ($activeTab === 'list'): ?>
<!-- ── ALL SUBJECTS TABLE ──────────────────────────────────────────────────── -->
<div class="card table-card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h6 class="card-title mb-0">All Subjects <span class="badge bg-secondary ms-2"><?= count($subjects) ?></span></h6>
    <input type="text" id="table-search" class="form-control form-control-sm" style="width:200px;" placeholder="Search…" data-target="data-table">
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="data-table">
      <thead>
        <tr>
          <th class="ps-3">#</th>
          <th>Name</th>
          <th>Code</th>
          <th>Type</th>
          <th>Levels</th>
          <th>Flags</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($subjects)): ?>
          <tr><td colspan="7"><div class="empty-state"><i class="bi bi-book"></i><p>No subjects added yet</p></div></td></tr>
        <?php else: foreach ($subjects as $i => $sub): ?>
        <tr>
          <td class="ps-3 text-muted"><?= $i+1 ?></td>
          <td>
            <div class="fw-600"><?= e($sub['subject_name']) ?></div>
            <?php if ($sub['is_religious_alt'] && $sub['alt_religious_for_id']): ?>
              <?php $mainSub = array_filter($subjects, fn($s) => $s['id'] == $sub['alt_religious_for_id']); ?>
              <small class="text-warning"><i class="bi bi-arrow-right me-1"></i>Alt for: <?= e(reset($mainSub)['subject_name'] ?? 'Unknown') ?></small>
            <?php endif; ?>
          </td>
          <td><code class="small"><?= e($sub['subject_code'] ?? '—') ?></code></td>
          <td><span class="badge bg-light text-dark"><?= e(str_replace('_',' ',ucfirst($sub['subject_type']))) ?></span></td>
          <td>
            <?php if ($sub['level_codes']): ?>
              <?php foreach (explode(',', $sub['level_codes']) as $lc): ?>
                <span class="badge bg-secondary me-1"><?= e(trim($lc)) ?></span>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="text-muted small">All levels</span>
            <?php endif; ?>
          </td>
          <td>
            <?php
            $flags = [];
            if ($sub['is_group_subject'])  $flags[] = '<span class="badge bg-info">Stream</span>';
            if ($sub['is_religious'])       $flags[] = '<span class="badge bg-warning text-dark">Religious</span>';
            if ($sub['is_religious_alt'])   $flags[] = '<span class="badge bg-warning">Alt-Religion</span>';
            if ($sub['can_be_3rd'])         $flags[] = '<span class="badge bg-secondary">3rd</span>';
            if ($sub['can_be_4th'])         $flags[] = '<span class="badge bg-secondary">4th</span>';
            if ($sub['has_practical'])      $flags[] = '<span class="badge bg-success">Practical</span>';
            if ($sub['has_mcq'])            $flags[] = '<span class="badge bg-primary">MCQ</span>';
            echo implode(' ', $flags) ?: '<span class="text-muted">—</span>';
            ?>
          </td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#subjectModal"
                      onclick="setForm(<?= htmlspecialchars(json_encode($sub), ENT_QUOTES) ?>)" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id"     value="<?= $sub['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger"
                        data-soft-delete="<?= e($sub['subject_name']) ?>"
                        data-soft-delete-warn="This will hide the subject from all class mappings and mark entries."
                        data-form-id="delsub<?= $sub['id'] ?>"
                        id="delsub<?= $sub['id'] ?>" title="Delete">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($activeTab === 'by_level'): ?>
<!-- ── SUBJECTS BY INSTITUTE LEVEL ────────────────────────────────────────── -->
<div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Subjects can belong to multiple levels. A subject without level codes appears in <strong>All Levels</strong>.</div>

<?php
$levelDisplay = [];
foreach ($levels as $lv) $levelDisplay[$lv['level_code']] = $lv['level_name'];
$levelDisplay['all'] = 'All Levels (No restriction)';
?>
<?php foreach ($levelDisplay as $lCode => $lName): ?>
  <?php $lvSubjects = $subjectsByLevel[$lCode] ?? []; if (empty($lvSubjects) && $lCode !== 'all') continue; ?>
  <div class="card mb-3">
    <div class="card-header">
      <h6 class="card-title mb-0">
        <span class="badge bg-primary me-2"><?= e($lName) ?></span>
        <span class="text-muted small"><?= count($lvSubjects) ?> subjects</span>
      </h6>
    </div>
    <div class="card-body">
      <div class="row g-2">
        <?php if (empty($lvSubjects)): ?>
          <div class="col-12 text-muted small">No subjects assigned to this level yet.</div>
        <?php else: foreach ($lvSubjects as $sub): ?>
        <div class="col-md-3 col-6">
          <div class="border rounded p-2 small">
            <div class="fw-600"><?= e($sub['subject_name']) ?></div>
            <div class="text-muted"><?= e(str_replace('_',' ',ucfirst($sub['subject_type']))) ?></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php elseif ($activeTab === 'religion'): ?>
<!-- ── RELIGIOUS SUBJECT ASSIGNMENT ──────────────────────────────────────── -->
<div class="alert alert-info small">
  <i class="bi bi-info-circle me-1"></i>
  Students are assigned a <strong>religious subject</strong> based on their religion by default.
  Use this tab to assign <strong>alternative religious subjects</strong> (e.g. students who should take Hindu Dharma instead of Islam).
</div>

<?php
// Load sessions and filter controls
$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions WHERE deleted_at IS NULL ORDER BY id DESC')->fetchAll();
$filterSess = int_param('sess_id', $currentSessId, $_GET);
$filterRelig = $_GET['filter_religion'] ?? '';

// Load students with their enrollment and current alt choice
$studentQ = '
    SELECT sp.user_id AS student_id,
           CONCAT(sp.first_name," ",sp.last_name) AS student_name,
           sp.religion,
           se.class_id, se.section_id,
           c.class_name, s.section_name,
           ssc.alt_religious_subject_id,
           sub_alt.subject_name AS alt_subject_name
    FROM student_profiles sp
    JOIN student_enrollments se ON se.student_id=sp.user_id AND se.session_id=? AND se.status="active"
    JOIN classes c ON c.id=se.class_id
    JOIN sections s ON s.id=se.section_id
    LEFT JOIN student_subject_choices ssc ON ssc.student_id=sp.user_id AND ssc.session_id=?
    LEFT JOIN subjects sub_alt ON sub_alt.id=ssc.alt_religious_subject_id';
$params = [$filterSess, $filterSess];
if ($filterRelig) { $studentQ .= ' WHERE sp.religion=?'; $params[] = $filterRelig; }
$studentQ .= ' ORDER BY c.display_order, s.section_name, sp.first_name';
$students = $pdo->prepare($studentQ);
$students->execute($params);
$students = $students->fetchAll();
?>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="religion">
      <div class="col-md-3">
        <label class="form-label small">Session</label>
        <select name="sess_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sessions as $sess): ?>
            <option value="<?= $sess['id'] ?>" <?= $filterSess == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small">Filter by Religion</label>
        <select name="filter_religion" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Religions</option>
          <?php foreach (['Islam','Hindu','Christian','Buddhist'] as $rel): ?>
            <option value="<?= $rel ?>" <?= $filterRelig === $rel ? 'selected' : '' ?>><?= $rel ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th class="ps-3">Student</th>
          <th>Religion</th>
          <th>Class / Section</th>
          <th>Current Alt Subject</th>
          <th>Assign Alt Subject</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">No students found for this session.</td></tr>
        <?php else: foreach ($students as $st): ?>
        <tr>
          <td class="fw-600 ps-3"><?= e($st['student_name']) ?></td>
          <td><span class="badge bg-light text-dark"><?= e($st['religion'] ?? '—') ?></span></td>
          <td><?= e($st['class_name']) ?> – <?= e($st['section_name']) ?></td>
          <td>
            <?php if ($st['alt_subject_name']): ?>
              <span class="badge bg-warning text-dark"><?= e($st['alt_subject_name']) ?></span>
            <?php else: ?>
              <span class="text-muted small">Default (based on religion)</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" class="d-flex gap-2 align-items-center">
              <?= csrf_field() ?>
              <input type="hidden" name="action"     value="assign_alt_religion">
              <input type="hidden" name="student_id" value="<?= $st['student_id'] ?>">
              <input type="hidden" name="session_id" value="<?= $filterSess ?>">
              <select name="alt_subject_id" class="form-select form-select-sm" style="width:auto;">
                <option value="">— None / Default —</option>
                <?php foreach ($religiousAltList as $ra): ?>
                  <option value="<?= $ra['id'] ?>" <?= $st['alt_religious_subject_id'] == $ra['id'] ? 'selected' : '' ?>>
                    <?= e($ra['subject_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Subject Modal -->
<div class="modal fade" id="subjectModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="sub_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="subjectModalTitle">Add Subject</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Subject Name <span class="text-danger">*</span></label>
              <input type="text" name="subject_name" id="sub_name" class="form-control" placeholder="e.g. Mathematics, Islam Studies" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Code</label>
              <input type="text" name="subject_code" id="sub_code" class="form-control" maxlength="30" placeholder="MAT">
            </div>
            <div class="col-md-4">
              <label class="form-label">Type</label>
              <select name="subject_type" id="sub_type" class="form-select">
                <option value="core">Core / Compulsory</option>
                <option value="religious">Religious</option>
                <option value="optional">Optional</option>
                <option value="4th_subject">4th Subject</option>
                <option value="practical">Practical Only</option>
              </select>
            </div>
          </div>

          <div class="form-section-title">Institute Level Applicability</div>
          <p class="text-muted small">Leave all unchecked = applies to all levels.</p>
          <div class="d-flex flex-wrap gap-3">
            <?php foreach ($levels as $lv): ?>
            <div class="form-check">
              <input type="checkbox" class="form-check-input" name="level_codes[]"
                     id="lv_<?= $lv['level_code'] ?>" value="<?= e($lv['level_code']) ?>">
              <label class="form-check-label" for="lv_<?= $lv['level_code'] ?>"><?= e($lv['level_name']) ?></label>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="form-section-title">Subject Properties</div>
          <div class="row g-3">
            <?php $boxes = [
              'is_group_subject' => ['Stream/Group Subject',   'Belongs to a specific stream (Science/Commerce/Arts)'],
              'is_religious'     => ['Main Religious Subject', 'This is a main religious subject (assigned by religion)'],
              'is_religious_alt' => ['Alternative Religious',  'This replaces the main religious subject for some students'],
              'can_be_3rd'       => ['3rd Subject Option',     'Students can select this as their 3rd elective'],
              'can_be_4th'       => ['4th Subject Option',     'Students can select this as their 4th elective'],
              'has_practical'    => ['Has Practical Component','Separate marks for practical/lab work'],
              'has_mcq'          => ['Has MCQ Component',      'Separate marks for multiple-choice questions'],
            ];
            foreach ($boxes as $k => [$lbl, $desc]): ?>
            <div class="col-md-6">
              <div class="form-check border rounded p-2">
                <input type="checkbox" class="form-check-input" name="<?= $k ?>" id="sub_<?= $k ?>" value="1">
                <label class="form-check-label" for="sub_<?= $k ?>">
                  <div class="fw-600 small"><?= $lbl ?></div>
                  <div class="text-muted" style="font-size:.75rem;"><?= $desc ?></div>
                </label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="mt-3" id="altForWrapper" style="display:none;">
            <label class="form-label">This is Alternative For:</label>
            <select name="alt_religious_for_id" id="sub_alt_for" class="form-select">
              <option value="0">— Select Main Religious Subject —</option>
              <?php foreach (array_filter($subjects, fn($s) => $s['is_religious']) as $rs): ?>
                <option value="<?= $rs['id'] ?>"><?= e($rs['subject_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Subject</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setForm(sub) {
  document.getElementById('subjectModalTitle').textContent = sub ? 'Edit Subject' : 'Add Subject';
  document.getElementById('sub_id').value   = sub ? sub.id : 0;
  document.getElementById('sub_name').value = sub ? sub.subject_name : '';
  document.getElementById('sub_code').value = sub ? (sub.subject_code || '') : '';
  document.getElementById('sub_type').value = sub ? sub.subject_type : 'core';

  // Level codes checkboxes
  document.querySelectorAll('[name="level_codes[]"]').forEach(cb => {
    const codes = sub && sub.level_codes ? sub.level_codes.split(',').map(s => s.trim()) : [];
    cb.checked = codes.includes(cb.value);
  });

  // Boolean flags
  ['is_group_subject','is_religious','is_religious_alt','can_be_3rd','can_be_4th','has_practical','has_mcq'].forEach(k => {
    const el = document.getElementById('sub_' + k);
    if (el) el.checked = sub ? sub[k] == 1 : false;
  });

  const altFor = document.getElementById('sub_alt_for');
  if (altFor) altFor.value = sub ? (sub.alt_religious_for_id || 0) : 0;
  toggleAltFor();
}

function toggleAltFor() {
  const isAlt = document.getElementById('sub_is_religious_alt')?.checked;
  const wrap  = document.getElementById('altForWrapper');
  if (wrap) wrap.style.display = isAlt ? '' : 'none';
}

document.getElementById('sub_is_religious_alt')?.addEventListener('change', toggleAltFor);
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
