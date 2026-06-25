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
        $id    = int_param('id', 0, $_POST);
        $name  = trim($_POST['class_name'] ?? '');
        $num   = int_param('class_numeric', 0, $_POST);
        $level = $_POST['class_level'] ?? 'primary';
        $order = int_param('display_order', 0, $_POST);
        if ($name) {
            if ($id) {
                $pdo->prepare('UPDATE classes SET class_name=?,class_numeric=?,class_level=?,display_order=? WHERE id=?')
                    ->execute([$name,$num,$level,$order,$id]);
                flash('success', 'Class updated.');
            } else {
                $pdo->prepare('INSERT INTO classes (class_name,class_numeric,class_level,display_order) VALUES (?,?,?,?)')
                    ->execute([$name,$num,$level,$order]);
                flash('success', "Class '$name' added.");
            }
        }
    } elseif ($action === 'delete_class') {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('DELETE FROM classes WHERE id=:id')->execute([':id' => $id]);
        flash('success', 'Class removed.');
    } elseif ($action === 'save_section') {
        $id       = int_param('id', 0, $_POST);
        $classId  = int_param('class_id', 0, $_POST);
        $name     = trim($_POST['section_name'] ?? '');
        $shift    = $_POST['shift'] ?? 'day';
        $capacity = int_param('capacity', 40, $_POST);
        $classTeacherId = int_param('class_teacher_id', 0, $_POST) ?: null;
        $firstPeriodDays = isset($_POST['class_teacher_first_period_days']) && is_array($_POST['class_teacher_first_period_days']) 
            ? implode(',', $_POST['class_teacher_first_period_days']) 
            : null;
        if ($classId && $name) {
            if ($id) {
                $pdo->prepare('UPDATE sections SET class_id=?,section_name=?,shift=?,capacity=?,class_teacher_id=?,class_teacher_first_period_days=? WHERE id=?')
                    ->execute([$classId,$name,$shift,$capacity,$classTeacherId,$firstPeriodDays,$id]);
                flash('success', 'Section updated.');
            } else {
                $pdo->prepare('INSERT INTO sections (class_id,section_name,shift,capacity,class_teacher_id,class_teacher_first_period_days) VALUES (?,?,?,?,?,?)')
                    ->execute([$classId,$name,$shift,$capacity,$classTeacherId,$firstPeriodDays]);
                flash('success', "Section '$name' added.");
            }
        }
    } elseif ($action === 'delete_section') {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('DELETE FROM sections WHERE id=:id')->execute([':id' => $id]);
        flash('success', 'Section removed.');
    }
    header('Location: classes.php');
    exit;
}

$classes  = $pdo->query('SELECT * FROM classes WHERE status=1 ORDER BY display_order, class_numeric, class_name')->fetchAll();
$sections = $pdo->query('
    SELECT s.*, c.class_name, CONCAT(sp.first_name, " ", sp.last_name) as teacher_name 
    FROM sections s 
    JOIN classes c ON c.id=s.class_id 
    LEFT JOIN staff_profiles sp ON sp.user_id = s.class_teacher_id
    ORDER BY c.display_order, s.section_name
')->fetchAll();

$teachers = $pdo->query("
    SELECT sp.user_id as id, CONCAT(sp.first_name, ' ', sp.last_name) as name 
    FROM staff_profiles sp 
    WHERE sp.status='active' 
    ORDER BY name
")->fetchAll();

// Group sections by class
$sectionsByClass = [];
foreach ($sections as $sec) $sectionsByClass[$sec['class_id']][] = $sec;

require_once EMS_ROOT . '/includes/header.php';

?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-building me-2 text-primary"></i>Classes & Sections</h1>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#classSectionModal" onclick="setSectionForm(null)">
      <i class="bi bi-plus-lg me-1"></i>Add Section
    </button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#classModal" onclick="setClassForm(null)">
      <i class="bi bi-plus-lg me-1"></i>Add Class
    </button>
  </div>
</div>

<!-- Class list with sections -->
<div class="row g-3">
  <?php if (empty($classes)): ?>
  <div class="col-12">
    <div class="card"><div class="card-body">
      <div class="empty-state"><i class="bi bi-building-slash"></i><p>No classes added yet. Start by adding a class.</p></div>
    </div></div>
  </div>
  <?php else: foreach ($classes as $cls): ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between py-3 px-3">
        <div>
          <span class="fw-700"><?= e($cls['class_name']) ?></span>
          <span class="badge bg-light text-secondary ms-1 small"><?= ucfirst(str_replace('_',' ',e($cls['class_level']))) ?></span>
        </div>
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#classModal"
                  onclick="setClassForm(<?= htmlspecialchars(json_encode($cls), ENT_QUOTES) ?>)">
            <i class="bi bi-pencil"></i>
          </button>
          <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_class">
            <input type="hidden" name="id" value="<?= $cls['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"
                    data-confirm="Delete class '<?= e($cls['class_name']) ?>' and all its sections?">
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
        <table class="table table-sm mb-0">
          <thead><tr><th>Section</th><th>Shift</th><th>Cap</th><th>Class Teacher</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($clsSections as $sec): ?>
          <tr>
            <td class="fw-600"><?= e($sec['section_name']) ?></td>
            <td class="text-capitalize"><?= e($sec['shift']) ?></td>
            <td><?= $sec['capacity'] ?></td>
            <td>
              <div class="fw-600 text-dark" style="font-size:0.8rem;"><?= e($sec['teacher_name'] ?? '— Unassigned —') ?></div>
              <?php if ($sec['class_teacher_first_period_days']): ?>
                <div class="text-muted" style="font-size: 0.65rem;" title="Days class teacher takes 1st period"><i class="bi bi-clock me-1"></i>1st Period: <?= e($sec['class_teacher_first_period_days']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-1">
                <button class="btn btn-xs btn-outline-primary" style="padding:.1rem .4rem;font-size:.7rem;"
                        data-bs-toggle="modal" data-bs-target="#classSectionModal"
                        onclick="setSectionForm(<?= htmlspecialchars(json_encode($sec), ENT_QUOTES) ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_section">
                  <input type="hidden" name="id" value="<?= $sec['id'] ?>">
                  <button type="submit" class="btn btn-xs btn-outline-danger" style="padding:.1rem .4rem;font-size:.7rem;"
                          data-confirm="Delete section '<?= e($sec['section_name']) ?>'?">
                    <i class="bi bi-x"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
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
  <?php endforeach; endif; ?>
</div>

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
          <div class="mt-3">
            <label class="form-label">Level</label>
            <select name="class_level" id="cls_level" class="form-select">
              <option value="playgroup">Playgroup / KG</option>
              <option value="primary">Primary (1–5)</option>
              <option value="secondary">Secondary (6–10 / SSC)</option>
              <option value="higher_secondary">Higher Secondary (HSC)</option>
              <option value="other">Other</option>
            </select>
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
            <label class="form-label fw-600">Class Teacher</label>
            <select name="class_teacher_id" id="sec_teacher" class="form-select">
              <option value="0">— Select Teacher —</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
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
  document.getElementById('cls_id').value    = cls ? cls.id : 0;
  document.getElementById('cls_name').value  = cls ? cls.class_name : '';
  document.getElementById('cls_num').value   = cls ? cls.class_numeric : 0;
  document.getElementById('cls_order').value = cls ? cls.display_order : 0;
  document.getElementById('cls_level').value = cls ? cls.class_level : 'primary';
}
function setSectionForm(sec, preClassId, preClassName) {
  document.getElementById('sectionModalTitle').textContent = sec ? 'Edit Section' : 'Add Section';
  document.getElementById('sec_id').value    = sec ? sec.id : 0;
  document.getElementById('sec_class').value = sec ? sec.class_id : (preClassId || '');
  document.getElementById('sec_name').value  = sec ? sec.section_name : '';
  document.getElementById('sec_shift').value = sec ? sec.shift : 'day';
  document.getElementById('sec_cap').value   = sec ? sec.capacity : 40;
  document.getElementById('sec_teacher').value = sec ? (sec.class_teacher_id || 0) : 0;
  
  // Uncheck all days
  document.querySelectorAll('.day-chk').forEach(cb => cb.checked = false);
  
  // Check the days from data
  if (sec && sec.class_teacher_first_period_days) {
    const days = sec.class_teacher_first_period_days.split(',');
    days.forEach(d => {
      const cb = document.getElementById('day_' + d.trim());
      if (cb) cb.checked = true;
    });
  }
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
