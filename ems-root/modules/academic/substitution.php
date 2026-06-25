<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Daily Substitution Planner';
$breadcrumbs = ['Academic' => 'classes.php', 'Substitution Planner' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['routine.view']);

$pdo = db();
$session_id = (int)setting('current_session_id', 0);
$selected_date = $_GET['date'] ?? date('Y-m-d');
$weekday = date('l', strtotime($selected_date));

// Handle POST actions (Declare absent / Save substitutions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('routine.manage')) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'declare_absent') {
        $teacher_id = int_param('teacher_id', 0, $_POST);
        $date = $_POST['date'] ?? date('Y-m-d');
        if ($teacher_id && $date) {
            $stmt = $pdo->prepare("
                INSERT INTO staff_attendance (staff_id, attendance_date, status) 
                VALUES (?, ?, 'absent')
                ON DUPLICATE KEY UPDATE status = 'absent'
            ");
            $stmt->execute([$teacher_id, $date]);
            flash('success', 'Teacher declared absent for today.');
        }
    } elseif ($action === 'save_substitution') {
        $slot_id = int_param('slot_id', 0, $_POST);
        $substitute_teacher_id = int_param('substitute_teacher_id', 0, $_POST) ?: null;
        $date = $_POST['date'] ?? date('Y-m-d');
        
        // Fetch the original master slot
        $stmt = $pdo->prepare("SELECT * FROM routine_slots WHERE id = ?");
        $stmt->execute([$slot_id]);
        $orig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($orig && $date) {
            // Check if there is already a substitute slot for this master slot on this date
            $chk = $pdo->prepare("
                SELECT id FROM routine_slots 
                WHERE class_id = ? AND section_id = ? AND day_of_week = ? 
                  AND start_time = ? AND end_time = ? 
                  AND is_substitute = 1 AND substitute_date = ? AND status = 1
            ");
            $chk->execute([
                $orig['class_id'], 
                $orig['section_id'], 
                $orig['day_of_week'], 
                $orig['start_time'], 
                $orig['end_time'], 
                $date
            ]);
            $existing_sub_id = $chk->fetchColumn();
            
            if ($substitute_teacher_id) {
                if ($existing_sub_id) {
                    // Update substitute teacher
                    $upd = $pdo->prepare("UPDATE routine_slots SET teacher_id = ? WHERE id = ?");
                    $upd->execute([$substitute_teacher_id, $existing_sub_id]);
                } else {
                    // Insert new substitute slot
                    $ins = $pdo->prepare("
                        INSERT INTO routine_slots 
                        (session_id, class_id, section_id, subject_id, teacher_id, room_id, day_of_week, start_time, end_time, is_substitute, substitute_date, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 1)
                    ");
                    $ins->execute([
                        $orig['session_id'],
                        $orig['class_id'],
                        $orig['section_id'],
                        $orig['subject_id'],
                        $substitute_teacher_id,
                        $orig['room_id'],
                        $orig['day_of_week'],
                        $orig['start_time'],
                        $orig['end_time'],
                        $date
                    ]);
                }
                flash('success', 'Substitution assigned successfully.');
            } else {
                // Remove substitution (deactivate date-specific slot)
                if ($existing_sub_id) {
                    $del = $pdo->prepare("UPDATE routine_slots SET status = 0 WHERE id = ?");
                    $del->execute([$existing_sub_id]);
                }
                flash('success', 'Substitution cleared.');
            }
        }
    }
    header("Location: substitution.php?date=" . $date);
    exit;
}

// Fetch all active teachers list for the "Declare Absent" dropdown
$all_teachers = $pdo->query("
    SELECT u.id, CONCAT(sp.first_name, ' ', sp.last_name) as name, sp.designation
    FROM staff_profiles sp
    JOIN users u ON u.id = sp.user_id
    WHERE sp.status='active' AND (sp.designation LIKE '%teacher%' OR sp.designation LIKE '%lecturer%' OR sp.designation LIKE '%faculty%' OR sp.designation LIKE '%instructor%')
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch teachers marked absent/leave on this date
$absents_stmt = $pdo->prepare("
    SELECT u.id, CONCAT(sp.first_name, ' ', sp.last_name) as name, sp.designation, sa.status as att_status
    FROM staff_attendance sa
    JOIN users u ON u.id = sa.staff_id
    JOIN staff_profiles sp ON sp.user_id = sa.staff_id
    WHERE sa.attendance_date = ? AND sa.status IN ('absent', 'on_leave')
");
$absents_stmt->execute([$selected_date]);
$absents = $absents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch routine slots for these absent teachers today
$affected_slots = [];
if (!empty($absents)) {
    $absent_ids = array_column($absents, 'id');
    $placeholders = implode(',', array_fill(0, count($absent_ids), '?'));
    
    $stmt = $pdo->prepare("
        SELECT rs.*, s.subject_name, c.class_name, sec.section_name, 
               CONCAT(sp.first_name, ' ', sp.last_name) as absent_teacher_name,
               (
                   SELECT sub.teacher_id FROM routine_slots sub 
                   WHERE sub.class_id = rs.class_id AND sub.section_id = rs.section_id 
                     AND sub.day_of_week = rs.day_of_week AND sub.start_time = rs.start_time 
                     AND sub.is_substitute = 1 AND sub.substitute_date = ? AND sub.status = 1
                   LIMIT 1
               ) as substitute_teacher_id
        FROM routine_slots rs
        JOIN subjects s ON s.id = rs.subject_id
        JOIN classes c ON c.id = rs.class_id
        JOIN sections sec ON sec.id = rs.section_id
        JOIN staff_profiles sp ON sp.user_id = rs.teacher_id
        WHERE rs.session_id = ? AND rs.teacher_id IN ($placeholders) 
          AND rs.day_of_week = ? AND rs.status = 1 AND rs.is_substitute = 0
        ORDER BY rs.start_time
    ");
    
    $params = array_merge([$selected_date, $session_id], $absent_ids, [$weekday]);
    $stmt->execute($params);
    $affected_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper to fetch and rank substitute suggestions
function get_substitute_suggestions($pdo, $slot, $selected_date, $session_id) {
    $start_time = $slot['start_time'];
    $end_time = $slot['end_time'];
    $day = $slot['day_of_week'];
    $subject_id = $slot['subject_id'];
    
    // Get all absent/leave teachers today to exclude them
    $absent_stmt = $pdo->prepare("
        SELECT staff_id FROM staff_attendance 
        WHERE attendance_date = ? AND status IN ('absent', 'on_leave')
    ");
    $absent_stmt->execute([$selected_date]);
    $todays_absents = $absent_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    
    // Also exclude original teacher
    $todays_absents[] = $slot['teacher_id'];
    
    // Query all active teachers
    $teachers = $pdo->query("
        SELECT u.id, CONCAT(sp.first_name, ' ', sp.last_name) as name, sp.max_classes_per_day, sp.max_classes_per_week
        FROM staff_profiles sp
        JOIN users u ON u.id = sp.user_id
        WHERE sp.status = 'active' AND (sp.designation LIKE '%teacher%' OR sp.designation LIKE '%lecturer%' OR sp.designation LIKE '%faculty%' OR sp.designation LIKE '%instructor%')
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $suggestions = [];
    
    foreach ($teachers as $t) {
        $t_id = $t['id'];
        if (in_array($t_id, $todays_absents)) continue;
        
        // Check if teacher is busy during this period on this date
        $busy_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM routine_slots rs
            WHERE rs.teacher_id = :t_id AND rs.day_of_week = :day AND rs.status = 1 AND rs.session_id = :sess
              AND NOT (rs.end_time <= :start OR rs.start_time >= :end)
              AND (
                  (rs.is_substitute = 0 AND rs.substitute_date IS NULL AND NOT EXISTS (
                      SELECT 1 FROM routine_slots sub 
                      WHERE sub.class_id = rs.class_id AND sub.section_id = rs.section_id 
                        AND sub.day_of_week = rs.day_of_week AND sub.start_time = rs.start_time 
                        AND sub.is_substitute = 1 AND sub.substitute_date = :date AND sub.status = 1
                  ))
                  OR
                  (rs.is_substitute = 1 AND rs.substitute_date = :date)
              )
        ");
        $busy_stmt->execute([
            ':t_id' => $t_id,
            ':day' => $day,
            ':sess' => $session_id,
            ':start' => $start_time,
            ':end' => $end_time,
            ':date' => $selected_date
        ]);
        if ((int)$busy_stmt->fetchColumn() > 0) {
            continue;
        }
        
        // Load counts
        $wk_stmt = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE teacher_id = ? AND session_id = ? AND status = 1 AND is_substitute = 0");
        $wk_stmt->execute([$t_id, $session_id]);
        $weekly_load = (int)$wk_stmt->fetchColumn();
        
        $dy_stmt = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE teacher_id = ? AND session_id = ? AND day_of_week = ? AND status = 1 AND is_substitute = 0");
        $dy_stmt->execute([$t_id, $session_id, $day]);
        $daily_load = (int)$dy_stmt->fetchColumn();
        
        // Check expertise
        $exp_stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = ? AND subject_id = ?");
        $exp_stmt->execute([$t_id, $subject_id]);
        $is_expert = (int)$exp_stmt->fetchColumn() > 0;
        
        $suggestions[] = [
            'id' => $t_id,
            'name' => $t['name'],
            'is_expert' => $is_expert,
            'weekly_load' => $weekly_load,
            'daily_load' => $daily_load,
            'max_week' => $t['max_classes_per_week'] ?: 20,
            'max_day' => $t['max_classes_per_day'] ?: 4
        ];
    }
    
    // Sort suggestions: Experts first, then lower weekly workload
    usort($suggestions, function($a, $b) {
        if ($a['is_expert'] !== $b['is_expert']) {
            return $b['is_expert'] <=> $a['is_expert'];
        }
        return $a['weekly_load'] <=> $b['weekly_load'];
    });
    
    return $suggestions;
}

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-shuffle me-2 text-primary"></i>Daily Substitution Planner</h1>
  <div class="d-flex gap-2">
    <a href="master_routine.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-grid-3x3-gap me-1"></i>Master Planner</a>
    <a href="routine.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-event me-1"></i>Class Routines</a>
  </div>
</div>

<!-- Date & weekday indicator -->
<div class="card mb-4 border-0 shadow-sm">
  <div class="card-body py-2 d-flex align-items-center gap-4">
    <form method="GET" class="d-flex align-items-center gap-2 mb-0">
      <label class="form-label mb-0 fw-bold small text-muted">Date:</label>
      <input type="date" name="date" class="form-control form-control-sm" value="<?= e($selected_date) ?>" onchange="this.form.submit()" style="width: auto;">
    </form>
    <span class="badge bg-primary fs-6"><?= $weekday ?></span>
    <span class="text-muted small"><?= date('d M Y', strtotime($selected_date)) ?></span>
    <?php if (!empty($absents)): ?>
      <span class="badge bg-danger fs-6"><i class="bi bi-person-x me-1"></i><?= count($absents) ?> Absent</span>
    <?php else: ?>
      <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>All Present</span>
    <?php endif; ?>
  </div>
</div>

<!-- Tab Navigation -->
<ul class="nav nav-tabs mb-4" id="subTabs">
  <li class="nav-item">
    <a class="nav-link active" data-bs-toggle="tab" href="#tab-absent">
      <i class="bi bi-person-dash me-2 text-danger"></i>Absent & Substitutions
      <?php if (!empty($absents)): ?><span class="badge bg-danger ms-1"><?= count($absents) ?></span><?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#tab-today">
      <i class="bi bi-calendar-day me-2 text-primary"></i>Today's Full Schedule
    </a>
  </li>
</ul>

<div class="tab-content">

<!-- TAB 1: Absent Teachers & Substitutions -->
<div class="tab-pane fade show active" id="tab-absent">
  <div class="row g-3">
    <!-- Left: Declare Absent -->
    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header bg-light py-3">
          <span class="card-title mb-0 fw-bold"><i class="bi bi-person-dash me-2 text-danger"></i>Declare Teacher Absent</span>
        </div>
        <div class="card-body">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="declare_absent">
            <input type="hidden" name="date" value="<?= e($selected_date) ?>">
            <div class="mb-3">
              <label class="form-label small fw-bold">Select Teacher</label>
              <select name="teacher_id" class="form-select select2-simple" required>
                <option value="">— Select Teacher —</option>
                <?php foreach ($all_teachers as $t): ?>
                  <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['designation']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-danger btn-sm w-100"><i class="bi bi-check-lg me-1"></i>Mark Absent Today</button>
          </form>
        </div>
      </div>

      <!-- Absent Teachers List -->
      <div class="card">
        <div class="card-header bg-danger-subtle py-3">
          <span class="card-title mb-0 fw-bold text-danger"><i class="bi bi-people me-2"></i>Absent Teachers Today (<?= count($absents) ?>)</span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($absents)): ?>
            <div class="p-4 text-center text-muted">
              <i class="bi bi-emoji-smile fs-1 mb-2 d-block text-success"></i>
              All teachers present today!
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table align-middle mb-0 table-hover">
                <tbody>
                  <?php foreach ($absents as $abs): ?>
                    <tr>
                      <td>
                        <div class="fw-bold"><?= e($abs['name']) ?></div>
                        <small class="text-muted"><?= e($abs['designation']) ?></small>
                      </td>
                      <td><span class="badge bg-danger text-white text-uppercase"><?= e($abs['att_status']) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Right: Affected Periods -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-light py-3">
          <span class="card-title mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-warning"></i>Affected Class Periods & Suggestions</span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($affected_slots)): ?>
            <div class="p-4 text-center text-muted">
              <i class="bi bi-check2-circle fs-1 mb-2 d-block text-success"></i>
              No class routine slots are affected today.
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Class / Section</th>
                    <th>Time / Period</th>
                    <th>Subject / Absent Teacher</th>
                    <th>Assign Substitute</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($affected_slots as $slot):
                    $suggestions = get_substitute_suggestions($pdo, $slot, $selected_date, $session_id);
                    $current_sub_id = $slot['substitute_teacher_id'];
                  ?>
                    <tr>
                      <td>
                        <div class="fw-bold"><?= e($slot['class_name']) ?></div>
                        <span class="badge bg-secondary"><?= e($slot['section_name']) ?></span>
                      </td>
                      <td>
                        <div class="small fw-bold text-primary"><?= substr($slot['start_time'],0,5) ?> - <?= substr($slot['end_time'],0,5) ?></div>
                        <small class="text-muted">Master Slot</small>
                      </td>
                      <td>
                        <div class="fw-bold"><?= e($slot['subject_name']) ?></div>
                        <small class="text-muted">Absent: <?= e($slot['absent_teacher_name']) ?></small>
                      </td>
                      <td>
                        <form method="POST" id="form-sub-<?= $slot['id'] ?>">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="save_substitution">
                          <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>">
                          <input type="hidden" name="date" value="<?= e($selected_date) ?>">
                          <select name="substitute_teacher_id" class="form-select form-select-sm" style="min-width:220px;" onchange="this.form.submit()">
                            <option value="0">— Leave Empty / No Sub —</option>
                            <?php foreach ($suggestions as $sug):
                              $badge = $sug['is_expert'] ? '★ Expert' : 'Free';
                              $loadStr = "Load: {$sug['weekly_load']}/{$sug['max_week']}";
                              $selected = $current_sub_id == $sug['id'] ? 'selected' : '';
                              $color = $sug['is_expert'] ? 'color:#0d6efd;font-weight:bold;' : '';
                            ?>
                              <option value="<?= $sug['id'] ?>" <?= $selected ?> style="<?= $color ?>"><?= e($sug['name']) ?> (<?= $badge ?>, <?= $loadStr ?>)</option>
                            <?php endforeach; ?>
                          </select>
                        </form>
                      </td>
                      <td>
                        <?php if ($current_sub_id): ?>
                          <span class="badge bg-success p-2"><i class="bi bi-shield-fill me-1"></i>Assigned</span>
                        <?php else: ?>
                          <span class="badge bg-warning text-dark p-2"><i class="bi bi-exclamation-triangle me-1"></i>Pending</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- TAB 2: Today's Full Class Schedule -->
<div class="tab-pane fade" id="tab-today">
  <?php
  // Load ALL routine slots for today (master + active substitutes)
  $today_stmt = $pdo->prepare("
      SELECT 
        rs.id, rs.class_id, rs.section_id, rs.subject_id, rs.teacher_id,
        rs.start_time, rs.end_time, rs.is_substitute,
        rs.day_of_week,
        c.class_name, c.display_order,
        sec.section_name, sub.subject_name,
        CONCAT(sp.first_name,' ',sp.last_name) as teacher_name,
        r.room_name,
        -- Check if original teacher is absent today
        (
          SELECT sa.status FROM staff_attendance sa 
          WHERE sa.staff_id = rs.teacher_id AND sa.attendance_date = ? LIMIT 1
        ) as teacher_att_status,
        -- Check if there is a sub covering this slot today
        (
          SELECT u2.id FROM routine_slots sub2
          JOIN users u2 ON u2.id = sub2.teacher_id
          WHERE sub2.class_id = rs.class_id AND sub2.section_id = rs.section_id
            AND sub2.day_of_week = rs.day_of_week AND sub2.start_time = rs.start_time
            AND sub2.is_substitute = 1 AND sub2.substitute_date = ? AND sub2.status = 1
          LIMIT 1
        ) as sub_teacher_id,
        (
          SELECT CONCAT(sp3.first_name,' ',sp3.last_name) FROM routine_slots sub2
          JOIN staff_profiles sp3 ON sp3.user_id = sub2.teacher_id
          WHERE sub2.class_id = rs.class_id AND sub2.section_id = rs.section_id
            AND sub2.day_of_week = rs.day_of_week AND sub2.start_time = rs.start_time
            AND sub2.is_substitute = 1 AND sub2.substitute_date = ? AND sub2.status = 1
          LIMIT 1
        ) as sub_teacher_name
      FROM routine_slots rs
      JOIN classes c ON c.id = rs.class_id
      JOIN sections sec ON sec.id = rs.section_id
      JOIN subjects sub ON sub.id = rs.subject_id
      LEFT JOIN users u ON u.id = rs.teacher_id
      LEFT JOIN staff_profiles sp ON sp.user_id = rs.teacher_id
      LEFT JOIN rooms r ON r.id = rs.room_id
      WHERE rs.session_id = ? AND rs.day_of_week = ? 
        AND rs.status = 1 AND rs.is_substitute = 0
      ORDER BY c.display_order, sec.section_name, rs.start_time
  ");
  $today_stmt->execute([$selected_date, $selected_date, $selected_date, $session_id, $weekday]);
  $today_slots = $today_stmt->fetchAll(PDO::FETCH_ASSOC);

  // Group by section
  $by_section = [];
  foreach ($today_slots as $ts) {
    $key = $ts['class_name'] . ' — ' . $ts['section_name'];
    $by_section[$key][] = $ts;
  }

  // Stats
  $total_periods = count($today_slots);
  $absent_periods = 0; $substituted_periods = 0; $uncovered_periods = 0;
  foreach ($today_slots as $ts) {
    $isAbsent = in_array($ts['teacher_att_status'], ['absent','on_leave']);
    if ($isAbsent) {
      $absent_periods++;
      if ($ts['sub_teacher_id']) $substituted_periods++;
      else $uncovered_periods++;
    }
  }
  ?>

  <!-- Quick stats row -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-1 fw-bold text-primary"><?= $total_periods ?></div>
        <div class="text-muted small">Total Periods Today</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-warning text-center py-3">
        <div class="fs-1 fw-bold text-warning"><?= $absent_periods ?></div>
        <div class="text-muted small">Periods with Absent Teacher</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-success text-center py-3">
        <div class="fs-1 fw-bold text-success"><?= $substituted_periods ?></div>
        <div class="text-muted small">Covered by Substitutes</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-danger text-center py-3">
        <div class="fs-1 fw-bold text-danger"><?= $uncovered_periods ?></div>
        <div class="text-muted small">Uncovered / Pending</div>
      </div>
    </div>
  </div>

  <?php if (empty($by_section)): ?>
    <div class="card">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-calendar-x fs-1 d-block mb-3 text-secondary"></i>
        No routine slots found for <strong><?= $weekday ?></strong>. 
        This may be a holiday or the timetable hasn't been set up yet.
      </div>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($by_section as $sectionLabel => $slots): ?>
        <div class="col-md-6 col-xl-4">
          <div class="card h-100 shadow-sm">
            <div class="card-header py-2 bg-light d-flex align-items-center justify-content-between">
              <strong class="small"><?= e($sectionLabel) ?></strong>
              <?php
              $absCnt = 0; $subCnt = 0;
              foreach ($slots as $s) {
                if (in_array($s['teacher_att_status'],['absent','on_leave'])) { $absCnt++; if ($s['sub_teacher_id']) $subCnt++; }
              }
              if ($absCnt > 0 && $subCnt < $absCnt): ?>
                <span class="badge bg-danger"><?= $absCnt - $subCnt ?> uncovered</span>
              <?php elseif ($absCnt > 0): ?>
                <span class="badge bg-success">Covered</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border">Normal</span>
              <?php endif; ?>
            </div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th style="font-size:0.72rem;">Time</th>
                    <th style="font-size:0.72rem;">Subject</th>
                    <th style="font-size:0.72rem;">Teacher</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($slots as $s):
                    $isAbsent = in_array($s['teacher_att_status'], ['absent','on_leave']);
                    $hasSub = !empty($s['sub_teacher_id']);
                    $rowClass = '';
                    if ($isAbsent && $hasSub) $rowClass = 'table-warning';
                    elseif ($isAbsent && !$hasSub) $rowClass = 'table-danger';
                  ?>
                    <tr class="<?= $rowClass ?>">
                      <td style="font-size:0.72rem; white-space:nowrap;">
                        <?= date('g:i',strtotime($s['start_time'])) ?>–<?= date('g:i A',strtotime($s['end_time'])) ?>
                      </td>
                      <td style="font-size:0.72rem;"><?= e($s['subject_name']) ?></td>
                      <td style="font-size:0.72rem;">
                        <?php if ($isAbsent && $hasSub): ?>
                          <span class="text-muted text-decoration-line-through d-block" style="font-size:0.65rem;"><?= e($s['teacher_name'] ?: '—') ?></span>
                          <span class="text-warning fw-bold"><i class="bi bi-arrow-right me-1"></i><?= e($s['sub_teacher_name']) ?></span>
                        <?php elseif ($isAbsent): ?>
                          <span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>Absent</span>
                          <span class="text-muted d-block" style="font-size:0.65rem;"><?= e($s['teacher_name'] ?: '—') ?></span>
                        <?php else: ?>
                          <span class="text-dark"><?= e($s['teacher_name'] ?: '—') ?></span>
                          <?php if ($s['room_name']): ?>
                            <span class="text-muted d-block" style="font-size:0.65rem;"><i class="bi bi-geo-alt me-1"></i><?= e($s['room_name']) ?></span>
                          <?php endif; ?>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<!-- /TAB 2 -->

</div>
<!-- /tab-content -->

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>

      </div>
      <div class="card-body">
        <form method="GET" class="mb-2">
          <div class="mb-3">
            <label class="form-label small fw-bold">Date</label>
            <input type="date" name="date" class="form-control" value="<?= e($selected_date) ?>" onchange="this.form.submit()">
          </div>
        </form>
        <div class="alert alert-warning py-2 small mb-0">
          <strong>Weekday:</strong> <?= $weekday ?>
        </div>
      </div>
    </div>

    <!-- Quick Absentee Marker -->
    <div class="card">
      <div class="card-header bg-light py-3">
        <span class="card-title mb-0 fw-bold"><i class="bi bi-person-dash me-2 text-danger"></i>Declare Teacher Absent</span>
      </div>
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="declare_absent">
          <input type="hidden" name="date" value="<?= e($selected_date) ?>">
          <div class="mb-3">
            <label class="form-label small fw-bold">Select Teacher</label>
            <select name="teacher_id" class="form-select select2-simple" required>
              <option value="">— Select Teacher —</option>
              <?php foreach ($all_teachers as $t): ?>
                <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['designation']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-danger btn-sm w-100"><i class="bi bi-check-lg me-1"></i>Mark Absent Today</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Absentee Status & Recommendations -->
  <div class="col-lg-8">
    <!-- Today's Absentees list -->
    <div class="card mb-3">
      <div class="card-header bg-danger-subtle py-3">
        <span class="card-title mb-0 fw-bold text-danger-durable"><i class="bi bi-people me-2"></i>Absent Teachers Today (<?= count($absents) ?>)</span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($absents)): ?>
          <div class="p-4 text-center text-muted">
            <i class="bi bi-emoji-smile fs-1 mb-2 d-block text-success"></i>
            All teachers are present today! No substitutions needed.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
              <thead class="table-light">
                <tr>
                  <th>Absent Teacher</th>
                  <th>Designation</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($absents as $abs): ?>
                  <tr>
                    <td class="fw-bold"><?= e($abs['name']) ?></td>
                    <td><?= e($abs['designation']) ?></td>
                    <td>
                      <span class="badge bg-danger text-white text-uppercase"><?= e($abs['att_status']) ?></span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Affected periods requiring substitutions -->
    <div class="card">
      <div class="card-header bg-light py-3">
        <span class="card-title mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-warning"></i>Affected Class Periods & Suggestions</span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($affected_slots)): ?>
          <div class="p-4 text-center text-muted">
            <i class="bi bi-check2-circle fs-1 mb-2 d-block text-success"></i>
            No class routine slots are affected today.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Class / Section</th>
                  <th>Time / Period</th>
                  <th>Original Subject / Teacher</th>
                  <th>Recommended Substitutes</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($affected_slots as $slot): 
                  $suggestions = get_substitute_suggestions($pdo, $slot, $selected_date, $session_id);
                  $current_sub_id = $slot['substitute_teacher_id'];
                ?>
                  <tr>
                    <td>
                      <div class="fw-bold"><?= e($slot['class_name']) ?></div>
                      <span class="badge bg-secondary"><?= e($slot['section_name']) ?></span>
                    </td>
                    <td>
                      <div class="small fw-bold text-primary"><?= substr($slot['start_time'], 0, 5) ?> - <?= substr($slot['end_time'], 0, 5) ?></div>
                      <small class="text-muted">Master Slot</small>
                    </td>
                    <td>
                      <div class="fw-bold"><?= e($slot['subject_name']) ?></div>
                      <small class="text-muted">Absent: <?= e($slot['absent_teacher_name']) ?></small>
                    </td>
                    <td>
                      <form method="POST" id="form-sub-<?= $slot['id'] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_substitution">
                        <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>">
                        <input type="hidden" name="date" value="<?= e($selected_date) ?>">
                        
                        <select name="substitute_teacher_id" class="form-select form-select-sm" style="min-width: 220px;" onchange="this.form.submit()">
                          <option value="0">— Leave Class Empty / No Sub —</option>
                          <?php foreach ($suggestions as $sug): 
                            $badge = $sug['is_expert'] ? '★ Expert' : 'Free';
                            $loadStr = `Load: {$sug['weekly_load']}/{$sug['max_week']}`;
                            $selected = $current_sub_id == $sug['id'] ? 'selected' : '';
                            $color = $sug['is_expert'] ? 'color:#0d6efd;font-weight:bold;' : '';
                          ?>
                            <option value="<?= $sug['id'] ?>" <?= $selected ?> style="<?= $color ?>">
                              <?= e($sug['name']) ?> (<?= $badge ?>, <?= $loadStr ?>)
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </form>
                    </td>
                    <td>
                      <?php if ($current_sub_id): ?>
                        <span class="badge bg-success p-2 text-white"><i class="bi bi-shield-fill me-1"></i>Assigned</span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark p-2"><i class="bi bi-exclamation-triangle me-1"></i>Pending</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
