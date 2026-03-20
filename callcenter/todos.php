<?php
require_once 'config.php';
requireLogin();

$pageTitle  = 'Tasks';
$activePage = 'todos';
$aid        = agentId();

$f_status = $_GET['status'] ?? '';
$f_priority= $_GET['priority'] ?? '';
$f_agent  = (int)($_GET['agent'] ?? 0);
$f_search = trim($_GET['q'] ?? '');
$viewId   = (int)($_GET['id'] ?? 0);
$openNew  = isset($_GET['new']);

$agents = getAllAgents();

// ── Counts per status ─────────────────────────────────────────────────────────
$statusCounts = [];
foreach (['pending','in_progress','done','cancelled'] as $s) {
    $statusCounts[$s] = (int)$conn->query(
        "SELECT COUNT(*) AS c FROM todos WHERE (assigned_to=$aid OR created_by=$aid) AND status='$s'"
    )->fetch_assoc()['c'];
}

// ── Build query ───────────────────────────────────────────────────────────────
$where  = ["(t.assigned_to=$aid OR t.created_by=$aid)"];
$params = []; $types = '';

if ($f_status)   { $where[] = "t.status=?";   $params[] = $f_status;   $types .= 's'; }
if ($f_priority) { $where[] = "t.priority=?"; $params[] = $f_priority; $types .= 's'; }
if ($f_agent)    { $where[] = "t.assigned_to=?"; $params[] = $f_agent; $types .= 'i'; }
if ($f_search)   { $where[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $s = "%$f_search%"; $params = array_merge($params,[$s,$s]); $types .= 'ss'; }

$whereSQL = implode(' AND ', $where);

$stmt = $conn->prepare(
    "SELECT t.*, a.full_name AS assigned_name, cb.full_name AS creator_name,
            c.name AS contact_name, cl.src AS call_src,
            (SELECT COUNT(*) FROM todo_logs tl WHERE tl.todo_id=t.id) AS log_count
     FROM todos t
     JOIN agents a  ON a.id  = t.assigned_to
     JOIN agents cb ON cb.id = t.created_by
     LEFT JOIN contacts c  ON c.id  = t.contact_id
     LEFT JOIN call_logs cl ON cl.id = t.call_id
     WHERE $whereSQL
     ORDER BY FIELD(t.status,'in_progress','pending','done','cancelled'),
              FIELD(t.priority,'urgent','high','medium','low'),
              t.due_date ASC, t.created_at DESC"
);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$todos = $stmt->get_result();

// ── Single task view ──────────────────────────────────────────────────────────
$viewTask = null;
$taskLogs = null;
if ($viewId) {
    $viewTask = $conn->query(
        "SELECT t.*, a.full_name AS assigned_name, cb.full_name AS creator_name,
                c.name AS contact_name, cl.src AS call_src
         FROM todos t
         JOIN agents a  ON a.id  = t.assigned_to
         JOIN agents cb ON cb.id = t.created_by
         LEFT JOIN contacts c  ON c.id  = t.contact_id
         LEFT JOIN call_logs cl ON cl.id = t.call_id
         WHERE t.id=$viewId LIMIT 1"
    )->fetch_assoc();
    $taskLogs = $conn->query(
        "SELECT tl.*, a.full_name FROM todo_logs tl JOIN agents a ON a.id=tl.agent_id
         WHERE tl.todo_id=$viewId ORDER BY tl.created_at ASC"
    );
}

require_once 'includes/layout.php';
?>

<!-- Status tabs -->
<div class="quick-tabs mb-3">
    <a href="todos.php" class="qtab <?= !$f_status?'active':'' ?>">
        All <span class="qtab-badge bg-secondary"><?= array_sum($statusCounts) ?></span>
    </a>
    <?php $tabColors = ['pending'=>'warning','in_progress'=>'info','done'=>'success','cancelled'=>'secondary'];
    foreach ($statusCounts as $s => $c): ?>
    <a href="?status=<?= $s ?>" class="qtab <?= $f_status===$s?'active':'' ?>">
        <?= ucwords(str_replace('_',' ',$s)) ?>
        <span class="qtab-badge bg-<?= $tabColors[$s] ?>"><?= $c ?></span>
    </a>
    <?php endforeach; ?>
</div>

<div class="row g-4">

<!-- Task list -->
<div class="col-lg-<?= $viewTask ? '5' : '12' ?>">
    <div class="cc-card-head mb-2" style="display:flex;align-items:center;gap:.5rem">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-center flex-grow-1">
            <?php if ($f_status): ?><input type="hidden" name="status" value="<?= e($f_status) ?>"><?php endif; ?>
            <input type="text" name="q" class="form-control form-control-sm" style="max-width:180px"
                   placeholder="Search tasks…" value="<?= e($f_search) ?>">
            <select name="priority" class="form-select form-select-sm" style="width:auto">
                <option value="">All Priority</option>
                <?php foreach (['urgent','high','medium','low'] as $p): ?>
                <option value="<?= $p ?>" <?= $f_priority===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="agent" class="form-select form-select-sm" style="width:auto">
                <option value="">All Agents</option>
                <option value="<?= $aid ?>">Me</option>
                <?php foreach ($agents as $ag): ?>
                <option value="<?= $ag['id'] ?>" <?= $f_agent==(int)$ag['id']?'selected':'' ?>><?= e($ag['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="todos.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </form>
        <button class="btn btn-primary btn-sm" onclick="openNewTaskModal()">
            <i class="fas fa-plus me-1"></i>New Task
        </button>
    </div>

    <div class="cc-card">
        <?php if ($todos->num_rows === 0): ?>
        <div class="empty-state py-5"><i class="fas fa-list-check fa-2x mb-2 d-block"></i>No tasks found</div>
        <?php endif; ?>
        <?php while ($t = $todos->fetch_assoc()):
            $isMine    = $t['assigned_to'] == $aid;
            $isCreator = $t['created_by']  == $aid;
        ?>
        <div class="todo-item <?= $t['id']==$viewId?'todo-active':'' ?> <?= $t['status']==='done'?'todo-done':'' ?>"
             onclick="viewTask(<?= $t['id'] ?>)">
            <div class="todo-check-wrap">
                <input type="checkbox" class="todo-check"
                       <?= $t['status']==='done'?'checked':'' ?>
                       onchange="updateStatus(<?= $t['id'] ?>, this.checked?'done':'in_progress'); event.stopPropagation();">
            </div>
            <div class="todo-body">
                <div class="todo-title <?= $t['status']==='done'?'text-decoration-line-through text-muted':'' ?>">
                    <?= e($t['title']) ?>
                    <?php if ($t['status']==='in_progress'): ?>
                    <span class="badge bg-info ms-1" style="font-size:.65rem">In Progress</span>
                    <?php endif; ?>
                </div>
                <div class="todo-meta">
                    <span class="badge bg-<?= priorityClass($t['priority']) ?>"><?= $t['priority'] ?></span>
                    <span class="text-muted small">
                        <i class="fas fa-user me-1"></i><?= e($t['assigned_name']) ?>
                        <?php if (!$isMine): ?><em>(assigned by <?= e($t['creator_name']) ?>)</em><?php endif; ?>
                    </span>
                    <?php if ($t['contact_name']): ?>
                    <span class="text-muted small"><i class="fas fa-address-book me-1"></i><?= e($t['contact_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($t['call_src']): ?>
                    <span class="text-muted small"><i class="fas fa-phone me-1"></i><?= e($t['call_src']) ?></span>
                    <?php endif; ?>
                    <?php if ($t['due_date']): ?>
                    <span class="<?= strtotime($t['due_date']) < time() && $t['status'] !== 'done' ? 'text-danger fw-bold' : 'text-muted' ?> small">
                        <i class="fas fa-clock me-1"></i>Due <?= formatDt($t['due_date'],'d M Y, h:i A') ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($t['log_count']): ?>
                    <span class="text-muted small"><i class="fas fa-list me-1"></i><?= $t['log_count'] ?> logs</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="todo-actions" onclick="event.stopPropagation()">
                <button class="btn-sm-icon" title="Edit" onclick="editTaskModal(<?= $t['id'] ?>)">
                    <i class="fas fa-pen"></i>
                </button>
                <?php if ($t['status'] !== 'in_progress' && $t['status'] !== 'done'): ?>
                <button class="btn-sm-icon text-info" title="Start" onclick="updateStatus(<?= $t['id'] ?>,'in_progress')">
                    <i class="fas fa-play"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Task detail panel -->
<?php if ($viewTask): ?>
<div class="col-lg-7">
    <div class="cc-card">
        <div class="cc-card-head">
            <span>
                <span class="badge bg-<?= priorityClass($viewTask['priority']) ?> me-2"><?= $viewTask['priority'] ?></span>
                <span class="badge bg-<?= ['pending'=>'warning','in_progress'=>'info','done'=>'success','cancelled'=>'secondary'][$viewTask['status']] ?> me-2"><?= ucwords(str_replace('_',' ',$viewTask['status'])) ?></span>
                Task #<?= $viewTask['id'] ?>
            </span>
            <a href="todos.php" class="btn-icon" title="Close"><i class="fas fa-times"></i></a>
        </div>
        <div class="cc-card-body">
            <h4 class="mb-2"><?= e($viewTask['title']) ?></h4>
            <?php if ($viewTask['description']): ?>
            <div class="mb-3 text-muted"><?= nl2br(e($viewTask['description'])) ?></div>
            <?php endif; ?>

            <div class="detail-grid mb-3">
                <div class="detail-item">
                    <div class="detail-label">Assigned To</div>
                    <div class="detail-value"><?= e($viewTask['assigned_name']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Created By</div>
                    <div class="detail-value"><?= e($viewTask['creator_name']) ?></div>
                </div>
                <?php if ($viewTask['contact_name']): ?>
                <div class="detail-item">
                    <div class="detail-label">Contact</div>
                    <div class="detail-value">
                        <a href="<?= APP_URL ?>/contact_detail.php?id=<?= $viewTask['contact_id'] ?>">
                            <?= e($viewTask['contact_name']) ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($viewTask['call_src']): ?>
                <div class="detail-item">
                    <div class="detail-label">Call</div>
                    <div class="detail-value">
                        <a href="<?= APP_URL ?>/call_detail.php?id=<?= $viewTask['call_id'] ?>"><?= e($viewTask['call_src']) ?></a>
                    </div>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <div class="detail-label">Due Date</div>
                    <div class="detail-value <?= $viewTask['due_date'] && strtotime($viewTask['due_date']) < time() && $viewTask['status'] !== 'done' ? 'text-danger fw-bold' : '' ?>">
                        <?= $viewTask['due_date'] ? formatDt($viewTask['due_date'],'d M Y, h:i A') : '—' ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Created</div>
                    <div class="detail-value"><?= formatDt($viewTask['created_at']) ?></div>
                </div>
                <?php if ($viewTask['completed_at']): ?>
                <div class="detail-item">
                    <div class="detail-label">Completed</div>
                    <div class="detail-value text-success"><?= formatDt($viewTask['completed_at']) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick status buttons -->
            <div class="d-flex gap-2 flex-wrap mb-4">
                <?php foreach (['pending','in_progress','done','cancelled'] as $s): ?>
                <button class="btn btn-sm btn-<?= $viewTask['status']===$s?'primary':'outline-secondary' ?>"
                        onclick="updateStatus(<?= $viewTask['id'] ?>,'<?= $s ?>')">
                    <?= ucwords(str_replace('_',' ',$s)) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Reassign -->
            <div class="d-flex align-items-center gap-2 mb-4">
                <label class="text-muted small">Reassign to:</label>
                <select id="reassignSelect" class="form-select form-select-sm" style="width:auto">
                    <option value="">— Select —</option>
                    <option value="<?= $aid ?>">Me</option>
                    <?php foreach ($agents as $ag): ?>
                    <option value="<?= $ag['id'] ?>" <?= $viewTask['assigned_to']==(int)$ag['id']?'selected':'' ?>>
                        <?= e($ag['full_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary" onclick="reassign(<?= $viewId ?>)">Assign</button>
            </div>

            <!-- Comment / Log -->
            <div class="mb-3">
                <label class="form-label small">Add Comment / Update</label>
                <div class="d-flex gap-2">
                    <textarea id="commentText" class="form-control" rows="2"
                              placeholder="Note about this task…"></textarea>
                    <button class="btn btn-primary btn-sm" onclick="addComment(<?= $viewId ?>)">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>

            <!-- Activity log -->
            <div>
                <div class="detail-label mb-2">Activity Log</div>
                <div class="task-log-list">
                    <?php if ($taskLogs && $taskLogs->num_rows): ?>
                    <?php while ($log = $taskLogs->fetch_assoc()): ?>
                    <div class="task-log-item">
                        <div class="task-log-avatar"><?= strtoupper(substr($log['full_name'],0,1)) ?></div>
                        <div class="task-log-body">
                            <span class="fw-medium"><?= e($log['full_name']) ?></span>
                            <span class="text-muted small">
                                <?= ucwords(str_replace('_',' ',$log['action'])) ?>
                                <?php if ($log['old_value'] && $log['new_value']): ?>
                                <code><?= e($log['old_value']) ?></code> → <code><?= e($log['new_value']) ?></code>
                                <?php endif; ?>
                            </span>
                            <?php if ($log['notes']): ?><div class="text-muted small mt-1"><?= nl2br(e($log['notes'])) ?></div><?php endif; ?>
                            <div class="text-muted" style="font-size:.75rem"><?= timeAgo($log['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <div class="text-muted small">No activity yet</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div><!-- /.row -->

<!-- New Task Modal -->
<div class="modal fade" id="newTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i><span id="taskModalLabel">New Task</span></h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tmEditId" value="0">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" id="tmTitle" class="form-control" placeholder="What needs to be done?">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Assign To</label>
                        <select id="tmAssign" class="form-select">
                            <option value="<?= $aid ?>">Me</option>
                            <?php foreach ($agents as $ag): ?>
                            <option value="<?= $ag['id'] ?>"><?= e($ag['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Priority</label>
                        <select id="tmPriority" class="form-select">
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Due Date</label>
                        <input type="datetime-local" id="tmDue" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea id="tmDesc" class="form-control" rows="3" placeholder="Details, steps, context…"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveTask()"><i class="fas fa-save me-1"></i>Save Task</button>
            </div>
        </div>
    </div>
</div>

<script>
const APP_URL = '<?= APP_URL ?>';

function viewTask(id) { window.location = 'todos.php?id=' + id + '<?= $f_status?"&status=$f_status":'' ?>'; }
function updateStatus(id, status) {
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'status', id, status})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) { showToast('Status updated','success'); setTimeout(()=>location.reload(),500); }
        else showToast(d.error,'danger');
    });
}
function reassign(id) {
    const to = document.getElementById('reassignSelect').value;
    if (!to) { showToast('Select an agent','warning'); return; }
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'assign', id, assigned_to:parseInt(to)})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) { showToast('Reassigned','success'); setTimeout(()=>location.reload(),500); }
        else showToast(d.error,'danger');
    });
}
function addComment(id) {
    const notes = document.getElementById('commentText').value.trim();
    if (!notes) { showToast('Enter a comment','warning'); return; }
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'comment', id, notes})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) { showToast('Comment added','success'); setTimeout(()=>location.reload(),500); }
        else showToast(d.error,'danger');
    });
}

function openNewTaskModal() {
    document.getElementById('taskModalLabel').textContent = 'New Task';
    document.getElementById('tmEditId').value = '0';
    document.getElementById('tmTitle').value = '';
    document.getElementById('tmDesc').value  = '';
    document.getElementById('tmDue').value   = '';
    document.getElementById('tmAssign').value = '<?= $aid ?>';
    document.getElementById('tmPriority').value = 'medium';
    new bootstrap.Modal(document.getElementById('newTaskModal')).show();
    setTimeout(()=>document.getElementById('tmTitle').focus(),300);
}
function editTaskModal(id) {
    fetch(APP_URL + '/api/todos.php?action=get&id=' + id)
        .then(r=>r.json()).then(d=>{
            if (!d.ok) { showToast('Error','danger'); return; }
            const t = d.task;
            document.getElementById('taskModalLabel').textContent = 'Edit Task';
            document.getElementById('tmEditId').value    = t.id;
            document.getElementById('tmTitle').value     = t.title;
            document.getElementById('tmDesc').value      = t.description || '';
            document.getElementById('tmAssign').value    = t.assigned_to;
            document.getElementById('tmPriority').value  = t.priority;
            document.getElementById('tmDue').value       = t.due_date ? t.due_date.replace(' ','T').slice(0,16) : '';
            new bootstrap.Modal(document.getElementById('newTaskModal')).show();
        });
}
function saveTask() {
    const id    = document.getElementById('tmEditId').value;
    const title = document.getElementById('tmTitle').value.trim();
    if (!title) { showToast('Title required','warning'); return; }
    const action = id === '0' ? 'create' : 'edit';
    const data = {
        action, id: parseInt(id) || undefined,
        title,
        description: document.getElementById('tmDesc').value.trim(),
        assigned_to: document.getElementById('tmAssign').value,
        priority:    document.getElementById('tmPriority').value,
        due_date:    document.getElementById('tmDue').value,
    };
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('newTaskModal')).hide();
            showToast(action==='create'?'Task created':'Task updated','success');
            setTimeout(()=>location.reload(),600);
        } else showToast(d.error,'danger');
    });
}

<?php if ($openNew): ?>openNewTaskModal();<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
