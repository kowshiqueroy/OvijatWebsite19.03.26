<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Academic Sessions';
$breadcrumbs = ['Academic' => null, 'Sessions' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['academic.manage']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id         = int_param('id', 0, $_POST);
        $name       = trim($_POST['session_name'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date   = $_POST['end_date'] ?? '';
        $is_current = isset($_POST['is_current']) ? 1 : 0;
        $status     = $_POST['status'] ?? 'upcoming';

        if ($name && $start_date && $end_date) {
            if ($is_current) {
                $pdo->exec("UPDATE academic_sessions SET is_current=0");
            }
            if ($id) {
                $pdo->prepare('UPDATE academic_sessions SET session_name=?,start_date=?,end_date=?,is_current=?,status=? WHERE id=?')
                    ->execute([$name,$start_date,$end_date,$is_current,$status,$id]);
                flash('success', 'Session updated.');
            } else {
                $pdo->prepare('INSERT INTO academic_sessions (session_name,start_date,end_date,is_current,status) VALUES (?,?,?,?,?)')
                    ->execute([$name,$start_date,$end_date,$is_current,$status]);
                flash('success', "Session '$name' created.");
            }
            if ($is_current) {
                $newId = $id ?: (int)$pdo->lastInsertId();
                $pdo->prepare('UPDATE system_settings SET meta_value=? WHERE meta_key="current_session_id"')->execute([$newId]);
            }
        }
    } elseif ($action === 'delete') {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('DELETE FROM academic_sessions WHERE id=:id')->execute([':id' => $id]);
        flash('success', 'Session deleted.');
    }
    header('Location: sessions.php');
    exit;
}

$sessions  = $pdo->query('SELECT * FROM academic_sessions ORDER BY start_date DESC')->fetchAll();
$editId    = int_param('edit', 0, $_GET);
$editRow   = null;
if ($editId) {
    $s = $pdo->prepare('SELECT * FROM academic_sessions WHERE id=:id');
    $s->execute([':id' => $editId]);
    $editRow = $s->fetch();
}

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-calendar3 me-2 text-primary"></i>Academic Sessions</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sessionModal"
          onclick="setSessionForm(null)"><i class="bi bi-plus-lg me-1"></i>New Session</button>
</div>

<div class="row g-3">
  <div class="col-md-8">
    <div class="card table-card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>#</th><th>Session Name</th><th>Start</th><th>End</th><th>Status</th><th>Current?</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php if (empty($sessions)): ?>
              <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>No sessions yet</p></div></td></tr>
            <?php else: foreach ($sessions as $i => $sess): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td class="fw-600"><?= e($sess['session_name']) ?></td>
              <td><?= fmt_date($sess['start_date']) ?></td>
              <td><?= fmt_date($sess['end_date']) ?></td>
              <td><span class="badge-status badge-<?= $sess['status'] === 'active' ? 'active' : ($sess['status'] === 'completed' ? 'approved' : 'draft') ?>">
                <?= ucfirst(e($sess['status'])) ?></span></td>
              <td><?= $sess['is_current'] ? '<span class="badge bg-success">Yes</span>' : '—' ?></td>
              <td>
                <div class="table-actions">
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sessionModal"
                          onclick="setSessionForm(<?= htmlspecialchars(json_encode($sess), ENT_QUOTES) ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $sess['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            data-confirm="Delete session '<?= e($sess['session_name']) ?>'?">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Quick Tips -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Guide</span></div>
      <div class="card-body">
        <ul class="list-unstyled mb-0" style="font-size:.85rem;">
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Create a new session for each academic year (e.g., <strong>2026</strong>).</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Mark one session as <strong>Current</strong> — it drives fee generation and enrollments.</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>You can also set the current session from <a href="../setup/index.php">System Settings</a>.</li>
          <li><i class="bi bi-info-circle text-primary me-2"></i>Sessions cannot be deleted if they have enrolled students.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Session Modal -->
<div class="modal fade" id="sessionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="sess_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="sessionModalTitle">New Academic Session</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Session Name <span class="text-danger">*</span></label>
            <input type="text" name="session_name" id="sess_name" class="form-control" placeholder="e.g. 2026" required>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Start Date <span class="text-danger">*</span></label>
              <input type="date" name="start_date" id="sess_start" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label">End Date <span class="text-danger">*</span></label>
              <input type="date" name="end_date" id="sess_end" class="form-control" required>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Status</label>
            <select name="status" id="sess_status" class="form-select">
              <option value="upcoming">Upcoming</option>
              <option value="active">Active</option>
              <option value="completed">Completed</option>
            </select>
          </div>
          <div class="form-check mt-3">
            <input type="checkbox" class="form-check-input" name="is_current" id="sess_current" value="1">
            <label class="form-check-label" for="sess_current">
              <strong>Set as current session</strong>
              <small class="text-muted d-block">This will deactivate the previous current session</small>
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Session</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setSessionForm(sess) {
  const title = document.getElementById('sessionModalTitle');
  const id    = document.getElementById('sess_id');
  if (!sess) {
    title.textContent = 'New Academic Session';
    id.value = 0;
    document.getElementById('sess_name').value   = '';
    document.getElementById('sess_start').value  = '';
    document.getElementById('sess_end').value    = '';
    document.getElementById('sess_status').value = 'upcoming';
    document.getElementById('sess_current').checked = false;
  } else {
    title.textContent = 'Edit Session';
    id.value = sess.id;
    document.getElementById('sess_name').value   = sess.session_name;
    document.getElementById('sess_start').value  = sess.start_date;
    document.getElementById('sess_end').value    = sess.end_date;
    document.getElementById('sess_status').value = sess.status;
    document.getElementById('sess_current').checked = sess.is_current == 1;
  }
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
