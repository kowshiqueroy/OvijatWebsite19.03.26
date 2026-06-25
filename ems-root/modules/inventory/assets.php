<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Fixed Assets';
$breadcrumbs = ['Inventory' => null, 'Assets' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['inventory.view']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!has_permission('inventory.manage')) { flash('error', 'No permission.'); header('Location: assets.php'); exit; }

    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id     = int_param('id', 0, $_POST);
        $catId  = int_param('asset_category_id', 0, $_POST);
        $name   = trim($_POST['asset_name'] ?? '');
        $serial = trim($_POST['serial_number'] ?? '') ?: null;
        $date   = $_POST['purchase_date'] ?? null;
        $price  = (float)($_POST['purchase_price'] ?? 0);
        $vendor = trim($_POST['vendor'] ?? '');
        $room   = int_param('location_room_id', 0, $_POST) ?: null;
        $status = $_POST['status'] ?? 'available';
        $notes  = trim($_POST['notes'] ?? '');

        if ($name && $catId) {
            if ($id) {
                $pdo->prepare('UPDATE assets SET asset_category_id=?,asset_name=?,serial_number=?,purchase_date=?,purchase_price=?,vendor=?,location_room_id=?,status=?,notes=? WHERE id=?')
                    ->execute([$catId,$name,$serial,$date,$price,$vendor,$room,$status,$notes,$id]);
                flash('success', 'Asset updated.');
            } else {
                $pdo->prepare('INSERT INTO assets (asset_category_id,asset_name,serial_number,purchase_date,purchase_price,vendor,location_room_id,status,notes) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$catId,$name,$serial,$date,$price,$vendor,$room,$status,$notes]);
                flash('success', "Asset '$name' added.");
            }
        }
    } elseif ($action === 'assign') {
        $assetId    = int_param('asset_id', 0, $_POST);
        $assignedTo = int_param('assigned_to', 0, $_POST);
        $assignDate = $_POST['assigned_date'] ?? date('Y-m-d');
        $condition  = $_POST['condition_out'] ?? 'good';
        $notes      = trim($_POST['notes'] ?? '');

        if ($assetId && $assignedTo) {
            $pdo->prepare('INSERT INTO asset_assignments (asset_id,assigned_to,assigned_by,assigned_date,condition_out,notes) VALUES (?,?,?,?,?,?)')
                ->execute([$assetId,$assignedTo,current_user_id(),$assignDate,$condition,$notes]);
            $pdo->prepare("UPDATE assets SET status='assigned' WHERE id=?")->execute([$assetId]);
            flash('success', 'Asset assigned.');
        }
    } elseif ($action === 'return') {
        $assignId = int_param('assign_id', 0, $_POST);
        $condIn   = $_POST['condition_in'] ?? 'good';
        $retDate  = $_POST['return_date'] ?? date('Y-m-d');
        if ($assignId) {
            $aa = $pdo->prepare('SELECT asset_id FROM asset_assignments WHERE id=:id');
            $aa->execute([':id' => $assignId]);
            $assetId = $aa->fetchColumn();
            $pdo->prepare("UPDATE asset_assignments SET status='returned',condition_in=?,return_date=? WHERE id=?")->execute([$condIn,$retDate,$assignId]);
            $pdo->prepare("UPDATE assets SET status='available' WHERE id=?")->execute([$assetId]);
            flash('success', 'Asset returned.');
        }
    }
    header('Location: assets.php');
    exit;
}

$status_filter = $_GET['status'] ?? '';
$cat_filter    = int_param('cat', 0, $_GET);
$search        = trim($_GET['q'] ?? '');
$page          = max(1, int_param('page', 1, $_GET));

$where  = ['1=1'];
$params = [];
if ($status_filter) { $where[] = 'a.status=:st'; $params[':st'] = $status_filter; }
if ($cat_filter)    { $where[] = 'a.asset_category_id=:cat'; $params[':cat'] = $cat_filter; }
if ($search)        { $where[] = '(a.asset_name LIKE :q OR a.serial_number LIKE :q)'; $params[':q'] = "%$search%"; }

$whereStr = implode(' AND ', $where);
$cntStmt  = $pdo->prepare("SELECT COUNT(*) FROM assets a WHERE $whereStr");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pg    = paginate($total, $page);
$assets = $pdo->prepare(
    "SELECT a.*, ac.category_name, r.room_name,
            aa_active.id as assign_id, u.full_name as assigned_to_name
     FROM assets a
     JOIN asset_categories ac ON ac.id=a.asset_category_id
     LEFT JOIN rooms r ON r.id=a.location_room_id
     LEFT JOIN asset_assignments aa_active ON aa_active.asset_id=a.id AND aa_active.status='active'
     LEFT JOIN users u ON u.id=aa_active.assigned_to
     WHERE $whereStr
     ORDER BY a.id DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$assets->execute($params);
$assets = $assets->fetchAll();

$categories = $pdo->query('SELECT id, category_name FROM asset_categories ORDER BY category_name')->fetchAll();
$rooms      = $pdo->query('SELECT id, room_name FROM rooms ORDER BY room_name')->fetchAll();
$staff      = $pdo->query('SELECT sp.user_id, CONCAT(sp.first_name," ",sp.last_name) as name FROM staff_profiles sp WHERE sp.status="active" ORDER BY name LIMIT 100')->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-pc-display me-2 text-primary"></i>Fixed Assets</h1>
  <?php if (has_permission('inventory.manage')): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assetModal" onclick="setAssetForm(null)">
      <i class="bi bi-plus-lg me-1"></i>Add Asset
    </button>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small">Search</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Name, serial…" value="<?= e($search) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small">Category</label>
        <select name="cat" class="form-select form-select-sm">
          <option value="0">All</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $cat_filter == $c['id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach (['available','assigned','maintenance','disposed'] as $st): ?>
            <option value="<?= $st ?>" <?= $status_filter === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
        <a href="assets.php" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card table-card">
  <div class="card-header py-3 px-4">
    <span class="card-title">Assets <span class="badge bg-secondary"><?= $total ?></span></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>#</th><th>Asset</th><th>Category</th><th>Serial</th><th>Location/Assigned</th><th>Purchase</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($assets)): ?>
          <tr><td colspan="8"><div class="empty-state"><i class="bi bi-archive"></i><p>No assets found</p></div></td></tr>
        <?php else: foreach ($assets as $i => $a): ?>
        <tr>
          <td><?= $pg['offset'] + $i + 1 ?></td>
          <td class="fw-600"><?= e($a['asset_name']) ?></td>
          <td><?= e($a['category_name']) ?></td>
          <td><code><?= e($a['serial_number'] ?? '—') ?></code></td>
          <td>
            <?php if ($a['assigned_to_name']): ?>
              <span class="fw-600"><?= e($a['assigned_to_name']) ?></span>
            <?php else: ?>
              <?= e($a['room_name'] ?? '—') ?>
            <?php endif; ?>
          </td>
          <td><?= fmt_date($a['purchase_date']) ?> <small class="text-muted"><?= money($a['purchase_price']) ?></small></td>
          <td>
            <span class="badge-status badge-<?= $a['status'] === 'available' ? 'active' : ($a['status'] === 'assigned' ? 'pending' : 'draft') ?>">
              <?= ucfirst(e($a['status'])) ?>
            </span>
          </td>
          <td>
            <div class="table-actions">
              <?php if (has_permission('inventory.manage')): ?>
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assetModal"
                      onclick="setAssetForm(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <?php if ($a['status'] === 'available'): ?>
              <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#assignModal"
                      onclick="document.getElementById('asgn_asset').value=<?= $a['id'] ?>">
                <i class="bi bi-person-check"></i>
              </button>
              <?php elseif ($a['status'] === 'assigned' && $a['assign_id']): ?>
              <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#returnModal"
                      onclick="document.getElementById('ret_assign').value=<?= $a['assign_id'] ?>">
                <i class="bi bi-arrow-return-left"></i>
              </button>
              <?php endif; ?>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Asset Modal -->
<div class="modal fade" id="assetModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="a_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="assetModalTitle">Add Asset</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Category <span class="text-danger">*</span></label>
              <select name="asset_category_id" id="a_cat" class="form-select" required>
                <option value="">— Select —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Asset Name <span class="text-danger">*</span></label>
              <input type="text" name="asset_name" id="a_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Serial Number</label>
              <input type="text" name="serial_number" id="a_serial" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Location (Room)</label>
              <select name="location_room_id" id="a_room" class="form-select">
                <option value="">— None —</option>
                <?php foreach ($rooms as $r): ?>
                  <option value="<?= $r['id'] ?>"><?= e($r['room_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Purchase Date</label>
              <input type="date" name="purchase_date" id="a_date" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Purchase Price</label>
              <input type="number" name="purchase_price" id="a_price" class="form-control" step="0.01" min="0" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" id="a_status" class="form-select">
                <option value="available">Available</option>
                <option value="maintenance">Maintenance</option>
                <option value="disposed">Disposed</option>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label">Vendor</label>
              <input type="text" name="vendor" id="a_vendor" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" id="a_notes" class="form-control" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Asset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign">
        <input type="hidden" name="asset_id" id="asgn_asset" value="">
        <div class="modal-header"><h5 class="modal-title">Assign Asset</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Assign To (Staff) <span class="text-danger">*</span></label>
            <select name="assigned_to" class="form-select" required>
              <option value="">— Select Staff —</option>
              <?php foreach ($staff as $st): ?>
                <option value="<?= $st['user_id'] ?>"><?= e($st['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Date</label><input type="date" name="assigned_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
          <div class="mb-3">
            <label class="form-label">Condition</label>
            <select name="condition_out" class="form-select">
              <option value="excellent">Excellent</option>
              <option value="good" selected>Good</option>
              <option value="fair">Fair</option>
              <option value="poor">Poor</option>
            </select>
          </div>
          <div><label class="form-label">Notes</label><input type="text" name="notes" class="form-control"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">Assign</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="return">
        <input type="hidden" name="assign_id" id="ret_assign" value="">
        <div class="modal-header"><h5 class="modal-title">Return Asset</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Return Date</label><input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
          <div><label class="form-label">Condition on Return</label>
          <select name="condition_in" class="form-select">
            <option value="excellent">Excellent</option>
            <option value="good" selected>Good</option>
            <option value="fair">Fair</option>
            <option value="poor">Poor</option>
            <option value="damaged">Damaged</option>
          </select></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Confirm Return</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setAssetForm(a) {
  document.getElementById('assetModalTitle').textContent = a ? 'Edit Asset' : 'Add Asset';
  document.getElementById('a_id').value     = a ? a.id : 0;
  document.getElementById('a_cat').value    = a ? a.asset_category_id : '';
  document.getElementById('a_name').value   = a ? a.asset_name : '';
  document.getElementById('a_serial').value = a ? (a.serial_number || '') : '';
  document.getElementById('a_room').value   = a ? (a.location_room_id || '') : '';
  document.getElementById('a_date').value   = a ? (a.purchase_date || '') : '';
  document.getElementById('a_price').value  = a ? a.purchase_price : 0;
  document.getElementById('a_status').value = a ? a.status : 'available';
  document.getElementById('a_vendor').value = a ? (a.vendor || '') : '';
  document.getElementById('a_notes').value  = a ? (a.notes || '') : '';
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
