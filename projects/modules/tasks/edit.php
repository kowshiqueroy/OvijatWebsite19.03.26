<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('member');
$user = currentUser();
$id = (int)($_GET['id'] ?? 0);
$task = dbFetch("SELECT * FROM tasks WHERE id=?", [$id]);
if (!$task) { flash('error', 'Task not found.'); redirect(BASE_URL . '/modules/tasks/index.php'); }

$members    = dbFetchAll("SELECT u.id, u.full_name FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=?", [$task['project_id']]);
$curAssign  = array_column(dbFetchAll("SELECT user_id FROM task_assignees WHERE task_id=?", [$id]), 'user_id');
$milestones = dbFetchAll("SELECT id, title, status FROM milestones WHERE project_id=? ORDER BY status='completed', ISNULL(due_date), due_date, title", [$task['project_id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    if (isset($_POST['delete'])) {
        if ($user['role'] !== 'admin' && $task['created_by'] !== $user['id']) redirect(BASE_URL . '/modules/tasks/index.php');
        dbQuery("UPDATE tasks SET deleted_at=NOW() WHERE id=?", [$id]);
        dbInsert('updates', ['project_id' => $task['project_id'], 'user_id' => $user['id'], 'type' => 'task', 'message' => "Deleted task \"{$task['title']}\"."]);
        flash('success', 'Task deleted.');
        redirect(BASE_URL . '/modules/tasks/index.php?project_id=' . $task['project_id']);
    }
    $title    = trim($_POST['title'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $status   = $_POST['status'] ?? $task['status'];
    $priority = $_POST['priority'] ?? $task['priority'];
    $due      = $_POST['due_date'] ?? null;
    $estH     = $_POST['estimated_hours'] ? (float)$_POST['estimated_hours'] : null;
    $assignees = array_map('intval', $_POST['assignees'] ?? []);

    if (!$title) { flash('error', 'Title required.'); redirect(BASE_URL . '/modules/tasks/edit.php?id='.$id); }

    $milestoneId = (int)($_POST['milestone_id'] ?? 0) ?: null;
    $startDate   = $_POST['start_date'] ?: null;
    dbUpdate('tasks', [
        'title' => $title, 'description' => $desc ?: null,
        'status' => $status, 'priority' => $priority,
        'start_date' => $startDate, 'due_date' => $due ?: null,
        'estimated_hours' => $estH, 'milestone_id' => $milestoneId,
    ], ['id' => $id]);
    $prevAssignees = $curAssign;
    dbQuery("DELETE FROM task_assignees WHERE task_id=?", [$id]);
    foreach ($assignees as $uid) {
        try { dbInsert('task_assignees', ['task_id' => $id, 'user_id' => $uid]); } catch (\Exception $e) {}
        // Notify newly added assignees only
        if ($uid !== $user['id'] && !in_array($uid, $prevAssignees)) {
            try { dbInsert('notifications', ['user_id' => $uid, 'type' => 'task_assigned', 'message' => $user['full_name'] . ' assigned you to "' . $title . '"', 'link' => BASE_URL . '/modules/tasks/view.php?id=' . $id, 'related_entity_type' => 'task', 'related_entity_id' => $id, 'created_at' => date('Y-m-d H:i:s')]); } catch (\Exception $e) {}
        }
    }
    dbInsert('updates', ['project_id' => $task['project_id'], 'user_id' => $user['id'], 'type' => 'task', 'message' => "Updated task \"{$title}\"."]);
    flash('success', 'Task updated.');
    redirect(BASE_URL . '/modules/tasks/view.php?id='.$id);
}

layoutStart('Edit Task', 'tasks');
$project = dbFetch("SELECT name FROM projects WHERE id=?", [$task['project_id']]);
?>
<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $id ?>" style="font-size:.875rem;color:var(--text-muted)">&larr; Task</a>
        <h1 class="page-title" style="margin-top:4px">Edit Task</h1>
        <p class="page-subtitle"><?= e($project['name'] ?? '') ?></p>
    </div>
    <?php if ($user['role'] === 'admin' || $task['created_by'] === $user['id']): ?>
    <form method="POST" onsubmit="return confirm('Delete this task?')">
        <?= csrfField() ?>
        <input type="hidden" name="delete" value="1">
        <button class="btn btn-danger btn-sm">Delete Task</button>
    </form>
    <?php endif ?>
</div>

<div class="card" style="max-width:680px">
<div class="card-body">
<form method="POST">
    <?= csrfField() ?>
    <div class="form-group">
        <label class="form-label">Title *</label>
        <input name="title" class="form-control" value="<?= e($_POST['title'] ?? $task['title']) ?>" required autofocus>
    </div>
    <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? $task['description']) ?></textarea>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <?php foreach (['todo','in_progress','review','done'] as $s): ?>
                <option value="<?= $s ?>" <?= ($_POST['status'] ?? $task['status']) === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-control">
                <?php foreach (['low','medium','high'] as $p): ?>
                <option value="<?= $p ?>" <?= ($_POST['priority'] ?? $task['priority']) === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Start Date</label>
            <input name="start_date" type="date" class="form-control" value="<?= e($_POST['start_date'] ?? ($task['start_date'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Due Date</label>
            <input name="due_date" type="date" class="form-control" value="<?= e($_POST['due_date'] ?? $task['due_date']) ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Estimated Hours</label>
            <input name="estimated_hours" type="number" step="0.5" min="0" class="form-control" value="<?= e($_POST['estimated_hours'] ?? $task['estimated_hours']) ?>">
        </div>
        <div></div>
    </div>
    <?php if ($milestones): ?>
    <div class="form-group">
        <label class="form-label">Milestone</label>
        <select name="milestone_id" class="form-control">
            <option value="">— None —</option>
            <?php foreach ($milestones as $ms):
                $cur = (int)($_POST['milestone_id'] ?? $task['milestone_id']);
            ?>
            <option value="<?= $ms['id'] ?>" <?= $cur === (int)$ms['id'] ? 'selected' : '' ?>>
                <?= e($ms['title']) ?><?= $ms['status'] === 'completed' ? ' (completed)' : '' ?>
            </option>
            <?php endforeach ?>
        </select>
    </div>
    <?php endif ?>
    <div class="form-group">
        <label class="form-label">Assignees</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:10px;border:1px solid var(--border);border-radius:6px">
        <?php foreach ($members as $m): ?>
        <label style="display:flex;align-items:center;gap:6px;font-size:.875rem;cursor:pointer;padding:4px 8px;border-radius:4px;background:var(--bg)">
            <input type="checkbox" name="assignees[]" value="<?= $m['id'] ?>"
                <?= in_array($m['id'], isset($_POST['assignees']) ? array_map('intval',$_POST['assignees']) : $curAssign) ? 'checked' : '' ?>>
            <?= e($m['full_name']) ?>
        </label>
        <?php endforeach ?>
        </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
    </div>
</form>
</div>
</div>

<?php layoutEnd(); ?>
