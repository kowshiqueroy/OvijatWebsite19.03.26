<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Teacher Class Tracker & Logs';
$breadcrumbs = ['Academic' => 'classes.php', 'Class Tracker' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['routine.view']);

$pdo = db();
$session_id = (int)setting('current_session_id', 0);
$selected_date = $_GET['date'] ?? date('Y-m-d');
$weekday = date('l', strtotime($selected_date));

// Handle POST actions for saving class logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('routine.manage')) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_log') {
        $slot_id = int_param('slot_id', 0, $_POST);
        $date = $_POST['date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'conducted';
        $notes = trim($_POST['notes'] ?? '');
        
        // Fetch slot details to populate logs
        $slot_stmt = $pdo->prepare("SELECT * FROM routine_slots WHERE id = ?");
        $slot_stmt->execute([$slot_id]);
        $slot = $slot_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($slot && $date) {
            $stmt = $pdo->prepare("
                INSERT INTO teacher_class_logs 
                (session_id, class_id, section_id, subject_id, teacher_id, slot_id, log_date, status, notes, marked_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes), marked_by = VALUES(marked_by)
            ");
            $stmt->execute([
                $slot['session_id'],
                $slot['class_id'],
                $slot['section_id'],
                $slot['subject_id'],
                $slot['teacher_id'] ?: 0,
                $slot_id,
                $date,
                $status,
                $notes,
                $_SESSION['user_id']
            ]);
            flash('success', 'Class execution status saved.');
        }
    }
    header("Location: class_tracker.php?date=" . $date);
    exit;
}

// Fetch all active slots for the selected date, incorporating substitutions
$stmt = $pdo->prepare("
    SELECT rs.*, c.class_name, sec.section_name, s.subject_name, 
           CONCAT(sp.first_name, ' ', sp.last_name) as teacher_name,
           cl.status as log_status, cl.notes as log_notes, cl.id as log_id
    FROM routine_slots rs
    JOIN classes c ON c.id = rs.class_id
    JOIN sections sec ON sec.id = rs.section_id
    JOIN subjects s ON s.id = rs.subject_id
    LEFT JOIN staff_profiles sp ON sp.user_id = rs.teacher_id
    LEFT JOIN teacher_class_logs cl ON cl.slot_id = rs.id AND cl.log_date = ?
    WHERE rs.session_id = ? AND rs.status = 1 AND rs.day_of_week = ?
      AND (
          (rs.is_substitute = 1 AND rs.substitute_date = ?)
          OR
          (rs.is_substitute = 0 AND rs.substitute_date IS NULL AND NOT EXISTS (
              SELECT 1 FROM routine_slots sub 
              WHERE sub.class_id = rs.class_id AND sub.section_id = rs.section_id 
                AND sub.day_of_week = rs.day_of_week AND sub.start_time = rs.start_time 
                AND sub.is_substitute = 1 AND sub.substitute_date = ? AND sub.status = 1
          ))
      )
    ORDER BY c.display_order, sec.section_name, rs.start_time
");
$stmt->execute([$selected_date, $session_id, $weekday, $selected_date, $selected_date]);
$classes_today = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load report variables if filter is active
$report_teacher_id = int_param('report_teacher_id', 0, $_GET);
$report_class_id   = int_param('report_class_id', 0, $_GET);
$start_date        = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date          = $_GET['end_date'] ?? date('Y-m-d');

// Build query for reports
$report_logs = [];
$stats = ['conducted' => 0, 'missed' => 0, 'total' => 0];

if (isset($_GET['filter_report'])) {
    $where = ["cl.log_date BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];
    
    if ($report_teacher_id) {
        $where[] = "cl.teacher_id = ?";
        $params[] = $report_teacher_id;
    }
    if ($report_class_id) {
        $where[] = "cl.class_id = ?";
        $params[] = $report_class_id;
    }
    
    $where_sql = implode(' AND ', $where);
    $rep_stmt = $pdo->prepare("
        SELECT cl.*, c.class_name, sec.section_name, s.subject_name,
               CONCAT(sp.first_name, ' ', sp.last_name) as teacher_name,
               rs.start_time, rs.end_time, rs.is_substitute
        FROM teacher_class_logs cl
        JOIN classes c ON c.id = cl.class_id
        JOIN sections sec ON sec.id = cl.section_id
        JOIN subjects s ON s.id = cl.subject_id
        LEFT JOIN staff_profiles sp ON sp.user_id = cl.teacher_id
        LEFT JOIN routine_slots rs ON rs.id = cl.slot_id
        WHERE $where_sql
        ORDER BY cl.log_date DESC, rs.start_time ASC
    ");
    $rep_stmt->execute($params);
    $report_logs = $rep_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($report_logs as $log) {
        $stats['total']++;
        if ($log['status'] === 'conducted') $stats['conducted']++;
        else $stats['missed']++;
    }
}

// Load filters data
$teachers = $pdo->query("
    SELECT u.id, CONCAT(sp.first_name, ' ', sp.last_name) as name 
    FROM staff_profiles sp
    JOIN users u ON u.id = sp.user_id
    WHERE sp.status = 'active'
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$classes = $pdo->query("SELECT id, class_name FROM classes WHERE status=1 ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-journal-check me-2 text-primary"></i>Teacher Class Tracker</h1>
  <div class="d-flex gap-2">
    <a href="substitution.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-shuffle me-1"></i>Substitution Planner</a>
    <a href="routine.php" class="btn btn-primary btn-sm"><i class="bi bi-calendar3 me-1"></i>Class Routines</a>
  </div>
</div>

<ul class="nav nav-tabs mb-4" id="trackerTabs">
  <li class="nav-item"><a class="nav-link <?= !isset($_GET['filter_report']) ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab-daily"><i class="bi bi-calendar-day me-2 text-primary"></i>Daily Execution Tracker</a></li>
  <li class="nav-item"><a class="nav-link <?= isset($_GET['filter_report']) ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab-reports"><i class="bi bi-file-earmark-text me-2 text-success"></i>Missing Classes Report</a></li>
</ul>

<div class="tab-content">
  <!-- Daily Tracker Tab -->
  <div class="tab-pane fade <?= !isset($_GET['filter_report']) ? 'show active' : '' ?>" id="tab-daily">
    <div class="row g-3">
      <!-- Date select -->
      <div class="col-md-3">
        <div class="card">
          <div class="card-header bg-light py-3">
            <span class="card-title mb-0 fw-bold"><i class="bi bi-calendar3 me-2 text-primary"></i>Tracker Date</span>
          </div>
          <div class="card-body">
            <form method="GET">
              <div class="mb-2">
                <input type="date" name="date" class="form-control" value="<?= e($selected_date) ?>" onchange="this.form.submit()">
              </div>
            </form>
            <div class="alert alert-info py-2 small mb-0">
              <strong>Weekday:</strong> <?= $weekday ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Class list grid -->
      <div class="col-md-9">
        <div class="card">
          <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
            <span class="card-title mb-0 fw-bold"><i class="bi bi-card-checklist me-2 text-primary"></i>Scheduled Classes (<?= count($classes_today) ?>)</span>
            <span class="text-muted small">Update whether the class was conducted or missed.</span>
          </div>
          <div class="card-body p-0">
            <?php if (empty($classes_today)): ?>
              <div class="p-4 text-center text-muted">
                <i class="bi bi-calendar-x fs-1 mb-2 d-block text-secondary"></i>
                No classes scheduled for <?= $weekday ?> (<?= e($selected_date) ?>).
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Class & Section</th>
                      <th>Period & Time</th>
                      <th>Subject</th>
                      <th>Teacher</th>
                      <th>Status / Log Details</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($classes_today as $cls): 
                      $status_bg = 'bg-secondary';
                      if ($cls['log_status'] === 'conducted') $status_bg = 'bg-success';
                      elseif ($cls['log_status'] === 'missed') $status_bg = 'bg-danger';
                    ?>
                      <tr>
                        <td>
                          <div class="fw-bold"><?= e($cls['class_name']) ?></div>
                          <span class="badge bg-secondary"><?= e($cls['section_name']) ?></span>
                        </td>
                        <td>
                          <div class="fw-bold text-primary"><?= substr($cls['start_time'], 0, 5) ?> - <?= substr($cls['end_time'], 0, 5) ?></div>
                          <?php if ($cls['is_substitute']): ?>
                            <span class="badge bg-warning text-dark" style="font-size:0.6rem;">SUBSTITUTE CLASS</span>
                          <?php endif; ?>
                        </td>
                        <td class="fw-bold"><?= e($cls['subject_name']) ?></td>
                        <td>
                          <div class="small fw-600"><?= e($cls['teacher_name'] ?? '— Unassigned —') ?></div>
                        </td>
                        <td>
                          <?php if (has_permission('routine.manage')): ?>
                            <form method="POST" class="d-flex align-items-center gap-2">
                              <?= csrf_field() ?>
                              <input type="hidden" name="action" value="save_log">
                              <input type="hidden" name="slot_id" value="<?= $cls['id'] ?>">
                              <input type="hidden" name="date" value="<?= e($selected_date) ?>">
                              
                              <select name="status" class="form-select form-select-sm" style="width: 130px;" onchange="this.form.submit()">
                                <option value="conducted" <?= $cls['log_status'] === 'conducted' ? 'selected' : '' ?>>Conducted</option>
                                <option value="missed" <?= $cls['log_status'] === 'missed' ? 'selected' : '' ?>>Missed</option>
                              </select>
                              <input type="text" name="notes" class="form-control form-control-sm" placeholder="Reason/notes..." value="<?= e($cls['log_notes']) ?>" style="min-width: 150px;">
                              <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-save"></i></button>
                            </form>
                          <?php else: ?>
                            <span class="badge <?= $status_bg ?> p-2 text-uppercase"><?= e($cls['log_status'] ?: 'Pending') ?></span>
                            <?php if ($cls['log_notes']): ?>
                              <div class="small text-muted mt-1 italic">Note: <?= e($cls['log_notes']) ?></div>
                            <?php endif; ?>
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

  <!-- Reports Tab -->
  <div class="tab-pane fade <?= isset($_GET['filter_report']) ? 'show active' : '' ?>" id="tab-reports">
    <div class="card mb-4">
      <div class="card-header bg-light py-3">
        <span class="card-title mb-0 fw-bold"><i class="bi bi-funnel me-2 text-primary"></i>Filter Logs & Missing Classes</span>
      </div>
      <div class="card-body">
        <form method="GET" class="row g-2">
          <input type="hidden" name="filter_report" value="1">
          <div class="col-md-3">
            <label class="form-label small fw-bold">Teacher</label>
            <select name="report_teacher_id" class="form-select form-select-sm">
              <option value="0">— All Teachers —</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $report_teacher_id == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-bold">Class</label>
            <select name="report_class_id" class="form-select form-select-sm">
              <option value="0">— All Classes —</option>
              <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $report_class_id == $c['id'] ? 'selected' : '' ?>><?= e($c['class_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-bold">Start Date</label>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($start_date) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-bold">End Date</label>
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($end_date) ?>">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-filter me-1"></i>Filter Report</button>
          </div>
        </form>
      </div>
    </div>

    <?php if (isset($_GET['filter_report'])): ?>
      <!-- Stats Overview -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="card bg-success-subtle text-success p-3 border-0">
            <div class="small fw-bold">Conducted Classes</div>
            <div class="fs-2 fw-bold"><?= $stats['conducted'] ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card bg-danger-subtle text-danger p-3 border-0">
            <div class="small fw-bold">Missed / Skipped Classes</div>
            <div class="fs-2 fw-bold"><?= $stats['missed'] ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card bg-info-subtle text-info p-3 border-0">
            <div class="small fw-bold">Total Class Logs</div>
            <div class="fs-2 fw-bold"><?= $stats['total'] ?></div>
          </div>
        </div>
      </div>

      <!-- Logs Grid -->
      <div class="card">
        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
          <span class="card-title mb-0 fw-bold"><i class="bi bi-table me-2 text-primary"></i>Execution Logs Grid</span>
          <button type="button" class="btn btn-xs btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
        </div>
        <div class="card-body p-0">
          <?php if (empty($report_logs)): ?>
            <div class="p-4 text-center text-muted">No logs found matching search criteria.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Date</th>
                    <th>Teacher</th>
                    <th>Class / Section</th>
                    <th>Subject</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th>Reason / Notes</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($report_logs as $log): 
                    $badge = $log['status'] === 'conducted' ? 'bg-success' : 'bg-danger';
                  ?>
                    <tr>
                      <td class="fw-bold"><?= e($log['log_date']) ?></td>
                      <td class="fw-bold"><?= e($log['teacher_name']) ?></td>
                      <td><?= e($log['class_name']) ?> (<?= e($log['section_name']) ?>)</td>
                      <td><?= e($log['subject_name']) ?></td>
                      <td>
                        <?php if ($log['start_time']): ?>
                          <?= substr($log['start_time'], 0, 5) ?> - <?= substr($log['end_time'], 0, 5) ?>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="badge <?= $badge ?> text-white text-uppercase"><?= e($log['status']) ?></span>
                      </td>
                      <td><?= e($log['notes'] ?: '—') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
