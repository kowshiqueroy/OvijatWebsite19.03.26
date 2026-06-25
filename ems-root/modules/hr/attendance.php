<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Staff Attendance';
$breadcrumbs = ['HR & Payroll' => 'staff.php', 'Attendance' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['attendance.mark']);

$pdo  = db();
$date = $_GET['date'] ?? date('Y-m-d');
$dept = trim($_GET['dept'] ?? '');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Save daily attendance
    if ($action === 'save') {
        $att_date = $_POST['att_date'] ?? date('Y-m-d');
        $statuses = $_POST['status'] ?? [];
        $stmt = $pdo->prepare('INSERT INTO staff_attendance (staff_id,attendance_date,status,marked_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),marked_by=VALUES(marked_by)');
        foreach ($statuses as $uid => $st) {
            $stmt->execute([$uid, $att_date, $st, current_user_id()]);
        }
        flash('success', count($statuses) . ' records saved for ' . fmt_date($att_date) . '.');
        header("Location: attendance.php?date=$att_date&dept=" . urlencode($dept));
        exit;
    }

    // Assign temporary substitute teacher
    if ($action === 'assign_substitute') {
        $slot_id        = int_param('slot_id', 0, $_POST);
        $sub_teacher_id = int_param('substitute_teacher_id', 0, $_POST);
        $att_date       = $_POST['att_date'] ?? date('Y-m-d');

        if ($slot_id && $sub_teacher_id) {
            $orig = $pdo->prepare('SELECT * FROM routine_slots WHERE id = ?');
            $orig->execute([$slot_id]);
            $o = $orig->fetch();

            if ($o) {
                // Disable existing active substitute for same original details
                $pdo->prepare('UPDATE routine_slots SET status = 0 WHERE substitute_date = ? AND start_time = ? AND section_id = ?')
                    ->execute([$att_date, $o['start_time'], $o['section_id']]);

                // Insert new substitute slot
                $pdo->prepare(
                    'INSERT INTO routine_slots (session_id, class_id, section_id, subject_id, teacher_id, room_id, day_of_week, start_time, end_time, is_substitute, substitute_date)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
                )->execute([
                    $o['session_id'], $o['class_id'], $o['section_id'], $o['subject_id'],
                    $sub_teacher_id, $o['room_id'], $o['day_of_week'], $o['start_time'], $o['end_time'],
                    $att_date
                ]);
                flash('success', 'Substitute teacher assigned successfully.');
            }
        }
        header("Location: attendance.php?date=$att_date");
        exit;
    }
}

// Fetch staff profiles with attendance mapping
$where  = ['sp.status="active"'];
$params = [];
if ($dept) {
    $where[] = 'sp.department=:dept';
    $params[':dept'] = $dept;
}
$whereStr = implode(' AND ', $where);

$staff = $pdo->prepare(
    "SELECT sp.user_id, CONCAT(sp.first_name,' ',sp.last_name) as name, sp.designation, sp.department, sa.status as att_status 
     FROM staff_profiles sp 
     LEFT JOIN staff_attendance sa ON sa.staff_id=sp.user_id AND sa.attendance_date=:d 
     WHERE $whereStr 
     ORDER BY sp.department, sp.first_name"
);
$params[':d'] = $date;
$staff->execute($params);
$staff = $staff->fetchAll();

$depts = $pdo->query('SELECT DISTINCT department FROM staff_profiles WHERE department IS NOT NULL AND department!="" AND status="active" ORDER BY department')->fetchAll(PDO::FETCH_COLUMN);

// Last 7-day summary
$summary = $pdo->query("SELECT attendance_date, status, COUNT(*) as cnt FROM staff_attendance WHERE attendance_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY) GROUP BY attendance_date,status ORDER BY attendance_date DESC")->fetchAll();
$daySum = [];
foreach ($summary as $r) {
    $daySum[$r['attendance_date']][$r['status']] = $r['cnt'];
}

// ── Identify Absent Teachers & Find Scheduled Classes ───────────────────────
$absentTeachers = [];
$day_of_week = date('l', strtotime($date));

foreach ($staff as $st) {
    $status = $st['att_status'] ?? 'present';
    if ($status === 'absent' || $status === 'on_leave') {
        $desig = strtolower($st['designation'] ?? '');
        if (str_contains($desig, 'teacher') || str_contains($desig, 'lecturer') || str_contains($desig, 'faculty')) {
            // Fetch scheduled class slots for this absent teacher
            $slotsStmt = $pdo->prepare(
                "SELECT rs.*, c.class_name, sec.section_name, s.subject_name 
                 FROM routine_slots rs
                 JOIN classes c ON c.id = rs.class_id
                 JOIN sections sec ON sec.id = rs.section_id
                 JOIN subjects s ON s.id = rs.subject_id
                 WHERE rs.teacher_id = :tid AND rs.day_of_week = :day AND rs.status = 1 AND rs.is_substitute = 0"
            );
            $slotsStmt->execute([':tid' => $st['user_id'], ':day' => $day_of_week]);
            $slots = $slotsStmt->fetchAll();

            if (!empty($slots)) {
                $st['slots'] = $slots;
                $absentTeachers[] = $st;
            }
        }
    }
}

require_once EMS_ROOT . '/includes/header.php';
?>

<h1 class="page-title"><i class="bi bi-calendar-check-fill me-2 text-primary"></i>Staff Attendance</h1>

<!-- Filters & Quick Actions -->
<div class="card mb-4">
  <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <form method="GET" class="row g-2 align-items-end mb-0">
      <div class="col-auto">
        <label class="form-label small fw-600">Date</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>" onchange="this.form.submit()">
      </div>
      <div class="col-auto">
        <label class="form-label small fw-600">Department</label>
        <select name="dept" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Departments</option>
          <?php foreach ($depts as $d): ?><option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
        </select>
      </div>
    </form>
    
    <div class="d-flex align-items-center gap-2">
      <div class="form-check form-switch mb-0 me-3">
        <input class="form-check-input" type="checkbox" role="switch" id="absenteesOnlyMode" onchange="toggleMode()">
        <label class="form-check-label small fw-bold" for="absenteesOnlyMode">Absentees Only Mode</label>
      </div>
      <div class="d-flex gap-1" id="bulk-att-buttons">
        <button type="button" class="btn btn-sm btn-outline-success" onclick="setAll('present')">All Present</button>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="setAll('absent')">All Absent</button>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Attendance Marker -->
  <div class="col-md-8">
    <div class="card shadow-sm mb-4">
      <div class="card-header py-3 px-4 bg-light"><span class="card-title">Staff List — <?= fmt_date($date) ?> <span class="badge bg-secondary"><?= count($staff) ?></span></span></div>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="att_date" value="<?= e($date) ?>">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="width: 50px;">#</th>
                <th>Name</th>
                <th>Department</th>
                <th class="normal-att-header text-center">P</th>
                <th class="normal-att-header text-center">A</th>
                <th class="normal-att-header text-center">L</th>
                <th class="normal-att-header text-center">HD</th>
                <th class="normal-att-header text-center">OL</th>
                <th class="absent-only-header d-none text-center" style="width: 120px;">Is Absent?</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($staff as $i => $st):
                $cur = $st['att_status'] ?? 'present'; 
              ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td>
                  <div class="fw-600"><?= e($st['name']) ?></div>
                  <small class="text-muted"><?= e($st['designation'] ?? '') ?></small>
                </td>
                <td><?= e($st['department'] ?? '—') ?></td>
                <?php foreach (['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'half_day' => 'info', 'on_leave' => 'secondary'] as $s => $c): ?>
                  <td class="text-center normal-att-col">
                    <input type="radio" class="form-check-input att-radio" name="status[<?= $st['user_id'] ?>]" value="<?= $s ?>" <?= $cur === $s ? 'checked' : '' ?>>
                  </td>
                <?php endforeach; ?>
                <td class="text-center absent-only-col d-none">
                  <input type="checkbox" class="form-check-input absent-only-chk" style="cursor:pointer;"
                         onchange="syncRadio(<?= $st['user_id'] ?>, this.checked)"
                         <?= $cur === 'absent' ? 'checked' : '' ?>>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer py-3 px-4">
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Attendance</button>
        </div>
      </form>
    </div>

    <!-- Substitute Suggestions Section -->
    <?php if (!empty($absentTeachers)): ?>
      <div class="card shadow-sm">
        <div class="card-header bg-warning-subtle text-warning-durable py-3 px-4">
          <span class="card-title fw-bold"><i class="bi bi-person-exclamation me-2"></i>Absent Teacher Substitutions (<?= count($absentTeachers) ?>)</span>
        </div>
        <div class="card-body">
          <p class="text-muted small">Select a substitute teacher to cover lessons for absent faculty members today.</p>
          
          <?php foreach ($absentTeachers as $teacher): ?>
            <div class="border rounded p-3 mb-3 bg-light">
              <div class="fw-bold text-primary mb-2"><?= e($teacher['name']) ?> (<?= e($teacher['designation']) ?>)</div>
              
              <div class="list-group list-group-flush">
                <?php foreach ($teacher['slots'] as $slot): 
                  // Find available substitute teachers for this slot's time
                  $subs = $pdo->prepare(
                      "SELECT sp.user_id, CONCAT(sp.first_name, ' ', sp.last_name) as name, sp.designation,
                              (SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = sp.user_id AND subject_id = :sub) as is_expert
                       FROM staff_profiles sp
                       WHERE sp.status = 'active' AND (sp.designation LIKE '%teacher%' OR sp.designation LIKE '%lecturer%' OR sp.designation LIKE '%faculty%')
                       AND sp.user_id != :absent_id
                       AND sp.user_id NOT IN (
                           SELECT DISTINCT teacher_id FROM routine_slots 
                           WHERE day_of_week = :day AND status = 1 AND teacher_id IS NOT NULL
                           AND NOT (end_time <= :start OR start_time >= :end)
                       )
                       ORDER BY is_expert DESC, name"
                  );
                  $subs->execute([
                      ':sub'       => $slot['subject_id'],
                      ':absent_id' => $teacher['user_id'],
                      ':day'       => $day_of_week,
                      ':start'     => $slot['start_time'],
                      ':end'       => $slot['end_time']
                  ]);
                  $availableSubs = $subs->fetchAll();
                ?>
                  <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-2 px-0">
                    <div>
                      <span class="badge bg-secondary me-2"><?= substr($slot['start_time'], 0, 5) ?> - <?= substr($slot['end_time'], 0, 5) ?></span>
                      <strong><?= e($slot['class_name']) ?> (<?= e($slot['section_name']) ?>)</strong> — <?= e($slot['subject_name']) ?>
                    </div>
                    
                    <div>
                      <?php if (empty($availableSubs)): ?>
                        <span class="text-danger small">No free teachers available</span>
                      <?php else: ?>
                        <form method="POST" class="d-flex align-items-center gap-2">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="assign_substitute">
                          <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>">
                          <input type="hidden" name="att_date" value="<?= e($date) ?>">
                          <select name="substitute_teacher_id" class="form-select form-select-sm" style="max-width: 220px;" required>
                            <option value="">— Select Substitute —</option>
                            <?php foreach ($availableSubs as $sub): ?>
                              <option value="<?= $sub['user_id'] ?>">
                                <?= e($sub['name']) ?> <?= $sub['is_expert'] ? '★' : '' ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                          <button type="submit" class="btn btn-sm btn-primary">Assign</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Summary Column -->
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-header py-3 px-4 bg-light"><span class="card-title">7-Day Summary</span></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Date</th><th class="text-success text-center">P</th><th class="text-danger text-center">A</th><th class="text-warning text-center">L</th></tr></thead>
          <tbody>
            <?php foreach ($daySum as $d => $row): ?>
            <tr class="<?= $d === $date ? 'table-primary' : '' ?>">
              <td><?= fmt_date($d, 'd M') ?></td>
              <td class="text-success fw-600 text-center"><?= $row['present'] ?? 0 ?></td>
              <td class="text-danger text-center"><?= $row['absent'] ?? 0 ?></td>
              <td class="text-warning text-center"><?= ($row['late'] ?? 0) + ($row['half_day'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($daySum)): ?><tr><td colspan="4" class="text-muted text-center small py-3">No data logged.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function toggleMode() {
  const isAbsentMode = document.getElementById('absenteesOnlyMode').checked;
  
  // Toggle headers
  document.querySelectorAll('.normal-att-header').forEach(el => el.classList.toggle('d-none', isAbsentMode));
  document.querySelectorAll('.absent-only-header').forEach(el => el.classList.toggle('d-none', !isAbsentMode));
  
  // Toggle columns
  document.querySelectorAll('.normal-att-col').forEach(el => el.classList.toggle('d-none', isAbsentMode));
  document.querySelectorAll('.absent-only-col').forEach(el => el.classList.toggle('d-none', !isAbsentMode));
  
  // Toggle bulk buttons
  document.getElementById('bulk-att-buttons').classList.toggle('d-none', isAbsentMode);
}

function syncRadio(uid, isChecked) {
  const val = isChecked ? 'absent' : 'present';
  const radio = document.querySelector(`input[name="status[${uid}]"][value="${val}"]`);
  if (radio) radio.checked = true;
}

function setAll(s) {
  document.querySelectorAll('.att-radio[value="' + s + '"]').forEach(r => r.checked = true);
  document.querySelectorAll('.absent-only-chk').forEach(chk => {
    chk.checked = (s === 'absent');
  });
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
