<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Deleted Items';
$breadcrumbs = ['Setup' => 'index.php', 'Deleted Items' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['deleted.view']);

$pdo = db();

// Tables that support soft delete and their display config
$SOFT_TABLES = [
    'classes'            => ['label'=>'Classes',            'name_col'=>'class_name',    'extra'=>'class_level'],
    'sections'           => ['label'=>'Sections',           'name_col'=>'section_name',  'extra'=>null],
    'subjects'           => ['label'=>'Subjects',           'name_col'=>'subject_name',  'extra'=>'subject_type'],
    'exams'              => ['label'=>'Exams',              'name_col'=>'exam_name',     'extra'=>'exam_type'],
    'rooms'              => ['label'=>'Rooms',              'name_col'=>'room_name',     'extra'=>'room_type'],
    'special_batches'    => ['label'=>'Special Batches',    'name_col'=>'batch_name',    'extra'=>'batch_type'],
    'notices'            => ['label'=>'Notices',            'name_col'=>'title',         'extra'=>null],
    'document_templates' => ['label'=>'Document Templates', 'name_col'=>'template_name', 'extra'=>'template_type'],
    'fee_categories'     => ['label'=>'Fee Categories',     'name_col'=>'category_name', 'extra'=>'category_type'],
];

$active_tab = $_GET['tab'] ?? 'classes';
if (!array_key_exists($active_tab, $SOFT_TABLES)) $active_tab = 'classes';

// ── Actions ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action   = $_POST['action'] ?? '';
    $table    = $_POST['table']  ?? '';
    $id       = int_param('id', 0, $_POST);
    $pin      = trim($_POST['dev_pin'] ?? '');

    if (!array_key_exists($table, $SOFT_TABLES)) {
        flash('error', 'Invalid table.');
        header('Location: deleted.php?tab=' . urlencode($table));
        exit;
    }

    if ($action === 'restore') {
        // Restore: clear deleted_at / deleted_by
        $pdo->prepare("UPDATE `$table` SET deleted_at=NULL, deleted_by=NULL WHERE id=:id")
            ->execute([':id' => $id]);
        log_activity('restore', $table, $id);
        flash('success', 'Record restored successfully.');

    } elseif ($action === 'hard_delete') {
        // Hard delete — requires developer PIN and admin role
        if (!has_role('super_admin')) {
            flash('error', 'Hard delete is restricted to Super Admin only.');
        } elseif (!verify_dev_pin($pin)) {
            flash('error', 'Incorrect developer PIN. Hard delete cancelled.');
        } else {
            // Log the record before deletion
            $row  = $pdo->prepare("SELECT * FROM `$table` WHERE id=:id")->execute([':id'=>$id]);
            $data = json_encode($row ?: []);
            $pdo->prepare("DELETE FROM `$table` WHERE id=:id")->execute([':id' => $id]);
            $pdo->prepare('INSERT INTO developer_actions_log (action_type, table_name, record_id, record_data, performed_by, reason)
                           VALUES ("hard_delete", :t, :id, :data, :by, :reason)')
                ->execute([':t'=>$table, ':id'=>$id, ':data'=>$data,
                           ':by'=>$_SESSION['user_id']??null,
                           ':reason'=>trim($_POST['reason']??'')]);
            log_activity('hard_delete', $table, $id);
            flash('success', 'Record permanently deleted and logged.');
        }
    }

    header('Location: deleted.php?tab=' . urlencode($table));
    exit;
}

// ── Load deleted items for active tab ─────────────────────────────────────────
$cfg   = $SOFT_TABLES[$active_tab];
$name  = $cfg['name_col'];
$extra = $cfg['extra'];

$extraSel = $extra ? ", `$extra`" : '';
$items = $pdo->query(
    "SELECT id, `$name` AS record_name $extraSel, deleted_at, deleted_by
     FROM `$active_tab`
     WHERE deleted_at IS NOT NULL
     ORDER BY deleted_at DESC"
)->fetchAll();

// Count of deleted per tab (for badges)
$counts = [];
foreach ($SOFT_TABLES as $tbl => $c) {
    try {
        $counts[$tbl] = (int)$pdo->query("SELECT COUNT(*) FROM `$tbl` WHERE deleted_at IS NOT NULL")->fetchColumn();
    } catch (Exception $e) { $counts[$tbl] = 0; }
}

// Deleted-by user names
$deletedByUsers = [];
if ($items) {
    $byIds = array_unique(array_filter(array_column($items, 'deleted_by')));
    if ($byIds) {
        $in = implode(',', array_map('intval', $byIds));
        $deletedByUsers = $pdo->query("SELECT id, full_name FROM users WHERE id IN ($in)")
                              ->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

$isAdmin = has_role('super_admin');

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h1 class="page-title mb-0"><i class="bi bi-trash3 me-2 text-danger"></i>Deleted Items</h1>
  <div class="d-flex gap-2">
    <span class="badge bg-warning text-dark">
      <i class="bi bi-lock-fill me-1"></i>Soft-deleted only — records are hidden, not gone
    </span>
    <?php if ($isAdmin): ?>
    <span class="badge bg-danger">
      <i class="bi bi-shield-exclamation me-1"></i>Hard Delete: PIN required
    </span>
    <?php endif; ?>
  </div>
</div>

<div class="alert alert-info d-flex gap-2 mb-3">
  <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
  <div>
    Items shown here were <strong>soft-deleted</strong> — hidden from all normal views but safely preserved in the database.
    You can <strong>Restore</strong> any item instantly. <strong>Hard Delete</strong> is permanent and requires the Developer PIN
    and is available to Super Admin only.
  </div>
</div>

<!-- Tab navigation with counts -->
<ul class="nav nav-tabs mb-3 flex-wrap">
  <?php foreach ($SOFT_TABLES as $tbl => $c): ?>
  <li class="nav-item">
    <a class="nav-link <?= $active_tab === $tbl ? 'active' : '' ?>" href="?tab=<?= urlencode($tbl) ?>">
      <?= e($c['label']) ?>
      <?php if ($counts[$tbl] > 0): ?>
        <span class="badge bg-danger ms-1"><?= $counts[$tbl] ?></span>
      <?php endif; ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<!-- Deleted items table -->
<div class="card">
  <div class="card-header">
    <h6 class="card-title mb-0">
      Deleted <?= e($cfg['label']) ?>
      <span class="badge bg-secondary ms-2"><?= count($items) ?></span>
    </h6>
  </div>
  <?php if (empty($items)): ?>
  <div class="card-body">
    <div class="empty-state">
      <i class="bi bi-check-circle-fill text-success"></i>
      <p>No deleted items in this category. Everything looks clean!</p>
    </div>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <?php if ($extra): ?><th>Type</th><?php endif; ?>
          <th>Deleted At</th>
          <th>Deleted By</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $row): ?>
        <tr class="table-danger">
          <td><span class="text-muted"><?= $row['id'] ?></span></td>
          <td><strong><?= e($row['record_name']) ?></strong></td>
          <?php if ($extra): ?>
          <td><span class="badge bg-secondary"><?= e(ucwords(str_replace('_',' ',$row[$extra]??''))) ?></span></td>
          <?php endif; ?>
          <td><?= $row['deleted_at'] ? date('d M Y, H:i', strtotime($row['deleted_at'])) : '—' ?></td>
          <td><?= e($deletedByUsers[$row['deleted_by']] ?? ('User #' . ($row['deleted_by'] ?? '?'))) ?></td>
          <td>
            <div class="d-flex gap-1 flex-wrap">
              <!-- Restore -->
              <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="table"  value="<?= e($active_tab) ?>">
                <input type="hidden" name="id"     value="<?= $row['id'] ?>">
                <button type="submit" class="btn btn-success btn-sm"
                        data-confirm="Restore '<?= e(addslashes($row['record_name'])) ?>'?">
                  <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                </button>
              </form>
              <!-- Hard Delete (admin + PIN) -->
              <?php if ($isAdmin): ?>
              <button type="button" class="btn btn-danger btn-sm"
                      onclick="showHardDelete('<?= e(addslashes($active_tab)) ?>',<?= $row['id'] ?>,'<?= e(addslashes($row['record_name'])) ?>')">
                <i class="bi bi-x-octagon me-1"></i>Hard Delete
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<!-- Hard Delete Modal — PIN protected -->
<div class="modal fade" id="hardDeleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-x-octagon me-2"></i>Permanent Hard Delete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="hard_delete">
        <input type="hidden" name="table"  id="hdTable">
        <input type="hidden" name="id"     id="hdId">
        <div class="modal-body">
          <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>PERMANENT ACTION</strong> — This record and its database entry will be gone forever.
            This cannot be undone.
          </div>
          <p>You are about to permanently delete: <strong id="hdName" class="text-danger"></strong></p>
          <div class="mb-3">
            <label class="form-label fw-bold">Reason for Hard Delete</label>
            <input type="text" name="reason" class="form-control" placeholder="Enter reason (logged for audit)">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold text-danger">Developer PIN <span class="text-danger">*</span></label>
            <input type="password" name="dev_pin" id="hdPin" class="form-control border-danger"
                   placeholder="Enter 4-digit developer PIN" maxlength="10" autocomplete="off">
            <small class="text-muted">Only the system developer PIN is accepted. Contact SohojWeb for the PIN.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash3-fill me-1"></i>Permanently Delete
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function showHardDelete(table, id, name) {
  document.getElementById('hdTable').value = table;
  document.getElementById('hdId').value    = id;
  document.getElementById('hdName').textContent = name;
  document.getElementById('hdPin').value   = '';
  new bootstrap.Modal(document.getElementById('hardDeleteModal')).show();
}
</script>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
