<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Class Routine';
$breadcrumbs = ['Academic' => null, 'Routine' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['routine.view']);

$pdo = db();
$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);
$class_id   = int_param('class_id', 0, $_GET);
$section_id = int_param('section_id', 0, $_GET);

// Handle POST — add/edit/delete slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('routine.manage')) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_slot') {
        $id         = int_param('id', 0, $_POST);
        $sess       = int_param('session_id', 0, $_POST);
        $cls        = int_param('class_id', 0, $_POST);
        $sec        = int_param('section_id', 0, $_POST);
        $subj       = int_param('subject_id', 0, $_POST);
        $teacher    = int_param('teacher_id', 0, $_POST) ?: null;
        $room       = int_param('room_id', 0, $_POST) ?: null;
        $day        = $_POST['day_of_week'] ?? '';
        $start      = $_POST['start_time'] ?? '';
        $end        = $_POST['end_time'] ?? '';

        if ($sess && $cls && $sec && $subj && $day && $start && $end) {
            // ── Conflict check ────────────────────────────────────────
            $conflicts = [];
            $exclude   = $id ? "AND id != $id" : '';

            if ($teacher) {
                $tc = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE teacher_id=? AND day_of_week=? AND status=1 AND NOT (end_time <= ? OR start_time >= ?) $exclude");
                $tc->execute([$teacher, $day, $start, $end]);
                if ((int)$tc->fetchColumn()) $conflicts[] = 'Teacher already has a class at this time.';
            }
            if ($room) {
                $rc = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE room_id=? AND day_of_week=? AND status=1 AND NOT (end_time <= ? OR start_time >= ?) $exclude");
                $rc->execute([$room, $day, $start, $end]);
                if ((int)$rc->fetchColumn()) $conflicts[] = 'Room is already occupied at this time.';
            }
            $sc = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE section_id=? AND day_of_week=? AND status=1 AND NOT (end_time <= ? OR start_time >= ?) $exclude");
            $sc->execute([$sec, $day, $start, $end]);
            if ((int)$sc->fetchColumn()) $conflicts[] = 'Section already has a class at this time.';

            if ($conflicts) {
                flash('error', implode(' ', $conflicts));
            } else {
                if ($id) {
                    $pdo->prepare('UPDATE routine_slots SET session_id=?,class_id=?,section_id=?,subject_id=?,teacher_id=?,room_id=?,day_of_week=?,start_time=?,end_time=? WHERE id=?')
                        ->execute([$sess,$cls,$sec,$subj,$teacher,$room,$day,$start,$end,$id]);
                    flash('success', 'Slot updated.');
                } else {
                    $pdo->prepare('INSERT INTO routine_slots (session_id,class_id,section_id,subject_id,teacher_id,room_id,day_of_week,start_time,end_time) VALUES (?,?,?,?,?,?,?,?,?)')
                        ->execute([$sess,$cls,$sec,$subj,$teacher,$room,$day,$start,$end]);
                    flash('success', 'Slot added.');
                }
            }
        }
    } elseif ($action === 'delete_slot') {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('UPDATE routine_slots SET status=0 WHERE id=:id')->execute([':id'=>$id]);
        flash('success', 'Slot removed.');
    }
    header("Location: routine.php?session_id=$session_id&class_id=$class_id&section_id=$section_id");
    exit;
}

$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id, class_name FROM classes WHERE status=1 ORDER BY display_order, class_numeric')->fetchAll();
$sections = $class_id
    ? $pdo->prepare('SELECT id, section_name FROM sections WHERE class_id=:c AND status=1 ORDER BY section_name')
    : null;
if ($sections) { $sections->execute([':c'=>$class_id]); $sections = $sections->fetchAll(); }
else $sections = [];

// Load routine slots for selected class/section/session
$slots = [];
$days = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
if ($class_id && $section_id && $session_id) {
    $sl = $pdo->prepare(
        'SELECT rs.*, s.subject_name, u.full_name as teacher_name, r.room_name
         FROM routine_slots rs
         JOIN subjects s ON s.id=rs.subject_id
         LEFT JOIN users u ON u.id=rs.teacher_id
         LEFT JOIN rooms r ON r.id=rs.room_id
         WHERE rs.session_id=:sess AND rs.class_id=:cls AND rs.section_id=:sec AND rs.status=1
         ORDER BY FIELD(rs.day_of_week,"Saturday","Sunday","Monday","Tuesday","Wednesday","Thursday","Friday"), rs.start_time'
    );
    $sl->execute([':sess'=>$session_id,':cls'=>$class_id,':sec'=>$section_id]);
    foreach ($sl->fetchAll() as $row) $slots[$row['day_of_week']][] = $row;
}

$subjects = $pdo->query('SELECT id, subject_name FROM subjects WHERE status=1 ORDER BY subject_name')->fetchAll();
$teachers = $pdo->query("SELECT sp.user_id as id, CONCAT(sp.first_name,' ',sp.last_name) as name FROM staff_profiles sp WHERE sp.status='active' ORDER BY name")->fetchAll();
$rooms    = $pdo->query('SELECT id, room_name, capacity FROM rooms WHERE status=1 ORDER BY room_name')->fetchAll();

$workingDays = array_filter($days, fn($d) => !in_array($d, WEEKENDS));

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-calendar-week-fill me-2 text-primary"></i>Class Routine</h1>
  <?php if (has_permission('routine.manage') && $class_id && $section_id): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#slotModal" onclick="setSlotForm(null)">
    <i class="bi bi-plus-lg me-1"></i>Add Slot
  </button>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label small">Session</label>
        <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sessions as $sess): ?>
            <option value="<?= $sess['id'] ?>" <?= $session_id==$sess['id']?'selected':'' ?>><?= e($sess['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">— Select —</option>
          <?php foreach ($classes as $cls): ?>
            <option value="<?= $cls['id'] ?>" <?= $class_id==$cls['id']?'selected':'' ?>><?= e($cls['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!empty($sections)): ?>
      <div class="col-md-2">
        <label class="form-label small">Section</label>
        <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">— Select —</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>" <?= $section_id==$sec['id']?'selected':'' ?>><?= e($sec['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-auto">
        <button onclick="window.print()" type="button" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer"></i></button>
      </div>
    </form>
  </div>
</div>

<?php if ($class_id && $section_id): ?>
<!-- Routine grid -->
<div class="card">
  <div class="table-responsive">
    <table class="table table-bordered mb-0">
      <thead class="table-dark">
        <tr>
          <th style="width:110px;">Day</th>
          <th>Periods</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($days as $day):
          $isWorking = !in_array($day, WEEKENDS);
          $daySlots  = $slots[$day] ?? [];
        ?>
        <tr class="<?= !$isWorking ? 'table-secondary' : '' ?>">
          <td class="fw-700 align-middle text-center py-3">
            <?= $day ?>
            <?php if (!$isWorking): ?><br><small class="text-muted">Weekend</small><?php endif; ?>
          </td>
          <td>
            <?php if (!$isWorking): ?>
              <span class="text-muted small">—</span>
            <?php elseif (empty($daySlots)): ?>
              <span class="text-muted small">No classes scheduled</span>
            <?php else: ?>
            <div class="d-flex flex-wrap gap-2 py-1">
              <?php foreach ($daySlots as $slot): ?>
              <div class="border rounded p-2" style="min-width:160px;background:#f8fafc;">
                <div class="fw-700 small"><?= substr($slot['start_time'],0,5) ?>–<?= substr($slot['end_time'],0,5) ?></div>
                <div class="fw-600"><?= e($slot['subject_name']) ?></div>
                <div class="text-muted small"><?= e($slot['teacher_name'] ?? 'No teacher') ?></div>
                <?php if ($slot['room_name']): ?><div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= e($slot['room_name']) ?></div><?php endif; ?>
                <?php if (has_permission('routine.manage')): ?>
                <div class="mt-1 d-flex gap-1">
                  <button class="btn btn-xs btn-outline-primary" style="font-size:.65rem;padding:.1rem .3rem;"
                          data-bs-toggle="modal" data-bs-target="#slotModal"
                          onclick="setSlotForm(<?= htmlspecialchars(json_encode($slot),ENT_QUOTES) ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_slot">
                    <input type="hidden" name="id" value="<?= $slot['id'] ?>">
                    <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.65rem;padding:.1rem .3rem;"
                            data-confirm="Remove this slot?"><i class="bi bi-x"></i></button>
                  </form>
                </div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-calendar-week"></i><p>Select class and section to view or build the routine.</p></div></div></div>
<?php endif; ?>

<!-- Slot Modal -->
<div class="modal fade" id="slotModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_slot">
        <input type="hidden" name="id" id="sl_id" value="0">
        <input type="hidden" name="session_id" value="<?= $session_id ?>">
        <input type="hidden" name="class_id" value="<?= $class_id ?>">
        <input type="hidden" name="section_id" value="<?= $section_id ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="slotModalTitle">Add Period</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Day <span class="text-danger">*</span></label>
              <select name="day_of_week" id="sl_day" class="form-select" required>
                <?php foreach ($days as $d): ?>
                  <option value="<?= $d ?>" <?= in_array($d, WEEKENDS)?'disabled':'' ?>><?= $d ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Start Time <span class="text-danger">*</span></label>
              <input type="time" name="start_time" id="sl_start" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">End Time <span class="text-danger">*</span></label>
              <input type="time" name="end_time" id="sl_end" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Subject <span class="text-danger">*</span></label>
              <select name="subject_id" id="sl_subj" class="form-select" required>
                <option value="">— Select —</option>
                <?php foreach ($subjects as $sub): ?>
                  <option value="<?= $sub['id'] ?>"><?= e($sub['subject_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Teacher</label>
              <select name="teacher_id" id="sl_teacher" class="form-select">
                <option value="">— No teacher —</option>
                <?php foreach ($teachers as $t): ?>
                  <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Room</label>
              <select name="room_id" id="sl_room" class="form-select">
                <option value="">— No room —</option>
                <?php foreach ($rooms as $r): ?>
                  <option value="<?= $r['id'] ?>"><?= e($r['room_name']) ?> (<?= $r['capacity'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="alert alert-info mt-3 small d-flex gap-2">
            <i class="bi bi-shield-check-fill"></i>
            Conflict check runs on save — teacher, room, and section overlaps are automatically blocked.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Slot</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function setSlotForm(sl) {
  document.getElementById('slotModalTitle').textContent = sl ? 'Edit Period' : 'Add Period';
  document.getElementById('sl_id').value     = sl ? sl.id : 0;
  document.getElementById('sl_day').value    = sl ? sl.day_of_week : 'Saturday';
  document.getElementById('sl_start').value  = sl ? sl.start_time : '';
  document.getElementById('sl_end').value    = sl ? sl.end_time : '';
  document.getElementById('sl_subj').value   = sl ? sl.subject_id : '';
  document.getElementById('sl_teacher').value= sl ? (sl.teacher_id||'') : '';
  document.getElementById('sl_room').value   = sl ? (sl.room_id||'') : '';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
