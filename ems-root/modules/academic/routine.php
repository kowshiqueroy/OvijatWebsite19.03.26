<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Class Routine';
$breadcrumbs = ['Academic' => null, 'Routine' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['routine.view']);

$pdo = db();
$session_id = int_param('session_id', (int)setting('current_session_id', 0), $_GET);
$class_id   = int_param('class_id', 0, $_GET);
$section_id = int_param('section_id', 0, $_GET);
$selected_date = $_GET['date'] ?? '';

$weekday_filter = '';
if ($selected_date) {
    $weekday_filter = date('l', strtotime($selected_date));
}

// ── AJAX: suggest available teachers for a subject/day/time ─────────────────
if (($_POST['action'] ?? '') === 'suggest_teachers') {
    header('Content-Type: application/json');
    csrf_check();
    $subj  = int_param('subject_id', 0, $_POST);
    $day   = $_POST['day'] ?? '';
    $start = $_POST['start'] ?? '';
    $end   = $_POST['end'] ?? '';
    $sess  = int_param('session_id', $session_id, $_POST);
    $excl  = int_param('exclude_slot', 0, $_POST); // slot id being edited

    $excludeClause = $excl ? "AND rs.id != $excl" : '';

    $experts = $pdo->prepare("
        SELECT sp.user_id AS id, CONCAT(sp.first_name,' ',sp.last_name) AS name,
               sp.designation, 1 AS is_expert,
               (SELECT COUNT(*) FROM routine_slots WHERE teacher_id=sp.user_id AND session_id=? AND status=1 AND is_substitute=0) AS week_load
        FROM staff_profiles sp
        JOIN teacher_subjects ts ON ts.teacher_id=sp.user_id AND ts.subject_id=?
        WHERE sp.status='active'
          AND NOT EXISTS (
              SELECT 1 FROM routine_slots rs
              WHERE rs.teacher_id=sp.user_id AND rs.day_of_week=? AND rs.session_id=? AND rs.status=1 AND rs.is_substitute=0 $excludeClause
                AND NOT (rs.end_time <= ? OR rs.start_time >= ?)
          )
        ORDER BY week_load ASC LIMIT 8
    ");
    $experts->execute([$sess, $subj, $day, $sess, $start, $end]);
    $expertList = $experts->fetchAll();

    $otherIds = array_column($expertList, 'id') ?: [0];
    $inP = implode(',', array_fill(0, count($otherIds), '?'));
    $others = $pdo->prepare("
        SELECT sp.user_id AS id, CONCAT(sp.first_name,' ',sp.last_name) AS name,
               sp.designation, 0 AS is_expert,
               (SELECT COUNT(*) FROM routine_slots WHERE teacher_id=sp.user_id AND session_id=? AND status=1 AND is_substitute=0) AS week_load
        FROM staff_profiles sp
        WHERE sp.status='active' AND sp.user_id NOT IN ($inP)
          AND NOT EXISTS (
              SELECT 1 FROM routine_slots rs
              WHERE rs.teacher_id=sp.user_id AND rs.day_of_week=? AND rs.session_id=? AND rs.status=1 AND rs.is_substitute=0 $excludeClause
                AND NOT (rs.end_time <= ? OR rs.start_time >= ?)
          )
        ORDER BY week_load ASC LIMIT 5
    ");
    $others->execute(array_merge([$sess], $otherIds, [$day, $sess, $start, $end]));
    echo json_encode(['experts' => $expertList, 'others' => $others->fetchAll()]);
    exit;
}

// ── POST: save custom working days for this class/section ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_working_days') {
    csrf_check();
    require_auth(['routine.manage']);
    $wdays = implode(',', array_filter((array)($_POST['wdays'] ?? []), fn($d) => in_array($d, ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'])));
    $secId = int_param('section_id', 0, $_POST) ?: null;
    $pdo->prepare('INSERT INTO section_working_days (session_id,class_id,section_id,working_days,updated_by)
                   VALUES (?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE working_days=VALUES(working_days), updated_by=VALUES(updated_by)')
        ->execute([$session_id, $class_id, $secId, $wdays, current_user_id()]);
    flash('success', 'Working days saved for this class/section.');
    header("Location: routine.php?session_id=$session_id&class_id=$class_id&section_id=$section_id");
    exit;
}

// ── POST: add substitute slot ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_substitute') {
    csrf_check();
    require_auth(['routine.manage']);
    $orig_slot_id = int_param('orig_slot_id', 0, $_POST);
    $sub_teacher  = int_param('sub_teacher_id', 0, $_POST) ?: null;
    $sub_date     = $_POST['sub_date'] ?? '';
    $sub_room     = int_param('sub_room_id', 0, $_POST) ?: null;

    if ($orig_slot_id && $sub_date) {
        $orig = $pdo->prepare('SELECT * FROM routine_slots WHERE id=?');
        $orig->execute([$orig_slot_id]);
        $orig = $orig->fetch();
        if ($orig) {
            // Remove any existing substitute for this slot+date
            $pdo->prepare('UPDATE routine_slots SET status=0 WHERE session_id=? AND section_id=? AND day_of_week=? AND start_time=? AND is_substitute=1 AND substitute_date=?')
                ->execute([$orig['session_id'], $orig['section_id'], $orig['day_of_week'], $orig['start_time'], $sub_date]);
            $pdo->prepare('INSERT INTO routine_slots (session_id,class_id,section_id,subject_id,teacher_id,room_id,day_of_week,start_time,end_time,is_substitute,substitute_date)
                           VALUES (?,?,?,?,?,?,?,?,?,1,?)')
                ->execute([$orig['session_id'],$orig['class_id'],$orig['section_id'],$orig['subject_id'],
                           $sub_teacher, $sub_room ?: $orig['room_id'],
                           $orig['day_of_week'],$orig['start_time'],$orig['end_time'],$sub_date]);
            flash('success', 'Substitute assigned for ' . date('d M Y', strtotime($sub_date)) . '.');
        }
    }
    header("Location: routine.php?session_id=$session_id&class_id=$class_id&section_id=$section_id&date=$sub_date");
    exit;
}

// Handle POST — add/edit/delete slot (only in permanent master mode, i.e., no date selected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('routine.manage') && !$selected_date) {
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
            $conflicts = [];
            $exclude   = $id ? "AND id != $id" : '';

            // Section double booking check
            $sc = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE section_id=? AND day_of_week=? AND status=1 AND NOT (end_time <= ? OR start_time >= ?) $exclude");
            $sc->execute([$sec, $day, $start, $end]);
            
            // Allow manual override for split/parallel subject slots
            $is_overridden = isset($_POST['override_conflict']);

            if ((int)$sc->fetchColumn() > 0 && !$is_overridden) {
                $conflicts[] = 'Section already has a class scheduled at this time. Check override to save as split subject.';
            }

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
    if ($selected_date) {
        // Query showing substitutions for this specific date
        $sl = $pdo->prepare("
            SELECT rs.*, s.subject_name, u.full_name as teacher_name, r.room_name
            FROM routine_slots rs
            JOIN subjects s ON s.id=rs.subject_id
            LEFT JOIN users u ON u.id=rs.teacher_id
            LEFT JOIN rooms r ON r.id=rs.room_id
            WHERE rs.session_id=:sess AND rs.class_id=:cls AND rs.section_id=:sec AND rs.status=1
              AND (
                  (rs.is_substitute = 1 AND rs.substitute_date = :date_val)
                  OR
                  (rs.is_substitute = 0 AND rs.substitute_date IS NULL AND NOT EXISTS (
                      SELECT 1 FROM routine_slots sub 
                      WHERE sub.session_id = rs.session_id 
                        AND sub.section_id = rs.section_id 
                        AND sub.day_of_week = rs.day_of_week 
                        AND sub.start_time = rs.start_time 
                        AND sub.is_substitute = 1 
                        AND sub.substitute_date = :date_val 
                        AND sub.status = 1
                  ))
              )
            ORDER BY rs.start_time
        ");
        $sl->execute([
            ':sess' => $session_id,
            ':cls' => $class_id,
            ':sec' => $section_id,
            ':date_val' => $selected_date
        ]);
        foreach ($sl->fetchAll() as $row) {
            $slots[$row['day_of_week']][] = $row;
        }
    } else {
        // Standard query showing master routine slots
        $sl = $pdo->prepare("
            SELECT rs.*, s.subject_name, u.full_name as teacher_name, r.room_name
            FROM routine_slots rs
            JOIN subjects s ON s.id=rs.subject_id
            LEFT JOIN users u ON u.id=rs.teacher_id
            LEFT JOIN rooms r ON r.id=rs.room_id
            WHERE rs.session_id=:sess AND rs.class_id=:cls AND rs.section_id=:sec AND rs.status=1 AND rs.is_substitute=0
            ORDER BY rs.start_time
        ");
        $sl->execute([':sess'=>$session_id,':cls'=>$class_id,':sec'=>$section_id]);
        foreach ($sl->fetchAll() as $row) {
            $slots[$row['day_of_week']][] = $row;
        }
    }
}

$subjects = $pdo->query('SELECT id, subject_name FROM subjects WHERE status=1 ORDER BY subject_name')->fetchAll();
$teachers = $pdo->query("SELECT sp.user_id as id, CONCAT(sp.first_name,' ',sp.last_name) as name FROM staff_profiles sp WHERE sp.status='active' ORDER BY name")->fetchAll();
$rooms    = $pdo->query('SELECT id, room_name, capacity FROM rooms WHERE status=1 ORDER BY room_name')->fetchAll();

// Load Custom Section Periods or Global default
$sec_periods = [];
if ($section_id) {
    $sec_periods_key = "section_periods_" . $section_id;
    $sec_periods_raw = setting($sec_periods_key);
    $sec_periods = $sec_periods_raw ? json_decode($sec_periods_raw, true) : null;
}
if (empty($sec_periods)) {
    $sec_periods_raw = setting('routine_periods');
    $sec_periods = $sec_periods_raw ? json_decode($sec_periods_raw, true) : [
        ['name' => 'Period 1', 'start' => '09:00', 'end' => '09:45', 'is_break' => 0],
        ['name' => 'Period 2', 'start' => '09:45', 'end' => '10:30', 'is_break' => 0],
        ['name' => 'Tiffin', 'start' => '10:30', 'end' => '11:00', 'is_break' => 1],
        ['name' => 'Period 3', 'start' => '11:00', 'end' => '11:45', 'is_break' => 0],
        ['name' => 'Period 4', 'start' => '11:45', 'end' => '12:30', 'is_break' => 0],
        ['name' => 'Prayer Break', 'start' => '12:30', 'end' => '13:30', 'is_break' => 1],
        ['name' => 'Period 5', 'start' => '13:30', 'end' => '14:15', 'is_break' => 0],
        ['name' => 'Period 6', 'start' => '14:15', 'end' => '15:00', 'is_break' => 0]
    ];
}

// Load custom working days (section → class → global WEEKENDS fallback)
$customDaysRow = null;
if ($class_id && $session_id) {
    $cd = $pdo->prepare('SELECT working_days FROM section_working_days WHERE session_id=? AND class_id=? AND (section_id=? OR section_id IS NULL) ORDER BY section_id DESC LIMIT 1');
    $cd->execute([$session_id, $class_id, $section_id ?: 0]);
    $customDaysRow = $cd->fetchColumn();
}
$workingDays = $customDaysRow
    ? array_map('trim', explode(',', $customDaysRow))
    : array_values(array_filter($days, fn($d) => !in_array($d, WEEKENDS)));

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-calendar-week-fill me-2 text-primary"></i>Class Routine
    <?php if ($customDaysRow): ?>
      <span class="badge bg-info ms-2" style="font-size:.7rem;">Custom Days</span>
    <?php endif; ?>
  </h1>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (has_permission('routine.manage') && $class_id && $section_id && !$selected_date): ?>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#slotModal" onclick="setSlotForm(null)">
        <i class="bi bi-plus-lg me-1"></i>Add Slot
      </button>
      <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#workingDaysModal">
        <i class="bi bi-calendar-check me-1"></i>Working Days
      </button>
    <?php endif; ?>
    <?php if ($class_id && $section_id): ?>
      <a href="print_routine.php?session_id=<?= $session_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>&date=<?= $selected_date ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-printer me-1"></i>Print
      </a>
    <?php endif; ?>
  </div>
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
      <div class="col-md-3">
        <label class="form-label small text-warning fw-600">View Date Routine (Substitutions)</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= e($selected_date) ?>" onchange="this.form.submit()">
      </div>
      <?php if ($selected_date): ?>
        <div class="col-auto">
          <a href="routine.php?session_id=<?= $session_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>" class="btn btn-xs btn-outline-danger">Clear Date</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($class_id && $section_id): ?>
<!-- Structured Weekday-by-Period Routine Grid -->
<div class="card">
  <div class="table-responsive">
    <table class="table table-bordered align-middle text-center mb-0" style="font-size:0.85rem;">
      <thead class="table-dark">
        <tr>
          <th style="width: 130px;">Day</th>
          <?php foreach ($sec_periods as $p): ?>
            <th>
              <div><?= e($p['name']) ?></div>
              <small class="fw-normal opacity-75"><?= e($p['start']) ?> - <?= e($p['end']) ?></small>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($days as $day):
          $isWorking = in_array($day, $workingDays);
          $isToday = $selected_date && ($weekday_filter === $day);
          $rowClass = !$isWorking ? 'table-secondary text-muted' : ($isToday ? 'table-primary-subtle' : '');
        ?>
        <tr class="<?= $rowClass ?>">
          <td class="fw-bold bg-light">
            <?= $day ?>
            <?php if (!$isWorking): ?><br><small class="text-muted">Weekend</small><?php endif; ?>
            <?php if ($isToday): ?><br><span class="badge bg-primary text-white" style="font-size:0.6rem;">SELECTED DATE</span><?php endif; ?>
          </td>
          <?php foreach ($sec_periods as $p):
            if (!empty($p['is_break'])): ?>
              <td class="bg-secondary-subtle text-muted fw-bold py-3" style="font-size: 0.75rem; letter-spacing: 0.05rem;">
                <?= strtoupper(e($p['name'])) ?>
              </td>
            <?php else:
              // Match slots for this start time
              $cell_slots = [];
              $daySlots = $slots[$day] ?? [];
              foreach ($daySlots as $slot) {
                  // Format time to compare HH:MM
                  if (date('H:i', strtotime($slot['start_time'])) === date('H:i', strtotime($p['start']))) {
                      $cell_slots[] = $slot;
                  }
              }
            ?>
              <td class="p-2">
                <?php if (empty($cell_slots)): ?>
                  <span class="text-muted opacity-25">—</span>
                <?php else: ?>
                  <div class="d-flex flex-column gap-2 justify-content-center align-items-center">
                    <?php foreach ($cell_slots as $slot): 
                      $isSub = !empty($slot['is_substitute']);
                      $border = $isSub ? 'border-warning bg-warning-subtle' : 'border-light-subtle bg-light';
                    ?>
                      <div class="p-2 border rounded text-start shadow-sm <?= $border ?>" style="min-width: 140px; font-size: 0.78rem;">
                        <div class="fw-bold text-dark"><?= e($slot['subject_name']) ?></div>
                        <div class="text-secondary small mt-1">
                          <i class="bi bi-person me-1"></i><?= e($slot['teacher_name'] ?? 'No teacher') ?>
                        </div>
                        <?php if ($slot['room_name']): ?>
                          <div class="text-secondary small">
                            <i class="bi bi-geo-alt me-1"></i><?= e($slot['room_name']) ?>
                          </div>
                        <?php endif; ?>
                        <?php if ($isSub): ?>
                          <span class="badge bg-warning text-dark p-1 mt-1" style="font-size: 0.6rem; font-weight:bold;">SUBSTITUTE</span>
                        <?php endif; ?>
                        
                        <?php if (has_permission('routine.manage') && !$selected_date): ?>
                          <div class="mt-2 d-flex gap-1 flex-wrap no-print">
                            <button class="btn btn-xs btn-outline-primary" title="Edit slot"
                                    data-bs-toggle="modal" data-bs-target="#slotModal"
                                    onclick="setSlotForm(<?= htmlspecialchars(json_encode($slot),ENT_QUOTES) ?>)">
                              <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-xs btn-outline-warning" title="Set substitute for a date"
                                    onclick="openSubstModal(<?= $slot['id'] ?>, '<?= e(addslashes($slot['subject_name'])) ?>', '<?= e(addslashes($day)) ?>')">
                              <i class="bi bi-person-fill-gear"></i>
                            </button>
                            <form method="POST" class="d-inline" data-no-protect>
                              <?= csrf_field() ?>
                              <input type="hidden" name="action" value="delete_slot">
                              <input type="hidden" name="id" value="<?= $slot['id'] ?>">
                              <button type="submit" class="btn btn-xs btn-outline-danger" title="Remove slot"
                                      onclick="return confirm('Remove this slot from the routine?')"><i class="bi bi-x"></i></button>
                            </form>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-calendar-week"></i><p>Select class and section to view or build the routine.</p></div></div></div>
<?php endif; ?>

<?php
// Pass PHP data to JS
$jsWorkingDays = json_encode($workingDays);
$jsPeriods     = json_encode($sec_periods);
$jsSessionId   = $session_id;
$jsSectionId   = $section_id;
?>

<!-- ── Working Days Config Modal ─────────────────────────── -->
<?php if (has_permission('routine.manage') && $class_id): ?>
<div class="modal fade" id="workingDaysModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_working_days">
        <input type="hidden" name="section_id" value="<?= $section_id ?>">
        <div class="modal-header bg-warning py-2">
          <h6 class="modal-title fw-600"><i class="bi bi-calendar-check me-2"></i>Custom Working Days</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">
            Select working days for <strong>Class <?= e(array_column($classes,'class_name','id')[$class_id] ?? '') ?><?= $section_id ? ', ' . e(array_column($sections,'section_name','id')[$section_id] ?? '') : ' (all sections)' ?></strong>.
            Leave unchecked = use global setting (weekends: Fri & Sat).
          </p>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach (['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'] as $wd): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="wdays[]" value="<?= $wd ?>"
                     id="wd_<?= $wd ?>" <?= in_array($wd, $workingDays) ? 'checked' : '' ?>>
              <label class="form-check-label" for="wd_<?= $wd ?>"><?= $wd ?></label>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="alert alert-info mt-3 py-2 small">
            <i class="bi bi-info-circle me-1"></i>This setting applies to the routine grid and conflict detection for this class/section/session.
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning btn-sm">Save Working Days</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Substitute Modal ───────────────────────────────────── -->
<?php if (has_permission('routine.manage')): ?>
<div class="modal fade" id="substModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_substitute">
        <input type="hidden" name="orig_slot_id" id="sub_slot_id">
        <div class="modal-header bg-warning py-2">
          <h6 class="modal-title fw-600"><i class="bi bi-person-fill-gear me-2"></i>Assign Substitute Teacher</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-3">Subject: <strong id="sub_subject_name" class="text-dark"></strong> &nbsp;|&nbsp; Day: <strong id="sub_day_name" class="text-dark"></strong></p>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-600">Date <span class="text-danger">*</span></label>
              <input type="date" name="sub_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Substitute Teacher</label>
              <select name="sub_teacher_id" class="form-select form-select-sm">
                <option value="">— Keep original / None —</option>
                <?php foreach ($teachers as $t): ?>
                  <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-12">
              <label class="form-label small fw-600">Room (optional override)</label>
              <select name="sub_room_id" class="form-select form-select-sm">
                <option value="">— Same room —</option>
                <?php foreach ($rooms as $r): ?>
                  <option value="<?= $r['id'] ?>"><?= e($r['room_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-check-lg me-1"></i>Save Substitute</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Add/Edit Slot Modal ────────────────────────────────── -->
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
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="slotModalTitle">Add Period</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-600">Day <span class="text-danger">*</span></label>
              <select name="day_of_week" id="sl_day" class="form-select form-select-sm" required onchange="clearSuggestions()">
                <?php foreach ($days as $d): ?>
                  <option value="<?= $d ?>" <?= !in_array($d,$workingDays)?'class="text-muted"':'' ?>><?= $d ?><?= !in_array($d,$workingDays)?' (off)':'' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-600">Start Time <span class="text-danger">*</span></label>
              <select id="sl_start_sel" class="form-select form-select-sm" onchange="document.getElementById('sl_start').value=this.value;clearSuggestions()">
                <option value="">— Pick period —</option>
                <?php foreach ($sec_periods as $p): if (empty($p['is_break'])): ?>
                <option value="<?= $p['start'] ?>"><?= e($p['name']) ?> (<?= $p['start'] ?>)</option>
                <?php endif; endforeach; ?>
              </select>
              <input type="time" name="start_time" id="sl_start" class="form-control form-control-sm mt-1" required oninput="clearSuggestions()">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-600">End Time <span class="text-danger">*</span></label>
              <input type="time" name="end_time" id="sl_end" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Subject <span class="text-danger">*</span></label>
              <select name="subject_id" id="sl_subj" class="form-select form-select-sm" required onchange="clearSuggestions()">
                <option value="">— Select subject —</option>
                <?php foreach ($subjects as $sub): ?>
                  <option value="<?= $sub['id'] ?>"><?= e($sub['subject_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Teacher
                <button type="button" class="btn btn-xs btn-outline-primary ms-2" id="suggestBtn" onclick="suggestTeachers()" title="Auto-suggest available expert teachers">
                  <i class="bi bi-magic me-1"></i>Suggest
                </button>
              </label>
              <select name="teacher_id" id="sl_teacher" class="form-select form-select-sm">
                <option value="">— No teacher assigned —</option>
                <?php foreach ($teachers as $t): ?>
                  <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <!-- Suggestion panel -->
              <div id="suggest-panel" class="mt-2 d-none">
                <div class="small fw-600 text-success mb-1"><i class="bi bi-stars me-1"></i>Available Expert Teachers:</div>
                <div id="suggest-list" class="d-flex flex-wrap gap-1"></div>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Room</label>
              <select name="room_id" id="sl_room" class="form-select form-select-sm">
                <option value="">— No room —</option>
                <?php foreach ($rooms as $r): ?>
                  <option value="<?= $r['id'] ?>"><?= e($r['room_name']) ?> (cap: <?= $r['capacity'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="override_conflict" value="1" id="overrideConflict">
                <label class="form-check-label small text-warning fw-600" for="overrideConflict">
                  Force save (allow parallel/split classes)
                </label>
              </div>
            </div>
          </div>
          <div class="alert alert-light border mt-3 py-2 small">
            <i class="bi bi-shield-check-fill text-success me-1"></i>
            Conflicts (teacher double-booking, room clash) are auto-detected on save.
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Save Slot</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const ROUTINE_PERIODS   = <?= $jsPeriods ?>;
const ROUTINE_SESS_ID   = <?= $jsSessionId ?>;
const ROUTINE_SECTION_ID= <?= $jsSectionId ?>;
const ROUTINE_CSRF      = document.querySelector('meta[name=csrf-token]')?.content ?? '';

function setSlotForm(sl) {
  document.getElementById('slotModalTitle').textContent = sl ? 'Edit Period' : 'Add Period';
  document.getElementById('sl_id').value      = sl ? sl.id : 0;
  document.getElementById('sl_day').value     = sl ? sl.day_of_week : '<?= $workingDays[0] ?? 'Saturday' ?>';
  const start = sl ? sl.start_time.substring(0,5) : '';
  const end   = sl ? sl.end_time.substring(0,5)   : '';
  document.getElementById('sl_start').value   = start;
  document.getElementById('sl_end').value     = end;
  document.getElementById('sl_subj').value    = sl ? sl.subject_id : '';
  document.getElementById('sl_teacher').value = sl ? (sl.teacher_id || '') : '';
  document.getElementById('sl_room').value    = sl ? (sl.room_id || '') : '';
  document.getElementById('overrideConflict').checked = false;
  // Sync period dropdown
  const sel = document.getElementById('sl_start_sel');
  if (sel) sel.value = start || '';
  clearSuggestions();
}

// Auto-fill end time when period is picked
document.getElementById('sl_start_sel')?.addEventListener('change', function () {
  const pName = this.options[this.selectedIndex]?.text ?? '';
  const pObj  = ROUTINE_PERIODS.find(p => p.start === this.value && !p.is_break);
  if (pObj) document.getElementById('sl_end').value = pObj.end;
});

async function suggestTeachers() {
  const subj  = document.getElementById('sl_subj').value;
  const day   = document.getElementById('sl_day').value;
  const start = document.getElementById('sl_start').value;
  const end   = document.getElementById('sl_end').value;
  const slotId= document.getElementById('sl_id').value;
  if (!subj || !day || !start) { EMS.showError('Select subject, day and start time before suggesting teachers.', null, 3000); return; }
  document.getElementById('suggestBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  const body = new FormData();
  body.append('action','suggest_teachers');
  body.append('_csrf', ROUTINE_CSRF);
  body.append('subject_id', subj);
  body.append('day', day);
  body.append('start', start);
  body.append('end', end || '23:59');
  body.append('session_id', ROUTINE_SESS_ID);
  body.append('exclude_slot', slotId);

  try {
    const res  = await fetch('routine.php', { method:'POST', body });
    const data = await res.json();
    const panel = document.getElementById('suggest-panel');
    const list  = document.getElementById('suggest-list');
    list.innerHTML = '';
    const all = [
      ...data.experts.map(t => ({ ...t, badge: '⭐ Expert' })),
      ...data.others.map(t => ({ ...t, badge: 'Available' }))
    ];
    if (!all.length) {
      list.innerHTML = '<span class="text-danger small">No available teachers for this slot.</span>';
    } else {
      all.forEach(t => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-xs ' + (t.is_expert ? 'btn-success' : 'btn-outline-secondary');
        btn.innerHTML = `${t.badge} ${t.name} <small class="opacity-75">(${t.week_load} cls/wk)</small>`;
        btn.onclick = () => {
          document.getElementById('sl_teacher').value = t.id;
          panel.classList.add('d-none');
        };
        list.appendChild(btn);
      });
    }
    panel.classList.remove('d-none');
  } catch(e) {
    EMS.showError('Could not load suggestions.', e.message);
  } finally {
    document.getElementById('suggestBtn').innerHTML = '<i class="bi bi-magic me-1"></i>Suggest';
  }
}

function clearSuggestions() {
  document.getElementById('suggest-panel')?.classList.add('d-none');
}

function openSubstModal(slotId, subjectName, day) {
  document.getElementById('sub_slot_id').value       = slotId;
  document.getElementById('sub_subject_name').textContent = subjectName;
  document.getElementById('sub_day_name').textContent = day;
  new bootstrap.Modal(document.getElementById('substModal')).show();
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
