<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('admin');
$user = currentUser();
$id = (int)($_GET['id'] ?? 0);
$u = dbFetch("SELECT * FROM users WHERE id=?", [$id]);
if (!$u) { flash('error', 'User not found.'); redirect(BASE_URL . '/modules/users/index.php'); }

$updates    = dbFetchAll("SELECT u.*, p.name as project_name FROM updates u LEFT JOIN projects p ON p.id=u.project_id WHERE u.user_id=? ORDER BY u.created_at DESC LIMIT 50", [$id]);
$tasks      = dbFetchAll("SELECT t.*, p.name as project_name FROM task_assignees ta JOIN tasks t ON t.id=ta.task_id JOIN projects p ON p.id=t.project_id WHERE ta.user_id=? ORDER BY t.updated_at DESC LIMIT 20", [$id]);
$timeLogs   = dbFetchAll("SELECT tl.*, t.title FROM task_time_logs tl JOIN tasks t ON t.id=tl.task_id WHERE tl.user_id=? ORDER BY tl.logged_at DESC LIMIT 20", [$id]);
$totalHours = (float)(dbFetch("SELECT SUM(hours) s FROM task_time_logs WHERE user_id=?", [$id])['s'] ?? 0);

layoutStart(e($u['full_name']).' — Activity', 'users');
$typeIcons = ['task' => '✅', 'meeting' => '📅', 'project' => '📁', 'general' => '💬'];
?>
<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/modules/users/index.php" style="font-size:.875rem;color:var(--text-muted)">&larr; Users</a>
        <h1 class="page-title" style="margin-top:4px"><?= e($u['full_name']) ?></h1>
        <p class="page-subtitle">Activity overview</p>
    </div>
    <a href="<?= BASE_URL ?>/modules/users/edit.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">Edit User</a>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card"><div class="stat-value"><?= count($updates) ?></div><div class="stat-label">Updates</div></div>
    <div class="stat-card"><div class="stat-value"><?= count($tasks) ?></div><div class="stat-label">Assigned Tasks</div></div>
    <div class="stat-card"><div class="stat-value"><?= number_format($totalHours, 1) ?>h</div><div class="stat-label">Time Logged</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="activity-grid">
<div class="card">
    <div class="card-header"><span class="card-title">Assigned Tasks</span></div>
    <div style="padding:0">
    <?php if ($tasks): foreach ($tasks as $t): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border)">
        <span class="badge badge-<?= e($t['status']) ?>"><?= ucwords(str_replace('_',' ',$t['status'])) ?></span>
        <div style="flex:1">
            <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>" style="font-size:.875rem;color:var(--text);font-weight:500"><?= e($t['title']) ?></a>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= e($t['project_name']) ?></div>
        </div>
        <span class="badge badge-<?= e($t['priority']) ?>"><?= ucfirst($t['priority']) ?></span>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state" style="padding:20px"><p>No tasks assigned.</p></div>
    <?php endif ?>
    </div>
</div>

<div>
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">Time Logs</span></div>
    <div style="padding:0">
    <?php if ($timeLogs): foreach ($timeLogs as $l): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;border-bottom:1px solid var(--border)">
        <div>
            <div style="font-size:.875rem;font-weight:500"><?= e($l['title']) ?></div>
            <?php if ($l['note']): ?><div style="font-size:.75rem;color:var(--text-muted)"><?= e($l['note']) ?></div><?php endif ?>
        </div>
        <div style="text-align:right">
            <div style="font-weight:600"><?= $l['hours'] ?>h</div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= date('M j', strtotime($l['logged_at'])) ?></div>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state" style="padding:20px"><p>No time logged.</p></div>
    <?php endif ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Updates</span></div>
    <div style="padding:0">
    <?php if ($updates): foreach ($updates as $upd): ?>
    <div style="padding:10px 16px;border-bottom:1px solid var(--border)">
        <div style="font-size:.875rem"><?= $typeIcons[$upd['type']] ?> <?= e($upd['message']) ?></div>
        <div style="font-size:.75rem;color:var(--text-muted)"><?= $upd['project_name'] ? e($upd['project_name']).' · ' : '' ?><?= date('M j, g:i A', strtotime($upd['created_at'])) ?></div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state" style="padding:20px"><p>No updates.</p></div>
    <?php endif ?>
    </div>
</div>
</div>
</div>

<style>@media(max-width:767px){.activity-grid{grid-template-columns:1fr!important}}</style>
<?php layoutEnd(); ?>
