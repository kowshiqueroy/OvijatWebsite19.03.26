<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Enrollment Manager';
$breadcrumbs = ['Students' => 'index.php', 'Enroll' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.create']);

$pdo = db();
$session_id = int_param('session_id',(int)setting('current_session_id',0),$_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'enroll') {
        $student_id  = int_param('student_id', 0, $_POST);
        $sess        = int_param('session_id', $session_id, $_POST);
        $cls         = int_param('class_id', 0, $_POST);
        $sec         = int_param('section_id', 0, $_POST);
        $grp         = int_param('group_id', 0, $_POST) ?: null;
        $roll        = int_param('roll_number', 0, $_POST);
        // Mid-session join tracking
        $join_month  = int_param('join_month', 0, $_POST) ?: null;
        $join_year   = int_param('join_year', 0, $_POST) ?: null;
        $is_mid      = $join_month && $join_year;

        if ($student_id && $sess && $cls && $sec) {
            if (!$roll) {
                $mr = $pdo->prepare('SELECT COALESCE(MAX(roll_number),0)+1 FROM student_enrollments WHERE session_id=? AND class_id=? AND section_id=?');
                $mr->execute([$sess, $cls, $sec]);
                $roll = (int)$mr->fetchColumn();
            }
            try {
                $pdo->prepare('INSERT INTO student_enrollments (student_id,session_id,class_id,section_id,group_id,roll_number,join_month,join_year,status) VALUES (?,?,?,?,?,?,?,?,"active")')
                    ->execute([$student_id, $sess, $cls, $sec, $grp, $roll, $join_month, $join_year]);
                $msg = "Student enrolled with roll $roll.";
                if ($is_mid) $msg .= " Mid-session join: fees will start from " . date('M Y', mktime(0,0,0,$join_month,1,$join_year)) . ".";
                flash('success', $msg);
                log_activity('enroll_student', 'students', $student_id, '', "Roll:$roll Sess:$sess" . ($is_mid ? " JoinMonth:$join_month/$join_year" : ''));
            } catch (Exception $e) {
                flash('error', 'Already enrolled in this session or duplicate roll number.');
            }
        }

    } elseif ($action === 'change_status') {
        // Mid-session deactivation / reactivation of an enrollment
        $enroll_id  = int_param('id', 0, $_POST);
        $new_status = $_POST['new_status'] ?? '';
        $left_date  = $_POST['left_date'] ?: date('Y-m-d');
        $reason     = trim($_POST['reason'] ?? '');
        $allowed    = ['active', 'transferred', 'suspended', 'opt_out', 'graduated'];

        if ($enroll_id && in_array($new_status, $allowed)) {
            $isLeaving = $new_status !== 'active';
            $pdo->prepare(
                'UPDATE student_enrollments
                 SET status=?, left_date=?, left_reason=?, status_changed_by=?
                 WHERE id=?'
            )->execute([
                $new_status,
                $isLeaving ? $left_date : null,
                $isLeaving ? $reason : null,
                current_user_id(),
                $enroll_id
            ]);
            $label = ucfirst(str_replace('_', ' ', $new_status));
            flash('success', "Enrollment status changed to: $label.");
            log_activity('change_enrollment_status', 'students', $enroll_id, '', "Status:$new_status Reason:$reason");
        }

    } elseif ($action === 'withdraw') {
        $enroll_id = int_param('id', 0, $_POST);
        $pdo->prepare("UPDATE student_enrollments SET status='opt_out', left_date=CURDATE(), status_changed_by=? WHERE id=?")
            ->execute([current_user_id(), $enroll_id]);
        flash('success', 'Enrollment withdrawn.');
    }
    header('Location: enroll.php?session_id='.$session_id);
    exit;
}

$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY display_order,class_numeric')->fetchAll();
$groups   = $pdo->query('SELECT id,group_name FROM groups_stream ORDER BY group_name')->fetchAll();

$searchQ = trim($_GET['q']??'');
$students = [];
if ($searchQ) {
    $sr = $pdo->prepare("SELECT u.id, u.full_name, sp.student_id_no FROM users u JOIN student_profiles sp ON sp.user_id=u.id WHERE (sp.first_name LIKE :q OR sp.last_name LIKE :q OR sp.student_id_no LIKE :q) AND u.status='active' LIMIT 20");
    $sr->execute([':q'=>"%$searchQ%"]);
    $students = $sr->fetchAll();
}

// All enrollments for this session (active + inactive), show all for management
$recent = $pdo->prepare(
    "SELECT se.*, sp.first_name, sp.last_name, sp.student_id_no, c.class_name, sec.section_name
     FROM student_enrollments se
     JOIN student_profiles sp ON sp.user_id=se.student_id
     JOIN classes c ON c.id=se.class_id
     JOIN sections sec ON sec.id=se.section_id
     WHERE se.session_id=:sess
     ORDER BY se.status='active' DESC, c.display_order, se.roll_number
     LIMIT 60"
);
$recent->execute([':sess' => $session_id]);
$recent = $recent->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-person-check-fill me-2 text-primary"></i>Enrollment Manager</h1>
  <select class="form-select form-select-sm" onchange="location='?session_id='+this.value" style="width:auto">
    <?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?>
  </select>
</div>
<div class="row g-3">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Enroll a Student</span></div>
      <div class="card-body">
        <form method="GET" class="mb-3">
          <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Search student by name or ID…" value="<?= e($searchQ) ?>"><input type="hidden" name="session_id" value="<?= $session_id ?>">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
          </div>
        </form>
        <?php if(!empty($students)): ?>
        <div class="list-group list-group-flush mb-3">
          <?php foreach($students as $stu): ?>
          <div class="list-group-item py-2 d-flex align-items-center justify-content-between">
            <div><span class="fw-600"><?= e($stu['full_name']) ?></span><br><small class="text-muted"><?= e($stu['student_id_no']??'') ?></small></div>
            <button class="btn btn-sm btn-success" onclick="setEnrollForm(<?= $stu['id'] ?>, '<?= e($stu['full_name']) ?>')"><i class="bi bi-plus-lg"></i></button>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div id="enroll-form" style="display:none;">
          <div class="alert alert-info small py-2 mb-2 d-flex gap-2 align-items-center">
            <i class="bi bi-person-plus-fill"></i>
            <span>Enrolling: <strong id="enroll_name"></strong></span>
          </div>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="enroll">
            <input type="hidden" name="student_id" id="enroll_sid" value="">
            <input type="hidden" name="session_id" value="<?= $session_id ?>">
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label small fw-600">Class <span class="text-danger">*</span></label>
                <select name="class_id" id="enroll_cls" class="form-select form-select-sm" onchange="loadSections(this.value)" required>
                  <option value="">— Select —</option>
                  <?php foreach($classes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['class_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small fw-600">Section <span class="text-danger">*</span></label>
                <select name="section_id" id="enroll_sec" class="form-select form-select-sm" required>
                  <option value="">— pick class —</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small fw-600">Group <small class="text-muted fw-400">(optional)</small></label>
                <select name="group_id" class="form-select form-select-sm">
                  <option value="">— None —</option>
                  <?php foreach($groups as $g): ?>
                    <option value="<?= $g['id'] ?>"><?= e($g['group_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small fw-600">Roll <small class="text-muted fw-400">(0=auto)</small></label>
                <input type="number" name="roll_number" class="form-control form-control-sm" value="0" min="0">
              </div>
            </div>

            <!-- Mid-session join option -->
            <div class="form-check form-switch mt-3 mb-1">
              <input class="form-check-input" type="checkbox" id="midSessionToggle" onchange="document.getElementById('midSessionFields').style.display=this.checked?'':'none'">
              <label class="form-check-label small fw-600" for="midSessionToggle">
                <i class="bi bi-calendar-plus me-1 text-warning"></i>Mid-session admission (joined after session start)
              </label>
            </div>
            <div id="midSessionFields" style="display:none;" class="border rounded p-2 bg-warning-subtle mb-2">
              <p class="small text-warning mb-2"><i class="bi bi-info-circle me-1"></i>Monthly fees will only be generated from the specified join month onwards.</p>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label small fw-600">Join Month</label>
                  <select name="join_month" class="form-select form-select-sm">
                    <option value="">— Select —</option>
                    <?php for($m=1;$m<=12;$m++): ?>
                      <option value="<?= $m ?>"><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-6">
                  <label class="form-label small fw-600">Join Year</label>
                  <input type="number" name="join_year" class="form-control form-control-sm" value="<?= date('Y') ?>" min="2020" max="2030">
                </div>
              </div>
              <div class="mt-2 small text-muted">
                <strong>Admission / Back-fees:</strong> Use <a href="collect.php" target="_blank">Fee Collection → Custom Fees</a> to charge one-time admission fees or any arrear amounts.
              </div>
            </div>

            <button type="submit" class="btn btn-success w-100 mt-1 btn-sm">
              <i class="bi bi-person-check me-1"></i>Enroll Student
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card table-card">
      <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="card-title">Enrollments — <?= e(array_column($sessions,'session_name','id')[$session_id] ?? $session_id) ?></span>
        <span class="badge bg-secondary"><?= count($recent) ?></span>
      </div>
      <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
        <table class="table table-sm mb-0" style="font-size:.82rem;">
          <thead style="position:sticky;top:0;background:#1e293b;z-index:2;color:#fff;">
            <tr>
              <th style="background:#1e293b;color:#fff;">Roll</th>
              <th style="background:#1e293b;color:#fff;">Student</th>
              <th style="background:#1e293b;color:#fff;">Class / Sec</th>
              <th style="background:#1e293b;color:#fff;">Joined</th>
              <th style="background:#1e293b;color:#fff;">Status</th>
              <th style="background:#1e293b;color:#fff;"></th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($recent)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No enrollments yet this session.</td></tr>
            <?php endif; ?>
            <?php foreach($recent as $en):
              $isActive = $en['status'] === 'active';
              $rowCls = !$isActive ? 'text-muted' : '';
              $statusBadge = match($en['status']) {
                'active'      => '<span class="badge bg-success">Active</span>',
                'transferred' => '<span class="badge bg-warning text-dark">Transferred</span>',
                'suspended'   => '<span class="badge bg-danger">Suspended</span>',
                'opt_out'     => '<span class="badge bg-secondary">Withdrawn</span>',
                'graduated'   => '<span class="badge bg-info">Graduated</span>',
                default       => '<span class="badge bg-light text-dark">'.ucfirst($en['status']).'</span>',
              };
            ?>
            <tr class="<?= $rowCls ?>">
              <td class="fw-700"><?= $en['roll_number'] ?></td>
              <td>
                <div class="fw-600"><?= e($en['first_name'].' '.$en['last_name']) ?></div>
                <small class="text-muted"><?= e($en['student_id_no']??'') ?></small>
              </td>
              <td><?= e($en['class_name']) ?> / <?= e($en['section_name']) ?></td>
              <td class="text-muted">
                <?php if($en['join_month'] && $en['join_year']): ?>
                  <span class="badge bg-warning text-dark" style="font-size:.68rem;"><?= date('M Y', mktime(0,0,0,$en['join_month'],1,$en['join_year'])) ?></span>
                <?php else: ?>
                  <span class="text-muted small">Session start</span>
                <?php endif; ?>
              </td>
              <td><?= $statusBadge ?></td>
              <td class="text-end">
                <?php if($isActive): ?>
                  <button class="btn btn-xs btn-outline-warning"
                          onclick="openStatusModal(<?= $en['id'] ?>, '<?= e(addslashes($en['first_name'].' '.$en['last_name'])) ?>')"
                          title="Change status">
                    <i class="bi bi-person-dash"></i>
                  </button>
                <?php else: ?>
                  <!-- Reactivate -->
                  <form method="POST" class="d-inline" data-no-protect>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="id" value="<?= $en['id'] ?>">
                    <input type="hidden" name="new_status" value="active">
                    <input type="hidden" name="reason" value="Reactivated">
                    <button type="submit" class="btn btn-xs btn-outline-success" title="Reactivate"
                            onclick="return confirm('Reactivate this enrollment?')">
                      <i class="bi bi-person-check"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Status Change Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_status">
            <input type="hidden" name="id" id="st-enroll-id">
            <div class="modal-header bg-warning py-2">
              <h6 class="modal-title fw-600"><i class="bi bi-person-dash me-2"></i>Change Enrollment Status</h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p class="text-muted small mb-3">Student: <strong id="st-student-name" class="text-dark"></strong></p>
              <div class="alert alert-info py-2 small mb-3">
                <i class="bi bi-info-circle me-1"></i>
                The student's data (marks, attendance, fees) for this session is <strong>always preserved</strong>. Only their active status changes.
              </div>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label small fw-600">New Status</label>
                  <select name="new_status" class="form-select form-select-sm" required>
                    <option value="transferred">Transferred to Another School</option>
                    <option value="suspended">Suspended</option>
                    <option value="opt_out">Withdrawn / Opted Out</option>
                    <option value="graduated">Graduated (End of Session)</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-600">Effective Date</label>
                  <input type="date" name="left_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label small fw-600">Reason / Notes</label>
                  <input type="text" name="reason" class="form-control form-control-sm" placeholder="e.g. Moved to Dhaka, Family relocation" required>
                </div>
              </div>
            </div>
            <div class="modal-footer py-2">
              <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-warning btn-sm">Change Status</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
function setEnrollForm(id, name) {
  document.getElementById('enroll_sid').value = id;
  document.getElementById('enroll_name').textContent = name;
  document.getElementById('enroll-form').style.display = 'block';
  document.getElementById('enroll-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function loadSections(classId) {
  const sel = document.getElementById('enroll_sec');
  if (!classId) { sel.innerHTML = '<option>— pick class —</option>'; return; }
  sel.innerHTML = '<option>Loading…</option>';
  fetch(`../academic/ajax.php?action=sections&class_id=${classId}`)
    .then(r => r.json())
    .then(data => {
      sel.innerHTML = '<option value="">— Select —</option>';
      data.forEach(s => { sel.innerHTML += `<option value="${s.id}">${s.section_name}</option>`; });
    })
    .catch(() => sel.innerHTML = '<option value="">Error</option>');
}
function openStatusModal(enrollId, studentName) {
  document.getElementById('st-enroll-id').value = enrollId;
  document.getElementById('st-student-name').textContent = studentName;
  new bootstrap.Modal(document.getElementById('statusModal')).show();
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
