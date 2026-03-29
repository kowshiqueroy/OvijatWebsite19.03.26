<?php
require_once 'config.php';
requireLogin();

$conn->query("CREATE TABLE IF NOT EXISTS todo_recurring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    agent_id INT NOT NULL,
    recurrence_type ENUM('daily','weekly','monthly','yearly') NOT NULL,
    recurrence_interval INT DEFAULT 1,
    recurrence_days SET('mon','tue','wed','thu','fri','sat','sun') DEFAULT NULL,
    recurrence_time TIME DEFAULT NULL,
    next_due DATETIME NOT NULL,
    active TINYINT(1) DEFAULT 1,
    skip_until DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES todos(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(id)
)");

$result = $conn->query("SHOW COLUMNS FROM todo_recurring LIKE 'skip_until'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE todo_recurring ADD COLUMN skip_until DATE DEFAULT NULL AFTER active");
}

$pageTitle  = 'Tasks';
$activePage = 'todos';
$aid        = agentId();

$f_view     = $_GET['view'] ?? 'me';
$f_status   = $_GET['status'] ?? '';
$f_priority = $_GET['priority'] ?? '';
$f_search   = trim($_GET['q'] ?? '');
$viewId     = (int)($_GET['id'] ?? 0);
$openNew    = isset($_GET['new']);

$recurringTasks = $conn->query("
    SELECT tr.*, t.title, t.description, t.priority, t.contact_id, t.call_id, t.created_by
    FROM todo_recurring tr
    JOIN todos t ON t.id = tr.task_id
    WHERE tr.active = 1 
    AND (tr.skip_until IS NULL OR tr.skip_until <= CURDATE())
    AND tr.next_due <= NOW()
");
while ($rt = $recurringTasks->fetch_assoc()) {
    $today = date('Y-m-d');
    $checkExists = $conn->query("SELECT id FROM todos WHERE title='{$rt['title']}' AND assigned_to={$rt['agent_id']} AND DATE(due_date)='$today' LIMIT 1");
    if ($checkExists->num_rows > 0) {
        $newDue = date('Y-m-d H:i:s', strtotime("+{$rt['recurrence_interval']} " . $rt['recurrence_type'], strtotime($rt['next_due'])));
        $conn->query("UPDATE todo_recurring SET next_due = '$newDue' WHERE id = {$rt['id']}");
        continue;
    }
    
    $dueDate = $rt['recurrence_time'] ? date('Y-m-d', strtotime($rt['next_due'])) . ' ' . $rt['recurrence_time'] : $rt['next_due'];
    $conn->query("INSERT INTO todos (title, description, assigned_to, created_by, priority, contact_id, call_id, due_date, status) 
        VALUES ('{$rt['title']}', '{$rt['description']}', {$rt['agent_id']}, {$rt['created_by']}, '{$rt['priority']}', " . ($rt['contact_id']?$rt['contact_id']:'NULL') . ", " . ($rt['call_id']?$rt['call_id']:'NULL') . ", '$dueDate', 'pending')");
    
    $newDue = date('Y-m-d H:i:s', strtotime("+{$rt['recurrence_interval']} " . $rt['recurrence_type'], strtotime($rt['next_due'])));
    $conn->query("UPDATE todo_recurring SET next_due = '$newDue' WHERE id = {$rt['id']}");
}

$agents = getAllAgents();

$dateFilter = $_GET['date'] ?? '';
$showAll = $dateFilter === 'all';
$twoDaysAgo = date('Y-m-d 00:00:00', strtotime('-2 days'));

$statusCounts = [];
foreach (['pending','in_progress','done','cancelled'] as $s) {
    $dateClause = $showAll ? "1=1" : "(t.due_date IS NULL OR t.due_date >= '$twoDaysAgo')";
    $statusCounts[$s] = (int)$conn->query(
        "SELECT COUNT(*) AS c FROM todos t WHERE (t.assigned_to=$aid OR t.created_by=$aid) AND t.status='$s' AND $dateClause"
    )->fetch_assoc()['c'];
}

$dateClause = $showAll ? "1=1" : "(due_date IS NULL OR due_date >= '$twoDaysAgo')";
$myCount = (int)$conn->query(
    "SELECT COUNT(*) AS c FROM todos WHERE (assigned_to=$aid OR created_by=$aid) AND $dateClause"
)->fetch_assoc()['c'];

$allCount = (int)$conn->query("SELECT COUNT(*) AS c FROM todos WHERE $dateClause")->fetch_assoc()['c'];

$where  = ["1=1"];
$params = []; $types = '';

if ($f_view === 'me') {
    $where[] = "(t.assigned_to=$aid OR t.created_by=$aid)";
} elseif ($f_view === 'created') {
    $where[] = "t.created_by=$aid";
}

if ($f_status)   { $where[] = "t.status=?";   $params[] = $f_status;   $types .= 's'; }
if ($f_priority) { $where[] = "t.priority=?"; $params[] = $f_priority; $types .= 's'; }
if ($f_search)   { $where[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $s = "%$f_search%"; $params = array_merge($params,[$s,$s]); $types .= 'ss'; }
if (!$showAll) { $where[] = "(t.due_date IS NULL OR t.due_date >= '$twoDaysAgo')"; }

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

<style>
.kanban-board {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    padding-bottom: 1rem;
    min-height: calc(100vh - 180px);
}
.kanban-column {
    min-width: 300px;
    max-width: 300px;
    background: var(--card);
    border-radius: 12px;
    border: 1px solid var(--border);
    display: flex;
    flex-direction: column;
}
.kanban-header {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}
.kanban-header .count {
    background: var(--border2);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: .75rem;
}
.kanban-body {
    flex: 1;
    padding: .75rem;
    overflow-y: auto;
    max-height: calc(100vh - 260px);
}
.kanban-card {
    background: var(--card2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: .875rem;
    margin-bottom: .75rem;
    cursor: pointer;
    transition: all .2s ease;
}
.kanban-card:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}
.kanban-card.todo-done {
    opacity: .6;
}
.kanban-card.todo-done .kanban-title {
    text-decoration: line-through;
}
.kanban-title {
    font-weight: 500;
    margin-bottom: .5rem;
    line-height: 1.4;
}
.kanban-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    font-size: .75rem;
}
.kanban-priority {
    font-size: .65rem;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    font-weight: 600;
}
.priority-urgent { background: #fee2e2; color: #dc2626; }
.priority-high { background: #ffedd5; color: #ea580c; }
.priority-medium { background: #dbeafe; color: #2563eb; }
.priority-low { background: #f1f5f9; color: #64748b; }
.recurring-badge {
    font-size: .6rem;
    background: var(--accent);
    color: white;
    padding: 2px 5px;
    border-radius: 4px;
    margin-left: .5rem;
}
.kanban-assignee {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    margin-top: .5rem;
}
.kanban-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--accent);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .65rem;
    font-weight: 600;
}
.kanban-due {
    font-size: .7rem;
    display: flex;
    align-items: center;
    gap: .25rem;
}
.kanban-due.overdue {
    color: #ef4444;
}
.view-tabs {
    display: flex;
    gap: .25rem;
    background: var(--card);
    padding: .25rem;
    border-radius: 8px;
    border: 1px solid var(--border);
    margin-bottom: 1rem;
}
.view-tab {
    padding: .5rem 1rem;
    border-radius: 6px;
    color: var(--muted);
    text-decoration: none;
    font-weight: 500;
    transition: all .2s;
}
.view-tab:hover {
    color: var(--text);
    background: var(--card2);
}
.view-tab.active {
    background: var(--accent);
    color: white;
}
.agent-check-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: .5rem;
}
.agent-check-item {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .375rem .5rem;
    border-radius: 4px;
    cursor: pointer;
}
.agent-check-item:hover {
    background: var(--card2);
}
.agent-check-item input {
    accent-color: var(--accent);
}
.agent-check-item .avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--accent);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .7rem;
    font-weight: 600;
}
</style>

<div class="view-tabs">
    <a href="?view=me" class="view-tab <?= $f_view==='me'?'active':'' ?>">
        <i class="fas fa-user me-1"></i>My Tasks <span class="badge bg-light text-dark"><?= $myCount ?></span>
    </a>
    <a href="?view=all" class="view-tab <?= $f_view==='all'?'active':'' ?>">
        <i class="fas fa-list me-1"></i>All Tasks <span class="badge bg-light text-dark"><?= $allCount ?></span>
    </a>
    <a href="?view=created" class="view-tab <?= $f_view==='created'?'active':'' ?>">
        <i class="fas fa-plus-circle me-1"></i>Created by Me
    </a>
</div>

<div class="cc-card-head mb-3" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
    <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
        <?php if ($f_view): ?><input type="hidden" name="view" value="<?= e($f_view) ?>"><?php endif; ?>
        <input type="text" name="q" class="form-control form-control-sm" style="max-width:180px"
               placeholder="Search tasks…" value="<?= e($f_search) ?>">
        <select name="priority" class="form-select form-select-sm" style="width:auto">
            <option value="">All Priority</option>
            <?php foreach (['urgent','high','medium','low'] as $p): ?>
            <option value="<?= $p ?>" <?= $f_priority===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-select form-select-sm" style="width:auto">
            <option value="">All Status</option>
            <?php foreach (['pending','in_progress','done','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $f_status===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="date" class="form-select form-select-sm" style="width:auto">
            <option value="">Last 2 Days + Future</option>
            <option value="all" <?= $dateFilter==='all'?'selected':'' ?>>Show All</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
        <a href="?view=<?= $f_view ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
    </form>
    <button class="btn btn-primary btn-sm ms-auto" onclick="openNewTaskModal()">
        <i class="fas fa-plus me-1"></i>New Task
    </button>
</div>

<div class="kanban-board">
    <?php 
    $columns = [
        'in_progress' => ['label' => 'In Progress', 'color' => 'info'],
        'pending' => ['label' => 'Pending', 'color' => 'warning'],
        'done' => ['label' => 'Done', 'color' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'secondary']
    ];
    
    $todos_by_status = [];
    $todos->data_seek(0);
    while ($t = $todos->fetch_assoc()) {
        $todos_by_status[$t['status']][] = $t;
    }
    
    foreach ($columns as $colStatus => $colInfo): 
        $colTodos = $todos_by_status[$colStatus] ?? [];
    ?>
    <div class="kanban-column">
        <div class="kanban-header">
            <span><i class="fas fa-<?= $colStatus=='in_progress'?'play':($colStatus=='pending'?'clock':($colStatus=='done'?'check':'times')) ?> me-2"></i><?= $colInfo['label'] ?></span>
            <span class="count"><?= count($colTodos) ?></span>
        </div>
        <div class="kanban-body">
            <?php foreach ($colTodos as $t): ?>
            <div class="kanban-card <?= $t['status']==='done'?'todo-done':'' ?>" 
                 onclick="viewTask(<?= $t['id'] ?>)">
                <div class="kanban-title">
                    <?= e($t['title']) ?>
                    <?php 
                    $isRecurring = $conn->query("SELECT id FROM todo_recurring WHERE task_id={$t['id']} AND active=1")->num_rows > 0;
                    if ($isRecurring): ?>
                    <span class="recurring-badge"><i class="fas fa-redo"></i></span>
                    <?php endif; ?>
                </div>
                <div class="kanban-meta">
                    <span class="kanban-priority priority-<?= $t['priority'] ?>"><?= $t['priority'] ?></span>
                    <?php if ($t['due_date']): ?>
                    <span class="kanban-due <?= strtotime($t['due_date']) < time() && $t['status'] !== 'done' ? 'overdue' : '' ?>">
                        <i class="fas fa-clock"></i> <?= date('M d', strtotime($t['due_date'])) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($t['log_count']): ?>
                    <span class="text-muted"><i class="fas fa-comment"></i> <?= $t['log_count'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="kanban-assignee">
                    <div class="kanban-avatar" title="<?= e($t['assigned_name']) ?>"><?= strtoupper(substr($t['assigned_name'],0,1)) ?></div>
                    <span class="text-muted" style="font-size:.7rem"><?= e($t['assigned_name']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($colTodos)): ?>
            <div class="text-center text-muted py-4">No tasks</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($viewTask): ?>
<div class="modal fade show" id="taskDetailModal" tabindex="-1" style="display:block;background:rgba(0,0,0,0.5)">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <span>
                    <span class="badge bg-<?= priorityClass($viewTask['priority']) ?> me-2"><?= $viewTask['priority'] ?></span>
                    <span class="badge bg-<?= ['pending'=>'warning','in_progress'=>'info','done'=>'success','cancelled'=>'secondary'][$viewTask['status']] ?> me-2"><?= ucwords(str_replace('_',' ',$viewTask['status'])) ?></span>
                    Task #<?= $viewTask['id'] ?>
                </span>
                <a href="todos.php?view=<?= $f_view ?><?= $f_status?"&status=$f_status":'' ?>" class="btn-close btn-close-white"></a>
            </div>
            <div class="modal-body">
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
                            <a href="<?= APP_URL ?>/contact_detail.php?id=<?= $viewTask['contact_id'] ?>"><?= e($viewTask['contact_name']) ?></a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($viewTask['call_src']): ?>
                    <div class="detail-item">
                        <div class="detail-label">Call</div>
                        <div class="detail-value">
                            <a href="<?= APP_URL ?>/call_detail.php?id=<?= $viewTask['call_id'] ?>"><?= phoneLink($viewTask['call_src']) ?></a>
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
                    <?php 
                    $recurring = $conn->query("SELECT * FROM todo_recurring WHERE task_id={$viewTask['id']} AND active=1")->fetch_assoc();
                    if ($recurring): ?>
                    <div class="detail-item">
                        <div class="detail-label">Recurrence</div>
                        <div class="detail-value">
                            <span class="badge bg-info"><i class="fas fa-redo me-1"></i><?= ucfirst($recurring['recurrence_type']) ?></span>
                            every <?= $recurring['recurrence_interval'] ?> 
                            <?= $recurring['recurrence_interval'] > 1 ? $recurring['recurrence_type'].'s' : $recurring['recurrence_type'] ?>
                            <?php if ($recurring['recurrence_time']): ?> at <?= date('H:i', strtotime($recurring['recurrence_time'])) ?><?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Skip / Holiday</div>
                        <div class="detail-value">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <input type="date" id="skipUntil" class="form-control form-control-sm" style="width:auto" 
                                       min="<?= date('Y-m-d') ?>" value="<?= $recurring['skip_until'] ?? '' ?>">
                                <button class="btn btn-sm btn-outline-warning" onclick="skipRecurrence(<?= $viewTask['id'] ?>)">
                                    <i class="fas fa-calendar-minus me-1"></i>Skip Until
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="stopRecurrence(<?= $viewTask['id'] ?>)">
                                    <i class="fas fa-stop me-1"></i>Stop
                                </button>
                            </div>
                            <?php if ($recurring['skip_until']): ?>
                            <small class="text-warning">Skipped until <?= date('M d, Y', strtotime($recurring['skip_until'])) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2 flex-wrap mb-4">
                    <?php foreach (['pending','in_progress','done','cancelled'] as $s): ?>
                    <button class="btn btn-sm btn-<?= $viewTask['status']===$s?'primary':'outline-secondary' ?>"
                            onclick="updateStatus(<?= $viewTask['id'] ?>,'<?= $s ?>')">
                        <?= ucwords(str_replace('_',' ',$s)) ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Add Comment / Update</label>
                    <div class="d-flex gap-2">
                        <textarea id="commentText" class="form-control" rows="2" placeholder="Note about this task…"></textarea>
                        <button class="btn btn-primary btn-sm" onclick="addComment(<?= $viewId ?>)">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>

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
</div>
<?php endif; ?>

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
                    <div class="col-12">
                        <label class="form-label">Assign To (select multiple to create separate tasks)</label>
                        <div class="agent-check-list">
                            <?php foreach ($agents as $ag): ?>
                            <label class="agent-check-item">
                                <input type="checkbox" name="tmAssign[]" value="<?= $ag['id'] ?>" <?= $ag['id']==$aid?'checked':'' ?>>
                                <span class="avatar"><?= strtoupper(substr($ag['full_name'],0,1)) ?></span>
                                <span><?= e($ag['full_name']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Priority</label>
                        <select id="tmPriority" class="form-select">
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Due Date</label>
                        <input type="datetime-local" id="tmDue" class="form-control">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Repeat</label>
                        <select id="tmRecurrence" class="form-select" onchange="toggleRecurrenceOptions()">
                            <option value="">No Repeat</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="col-12" id="recurrenceOptions" style="display:none">
                        <div class="row g-2">
                            <div class="col-sm-4">
                                <label class="form-label small">Every</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" id="tmRecurInterval" class="form-control" value="1" min="1" style="width:60px">
                                    <span class="input-group-text" id="recurIntervalLabel">day(s)</span>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label small">Time</label>
                                <input type="time" id="tmRecurTime" class="form-control form-control-sm">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label small">End Date</label>
                                <input type="date" id="tmRecurEnd" class="form-control form-control-sm">
                            </div>
                        </div>
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

function viewTask(id) { 
    const params = new URLSearchParams(window.location.search);
    let base = 'todos.php?id=' + id;
    if (params.get('view')) base += '&view=' + params.get('view');
    if (params.get('status')) base += '&status=' + params.get('status');
    window.location = base; 
}

function updateStatus(id, status) {
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'status', id, status})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) { showToast('Status updated','success'); setTimeout(()=>location.reload(),500); }
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
    document.getElementById('tmPriority').value = 'medium';
    document.getElementById('tmRecurrence').value = '';
    document.getElementById('tmRecurInterval').value = 1;
    document.getElementById('tmRecurTime').value = '';
    document.getElementById('tmRecurEnd').value = '';
    toggleRecurrenceOptions();
    document.querySelectorAll('input[name="tmAssign[]"]').forEach(cb => cb.checked = (cb.value == '<?= $aid ?>'));
    new bootstrap.Modal(document.getElementById('newTaskModal')).show();
    setTimeout(()=>document.getElementById('tmTitle').focus(),300);
}

function toggleRecurrenceOptions() {
    const recurType = document.getElementById('tmRecurrence').value;
    const optionsDiv = document.getElementById('recurrenceOptions');
    const intervalLabel = document.getElementById('recurIntervalLabel');
    if (recurType) {
        optionsDiv.style.display = 'block';
        switch (recurType) {
            case 'daily': intervalLabel.textContent = 'day(s)'; break;
            case 'weekly': intervalLabel.textContent = 'week(s)'; break;
            case 'monthly': intervalLabel.textContent = 'month(s)'; break;
            case 'yearly': intervalLabel.textContent = 'year(s)'; break;
        }
    } else {
        optionsDiv.style.display = 'none';
    }
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
            document.getElementById('tmPriority').value  = t.priority;
            document.getElementById('tmDue').value       = t.due_date ? t.due_date.replace(' ','T').slice(0,16) : '';
            
            document.querySelectorAll('input[name="tmAssign[]"]').forEach(cb => {
                cb.checked = (parseInt(cb.value) === parseInt(t.assigned_to));
            });
            
            new bootstrap.Modal(document.getElementById('newTaskModal')).show();
        });
}

function saveTask() {
    const id    = document.getElementById('tmEditId').value;
    const title = document.getElementById('tmTitle').value.trim();
    if (!title) { showToast('Title required','warning'); return; }
    
    const selectedAssignees = Array.from(document.querySelectorAll('input[name="tmAssign[]"]:checked')).map(cb => parseInt(cb.value));
    if (selectedAssignees.length === 0) { showToast('Select at least one assignee','warning'); return; }
    
    const action = id === '0' ? 'create' : 'edit';
    const dueDate = document.getElementById('tmDue').value;
    const recurType = document.getElementById('tmRecurrence').value;
    
    const data = {
        action, id: parseInt(id) || undefined,
        title,
        description: document.getElementById('tmDesc').value.trim(),
        assigned_to: selectedAssignees.join(','),
        priority:    document.getElementById('tmPriority').value,
        due_date:    dueDate,
        recurrence_type: recurType || null,
        recurrence_interval: recurType ? parseInt(document.getElementById('tmRecurInterval').value) : null,
        recurrence_time: recurType && document.getElementById('tmRecurTime').value ? document.getElementById('tmRecurTime').value : null,
    };
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('newTaskModal')).hide();
            const msg = action==='create' 
                ? (d.count > 1 ? `Created ${d.count} tasks` : 'Task created') 
                : 'Task updated';
            showToast(msg,'success');
            setTimeout(()=>location.reload(),600);
        } else showToast(d.error,'danger');
    });
}

function stopRecurrence(taskId) {
    if (!confirm('Stop this task from recurring?')) return;
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'stop_recurrence', id: taskId})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) { showToast('Recurrence stopped','success'); setTimeout(()=>location.reload(),500); }
        else showToast(d.error,'danger');
    });
}

function skipRecurrence(taskId) {
    const skipUntil = document.getElementById('skipUntil').value;
    if (!skipUntil) { showToast('Select a date to skip until','warning'); return; }
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'skip_recurrence', id: taskId, skip_until: skipUntil})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) { showToast('Recurrence skipped until ' + skipUntil,'success'); setTimeout(()=>location.reload(),500); }
        else showToast(d.error,'danger');
    });
}

<?php if ($openNew): ?>openNewTaskModal();<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
