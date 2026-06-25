<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Asset Assignments';
$breadcrumbs = ['Inventory' => 'assets.php', 'Assignments' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['inventory.view']);

$pdo = db();

// Handle return action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('inventory.manage')) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'return') {
        $assign_id   = int_param('assign_id', 0, $_POST);
        $condition   = $_POST['condition_in'] ?? 'good';
        $return_date = $_POST['return_date'] ?: date('Y-m-d');
        $notes       = trim($_POST['notes'] ?? '');

        if ($assign_id) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('SELECT * FROM asset_assignments WHERE id = ? AND status = "active"');
                $stmt->execute([$assign_id]);
                $asgn = $stmt->fetch();
                if ($asgn) {
                    $pdo->prepare('UPDATE asset_assignments SET status="returned", condition_in=?, return_date=?, notes=CONCAT(COALESCE(notes,""), " | Return note: ", ?) WHERE id=?')
                        ->execute([$condition, $return_date, $notes, $assign_id]);
                    $pdo->prepare('UPDATE assets SET status="available" WHERE id=?')
                        ->execute([$asgn['asset_id']]);
                    $pdo->commit();
                    log_activity('return_asset', 'inventory', $asgn['asset_id'], 'assigned', 'returned');
                    flash('success', 'Asset returned successfully.');
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', 'Return failed: ' . $e->getMessage());
            }
        }
    }

    if ($action === 'assign') {
        $asset_id  = int_param('asset_id', 0, $_POST);
        $user_id   = int_param('assigned_to', 0, $_POST);
        $asgn_date = $_POST['assigned_date'] ?: date('Y-m-d');
        $condition = $_POST['condition_out'] ?? 'good';
        $notes     = trim($_POST['notes'] ?? '');

        if ($asset_id && $user_id) {
            $pdo->beginTransaction();
            try {
                $chk = $pdo->prepare('SELECT status FROM assets WHERE id = ?');
                $chk->execute([$asset_id]);
                $assetRow = $chk->fetch();
                if (!$assetRow || $assetRow['status'] !== 'available') {
                    throw new Exception('Asset is not available for assignment.');
                }
                $pdo->prepare('INSERT INTO asset_assignments (asset_id, assigned_to, assigned_by, assigned_date, condition_out, notes, status) VALUES (?,?,?,?,?,?,"active")')
                    ->execute([$asset_id, $user_id, current_user_id(), $asgn_date, $condition, $notes]);
                $pdo->prepare('UPDATE assets SET status="assigned" WHERE id=?')->execute([$asset_id]);
                $pdo->commit();
                log_activity('assign_asset', 'inventory', $asset_id, 'available', "Assigned to user $user_id");
                flash('success', 'Asset assigned successfully.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', $e->getMessage());
            }
        } else {
            flash('error', 'Asset and assignee are required.');
        }
    }

    header('Location: assignments.php');
    exit;
}

// Filters
$filter_status = $_GET['status'] ?? 'active';
$where = $filter_status ? 'WHERE aa.status = :s' : 'WHERE 1=1';
$params = $filter_status ? [':s' => $filter_status] : [];

$assignments = $pdo->prepare(
    "SELECT aa.*, a.asset_name, a.serial_number,
            ac.category_name,
            u.full_name AS assignee_name,
            ab.full_name AS assigned_by_name
     FROM asset_assignments aa
     JOIN assets a ON a.id = aa.asset_id
     JOIN asset_categories ac ON ac.id = a.asset_category_id
     JOIN users u ON u.id = aa.assigned_to
     JOIN users ab ON ab.id = aa.assigned_by
     $where
     ORDER BY aa.id DESC"
);
$assignments->execute($params);
$assignments = $assignments->fetchAll();

// Available assets for assignment form
$availableAssets = $pdo->query(
    "SELECT a.id, a.asset_name, a.serial_number, ac.category_name
     FROM assets a
     JOIN asset_categories ac ON ac.id = a.asset_category_id
     WHERE a.status = 'available'
     ORDER BY ac.category_name, a.asset_name"
)->fetchAll();

// All users for assignee dropdown
$allUsers = $pdo->query(
    "SELECT u.id, u.full_name, r.role_name
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     WHERE u.status = 'active'
     GROUP BY u.id
     ORDER BY u.full_name"
)->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-box-arrow-right me-2 text-primary"></i>Asset Assignments</h1>
  <?php if (has_permission('inventory.manage') && !empty($availableAssets)): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignModal">
      <i class="bi bi-plus-lg me-1"></i>New Assignment
    </button>
  <?php endif; ?>
</div>

<?php render_flash(); ?>

<!-- Filter bar -->
<div class="card mb-4 border-0 shadow-sm">
  <div class="card-body py-2">
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <span class="text-muted small fw-600">Filter:</span>
      <?php foreach (['active' => 'Active', 'returned' => 'Returned', '' => 'All'] as $v => $label): ?>
        <a href="?status=<?= $v ?>" class="btn btn-sm <?= $filter_status === $v ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $label ?></a>
      <?php endforeach; ?>
      <span class="ms-auto text-muted small"><?= count($assignments) ?> record(s)</span>
    </div>
  </div>
</div>

<!-- Assignments table -->
<div class="card shadow-sm border-0">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>#</th>
          <th>Asset</th>
          <th>Assigned To</th>
          <th>Assigned By</th>
          <th>Date Out</th>
          <th>Condition Out</th>
          <th>Date In</th>
          <th>Condition In</th>
          <th class="text-center">Status</th>
          <?php if (has_permission('inventory.manage')): ?>
            <th class="text-end">Action</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($assignments)): ?>
          <tr><td colspan="10" class="text-center py-5 text-muted">No assignment records found.</td></tr>
        <?php else: ?>
          <?php foreach ($assignments as $a): ?>
            <tr>
              <td class="text-muted small"><?= $a['id'] ?></td>
              <td>
                <div class="fw-600"><?= e($a['asset_name']) ?></div>
                <small class="text-muted"><?= e($a['category_name']) ?><?= $a['serial_number'] ? ' · S/N: ' . e($a['serial_number']) : '' ?></small>
              </td>
              <td><?= e($a['assignee_name']) ?></td>
              <td class="text-muted small"><?= e($a['assigned_by_name']) ?></td>
              <td><?= fmt_date($a['assigned_date']) ?></td>
              <td><span class="badge bg-secondary"><?= ucfirst(e($a['condition_out'])) ?></span></td>
              <td><?= $a['return_date'] ? fmt_date($a['return_date']) : '<span class="text-muted">—</span>' ?></td>
              <td><?= $a['condition_in'] ? '<span class="badge bg-info text-dark">' . ucfirst(e($a['condition_in'])) . '</span>' : '<span class="text-muted">—</span>' ?></td>
              <td class="text-center">
                <?php if ($a['status'] === 'active'): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Returned</span>
                <?php endif; ?>
              </td>
              <?php if (has_permission('inventory.manage')): ?>
                <td class="text-end">
                  <?php if ($a['status'] === 'active'): ?>
                    <button class="btn btn-xs btn-outline-warning"
                      onclick="openReturnModal(<?= $a['id'] ?>, '<?= e($a['asset_name']) ?>', '<?= e($a['assignee_name']) ?>')">
                      <i class="bi bi-box-arrow-in-left me-1"></i>Return
                    </button>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (has_permission('inventory.manage')): ?>
<!-- New Assignment Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-600"><i class="bi bi-box-arrow-right me-2"></i>Assign Asset</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-600">Asset <span class="text-danger">*</span></label>
            <select name="asset_id" class="form-select form-select-sm" required>
              <option value="">— Select Available Asset —</option>
              <?php foreach ($availableAssets as $a): ?>
                <option value="<?= $a['id'] ?>">[<?= e($a['category_name']) ?>] <?= e($a['asset_name']) ?><?= $a['serial_number'] ? ' (S/N: ' . e($a['serial_number']) . ')' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-600">Assign To <span class="text-danger">*</span></label>
            <select name="assigned_to" class="form-select form-select-sm" required>
              <option value="">— Select Staff / User —</option>
              <?php foreach ($allUsers as $u): ?>
                <option value="<?= $u['id'] ?>"><?= e($u['full_name']) ?><?= $u['role_name'] ? ' (' . e($u['role_name']) . ')' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-600">Assignment Date</label>
              <input type="date" name="assigned_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Condition at Issue</label>
              <select name="condition_out" class="form-select form-select-sm">
                <option value="excellent">Excellent</option>
                <option value="good" selected>Good</option>
                <option value="fair">Fair</option>
                <option value="poor">Poor</option>
              </select>
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label small fw-600">Notes / Remarks</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional">
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Assign</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="return">
        <input type="hidden" name="assign_id" id="ret-assign-id">
        <div class="modal-header bg-warning">
          <h5 class="modal-title fw-600"><i class="bi bi-box-arrow-in-left me-2"></i>Return Asset</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">Returning: <strong id="ret-asset-name" class="text-dark"></strong> from <strong id="ret-assignee-name" class="text-dark"></strong></p>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-600">Return Date</label>
              <input type="date" name="return_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Condition on Return</label>
              <select name="condition_in" class="form-select form-select-sm">
                <option value="excellent">Excellent</option>
                <option value="good" selected>Good</option>
                <option value="fair">Fair</option>
                <option value="poor">Poor</option>
                <option value="damaged">Damaged</option>
              </select>
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label small fw-600">Return Notes</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Minor scratches on lid">
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-check-lg me-1"></i>Confirm Return</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openReturnModal(id, assetName, assigneeName) {
  document.getElementById('ret-assign-id').value = id;
  document.getElementById('ret-asset-name').textContent = assetName;
  document.getElementById('ret-assignee-name').textContent = assigneeName;
  new bootstrap.Modal(document.getElementById('returnModal')).show();
}
</script>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
