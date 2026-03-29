<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('member');
$user = currentUser();

$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];

if ($user['role'] !== 'admin') {
    $where[] = 'p.id IN (SELECT project_id FROM project_members WHERE user_id=?)';
    $params[] = $user['id'];
}
if ($status) { $where[] = 'p.status=?'; $params[] = $status; }
if ($search)  { $where[] = '(p.name LIKE ? OR p.client_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$projects = dbFetchAll(
    "SELECT p.*, u.full_name as creator,
     (SELECT COUNT(*) FROM tasks WHERE project_id=p.id) as task_count,
     (SELECT COUNT(*) FROM tasks WHERE project_id=p.id AND status='done') as done_count,
     (SELECT COUNT(*) FROM project_members WHERE project_id=p.id) as member_count
     FROM projects p JOIN users u ON u.id=p.created_by
     WHERE " . implode(' AND ', $where) . "
     ORDER BY p.updated_at DESC",
    $params
);

layoutStart('Projects', 'projects');
$statuses = ['planning','active','on_hold','completed','archived'];
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Projects</h1>
        <p class="page-subtitle"><?= count($projects) ?> project<?= count($projects) !== 1 ? 's' : '' ?></p>
    </div>
    <?php if ($user['role'] === 'admin'): ?>
    <a href="<?= BASE_URL ?>/modules/projects/create.php" class="btn btn-primary">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Project
    </a>
    <?php endif ?>
</div>

<!-- Filters -->
<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search projects..." class="form-control" style="max-width:220px">
    <select name="status" class="form-control" style="max-width:160px" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <?php foreach ($statuses as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
        <?php endforeach ?>
    </select>
    <button class="btn btn-secondary">Search</button>
    <?php if ($status || $search): ?><a href="?" class="btn btn-ghost">Clear</a><?php endif ?>
</form>

<?php if ($projects): ?>
<div class="projects-grid">
<?php foreach ($projects as $p):
    $pct = $p['task_count'] > 0 ? round($p['done_count'] / $p['task_count'] * 100) : 0; ?>
<div class="project-card">
    <div class="project-card-header">
        <a href="<?= BASE_URL ?>/modules/projects/view.php?id=<?= $p['id'] ?>" class="project-card-title"><?= e($p['name']) ?></a>
        <span class="badge badge-<?= e($p['status']) ?>"><?= e(ucwords(str_replace('_',' ',$p['status']))) ?></span>
    </div>
    <?php if ($p['client_name']): ?>
    <div class="project-card-client"><?= e($p['client_name']) ?></div>
    <?php endif ?>
    <?php if ($p['description']): ?>
    <p style="font-size:.8125rem;color:var(--text-muted);margin-bottom:10px;-webkit-line-clamp:2;display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden"><?= e($p['description']) ?></p>
    <?php endif ?>
    <?php if ($p['task_count'] > 0): ?>
    <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:.75rem;color:var(--text-muted);margin-bottom:4px">
            <span>Progress</span><span><?= $pct ?>%</span>
        </div>
        <div class="progress"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
    </div>
    <?php endif ?>
    <div class="project-card-footer">
        <div style="display:flex;gap:12px;font-size:.75rem;color:var(--text-muted)">
            <span><?= $p['task_count'] ?> task<?= $p['task_count'] !== '1' ? 's' : '' ?></span>
            <span><?= $p['member_count'] ?> member<?= $p['member_count'] !== '1' ? 's' : '' ?></span>
            <?php if ($p['due_date']): ?>
            <span>Due <?= date('M j, Y', strtotime($p['due_date'])) ?></span>
            <?php endif ?>
        </div>
        <div style="display:flex;gap:6px">
            <?php if ($user['role'] === 'admin'): ?>
            <a href="<?= BASE_URL ?>/modules/projects/edit.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Edit">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </a>
            <?php endif ?>
            <a href="<?= BASE_URL ?>/modules/projects/view.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="View">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </a>
        </div>
    </div>
</div>
<?php endforeach ?>
</div>
<?php else: ?>
<div class="card"><div class="empty-state">
    <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
    <p>No projects found.</p>
    <?php if ($user['role'] === 'admin'): ?>
    <a href="<?= BASE_URL ?>/modules/projects/create.php" class="btn btn-primary" style="margin-top:12px">Create your first project</a>
    <?php endif ?>
</div></div>
<?php endif ?>

<?php layoutEnd(); ?>
