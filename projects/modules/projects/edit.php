<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('admin');
$user = currentUser();
$id = (int)($_GET['id'] ?? 0);
$project = dbFetch("SELECT * FROM projects WHERE id=?", [$id]);
if (!$project) { flash('error', 'Project not found.'); redirect(BASE_URL . '/modules/projects/index.php'); }

$allUsers    = dbFetchAll("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name");
$curMembers  = dbFetchAll("SELECT user_id, role FROM project_members WHERE project_id=?", [$id]);
$curMemberIds = array_column($curMembers, 'user_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? $project['status'];
    $client = trim($_POST['client_name'] ?? '');
    $start  = $_POST['start_date'] ?? null;
    $due    = $_POST['due_date'] ?? null;
    $tools  = $_POST['tools_used'] ?? '[]';
    $ai     = $_POST['ai_used'] ?? '[]';
    $tech   = trim($_POST['tech_notes'] ?? '');
    $mems   = array_map('intval', $_POST['members'] ?? []);

    if (!$name) { flash('error', 'Project name is required.'); redirect(BASE_URL . '/modules/projects/edit.php?id='.$id); }

    dbUpdate('projects', [
        'name' => $name, 'description' => $desc ?: null, 'status' => $status,
        'client_name' => $client ?: null, 'start_date' => $start ?: null, 'due_date' => $due ?: null,
        'tools_used' => $tools, 'ai_used' => $ai, 'tech_notes' => $tech ?: null,
    ], ['id' => $id]);

    // Sync members (keep creator as lead)
    dbQuery("DELETE FROM project_members WHERE project_id=? AND user_id != ?", [$id, $project['created_by']]);
    foreach ($mems as $mid) {
        if ($mid === (int)$project['created_by']) continue;
        $role = in_array($mid, $curMemberIds) ? (array_column($curMembers,'role','user_id')[$mid] ?? 'member') : 'member';
        try { dbInsert('project_members', ['project_id' => $id, 'user_id' => $mid, 'role' => $role]); } catch (\Exception $e) {}
    }

    dbInsert('updates', ['project_id' => $id, 'user_id' => $user['id'], 'type' => 'project', 'message' => "Updated project \"{$name}\"."]);
    flash('success', 'Project updated.');
    redirect(BASE_URL . '/modules/projects/view.php?id='.$id);
}

layoutStart('Edit Project', 'projects');
$tools_val = $project['tools_used'] ?? '[]';
$ai_val    = $project['ai_used'] ?? '[]';
?>
<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/modules/projects/view.php?id=<?= $id ?>" style="font-size:.875rem;color:var(--text-muted)">&larr; <?= e($project['name']) ?></a>
        <h1 class="page-title" style="margin-top:4px">Edit Project</h1>
    </div>
</div>

<div class="card" style="max-width:740px">
<div class="card-body">
<form method="POST">
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Project Name *</label>
            <input name="name" class="form-control" value="<?= e($_POST['name'] ?? $project['name']) ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <?php foreach (['planning','active','on_hold','completed','archived'] as $s): ?>
                <option value="<?= $s ?>" <?= ($_POST['status'] ?? $project['status']) === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? $project['description']) ?></textarea>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Client Name</label>
            <input name="client_name" class="form-control" value="<?= e($_POST['client_name'] ?? $project['client_name']) ?>">
        </div>
        <div class="form-group"></div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Start Date</label>
            <input name="start_date" type="date" class="form-control" value="<?= e($_POST['start_date'] ?? $project['start_date']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Due Date</label>
            <input name="due_date" type="date" class="form-control" value="<?= e($_POST['due_date'] ?? $project['due_date']) ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label">Tools Used</label>
        <input type="text" id="toolsInput" class="form-control" placeholder="Type and press Enter">
        <input type="hidden" name="tools_used" id="toolsHidden" value="<?= e($_POST['tools_used'] ?? $tools_val) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">AI Used</label>
        <input type="text" id="aiInput" class="form-control" placeholder="Type and press Enter">
        <input type="hidden" name="ai_used" id="aiHidden" value="<?= e($_POST['ai_used'] ?? $ai_val) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Tech Notes</label>
        <textarea name="tech_notes" class="form-control" rows="3"><?= e($_POST['tech_notes'] ?? $project['tech_notes']) ?></textarea>
    </div>

    <div class="form-group">
        <label class="form-label">Team Members</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:10px;border:1px solid var(--border);border-radius:6px">
        <?php foreach ($allUsers as $u): ?>
        <label style="display:flex;align-items:center;gap:6px;font-size:.875rem;cursor:pointer;padding:4px 8px;border-radius:4px;background:var(--bg)">
            <input type="checkbox" name="members[]" value="<?= $u['id'] ?>"
                <?= in_array($u['id'], isset($_POST['members']) ? array_map('intval',$_POST['members']) : $curMemberIds) ? 'checked' : '' ?>>
            <?= e($u['full_name']) ?>
        </label>
        <?php endforeach ?>
        </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="<?= BASE_URL ?>/modules/projects/view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
    </div>
</form>
</div>
</div>

<script>
initTagInput(document.getElementById('toolsInput'), document.getElementById('toolsHidden'));
initTagInput(document.getElementById('aiInput'), document.getElementById('aiHidden'));
</script>
<?php layoutEnd(); ?>
