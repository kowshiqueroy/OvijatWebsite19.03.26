<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Classes & Sections';
$breadcrumbs = ['Academic' => null, 'Classes & Sections' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['academic.manage']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_class') {
        $id      = int_param('id', 0, $_POST);
        $name    = trim($_POST['class_name'] ?? '');
        $num     = int_param('class_numeric', 0, $_POST);
        $level   = $_POST['class_level'] ?? 'primary';
        $levelId = int_param('level_id', 0, $_POST) ?: null;
        $order   = int_param('display_order', 0, $_POST);
        if ($name) {
            if ($id) {
                $pdo->prepare('UPDATE classes SET class_name=?,class_numeric=?,class_level=?,level_id=?,display_order=? WHERE id=?')
                    ->execute([$name,$num,$level,$levelId,$order,$id]);
                flash('success', 'Class updated.');
            } else {
                $pdo->prepare('INSERT INTO classes (class_name,class_numeric,class_level,level_id,display_order) VALUES (?,?,?,?,?)')
                    ->execute([$name,$num,$level,$levelId,$order]);
                flash('success', "Class '$name' added.");
            }
        }
    } elseif ($action === 'delete_class') {
        $id = int_param('id', 0, $_POST);
        // Count related items before soft-delete
        $secCount = (int)$pdo->prepare('SELECT COUNT(*) FROM sections WHERE class_id=?')->execute([$id]) ? 0 : 0;
        $sc = $pdo->prepare('SELECT COUNT(*) FROM sections WHERE class_id=? AND deleted_at IS NULL');
        $sc->execute([$id]);
        $secCount = (int)$sc->fetchColumn();
        $pdo->prepare('UPDATE classes SET deleted_at=NOW(), deleted_by=? WHERE id=?')
            ->execute([$_SESSION['user_id']??null, $id]);
        flash('success', "Class moved to deleted items." . ($secCount ? " $secCount sections hidden too." : ''));
    } elseif ($action === 'save_section') {
        $id       = int_param('id', 0, $_POST);
        $classId  = int_param('class_id', 0, $_POST);
        $name     = trim($_POST['section_name'] ?? '');
        $shift    = $_POST['shift'] ?? 'day';
        $capacity = int_param('capacity', 40, $_POST);
        $roomId   = int_param('room_id', 0, $_POST) ?: null;
        $classTeacherId  = int_param('class_teacher_id', 0, $_POST) ?: null;
        $firstPeriodDays = isset($_POST['class_teacher_first_period_days']) && is_array($_POST['class_teacher_first_period_days'])
            ? implode(',', $_POST['class_teacher_first_period_days']) : null;
        if ($classId && $name) {
            if ($id) {
                $pdo->prepare('UPDATE sections SET class_id=?,section_name=?,shift=?,capacity=?,room_id=?,class_teacher_id=?,class_teacher_first_period_days=? WHERE id=?')
                    ->execute([$classId,$name,$shift,$capacity,$roomId,$classTeacherId,$firstPeriodDays,$id]);
                flash('success', 'Section updated.');
            } else {
                $pdo->prepare('INSERT INTO sections (class_id,section_name,shift,capacity,room_id,class_teacher_id,class_teacher_first_period_days) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$classId,$name,$shift,$capacity,$roomId,$classTeacherId,$firstPeriodDays]);
                flash('success', "Section '$name' added.");
            }
        }

    } elseif ($action === 'auto_assign_rooms') {
        // Auto-assign available classrooms to sections that have no room, by capacity match
        $unassigned = $pdo->query('SELECT id, capacity FROM sections WHERE room_id IS NULL AND deleted_at IS NULL ORDER BY capacity DESC')->fetchAll();
        $rooms = $pdo->query("SELECT id, capacity FROM rooms WHERE room_type='classroom' AND status=1 AND id NOT IN (SELECT room_id FROM sections WHERE room_id IS NOT NULL) ORDER BY capacity DESC")->fetchAll();
        $assigned = 0;
        foreach ($unassigned as $sec) {
            foreach ($rooms as $i => $room) {
                if ($room['capacity'] >= $sec['capacity']) {
                    $pdo->prepare('UPDATE sections SET room_id=? WHERE id=?')->execute([$room['id'], $sec['id']]);
                    unset($rooms[$i]);
                    $assigned++;
                    break;
                }
            }
        }
        flash('success', "Auto-assigned rooms to $assigned sections.");

    } elseif ($action === 'auto_assign_class_teachers') {
        // Assign staff to sections that have no class teacher (first available active staff)
        $unassigned = $pdo->query('SELECT id FROM sections WHERE class_teacher_id IS NULL AND deleted_at IS NULL ORDER BY id')->fetchAll();
        $teachers   = $pdo->query("SELECT sp.user_id FROM staff_profiles sp WHERE sp.status='active' ORDER BY sp.first_name")->fetchAll(PDO::FETCH_COLUMN);
        $assigned   = 0;
        $tIdx       = 0;
        foreach ($unassigned as $sec) {
            if (!isset($teachers[$tIdx])) break;
            $pdo->prepare('UPDATE sections SET class_teacher_id=? WHERE id=?')->execute([$teachers[$tIdx], $sec['id']]);
            $tIdx++;
            $assigned++;
        }
        flash('success', "Auto-assigned class teachers to $assigned sections.");
    } elseif ($action === 'delete_section') {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('UPDATE sections SET deleted_at=NOW(), deleted_by=? WHERE id=?')
            ->execute([$_SESSION['user_id']??null, $id]);
        flash('success', 'Section moved to deleted items.');
    }
    header('Location: classes.php');
    exit;
}

$classes  = $pdo->query('SELECT c.*, il.level_name FROM classes c LEFT JOIN institute_levels il ON il.id=c.level_id WHERE c.status=1 AND c.deleted_at IS NULL ORDER BY c.display_order, c.class_numeric, c.class_name')->fetchAll();
$sections = $pdo->query('
    SELECT s.*, c.class_name,
           CONCAT(sp.first_name," ",sp.last_name) AS teacher_name,
           r.room_name, r.room_number, r.capacity AS room_capacity
    FROM sections s
    JOIN classes c ON c.id=s.class_id
    LEFT JOIN staff_profiles sp ON sp.user_id = s.class_teacher_id
    LEFT JOIN rooms r ON r.id = s.room_id
    WHERE s.deleted_at IS NULL AND c.deleted_at IS NULL
    ORDER BY c.display_order, c.class_numeric, s.section_name
')->fetchAll();

$teachers = $pdo->query("
    SELECT sp.user_id AS id, CONCAT(sp.first_name,' ',sp.last_name) AS name, sp.designation
    FROM staff_profiles sp
    WHERE sp.status='active'
    ORDER BY name
")->fetchAll();

$rooms = $pdo->query("
    SELECT id, room_name, room_number, capacity, room_type
    FROM rooms WHERE status=1 AND deleted_at IS NULL ORDER BY room_name
")->fetchAll();

$levels = $pdo->query('SELECT * FROM institute_levels WHERE status=1 ORDER BY display_order')->fetchAll();

// Group sections by class
$sectionsByClass = [];
foreach ($sections as $sec) $sectionsByClass[$sec['class_id']][] = $sec;

// Group classes by level
$classesByLevel = [];
foreach ($classes as $cls) {
    $lvl = $cls['level_name'] ?? $cls['class_level'] ?? 'other';
    $classesByLevel[$lvl][] = $cls;
}

require_once EMS_ROOT . '/includes/header.php';

?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h1 class="page-title mb-0"><i class="bi bi-building me-2 text-primary"></i>Classes & Sections</h1>
  <div class="d-flex flex-wrap gap-2">
    <!-- Auto-assignment quick actions -->
    <form method="POST" class="d-inline">
      <?= csrf_field() ?>
      <button type="submit" name="action" value="auto_assign_rooms" class="btn btn-outline-secondary btn-sm"
              data-confirm="Auto-assign available rooms to sections without a room?">
        <i class="bi bi-door-open me-1"></i>Auto-Assign Rooms
      </button>
    </form>
    <form method="POST" class="d-inline">
      <?= csrf_field() ?>
      <button type="submit" name="action" value="auto_assign_class_teachers" class="btn btn-outline-secondary btn-sm"
              data-confirm="Auto-assign active teachers as class teachers to sections without one?">
        <i class="bi bi-person-check me-1"></i>Auto-Assign Teachers
      </button>
    </form>
    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#classSectionModal" onclick="setSectionForm(null)">
      <i class="bi bi-plus-lg me-1"></i>Add Section
    </button>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#classModal" onclick="setClassForm(null)">
      <i class="bi bi-plus-lg me-1"></i>Add Class
    </button>
  </div>
</div>

<?php if (empty($classes)): ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><i class="bi bi-building-slash"></i><p>No classes added yet. Start by adding a class.</p></div>
</div></div>
<?php else: ?>

<?php
// Display by institute level grouping
$levelOrder = ['Pre-Primary','Primary','Secondary','Higher Secondary','Other'];
$displayedClasses = [];
$groupedByDisplayLevel = [];
foreach ($classes as $cls) {
    $lvl = $cls['level_name'] ?? ucfirst(str_replace('_',' ',$cls['class_level']));
    $groupedByDisplayLevel[$lvl][] = $cls;
    $displayedClasses[$cls['id']] = true;
}
// Sort by predefined order
uksort($groupedByDisplayLevel, fn($a,$b) => (array_search($a,$levelOrder)??99) - (array_search($b,$levelOrder)??99));
?>

<?php foreach ($groupedByDisplayLevel as $levelName => $levelClasses): ?>
<div class="mb-4">
  <div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge bg-primary fs-6 px-3 py-2"><?= e($levelName) ?></span>
    <small class="text-muted"><?= count($levelClasses) ?> class(es)</small>
  </div>

<div class="row g-3">
  <?php foreach ($levelClasses as $cls): ?>
  <div class="col-12">
    <div class="card mb-2">
      <div class="card-header d-flex align-items-center justify-content-between py-3 px-3 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
          <span class="fw-700 fs-6"><?= e($cls['class_name']) ?></span>
          <span class="badge bg-light text-secondary small"><?= e(ucwords(str_replace('_',' ',$cls['class_level']))) ?></span>
          <?php $secCnt = count($sectionsByClass[$cls['id']] ?? []); ?>
          <span class="badge bg-secondary small"><?= $secCnt ?> section<?= $secCnt != 1 ? 's' : '' ?></span>
        </div>
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#classSectionModal"
                  onclick="setSectionForm(null, <?= $cls['id'] ?>)" title="Add section to this class">
            <i class="bi bi-plus-lg"></i> Section
          </button>
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#classModal"
                  onclick="setClassForm(<?= htmlspecialchars(json_encode($cls), ENT_QUOTES) ?>)" title="Edit class">
            <i class="bi bi-pencil"></i>
          </button>
          <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_class">
            <input type="hidden" name="id" value="<?= $cls['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"
                    data-soft-delete="<?= e($cls['class_name']) ?>"
                    data-soft-delete-warn="<?= $secCnt ?> section(s) in this class will also be hidden."
                    data-form-id="delcls<?= $cls['id'] ?>"
                    id="delcls<?= $cls['id'] ?>" title="Soft delete class">
              <i class="bi bi-trash"></i>
            </button>
          </form>
        </div>
      </div>
      <div class="card-body p-0">
        <?php $clsSections = $sectionsByClass[$cls['id']] ?? []; ?>
        <?php if (empty($clsSections)): ?>
          <div class="text-center py-3 text-muted small">No sections yet</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm mb-0 align-middle">
            <thead>
              <tr>
                <th class="ps-3">Section</th>
                <th>Shift</th>
                <th>Capacity</th>
                <th>Room</th>
                <th>Class Teacher</th>
                <th class="pe-3"></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($clsSections as $sec): ?>
            <tr>
              <td class="fw-600 ps-3"><?= e($sec['section_name']) ?></td>
              <td class="text-capitalize"><span class="badge bg-light text-dark"><?= e($sec['shift']) ?></span></td>
              <td><?= $sec['capacity'] ?></td>
              <td>
                <?php if ($sec['room_name']): ?>
                  <span class="badge bg-info text-dark"><i class="bi bi-door-open me-1"></i><?= e($sec['room_name']) ?><?= $sec['room_number'] ? ' ('.$sec['room_number'].')' : '' ?></span>
                <?php else: ?>
                  <span class="text-muted small">No room</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($sec['teacher_name']): ?>
                  <div class="fw-600 small"><?= e($sec['teacher_name']) ?></div>
                  <?php if ($sec['class_teacher_first_period_days']): ?>
                    <div class="text-muted" style="font-size:.7rem;"><i class="bi bi-clock me-1"></i>1st period: <?= e($sec['class_teacher_first_period_days']) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted small">Unassigned</span>
                <?php endif; ?>
              </td>
              <td class="pe-3">
                <div class="d-flex gap-1 justify-content-end">
                  <button class="btn btn-xs btn-outline-primary" style="padding:.15rem .5rem;font-size:.75rem;"
                          data-bs-toggle="modal" data-bs-target="#classSectionModal"
                          onclick="setSectionForm(<?= htmlspecialchars(json_encode($sec), ENT_QUOTES) ?>)" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_section">
                    <input type="hidden" name="id" value="<?= $sec['id'] ?>">
                    <button type="submit" class="btn btn-xs btn-outline-danger" style="padding:.15rem .5rem;font-size:.75rem;"
                            data-soft-delete="Section <?= e($sec['section_name']) ?>"
                            data-soft-delete-warn="Any routine slots and attendance for this section will be hidden too."
                            data-form-id="delsec<?= $sec['id'] ?>"
                            id="delsec<?= $sec['id'] ?>" title="Soft delete">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
      <div class="card-footer p-2">
        <button class="btn btn-sm w-100 btn-outline-success" data-bs-toggle="modal" data-bs-target="#classSectionModal"
                onclick="setSectionForm(null, <?= $cls['id'] ?>, <?= htmlspecialchars(json_encode($cls['class_name']), ENT_QUOTES) ?>)">
          <i class="bi bi-plus-lg me-1"></i>Add Section
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
</div><!-- /level group -->
<?php endforeach; ?>
<?php endif; ?>

<!-- Class Modal -->
<div class="modal fade" id="classModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_class">
        <input type="hidden" name="id" id="cls_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="classModalTitle">Add Class</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Class Name <span class="text-danger">*</span></label>
            <input type="text" name="class_name" id="cls_name" class="form-control" placeholder="e.g. Class 1, Playgroup, HSC 1st Year" required>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Numeric Order <small class="text-muted">(for sorting)</small></label>
              <input type="number" name="class_numeric" id="cls_num" class="form-control" min="0" max="20" value="0">
            </div>
            <div class="col-6">
              <label class="form-label">Display Order</label>
              <input type="number" name="display_order" id="cls_order" class="form-control" min="0" value="0">
            </div>
          </div>
          <div class="row g-3 mt-2">
            <div class="col-md-6">
              <label class="form-label">Level Category</label>
              <select name="class_level" id="cls_level" class="form-select">
                <option value="playgroup">Playgroup / KG</option>
                <option value="pre_primary">Pre-Primary</option>
                <option value="primary">Primary (1–5)</option>
                <option value="secondary">Secondary (6–10 / SSC)</option>
                <option value="higher_secondary">Higher Secondary (HSC)</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Institute Level <small class="text-muted">(from settings)</small></label>
              <select name="level_id" id="cls_level_id" class="form-select">
                <option value="">— None —</option>
                <?php foreach ($levels as $lv): ?>
                  <option value="<?= $lv['id'] ?>"><?= e($lv['level_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Class</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Section Modal -->
<div class="modal fade" id="classSectionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_section">
        <input type="hidden" name="id" id="sec_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="sectionModalTitle">Add Section</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Class <span class="text-danger">*</span></label>
            <select name="class_id" id="sec_class" class="form-select" required>
              <option value="">— Select Class —</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?= $cls['id'] ?>"><?= e($cls['class_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Section Name <span class="text-danger">*</span></label>
            <input type="text" name="section_name" id="sec_name" class="form-control" placeholder="A, B, Science, Morning…" required>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Shift</label>
              <select name="shift" id="sec_shift" class="form-select">
                <option value="morning">Morning</option>
                <option value="day" selected>Day</option>
                <option value="evening">Evening</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Capacity</label>
              <input type="number" name="capacity" id="sec_cap" class="form-control" value="40" min="1">
            </div>
          </div>
          <div class="mb-3 mt-3">
            <label class="form-label fw-600">Assigned Room <small class="text-muted">(optional)</small></label>
            <select name="room_id" id="sec_room" class="form-select">
              <option value="0">— No Room Assigned —</option>
              <?php foreach ($rooms as $r): ?>
                <option value="<?= $r['id'] ?>"><?= e($r['room_name']) ?><?= $r['room_number'] ? ' ('.$r['room_number'].')' : '' ?> — Cap: <?= $r['capacity'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-600">Class Teacher</label>
            <select name="class_teacher_id" id="sec_teacher" class="form-select">
              <option value="0">— Select Teacher —</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>"><?= e($t['name']) ?><?= $t['designation'] ? ' ('.$t['designation'].')' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label d-block fw-600">First Period Class Teacher Days</label>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach (['Sat','Sun','Mon','Tue','Wed','Thu','Fri'] as $day): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input day-chk" type="checkbox" name="class_teacher_first_period_days[]" value="<?= $day ?>" id="day_<?= $day ?>">
                  <label class="form-check-label small" for="day_<?= $day ?>"><?= $day ?></label>
                </div>
              <?php endforeach; ?>
            </div>
            <small class="text-muted" style="font-size:0.75rem;">If selected, this teacher is auto-recommended for the first period of this section on these weekdays.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Section</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setClassForm(cls) {
  document.getElementById('classModalTitle').textContent = cls ? 'Edit Class' : 'Add Class';
  document.getElementById('cls_id').value       = cls ? cls.id : 0;
  document.getElementById('cls_name').value     = cls ? cls.class_name : '';
  document.getElementById('cls_num').value      = cls ? cls.class_numeric : 0;
  document.getElementById('cls_order').value    = cls ? cls.display_order : 0;
  document.getElementById('cls_level').value    = cls ? cls.class_level : 'primary';
  document.getElementById('cls_level_id').value = cls ? (cls.level_id || '') : '';
}
function setSectionForm(sec, preClassId) {
  document.getElementById('sectionModalTitle').textContent = sec ? 'Edit Section' : 'Add Section';
  document.getElementById('sec_id').value      = sec ? sec.id : 0;
  document.getElementById('sec_class').value   = sec ? sec.class_id : (preClassId || '');
  document.getElementById('sec_name').value    = sec ? sec.section_name : '';
  document.getElementById('sec_shift').value   = sec ? sec.shift : 'day';
  document.getElementById('sec_cap').value     = sec ? sec.capacity : 40;
  document.getElementById('sec_room').value    = sec ? (sec.room_id || 0) : 0;
  document.getElementById('sec_teacher').value = sec ? (sec.class_teacher_id || 0) : 0;
  document.querySelectorAll('.day-chk').forEach(cb => cb.checked = false);
  if (sec && sec.class_teacher_first_period_days) {
    sec.class_teacher_first_period_days.split(',').forEach(d => {
      const cb = document.getElementById('day_' + d.trim());
      if (cb) cb.checked = true;
    });
  }
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
