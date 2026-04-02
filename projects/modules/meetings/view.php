<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('member');
$user = currentUser();
validateCsrf();
$id = (int)($_GET['id'] ?? 0);
$meeting = dbFetch("SELECT m.*, p.name as project_name, u.full_name as creator FROM meetings m LEFT JOIN projects p ON p.id=m.project_id JOIN users u ON u.id=m.created_by WHERE m.id=?", [$id]);
if (!$meeting) { flash('error', 'Meeting not found.'); redirect(BASE_URL . '/modules/meetings/index.php'); }

// Handle Task Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_task'])) {
    $aid = (int)$_POST['action_id'];
    $ai  = dbFetch("SELECT * FROM meeting_action_items WHERE id=? AND meeting_id=?", [$aid, $id]);
    if ($ai && !$ai['task_id'] && $meeting['project_id']) {
        $taskId = dbInsert('tasks', [
            'project_id' => $meeting['project_id'],
            'title' => $ai['description'],
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => $ai['due_date'],
            'created_by' => $user['id']
        ]);
        if ($ai['assigned_to']) {
            dbInsert('task_assignees', ['task_id' => $taskId, 'user_id' => $ai['assigned_to']]);
        }
        dbUpdate('meeting_action_items', ['task_id' => $taskId], ['id' => $aid]);
        dbInsert('updates', ['project_id' => $meeting['project_id'], 'user_id' => $user['id'], 'type' => 'task', 'message' => "Generated task from meeting action item: \"{$ai['description']}\"."]);
        flash('success', 'Task generated from action item.');
    }
    redirect(BASE_URL . '/modules/meetings/view.php?id='.$id);
}

// Handle RSVP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rsvp'])) {
    $rsvp = $_POST['rsvp'];
    if (in_array($rsvp, ['yes','no','maybe','pending'])) {
        $exists = dbFetch("SELECT id FROM meeting_attendees WHERE meeting_id=? AND user_id=?", [$id, $user['id']]);
        if ($exists) {
            dbUpdate('meeting_attendees', ['rsvp' => $rsvp], ['meeting_id' => $id, 'user_id' => $user['id']]);
        } else {
            dbInsert('meeting_attendees', ['meeting_id' => $id, 'user_id' => $user['id'], 'rsvp' => $rsvp]);
        }
    }
    redirect(BASE_URL . '/modules/meetings/view.php?id='.$id);
}
// Add action item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_action'])) {
    $desc   = trim($_POST['description'] ?? '');
    $assign = (int)($_POST['assigned_to'] ?? 0) ?: null;
    $due    = $_POST['due_date'] ?? null;
    if ($desc) {
        dbInsert('meeting_action_items', ['meeting_id' => $id, 'description' => $desc, 'assigned_to' => $assign, 'due_date' => $due ?: null, 'created_at' => date('Y-m-d H:i:s')]);
    }
    redirect(BASE_URL . '/modules/meetings/view.php?id='.$id);
}
// Toggle action item done
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_action'])) {
    $aid = (int)$_POST['action_id'];
    $ai = dbFetch("SELECT * FROM meeting_action_items WHERE id=?", [$aid]);
    if ($ai && $ai['meeting_id'] === $id) {
        dbUpdate('meeting_action_items', ['is_done' => $ai['is_done'] ? 0 : 1], ['id' => $aid]);
    }
    redirect(BASE_URL . '/modules/meetings/view.php?id='.$id);
}
// Delete meeting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && $user['role'] === 'admin') {
    dbQuery("DELETE FROM meetings WHERE id=?", [$id]);
    flash('success', 'Meeting deleted.');
    redirect(BASE_URL . '/modules/meetings/index.php');
}

$attendees   = dbFetchAll("SELECT ma.*, u.full_name FROM meeting_attendees ma JOIN users u ON u.id=ma.user_id WHERE ma.meeting_id=? ORDER BY ma.rsvp, u.full_name", [$id]);
$actionItems = dbFetchAll("SELECT ai.*, u.full_name as assignee_name FROM meeting_action_items ai LEFT JOIN users u ON u.id=ai.assigned_to WHERE ai.meeting_id=? ORDER BY ai.is_done, ai.due_date", [$id]);
$allUsers    = dbFetchAll("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name");
$myRsvp = '';
foreach ($attendees as $a) { if ((int)$a['user_id'] === $user['id']) { $myRsvp = $a['rsvp']; break; } }

layoutStart(e($meeting['title']), 'meetings');
?>
<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/modules/meetings/index.php" style="font-size:.875rem;color:var(--text-muted)">&larr; Meetings</a>
        <h1 class="page-title" style="margin-top:4px"><?= e($meeting['title']) ?></h1>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
            <span class="badge badge-<?= $meeting['status'] === 'done' ? 'done-meet' : e($meeting['status']) ?>"><?= ucfirst($meeting['status']) ?></span>
            <?php if ($meeting['project_name']): ?><span style="font-size:.8125rem;color:var(--text-muted)"><?= e($meeting['project_name']) ?></span><?php endif ?>
        </div>
    </div>
    <div style="display:flex;gap:8px">
        <!-- RSVP -->
        <form method="POST" style="display:flex;gap:4px">
            <?= csrfField() ?>
            <input type="hidden" name="rsvp" value="yes">
            <button class="btn btn-sm <?= $myRsvp === 'yes' ? 'btn-primary' : 'btn-secondary' ?>">✓ Yes</button>
        </form>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="rsvp" value="maybe">
            <button class="btn btn-sm <?= $myRsvp === 'maybe' ? 'btn-warning' : 'btn-secondary' ?>">? Maybe</button>
        </form>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="rsvp" value="no">
            <button class="btn btn-sm <?= $myRsvp === 'no' ? 'btn-danger' : 'btn-secondary' ?>">✗ No</button>
        </form>
        <?php if ($user['role'] === 'admin'): ?>
        <form method="POST" onsubmit="return confirm('Delete this meeting?')">
            <?= csrfField() ?>
            <input type="hidden" name="delete" value="1">
            <button class="btn btn-danger btn-sm">Delete</button>
        </form>
        <?php endif ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start" class="meeting-grid">
<div>

<!-- Details -->
<div class="card" style="margin-bottom:16px">
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:20px;font-size:.875rem;margin-bottom:16px">
            <div>
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted)">Date & Time</div>
                <div style="font-weight:500"><?= date('D, M j, Y · g:i A', strtotime($meeting['meeting_date'])) ?></div>
            </div>
            <?php if ($meeting['duration_minutes']): ?>
            <div>
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted)">Duration</div>
                <div><?= $meeting['duration_minutes'] ?> min</div>
            </div>
            <?php endif ?>
            <?php if ($meeting['location_or_link']): ?>
            <div>
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted)">Location</div>
                <div><?php
                    $loc = $meeting['location_or_link'];
                    if (filter_var($loc, FILTER_VALIDATE_URL)) echo '<a href="'.e($loc).'" target="_blank" rel="noopener">'.e($loc).'</a>';
                    else echo e($loc);
                ?></div>
            </div>
            <?php endif ?>
            <?php if ($meeting['recurrence'] !== 'none'): ?>
            <div>
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted)">Recurrence</div>
                <div><?= ucfirst($meeting['recurrence']) ?></div>
            </div>
            <?php endif ?>
        </div>
        <?php if ($meeting['agenda']): ?>
        <div class="detail-section">
            <div class="detail-section-title">Agenda</div>
            <div style="font-size:.875rem;white-space:pre-wrap"><?= e($meeting['agenda']) ?></div>
        </div>
        <?php endif ?>
        <?php if ($meeting['notes']): ?>
        <div class="detail-section">
            <div class="detail-section-title">Notes</div>
            <div style="font-size:.875rem;white-space:pre-wrap"><?= e($meeting['notes']) ?></div>
        </div>
        <?php endif ?>
    </div>
</div>

<!-- Action Items -->
<div class="card">
    <div class="card-header"><span class="card-title">Action Items</span></div>
    <div style="padding:0 16px">
    <?php foreach ($actionItems as $ai): ?>
    <div class="action-item <?= $ai['is_done'] ? 'done' : '' ?>">
        <form method="POST" style="flex-shrink:0">
            <?= csrfField() ?>
            <input type="hidden" name="toggle_action" value="1">
            <input type="hidden" name="action_id" value="<?= $ai['id'] ?>">
            <button type="submit" style="width:20px;height:20px;border-radius:50%;border:2px solid var(--border);background:<?= $ai['is_done'] ? 'var(--success)' : '#fff' ?>;cursor:pointer;flex-shrink:0"></button>
        </form>
        <div style="flex:1">
            <div class="action-item-text" style="font-size:.875rem"><?= e($ai['description']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)">
                <?= $ai['assignee_name'] ? e($ai['assignee_name']) : 'Unassigned' ?>
                <?= $ai['due_date'] ? ' · Due '.date('M j', strtotime($ai['due_date'])) : '' ?>
                <?php if ($ai['task_id']): ?>
                · <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $ai['task_id'] ?>" style="color:var(--accent)">Linked Task</a>
                <?php endif ?>
            </div>
        </div>
        <?php if (!$ai['task_id'] && $meeting['project_id']): ?>
        <form method="POST" style="margin-left:8px">
            <?= csrfField() ?>
            <input type="hidden" name="generate_task" value="1">
            <input type="hidden" name="action_id" value="<?= $ai['id'] ?>">
            <button type="submit" class="btn btn-ghost btn-sm" title="Generate Task">⚙ Task</button>
        </form>
        <?php endif ?>
    </div>
    <?php endforeach ?>
    <?php if (!$actionItems): ?><div class="empty-state" style="padding:20px"><p>No action items yet.</p></div><?php endif ?>
    </div>
    <div class="card-body" style="border-top:1px solid var(--border)">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="add_action" value="1">
            <div class="form-group">
                <input name="description" class="form-control" placeholder="Action item description" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <select name="assigned_to" class="form-control">
                        <option value="">Unassigned</option>
                        <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= e($u['full_name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="form-group">
                    <input name="due_date" type="date" class="form-control">
                </div>
            </div>
            <button class="btn btn-primary btn-sm">Add Action Item</button>
        </form>
    </div>
</div>
</div>

<!-- Attendees -->
<div class="card">
    <div class="card-header"><span class="card-title">Attendees (<?= count($attendees) ?>)</span></div>
    <div style="padding:8px 0">
    <?php foreach ($attendees as $a): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 16px">
        <div class="avatar avatar-sm" style="background:<?= avatarColor($a['full_name']) ?>"><?= userInitials($a['full_name']) ?></div>
        <span style="flex:1;font-size:.875rem"><?= e($a['full_name']) ?></span>
        <span class="badge badge-<?= e($a['rsvp']) ?>"><?= ucfirst($a['rsvp']) ?></span>
    </div>
    <?php endforeach ?>
    <?php if (!$attendees): ?><div style="padding:16px;color:var(--text-muted);font-size:.875rem">No attendees.</div><?php endif ?>
    </div>
</div>
</div>

<style>
@media(max-width:767px){.meeting-grid{grid-template-columns:1fr!important}}
</style>
<?php layoutEnd(); ?>
