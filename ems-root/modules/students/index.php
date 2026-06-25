<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Students';
$breadcrumbs = ['Students' => null, 'Student List' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.view']);

$pdo = db();

$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);
$class_id   = int_param('class_id', 0, $_GET);
$section_id = int_param('section_id', 0, $_GET);
$search     = trim($_GET['q'] ?? '');
$page       = max(1, int_param('page', 1, $_GET));

// Build query
$where  = ['se.session_id = :sess'];
$params = [':sess' => $session_id];
if ($class_id)   { $where[] = 'se.class_id = :cls';  $params[':cls']  = $class_id; }
if ($section_id) { $where[] = 'se.section_id = :sec'; $params[':sec']  = $section_id; }
if ($search)     { $where[] = '(sp.first_name LIKE :q OR sp.last_name LIKE :q OR sp.student_id_no LIKE :q OR u.username LIKE :q)'; $params[':q'] = "%$search%"; }

$whereStr = implode(' AND ', $where);

$cntStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM student_enrollments se
     JOIN users u ON u.id = se.student_id
     LEFT JOIN student_profiles sp ON sp.user_id = se.student_id
     WHERE $whereStr AND se.status='active'"
);
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pg    = paginate($total, $page);

$students = $pdo->prepare(
    "SELECT se.id as enroll_id, se.roll_number, se.status,
            sp.first_name, sp.last_name, sp.student_id_no, sp.photo,
            sp.guardian_phone, sp.gender,
            u.id as user_id,
            c.class_name, s.section_name
     FROM student_enrollments se
     JOIN users u ON u.id = se.student_id
     LEFT JOIN student_profiles sp ON sp.user_id = se.student_id
     JOIN classes c ON c.id = se.class_id
     JOIN sections s ON s.id = se.section_id
     WHERE $whereStr AND se.status='active'
     ORDER BY c.display_order, se.roll_number
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$students->execute($params);
$students = $students->fetchAll();

$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id, class_name FROM classes WHERE status=1 ORDER BY display_order')->fetchAll();
$sections = $class_id
    ? $pdo->prepare('SELECT id, section_name FROM sections WHERE class_id=:c AND status=1 ORDER BY section_name')
    : null;
if ($sections) { $sections->execute([':c' => $class_id]); $sections = $sections->fetchAll(); }
else $sections = [];

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-person-badge-fill me-2 text-primary"></i>Students</h1>
  <?php if (has_permission('students.create')): ?>
    <a href="create.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>New Admission</a>
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
            <option value="<?= $sess['id'] ?>" <?= $session_id == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">All Classes</option>
          <?php foreach ($classes as $cls): ?>
            <option value="<?= $cls['id'] ?>" <?= $class_id == $cls['id'] ? 'selected' : '' ?>><?= e($cls['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($class_id && !empty($sections)): ?>
      <div class="col-md-2">
        <label class="form-label small">Section</label>
        <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">All Sections</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>" <?= $section_id == $sec['id'] ? 'selected' : '' ?>><?= e($sec['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-3">
        <label class="form-label small">Search</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Name, ID No…" value="<?= e($search) ?>">
      </div>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        <?php if (has_permission('students.create')): ?>
          <a href="enroll.php?session_id=<?= $session_id ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-person-check me-1"></i>Enroll
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card table-card">
  <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
    <span class="card-title">Students <span class="badge bg-secondary"><?= $total ?></span></span>
    <div class="d-flex gap-2">
      <a href="promote.php" class="btn btn-sm btn-outline-warning" <?= has_permission('students.promote') ? '' : 'style="display:none"' ?>>
        <i class="bi bi-arrow-up-circle me-1"></i>Promote
      </a>
      <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" id="data-table">
      <thead>
        <tr><th>Roll</th><th>Student</th><th>ID No</th><th>Class</th><th>Section</th><th>Gender</th><th>Guardian Phone</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
          <tr><td colspan="8"><div class="empty-state"><i class="bi bi-person-slash"></i><p>No students found</p></div></td></tr>
        <?php else: foreach ($students as $s): ?>
        <tr>
          <td class="fw-700 text-primary"><?= $s['roll_number'] ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="topbar-avatar" style="width:30px;height:30px;font-size:.75rem;">
                <?php if ($s['photo'] && file_exists(UPLOAD_PHOTOS . $s['photo'])): ?>
                  <img src="../../uploads/photos/<?= e($s['photo']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
                <?php else: ?>
                  <?= strtoupper(substr($s['first_name'],0,1)) ?>
                <?php endif; ?>
              </div>
              <div>
                <div class="fw-600"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></div>
              </div>
            </div>
          </td>
          <td><code><?= e($s['student_id_no'] ?? '—') ?></code></td>
          <td><?= e($s['class_name']) ?></td>
          <td><?= e($s['section_name']) ?></td>
          <td class="text-capitalize"><?= e($s['gender'] ?? '—') ?></td>
          <td><?= e($s['guardian_phone'] ?? '—') ?></td>
          <td>
            <div class="table-actions">
              <a href="view.php?id=<?= $s['user_id'] ?>" class="btn btn-sm btn-outline-info" title="View">
                <i class="bi bi-eye"></i>
              </a>
              <?php if (has_permission('students.edit')): ?>
              <a href="edit.php?id=<?= $s['user_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <?php endif; ?>
              <?php if (has_permission('fees.collect')): ?>
              <a href="../finance/collect.php?student_id=<?= $s['user_id'] ?>" class="btn btn-sm btn-outline-success" title="Collect Fee">
                <i class="bi bi-cash"></i>
              </a>
              <?php endif; ?>
              <?php if (has_permission('students.tc')): ?>
              <a href="tc.php?id=<?= $s['user_id'] ?>" class="btn btn-sm btn-outline-warning" title="TC">
                <i class="bi bi-file-earmark-arrow-up"></i>
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pg['total_pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center justify-content-between py-2 px-4">
    <small class="text-muted">Showing <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'],$total) ?> of <?= $total ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($p = max(1,$pg['page']-2); $p <= min($pg['total_pages'],$pg['page']+2); $p++): ?>
        <li class="page-item <?= $p === $pg['page'] ? 'active' : '' ?>">
          <a class="page-link" href="?session_id=<?= $session_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>&q=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
