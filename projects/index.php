<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/includes/layout.php';

requireRole('member');
$user = currentUser();
validateCsrf();

// Stats
if ($user['role'] === 'admin') {
    $projects    = dbFetchAll("SELECT * FROM projects ORDER BY updated_at DESC LIMIT 6");
    $totalProj   = (int)dbFetch("SELECT COUNT(*) c FROM projects")['c'];
    $activeTasks = (int)dbFetch("SELECT COUNT(*) c FROM tasks WHERE status IN ('todo','in_progress','review')")['c'];
    $totalUsers  = (int)dbFetch("SELECT COUNT(*) c FROM users WHERE is_active=1")['c'];
    
    $recentUpdates = dbFetchAll(
        "SELECT u.*, usr.full_name, p.name as project_name FROM updates u
         JOIN users usr ON usr.id=u.user_id
         LEFT JOIN projects p ON p.id=u.project_id
         ORDER BY u.created_at DESC LIMIT 5"
    );
} else {
    $projects    = dbFetchAll("SELECT p.* FROM projects p INNER JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? ORDER BY p.updated_at DESC LIMIT 6", [$user['id']]);
    $totalProj   = count($projects);
    $activeTasks = (int)dbFetch("SELECT COUNT(*) c FROM tasks t INNER JOIN task_assignees ta ON ta.task_id=t.id WHERE ta.user_id=? AND t.status IN ('todo','in_progress','review')", [$user['id']])['c'];
    $totalUsers  = null;
    
    // Fixed privacy leak: Only show updates for projects I am a member of (or general updates)
    $recentUpdates = dbFetchAll(
        "SELECT u.*, usr.full_name, p.name as project_name FROM updates u
         JOIN users usr ON usr.id=u.user_id
         LEFT JOIN projects p ON p.id=u.project_id
         WHERE u.project_id IS NULL OR u.project_id IN (SELECT project_id FROM project_members WHERE user_id=?)
         ORDER BY u.created_at DESC LIMIT 5",
        [$user['id']]
    );
}

$myTasks = dbFetchAll(
    "SELECT t.*, p.name as project_name FROM tasks t
     INNER JOIN task_assignees ta ON ta.task_id=t.id
     INNER JOIN projects p ON p.id=t.project_id
     WHERE ta.user_id=? AND t.status != 'done'
     ORDER BY ISNULL(t.due_date), t.due_date ASC, t.priority DESC
     LIMIT 8",
    [$user['id']]
);

$upcomingMeetings = dbFetchAll(
    "SELECT m.* FROM meetings m
     INNER JOIN meeting_attendees ma ON ma.meeting_id=m.id
     WHERE ma.user_id=? AND m.status='scheduled' AND m.meeting_date >= NOW()
     ORDER BY m.meeting_date ASC LIMIT 5",
    [$user['id']]
);

// Due-soon: tasks due today, overdue, or due within next 3 days
$dueSoon = dbFetchAll(
    "SELECT t.*, p.name as project_name FROM tasks t
     INNER JOIN task_assignees ta ON ta.task_id=t.id
     INNER JOIN projects p ON p.id=t.project_id
     WHERE ta.user_id=? AND t.status != 'done'
       AND t.due_date IS NOT NULL AND t.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
     ORDER BY t.due_date ASC LIMIT 10",
    [$user['id']]
);
$overdueCount = count(array_filter($dueSoon, fn($t) => $t['due_date'] < date('Y-m-d')));
$dueTodayCount = count(array_filter($dueSoon, fn($t) => $t['due_date'] === date('Y-m-d')));

layoutStart('Dashboard', 'dashboard');

$priorityColors = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'];
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Welcome back, <?= e($user['full_name']) ?></p>
    </div>
    <?php if ($user['role'] === 'admin'): ?>
    <a href="<?= BASE_URL ?>/modules/projects/create.php" class="btn btn-primary">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Project
    </a>
    <?php endif ?>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
        <div class="stat-value"><?= $totalProj ?></div>
        <div class="stat-label"><?= $user['role'] === 'admin' ? 'Total' : 'My' ?> Projects</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-icon"><svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
        <div class="stat-value"><?= $activeTasks ?></div>
        <div class="stat-label">Active Tasks</div>
    </div>
    <?php if ($totalUsers !== null): ?>
    <div class="stat-card stat-purple">
        <div class="stat-icon"><svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-label">Team Members</div>
    </div>
    <?php endif ?>
    <div class="stat-card stat-success">
        <div class="stat-icon"><svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg></div>
        <div class="stat-value"><?= count($upcomingMeetings) ?></div>
        <div class="stat-label">Upcoming Meetings</div>
    </div>
    <div class="stat-card <?= $overdueCount > 0 ? 'stat-danger' : 'stat-success' ?>">
        <div class="stat-icon"><svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
        <div class="stat-value" style="color:<?= $overdueCount > 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= $overdueCount ?></div>
        <div class="stat-label">Overdue Tasks</div>
    </div>
</div>

<?php if ($dueSoon): ?>
<div class="card" style="margin-bottom:20px;border-color:<?= $overdueCount > 0 ? 'var(--danger)' : 'var(--warning)' ?>;border-left-width:4px">
    <div class="card-header" style="padding:12px 16px">
        <span class="card-title" style="font-size:.875rem;display:flex;align-items:center;gap:8px">
            <?php if ($overdueCount > 0): ?>
            <span style="color:var(--danger)">⚠ <?= $overdueCount ?> overdue<?= $dueTodayCount > 0 ? ', '.$dueTodayCount.' due today' : '' ?></span>
            <?php else: ?>
            <span style="color:var(--warning)">🕐 <?= $dueTodayCount ?> due today<?= count($dueSoon) > $dueTodayCount ? ', '.( count($dueSoon) - $dueTodayCount).' due in next 3 days' : '' ?></span>
            <?php endif ?>
        </span>
        <a href="<?= BASE_URL ?>/modules/tasks/index.php" class="btn btn-ghost btn-sm">View all tasks</a>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:0">
    <?php foreach ($dueSoon as $t):
        $isOverdue = $t['due_date'] < date('Y-m-d');
        $isToday   = $t['due_date'] === date('Y-m-d');
    ?>
    <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>"
       style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);color:var(--text);width:100%;text-decoration:none">
        <span class="badge badge-<?= $t['status'] ?>" style="flex-shrink:0"><?= ucwords(str_replace('_',' ',$t['status'])) ?></span>
        <div style="flex:1;min-width:0">
            <div style="font-size:.875rem;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($t['title']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= e($t['project_name']) ?></div>
        </div>
        <span style="font-size:.75rem;white-space:nowrap;font-weight:600;color:<?= $isOverdue ? 'var(--danger)' : ($isToday ? 'var(--warning)' : 'var(--text-muted)') ?>">
            <?= $isOverdue ? 'Overdue · '.date('M j', strtotime($t['due_date'])) : ($isToday ? 'Today' : 'In '.ceil((strtotime($t['due_date'])-time())/86400).'d') ?>
        </span>
        <span class="badge badge-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span>
    </a>
    <?php endforeach ?>
    </div>
</div>
<?php endif ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start" class="dashboard-grid">

<!-- My Tasks -->
<div class="card">
    <div class="card-header">
        <span class="card-title">My Tasks</span>
        <a href="<?= BASE_URL ?>/modules/tasks/index.php" class="btn btn-ghost btn-sm">View all</a>
    </div>
    <div style="padding:0">
    <?php if ($myTasks): foreach ($myTasks as $t):
        $overdue = $t['due_date'] && $t['due_date'] < date('Y-m-d') && $t['status'] !== 'done'; ?>
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
            <span class="badge badge-<?= e($t['status']) ?>" style="flex-shrink:0"><?= e(str_replace('_',' ',ucfirst($t['status']))) ?></span>
            <div style="flex:1;min-width:0">
                <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>" style="font-size:.875rem;font-weight:500;color:var(--text)"><?= e($t['title']) ?></a>
                <div style="font-size:.75rem;color:var(--text-muted)"><?= e($t['project_name']) ?></div>
            </div>
            <?php if ($t['due_date']): ?>
            <span style="font-size:.75rem;white-space:nowrap" class="<?= $overdue ? 'due-overdue' : '' ?>">
                <?= $overdue ? 'Overdue' : date('M j', strtotime($t['due_date'])) ?>
            </span>
            <?php endif ?>
            <span class="badge badge-<?= e($t['priority']) ?>"><?= e(ucfirst($t['priority'])) ?></span>
        </div>
    <?php endforeach; else: ?>
    <div class="empty-state"><p>No active tasks assigned to you.</p></div>
    <?php endif ?>
    </div>
</div>

<!-- Right column -->
<div style="display:flex;flex-direction:column;gap:20px">

<!-- Recent Projects -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Projects</span>
        <a href="<?= BASE_URL ?>/modules/projects/index.php" class="btn btn-ghost btn-sm">View all</a>
    </div>
    <div style="padding:0">
    <?php if ($projects): foreach ($projects as $p): ?>
        <a href="<?= BASE_URL ?>/modules/projects/view.php?id=<?= $p['id'] ?>" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--border);color:var(--text)">
            <div>
                <div style="font-size:.875rem;font-weight:500"><?= e($p['name']) ?></div>
                <?php if ($p['client_name']): ?><div style="font-size:.75rem;color:var(--text-muted)"><?= e($p['client_name']) ?></div><?php endif ?>
            </div>
            <span class="badge badge-<?= e($p['status']) ?>"><?= e(ucwords(str_replace('_',' ',$p['status']))) ?></span>
        </a>
    <?php endforeach; else: ?>
    <div class="empty-state"><p>No projects yet.</p></div>
    <?php endif ?>
    </div>
</div>

<!-- Upcoming Meetings -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Upcoming Meetings</span>
        <a href="<?= BASE_URL ?>/modules/meetings/index.php" class="btn btn-ghost btn-sm">View all</a>
    </div>
    <div style="padding:0">
    <?php if ($upcomingMeetings): foreach ($upcomingMeetings as $m): ?>
        <a href="<?= BASE_URL ?>/modules/meetings/view.php?id=<?= $m['id'] ?>" style="display:block;padding:12px 16px;border-bottom:1px solid var(--border);color:var(--text)">
            <div style="font-size:.875rem;font-weight:500"><?= e($m['title']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= date('D, M j · g:i A', strtotime($m['meeting_date'])) ?></div>
        </a>
    <?php endforeach; else: ?>
    <div class="empty-state"><p>No upcoming meetings.</p></div>
    <?php endif ?>
    </div>
</div>

</div><!-- /right -->
</div><!-- /grid -->

<style>
@media(max-width:767px){.dashboard-grid{grid-template-columns:1fr!important}}
</style>

<?php layoutEnd(); ?>
