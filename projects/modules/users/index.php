<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('admin');
$user = currentUser();

// Toggle active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    $uid = (int)$_POST['user_id'];
    if ($uid !== $user['id']) {
        $u = dbFetch("SELECT is_active FROM users WHERE id=?", [$uid]);
        if ($u) dbUpdate('users', ['is_active' => $u['is_active'] ? 0 : 1], ['id' => $uid]);
    }
    redirect(BASE_URL . '/modules/users/index.php');
}

$users = dbFetchAll(
    "SELECT u.*,
     (SELECT COUNT(*) FROM project_members WHERE user_id=u.id) as project_count,
     (SELECT COUNT(*) FROM task_assignees ta JOIN tasks t ON t.id=ta.task_id WHERE ta.user_id=u.id AND t.status!='done') as active_tasks
     FROM users u ORDER BY u.role, u.full_name"
);

layoutStart('Users', 'users');
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Users</h1>
        <p class="page-subtitle"><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= BASE_URL ?>/modules/users/create.php" class="btn btn-primary">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New User
    </a>
</div>

<div class="card">
<div class="table-wrap">
<table>
<thead>
<tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Projects</th><th>Active Tasks</th><th>Joined</th><th></th></tr>
</thead>
<tbody>
<?php foreach ($users as $u): ?>
<tr>
    <td>
        <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar avatar-sm" style="background:<?= avatarColor($u['full_name']) ?>"><?= userInitials($u['full_name']) ?></div>
            <span style="font-weight:500"><?= e($u['full_name']) ?></span>
        </div>
    </td>
    <td style="font-size:.875rem;color:var(--text-muted)"><?= e($u['username']) ?></td>
    <td><span class="badge <?= $u['role'] === 'admin' ? 'badge-active' : 'badge-planning' ?>"><?= ucfirst($u['role']) ?></span></td>
    <td><span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-archived' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
    <td style="font-size:.875rem"><?= $u['project_count'] ?></td>
    <td style="font-size:.875rem"><?= $u['active_tasks'] ?></td>
    <td style="font-size:.8125rem;color:var(--text-muted)"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
    <td>
        <div class="table-actions">
            <a href="<?= BASE_URL ?>/modules/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Edit">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </a>
            <?php if ($u['id'] !== $user['id']): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Toggle user status?')">
                <input type="hidden" name="toggle_active" value="1">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn btn-ghost btn-sm btn-icon" title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                    <?php if ($u['is_active']): ?>
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                    <?php else: ?>
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php endif ?>
                </button>
            </form>
            <?php endif ?>
            <a href="<?= BASE_URL ?>/modules/users/activity.php?id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Activity">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </a>
        </div>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
</div>

<?php layoutEnd(); ?>
