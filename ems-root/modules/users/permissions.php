<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Permissions';
$breadcrumbs = ['Users & Roles' => 'index.php', 'Permissions' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['roles.manage']);

$pdo = db();

// Handle add/edit permission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id    = int_param('id', 0, $_POST);
        $key   = preg_replace('/[^a-z0-9_.]/', '', strtolower(trim($_POST['permission_key']??'')));
        $module= trim($_POST['module']??'');
        $label = trim($_POST['label']??'');
        $desc  = trim($_POST['description']??'');
        if ($key && $module) {
            try {
                if ($id) {
                    $pdo->prepare('UPDATE permissions SET permission_key=?,module=?,label=?,description=? WHERE id=?')->execute([$key,$module,$label,$desc,$id]);
                    flash('success','Permission updated.');
                } else {
                    $pdo->prepare('INSERT INTO permissions (permission_key,module,label,description) VALUES (?,?,?,?)')->execute([$key,$module,$label,$desc]);
                    flash('success',"Permission '$key' added.");
                }
            } catch (Exception $e) { flash('error','Key already exists.'); }
        }
    } elseif ($action === 'delete') {
        $id = int_param('id',0,$_POST);
        $pdo->prepare('DELETE FROM permissions WHERE id=?')->execute([$id]);
        flash('success','Permission deleted.');
    }
    header('Location: permissions.php');
    exit;
}

$perms = $pdo->query('SELECT p.*, COUNT(rp.role_id) as role_count FROM permissions p LEFT JOIN role_permissions rp ON rp.permission_id=p.id GROUP BY p.id ORDER BY p.module, p.permission_key')->fetchAll();
$modules = array_unique(array_column($perms, 'module'));
sort($modules);

// Group by module
$grouped = [];
foreach ($perms as $p) $grouped[$p['module']][] = $p;

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-shield-lock-fill me-2 text-primary"></i>Permissions</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#permModal" onclick="setPermForm(null)">
    <i class="bi bi-plus-lg me-1"></i>Add Permission
  </button>
</div>

<div class="alert alert-info d-flex gap-2 mb-3">
  <i class="bi bi-info-circle-fill"></i>
  <div>Permissions are assigned to <strong>roles</strong> (not users directly). Manage role-permission assignments on the <a href="roles.php">Roles</a> page.</div>
</div>

<?php foreach ($grouped as $module => $perms): ?>
<div class="card mb-3">
  <div class="card-header py-3 px-4">
    <span class="card-title text-capitalize"><?= e(ucwords(str_replace('_',' ',$module))) ?> <span class="badge bg-secondary"><?= count($perms) ?></span></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 small">
      <thead><tr><th>Key</th><th>Label</th><th>Description</th><th>Used by Roles</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($perms as $p): ?>
        <tr>
          <td><code class="text-primary"><?= e($p['permission_key']) ?></code></td>
          <td class="fw-600"><?= e($p['label']??'—') ?></td>
          <td class="text-muted"><?= e($p['description']??'—') ?></td>
          <td><span class="badge bg-secondary"><?= $p['role_count'] ?> roles</span></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:.15rem .4rem;"
                      data-bs-toggle="modal" data-bs-target="#permModal"
                      onclick="setPermForm(<?= htmlspecialchars(json_encode($p),ENT_QUOTES) ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:.15rem .4rem;"
                        data-confirm="Delete permission '<?= e($p['permission_key']) ?>'? This removes it from all roles.">
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
</div>
<?php endforeach; ?>

<div class="modal fade" id="permModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="pm_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="permModalTitle">Add Permission</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Permission Key <span class="text-danger">*</span> <small class="text-muted">(lowercase, dots allowed)</small></label>
            <input type="text" name="permission_key" id="pm_key" class="form-control" placeholder="e.g. exams.publish" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Module <span class="text-danger">*</span></label>
            <input type="text" name="module" id="pm_mod" class="form-control" placeholder="e.g. exams" list="module-list" required>
            <datalist id="module-list">
              <?php foreach($modules as $m): ?><option value="<?= e($m) ?>"><?php endforeach; ?>
            </datalist>
          </div>
          <div class="mb-3">
            <label class="form-label">Label</label>
            <input type="text" name="label" id="pm_label" class="form-control" placeholder="e.g. Publish Results">
          </div>
          <div class="mb-0">
            <label class="form-label">Description</label>
            <input type="text" name="description" id="pm_desc" class="form-control" placeholder="What this permission allows">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setPermForm(p) {
  document.getElementById('permModalTitle').textContent = p ? 'Edit Permission' : 'Add Permission';
  document.getElementById('pm_id').value    = p ? p.id : 0;
  document.getElementById('pm_key').value   = p ? p.permission_key : '';
  document.getElementById('pm_mod').value   = p ? p.module : '';
  document.getElementById('pm_label').value = p ? (p.label||'') : '';
  document.getElementById('pm_desc').value  = p ? (p.description||'') : '';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
