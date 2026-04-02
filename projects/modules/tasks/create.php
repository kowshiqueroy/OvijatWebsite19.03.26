<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('member');
$user = currentUser();

$preProjectId = (int)($_GET['project_id'] ?? 0);
$preParentId  = (int)($_GET['parent_id'] ?? 0);

$projects = $user['role'] === 'admin'
    ? dbFetchAll("SELECT id, name FROM projects WHERE status NOT IN ('archived','completed') ORDER BY name")
    : dbFetchAll("SELECT p.id, p.name FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? AND p.status NOT IN ('archived','completed') ORDER BY p.name", [$user['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $projectId  = (int)$_POST['project_id'];
    $parentId   = (int)($_POST['parent_task_id'] ?? 0) ?: null;
    $title      = trim($_POST['title'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $status     = $_POST['status'] ?? 'todo';
    $priority   = $_POST['priority'] ?? 'medium';
    $due        = $_POST['due_date'] ?? null;
    $estHours   = $_POST['estimated_hours'] ? (float)$_POST['estimated_hours'] : null;
    $assignees  = array_map('intval', $_POST['assignees'] ?? []);

    if (!$title || !$projectId) { flash('error', 'Title and project are required.'); redirect(BASE_URL . '/modules/tasks/create.php?project_id='.$projectId); }

    $milestoneId = (int)($_POST['milestone_id'] ?? 0) ?: null;
    $startDate = $_POST['start_date'] ?: null;
    $id = dbInsert('tasks', [
        'project_id' => $projectId, 'parent_task_id' => $parentId,
        'milestone_id' => $milestoneId,
        'title' => $title, 'description' => $desc ?: null,
        'status' => $status, 'priority' => $priority,
        'start_date' => $startDate, 'due_date' => $due ?: null,
        'estimated_hours' => $estHours,
        'created_by' => $user['id'],
    ]);
    foreach ($assignees as $uid) {
        try { dbInsert('task_assignees', ['task_id' => $id, 'user_id' => $uid]); } catch (\Exception $e) {}
        if ($uid !== $user['id']) {
            try { dbInsert('notifications', ['user_id' => $uid, 'type' => 'task_assigned', 'message' => $user['full_name'] . ' assigned you to "' . $title . '"', 'link' => BASE_URL . '/modules/tasks/view.php?id=' . $id, 'related_entity_type' => 'task', 'related_entity_id' => $id, 'created_at' => date('Y-m-d H:i:s')]); } catch (\Exception $e) {}
        }
    }
    dbInsert('updates', ['project_id' => $projectId, 'user_id' => $user['id'], 'type' => 'task', 'message' => "Created task \"{$title}\"."]);
    flash('success', 'Task created.');
    redirect(BASE_URL . '/modules/tasks/view.php?id='.$id);
}

// Members for selected project
$projId = (int)($_POST['project_id'] ?? $preProjectId);
$members    = $projId ? dbFetchAll("SELECT u.id, u.full_name FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=?", [$projId]) : [];
$milestones = $projId ? dbFetchAll("SELECT id, title FROM milestones WHERE project_id=? AND status='open' ORDER BY ISNULL(due_date), due_date, title", [$projId]) : [];

layoutStart('New Task', 'tasks');
?>
<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/modules/tasks/index.php" style="font-size:.875rem;color:var(--text-muted)">&larr; Tasks</a>
        <h1 class="page-title" style="margin-top:4px">New Task</h1>
    </div>
</div>

<div class="card" style="max-width:680px">
<div class="card-body">
<form method="POST" id="taskForm">
    <?= csrfField() ?>
    <div class="form-group">
        <label class="form-label">Project *</label>
        <select name="project_id" class="form-control" required onchange="this.form.submit()" id="projectSel">
            <option value="">— Select project —</option>
            <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $projId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <?php if ($preParentId): ?>
    <input type="hidden" name="parent_task_id" value="<?= $preParentId ?>">
    <?php endif ?>
    <div class="form-group">
        <label class="form-label">Title *</label>
        <input name="title" class="form-control" value="<?= e($_POST['title'] ?? '') ?>" required autofocus>
    </div>
    <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <?php foreach (['todo','in_progress','review','done'] as $s): ?>
                <option value="<?= $s ?>" <?= ($_POST['status'] ?? 'todo') === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-control">
                <?php foreach (['low','medium','high'] as $p): ?>
                <option value="<?= $p ?>" <?= ($_POST['priority'] ?? 'medium') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Start Date</label>
            <input name="start_date" type="date" class="form-control" value="<?= e($_POST['start_date'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Due Date</label>
            <input name="due_date" type="date" class="form-control" value="<?= e($_POST['due_date'] ?? '') ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Estimated Hours</label>
            <input name="estimated_hours" type="number" step="0.5" min="0" class="form-control" value="<?= e($_POST['estimated_hours'] ?? '') ?>">
        </div>
        <div></div>
    </div>
    <?php if ($milestones): ?>
    <div class="form-group">
        <label class="form-label">Milestone</label>
        <select name="milestone_id" class="form-control">
            <option value="">— None —</option>
            <?php foreach ($milestones as $ms): ?>
            <option value="<?= $ms['id'] ?>" <?= ((int)($_POST['milestone_id'] ?? 0)) === (int)$ms['id'] ? 'selected' : '' ?>><?= e($ms['title']) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <?php endif ?>
    <?php if ($members): ?>
    <div class="form-group">
        <label class="form-label">Assignees</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:10px;border:1px solid var(--border);border-radius:6px">
        <?php foreach ($members as $m): ?>
        <label style="display:flex;align-items:center;gap:6px;font-size:.875rem;cursor:pointer;padding:4px 8px;border-radius:4px;background:var(--bg)">
            <input type="checkbox" name="assignees[]" value="<?= $m['id'] ?>" <?= in_array($m['id'], $_POST['assignees'] ?? []) ? 'checked' : '' ?>>
            <?= e($m['full_name']) ?>
        </label>
        <?php endforeach ?>
        </div>
    </div>
    <?php endif ?>
    <div style="display:flex;gap:8px;margin-top:8px">
        <button type="submit" class="btn btn-primary" id="submitBtn">Create Task</button>
        <a href="<?= BASE_URL ?>/modules/tasks/index.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>
</div>
</div>

<script>
// Prevent form submission when changing project selector
document.getElementById('projectSel').addEventListener('change', function() {
    document.getElementById('submitBtn').disabled = true;
    this.form.submit();
});
</script>
<?php layoutEnd(); ?>
