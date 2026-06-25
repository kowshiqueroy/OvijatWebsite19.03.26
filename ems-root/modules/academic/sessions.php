<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Academic Sessions';
$breadcrumbs = ['Academic' => null, 'Sessions' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['academic.manage']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id         = int_param('id', 0, $_POST);
        $name       = trim($_POST['session_name'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date   = $_POST['end_date'] ?? '';
        $is_current = isset($_POST['is_current']) ? 1 : 0;
        $status     = $_POST['status'] ?? 'upcoming';

        if ($name && $start_date && $end_date) {
            if ($is_current) {
                $pdo->exec("UPDATE academic_sessions SET is_current=0");
            }
            if ($id) {
                $pdo->prepare('UPDATE academic_sessions SET session_name=?,start_date=?,end_date=?,is_current=?,status=? WHERE id=?')
                    ->execute([$name,$start_date,$end_date,$is_current,$status,$id]);
                flash('success', 'Session updated.');
            } else {
                $pdo->prepare('INSERT INTO academic_sessions (session_name,start_date,end_date,is_current,status) VALUES (?,?,?,?,?)')
                    ->execute([$name,$start_date,$end_date,$is_current,$status]);
                flash('success', "Session '$name' created.");
            }
            if ($is_current) {
                $newId = $id ?: (int)$pdo->lastInsertId();
                $pdo->prepare('UPDATE system_settings SET meta_value=? WHERE meta_key="current_session_id"')->execute([$newId]);
            }
        }
    } elseif ($action === 'delete') {
        $id = int_param('id', 0, $_POST);
        // Safety check — don't delete if students enrolled
        $enrolled = (int)$pdo->prepare('SELECT COUNT(*) FROM student_enrollments WHERE session_id=?')->execute([$id]) ? 0 : 0;
        $ec = $pdo->prepare('SELECT COUNT(*) FROM student_enrollments WHERE session_id=?');
        $ec->execute([$id]);
        if ((int)$ec->fetchColumn() > 0) {
            flash('error', 'Cannot delete session with enrolled students. Archive it instead.');
        } else {
            $pdo->prepare('DELETE FROM academic_sessions WHERE id=?')->execute([$id]);
            flash('success', 'Session deleted.');
        }

    } elseif ($action === 'clone_session') {
        $from_id   = int_param('from_session_id', 0, $_POST);
        $new_name  = trim($_POST['new_session_name'] ?? '');
        $new_start = $_POST['new_start_date'] ?? '';
        $new_end   = $_POST['new_end_date'] ?? '';
        $clone_opts= (array)($_POST['clone_items'] ?? []);

        if (!$from_id || !$new_name || !$new_start || !$new_end) {
            flash('error', 'All fields are required for session clone.');
        } else {
            try {
                $pdo->beginTransaction();

                // Create the new session
                $pdo->prepare('INSERT INTO academic_sessions (session_name,start_date,end_date,status) VALUES (?,?,?,"upcoming")')
                    ->execute([$new_name, $new_start, $new_end]);
                $new_id = (int)$pdo->lastInsertId();

                $cloned = [];

                // Clone fee structures
                if (in_array('fee_structures', $clone_opts)) {
                    $fs = $pdo->prepare('SELECT class_id,fee_category_id,amount,due_day,frequency FROM fee_structures WHERE session_id=?');
                    $fs->execute([$from_id]);
                    $ins = $pdo->prepare('INSERT IGNORE INTO fee_structures (session_id,class_id,fee_category_id,amount,due_day,frequency) VALUES (?,?,?,?,?,?)');
                    $count = 0;
                    foreach ($fs->fetchAll() as $r) { $ins->execute([$new_id,$r['class_id'],$r['fee_category_id'],$r['amount'],$r['due_day'],$r['frequency']]); $count++; }
                    $cloned['fee_structures'] = $count;
                }

                // Clone class-subject mappings (marks config + periods/week)
                if (in_array('class_subjects', $clone_opts)) {
                    $cs = $pdo->prepare('SELECT class_id,subject_id,group_id,full_marks_written,full_marks_mcq,full_marks_practical,pass_marks_written,pass_marks_mcq,pass_marks_practical,periods_per_week FROM class_subjects WHERE session_id=?');
                    $cs->execute([$from_id]);
                    $ins2 = $pdo->prepare('INSERT IGNORE INTO class_subjects (session_id,class_id,subject_id,group_id,full_marks_written,full_marks_mcq,full_marks_practical,pass_marks_written,pass_marks_mcq,pass_marks_practical,periods_per_week) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                    $count = 0;
                    foreach ($cs->fetchAll() as $r) {
                        $ins2->execute([$new_id,$r['class_id'],$r['subject_id'],$r['group_id'],$r['full_marks_written'],$r['full_marks_mcq'],$r['full_marks_practical'],$r['pass_marks_written'],$r['pass_marks_mcq'],$r['pass_marks_practical'],$r['periods_per_week']]);
                        $count++;
                    }
                    $cloned['class_subjects'] = $count;
                }

                // Clone routine slots (master slots only)
                if (in_array('routine_slots', $clone_opts)) {
                    $rs = $pdo->prepare('SELECT class_id,section_id,subject_id,teacher_id,room_id,day_of_week,start_time,end_time FROM routine_slots WHERE session_id=? AND is_substitute=0 AND status=1');
                    $rs->execute([$from_id]);
                    $ins3 = $pdo->prepare('INSERT INTO routine_slots (session_id,class_id,section_id,subject_id,teacher_id,room_id,day_of_week,start_time,end_time) VALUES (?,?,?,?,?,?,?,?,?)');
                    $count = 0;
                    foreach ($rs->fetchAll() as $r) {
                        $ins3->execute([$new_id,$r['class_id'],$r['section_id'],$r['subject_id'],$r['teacher_id'],$r['room_id'],$r['day_of_week'],$r['start_time'],$r['end_time']]);
                        $count++;
                    }
                    $cloned['routine_slots'] = $count;
                }

                // Clone custom working days
                if (in_array('working_days', $clone_opts)) {
                    $wd = $pdo->prepare('SELECT class_id,section_id,working_days FROM section_working_days WHERE session_id=?');
                    $wd->execute([$from_id]);
                    $ins4 = $pdo->prepare('INSERT IGNORE INTO section_working_days (session_id,class_id,section_id,working_days,updated_by) VALUES (?,?,?,?,?)');
                    $count = 0;
                    foreach ($wd->fetchAll() as $r) { $ins4->execute([$new_id,$r['class_id'],$r['section_id'],$r['working_days'],current_user_id()]); $count++; }
                    $cloned['working_days'] = $count;
                }

                // Log the clone
                $pdo->prepare('INSERT INTO session_clone_logs (from_session_id,to_session_id,cloned_by,cloned_items,notes) VALUES (?,?,?,?,?)')
                    ->execute([$from_id, $new_id, current_user_id(), json_encode($cloned), "Cloned to '$new_name'"]);

                $pdo->commit();
                $summary = implode(', ', array_map(fn($k,$v)=>"$v $k", array_keys($cloned), $cloned));
                log_activity('clone_session','academic',$new_id,"from:$from_id","Cloned: $summary");
                flash('success', "Session '$new_name' created with: " . ($summary ?: 'no items cloned') . '.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', 'Clone failed: ' . $e->getMessage());
            }
        }
    }
    header('Location: sessions.php');
    exit;
}

$sessions  = $pdo->query('SELECT * FROM academic_sessions ORDER BY start_date DESC')->fetchAll();
$editId    = int_param('edit', 0, $_GET);
$editRow   = null;
if ($editId) {
    $s = $pdo->prepare('SELECT * FROM academic_sessions WHERE id=:id');
    $s->execute([':id' => $editId]);
    $editRow = $s->fetch();
}

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-calendar3 me-2 text-primary"></i>Academic Sessions</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sessionModal"
          onclick="setSessionForm(null)"><i class="bi bi-plus-lg me-1"></i>New Session</button>
</div>

<div class="row g-3">
  <div class="col-md-8">
    <div class="card table-card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>#</th><th>Session Name</th><th>Start</th><th>End</th><th>Status</th><th>Current?</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php if (empty($sessions)): ?>
              <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>No sessions yet</p></div></td></tr>
            <?php else: foreach ($sessions as $i => $sess): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td class="fw-600"><?= e($sess['session_name']) ?></td>
              <td><?= fmt_date($sess['start_date']) ?></td>
              <td><?= fmt_date($sess['end_date']) ?></td>
              <td><span class="badge-status badge-<?= $sess['status'] === 'active' ? 'active' : ($sess['status'] === 'completed' ? 'approved' : 'draft') ?>">
                <?= ucfirst(e($sess['status'])) ?></span></td>
              <td><?= $sess['is_current'] ? '<span class="badge bg-success">Yes</span>' : '—' ?></td>
              <td>
                <div class="table-actions">
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sessionModal"
                          onclick="setSessionForm(<?= htmlspecialchars(json_encode($sess), ENT_QUOTES) ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-success" title="Clone this session"
                          data-bs-toggle="modal" data-bs-target="#cloneModal"
                          onclick="setCloneForm(<?= $sess['id'] ?>, '<?= e(addslashes($sess['session_name'])) ?>')">
                    <i class="bi bi-copy"></i>
                  </button>
                  <form method="POST" class="d-inline" data-no-protect>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $sess['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Delete session \'<?= e(addslashes($sess['session_name'])) ?>\'? This cannot be undone.')">
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
  </div>

  <!-- Quick Tips -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Guide</span></div>
      <div class="card-body">
        <ul class="list-unstyled mb-0" style="font-size:.85rem;">
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Create a new session for each academic year (e.g., <strong>2026</strong>).</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Mark one session as <strong>Current</strong> — it drives fee generation and enrollments.</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>You can also set the current session from <a href="../setup/index.php">System Settings</a>.</li>
          <li><i class="bi bi-info-circle text-primary me-2"></i>Sessions cannot be deleted if they have enrolled students.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Session Modal -->
<div class="modal fade" id="sessionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="sess_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="sessionModalTitle">New Academic Session</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Session Name <span class="text-danger">*</span></label>
            <input type="text" name="session_name" id="sess_name" class="form-control" placeholder="e.g. 2026" required>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Start Date <span class="text-danger">*</span></label>
              <input type="date" name="start_date" id="sess_start" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label">End Date <span class="text-danger">*</span></label>
              <input type="date" name="end_date" id="sess_end" class="form-control" required>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Status</label>
            <select name="status" id="sess_status" class="form-select">
              <option value="upcoming">Upcoming</option>
              <option value="active">Active</option>
              <option value="completed">Completed</option>
            </select>
          </div>
          <div class="form-check mt-3">
            <input type="checkbox" class="form-check-input" name="is_current" id="sess_current" value="1">
            <label class="form-check-label" for="sess_current">
              <strong>Set as current session</strong>
              <small class="text-muted d-block">This will deactivate the previous current session</small>
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Session</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setSessionForm(sess) {
  const title = document.getElementById('sessionModalTitle');
  const id    = document.getElementById('sess_id');
  if (!sess) {
    title.textContent = 'New Academic Session';
    id.value = 0;
    document.getElementById('sess_name').value   = '';
    document.getElementById('sess_start').value  = '';
    document.getElementById('sess_end').value    = '';
    document.getElementById('sess_status').value = 'upcoming';
    document.getElementById('sess_current').checked = false;
  } else {
    title.textContent = 'Edit Session';
    id.value = sess.id;
    document.getElementById('sess_name').value   = sess.session_name;
    document.getElementById('sess_start').value  = sess.start_date;
    document.getElementById('sess_end').value    = sess.end_date;
    document.getElementById('sess_status').value = sess.status;
    document.getElementById('sess_current').checked = sess.is_current == 1;
  }
}
</script>

<!-- Clone Session Modal -->
<div class="modal fade" id="cloneModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="clone_session">
        <input type="hidden" name="from_session_id" id="clone-from-id">
        <div class="modal-header bg-success text-white py-2">
          <h5 class="modal-title fw-600"><i class="bi bi-copy me-2"></i>Clone Session</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">Cloning from: <strong id="clone-from-name" class="text-dark"></strong></p>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-600">New Session Name <span class="text-danger">*</span></label>
              <input type="text" name="new_session_name" id="clone-new-name" class="form-control form-control-sm" required placeholder="e.g., 2027">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-600">Start Date</label>
              <input type="date" name="new_start_date" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-600">End Date</label>
              <input type="date" name="new_end_date" class="form-control form-control-sm" required>
            </div>
          </div>
          <hr class="my-3">
          <p class="small fw-600 mb-2">What to copy from the source session:</p>
          <div class="row g-2">
            <?php foreach ([
              'fee_structures' => ['Fee Structures (class × fee × amount)', 'cash-coin', 'checked'],
              'class_subjects' => ['Subject Assignments (marks config + periods/week)', 'book-half', 'checked'],
              'routine_slots'  => ['Full Timetable (all routine slots)', 'calendar-week', ''],
              'working_days'   => ['Custom Working Days per Class/Section', 'calendar-check', 'checked'],
            ] as $val => [$label, $icon, $chk]): ?>
            <div class="col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="clone_items[]" value="<?= $val ?>" id="ci_<?= $val ?>" <?= $chk ?>>
                <label class="form-check-label small" for="ci_<?= $val ?>">
                  <i class="bi bi-<?= $icon ?> me-1 text-primary"></i><?= $label ?>
                </label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="alert alert-info mt-3 py-2 small">
            <i class="bi bi-info-circle me-1"></i>
            Student enrollments and payments are <strong>not</strong> copied — the new session starts fresh.
            Routine slots will use the same teachers and rooms as the source.
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-copy me-1"></i>Clone Session</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setCloneForm(id, name) {
  document.getElementById('clone-from-id').value = id;
  document.getElementById('clone-from-name').textContent = name;
  document.getElementById('clone-new-name').value = '';
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
