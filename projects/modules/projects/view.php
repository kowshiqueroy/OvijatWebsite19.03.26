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

function checkProjectAccess(int $pid, array $user): array {
    if (!$pid) { flash('error', 'Project ID required'); redirect(BASE_URL . '/modules/projects/index.php'); }
    $p = dbFetch("SELECT p.*, u.full_name as creator_name FROM projects p JOIN users u ON u.id=p.created_by WHERE p.id=?", [$pid]);
    if (!$p) { flash('error', 'Project not found'); redirect(BASE_URL . '/modules/projects/index.php'); }
    if ($user['role'] !== 'admin') {
        $isMember = dbFetch("SELECT id FROM project_members WHERE project_id=? AND user_id=?", [$pid, $user['id']]);
        if (!$isMember) { flash('error', 'Access denied'); redirect(BASE_URL . '/modules/projects/index.php'); }
    }
    return $p;
}

$project = checkProjectAccess($id, $user);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && $user['role'] === 'admin') {
    dbQuery("DELETE FROM projects WHERE id=?", [$id]);
    flash('success', 'Project deleted.');
    redirect(BASE_URL . '/modules/projects/index.php');
}

// Add milestone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_milestone'])) {
    $title = trim($_POST['milestone_title'] ?? '');
    $due   = $_POST['milestone_due'] ?: null;
    if ($title) dbInsert('milestones', ['project_id' => $id, 'title' => $title, 'due_date' => $due, 'created_by' => $user['id']]);
    redirect(BASE_URL . '/modules/projects/view.php?id=' . $id . '#milestones');
}

// Toggle milestone open/completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_milestone'])) {
    $mid = (int)$_POST['milestone_id'];
    // Ensure milestone belongs to project
    $ms  = dbFetch("SELECT status FROM milestones WHERE id=? AND project_id=?", [$mid, $id]);
    if ($ms) dbUpdate('milestones', ['status' => $ms['status'] === 'completed' ? 'open' : 'completed'], ['id' => $mid]);
    redirect(BASE_URL . '/modules/projects/view.php?id=' . $id . '#milestones');
}

// Delete milestone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_milestone']) && $user['role'] === 'admin') {
    $mid = (int)$_POST['milestone_id'];
    dbQuery("DELETE FROM milestones WHERE id=? AND project_id=?", [$mid, $id]);
    redirect(BASE_URL . '/modules/projects/view.php?id=' . $id . '#milestones');
}

$members    = dbFetchAll("SELECT u.id, u.full_name, u.role as sys_role, pm.role as proj_role FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=? ORDER BY pm.role, u.full_name", [$id]);
$milestones = dbFetchAll(
    "SELECT m.*,
       (SELECT COUNT(*) FROM tasks WHERE milestone_id=m.id) as task_total,
       (SELECT COUNT(*) FROM tasks WHERE milestone_id=m.id AND status='done') as task_done
     FROM milestones m WHERE m.project_id=?
     ORDER BY m.status='completed', ISNULL(m.due_date), m.due_date, m.id",
    [$id]
);
$tasks     = dbFetchAll("SELECT * FROM tasks WHERE project_id=? ORDER BY status, priority DESC", [$id]);
$meetings  = dbFetchAll("SELECT * FROM meetings WHERE project_id=? ORDER BY meeting_date DESC LIMIT 5", [$id]);
$updates   = dbFetchAll("SELECT u.*, usr.full_name FROM updates u JOIN users usr ON usr.id=u.user_id WHERE u.project_id=? ORDER BY u.created_at DESC LIMIT 10", [$id]);

$tasksByStatus = ['todo' => [], 'in_progress' => [], 'review' => [], 'done' => []];
foreach ($tasks as $t) { $tasksByStatus[$t['status']][] = $t; }
$doneCount  = count($tasksByStatus['done']);
$totalTasks = count($tasks);
$pct = $totalTasks > 0 ? round($doneCount / $totalTasks * 100) : 0;

$tools = json_decode($project['tools_used'] ?? '[]', true) ?: [];
$ai    = json_decode($project['ai_used'] ?? '[]', true) ?: [];

layoutStart(e($project['name']), 'projects');
?>
<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/modules/projects/index.php" style="font-size:.875rem;color:var(--text-muted)">&larr; Projects</a>
        <h1 class="page-title" style="margin-top:4px"><?= e($project['name']) ?></h1>
        <div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap">
            <span class="badge badge-<?= e($project['status']) ?>"><?= ucwords(str_replace('_',' ',$project['status'])) ?></span>
            <?php if ($project['client_name']): ?><span style="font-size:.8125rem;color:var(--text-muted)"><?= e($project['client_name']) ?></span><?php endif ?>
        </div>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/gantt.php?project_id=<?= $id ?>" class="btn btn-secondary btn-sm">Timeline</a>
        <a href="<?= BASE_URL ?>/worksheet/index.php?project_id=<?= $id ?>" class="btn btn-secondary btn-sm">Open Worksheet</a>
        <?php if ($user['role'] === 'admin'): ?>
        <a href="<?= BASE_URL ?>/modules/projects/edit.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">Edit</a>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this project and all its data?')">
            <input type="hidden" name="delete" value="1">
            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
        </form>
        <?php endif ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start" class="proj-grid">

<div>
<!-- Task Summary -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title">Tasks</span>
        <a href="<?= BASE_URL ?>/modules/tasks/create.php?project_id=<?= $id ?>" class="btn btn-primary btn-sm">+ Add Task</a>
    </div>
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;font-size:.8125rem;color:var(--text-muted);margin-bottom:6px">
            <span><?= $doneCount ?>/<?= $totalTasks ?> done</span><span><?= $pct ?>%</span>
        </div>
        <div class="progress" style="height:8px;margin-bottom:16px"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
        <?php
        $labels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'Review', 'done' => 'Done'];
        foreach ($tasksByStatus as $status => $tlist): if (empty($tlist)) continue; ?>
        <div style="margin-bottom:12px">
            <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:6px"><?= $labels[$status] ?> (<?= count($tlist) ?>)</div>
            <?php foreach ($tlist as $t): ?>
            <div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--border)">
                <span class="badge badge-<?= e($t['priority']) ?>" style="width:52px;justify-content:center"><?= ucfirst($t['priority']) ?></span>
                <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>" style="flex:1;font-size:.875rem;color:var(--text)"><?= e($t['title']) ?></a>
                <?php if ($t['due_date']):
                    $over = $t['due_date'] < date('Y-m-d') && $t['status'] !== 'done'; ?>
                <span style="font-size:.75rem;color:<?= $over ? 'var(--danger)' : 'var(--text-muted)' ?>"><?= date('M j', strtotime($t['due_date'])) ?></span>
                <?php endif ?>
            </div>
            <?php endforeach ?>
        </div>
        <?php endforeach ?>
        <?php if (!$totalTasks): ?><div class="empty-state" style="padding:20px"><p>No tasks yet.</p></div><?php endif ?>
    </div>
</div>

<!-- Milestones -->
<div class="card" style="margin-bottom:20px" id="milestones">
    <div class="card-header">
        <span class="card-title">Milestones</span>
        <button onclick="openModal('addMilestoneModal')" class="btn btn-primary btn-sm">+ Add</button>
    </div>
    <?php if ($milestones): ?>
    <div style="padding:0">
    <?php foreach ($milestones as $ms):
        $mPct      = $ms['task_total'] > 0 ? round($ms['task_done'] / $ms['task_total'] * 100) : 0;
        $isDone    = $ms['status'] === 'completed';
        $isOverdue = $ms['due_date'] && !$isDone && $ms['due_date'] < date('Y-m-d');
    ?>
    <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:6px">
            <div style="flex:1;min-width:0">
                <span style="font-size:.875rem;font-weight:500;<?= $isDone ? 'text-decoration:line-through;color:var(--text-muted)' : '' ?>"><?= e($ms['title']) ?></span>
                <?php if ($ms['due_date']): ?>
                <span style="font-size:.75rem;margin-left:8px;color:<?= $isOverdue ? 'var(--danger)' : 'var(--text-muted)' ?>">
                    <?= $isOverdue ? '⚠ ' : '' ?><?= date('M j, Y', strtotime($ms['due_date'])) ?>
                </span>
                <?php endif ?>
            </div>
            <div style="display:flex;gap:2px;flex-shrink:0">
                <form method="POST" style="margin:0">
                    <input type="hidden" name="toggle_milestone" value="1">
                    <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                    <button class="btn btn-ghost btn-sm" style="font-size:.75rem" title="<?= $isDone ? 'Reopen' : 'Mark complete' ?>">
                        <?= $isDone ? '↩ Reopen' : '✓ Done' ?>
                    </button>
                </form>
                <?php if ($user['role'] === 'admin'): ?>
                <form method="POST" style="margin:0" onsubmit="return confirm('Delete milestone?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="delete_milestone" value="1">
                    <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                    <button class="btn btn-ghost btn-sm btn-icon" title="Delete">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    </button>
                </form>
                <?php endif ?>
            </div>
        </div>
        <?php if ($ms['task_total'] > 0): ?>
        <div style="display:flex;align-items:center;gap:8px">
            <div class="progress" style="flex:1;height:5px">
                <div class="progress-bar" style="width:<?= $mPct ?>%;<?= $isDone ? 'background:var(--success)' : '' ?>"></div>
            </div>
            <span style="font-size:.75rem;color:var(--text-muted);white-space:nowrap"><?= $ms['task_done'] ?>/<?= $ms['task_total'] ?> tasks</span>
        </div>
        <?php else: ?>
        <div style="font-size:.75rem;color:var(--text-muted)">No tasks assigned yet</div>
        <?php endif ?>
    </div>
    <?php endforeach ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:20px"><p>No milestones yet. Break the project into phases.</p></div>
    <?php endif ?>
</div>

<!-- Add Milestone Modal -->
<div class="modal-overlay" id="addMilestoneModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add Milestone</span>
            <button class="modal-close" onclick="closeModal('addMilestoneModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="add_milestone" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input name="milestone_title" class="form-control" required placeholder="e.g. Phase 1 — Design">
                </div>
                <div class="form-group">
                    <label class="form-label">Due Date</label>
                    <input name="milestone_due" type="date" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addMilestoneModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Milestone</button>
            </div>
        </form>
    </div>
</div>

<!-- Meetings -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Meetings</span>
        <a href="<?= BASE_URL ?>/modules/meetings/create.php?project_id=<?= $id ?>" class="btn btn-primary btn-sm">+ Add</a>
    </div>
    <div style="padding:0">
    <?php if ($meetings): foreach ($meetings as $m): ?>
    <a href="<?= BASE_URL ?>/modules/meetings/view.php?id=<?= $m['id'] ?>" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--border);color:var(--text)">
        <div>
            <div style="font-size:.875rem;font-weight:500"><?= e($m['title']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= date('M j, Y · g:i A', strtotime($m['meeting_date'])) ?></div>
        </div>
        <span class="badge badge-<?= e($m['status']) ?>"><?= ucfirst($m['status']) ?></span>
    </a>
    <?php endforeach; else: ?>
    <div class="empty-state" style="padding:20px"><p>No meetings linked.</p></div>
    <?php endif ?>
    </div>
</div>
</div>

<!-- Right sidebar -->
<div>
<!-- Project Details -->
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">Details</span></div>
    <div class="card-body" style="font-size:.875rem">
        <?php if ($project['description']): ?>
        <div style="margin-bottom:12px;color:var(--text-muted)"><?= nl2br(e($project['description'])) ?></div>
        <?php endif ?>
        <?php if ($project['start_date'] || $project['due_date']): ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
            <?php if ($project['start_date']): ?>
            <div><div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">Start</div><div><?= date('M j, Y', strtotime($project['start_date'])) ?></div></div>
            <?php endif ?>
            <?php if ($project['due_date']): ?>
            <div><div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">Due</div><div><?= date('M j, Y', strtotime($project['due_date'])) ?></div></div>
            <?php endif ?>
        </div>
        <?php endif ?>
        <?php if ($tools): ?>
        <div style="margin-bottom:8px"><div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">Tools</div>
        <div style="display:flex;flex-wrap:wrap;gap:4px"><?php foreach ($tools as $t): ?><span class="badge badge-planning"><?= e($t) ?></span><?php endforeach ?></div></div>
        <?php endif ?>
        <?php if ($ai): ?>
        <div style="margin-bottom:8px"><div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">AI</div>
        <div style="display:flex;flex-wrap:wrap;gap:4px"><?php foreach ($ai as $a): ?><span class="badge badge-completed"><?= e($a) ?></span><?php endforeach ?></div></div>
        <?php endif ?>
        <?php if ($project['tech_notes'] || $user['role'] === 'admin'): ?>
        <div id="techNotesSection">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">Tech Notes</div>
                <?php if ($user['role'] === 'admin'): ?>
                <button onclick="toggleTechEdit()" class="btn btn-ghost btn-sm" style="padding:2px 4px;font-size:.65rem">Edit</button>
                <?php endif ?>
            </div>
            <div id="techNotesView" style="color:var(--text-muted);font-size:.8125rem">
                <?= $project['tech_notes'] ? nl2br(e($project['tech_notes'])) : '<em style="font-size:.75rem">No notes added.</em>' ?>
            </div>
            <?php if ($user['role'] === 'admin'): ?>
            <form id="techNotesEdit" method="POST" style="display:none">
                <?= csrfField() ?>
                <input type="hidden" name="update_tech_notes" value="1">
                <textarea name="tech_notes" class="form-control" rows="5" style="font-size:.8125rem;margin-bottom:8px"><?= e($project['tech_notes']) ?></textarea>
                <div style="display:flex;gap:4px">
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    <button type="button" onclick="toggleTechEdit()" class="btn btn-secondary btn-sm">Cancel</button>
                </div>
            </form>
            <?php endif ?>
        </div>
        <?php endif ?>
    </div>
</div>

<script>
function toggleTechEdit() {
    const view = document.getElementById('techNotesView');
    const edit = document.getElementById('techNotesEdit');
    if (view.style.display === 'none') {
        view.style.display = 'block';
        edit.style.display = 'none';
    } else {
        view.style.display = 'none';
        edit.style.display = 'block';
    }
}
</script>

<!-- Team -->
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">Team</span></div>
    <div style="padding:8px 0">
    <?php foreach ($members as $m): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 16px">
        <div class="avatar avatar-sm" style="background:<?= avatarColor($m['full_name']) ?>"><?= userInitials($m['full_name']) ?></div>
        <div style="flex:1">
            <div style="font-size:.875rem;font-weight:500"><?= e($m['full_name']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= ucfirst($m['proj_role'] ?? $m['sys_role'] ?? 'Member') ?></div>
        </div>
    </div>
    <?php endforeach ?>
    </div>
</div>

<!-- Recent Updates -->
<div class="card">
    <div class="card-header"><span class="card-title">Activity</span></div>
    <div style="padding:0">
    <?php $typeIcon = ['task'=>'✅','meeting'=>'📅','project'=>'📁','general'=>'💬'];
    foreach ($updates as $u): ?>
    <div style="padding:10px 16px;border-bottom:1px solid var(--border);font-size:.8125rem">
        <span><?= $typeIcon[$u['type']] ?? '•' ?></span>
        <span style="color:var(--text)"><?= e($u['message']) ?></span>
        <div style="color:var(--text-muted);font-size:.75rem;margin-top:2px"><?= e($u['full_name']) ?> · <?= date('M j, g:i A', strtotime($u['created_at'])) ?></div>
    </div>
    <?php endforeach ?>
    <?php if (!$updates): ?><div class="empty-state" style="padding:20px"><p>No activity yet.</p></div><?php endif ?>
    </div>
</div>
</div>
</div>

<style>
@media(max-width:767px){.proj-grid{grid-template-columns:1fr!important}}
</style>
<?php layoutEnd(); ?>
