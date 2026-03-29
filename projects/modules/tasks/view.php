<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('member');
$user = currentUser();
$id = (int)($_GET['id'] ?? 0);
$task = dbFetch("SELECT t.*, p.name as project_name, p.id as project_id, u.full_name as creator_name FROM tasks t JOIN projects p ON p.id=t.project_id JOIN users u ON u.id=t.created_by WHERE t.id=?", [$id]);
if (!$task) { flash('error', 'Task not found.'); redirect(BASE_URL . '/modules/tasks/index.php'); }

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $body = trim($_POST['body'] ?? '');
    if ($body) {
        dbInsert('task_comments', ['task_id' => $id, 'user_id' => $user['id'], 'body' => $body]);
    }
    redirect(BASE_URL . '/modules/tasks/view.php?id='.$id);
}
// Handle time log submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_time'])) {
    $hours = (float)($_POST['hours'] ?? 0);
    $note  = trim($_POST['note'] ?? '');
    $date  = $_POST['logged_at'] ?? date('Y-m-d');
    if ($hours > 0) {
        dbInsert('task_time_logs', ['task_id' => $id, 'user_id' => $user['id'], 'hours' => $hours, 'note' => $note ?: null, 'logged_at' => $date]);
    }
    redirect(BASE_URL . '/modules/tasks/view.php?id='.$id);
}
// Handle attachment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_link'])) {
    $label = trim($_POST['label'] ?? '');
    $url   = trim($_POST['url'] ?? '');
    if ($label && $url) {
        dbInsert('task_attachments', ['task_id' => $id, 'user_id' => $user['id'], 'label' => $label, 'url' => $url]);
    }
    redirect(BASE_URL . '/modules/tasks/view.php?id='.$id);
}
// Handle delete attachment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attach'])) {
    $aid = (int)$_POST['attach_id'];
    $att = dbFetch("SELECT * FROM task_attachments WHERE id=?", [$aid]);
    if ($att && ($user['role'] === 'admin' || $att['user_id'] === $user['id'])) {
        dbQuery("DELETE FROM task_attachments WHERE id=?", [$aid]);
    }
    redirect(BASE_URL . '/modules/tasks/view.php?id='.$id);
}

$assignees   = dbFetchAll("SELECT u.id, u.full_name FROM task_assignees ta JOIN users u ON u.id=ta.user_id WHERE ta.task_id=?", [$id]);
$comments    = dbFetchAll("SELECT tc.*, u.full_name FROM task_comments tc JOIN users u ON u.id=tc.user_id WHERE tc.task_id=? ORDER BY tc.created_at ASC", [$id]);
$timeLogs    = dbFetchAll("SELECT tl.*, u.full_name FROM task_time_logs tl JOIN users u ON u.id=tl.user_id WHERE tl.task_id=? ORDER BY tl.logged_at DESC", [$id]);
$attachments = dbFetchAll("SELECT ta.*, u.full_name FROM task_attachments ta JOIN users u ON u.id=ta.user_id WHERE ta.task_id=? ORDER BY ta.created_at DESC", [$id]);
$subtasks    = dbFetchAll("SELECT * FROM tasks WHERE parent_task_id=? ORDER BY status, priority DESC", [$id]);
$projectMembers = dbFetchAll("SELECT u.id, u.full_name, u.username FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=?", [$task['project_id']]);

$totalHours  = array_sum(array_column($timeLogs, 'hours'));
$overdue     = $task['due_date'] && $task['due_date'] < date('Y-m-d') && $task['status'] !== 'done';

layoutStart(e($task['title']), 'tasks');
$members_json = json_encode(array_map(fn($m) => ['id' => $m['id'], 'username' => strtolower(str_replace(' ','_',$m['full_name'])), 'name' => $m['full_name'], 'initials' => userInitials($m['full_name'])], $projectMembers));
?>
<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/modules/projects/view.php?id=<?= $task['project_id'] ?>" style="font-size:.875rem;color:var(--text-muted)">&larr; <?= e($task['project_name']) ?></a>
        <h1 class="page-title" style="margin-top:4px"><?= e($task['title']) ?></h1>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
            <span class="badge badge-<?= e($task['status']) ?>"><?= ucwords(str_replace('_',' ',$task['status'])) ?></span>
            <span class="badge badge-<?= e($task['priority']) ?>"><?= ucfirst($task['priority']) ?></span>
            <?php if ($overdue): ?><span class="badge" style="background:#FEE2E2;color:#991B1B">Overdue</span><?php endif ?>
        </div>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/modules/tasks/edit.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">Edit</a>
        <a href="<?= BASE_URL ?>/modules/tasks/create.php?project_id=<?= $task['project_id'] ?>&parent_id=<?= $id ?>" class="btn btn-ghost btn-sm">+ Subtask</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start" class="task-detail-grid">
<div>

<!-- Description -->
<?php if ($task['description']): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-body">
        <div class="detail-section-title">Description</div>
        <div style="font-size:.9rem;line-height:1.7"><?= nl2br(e($task['description'])) ?></div>
    </div>
</div>
<?php endif ?>

<!-- Subtasks -->
<?php if ($subtasks): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">Subtasks (<?= count($subtasks) ?>)</span></div>
    <div style="padding:0">
    <?php foreach ($subtasks as $st): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border)">
        <span class="badge badge-<?= e($st['status']) ?>"><?= ucwords(str_replace('_',' ',$st['status'])) ?></span>
        <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $st['id'] ?>" style="flex:1;font-size:.875rem;color:var(--text)"><?= e($st['title']) ?></a>
        <span class="badge badge-<?= e($st['priority']) ?>"><?= ucfirst($st['priority']) ?></span>
    </div>
    <?php endforeach ?>
    </div>
</div>
<?php endif ?>

<!-- Comments -->
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">Comments (<?= count($comments) ?>)</span></div>
    <div class="card-body" style="padding-bottom:0">
    <?php foreach ($comments as $c): ?>
    <div class="chat-item" style="padding:10px 0;border-bottom:1px solid var(--border)">
        <div class="avatar" style="background:<?= avatarColor($c['full_name']) ?>"><?= userInitials($c['full_name']) ?></div>
        <div>
            <div class="chat-meta"><strong><?= e($c['full_name']) ?></strong> &nbsp;<?= date('M j, g:i A', strtotime($c['created_at'])) ?></div>
            <div class="chat-body"><?= preg_replace('/@(\w+)/', '<span class="mention">@$1</span>', nl2br(e($c['body']))) ?></div>
        </div>
    </div>
    <?php endforeach ?>
    <?php if (!$comments): ?><p style="color:var(--text-muted);font-size:.875rem;margin-bottom:12px">No comments yet.</p><?php endif ?>
    </div>
    <div class="card-body" style="padding-top:12px;border-top:1px solid var(--border);position:relative">
        <form method="POST">
            <input type="hidden" name="comment" value="1">
            <textarea name="body" id="commentInput" class="form-control" rows="2" placeholder="Write a comment… @mention teammates" style="margin-bottom:8px"></textarea>
            <button class="btn btn-primary btn-sm">Post Comment</button>
        </form>
    </div>
</div>

<!-- Attachments -->
<div class="card">
    <div class="card-header"><span class="card-title">File Links</span></div>
    <div style="padding:0">
    <?php foreach ($attachments as $a): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border)">
        <div style="flex:1">
            <a href="<?= e($a['url']) ?>" target="_blank" rel="noopener" style="font-weight:500;font-size:.875rem"><?= e($a['label']) ?></a>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= e($a['full_name']) ?> · <?= date('M j', strtotime($a['created_at'])) ?></div>
        </div>
        <?php if ($user['role'] === 'admin' || $a['user_id'] === $user['id']): ?>
        <form method="POST" onsubmit="return confirm('Remove link?')">
            <input type="hidden" name="delete_attach" value="1">
            <input type="hidden" name="attach_id" value="<?= $a['id'] ?>">
            <button class="btn btn-ghost btn-sm btn-icon" title="Remove">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
            </button>
        </form>
        <?php endif ?>
    </div>
    <?php endforeach ?>
    </div>
    <div class="card-body" style="border-top:1px solid var(--border)">
        <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap">
            <input type="hidden" name="add_link" value="1">
            <input name="label" class="form-control" placeholder="Label" style="flex:1;min-width:120px" required>
            <input name="url" type="url" class="form-control" placeholder="https://..." style="flex:2;min-width:200px" required>
            <button class="btn btn-primary btn-sm">Add Link</button>
        </form>
    </div>
</div>

</div>

<!-- Sidebar -->
<div>
<div class="card" style="margin-bottom:16px">
    <div class="card-body">
        <div class="detail-section">
            <div class="detail-section-title">Details</div>
            <dl style="font-size:.875rem">
                <dt style="color:var(--text-muted);font-size:.75rem;margin-top:8px">Created by</dt>
                <dd><?= e($task['creator_name']) ?></dd>
                <dt style="color:var(--text-muted);font-size:.75rem;margin-top:8px">Created</dt>
                <dd><?= date('M j, Y', strtotime($task['created_at'])) ?></dd>
                <?php if ($task['due_date']): ?>
                <dt style="color:var(--text-muted);font-size:.75rem;margin-top:8px">Due</dt>
                <dd class="<?= $overdue ? 'due-overdue' : '' ?>"><?= date('M j, Y', strtotime($task['due_date'])) ?></dd>
                <?php endif ?>
                <?php if ($task['estimated_hours']): ?>
                <dt style="color:var(--text-muted);font-size:.75rem;margin-top:8px">Estimated</dt>
                <dd><?= $task['estimated_hours'] ?>h</dd>
                <?php endif ?>
            </dl>
        </div>
        <div class="detail-section">
            <div class="detail-section-title">Assignees</div>
            <?php if ($assignees): foreach ($assignees as $a): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <div class="avatar avatar-sm" style="background:<?= avatarColor($a['full_name']) ?>"><?= userInitials($a['full_name']) ?></div>
                <span style="font-size:.875rem"><?= e($a['full_name']) ?></span>
            </div>
            <?php endforeach; else: ?><p style="font-size:.8125rem;color:var(--text-muted)">None assigned</p><?php endif ?>
        </div>
    </div>
</div>

<!-- Time Log -->
<div class="card">
    <div class="card-header"><span class="card-title">Time Log</span></div>
    <div class="card-body">
        <div style="font-size:1.4rem;font-weight:700"><?= number_format($totalHours, 1) ?>h</div>
        <div style="font-size:.75rem;color:var(--text-muted)">total logged<?= $task['estimated_hours'] ? ' / '.$task['estimated_hours'].'h est.' : '' ?></div>
        <?php if ($task['estimated_hours'] && $totalHours):
            $logPct = min(100, round($totalHours / $task['estimated_hours'] * 100)); ?>
        <div class="progress" style="margin-top:8px"><div class="progress-bar" style="width:<?= $logPct ?>%"></div></div>
        <?php endif ?>
        <div style="margin-top:12px">
        <?php foreach ($timeLogs as $l): ?>
        <div style="font-size:.8125rem;padding:4px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:8px">
            <span style="color:var(--text-muted)"><?= e($l['full_name']) ?><?= $l['note'] ? ' — '.e($l['note']) : '' ?></span>
            <span style="font-weight:600;white-space:nowrap"><?= $l['hours'] ?>h <span style="color:var(--text-muted);font-weight:400"><?= date('M j', strtotime($l['logged_at'])) ?></span></span>
        </div>
        <?php endforeach ?>
        </div>
    </div>
    <div class="card-body" style="border-top:1px solid var(--border)">
        <form method="POST">
            <input type="hidden" name="log_time" value="1">
            <div class="form-row" style="margin-bottom:8px">
                <div><input name="hours" type="number" step="0.5" min="0.5" class="form-control" placeholder="Hours" required></div>
                <div><input name="logged_at" type="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            </div>
            <input name="note" class="form-control" placeholder="Note (optional)" style="margin-bottom:8px">
            <button class="btn btn-primary btn-sm">Log Time</button>
        </form>
    </div>
</div>
</div>

</div>

<style>
@media(max-width:767px){.task-detail-grid{grid-template-columns:1fr!important}}
</style>
<script>
initMentionInput(document.getElementById('commentInput'), <?= $members_json ?>);
</script>
<?php layoutEnd(); ?>
