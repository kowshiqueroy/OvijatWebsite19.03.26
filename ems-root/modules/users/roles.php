<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Roles & Permissions';
$breadcrumbs = ['Users & Roles' => 'index.php', 'Roles' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['roles.manage']);

$pdo = db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_role') {
        $name = trim($_POST['role_name'] ?? '');
        $slug = preg_replace('/\s+/', '_', strtolower(trim($_POST['role_slug'] ?? $name)));
        $desc = trim($_POST['description'] ?? '');
        if ($name && $slug) {
            try {
                $pdo->prepare('INSERT INTO roles (role_name, role_slug, description) VALUES (?,?,?)')->execute([$name, $slug, $desc]);
                flash('success', "Role '$name' created.");
            } catch (Exception $e) {
                flash('error', 'Role slug already exists.');
            }
        }
    } elseif ($action === 'save_permissions') {
        $roleId   = int_param('role_id', 0, $_POST);
        $permIds  = array_map('intval', (array)($_POST['perm_ids'] ?? []));
        $pdo->prepare('DELETE FROM role_permissions WHERE role_id=:rid')->execute([':rid' => $roleId]);
        $stmt = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?,?)');
        foreach ($permIds as $pid) { if ($pid) $stmt->execute([$roleId, $pid]); }
        log_activity('update_role_perms', 'users', $roleId);
        flash('success', 'Permissions updated.');
    } elseif ($action === 'delete_role') {
        $roleId = int_param('role_id', 0, $_POST);
        $pdo->prepare('DELETE FROM roles WHERE id=:id AND role_slug NOT IN ("super_admin")')->execute([':id' => $roleId]);
        flash('success', 'Role deleted.');
    }
    header('Location: roles.php' . (isset($_POST['role_id']) ? '?edit=' . (int)$_POST['role_id'] : ''));
    exit;
}

$roles   = $pdo->query('SELECT r.*, COUNT(ur.user_id) as user_count FROM roles r LEFT JOIN user_roles ur ON ur.role_id=r.id GROUP BY r.id ORDER BY r.id')->fetchAll();
$editId  = int_param('edit', 0, $_GET);

// Permissions grouped by module
$allPerms    = $pdo->query('SELECT * FROM permissions ORDER BY module, permission_key')->fetchAll();
$permGroups  = [];
foreach ($allPerms as $p) $permGroups[$p['module']][] = $p;

// Current role's permissions
$currentPerms = [];
if ($editId) {
    $cp = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id=:rid');
    $cp->execute([':rid' => $editId]);
    $currentPerms = $cp->fetchAll(PDO::FETCH_COLUMN);
}

$editRole = null;
if ($editId) {
    foreach ($roles as $r) { if ((int)$r['id'] === $editId) { $editRole = $r; break; } }
}

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-shield-fill me-2 text-primary"></i>Roles & Permissions</h1>
</div>

<div class="row g-3">

  <!-- Roles list -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="card-title">Roles</span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newRoleModal">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach ($roles as $role): ?>
        <a href="roles.php?edit=<?= $role['id'] ?>"
           class="list-group-item list-group-item-action d-flex align-items-center justify-content-between
                  <?= $editId === (int)$role['id'] ? 'active' : '' ?>">
          <div>
            <div class="fw-600"><?= e($role['role_name']) ?></div>
            <small class="<?= $editId === (int)$role['id'] ? 'text-white-50' : 'text-muted' ?>">
              <code><?= e($role['role_slug']) ?></code>
            </small>
          </div>
          <span class="badge bg-secondary"><?= $role['user_count'] ?> users</span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Permissions editor -->
  <div class="col-md-8">
    <?php if ($editRole): ?>
    <div class="card">
      <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="card-title">Permissions for: <em><?= e($editRole['role_name']) ?></em></span>
        <?php if ($editRole['role_slug'] !== 'super_admin'): ?>
          <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_role">
            <input type="hidden" name="role_id" value="<?= $editId ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"
                    data-confirm="Delete role '<?= e($editRole['role_name']) ?>'?">
              <i class="bi bi-trash"></i>
            </button>
          </form>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_permissions">
          <input type="hidden" name="role_id" value="<?= $editId ?>">

          <?php if ($editRole['role_slug'] === 'super_admin'): ?>
            <div class="alert alert-info d-flex gap-2">
              <i class="bi bi-info-circle-fill"></i>
              Super Admin has all permissions (wildcard). No need to assign individually.
            </div>
          <?php else: ?>

          <!-- Select all toggle -->
          <div class="mb-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(true)">Select All</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="toggleAll(false)">Deselect All</button>
          </div>

          <?php foreach ($permGroups as $module => $perms): ?>
          <div class="mb-3">
            <div class="form-section-title mt-0 text-capitalize"><?= e(ucwords(str_replace('_',' ',$module))) ?></div>
            <div class="row g-2">
              <?php foreach ($perms as $perm): ?>
              <div class="col-md-6">
                <div class="form-check">
                  <input type="checkbox" class="form-check-input perm-cb"
                         name="perm_ids[]" id="perm_<?= $perm['id'] ?>"
                         value="<?= $perm['id'] ?>"
                         <?= in_array((string)$perm['id'], $currentPerms) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="perm_<?= $perm['id'] ?>">
                    <span class="fw-600 small"><?= e($perm['label'] ?? $perm['permission_key']) ?></span><br>
                    <code class="text-muted" style="font-size:.7rem;"><?= e($perm['permission_key']) ?></code>
                  </label>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>

          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Save Permissions
          </button>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <?php else: ?>
    <div class="card">
      <div class="card-body">
        <div class="empty-state">
          <i class="bi bi-shield-shaded"></i>
          <p>Select a role from the left to manage its permissions.</p>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- New Role Modal -->
<div class="modal fade" id="newRoleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_role">
        <div class="modal-header">
          <h5 class="modal-title">Create New Role</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Role Name <span class="text-danger">*</span></label>
            <input type="text" name="role_name" class="form-control" placeholder="e.g. Science Teacher" required
                   oninput="this.form.role_slug.value = this.value.toLowerCase().replace(/\s+/g,'_')">
          </div>
          <div class="mb-3">
            <label class="form-label">Role Slug <small class="text-muted">(auto-generated)</small></label>
            <input type="text" name="role_slug" class="form-control" placeholder="science_teacher" pattern="[a-z0-9_]+">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Role</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleAll(state) {
  document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = state);
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
