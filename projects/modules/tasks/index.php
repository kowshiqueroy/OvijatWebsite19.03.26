<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('member');
$user = currentUser();

// AJAX: bulk action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $ids    = array_filter(array_map('intval', json_decode($_POST['ids'] ?? '[]', true)));
    $action = $_POST['bulk_action'];
    $value  = trim($_POST['value'] ?? '');
    if (!$ids) { echo json_encode(['ok' => false, 'error' => 'No tasks selected']); exit; }
    $phs = implode(',', array_fill(0, count($ids), '?'));
    if ($action === 'status' && in_array($value, ['todo','in_progress','review','done'])) {
        dbQuery("UPDATE tasks SET status=? WHERE id IN ($phs)", array_merge([$value], array_values($ids)));
        echo json_encode(['ok' => true]);
    } elseif ($action === 'priority' && in_array($value, ['low','medium','high'])) {
        dbQuery("UPDATE tasks SET priority=? WHERE id IN ($phs)", array_merge([$value], array_values($ids)));
        echo json_encode(['ok' => true]);
    } elseif ($action === 'delete' && $user['role'] === 'admin') {
        dbQuery("UPDATE tasks SET deleted_at=NOW() WHERE id IN ($phs)", array_values($ids));
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// AJAX: inline status update from task list
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

$filterStatus   = $_GET['status'] ?? '';
$filterPriority = $_GET['priority'] ?? '';
$filterProject  = (int)($_GET['project_id'] ?? 0);
$search         = trim($_GET['q'] ?? '');

$where = ['t.deleted_at IS NULL'];
$params = [];

if ($user['role'] !== 'admin') {
    $where[] = '(ta.user_id=? OR t.created_by=?)';
    $params[] = $user['id']; $params[] = $user['id'];
}
if ($filterStatus)   { $where[] = 't.status=?'; $params[] = $filterStatus; }
if ($filterPriority) { $where[] = 't.priority=?'; $params[] = $filterPriority; }
if ($filterProject)  { $where[] = 't.project_id=?'; $params[] = $filterProject; }
if ($search) {
    $safe = str_replace(['\\','%','_'], ['\\\\','\%','\_'], $search);
    $where[] = 't.title LIKE ?'; $params[] = "%$safe%";
}

$tasks = dbFetchAll(
    "SELECT DISTINCT t.*, p.name as project_name FROM tasks t
     LEFT JOIN task_assignees ta ON ta.task_id=t.id
     JOIN projects p ON p.id=t.project_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY FIELD(t.status,'in_progress','review','todo','done'), t.priority DESC, t.due_date ASC",
    $params
);

// Projects for filter dropdown
$projectsForFilter = $user['role'] === 'admin'
    ? dbFetchAll("SELECT id, name FROM projects ORDER BY name")
    : dbFetchAll("SELECT p.id, p.name FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? ORDER BY p.name", [$user['id']]);

// Assignees per task
$taskIds = array_column($tasks, 'id');
$assigneeMap = [];
if ($taskIds) {
    $phs = implode(',', array_fill(0, count($taskIds), '?'));
    $rows = dbFetchAll("SELECT ta.task_id, u.full_name FROM task_assignees ta JOIN users u ON u.id=ta.user_id WHERE ta.task_id IN ($phs)", $taskIds);
    foreach ($rows as $r) $assigneeMap[$r['task_id']][] = $r['full_name'];
}

layoutStart('Tasks', 'tasks');
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Tasks</h1>
        <p class="page-subtitle"><?= count($tasks) ?> task<?= count($tasks) !== 1 ? 's' : '' ?></p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/gantt.php<?= $filterProject ? '?project_id='.$filterProject : '' ?>" class="btn btn-secondary">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>
            Timeline
        </a>
        <a href="<?= BASE_URL ?>/modules/tasks/board.php<?= $filterProject ? '?project_id='.$filterProject : '' ?>" class="btn btn-secondary">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="4" height="18"/><rect x="10" y="3" width="4" height="18"/><rect x="17" y="3" width="4" height="18"/></svg>
            Board
        </a>
        <a href="<?= BASE_URL ?>/export.php?type=tasks<?= $filterProject ? '&project_id='.$filterProject : '' ?><?= $filterStatus ? '&status='.$filterStatus : '' ?><?= $filterPriority ? '&priority='.$filterPriority : '' ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-secondary" title="Export visible tasks as CSV">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            CSV
        </a>
        <a href="<?= BASE_URL ?>/modules/tasks/create.php" class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Task
        </a>
    </div>
</div>

<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
    <input name="q" value="<?= e($search) ?>" placeholder="Search tasks..." class="form-control" style="max-width:200px">
    <select name="project_id" class="form-control" style="max-width:180px" onchange="this.form.submit()">
        <option value="">All Projects</option>
        <?php foreach ($projectsForFilter as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $filterProject === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach ?>
    </select>
    <select name="status" class="form-control" style="max-width:150px" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <?php foreach (['todo','in_progress','review','done'] as $s): ?>
        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
        <?php endforeach ?>
    </select>
    <select name="priority" class="form-control" style="max-width:130px" onchange="this.form.submit()">
        <option value="">All Priorities</option>
        <?php foreach (['high','medium','low'] as $p): ?>
        <option value="<?= $p ?>" <?= $filterPriority === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
        <?php endforeach ?>
    </select>
    <button class="btn btn-secondary">Search</button>
    <?php if ($filterStatus || $filterPriority || $filterProject || $search): ?><a href="?" class="btn btn-ghost">Clear</a><?php endif ?>
</form>

<div class="card">
<div class="table-wrap">
<table>
<thead>
<tr>
    <th style="width:36px"><input type="checkbox" id="selAll" title="Select all"></th>
    <th>Title</th><th>Project</th><th>Status</th><th>Priority</th><th>Assignees</th><th>Due</th><th></th>
</tr>
</thead>
<tbody>
<?php if ($tasks): foreach ($tasks as $t):
    $overdue = $t['due_date'] && $t['due_date'] < date('Y-m-d') && $t['status'] !== 'done';
    $assignees = $assigneeMap[$t['id']] ?? [];
?>
<tr data-id="<?= $t['id'] ?>">
    <td><input type="checkbox" class="row-cb" value="<?= $t['id'] ?>" onchange="onCheck()"></td>
    <td><a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>" style="font-weight:500;color:var(--text)"><?= e($t['title']) ?></a>
        <?php if ($t['parent_task_id']): ?><span style="font-size:.7rem;color:var(--text-muted);margin-left:4px">subtask</span><?php endif ?>
    </td>
    <td><a href="<?= BASE_URL ?>/modules/projects/view.php?id=<?= $t['project_id'] ?>" style="font-size:.8125rem"><?= e($t['project_name']) ?></a></td>
    <td>
        <select class="status-select" data-task="<?= $t['id'] ?>" data-orig="<?= $t['status'] ?>" onchange="updateStatus(this)">
            <?php foreach (['todo','in_progress','review','done'] as $s): ?>
            <option value="<?= $s ?>" <?= $t['status'] === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
            <?php endforeach ?>
        </select>
    </td>
    <td><span class="badge badge-<?= e($t['priority']) ?>"><?= ucfirst($t['priority']) ?></span></td>
    <td>
        <div class="avatar-stack">
        <?php foreach (array_slice($assignees,0,3) as $a): ?><div class="avatar avatar-sm" style="background:<?= avatarColor($a) ?>" title="<?= e($a) ?>"><?= userInitials($a) ?></div><?php endforeach ?>
        </div>
    </td>
    <td style="font-size:.8125rem;color:<?= $overdue ? 'var(--danger)' : 'var(--text-muted)' ?>;white-space:nowrap">
        <?= $t['due_date'] ? ($overdue ? 'Overdue · '.date('M j',strtotime($t['due_date'])) : date('M j, Y',strtotime($t['due_date']))) : '—' ?>
    </td>
    <td>
        <div class="table-actions">
            <a href="<?= BASE_URL ?>/modules/tasks/edit.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Edit">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </a>
            <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="View">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </a>
        </div>
    </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="8"><div class="empty-state"><p>No tasks found.</p></div></td></tr>
<?php endif ?>
</tbody>
</table>
</div>
</div>

<!-- Bulk action bar -->
<div id="bulkBar" style="display:none;position:fixed;bottom:28px;left:50%;transform:translateX(-50%);
  background:var(--text);color:#fff;border-radius:12px;padding:10px 16px;
  display:none;align-items:center;gap:10px;box-shadow:0 4px 24px rgba(0,0,0,.25);
  z-index:300;white-space:nowrap;font-size:.875rem">
    <span id="bulkCount" style="font-weight:600;min-width:80px"></span>
    <span style="width:1px;height:20px;background:rgba(255,255,255,.2)"></span>
    <select id="bulkStatusSel" class="bulk-sel">
        <option value="">Set status…</option>
        <?php foreach (['todo','in_progress','review','done'] as $s): ?>
        <option value="<?= $s ?>"><?= ucwords(str_replace('_',' ',$s)) ?></option>
        <?php endforeach ?>
    </select>
    <select id="bulkPrioritySel" class="bulk-sel">
        <option value="">Set priority…</option>
        <?php foreach (['high','medium','low'] as $p): ?>
        <option value="<?= $p ?>"><?= ucfirst($p) ?></option>
        <?php endforeach ?>
    </select>
    <?php if ($user['role'] === 'admin'): ?>
    <button onclick="bulkDelete()" style="background:#EF4444;border:none;color:#fff;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:.8125rem;font-weight:500">
        Delete
    </button>
    <?php endif ?>
    <button onclick="clearSel()" style="background:none;border:none;color:rgba(255,255,255,.6);cursor:pointer;font-size:1.1rem;line-height:1;padding:0 4px">✕</button>
</div>

<style>
.status-select {
  appearance: none; border: none; outline: none; cursor: pointer;
  font-weight: 600; font-size: .7rem; letter-spacing: .02em;
  padding: 3px 10px; border-radius: 20px;
  font-family: inherit; transition: opacity .15s;
}
.status-select:hover { opacity: .8; }
.bulk-sel {
  background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2);
  color: #fff; border-radius: 6px; padding: 5px 10px;
  font-size: .8125rem; cursor: pointer; outline: none;
}
.bulk-sel option { background: #1A1D23; color: #fff; }
tr.selected td { background: rgba(79,107,237,.06); }
</style>
<script>
/* ── Inline status ───────────────────────────────── */
const STATUS_STYLE = {
  todo:        { bg:'#F3F4F6', color:'#6B7280' },
  in_progress: { bg:'#FEF3C7', color:'#92400E' },
  review:      { bg:'#EDE9FE', color:'#5B21B6' },
  done:        { bg:'#D1FAE5', color:'#065F46' },
};
function applyStyle(sel) {
  const s = STATUS_STYLE[sel.value] || STATUS_STYLE.todo;
  sel.style.background = s.bg; sel.style.color = s.color;
}
function updateStatus(sel) {
  applyStyle(sel);
  fetch('', { method:'POST', body: new URLSearchParams({task_id: sel.dataset.task, status: sel.value}) })
    .then(r => r.json())
    .then(d => {
      if (!d.ok) { sel.value = sel.dataset.orig; applyStyle(sel); }
      else sel.dataset.orig = sel.value;
    })
    .catch(() => { sel.value = sel.dataset.orig; applyStyle(sel); });
}
document.querySelectorAll('.status-select').forEach(applyStyle);

/* ── Bulk selection ──────────────────────────────── */
const bar     = document.getElementById('bulkBar');
const countEl = document.getElementById('bulkCount');
const selAll  = document.getElementById('selAll');

function selectedIds() {
  return [...document.querySelectorAll('.row-cb:checked')].map(c => +c.value);
}
function onCheck() {
  const ids = selectedIds();
  document.querySelectorAll('tbody tr').forEach(tr => {
    tr.classList.toggle('selected', !!tr.querySelector('.row-cb:checked'));
  });
  if (ids.length > 0) {
    bar.style.display = 'flex';
    countEl.textContent = ids.length + ' selected';
  } else {
    bar.style.display = 'none';
  }
  selAll.indeterminate = ids.length > 0 && ids.length < document.querySelectorAll('.row-cb').length;
  selAll.checked = ids.length > 0 && ids.length === document.querySelectorAll('.row-cb').length;
}
selAll.addEventListener('change', function() {
  document.querySelectorAll('.row-cb').forEach(c => { c.checked = this.checked; });
  onCheck();
});
function clearSel() {
  document.querySelectorAll('.row-cb').forEach(c => c.checked = false);
  selAll.checked = false; selAll.indeterminate = false;
  bar.style.display = 'none';
  document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('selected'));
}

/* ── Bulk apply ──────────────────────────────────── */
function bulkApply(action, value) {
  if (!value) return;
  const ids = selectedIds();
  if (!ids.length) return;
  fetch('', {
    method: 'POST',
    body: new URLSearchParams({ bulk_action: action, value, ids: JSON.stringify(ids) })
  }).then(r => r.json()).then(d => {
    if (d.ok) location.reload();
    else alert('Action failed.');
  });
}
function bulkDelete() {
  if (!confirm('Delete ' + selectedIds().length + ' task(s)? This cannot be undone.')) return;
  bulkApply('delete', '1');
}

document.getElementById('bulkStatusSel').addEventListener('change', function() {
  if (this.value) bulkApply('status', this.value);
});
document.getElementById('bulkPrioritySel').addEventListener('change', function() {
  if (this.value) bulkApply('priority', this.value);
});
</script>
<?php layoutEnd(); ?>
