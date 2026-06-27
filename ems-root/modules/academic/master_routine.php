<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Master Timetable Planner';
$breadcrumbs = ['Academic' => 'classes.php', 'Master Routine' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['routine.view']);

$pdo = db();
$session_id = int_param('session_id', (int)setting('current_session_id', 0), $_GET);

// Handle POST actions for Workload limits and expertise mappings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('routine.manage')) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_limits') {
        $teacher_id = int_param('teacher_id', 0, $_POST);
        $max_day = int_param('max_classes_per_day', 4, $_POST);
        $max_week = int_param('max_classes_per_week', 20, $_POST);
        if ($teacher_id) {
            $stmt = $pdo->prepare("UPDATE staff_profiles SET max_classes_per_day = ?, max_classes_per_week = ? WHERE user_id = ?");
            $stmt->execute([$max_day, $max_week, $teacher_id]);
            flash('success', 'Teacher workload limits updated successfully.');
        }
    } elseif ($action === 'update_expertise') {
        $teacher_id = int_param('teacher_id', 0, $_POST);
        $subjects = $_POST['subjects'] ?? [];
        if ($teacher_id) {
            $pdo->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?")->execute([$teacher_id]);
            if (!empty($subjects)) {
                $ins = $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
                foreach ($subjects as $sub_id) {
                    $ins->execute([$teacher_id, $sub_id]);
                }
            }
            flash('success', 'Teacher subject expertise updated successfully.');
        }
    } elseif ($action === 'reassign_permanent') {
        $slot_id = int_param('slot_id', 0, $_POST);
        $new_teacher_id = int_param('new_teacher_id', 0, $_POST) ?: null;
        if ($slot_id) {
            $pdo->prepare("UPDATE routine_slots SET teacher_id = ? WHERE id = ?")->execute([$new_teacher_id, $slot_id]);
            flash('success', 'Teacher reassigned permanently in routine.');
        }

    } elseif ($action === 'save_period_schedule') {
        // Save master period schedule to system_settings — routine.php reads this same setting
        $periods = [];
        $names   = $_POST['period_name']  ?? [];
        $starts  = $_POST['period_start'] ?? [];
        $ends    = $_POST['period_end']   ?? [];
        $breaks  = $_POST['period_break'] ?? [];
        foreach ($names as $i => $n) {
            if (!$n || !($starts[$i] ?? '')) continue;
            $periods[] = ['name' => trim($n), 'start' => $starts[$i], 'end' => $ends[$i] ?? '', 'is_break' => isset($breaks[$i]) ? 1 : 0];
        }
        if (!empty($periods)) {
            $pdo->prepare("INSERT INTO system_settings (meta_key,meta_value,meta_group) VALUES ('routine_periods',?,'academic') ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)")
                ->execute([json_encode($periods)]);
            flash('success', 'Period schedule saved. All class routines will now use this schedule.');
        }

    } elseif ($action === 'set_subject_classes_per_week') {
        // Bulk-update classes_per_week in class_subjects
        $updates = $_POST['ppw'] ?? []; // [class_subject_id => classes_per_week]
        $stmt = $pdo->prepare('UPDATE class_subjects SET classes_per_week=? WHERE id=?');
        foreach ($updates as $csid => $ppw) {
            $stmt->execute([(int)$ppw, (int)$csid]);
        }
        flash('success', 'Subject period targets updated.');
    }

    header("Location: master_routine.php?session_id=" . $session_id . "&tab=" . ($_POST['current_tab'] ?? 'health'));
    exit;
}

// Fetch sessions list
$sessions  = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$activeTab = $_GET['tab'] ?? 'planner';

// Load default periods
$default_periods = get_routine_periods();

function get_routine_periods() {
    $raw = setting('routine_periods');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $decoded;
    }
    return [
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

function get_master_suggestions($pdo, $slot, $session_id, $teachers) {
    $start_time = $slot['start_time'];
    $end_time = $slot['end_time'];
    $day = $slot['day_of_week'];
    $subject_id = $slot['subject_id'];
    
    $suggestions = [];
    foreach ($teachers as $t) {
        $t_id = $t['id'];
        if ($t_id == ($slot['teacher_id'] ?? null)) continue;
        
        // Check if busy in master routine at this period/day
        $busy_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM routine_slots 
            WHERE teacher_id = ? AND day_of_week = ? AND session_id = ? AND status = 1 AND is_substitute = 0
              AND NOT (end_time <= ? OR start_time >= ?)
        ");
        $busy_stmt->execute([$t_id, $day, $session_id, $start_time, $end_time]);
        if ((int)$busy_stmt->fetchColumn() > 0) continue; // Busy
        
        // Expert check
        $exp_stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = ? AND subject_id = ?");
        $exp_stmt->execute([$t_id, $subject_id]);
        $is_expert = (int)$exp_stmt->fetchColumn() > 0;
        
        // Workload check
        $wk_stmt = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE teacher_id = ? AND session_id = ? AND status = 1 AND is_substitute = 0");
        $wk_stmt->execute([$t_id, $session_id]);
        $weekly_load = (int)$wk_stmt->fetchColumn();
        
        $suggestions[] = [
            'id' => $t_id,
            'name' => $t['name'],
            'is_expert' => $is_expert,
            'weekly_load' => $weekly_load,
            'max_week' => $t['max_classes_per_week'] ?: 20
        ];
    }
    
    // Sort suggestions: Experts first, then lower workload
    usort($suggestions, function($a, $b) {
        if ($a['is_expert'] !== $b['is_expert']) {
            return $b['is_expert'] <=> $a['is_expert'];
        }
        return $a['weekly_load'] <=> $b['weekly_load'];
    });
    
    return $suggestions;
}

// Fetch all classes and sections with Class Teacher details
$sections = $pdo->query("
    SELECT s.id as section_id, s.class_id, c.class_name, s.section_name, s.class_teacher_id,
           CONCAT(sp.first_name, ' ', sp.last_name) as class_teacher_name, s.class_teacher_first_period_days
    FROM sections s 
    JOIN classes c ON c.id = s.class_id 
    LEFT JOIN staff_profiles sp ON sp.user_id = s.class_teacher_id
    WHERE s.status = 1 
    ORDER BY c.display_order, s.section_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch routine slots for the session (only master/non-substitute slots)
$slots_raw = $pdo->prepare("
    SELECT rs.*, s.subject_name, u.full_name as teacher_name, r.room_name 
    FROM routine_slots rs 
    JOIN subjects s ON s.id = rs.subject_id 
    LEFT JOIN users u ON u.id = rs.teacher_id 
    LEFT JOIN rooms r ON r.id = rs.room_id 
    WHERE rs.session_id = ? AND rs.status = 1 AND rs.is_substitute = 0
");
$slots_raw->execute([$session_id]);
$all_slots = $slots_raw->fetchAll(PDO::FETCH_ASSOC);

// Index slots: [section_id][period_start][day_of_week][] = slot
$indexed_slots = [];
foreach ($all_slots as $slot) {
    $start_time = date('H:i', strtotime($slot['start_time']));
    $indexed_slots[$slot['section_id']][$start_time][$slot['day_of_week']][] = $slot;
}

// Get active teacher statuses (active vs inactive/terminated)
$teacher_status = $pdo->query("SELECT user_id, status FROM staff_profiles")->fetchAll(PDO::FETCH_KEY_PAIR);

// Analyze conflicts
$teacher_bookings = [];
$room_bookings = [];
$slot_conflicts = [];

foreach ($all_slots as $slot) {
    $time_key = date('H:i', strtotime($slot['start_time'])) . '-' . date('H:i', strtotime($slot['end_time']));
    
    // Check if teacher is inactive/terminated
    if ($slot['teacher_id'] && ($teacher_status[$slot['teacher_id']] ?? '') !== 'active') {
        $slot_conflicts[$slot['id']][] = "Assigned teacher is inactive or terminated.";
    }

    // Teacher double booking check
    if ($slot['teacher_id']) {
        $teacher_bookings[$slot['teacher_id']][$slot['day_of_week']][$time_key][] = [
            'slot_id' => $slot['id'],
            'section' => $slot['class_id'] . '-' . $slot['section_id']
        ];
    }
    // Room double booking check
    if ($slot['room_id']) {
        $room_bookings[$slot['room_id']][$slot['day_of_week']][$time_key][] = [
            'slot_id' => $slot['id'],
            'section' => $slot['class_id'] . '-' . $slot['section_id']
        ];
    }
}

// Mark slots that have double bookings
foreach ($teacher_bookings as $t_id => $days) {
    foreach ($days as $day => $times) {
        foreach ($times as $time => $bookings) {
            if (count($bookings) > 1) {
                foreach ($bookings as $b) {
                    $slot_conflicts[$b['slot_id']][] = "Teacher double booked with another section at this time.";
                }
            }
        }
    }
}
foreach ($room_bookings as $r_id => $days) {
    foreach ($days as $day => $times) {
        foreach ($times as $time => $bookings) {
            if (count($bookings) > 1) {
                foreach ($bookings as $b) {
                    $slot_conflicts[$b['slot_id']][] = "Room occupied by another section at this time.";
                }
            }
        }
    }
}

// Load active teachers with workload details
$teachers = $pdo->query("
    SELECT u.id, CONCAT(sp.first_name, ' ', sp.last_name) as name, sp.designation, sp.department,
            sp.max_classes_per_day, sp.max_classes_per_week
     FROM staff_profiles sp
     JOIN users u ON u.id = sp.user_id
     WHERE sp.status = 'active' AND (sp.designation LIKE '%teacher%' OR sp.designation LIKE '%lecturer%' OR sp.designation LIKE '%faculty%' OR sp.designation LIKE '%instructor%')
     ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Load all active rooms
$rooms = $pdo->query('SELECT id, room_name, capacity FROM rooms WHERE status=1 ORDER BY room_name')->fetchAll(PDO::FETCH_ASSOC);

// Load all active subjects
$allSubjects = $pdo->query('SELECT id, subject_name FROM subjects WHERE status=1 ORDER BY subject_name')->fetchAll(PDO::FETCH_ASSOC);

// Aggregate weekly class loads
$workload_week = [];
$wk_stmt = $pdo->prepare("
    SELECT teacher_id, COUNT(*) as cnt 
    FROM routine_slots 
    WHERE session_id = ? AND status = 1 AND teacher_id IS NOT NULL AND is_substitute = 0
    GROUP BY teacher_id
");
$wk_stmt->execute([$session_id]);
foreach ($wk_stmt->fetchAll() as $r) {
    $workload_week[$r['teacher_id']] = (int)$r['cnt'];
}

// Aggregate daily class loads
$workload_daily = [];
$dy_stmt = $pdo->prepare("
    SELECT teacher_id, day_of_week, COUNT(*) as cnt 
    FROM routine_slots 
    WHERE session_id = ? AND status = 1 AND teacher_id IS NOT NULL AND is_substitute = 0
    GROUP BY teacher_id, day_of_week
");
$dy_stmt->execute([$session_id]);
foreach ($dy_stmt->fetchAll() as $r) {
    $workload_daily[$r['teacher_id']][$r['day_of_week']] = (int)$r['cnt'];
}

// Fetch subject expert listings
$expertise = [];
$exp_stmt = $pdo->query("
    SELECT ts.teacher_id, ts.subject_id, s.subject_name 
    FROM teacher_subjects ts 
    JOIN subjects s ON s.id = ts.subject_id
")->fetchAll();
foreach ($exp_stmt as $r) {
    $expertise[$r['teacher_id']][] = $r;
}

// Normalise working_days setting — stored as full names ("Saturday,...") OR abbrevs ("Sat,...")
$_day_norm = [
    'Sat'=>'Saturday','Sun'=>'Sunday','Mon'=>'Monday','Tue'=>'Tuesday',
    'Wed'=>'Wednesday','Thu'=>'Thursday','Fri'=>'Friday',
    'Saturday'=>'Saturday','Sunday'=>'Sunday','Monday'=>'Monday','Tuesday'=>'Tuesday',
    'Wednesday'=>'Wednesday','Thursday'=>'Thursday','Friday'=>'Friday',
];
$working_days = array_values(array_filter(array_map(
    fn($d) => $_day_norm[trim($d)] ?? null,
    explode(',', setting('working_days', 'Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday'))
)));
// Maps both 3-letter codes AND full names → full names (used in template lookups)
$day_full_names = $_day_norm;

$selected_day = $_GET['day'] ?? 'all';

// ── Subject coverage stats: actual vs target periods/week per class-section ──
$coverageData = [];
if ($session_id) {
    $cvStmt = $pdo->prepare("
        SELECT cs.id AS cs_id, cs.class_id, cs.subject_id, cs.classes_per_week AS target,
               c.class_name, sub.subject_name,
               COUNT(DISTINCT CONCAT(rs.section_id,'|',rs.day_of_week,'|',rs.start_time)) AS actual
        FROM class_subjects cs
        JOIN classes c ON c.id = cs.class_id
        JOIN subjects sub ON sub.id = cs.subject_id
        LEFT JOIN routine_slots rs ON rs.class_id=cs.class_id AND rs.subject_id=cs.subject_id
             AND rs.session_id=cs.session_id AND rs.status=1 AND rs.is_substitute=0
        WHERE cs.session_id = ?
        GROUP BY cs.id, cs.class_id, cs.subject_id, cs.classes_per_week, c.class_name, sub.subject_name
        ORDER BY c.display_order, c.class_name, sub.subject_name
    ");
    $cvStmt->execute([$session_id]);
    foreach ($cvStmt->fetchAll() as $r) {
        $coverageData[$r['class_name']][] = $r;
    }
}

require_once EMS_ROOT . '/includes/header.php';
?>

<!-- Tab view -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-grid-3x3-gap-fill me-2 text-primary"></i>Master Timetable Planner</h1>
  <div class="d-flex gap-2">
    <?php if (has_permission('routine.manage')): ?>
      <button class="btn btn-outline-danger btn-sm" onclick="triggerAutoGenerate()"><i class="bi bi-cpu me-1"></i>Auto-Generate Timetable</button>
    <?php endif; ?>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print Master Grid</button>
    <a href="routine.php?session_id=<?= $session_id ?>" class="btn btn-primary btn-sm"><i class="bi bi-calendar3 me-1"></i>Class Routines</a>
  </div>
</div>

<!-- Filters Panel -->
<div class="card mb-4 no-print">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-bold">Academic Session</label>
        <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sessions as $sess): ?>
            <option value="<?= $sess['id'] ?>" <?= $session_id == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-bold">Weekday Filter</label>
        <select name="day" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="all" <?= $selected_day === 'all' ? 'selected' : '' ?>>— Show All Days —</option>
          <?php foreach ($working_days as $day_code): 
            $day_name = $day_full_names[trim($day_code)] ?? '';
            if (!$day_name) continue;
          ?>
            <option value="<?= $day_name ?>" <?= $selected_day === $day_name ? 'selected' : '' ?>><?= $day_name ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 text-end">
        <span class="badge bg-info-subtle text-info border border-info-subtle p-2">
          <i class="bi bi-info-circle me-1"></i>
          Class Teachers taking the 1st period will be prioritized on designated days.
        </span>
      </div>
    </form>
  </div>
</div>

<ul class="nav nav-tabs mb-4 no-print flex-wrap" id="routineTabs">
  <li class="nav-item"><a class="nav-link <?= $activeTab==='planner'?'active':'' ?>" data-bs-toggle="tab" href="#tab-planner"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Timetable Grid</a></li>
  <li class="nav-item"><a class="nav-link <?= $activeTab==='periods'?'active':'' ?>" data-bs-toggle="tab" href="#tab-periods"><i class="bi bi-clock me-2 text-warning"></i>Period Schedule</a></li>
  <li class="nav-item"><a class="nav-link <?= $activeTab==='coverage'?'active':'' ?>" data-bs-toggle="tab" href="#tab-coverage"><i class="bi bi-bar-chart-steps me-2 text-info"></i>Subject Coverage</a></li>
  <li class="nav-item"><a class="nav-link <?= $activeTab==='workloads'?'active':'' ?>" data-bs-toggle="tab" href="#tab-workloads"><i class="bi bi-bar-chart-fill me-2 text-success"></i>Teacher Workloads</a></li>
  <li class="nav-item"><a class="nav-link <?= $activeTab==='health'?'active':'' ?>" data-bs-toggle="tab" href="#tab-health"><i class="bi bi-heart-pulse-fill me-2 text-danger"></i>Health Check</a></li>
</ul>

<div class="tab-content">
  <!-- Planner Grid -->
  <div class="tab-pane fade <?= $activeTab==='planner'||!in_array($activeTab,['periods','coverage','workloads','health'])?'show active':'' ?>" id="tab-planner">
    <div class="card">
      <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between bg-light no-print">
        <span class="card-title mb-0 fw-bold"><i class="bi bi-table me-2 text-primary"></i>Excel-Style Weekly Master Routine Grid</span>
        <span class="text-muted small">Click any cell to manage schedule details & parallel/split subjects.</span>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 text-center" style="min-width: 1000px; font-size: 0.85rem;">
          <thead class="table-dark">
            <tr>
              <th style="min-width: 180px; text-align: left;">Class & Section</th>
              <?php foreach ($default_periods as $p): ?>
                <th>
                  <div><?= e($p['name']) ?></div>
                  <small class="fw-normal opacity-75"><?= e($p['start']) ?> - <?= e($p['end']) ?></small>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sections as $sec): 
              // Load section custom periods if defined
              $sec_periods_key = "section_periods_" . $sec['section_id'];
              $sec_periods_raw = setting($sec_periods_key);
              $sec_periods = $sec_periods_raw ? json_decode($sec_periods_raw, true) : $default_periods;
            ?>
              <tr>
                <td class="fw-bold bg-light" style="text-align: left;">
                  <div class="d-flex align-items-center justify-content-between">
                    <div>
                      <?= e($sec['class_name']) ?> <span class="badge bg-secondary"><?= e($sec['section_name']) ?></span>
                      <?php if ($sec['class_teacher_name']): ?>
                        <div class="text-muted fw-normal" style="font-size: 0.72rem;">
                          <i class="bi bi-person-badge-fill me-1"></i><?= e($sec['class_teacher_name']) ?>
                        </div>
                      <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-xs btn-outline-secondary no-print" onclick="openSectionPeriodsModal(<?= $sec['section_id'] ?>, '<?= e($sec['class_name'] . ' - ' . $sec['section_name']) ?>')" title="Customize section period timings">
                      <i class="bi bi-clock"></i>
                    </button>
                  </div>
                </td>
                
                <?php foreach ($sec_periods as $p): 
                  if (!empty($p['is_break'])): ?>
                    <td class="bg-secondary-subtle text-muted small py-3" style="letter-spacing: 0.1rem; font-weight: bold;">
                      <?= strtoupper(e($p['name'])) ?>
                    </td>
                  <?php else: 
                    $start_key = $p['start'];
                    $day_slots = $indexed_slots[$sec['section_id']][$start_key] ?? [];
                  ?>
                    <td class="p-2 timetable-cell" style="cursor: pointer;" onclick="openPlannerCellModal(<?= $sec['section_id'] ?>, '<?= e($sec['class_name']) ?> - <?= e($sec['section_name']) ?>', '<?= e($p['name']) ?>', '<?= e($p['start']) ?>', '<?= e($p['end']) ?>')">
                      <div class="d-flex flex-column gap-1 text-start">
                        <?php 
                        $has_any = false;
                        foreach ($working_days as $day_code):
                          $day_name = $day_full_names[trim($day_code)] ?? '';
                          if (!$day_name) continue;
                          
                          // If filtering by specific day, skip others
                          if ($selected_day !== 'all' && $selected_day !== $day_name) {
                              continue;
                          }

                          $slots_for_day = $day_slots[$day_name] ?? [];
                          foreach ($slots_for_day as $slot):
                            $has_any = true;
                            $has_conflict = !empty($slot_conflicts[$slot['id']]);
                            $bg = $has_conflict ? 'bg-danger-subtle border-danger' : 'bg-light border-light-subtle';
                        ?>
                            <div class="p-1 rounded border small <?= $bg ?>" style="font-size: 0.72rem; line-height: 1.1; margin-bottom: 2px;">
                              <strong class="text-primary"><?= e($day_code) ?>:</strong> 
                              <span class="fw-600 text-dark"><?= e($slot['subject_name']) ?></span>
                              <div class="text-muted small mt-1 text-truncate" style="max-width: 130px;">
                                <i class="bi bi-person me-1"></i><?= e($slot['teacher_name'] ?: 'No Teacher') ?>
                              </div>
                              <?php if ($slot['room_name']): ?>
                                <div class="text-muted small text-truncate" style="max-width: 130px;">
                                  <i class="bi bi-geo-alt me-1"></i><?= e($slot['room_name']) ?>
                                </div>
                              <?php endif; ?>
                              <?php if ($has_conflict): ?>
                                <span class="badge bg-danger p-0 px-1 mt-1 text-white" style="font-size: 0.6rem;" title="<?= e(implode(' ', $slot_conflicts[$slot['id']])) ?>">CONFLICT</span>
                              <?php endif; ?>
                            </div>
                        <?php endforeach; endforeach; ?>
                        <?php if (!$has_any): ?>
                          <span class="text-muted small d-block text-center py-2">—</span>
                        <?php endif; ?>
                      </div>
                    </td>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Periods Configuration -->
  <div class="tab-pane fade" id="tab-periods">
    <div class="card">
      <div class="card-header py-3 px-4 bg-light d-flex align-items-center justify-content-between">
        <span class="card-title mb-0 fw-bold"><i class="bi bi-clock me-2 text-warning"></i>Configure Daily Period Slots & Breaks (Global Template)</span>
        <?php if (has_permission('routine.manage')): ?>
          <button class="btn btn-sm btn-primary" onclick="addNewPeriodRow('global')"><i class="bi bi-plus-lg me-1"></i>Add Period/Break</button>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <p class="text-muted small">Define global default periods. Section-specific period overrides can be configured directly from the grid.</p>
        <form id="periods-config-form" onsubmit="savePeriodsConfig(event)">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-3 text-center">
              <thead>
                <tr>
                  <th>Slot Name</th>
                  <th>Start Time</th>
                  <th>End Time</th>
                  <th>Is Break?</th>
                  <?php if (has_permission('routine.manage')): ?>
                    <th style="width: 100px;">Actions</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody id="periods-rows-container">
                <?php foreach ($default_periods as $idx => $p): ?>
                  <tr data-index="<?= $idx ?>">
                    <td>
                      <input type="text" class="form-control form-control-sm text-center fw-bold" name="name" value="<?= e($p['name']) ?>" required>
                    </td>
                    <td>
                      <input type="time" class="form-control form-control-sm text-center" name="start" value="<?= e($p['start']) ?>" required>
                    </td>
                    <td>
                      <input type="time" class="form-control form-control-sm text-center" name="end" value="<?= e($p['end']) ?>" required>
                    </td>
                    <td>
                      <select class="form-select form-select-sm text-center" name="is_break">
                        <option value="0" <?= empty($p['is_break']) ? 'selected' : '' ?>>No (Class Period)</option>
                        <option value="1" <?= !empty($p['is_break']) ? 'selected' : '' ?>>Yes (Break/Recess)</option>
                      </select>
                    </td>
                    <?php if (has_permission('routine.manage')): ?>
                      <td>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removePeriodRow(this)"><i class="bi bi-trash"></i></button>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (has_permission('routine.manage')): ?>
            <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Save Period Timetable Configuration</button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <!-- Workloads & Expertise -->
  <div class="tab-pane fade" id="tab-workloads">
    <div class="card mb-4">
      <div class="card-header py-3 px-4 bg-light d-flex align-items-center justify-content-between">
        <span class="card-title mb-0 fw-bold"><i class="bi bi-bar-chart-fill me-2 text-success"></i>Teachers Workload & Workday Distribution</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Teacher</th>
              <th>Designation / Dept</th>
              <th class="text-center">Weekly Load (Total)</th>
              <th class="text-center">Daily Limits (Max)</th>
              <th class="text-center">Days Distribution (Load Count)</th>
              <th>Expert Subjects</th>
              <?php if (has_permission('routine.manage')): ?>
                <th class="text-end">Actions</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($teachers as $t):
              $user_id   = $t['id'];
              $weekly    = $workload_week[$user_id] ?? 0;
              $max_day   = $t['max_classes_per_day'] ?: 4;
              $max_week  = $t['max_classes_per_week'] ?: 20;
              $t_exp     = $expertise[$user_id] ?? [];
              $daily_arr = $workload_daily[$user_id] ?? [];

              $week_warning = $weekly > $max_week ? 'bg-danger text-white' : ($weekly >= $max_week - 2 ? 'bg-warning text-dark' : 'bg-light text-dark');
            ?>
            <tr>
              <td><div class="fw-600"><?= e($t['name']) ?></div></td>
              <td><div class="small"><?= e($t['designation']) ?></div><small class="text-muted"><?= e($t['department']) ?></small></td>
              <td class="text-center">
                <span class="badge rounded-pill <?= $week_warning ?> fs-6 py-1 px-3">
                  <?= $weekly ?> / <?= $max_week ?>
                </span>
              </td>
              <td class="text-center">
                <span class="small font-monospace">Max: <?= $max_day ?>/day</span>
              </td>
              <td class="text-center">
                <div class="d-flex justify-content-center gap-1">
                  <?php foreach (['Sat','Sun','Mon','Tue','Wed','Thu','Fri'] as $day):
                    $full_days = ['Sat'=>'Saturday','Sun'=>'Sunday','Mon'=>'Monday','Tue'=>'Tuesday','Wed'=>'Wednesday','Thu'=>'Thursday','Fri'=>'Friday'];
                    $cnt = $daily_arr[$full_days[$day]] ?? 0;
                    $class = $cnt > $max_day ? 'bg-danger text-white' : ($cnt == 0 ? 'bg-secondary-subtle text-muted' : 'bg-success text-white');
                  ?>
                    <span class="badge <?= $class ?>" style="width: 32px; font-size: 0.72rem;" title="<?= $full_days[$day] ?>: <?= $cnt ?> classes">
                      <?= $day ?><br><?= $cnt ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </td>
              <td>
                <?php if (empty($t_exp)): ?>
                  <span class="text-muted small">— No subjects assigned —</span>
                <?php else: ?>
                  <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($t_exp as $sub): ?>
                      <span class="badge bg-info-subtle text-info border border-info-subtle"><?= e($sub['subject_name']) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <?php if (has_permission('routine.manage')): ?>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary me-1" onclick="openLimitsModal(<?= $user_id ?>, '<?= e($t['name']) ?>', <?= $max_day ?>, <?= $max_week ?>)">
                    <i class="bi bi-sliders"></i> Limits
                  </button>
                  <button class="btn btn-sm btn-outline-success" onclick="openExpertModal(<?= $user_id ?>, '<?= e($t['name']) ?>', <?= e(json_encode(array_column($t_exp, 'subject_id'))) ?>)">
                    <i class="bi bi-mortarboard"></i> Subjects
                  </button>
                </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Subject Coverage Tab -->
  <div class="tab-pane fade <?= $activeTab==='coverage'?'show active':'' ?>" id="tab-coverage">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-bold mb-0"><i class="bi bi-bar-chart-steps me-2 text-info"></i>Subject Coverage vs Target Periods/Week</h5>
      <a href="class_subjects.php?session_id=<?= $session_id ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Set Period Targets</a>
    </div>
    <?php if (empty($coverageData)): ?>
      <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-bar-chart-steps"></i><p>No class-subject assignments found. <a href="class_subjects.php">Set up subjects per class first.</a></p></div></div></div>
    <?php else: ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="set_subject_classes_per_week">
      <input type="hidden" name="session_id" value="<?= $session_id ?>">
      <input type="hidden" name="current_tab" value="coverage">
      <?php foreach ($coverageData as $className => $subjects): ?>
      <div class="card mb-3">
        <div class="card-header py-2 px-4 bg-light d-flex align-items-center gap-3">
          <strong><?= e($className) ?></strong>
          <a href="routine.php?session_id=<?= $session_id ?>&class_id=<?= $subjects[0]['class_id'] ?? 0 ?>" class="btn btn-xs btn-outline-primary ms-auto">
            <i class="bi bi-calendar-week me-1"></i>Edit Routine
          </a>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Subject</th><th class="text-center" style="width:110px">Target/wk</th><th class="text-center">Actual Slots</th><th style="width:160px">Status</th></tr></thead>
            <tbody>
              <?php foreach ($subjects as $cv):
                $diff = $cv['actual'] - $cv['target'];
                $statusClass = $cv['target'] == 0 ? 'text-muted' : ($diff < 0 ? 'text-danger' : ($diff == 0 ? 'text-success' : 'text-warning'));
                $statusLabel = $cv['target'] == 0 ? 'No target set' : ($diff < 0 ? abs($diff).' slots short' : ($diff == 0 ? 'On target ✓' : $diff.' extra slots'));
              ?>
              <tr>
                <td class="fw-600"><?= e($cv['subject_name']) ?></td>
                <td class="text-center">
                  <input type="number" name="ppw[<?= $cv['cs_id'] ?>]" class="form-control form-control-sm text-center" style="width:70px;margin:auto"
                         value="<?= $cv['target'] ?>" min="0" max="30">
                </td>
                <td class="text-center fw-700"><?= $cv['actual'] ?></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:6px">
                      <?php $pct = $cv['target'] > 0 ? min(100, round($cv['actual']/$cv['target']*100)) : 0; ?>
                      <div class="progress-bar <?= $pct>=100?'bg-success':($pct>=60?'bg-warning':'bg-danger') ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="small <?= $statusClass ?>" style="min-width:90px"><?= $statusLabel ?></span>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
      <div class="text-end mt-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save Period Targets</button>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <!-- Health & Reassignments Tab -->
  <div class="tab-pane fade <?= $activeTab==='health'?'show active':'' ?>" id="tab-health">
    <?php
    // ---- Health Analysis ----
    $health_issues = [];

    // 1. Unassigned Periods: slots with no teacher
    $unassigned_stmt = $pdo->prepare("
        SELECT rs.id, rs.class_id, rs.section_id, rs.day_of_week, rs.start_time, rs.end_time, rs.subject_id,
               c.class_name, sec.section_name, sub.subject_name
        FROM routine_slots rs
        JOIN classes c ON c.id = rs.class_id
        JOIN sections sec ON sec.id = rs.section_id
        JOIN subjects sub ON sub.id = rs.subject_id
        WHERE rs.session_id = ? AND rs.teacher_id IS NULL AND rs.status = 1 AND rs.is_substitute = 0
        ORDER BY c.display_order, sec.section_name, rs.day_of_week, rs.start_time
    ");
    $unassigned_stmt->execute([$session_id]);
    $unassigned_slots = $unassigned_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($unassigned_slots as $slot) {
        $sugg = get_master_suggestions($pdo, $slot, $session_id, $teachers);
        $health_issues[] = [
            'type'     => 'no_teacher',
            'severity' => 'danger',
            'slot'     => $slot,
            'suggestions' => $sugg
        ];
    }

    // 2. Inactive / Terminated teacher assigned to slots
    $inactive_stmt = $pdo->prepare("
        SELECT rs.id, rs.class_id, rs.section_id, rs.day_of_week, rs.start_time, rs.end_time, rs.subject_id, rs.teacher_id,
               c.class_name, sec.section_name, sub.subject_name,
               CONCAT(sp.first_name,' ',sp.last_name) as teacher_name, sp.status as t_status
        FROM routine_slots rs
        JOIN classes c ON c.id = rs.class_id
        JOIN sections sec ON sec.id = rs.section_id
        JOIN subjects sub ON sub.id = rs.subject_id
        JOIN staff_profiles sp ON sp.user_id = rs.teacher_id
        WHERE rs.session_id = ? AND rs.status = 1 AND rs.is_substitute = 0
          AND sp.status != 'active'
        ORDER BY c.display_order, sec.section_name, rs.day_of_week, rs.start_time
    ");
    $inactive_stmt->execute([$session_id]);
    $inactive_slots = $inactive_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($inactive_slots as $slot) {
        $sugg = get_master_suggestions($pdo, $slot, $session_id, $teachers);
        $health_issues[] = [
            'type'        => 'inactive_teacher',
            'severity'    => 'warning',
            'slot'        => $slot,
            'suggestions' => $sugg
        ];
    }

    // 3. Double-booked teachers (from $slot_conflicts populated earlier)
    $double_booked_slots = [];
    foreach ($all_slots as $slot) {
        if (!empty($slot_conflicts[$slot['id']])) {
            foreach ($slot_conflicts[$slot['id']] as $msg) {
                if (strpos($msg, 'double') !== false || strpos($msg, 'occupied') !== false) {
                    $double_booked_slots[] = ['slot' => $slot, 'msg' => $msg];
                }
            }
        }
    }

    // 4. Overloaded teachers (weekly)
    $overloaded = [];
    foreach ($teachers as $t) {
        $wk = $workload_week[$t['id']] ?? 0;
        $max = $t['max_classes_per_week'] ?: 20;
        if ($wk > $max) {
            $overloaded[] = ['teacher' => $t, 'load' => $wk, 'max' => $max, 'excess' => $wk - $max];
        }
    }

    // 5. Under-loaded teachers (active teachers with 0 classes)
    $underloaded = [];
    foreach ($teachers as $t) {
        $wk = $workload_week[$t['id']] ?? 0;
        if ($wk == 0) {
            $underloaded[] = $t;
        }
    }

    $total_issues = count($health_issues) + count($double_booked_slots) + count($overloaded);
    ?>

    <!-- Summary Badges -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card border-danger text-center py-3">
          <div class="fs-1 fw-bold text-danger"><?= count($unassigned_slots) ?></div>
          <div class="text-muted small">Unassigned Periods</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-warning text-center py-3">
          <div class="fs-1 fw-bold text-warning"><?= count($inactive_slots) ?></div>
          <div class="text-muted small">Inactive/Terminated Teacher Slots</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-danger text-center py-3">
          <div class="fs-1 fw-bold text-danger"><?= count($double_booked_slots) ?></div>
          <div class="text-muted small">Conflict / Double-Bookings</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-success text-center py-3">
          <div class="fs-1 fw-bold text-success"><?= count($underloaded) ?></div>
          <div class="text-muted small">Idle Teachers (0 Classes)</div>
        </div>
      </div>
    </div>

    <?php if ($total_issues === 0 && empty($underloaded)): ?>
      <div class="alert alert-success d-flex align-items-center gap-3">
        <i class="bi bi-shield-check fs-2 text-success"></i>
        <div>
          <strong>Timetable is Healthy!</strong><br>
          <span class="text-muted small">No conflicts, unassigned periods, or overloaded teachers found.</span>
        </div>
      </div>
    <?php else: ?>

    <!-- Unassigned Periods -->
    <?php if (!empty($unassigned_slots)): ?>
    <div class="card mb-4 border-danger">
      <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between">
        <span class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Unassigned Periods — No Teacher Set (<?= count($unassigned_slots) ?>)</span>
        <span class="badge bg-white text-danger">Action Required</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Class / Section</th>
              <th>Day</th>
              <th>Period Time</th>
              <th>Subject</th>
              <th>Top Suggestions (Expert → Lowest Load)</th>
              <?php if (has_permission('routine.manage')): ?>
                <th class="text-end">Quick Assign</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($unassigned_slots as $slot): 
              $sugg = get_master_suggestions($pdo, $slot, $session_id, $teachers);
              $top3 = array_slice($sugg, 0, 3);
            ?>
            <tr>
              <td><strong><?= e($slot['class_name']) ?></strong> <span class="badge bg-secondary"><?= e($slot['section_name']) ?></span></td>
              <td><?= e($slot['day_of_week']) ?></td>
              <td><span class="badge bg-light text-dark border"><?= date('g:i A', strtotime($slot['start_time'])) ?> – <?= date('g:i A', strtotime($slot['end_time'])) ?></span></td>
              <td><?= e($slot['subject_name']) ?></td>
              <td>
                <?php if (empty($top3)): ?>
                  <span class="text-muted small">No available teachers</span>
                <?php else: ?>
                  <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($top3 as $s): ?>
                      <span class="badge <?= $s['is_expert'] ? 'bg-success' : 'bg-secondary' ?>-subtle border text-dark" style="font-size: 0.72rem;">
                        <i class="bi bi-person me-1"></i><?= e($s['name']) ?>
                        <?php if ($s['is_expert']): ?><i class="bi bi-star-fill text-warning ms-1" title="Expert"></i><?php endif; ?>
                        <small class="text-muted ms-1"><?= $s['weekly_load'] ?>/<?= $s['max_week'] ?></small>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <?php if (has_permission('routine.manage')): ?>
              <td class="text-end">
                <select class="form-select form-select-sm d-inline-block w-auto quick-assign-teacher" data-slot-id="<?= $slot['id'] ?>">
                  <option value="">— Pick Teacher —</option>
                  <?php foreach ($sugg as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= e($s['name']) ?><?= $s['is_expert'] ? ' ★' : '' ?> (<?= $s['weekly_load'] ?>)</option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-danger ms-1" onclick="quickAssignTeacher(this)" data-slot-id="<?= $slot['id'] ?>">
                  <i class="bi bi-check-lg"></i> Assign
                </button>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Inactive/Terminated Teacher Slots -->
    <?php if (!empty($inactive_slots)): ?>
    <div class="card mb-4 border-warning">
      <div class="card-header bg-warning text-dark d-flex align-items-center justify-content-between">
        <span class="fw-bold"><i class="bi bi-person-x-fill me-2"></i>Inactive/Terminated Teacher Assignments (<?= count($inactive_slots) ?>)</span>
        <span class="badge bg-dark text-warning">Reassignment Needed</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Class / Section</th>
              <th>Day / Time</th>
              <th>Subject</th>
              <th>Current (Inactive) Teacher</th>
              <th>Top Replacement Suggestions</th>
              <?php if (has_permission('routine.manage')): ?>
                <th class="text-end">Reassign</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($inactive_slots as $slot): 
              $sugg = get_master_suggestions($pdo, $slot, $session_id, $teachers);
              $top3 = array_slice($sugg, 0, 3);
            ?>
            <tr>
              <td><strong><?= e($slot['class_name']) ?></strong> <span class="badge bg-secondary"><?= e($slot['section_name']) ?></span></td>
              <td>
                <div class="small fw-bold"><?= e($slot['day_of_week']) ?></div>
                <div class="text-muted small"><?= date('g:i A', strtotime($slot['start_time'])) ?> – <?= date('g:i A', strtotime($slot['end_time'])) ?></div>
              </td>
              <td><?= e($slot['subject_name']) ?></td>
              <td>
                <span class="text-danger fw-bold"><i class="bi bi-person-x me-1"></i><?= e($slot['teacher_name']) ?></span>
                <br><span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= ucfirst($slot['t_status']) ?></span>
              </td>
              <td>
                <?php if (empty($top3)): ?>
                  <span class="text-muted small">No available teachers</span>
                <?php else: ?>
                  <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($top3 as $s): ?>
                      <span class="badge <?= $s['is_expert'] ? 'bg-success' : 'bg-secondary' ?>-subtle border text-dark" style="font-size: 0.72rem;">
                        <i class="bi bi-person me-1"></i><?= e($s['name']) ?>
                        <?php if ($s['is_expert']): ?><i class="bi bi-star-fill text-warning ms-1"></i><?php endif; ?>
                        <small class="text-muted ms-1"><?= $s['weekly_load'] ?>/<?= $s['max_week'] ?></small>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <?php if (has_permission('routine.manage')): ?>
              <td class="text-end">
                <select class="form-select form-select-sm d-inline-block w-auto quick-assign-teacher" data-slot-id="<?= $slot['id'] ?>">
                  <option value="">— Pick Replacement —</option>
                  <?php foreach ($sugg as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= e($s['name']) ?><?= $s['is_expert'] ? ' ★' : '' ?> (<?= $s['weekly_load'] ?>)</option>
                  <?php endforeach; ?>
                  <option value="clear">— Clear (Unassign) —</option>
                </select>
                <button class="btn btn-sm btn-warning ms-1" onclick="quickAssignTeacher(this)" data-slot-id="<?= $slot['id'] ?>">
                  <i class="bi bi-arrow-repeat"></i> Reassign
                </button>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Conflict/Double-Booked Slots -->
    <?php if (!empty($double_booked_slots)): ?>
    <div class="card mb-4 border-danger">
      <div class="card-header bg-danger-subtle text-danger d-flex align-items-center">
        <i class="bi bi-arrow-left-right me-2 fs-5"></i>
        <span class="fw-bold">Conflict / Double-Booked Slots (<?= count($double_booked_slots) ?>)</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Class / Section</th>
              <th>Day / Time</th>
              <th>Subject</th>
              <th>Teacher</th>
              <th>Conflict Reason</th>
              <?php if (has_permission('routine.manage')): ?>
                <th class="text-end">Go to Cell</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($double_booked_slots as $item): 
              $slot = $item['slot'];
            ?>
            <tr class="table-danger">
              <td><strong><?= e($slot['class_name'] ?? '—') ?></strong></td>
              <td>
                <div class="small fw-bold"><?= e($slot['day_of_week']) ?></div>
                <div class="text-muted small"><?= date('g:i A', strtotime($slot['start_time'])) ?> – <?= date('g:i A', strtotime($slot['end_time'])) ?></div>
              </td>
              <td><?= e($slot['subject_name']) ?></td>
              <td><?= e($slot['teacher_name'] ?? 'N/A') ?></td>
              <td><span class="badge bg-danger"><?= e($item['msg']) ?></span></td>
              <?php if (has_permission('routine.manage')): ?>
              <td class="text-end">
                <a href="routine.php?session_id=<?= $session_id ?>" class="btn btn-sm btn-outline-danger">
                  <i class="bi bi-pencil-square me-1"></i>Fix in Routine
                </a>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Overloaded Teachers -->
    <?php if (!empty($overloaded)): ?>
    <div class="card mb-4 border-warning">
      <div class="card-header bg-warning-subtle text-warning-durable d-flex align-items-center">
        <i class="bi bi-speedometer2 me-2 fs-5"></i>
        <span class="fw-bold">Overloaded Teachers — Exceeding Weekly Limit (<?= count($overloaded) ?>)</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Teacher</th>
              <th>Department</th>
              <th class="text-center">Assigned / Max Classes</th>
              <th class="text-center">Over by</th>
              <th class="text-end">Manage Limit</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($overloaded as $item): ?>
            <tr class="table-warning">
              <td><strong><?= e($item['teacher']['name']) ?></strong></td>
              <td><span class="small text-muted"><?= e($item['teacher']['department']) ?></span></td>
              <td class="text-center">
                <span class="badge bg-danger fs-6"><?= $item['load'] ?> / <?= $item['max'] ?></span>
              </td>
              <td class="text-center">
                <span class="badge bg-warning text-dark">+<?= $item['excess'] ?> extra</span>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" onclick="openLimitsModal(<?= $item['teacher']['id'] ?>, '<?= e($item['teacher']['name']) ?>', <?= $item['teacher']['max_classes_per_day'] ?: 4 ?>, <?= $item['max'] ?>)">
                  <i class="bi bi-sliders me-1"></i>Adjust Limit
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Idle / Underloaded Teachers -->
    <?php if (!empty($underloaded)): ?>
    <div class="card mb-4 border-info">
      <div class="card-header bg-info-subtle text-info d-flex align-items-center">
        <i class="bi bi-person-dash me-2 fs-5"></i>
        <span class="fw-bold">Idle Active Teachers — No Classes Assigned (<?= count($underloaded) ?>)</span>
      </div>
      <div class="card-body py-2">
        <p class="text-muted small mb-2">These active teachers have zero classes in the current routine. Consider assigning unassigned periods to them.</p>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($underloaded as $t): 
            $tExp = $expertise[$t['id']] ?? [];
          ?>
            <div class="card border-info-subtle p-2 text-center" style="min-width: 160px;">
              <div class="fw-bold small"><?= e($t['name']) ?></div>
              <div class="text-muted" style="font-size: 0.72rem;"><?= e($t['designation']) ?></div>
              <?php if (!empty($tExp)): ?>
                <div class="mt-1 d-flex flex-wrap gap-1 justify-content-center">
                  <?php foreach (array_slice($tExp, 0, 2) as $sub): ?>
                    <span class="badge bg-info-subtle text-info border" style="font-size: 0.65rem;"><?= e($sub['subject_name']) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span class="text-muted" style="font-size: 0.7rem;">No expertise set</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- New Hire Auto-Resolve Notice -->
    <div class="alert alert-info d-flex align-items-start gap-3 mb-0">
      <i class="bi bi-lightbulb-fill fs-4 text-info mt-1"></i>
      <div>
        <strong>New Hire Auto-Resolution:</strong> When a new teacher is added to the staff roster with matching subject expertise, the system will automatically surface them as top candidates in the suggestions above and in the Substitution Planner. To permanently resolve inactive teacher slots, use the <em>Reassign</em> buttons above or click <strong>Fix in Grid</strong> to open the timetable editor for that period.
      </div>
    </div>

    <?php endif; ?>
  </div>
  <!-- /Health Tab -->

</div>

<!-- Redesigned Edit Planner Cell Modal (Supports Parallel/Split Subjects) -->
<div class="modal fade" id="plannerCellModal" tabindex="-1" aria-labelledby="plannerCellModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="plannerCellModalLabel"><i class="bi bi-pencil-square me-2 text-primary"></i>Redefine Period Slots</h5>
        <button type="button" class="btn-close" data-bs-close="modal" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2 small d-flex justify-content-between align-items-center mb-3">
          <div>
            <strong>Class & Section:</strong> <span id="cell-class-name" class="badge bg-primary fs-7"></span> &bull; 
            <strong>Period Name:</strong> <span id="cell-period-name" class="fw-bold"></span> &bull; 
            <strong>Period Time:</strong> <span id="cell-period-times" class="badge bg-secondary fs-7"></span>
          </div>
        </div>
        
        <!-- Headers -->
        <div class="row g-2 mb-2 fw-bold text-secondary text-center d-none d-md-flex">
          <div class="col-md-2 text-start ps-3">Weekday</div>
          <div class="col-md-3">Subject</div>
          <div class="col-md-3">Assigned Teacher</div>
          <div class="col-md-3">Room Assignment</div>
          <div class="col-md-1">Actions</div>
        </div>

        <div id="cell-days-container" class="d-flex flex-column gap-2">
          <!-- Weekday rows dynamically populated -->
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="saveCellSchedule()"><i class="bi bi-check-circle me-1"></i>Save Weekly Cell Timetable</button>
      </div>
    </div>
  </div>
</div>

<!-- Section-Specific Period timings customization Modal -->
<div class="modal fade" id="sectionPeriodsModal" tabindex="-1" aria-labelledby="sectionPeriodsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning-subtle">
        <h5 class="modal-title fw-bold text-warning-durable" id="sectionPeriodsModalLabel"><i class="bi bi-clock-history me-2"></i>Custom Periods: <span id="custom-sec-name"></span></h5>
        <button type="button" class="btn-close" data-bs-close="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">Configure specific timings or class periods for this section. If empty, the global template will be followed.</p>
        <form id="sec-periods-form" onsubmit="saveSectionPeriods(event)">
          <input type="hidden" id="custom-sec-id" name="section_id">
          <div class="table-responsive">
            <table class="table table-sm align-middle text-center">
              <thead>
                <tr>
                  <th>Slot Name</th>
                  <th>Start Time</th>
                  <th>End Time</th>
                  <th>Is Break?</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="sec-periods-rows-container">
                <!-- Custom section rows dynamically loaded -->
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addNewPeriodRow('section')"><i class="bi bi-plus-lg me-1"></i>Add Row</button>
            <div>
              <button type="button" class="btn btn-sm btn-outline-danger" onclick="resetSectionToGlobal()"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Global Default</button>
              <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Apply Timings</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Workload Limits Modal -->
<div class="modal fade" id="limitsModal" tabindex="-1" aria-labelledby="limitsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <form method="POST" action="master_routine.php?session_id=<?= $session_id ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_limits">
        <input type="hidden" name="teacher_id" id="limits-teacher-id">
        <div class="modal-header">
          <h6 class="modal-title fw-700" id="limitsModalLabel">Workload Limits</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-primary fw-600 mb-3" id="limits-teacher-name"></div>
          <div class="mb-3">
            <label class="form-label small">Max Classes / Day</label>
            <input type="number" name="max_classes_per_day" id="limits-max-day" class="form-control form-control-sm" min="1" max="10" required>
          </div>
          <div class="mb-3">
            <label class="form-label small">Max Classes / Week</label>
            <input type="number" name="max_classes_per_week" id="limits-max-week" class="form-control form-control-sm" min="1" max="40" required>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Subjects Expertise Modal -->
<div class="modal fade" id="expertiseModal" tabindex="-1" aria-labelledby="expertiseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="master_routine.php?session_id=<?= $session_id ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_expertise">
        <input type="hidden" name="teacher_id" id="exp-teacher-id">
        <div class="modal-header">
          <h6 class="modal-title fw-700" id="expertiseModalLabel">Expert Subjects Mapping</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
          <div class="text-primary fw-600 mb-3" id="exp-teacher-name"></div>
          <p class="text-muted small">Select the subjects this teacher specializes in teaching:</p>
          <div class="row g-2">
            <?php foreach ($allSubjects as $sub): ?>
              <div class="col-6">
                <div class="form-check">
                  <input type="checkbox" name="subjects[]" value="<?= $sub['id'] ?>" id="chk-sub-<?= $sub['id'] ?>" class="form-check-input exp-checkbox">
                  <label class="form-check-label small" for="chk-sub-<?= $sub['id'] ?>"><?= e($sub['subject_name']) ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Update Expert List</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const workingDays     = <?= json_encode($working_days) ?>;
const dayFullNames    = <?= json_encode($day_full_names) ?>;
const subjectsList    = <?= json_encode($allSubjects) ?>;
const teachersList    = <?= json_encode($teachers) ?>;
const roomsList       = <?= json_encode($rooms) ?>;
const defaultPeriods  = <?= json_encode($default_periods) ?>;
const currentSessionId = <?= (int)$session_id ?>;
// Section map: sectionId → {class_id, class_name, section_name}
const sectionMeta = <?= json_encode(array_column($sections, null, 'section_id')) ?>;

// Auto-activate tab from URL hash (e.g. after reassignment redirect)
document.addEventListener('DOMContentLoaded', function() {
  const hash = window.location.hash;
  if (hash) {
    const tabLink = document.querySelector(`a[href="${hash}"]`);
    if (tabLink) {
      new bootstrap.Tab(tabLink).show();
    }
  }
});


let currentSectionId = null;
let currentClassId   = null;
let currentPeriodStart = '';
let currentPeriodEnd   = '';

// Load data and open Planner Cell Modal
function openPlannerCellModal(sectionId, sectionName, periodName, startTime, endTime) {
  currentSectionId = sectionId;
  currentClassId   = (sectionMeta[sectionId] || {}).class_id || 0;
  currentPeriodStart = startTime;
  currentPeriodEnd   = endTime;

  document.getElementById('cell-class-name').innerText = sectionName;
  document.getElementById('cell-period-name').innerText = periodName;
  document.getElementById('cell-period-times').innerText = `${startTime} - ${endTime}`;

  const container = document.getElementById('cell-days-container');
  container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading slots...</div>';

  const modal = new bootstrap.Modal(document.getElementById('plannerCellModal'));
  modal.show();

  // Load existing slots for this section and time
  const slotsMap = <?= json_encode($indexed_slots) ?>;
  const sectionSlots = slotsMap[sectionId] ? (slotsMap[sectionId][startTime] || {}) : {};

  container.innerHTML = '';
  
  workingDays.forEach(dayCode => {
    const dayName = dayFullNames[dayCode.trim()];
    if (!dayName) return;
    
    const slots = sectionSlots[dayName] || [];
    
    // Add header or container for this weekday
    const daySection = document.createElement('div');
    daySection.className = `p-2 rounded border mb-2 day-group-container`;
    daySection.dataset.day = dayName;
    daySection.style.background = '#fcfdfd';
    
    daySection.innerHTML = `
      <div class="d-flex align-items-center justify-content-between mb-1">
        <span class="fw-bold text-primary"><i class="bi bi-calendar-check me-1"></i>${dayName}</span>
        <button type="button" class="btn btn-xs btn-outline-success" onclick="addSlotRow('${dayName}', null)">
          <i class="bi bi-plus-lg me-1"></i>Add Parallel Split
        </button>
      </div>
      <div class="slots-rows-wrapper d-flex flex-column gap-2"></div>
    `;
    
    container.appendChild(daySection);
    const rowsWrapper = daySection.querySelector('.slots-rows-wrapper');

    if (slots.length > 0) {
      slots.forEach(slot => {
        addSlotRow(dayName, slot, rowsWrapper);
      });
    } else {
      addSlotRow(dayName, null, rowsWrapper);
    }
  });
}

// Append a dynamic slot row for a given day
function addSlotRow(dayName, slot = null, containerEl = null) {
  if (!containerEl) {
    // Find container for this day
    const dayContainer = document.querySelector(`.day-group-container[data-day="${dayName}"]`);
    if (dayContainer) {
      containerEl = dayContainer.querySelector('.slots-rows-wrapper');
    }
  }
  if (!containerEl) return;

  const rowId = 'slot_' + Math.random().toString(36).substr(2, 9);
  const div = document.createElement('div');
  div.className = 'row g-2 align-items-center slot-edit-row';
  div.id = rowId;
  div.dataset.slotId = slot ? slot.id : 0;

  div.innerHTML = `
    <div class="col-md-4">
      <select class="form-select form-select-sm subject-select" onchange="loadSuggestions(this)">
        <option value="0">— Leave Empty —</option>
        ${subjectsList.map(s => `<option value="${s.id}" ${slot && slot.subject_id == s.id ? 'selected' : ''}>${s.subject_name}</option>`).join('')}
      </select>
    </div>
    <div class="col-md-4">
      <select class="form-select form-select-sm teacher-select">
        <option value="0">— No Teacher Assigned —</option>
        ${teachersList.map(t => `<option value="${t.id}" ${slot && slot.teacher_id == t.id ? 'selected' : ''}>${t.name}</option>`).join('')}
      </select>
    </div>
    <div class="col-md-3">
      <select class="form-select form-select-sm room-select">
        <option value="0">— No Room —</option>
        ${roomsList.map(r => `<option value="${r.id}" ${slot && slot.room_id == r.id ? 'selected' : ''}>${r.room_name}</option>`).join('')}
      </select>
    </div>
    <div class="col-md-1 text-center">
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSlotEditRow('${rowId}')"><i class="bi bi-x-lg"></i></button>
    </div>
  `;

  containerEl.appendChild(div);
  
  // Trigger initial suggestions load if slot contains a subject
  if (slot && slot.subject_id) {
    const subjSel = div.querySelector('.subject-select');
    loadSuggestions(subjSel, slot.teacher_id, slot.room_id);
  }
}

function removeSlotEditRow(rowId) {
  const row = document.getElementById(rowId);
  if (!row) return;
  const parentWrapper = row.parentElement;
  
  // If this is a saved slot, we mark it as deleted by appending to a global list
  const slotId = parseInt(row.dataset.slotId);
  if (slotId > 0) {
    if (!window.deletedSlotIds) window.deletedSlotIds = [];
    window.deletedSlotIds.push(slotId);
  }
  row.remove();

  // If wrapper becomes empty, add one empty row
  if (parentWrapper.children.length === 0) {
    const dayName = parentWrapper.closest('.day-group-container').dataset.day;
    addSlotRow(dayName, null, parentWrapper);
  }
}

// Load real-time expert recommendations, class teacher prioritization, and free rooms
function loadSuggestions(subjSelect, preSelectedTeacherId = 0, preSelectedRoomId = 0) {
  const row = subjSelect.closest('.slot-edit-row');
  const dayName = subjSelect.closest('.day-group-container').dataset.day;
  const subId = subjSelect.value;
  const teacherSelect = row.querySelector('.teacher-select');
  const roomSelect = row.querySelector('.room-select');

  const selectedTeacher = preSelectedTeacherId || teacherSelect.value;
  const selectedRoom = preSelectedRoomId || roomSelect.value;

  if (subId === '0') {
    teacherSelect.innerHTML = '<option value="0">— No Teacher Assigned —</option>' + 
      teachersList.map(t => `<option value="${t.id}" ${selectedTeacher == t.id ? 'selected' : ''}>${t.name}</option>`).join('');
    roomSelect.innerHTML = '<option value="0">— No Room —</option>' + 
      roomsList.map(r => `<option value="${r.id}" ${selectedRoom == r.id ? 'selected' : ''}>${r.room_name}</option>`).join('');
    return;
  }

  fetch(`ajax.php?action=get_suggestions&subject_id=${subId}&session_id=${currentSessionId}&day=${dayName}&start=${currentPeriodStart}&end=${currentPeriodEnd}&section_id=${currentSectionId}`)
    .then(r => r.text())
    .then(t => { try { return JSON.parse(t); } catch(e) { return {experts:[], free_rooms:[], class_teacher_rec:null}; } })
    .then(data => {
      // 1. Populate Teachers Dropdown
      let teacherHtml = '<option value="0">— No Teacher Assigned —</option>';
      const suggestedIds = [];

      // A. Class Teacher Recommendation First
      if (data.class_teacher_rec) {
        const ct = data.class_teacher_rec;
        suggestedIds.push(ct.id);
        const busyStr = ct.is_busy ? ` [Busy: ${ct.busy_with}]` : '';
        const loadStr = `Load: ${ct.weekly_load}/${ct.max_classes_per_week}`;
        const selected = selectedTeacher == ct.id ? 'selected' : '';
        teacherHtml += `<option value="${ct.id}" ${selected} style="font-weight:bold; color:#198754;">★ ${ct.name} (Class Teacher, ${loadStr})${busyStr}</option>`;
      }

      // B. Expert Teachers Next
      if (data.experts && data.experts.length > 0) {
        data.experts.forEach(exp => {
          if (suggestedIds.includes(exp.id)) return;
          suggestedIds.push(exp.id);
          const busyStr = exp.is_busy ? ` [Busy: ${exp.busy_with}]` : '';
          const loadStr = `Load: ${exp.weekly_load}/${exp.max_classes_per_week}`;
          const selected = selectedTeacher == exp.id ? 'selected' : '';
          teacherHtml += `<option value="${exp.id}" ${selected} style="font-weight:bold; color:#0d6efd;">★ ${exp.name} (Expert, ${loadStr})${busyStr}</option>`;
        });
      }

      // C. All Other Teachers
      teachersList.forEach(t => {
        if (!suggestedIds.includes(t.id)) {
          const selected = selectedTeacher == t.id ? 'selected' : '';
          teacherHtml += `<option value="${t.id}" ${selected}>${t.name}</option>`;
        }
      });
      teacherSelect.innerHTML = teacherHtml;

      // 2. Populate Rooms Dropdown
      let roomHtml = '<option value="0">— No Room —</option>';
      const freeRoomIds = (data.free_rooms || []).map(r => r.id);

      // Free Rooms first
      (data.free_rooms || []).forEach(r => {
        const selected = selectedRoom == r.id ? 'selected' : '';
        roomHtml += `<option value="${r.id}" ${selected} style="color:#198754; font-weight:500;">✓ ${r.room_name} (Free, Cap: ${r.capacity})</option>`;
      });

      // Occupied Rooms
      roomsList.forEach(r => {
        if (!freeRoomIds.includes(r.id)) {
          const selected = selectedRoom == r.id ? 'selected' : '';
          roomHtml += `<option value="${r.id}" ${selected} class="text-danger">[Busy] ${r.room_name}</option>`;
        }
      });
      roomSelect.innerHTML = roomHtml;
    });
}

// Save all rows for the cell
function saveCellSchedule() {
  const promises = [];
  window.deletedSlotIds = window.deletedSlotIds || [];

  // Delete removed slots
  window.deletedSlotIds.forEach(id => {
    const fd = new FormData();
    fd.append('id', id);
    promises.push(
      fetch('ajax.php?action=delete_slot', { method: 'POST', body: fd })
        .then(r => r.text()).then(t => { try { return JSON.parse(t); } catch(e) { return {success:false,error:t.substring(0,100)}; } })
    );
  });

  // Save/update rows
  const dayGroups = document.querySelectorAll('.day-group-container');
  dayGroups.forEach(group => {
    const day = group.dataset.day;
    const rows = group.querySelectorAll('.slot-edit-row');
    
    rows.forEach(row => {
      const slotId = row.dataset.slotId;
      const subId = row.querySelector('.subject-select').value;
      const teacherId = row.querySelector('.teacher-select').value;
      const roomId = row.querySelector('.room-select').value;

      if (subId === '0') {
        // If subject is cleared, delete slot if it existed
        if (slotId !== '0') {
          const fd = new FormData();
          fd.append('id', slotId);
          promises.push(
            fetch('ajax.php?action=delete_slot', { method: 'POST', body: fd })
              .then(r => r.text()).then(t => { try { return JSON.parse(t); } catch(e) { return {success:false,error:t.substring(0,100)}; } })
          );
        }
      } else {
        const fd = new FormData();
        fd.append('id', slotId);
        fd.append('session_id', currentSessionId);
        fd.append('class_id', currentClassId); // resolved from sectionMeta at modal open
        fd.append('section_id', currentSectionId);
        fd.append('subject_id', subId);
        fd.append('teacher_id', teacherId === '0' ? '' : teacherId);
        fd.append('room_id', roomId === '0' ? '' : roomId);
        fd.append('day_of_week', day);
        fd.append('start_time', currentPeriodStart);
        fd.append('end_time', currentPeriodEnd);
        fd.append('force', '1'); // Force save overrides conflicts

        promises.push(
          fetch('ajax.php?action=save_slot', { method: 'POST', body: fd })
            .then(r => r.text()).then(t => { try { return JSON.parse(t); } catch(e) { return {success:false,error:t.substring(0,100)}; } })
        );
      }
    });
  });

  Promise.all(promises)
    .then(results => {
      window.deletedSlotIds = [];
      const errors = results.filter(r => r && !r.success).map(r => r.error || 'Unknown error');
      if (errors.length > 0) {
        alert('Some slots could not be saved:\n' + errors.join('\n'));
      }
      window.location.reload();
    })
    .catch(err => {
      alert('Error saving timetable: ' + err.message);
    });
}

// Section Periods Override functions
function openSectionPeriodsModal(secId, secName) {
  currentSectionId = secId;
  document.getElementById('custom-sec-id').value = secId;
  document.getElementById('custom-sec-name').innerText = secName;

  const container = document.getElementById('sec-periods-rows-container');
  container.innerHTML = '<tr><td colspan="5" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Fetching timings...</td></tr>';

  new bootstrap.Modal(document.getElementById('sectionPeriodsModal')).show();

  // Section-specific period overrides are inlined from PHP at page-load time
  const allSectionPeriods = {
    <?php foreach ($sections as $s): 
      $k = "section_periods_" . $s['section_id'];
      $val = setting($k);
      if ($val): ?>
        "<?= $s['section_id'] ?>": <?= $val ?>,
      <?php endif; 
    endforeach; ?>
  };

  const currentPeriods = allSectionPeriods[secId] || defaultPeriods;
  populateSectionPeriodsRows(currentPeriods);
}

function populateSectionPeriodsRows(periods) {
  const container = document.getElementById('sec-periods-rows-container');
  container.innerHTML = '';
  periods.forEach((p, idx) => {
    const tr = document.createElement('tr');
    tr.dataset.index = idx;
    tr.innerHTML = `
      <td><input type="text" class="form-control form-control-sm text-center fw-bold row-name" value="${p.name}" required></td>
      <td><input type="time" class="form-control form-control-sm text-center row-start" value="${p.start}" required></td>
      <td><input type="time" class="form-control form-control-sm text-center row-end" value="${p.end}" required></td>
      <td>
        <select class="form-select form-select-sm text-center row-break">
          <option value="0" ${p.is_break == 0 ? 'selected' : ''}>No</option>
          <option value="1" ${p.is_break == 1 ? 'selected' : ''}>Yes</option>
        </select>
      </td>
      <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removePeriodRow(this)"><i class="bi bi-trash"></i></button></td>
    `;
    container.appendChild(tr);
  });
}

function addNewPeriodRow(type) {
  const container = type === 'global' 
    ? document.getElementById('periods-rows-container') 
    : document.getElementById('sec-periods-rows-container');
  
  const index = container.children.length;
  const tr = document.createElement('tr');
  tr.dataset.index = index;
  tr.innerHTML = `
    <td><input type="text" class="form-control form-control-sm text-center fw-bold ${type === 'global' ? '' : 'row-name'}" name="name" value="Period ${index + 1}" required></td>
    <td><input type="time" class="form-control form-control-sm text-center ${type === 'global' ? '' : 'row-start'}" name="start" value="09:00" required></td>
    <td><input type="time" class="form-control form-control-sm text-center ${type === 'global' ? '' : 'row-end'}" name="end" value="09:45" required></td>
    <td>
      <select class="form-select form-select-sm text-center ${type === 'global' ? '' : 'row-break'}" name="is_break">
        <option value="0">No (Class Period)</option>
        <option value="1">Yes (Break/Recess)</option>
      </select>
    </td>
    <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removePeriodRow(this)"><i class="bi bi-trash"></i></button></td>
  `;
  container.appendChild(tr);
}

function removePeriodRow(btn) {
  btn.closest('tr').remove();
}

function saveSectionPeriods(e) {
  e.preventDefault();
  const rows = document.querySelectorAll('#sec-periods-rows-container tr');
  const periodsData = [];

  rows.forEach(tr => {
    periodsData.push({
      name: tr.querySelector('.row-name').value,
      start: tr.querySelector('.row-start').value,
      end: tr.querySelector('.row-end').value,
      is_break: parseInt(tr.querySelector('.row-break').value)
    });
  });

  const fd = new FormData();
  fd.append('section_id', currentSectionId);
  fd.append('periods', JSON.stringify(periodsData));
  fd.append('csrf_token', '<?= csrf_token() ?>');

  fetch('ajax.php?action=save_section_periods', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        alert('Section custom period timings applied successfully!');
        window.location.reload();
      } else {
        alert('Error: ' + res.error);
      }
    });
}

function resetSectionToGlobal() {
  if (confirm('Are you sure you want to reset this section\'s periods to global defaults?')) {
    const fd = new FormData();
    fd.append('section_id', currentSectionId);
    fd.append('periods', '[]');
    fd.append('csrf_token', '<?= csrf_token() ?>');

    fetch('ajax.php?action=save_section_periods', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          alert('Section periods reset to global defaults!');
          window.location.reload();
        }
      });
  }
}

// Save Global periods configuration
function savePeriodsConfig(e) {
  e.preventDefault();
  const rows = document.querySelectorAll('#periods-rows-container tr');
  const periodsData = [];

  rows.forEach(tr => {
    periodsData.push({
      name: tr.querySelector('input[name="name"]').value,
      start: tr.querySelector('input[name="start"]').value,
      end: tr.querySelector('input[name="end"]').value,
      is_break: parseInt(tr.querySelector('select[name="is_break"]').value)
    });
  });

  const fd = new FormData();
  fd.append('periods', JSON.stringify(periodsData));
  fd.append('csrf_token', '<?= csrf_token() ?>');

  fetch('ajax.php?action=save_period_config', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        alert('Global period settings saved successfully!');
        window.location.reload();
      } else {
        alert('Error: ' + res.error);
      }
    });
}

// Trigger auto generation
function triggerAutoGenerate() {
  if (confirm('Are you sure you want to AUTO-GENERATE the weekly Master Timetable?\n\nWARNING: This will clear all existing scheduled slots for this session and build a fresh balanced draft!')) {
    const fd = new FormData();
    fd.append('session_id', currentSessionId);
    
    const btn = document.querySelector('button[onclick="triggerAutoGenerate()"]');
    btn.innerHTML = '⏳ Generating Timetable...';
    btn.disabled = true;

    fetch('ajax.php?action=auto_generate', { method: 'POST', body: fd })
      .then(r => r.text())
      .then(text => {
        let res;
        try { res = JSON.parse(text); }
        catch (e) {
          // PHP printed something before JSON (warning/error) — show first 300 chars
          throw new Error('Server error: ' + text.replace(/<[^>]+>/g, '').trim().substring(0, 300));
        }
        if (res.success) {
          alert('✓ Timetable auto-generated! The page will reload.');
          window.location.reload();
        } else {
          throw new Error(res.error || 'Unknown error from server');
        }
      })
      .catch(err => {
        alert('Auto-generate failed:\n\n' + err.message);
        btn.innerHTML = '<i class="bi bi-cpu me-1"></i>Auto-Generate Timetable';
        btn.disabled = false;
      });
  }
}

// Workload Limits Modal
function openLimitsModal(id, name, maxDay, maxWeek) {
  document.getElementById('limits-teacher-id').value = id;
  document.getElementById('limits-teacher-name').innerText = name;
  document.getElementById('limits-max-day').value = maxDay;
  document.getElementById('limits-max-week').value = maxWeek;
  new bootstrap.Modal(document.getElementById('limitsModal')).show();
}

// Subject Experts mapping modal
function openExpertModal(id, name, selectedSubIds) {
  document.getElementById('exp-teacher-id').value = id;
  document.getElementById('exp-teacher-name').innerText = name;
  
  // Uncheck all
  document.querySelectorAll('.exp-checkbox').forEach(cb => cb.checked = false);
  
  // Check the mapped ones
  selectedSubIds.forEach(subId => {
    const cb = document.getElementById('chk-sub-' + subId);
    if (cb) cb.checked = true;
  });
  
  new bootstrap.Modal(document.getElementById('expertiseModal')).show();
}

// Quick Assign / Reassign teacher from the Health tab
function quickAssignTeacher(btn) {
  const slotId = btn.dataset.slotId;
  // Find the nearest <select> sibling with class quick-assign-teacher
  const row = btn.closest('td');
  const sel = row ? row.querySelector('.quick-assign-teacher') : null;
  if (!sel) { alert('Could not find teacher selector.'); return; }
  
  const teacherValue = sel.value;
  if (!teacherValue) { alert('Please select a teacher first.'); return; }
  
  const newTeacherId = teacherValue === 'clear' ? null : parseInt(teacherValue);
  
  if (!confirm('Permanently reassign this slot to the selected teacher in the Master Routine?')) return;
  
  const fd = new FormData();
  fd.append('slot_id', slotId);
  fd.append('new_teacher_id', newTeacherId || '');
  fd.append('action', 'reassign_permanent');
  fd.append('csrf_token', '<?= csrf_token() ?>');
  
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  
  fetch('master_routine.php?session_id=' + currentSessionId, { method: 'POST', body: fd })
    .then(() => {
      window.location.reload();
    })
    .catch(() => {
      alert('Error saving reassignment. Please try again.');
      btn.disabled = false;
    });
}

</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
