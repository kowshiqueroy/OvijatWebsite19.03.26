<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('member');
$user = currentUser();

$filterStatus  = $_GET['status'] ?? '';
$filterProject = (int)($_GET['project_id'] ?? 0);

$where  = ['1=1'];
$params = [];

if ($user['role'] !== 'admin') {
    $where[] = 'm.id IN (SELECT meeting_id FROM meeting_attendees WHERE user_id=?)';
    $params[] = $user['id'];
}
if ($filterStatus)  { $where[] = 'm.status=?'; $params[] = $filterStatus; }
if ($filterProject) { $where[] = 'm.project_id=?'; $params[] = $filterProject; }

$meetings = dbFetchAll(
    "SELECT m.*, p.name as project_name, u.full_name as creator,
     (SELECT COUNT(*) FROM meeting_attendees WHERE meeting_id=m.id) as attendee_count
     FROM meetings m
     LEFT JOIN projects p ON p.id=m.project_id
     JOIN users u ON u.id=m.created_by
     WHERE " . implode(' AND ', $where) . "
     ORDER BY m.meeting_date DESC",
    $params
);

$projectsForFilter = $user['role'] === 'admin'
    ? dbFetchAll("SELECT id, name FROM projects ORDER BY name")
    : dbFetchAll("SELECT p.id, p.name FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? ORDER BY p.name", [$user['id']]);

layoutStart('Meetings', 'meetings');
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Meetings</h1>
        <p class="page-subtitle"><?= count($meetings) ?> meeting<?= count($meetings) !== 1 ? 's' : '' ?></p>
    </div>
    <div style="display:flex;gap:8px">
    <a href="<?= BASE_URL ?>/export.php?type=meetings<?= $filterProject ? '&project_id='.$filterProject : '' ?>" class="btn btn-secondary" title="Export as CSV">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        CSV
    </a>
    <a href="<?= BASE_URL ?>/modules/meetings/create.php" class="btn btn-primary">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Meeting
    </a>
    </div>
</div>

<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
    <select name="project_id" class="form-control" style="max-width:200px" onchange="this.form.submit()">
        <option value="">All Projects</option>
        <?php foreach ($projectsForFilter as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $filterProject === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach ?>
    </select>
    <select name="status" class="form-control" style="max-width:150px" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <?php foreach (['scheduled','done','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach ?>
    </select>
    <?php if ($filterStatus || $filterProject): ?><a href="?" class="btn btn-ghost">Clear</a><?php endif ?>
</form>

<div class="card">
<div class="table-wrap">
<table>
<thead>
<tr><th>Title</th><th>Project</th><th>Date</th><th>Status</th><th>Attendees</th><th>Recurrence</th><th></th></tr>
</thead>
<tbody>
<?php if ($meetings): foreach ($meetings as $m): ?>
<tr>
    <td><a href="<?= BASE_URL ?>/modules/meetings/view.php?id=<?= $m['id'] ?>" style="font-weight:500;color:var(--text)"><?= e($m['title']) ?></a></td>
    <td style="font-size:.8125rem"><?= $m['project_name'] ? e($m['project_name']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
    <td style="font-size:.8125rem;white-space:nowrap"><?= date('M j, Y · g:i A', strtotime($m['meeting_date'])) ?></td>
    <td><span class="badge badge-<?= e($m['status']) === 'done' ? 'done-meet' : e($m['status']) ?>"><?= ucfirst($m['status']) ?></span></td>
    <td style="font-size:.8125rem"><?= $m['attendee_count'] ?></td>
    <td style="font-size:.8125rem"><?= $m['recurrence'] !== 'none' ? ucfirst($m['recurrence']) : '—' ?></td>
    <td>
        <div class="table-actions">
            <a href="<?= BASE_URL ?>/modules/meetings/view.php?id=<?= $m['id'] ?>" class="btn btn-ghost btn-sm btn-icon">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </a>
        </div>
    </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="7"><div class="empty-state"><p>No meetings found.</p></div></td></tr>
<?php endif ?>
</tbody>
</table>
</div>
</div>

<?php layoutEnd(); ?>
