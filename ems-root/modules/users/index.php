<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Users';
$breadcrumbs = ['Users & Roles' => null, 'All Users' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['users.view']);

$pdo = db();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    if (has_permission('users.delete')) {
        $delId = int_param('user_id', 0, $_POST);
        if ($delId && $delId !== current_user_id()) {
            $pdo->prepare('UPDATE users SET status="archived" WHERE id=:id')->execute([':id' => $delId]);
            log_activity('archive_user', 'users', $delId);
            flash('success', 'User archived successfully.');
        }
    }
    header('Location: index.php');
    exit;
}

// Filters
$search  = trim($_GET['q'] ?? '');
$roleFilter = int_param('role_id', 0, $_GET);
$status  = $_GET['status'] ?? 'active';
$page    = max(1, int_param('page', 1, $_GET));

$where  = ['1=1'];
$params = [];
if ($search)  { $where[] = '(u.username LIKE :q OR u.full_name LIKE :q OR u.email LIKE :q)'; $params[':q'] = "%$search%"; }
if ($status !== 'all') { $where[] = 'u.status=:st'; $params[':st'] = $status; }
if ($roleFilter) { $where[] = 'EXISTS(SELECT 1 FROM user_roles ur2 WHERE ur2.user_id=u.id AND ur2.role_id=:rid)'; $params[':rid'] = $roleFilter; }

$whereStr = implode(' AND ', $where);

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM users u WHERE $whereStr")->execute($params) ?: 0;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pg = paginate($total, $page);

$users = $pdo->prepare(
    "SELECT u.id, u.username, u.full_name, u.email, u.phone, u.status, u.created_at,
            GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ', ') as roles
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     WHERE $whereStr
     GROUP BY u.id
     ORDER BY u.id DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$users->execute($params);
$users = $users->fetchAll();

$allRoles = $pdo->query('SELECT id, role_name FROM roles ORDER BY role_name')->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Users</h1>
  <?php if (has_permission('users.create')): ?>
    <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add User</a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small">Search</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Name, username, email…" value="<?= e($search) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small">Role</label>
        <select name="role_id" class="form-select form-select-sm">
          <option value="0">All Roles</option>
          <?php foreach ($allRoles as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $roleFilter === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['role_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
          <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
          <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card table-card">
  <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
    <span class="card-title">Users <span class="badge bg-secondary"><?= $total ?></span></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>#</th><th>Name</th><th>Username</th><th>Email</th><th>Roles</th><th>Status</th><th>Joined</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="8"><div class="empty-state"><i class="bi bi-people"></i><p>No users found</p></div></td></tr>
        <?php else: foreach ($users as $i => $u): ?>
        <tr>
          <td class="text-muted"><?= $pg['offset'] + $i + 1 ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="topbar-avatar" style="width:30px;height:30px;font-size:.75rem;">
                <?= strtoupper(substr($u['full_name'],0,1)) ?>
              </div>
              <span class="fw-600"><?= e($u['full_name']) ?></span>
            </div>
          </td>
          <td><code><?= e($u['username']) ?></code></td>
          <td><?= e($u['email'] ?? '—') ?></td>
          <td><?= e($u['roles'] ?? '—') ?></td>
          <td>
            <span class="badge-status badge-<?= $u['status'] === 'active' ? 'active' : ($u['status'] === 'suspended' ? 'rejected' : 'draft') ?>">
              <?= ucfirst(e($u['status'])) ?>
            </span>
          </td>
          <td><?= fmt_date($u['created_at'], 'd M Y') ?></td>
          <td>
            <div class="table-actions">
              <?php if (has_permission('users.edit')): ?>
                <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                  <i class="bi bi-pencil"></i>
                </a>
              <?php endif; ?>
              <?php if (has_permission('roles.manage')): ?>
                <a href="assign_roles.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-warning" title="Manage Roles">
                  <i class="bi bi-shield-check"></i>
                </a>
              <?php endif; ?>
              <?php if (has_permission('users.delete') && $u['id'] !== current_user_id()): ?>
                <form method="POST" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"
                          data-confirm="Archive user '<?= e($u['username']) ?>'? They can be restored later." title="Archive">
                    <i class="bi bi-archive"></i>
                  </button>
                </form>
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
      <?php for ($p = 1; $p <= $pg['total_pages']; $p++): ?>
        <li class="page-item <?= $p === $pg['page'] ? 'active' : '' ?>">
          <a class="page-link" href="?q=<?= urlencode($search) ?>&role_id=<?= $roleFilter ?>&status=<?= $status ?>&page=<?= $p ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
