<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('member');
$user = currentUser();

// AJAX: update task status on drag-drop
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'], $_POST['status'])) {
    $allowed = ['todo', 'in_progress', 'review', 'done'];
    $tid     = (int)$_POST['task_id'];
    $status  = $_POST['status'];
    if ($tid && in_array($status, $allowed)) {
        dbUpdate('tasks', ['status' => $status], ['id' => $tid]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

$filterProject = (int)($_GET['project_id'] ?? 0);

$projects = $user['role'] === 'admin'
    ? dbFetchAll("SELECT id, name FROM projects WHERE status != 'archived' ORDER BY name")
    : dbFetchAll("SELECT p.id, p.name FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? AND p.status != 'archived' ORDER BY p.name", [$user['id']]);

$where  = ['1=1'];
$params = [];
if ($filterProject) {
    $where[]  = 't.project_id=?';
    $params[] = $filterProject;
} elseif ($user['role'] !== 'admin') {
    $where[]  = '(t.created_by=? OR EXISTS (SELECT 1 FROM task_assignees ta2 WHERE ta2.task_id=t.id AND ta2.user_id=?))';
    $params[] = $user['id'];
    $params[] = $user['id'];
}

$tasks = dbFetchAll(
    "SELECT t.*, p.name as project_name
     FROM tasks t
     JOIN projects p ON p.id=t.project_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY FIELD(t.priority,'high','medium','low'), ISNULL(t.due_date), t.due_date ASC",
    $params
);

// Batch-fetch assignees to avoid N+1
$taskIds     = array_column($tasks, 'id');
$assigneeMap = [];
if ($taskIds) {
    $phs  = implode(',', array_fill(0, count($taskIds), '?'));
    $rows = dbFetchAll(
        "SELECT ta.task_id, u.full_name
         FROM task_assignees ta JOIN users u ON u.id=ta.user_id
         WHERE ta.task_id IN ($phs) ORDER BY u.full_name",
        $taskIds
    );
    foreach ($rows as $r) $assigneeMap[$r['task_id']][] = $r['full_name'];
}

// Group by status
$cols = ['todo' => [], 'in_progress' => [], 'review' => [], 'done' => []];
foreach ($tasks as $t) {
    if (isset($cols[$t['status']])) $cols[$t['status']][] = $t;
}

$labels  = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'Review', 'done' => 'Done'];
$accents = ['todo' => 'var(--text-muted)', 'in_progress' => 'var(--warning)', 'review' => '#7C3AED', 'done' => 'var(--success)'];

layoutStart('Board', 'tasks');
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Board</h1>
        <p class="page-subtitle"><?= count($tasks) ?> task<?= count($tasks) !== 1 ? 's' : '' ?></p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <select onchange="location='?project_id='+this.value" class="form-control" style="width:auto">
            <option value="">All Projects</option>
            <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $filterProject === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach ?>
        </select>
        <a href="<?= BASE_URL ?>/modules/tasks/index.php<?= $filterProject ? '?project_id='.$filterProject : '' ?>" class="btn btn-secondary btn-sm">List View</a>
        <a href="<?= BASE_URL ?>/modules/tasks/create.php<?= $filterProject ? '?project_id='.$filterProject : '' ?>" class="btn btn-primary btn-sm">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Task
        </a>
    </div>
</div>

<div class="kanban" id="kanban">
<?php foreach ($cols as $status => $cards): ?>
<div class="kanban-col" id="col-<?= $status ?>"
     ondragover="event.preventDefault()"
     ondrop="dropCard(event,'<?= $status ?>')">
    <div class="kanban-col-header">
        <span class="kanban-col-title" style="color:<?= $accents[$status] ?>"><?= $labels[$status] ?></span>
        <span class="kanban-count" id="cnt-<?= $status ?>"><?= count($cards) ?></span>
    </div>
    <?php foreach ($cards as $t):
        $overdue  = $t['due_date'] && $t['status'] !== 'done' && strtotime($t['due_date']) < time();
        $initials = $assigneeMap[$t['id']] ?? [];
    ?>
    <div class="task-card" draggable="true"
         data-id="<?= $t['id'] ?>"
         ondragstart="dragStart(event,<?= $t['id'] ?>)">
        <div class="task-card-title"
             onclick="location='<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>'"
             style="cursor:pointer"><?= e($t['title']) ?></div>
        <div class="task-card-meta">
            <span class="badge badge-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span>
            <?php if (!$filterProject): ?>
            <span><?= e($t['project_name']) ?></span>
            <?php endif ?>
            <?php if ($t['due_date']): ?>
            <span class="<?= $overdue ? 'due-overdue' : '' ?>">
                <?= $overdue ? '⚠ ' : '' ?><?= date('M j', strtotime($t['due_date'])) ?>
            </span>
            <?php endif ?>
            <?php if ($initials): ?>
            <span class="avatar-stack" style="margin-left:auto">
                <?php foreach (array_slice($initials, 0, 3) as $fullName): ?>
                <span class="avatar avatar-sm" style="background:<?= avatarColor($fullName) ?>" title="<?= e($fullName) ?>"><?= userInitials($fullName) ?></span>
                <?php endforeach ?>
            </span>
            <?php endif ?>
        </div>
    </div>
    <?php endforeach ?>
    <a href="<?= BASE_URL ?>/modules/tasks/create.php<?= $filterProject ? '?project_id='.$filterProject : '' ?>" class="kanban-add">+ Add task</a>
</div>
<?php endforeach ?>
</div>

<script>
let dragId  = null;
let srcColId = null;

function dragStart(e, id) {
    dragId   = id;
    srcColId = e.target.closest('.kanban-col').id;
    e.dataTransfer.effectAllowed = 'move';
    setTimeout(() => { const c = document.querySelector('[data-id="'+id+'"]'); if(c) c.style.opacity='.4'; }, 0);
}

function dropCard(e, newStatus) {
    e.preventDefault();
    if (!dragId) return;
    const card   = document.querySelector('[data-id="'+dragId+'"]');
    const oldCol = document.getElementById(srcColId);
    const newCol = document.getElementById('col-'+newStatus);
    if (!card || !oldCol || !newCol || oldCol === newCol) {
        if (card) card.style.opacity = '1';
        dragId = null; return;
    }
    // Optimistic move
    newCol.insertBefore(card, newCol.querySelector('.kanban-add'));
    card.style.opacity = '1';
    recount(oldCol); recount(newCol);

    const id = dragId;
    dragId = null;

    fetch('', { method: 'POST', body: new URLSearchParams({task_id: id, status: newStatus}) })
        .then(r => r.json())
        .then(d => {
            if (!d.ok) {
                // Revert
                oldCol.insertBefore(card, oldCol.querySelector('.kanban-add'));
                recount(oldCol); recount(newCol);
            }
        });
}

function recount(col) {
    const key = col.id.replace('col-', '');
    document.getElementById('cnt-'+key).textContent = col.querySelectorAll('.task-card').length;
}

document.addEventListener('dragend', e => {
    if (e.target.classList.contains('task-card')) e.target.style.opacity = '1';
});
</script>
<?php layoutEnd(); ?>
