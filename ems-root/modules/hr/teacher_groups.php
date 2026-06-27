<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Teacher Level Groups';
$breadcrumbs = ['HR & Payroll' => 'staff.php', 'Teacher Level Groups' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['teacher.level']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_assignments') {
        $teacherId = int_param('teacher_id', 0, $_POST);
        $levelIds  = array_map('intval', (array)($_POST['level_ids'] ?? []));

        if ($teacherId) {
            // Remove existing assignments for this teacher
            $pdo->prepare('DELETE FROM teacher_level_assignments WHERE teacher_id=?')->execute([$teacherId]);
            // Insert new ones
            $ins = $pdo->prepare('INSERT INTO teacher_level_assignments (teacher_id, level_id, assigned_by) VALUES (?,?,?)');
            foreach ($levelIds as $lvId) {
                if ($lvId) $ins->execute([$teacherId, $lvId, $_SESSION['user_id']??null]);
            }
            log_activity('update_teacher_levels', 'hr', $teacherId);
            flash('success', 'Level assignments saved.');
        }
    } elseif ($action === 'assign_all') {
        // Assign ALL active levels to all teachers who have none
        $teachers = $pdo->query('SELECT sp.user_id FROM staff_profiles sp WHERE sp.status="active"')->fetchAll(PDO::FETCH_COLUMN);
        $levels   = $pdo->query('SELECT id FROM institute_levels WHERE status=1')->fetchAll(PDO::FETCH_COLUMN);
        $ins = $pdo->prepare('INSERT IGNORE INTO teacher_level_assignments (teacher_id, level_id, assigned_by) VALUES (?,?,?)');
        $count = 0;
        foreach ($teachers as $tid) {
            foreach ($levels as $lid) {
                $ins->execute([$tid, $lid, $_SESSION['user_id']??null]);
                $count++;
            }
        }
        flash('success', "Assigned all levels to all teachers ($count assignments).");
    }
    header('Location: teacher_groups.php');
    exit;
}

// ── Load data ─────────────────────────────────────────────────────────────────
$levels = $pdo->query('SELECT * FROM institute_levels WHERE status=1 ORDER BY display_order')->fetchAll();

$teachers = $pdo->query("
    SELECT sp.user_id AS id,
           CONCAT(sp.first_name,' ',sp.last_name) AS name,
           sp.designation, sp.designation_type_id,
           dt.designation_name AS desig_type_name
    FROM staff_profiles sp
    LEFT JOIN designation_types dt ON dt.id=sp.designation_type_id
    WHERE sp.status='active' AND sp.deleted_at IS NULL
    ORDER BY sp.first_name, sp.last_name
")->fetchAll();

// Load all assignments into a map: teacher_id => [level_id, ...]
$assignments = [];
$rows = $pdo->query('SELECT teacher_id, level_id FROM teacher_level_assignments')->fetchAll();
foreach ($rows as $r) {
    $assignments[$r['teacher_id']][] = $r['level_id'];
}

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h1 class="page-title mb-0"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Teacher Level Groups</h1>
  <div class="d-flex gap-2">
    <form method="POST" class="d-inline">
      <?= csrf_field() ?>
      <button type="submit" name="action" value="assign_all" class="btn btn-outline-secondary btn-sm"
              data-confirm="Assign ALL institute levels to all active teachers?">
        <i class="bi bi-people-fill me-1"></i>Assign All Levels to All
      </button>
    </form>
  </div>
</div>

<div class="alert alert-info d-flex gap-2 mb-3">
  <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
  <div>
    Assign which <strong>institute levels</strong> each teacher can teach.
    This controls which classes appear in the <strong>Class Routine</strong> when assigning a teacher to a slot.
    A teacher with no level assigned can be placed in any class.
  </div>
</div>

<?php if (empty($levels)): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle me-2"></i>
  No institute levels defined yet. Please add levels in
  <a href="../setup/categories.php">Setup → Dropdown Options → Institute Levels</a>.
</div>
<?php elseif (empty($teachers)): ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><i class="bi bi-people"></i><p>No active staff found. Add staff first.</p></div>
</div></div>
<?php else: ?>

<!-- Level headers legend -->
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="text-muted small fw-600 me-2">Institute Levels:</span>
      <?php foreach ($levels as $lv): ?>
        <span class="badge bg-primary"><?= e($lv['level_name']) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card table-card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h6 class="card-title mb-0">Staff Level Assignments <span class="badge bg-secondary ms-2"><?= count($teachers) ?></span></h6>
    <input type="text" id="table-search" class="form-control form-control-sm" style="width:200px;" placeholder="Search staff…" data-target="teacher-table">
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="teacher-table">
      <thead>
        <tr>
          <th class="ps-3">Staff Member</th>
          <th>Designation</th>
          <?php foreach ($levels as $lv): ?>
            <th class="text-center"><?= e($lv['level_name']) ?></th>
          <?php endforeach; ?>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teachers as $t):
          $teacherLevels = $assignments[$t['id']] ?? [];
          $allAssigned   = count($teacherLevels) === count($levels);
        ?>
        <tr>
          <td class="fw-600 ps-3"><?= e($t['name']) ?></td>
          <td>
            <span class="text-muted small"><?= e($t['desig_type_name'] ?? $t['designation'] ?? '—') ?></span>
          </td>
          <?php foreach ($levels as $lv): ?>
          <td class="text-center">
            <?php if (in_array($lv['id'], $teacherLevels)): ?>
              <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <?php else: ?>
              <i class="bi bi-dash-circle text-muted"></i>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignModal"
                    onclick="openAssign(<?= $t['id'] ?>, '<?= e(addslashes($t['name'])) ?>', <?= json_encode($teacherLevels) ?>)">
              <i class="bi bi-pencil-square me-1"></i>Edit
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Assignment Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action"     value="save_assignments">
        <input type="hidden" name="teacher_id" id="am_teacher_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-diagram-3 me-2"></i>Assign Levels: <span id="am_teacher_name"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">
            Select which institute levels this teacher can take class in.
            Leave all unchecked to allow the teacher in all classes.
          </p>
          <div class="row g-2" id="am_levels">
            <?php foreach ($levels as $lv): ?>
            <div class="col-12">
              <div class="form-check border rounded p-3">
                <input type="checkbox" class="form-check-input am-lvl-check"
                       name="level_ids[]" value="<?= $lv['id'] ?>"
                       id="amlv_<?= $lv['id'] ?>">
                <label class="form-check-label" for="amlv_<?= $lv['id'] ?>">
                  <span class="fw-600"><?= e($lv['level_name']) ?></span>
                  <span class="badge bg-secondary ms-2"><?= e($lv['level_code']) ?></span>
                </label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.am-lvl-check').forEach(c=>c.checked=true)">
              Select All
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="document.querySelectorAll('.am-lvl-check').forEach(c=>c.checked=false)">
              Clear All
            </button>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Save Assignments
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAssign(teacherId, teacherName, assignedLevels) {
  document.getElementById('am_teacher_id').value   = teacherId;
  document.getElementById('am_teacher_name').textContent = teacherName;
  document.querySelectorAll('.am-lvl-check').forEach(cb => {
    cb.checked = assignedLevels.includes(parseInt(cb.value));
  });
  new bootstrap.Modal(document.getElementById('assignModal')).show();
}
</script>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
