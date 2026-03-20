<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/layout.php';

$pdo = getPDO();
$msg = $err = '';
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$isSuperadmin = ($_SESSION['admin_role'] ?? '') === 'superadmin';

function requireSuperadmin(): void {
    if (($_SESSION['admin_role'] ?? '') !== 'superadmin') {
        http_response_code(403);
        exit('Access denied. Superadmin required.');
    }
}

// DELETE
if ($action === 'delete' && $id) {
    requireSuperadmin();
    verifyCsrf();
    if ($id === (int)($_SESSION['admin_id'] ?? 0)) {
        $err = 'You cannot delete your own account.';
    } else {
        $superadminCount = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role='superadmin'")->fetchColumn();
        $stmt = $pdo->prepare("SELECT role FROM admin_users WHERE id=?");
        $stmt->execute([$id]);
        $targetRole = $stmt->fetchColumn();
        if ($targetRole === 'superadmin' && (int)$superadminCount <= 1) {
            $err = 'Cannot delete: at least one superadmin must exist.';
        } else {
            $pdo->prepare("DELETE FROM admin_users WHERE id=?")->execute([$id]);
            header('Location: ' . BASE_URL . '/admin/crud/users.php?msg=deleted'); exit;
        }
    }
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    requireSuperadmin();
    verifyCsrf();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'editor';

    if (!$name) { $err = 'Name is required.'; }
    elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $err = 'Valid email is required.'; }
    elseif ($action === 'add' && !$password) { $err = 'Password is required.'; }
    elseif ($password && strlen($password) < 6) { $err = 'Password must be at least 6 characters.'; }
    else {
        $existingEmail = $pdo->prepare("SELECT id FROM admin_users WHERE email=? AND id!=?");
        $existingEmail->execute([$email, $id]);
        if ($existingEmail->fetchColumn()) { $err = 'Email already exists.'; }
        else {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($action === 'add') {
                    $pdo->prepare("INSERT INTO admin_users (name,email,password_hash,role) VALUES (?,?,?,?)")
                        ->execute([$name,$email,$hash,$role]);
                } else {
                    $pdo->prepare("UPDATE admin_users SET name=?,email=?,password_hash=?,role=? WHERE id=?")
                        ->execute([$name,$email,$hash,$role,$id]);
                }
            } else {
                if ($action === 'add') {
                    $pdo->prepare("INSERT INTO admin_users (name,email,role) VALUES (?,?,?)")
                        ->execute([$name,$email,$role]);
                } else {
                    $pdo->prepare("UPDATE admin_users SET name=?,email=?,role=? WHERE id=?")
                        ->execute([$name,$email,$role,$id]);
                }
            }
            header('Location: ' . BASE_URL . '/admin/crud/users.php?msg=saved'); exit;
        }
    }
}

$editing = null;
if ($action === 'edit' && $id) {
    $s = $pdo->prepare("SELECT * FROM admin_users WHERE id=?"); $s->execute([$id]);
    $editing = $s->fetch();
    if (!$editing) { header('Location: ' . BASE_URL . '/admin/crud/users.php'); exit; }
}
if (isset($_GET['msg'])) $msg = $_GET['msg'] === 'saved' ? 'User saved.' : 'User deleted.';

adminOpen('Admin Users', 'users');
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<?php if (in_array($action,['add','edit'])): ?>
<div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;max-width:600px;">
  <h2 style="font-family:var(--ff-heading);font-size:1.4rem;margin-bottom:1.5rem;">
    <?= $action === 'add' ? 'Add Admin User' : 'Edit Admin User' ?>
  </h2>
  <form method="POST" novalidate>
    <?= csrfField() ?>
    <div class="form-group">
      <label class="form-label">Full Name *</label>
      <input class="form-control" name="name" required value="<?= e($name ?? $editing['name'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Email *</label>
      <input class="form-control" type="email" name="email" required value="<?= e($email ?? $editing['email'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Password <?= $action === 'add' ? '*' : '(leave blank to keep current)' ?></label>
      <input class="form-control" type="password" name="password" <?= $action === 'add' ? 'required' : '' ?>>
    </div>
    <div class="form-group">
      <label class="form-label">Role</label>
      <select class="form-control" name="role">
        <option value="editor" <?= ($role ?? $editing['role'] ?? '') === 'editor' ? 'selected' : '' ?>>Editor</option>
        <option value="superadmin" <?= ($role ?? $editing['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
      </select>
    </div>
    <div style="display:flex;gap:1rem;">
      <button type="submit" class="btn btn-primary">Save</button>
      <a href="<?= BASE_URL ?>/admin/crud/users.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<?php if ($isSuperadmin): ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1.25rem;">
  <a href="?action=add" class="btn btn-primary">+ Add Admin User</a>
</div>
<?php endif; ?>
<table class="admin-table">
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
  <tbody>
  <?php
  $rows = $pdo->query("SELECT * FROM admin_users ORDER BY id")->fetchAll();
  foreach ($rows as $r):
    $isCurrent = $r['id'] === (int)($_SESSION['admin_id'] ?? 0);
  ?>
  <tr>
    <td><strong><?= e($r['name']) ?><?= $isCurrent ? ' <span style="font-size:.7rem;color:var(--clr-gold);">(you)</span>' : '' ?></strong></td>
    <td style="color:var(--clr-muted);font-size:.82rem;"><?= e($r['email']) ?></td>
    <td><span class="badge <?= $r['role'] === 'superadmin' ? 'badge-warning' : 'badge-info' ?>"><?= ucfirst(e($r['role'])) ?></span></td>
    <td>
      <?php if ($isSuperadmin): ?>
      <a href="?action=edit&id=<?= $r['id'] ?>" style="color:var(--clr-dark);font-weight:600;font-size:.82rem;margin-right:.75rem;">Edit</a>
      <?php if (!$isCurrent): ?>
      <form method="POST" action="?action=delete&id=<?= $r['id'] ?>" style="display:inline;" onsubmit="return confirm('Delete this user?')">
        <?= csrfField() ?>
        <button type="submit" style="color:var(--clr-crimson);font-weight:600;font-size:.82rem;">Delete</button>
      </form>
      <?php endif; ?>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php adminClose(); ?>
