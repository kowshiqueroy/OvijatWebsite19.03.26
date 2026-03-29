<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../flash.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('member');
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $curPass  = $_POST['current_password'] ?? '';
    $newPass  = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$fullName) { flash('error', 'Full name required.'); redirect(BASE_URL . '/profile/index.php'); }

    $dbUser = dbFetch("SELECT * FROM users WHERE id=?", [$user['id']]);
    $data = ['full_name' => $fullName];

    if ($newPass || $curPass) {
        if (!password_verify($curPass, $dbUser['password_hash'])) { flash('error', 'Current password is incorrect.'); redirect(BASE_URL . '/profile/index.php'); }
        if ($newPass !== $confirm) { flash('error', 'New passwords do not match.'); redirect(BASE_URL . '/profile/index.php'); }
        if (strlen($newPass) < 6) { flash('error', 'Password must be at least 6 characters.'); redirect(BASE_URL . '/profile/index.php'); }
        $data['password_hash'] = password_hash($newPass, PASSWORD_DEFAULT);
    }

    dbUpdate('users', $data, ['id' => $user['id']]);
    $_SESSION['user']['full_name'] = $fullName;
    flash('success', 'Profile updated.');
    redirect(BASE_URL . '/profile/index.php');
}

$dbUser = dbFetch("SELECT * FROM users WHERE id=?", [$user['id']]);
$projects = dbFetchAll("SELECT p.*, pm.role as proj_role FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? ORDER BY p.updated_at DESC", [$user['id']]);
$assignedTasks = dbFetchAll("SELECT t.*, p.name as project_name FROM task_assignees ta JOIN tasks t ON t.id=ta.task_id JOIN projects p ON p.id=t.project_id WHERE ta.user_id=? AND t.status!='done' ORDER BY t.due_date ASC LIMIT 10", [$user['id']]);
$totalHours = (float)(dbFetch("SELECT SUM(hours) s FROM task_time_logs WHERE user_id=?", [$user['id']])['s'] ?? 0);

layoutStart('Profile', 'profile');
?>
<div class="page-header">
    <h1 class="page-title">My Profile</h1>
</div>

<div style="display:grid;grid-template-columns:380px 1fr;gap:20px;align-items:start" class="profile-grid">
<div>

<!-- Edit Profile -->
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">Account</span></div>
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border)">
            <div class="avatar avatar-lg" style="background:<?= avatarColor($dbUser['full_name']) ?>"><?= userInitials($dbUser['full_name']) ?></div>
            <div>
                <div style="font-weight:700;font-size:1.1rem"><?= e($dbUser['full_name']) ?></div>
                <div style="color:var(--text-muted);font-size:.875rem">@<?= e($dbUser['username']) ?> · <?= ucfirst($dbUser['role']) ?></div>
            </div>
        </div>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input name="full_name" class="form-control" value="<?= e($dbUser['full_name']) ?>" required>
            </div>
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
                <div style="font-size:.8125rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Change Password</div>
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input name="current_password" type="password" class="form-control" autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input name="new_password" type="password" class="form-control" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input name="confirm_password" type="password" class="form-control" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:8px">Save Changes</button>
        </form>
    </div>
</div>

<!-- Stats -->
<div class="card">
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="stat-card" style="box-shadow:none;border-color:var(--border)">
                <div class="stat-value"><?= count($projects) ?></div>
                <div class="stat-label">Projects</div>
            </div>
            <div class="stat-card" style="box-shadow:none;border-color:var(--border)">
                <div class="stat-value"><?= count($assignedTasks) ?></div>
                <div class="stat-label">Active Tasks</div>
            </div>
            <div class="stat-card" style="box-shadow:none;border-color:var(--border)">
                <div class="stat-value"><?= number_format($totalHours, 1) ?>h</div>
                <div class="stat-label">Time Logged
                    <a href="<?= BASE_URL ?>/export.php?type=timelogs" style="display:block;font-size:.7rem;color:var(--accent);margin-top:2px">Export CSV</a>
                </div>
            </div>
            <div class="stat-card" style="box-shadow:none;border-color:var(--border)">
                <div class="stat-value"><?= date('M Y', strtotime($dbUser['created_at'])) ?></div>
                <div class="stat-label">Member Since</div>
            </div>
        </div>
    </div>
</div>

</div>

<div>
<!-- My Projects -->
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">My Projects</span></div>
    <div style="padding:0">
    <?php if ($projects): foreach ($projects as $p): ?>
    <a href="<?= BASE_URL ?>/modules/projects/view.php?id=<?= $p['id'] ?>" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--border);color:var(--text)">
        <div>
            <div style="font-weight:500"><?= e($p['name']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= ucfirst($p['proj_role']) ?></div>
        </div>
        <span class="badge badge-<?= e($p['status']) ?>"><?= ucwords(str_replace('_',' ',$p['status'])) ?></span>
    </a>
    <?php endforeach; else: ?>
    <div class="empty-state" style="padding:20px"><p>Not assigned to any projects.</p></div>
    <?php endif ?>
    </div>
</div>

<!-- Active Tasks -->
<div class="card">
    <div class="card-header"><span class="card-title">My Active Tasks</span></div>
    <div style="padding:0">
    <?php if ($assignedTasks): foreach ($assignedTasks as $t):
        $overdue = $t['due_date'] && $t['due_date'] < date('Y-m-d'); ?>
    <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>" style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);color:var(--text)">
        <span class="badge badge-<?= e($t['status']) ?>"><?= ucwords(str_replace('_',' ',$t['status'])) ?></span>
        <div style="flex:1">
            <div style="font-size:.875rem;font-weight:500"><?= e($t['title']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= e($t['project_name']) ?></div>
        </div>
        <?php if ($t['due_date']): ?>
        <span style="font-size:.75rem;white-space:nowrap;color:<?= $overdue ? 'var(--danger)' : 'var(--text-muted)' ?>"><?= date('M j', strtotime($t['due_date'])) ?></span>
        <?php endif ?>
        <span class="badge badge-<?= e($t['priority']) ?>"><?= ucfirst($t['priority']) ?></span>
    </a>
    <?php endforeach; else: ?>
    <div class="empty-state" style="padding:20px"><p>No active tasks.</p></div>
    <?php endif ?>
    </div>
</div>
</div>
</div>

<style>@media(max-width:767px){.profile-grid{grid-template-columns:1fr!important}}</style>
<?php layoutEnd(); ?>
