<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/includes/layout.php';

requireRole('member');
$user = currentUser();

$q     = trim($_GET['q'] ?? '');
$tasks = $projects = $updates = $meetings = [];

if (strlen($q) >= 2) {
    $safe = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q);
    $like = '%' . $safe . '%';

    // Tasks
    if ($user['role'] === 'admin') {
        $tasks = dbFetchAll(
            "SELECT t.id, t.title, t.status, t.priority, p.name as project_name
             FROM tasks t JOIN projects p ON p.id=t.project_id
             WHERE t.title LIKE ? OR t.description LIKE ?
             ORDER BY t.updated_at DESC LIMIT 20",
            [$like, $like]
        );
    } else {
        $tasks = dbFetchAll(
            "SELECT DISTINCT t.id, t.title, t.status, t.priority, p.name as project_name
             FROM tasks t JOIN projects p ON p.id=t.project_id
             LEFT JOIN task_assignees ta ON ta.task_id=t.id
             WHERE (t.title LIKE ? OR t.description LIKE ?)
               AND (t.created_by=? OR ta.user_id=?)
             ORDER BY t.updated_at DESC LIMIT 20",
            [$like, $like, $user['id'], $user['id']]
        );
    }

    // Projects
    if ($user['role'] === 'admin') {
        $projects = dbFetchAll(
            "SELECT id, name, status, client_name FROM projects
             WHERE name LIKE ? OR description LIKE ? OR client_name LIKE ?
             ORDER BY name LIMIT 10",
            [$like, $like, $like]
        );
    } else {
        $projects = dbFetchAll(
            "SELECT p.id, p.name, p.status, p.client_name FROM projects p
             JOIN project_members pm ON pm.project_id=p.id
             WHERE pm.user_id=? AND (p.name LIKE ? OR p.description LIKE ? OR p.client_name LIKE ?)
             ORDER BY p.name LIMIT 10",
            [$user['id'], $like, $like, $like]
        );
    }

    // Updates (all users can see the feed)
    $updates = dbFetchAll(
        "SELECT u.id, u.message, u.type, u.created_at, usr.full_name
         FROM updates u JOIN users usr ON usr.id=u.user_id
         WHERE u.message LIKE ?
         ORDER BY u.created_at DESC LIMIT 10",
        [$like]
    );

    // Meetings
    if ($user['role'] === 'admin') {
        $meetings = dbFetchAll(
            "SELECT m.id, m.title, m.meeting_date, m.status, p.name as project_name
             FROM meetings m JOIN projects p ON p.id=m.project_id
             WHERE m.title LIKE ?
             ORDER BY m.meeting_date DESC LIMIT 10",
            [$like]
        );
    } else {
        $meetings = dbFetchAll(
            "SELECT DISTINCT m.id, m.title, m.meeting_date, m.status, p.name as project_name
             FROM meetings m JOIN projects p ON p.id=m.project_id
             JOIN project_members pm ON pm.project_id=m.project_id
             WHERE pm.user_id=? AND m.title LIKE ?
             ORDER BY m.meeting_date DESC LIMIT 10",
            [$user['id'], $like]
        );
    }
}

$total = count($tasks) + count($projects) + count($updates) + count($meetings);
layoutStart('Search', '');
?>
<div class="page-header">
    <h1 class="page-title">Search</h1>
</div>

<form method="GET" style="margin-bottom:24px">
    <div style="display:flex;gap:8px;max-width:540px">
        <input name="q" class="form-control" value="<?= e($q) ?>"
               placeholder="Search tasks, projects, updates, meetings…" autofocus>
        <button type="submit" class="btn btn-primary">Search</button>
    </div>
</form>

<?php if ($q && strlen($q) < 2): ?>
<p style="color:var(--text-muted)">Please enter at least 2 characters.</p>
<?php elseif ($q && !$total): ?>
<div class="empty-state"><p>No results for "<?= e($q) ?>".</p></div>
<?php elseif ($q): ?>
<p style="margin-bottom:20px;color:var(--text-muted);font-size:.875rem">
    <?= $total ?> result<?= $total !== 1 ? 's' : '' ?> for "<strong><?= e($q) ?></strong>"
</p>

<?php if ($tasks): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">Tasks (<?= count($tasks) ?>)</span></div>
    <div class="table-wrap"><table>
    <?php foreach ($tasks as $t): ?>
    <tr onclick="location='<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>'" style="cursor:pointer">
        <td><a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>" style="font-weight:500;color:var(--text)"><?= e($t['title']) ?></a></td>
        <td style="font-size:.8125rem;color:var(--text-muted)"><?= e($t['project_name']) ?></td>
        <td><span class="badge badge-<?= $t['status'] ?>"><?= ucwords(str_replace('_',' ',$t['status'])) ?></span></td>
        <td><span class="badge badge-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
    </tr>
    <?php endforeach ?>
    </table></div>
</div>
<?php endif ?>

<?php if ($projects): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">Projects (<?= count($projects) ?>)</span></div>
    <div class="table-wrap"><table>
    <?php foreach ($projects as $p): ?>
    <tr onclick="location='<?= BASE_URL ?>/modules/projects/view.php?id=<?= $p['id'] ?>'" style="cursor:pointer">
        <td><a href="<?= BASE_URL ?>/modules/projects/view.php?id=<?= $p['id'] ?>" style="font-weight:500;color:var(--text)"><?= e($p['name']) ?></a></td>
        <td style="font-size:.8125rem;color:var(--text-muted)"><?= e($p['client_name']) ?></td>
        <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span></td>
    </tr>
    <?php endforeach ?>
    </table></div>
</div>
<?php endif ?>

<?php if ($updates): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">Updates (<?= count($updates) ?>)</span></div>
    <?php foreach ($updates as $upd): ?>
    <div class="update-item">
        <div class="update-content">
            <div class="update-message"><?= nl2br(e($upd['message'])) ?></div>
            <div class="update-meta">
                <strong><?= e($upd['full_name']) ?></strong>
                · <?= date('M j, g:i A', strtotime($upd['created_at'])) ?>
            </div>
        </div>
    </div>
    <?php endforeach ?>
</div>
<?php endif ?>

<?php if ($meetings): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">Meetings (<?= count($meetings) ?>)</span></div>
    <div class="table-wrap"><table>
    <?php foreach ($meetings as $m): ?>
    <tr onclick="location='<?= BASE_URL ?>/modules/meetings/view.php?id=<?= $m['id'] ?>'" style="cursor:pointer">
        <td><a href="<?= BASE_URL ?>/modules/meetings/view.php?id=<?= $m['id'] ?>" style="font-weight:500;color:var(--text)"><?= e($m['title']) ?></a></td>
        <td style="font-size:.8125rem;color:var(--text-muted)"><?= e($m['project_name']) ?></td>
        <td style="font-size:.8125rem"><?= date('M j, Y', strtotime($m['meeting_date'])) ?></td>
        <td><span class="badge badge-<?= $m['status'] ?>"><?= ucfirst($m['status']) ?></span></td>
    </tr>
    <?php endforeach ?>
    </table></div>
</div>
<?php endif ?>

<?php endif ?>
<?php layoutEnd(); ?>
