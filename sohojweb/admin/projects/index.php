<?php
require_once __DIR__ . '/../../includes/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$canEdit        = hasPermission('editor');
$canDelete      = hasPermission('super_admin');
$canManageTasks = true; // all logged-in users

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

function generateProjectCode() {
    $row = db()->selectOne("SELECT MAX(CAST(SUBSTRING(project_code, 5) AS UNSIGNED)) as max_num FROM projects");
    $nextNum = ($row['max_num'] ?? 0) + 1;
    return 'PRJ-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

// ── POST: save project ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_project') {
    header('Content-Type: application/json');
    if (!hasPermission('editor')) { jsonResponse(['success' => false, 'message' => 'Permission denied'], 403); }
    try {
        $data = [
            'project_code' => sanitize($_POST['project_code'] ?? generateProjectCode()),
            'project_name' => sanitize($_POST['project_name']),
            'client_name'  => sanitize($_POST['client_name']) ?: null,
            'client_email' => sanitize($_POST['client_email']) ?: null,
            'description'  => sanitize($_POST['description']) ?: null,
            'status'       => sanitize($_POST['status'] ?? 'planning'),
            'priority'     => sanitize($_POST['priority'] ?? 'medium'),
            'start_date'   => sanitize($_POST['start_date']) ?: null,
            'due_date'     => sanitize($_POST['due_date']) ?: null,
            'budget'       => (float)($_POST['budget'] ?? 0),
            'created_by'   => $_SESSION['user_id'] ?? null,
        ];
        $pid = isset($_POST['project_id']) && $_POST['project_id'] ? (int)$_POST['project_id'] : null;
        if ($pid) {
            db()->update('projects', $data, 'id = :id', ['id' => $pid]);
            logAudit('project_updated', 'project', $pid, null, ['name' => $data['project_name']]);
            jsonResponse(['success' => true, 'message' => 'Project updated', 'id' => $pid]);
        } else {
            $newId = db()->insert('projects', $data);
            logAudit('project_created', 'project', $newId, null, ['name' => $data['project_name']]);
            jsonResponse(['success' => true, 'message' => 'Project created', 'id' => $newId]);
        }
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// ── POST: delete project ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_project') {
    header('Content-Type: application/json');
    if (!hasPermission('super_admin')) { jsonResponse(['success' => false, 'message' => 'Permission denied'], 403); }
    try {
        $delId = (int)$_POST['id'];
        db()->delete('project_tasks', 'project_id = :id', ['id' => $delId]);
        db()->delete('projects', 'id = :id', ['id' => $delId]);
        logAudit('project_deleted', 'project', $delId);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// ── POST: toggle portfolio visibility ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_portfolio') {
    header('Content-Type: application/json');
    if (!hasPermission('editor')) { jsonResponse(['success' => false, 'message' => 'Permission denied'], 403); }
    try {
        $pid = (int)$_POST['id'];
        $row = db()->selectOne("SELECT show_in_portfolio FROM projects WHERE id = ?", [$pid]);
        $newVal = $row['show_in_portfolio'] ? 0 : 1;
        db()->update('projects', ['show_in_portfolio' => $newVal], 'id = :id', ['id' => $pid]);
        jsonResponse(['success' => true, 'show_in_portfolio' => $newVal]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// ── POST: save project task ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_project_task') {
    header('Content-Type: application/json');
    if (!hasPermission('editor')) { jsonResponse(['success' => false, 'message' => 'Permission denied'], 403); }
    try {
        $data = [
            'project_id'       => (int)$_POST['project_id'],
            'task_title'       => sanitize($_POST['task_title']),
            'task_description' => sanitize($_POST['task_description']) ?: null,
            'status'           => sanitize($_POST['status'] ?? 'todo'),
            'priority'         => sanitize($_POST['priority'] ?? 'medium'),
            'due_date'         => sanitize($_POST['due_date']) ?: null,
            'assigned_to'      => !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : ($_SESSION['user_id'] ?? null),
        ];
        $tid = isset($_POST['task_id']) && $_POST['task_id'] ? (int)$_POST['task_id'] : null;
        if ($tid) {
            db()->update('project_tasks', $data, 'id = :id', ['id' => $tid]);
            jsonResponse(['success' => true, 'message' => 'Task updated', 'id' => $tid]);
        } else {
            $newId = db()->insert('project_tasks', $data);
            jsonResponse(['success' => true, 'message' => 'Task added', 'id' => $newId]);
        }
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// ── POST: delete project task ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_project_task') {
    header('Content-Type: application/json');
    if (!hasPermission('editor')) { jsonResponse(['success' => false, 'message' => 'Permission denied'], 403); }
    try {
        db()->delete('project_tasks', 'id = :id', ['id' => (int)$_POST['id']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// ── POST: change project task status (all logged in) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'change_task_status') {
    header('Content-Type: application/json');
    try {
        $newStatus = sanitize($_POST['status']);
        $extra = $newStatus === 'done' ? ['status' => $newStatus, 'completed_at' => date('Y-m-d H:i:s')] : ['status' => $newStatus];
        db()->update('project_tasks', $extra, 'id = :id', ['id' => (int)$_POST['id']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// ── POST: save daily task ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_daily_task') {
    header('Content-Type: application/json');
    try {
        $data = [
            'task_title'       => sanitize($_POST['task_title']),
            'task_description' => sanitize($_POST['task_description']) ?: null,
            'task_date'        => sanitize($_POST['task_date'] ?? date('Y-m-d')),
            'due_time'         => sanitize($_POST['due_time']) ?: null,
            'status'           => sanitize($_POST['status'] ?? 'pending'),
            'priority'         => sanitize($_POST['priority'] ?? 'medium'),
            'assigned_to'      => !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : ($_SESSION['user_id'] ?? null),
            'created_by'       => $_SESSION['user_id'] ?? null,
        ];
        $tid = isset($_POST['task_id']) && $_POST['task_id'] ? (int)$_POST['task_id'] : null;
        if ($tid) {
            db()->update('tasks', $data, 'id = :id', ['id' => $tid]);
            jsonResponse(['success' => true, 'message' => 'Task updated', 'id' => $tid]);
        } else {
            $newId = db()->insert('tasks', $data);
            jsonResponse(['success' => true, 'message' => 'Task created', 'id' => $newId]);
        }
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// ── POST: delete daily task ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_daily_task') {
    header('Content-Type: application/json');
    try {
        db()->delete('tasks', 'id = :id', ['id' => (int)$_POST['id']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// ── POST: toggle daily task ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_daily_task') {
    header('Content-Type: application/json');
    try {
        $task = db()->selectOne("SELECT status FROM tasks WHERE id = ?", [(int)$_POST['id']]);
        $newStatus = $task['status'] === 'completed' ? 'pending' : 'completed';
        db()->update('tasks', ['status' => $newStatus], 'id = :id', ['id' => (int)$_POST['id']]);
        jsonResponse(['success' => true, 'new_status' => $newStatus]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// ── Users list for assignment dropdowns ─────────────────────────────────────
$users = db()->select("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name");

// ── GET: project view ───────────────────────────────────────────────────────
$viewProject   = null;
$projectTasks  = [];
if ($action === 'view' && $id) {
    $viewProject  = db()->selectOne("SELECT * FROM projects WHERE id = ?", [$id]);
    $projectTasks = db()->select("SELECT pt.*, u.full_name as assignee_name FROM project_tasks pt LEFT JOIN users u ON pt.assigned_to = u.id WHERE pt.project_id = ? ORDER BY pt.created_at", [$id]);
}

// ── Stats ───────────────────────────────────────────────────────────────────
$today = date('Y-m-d');
$activeProjects    = db()->selectOne("SELECT COUNT(*) as c FROM projects WHERE status IN ('planning','in_progress','review','testing')")['c'] ?? 0;
$completedProjects = db()->selectOne("SELECT COUNT(*) as c FROM projects WHERE status = 'completed'")['c'] ?? 0;
$pendingTasks      = db()->selectOne("SELECT COUNT(*) as c FROM tasks WHERE status != 'completed' AND task_date >= ?", [$today])['c'] ?? 0;
$inProgressPTasks  = db()->selectOne("SELECT COUNT(*) as c FROM project_tasks WHERE status = 'in_progress'")['c'] ?? 0;

// ── Data ────────────────────────────────────────────────────────────────────
$projects     = db()->select("SELECT p.*, (SELECT COUNT(*) FROM project_tasks WHERE project_id=p.id) as task_total, (SELECT COUNT(*) FROM project_tasks WHERE project_id=p.id AND status='done') as task_done FROM projects p ORDER BY p.created_at DESC");
$todayTasks    = db()->select("SELECT t.*, u.full_name as assignee_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.task_date = ? ORDER BY t.due_time", [$today]);
$upcomingTasks = db()->select("SELECT t.*, u.full_name as assignee_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.task_date > ? AND t.status != 'completed' ORDER BY t.task_date, t.due_time LIMIT 20", [$today]);
$overdueTasks  = db()->select("SELECT t.*, u.full_name as assignee_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.task_date < ? AND t.status != 'completed' ORDER BY t.task_date DESC LIMIT 20", [$today]);
$doneTasks     = db()->select("SELECT t.*, u.full_name as assignee_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.status = 'completed' ORDER BY t.task_date DESC LIMIT 30", []);

$pageTitle = 'Project Tracker | SohojWeb Admin';
include __DIR__ . '/../header.php';

// Status badge helpers
function statusBadge($status) {
    $map = [
        'planning'    => 'bg-slate-100 text-slate-700',
        'in_progress' => 'bg-blue-100 text-blue-700',
        'review'      => 'bg-yellow-100 text-yellow-700',
        'testing'     => 'bg-purple-100 text-purple-700',
        'completed'   => 'bg-green-100 text-green-700',
        'on_hold'     => 'bg-orange-100 text-orange-700',
        'cancelled'   => 'bg-red-100 text-red-700',
        'todo'        => 'bg-slate-100 text-slate-600',
        'done'        => 'bg-green-100 text-green-700',
        'pending'     => 'bg-yellow-100 text-yellow-700',
    ];
    $cls = $map[$status] ?? 'bg-gray-100 text-gray-600';
    return "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold $cls\">" . ucwords(str_replace('_', ' ', $status)) . "</span>";
}
function priorityBadge($priority) {
    $map = [
        'urgent' => 'bg-red-100 text-red-700',
        'high'   => 'bg-orange-100 text-orange-700',
        'medium' => 'bg-yellow-100 text-yellow-700',
        'low'    => 'bg-slate-100 text-slate-600',
    ];
    $cls = $map[$priority] ?? 'bg-gray-100 text-gray-600';
    return "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold $cls\">" . ucfirst($priority) . "</span>";
}
?>

<!-- ═══════════════════════ STATS BAR ═══════════════════════ -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center shrink-0">
            <i class="fas fa-folder-open text-blue-600"></i>
        </div>
        <div>
            <p class="text-2xl font-bold text-slate-800"><?= $activeProjects ?></p>
            <p class="text-xs text-slate-500">Active Projects</p>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center shrink-0">
            <i class="fas fa-check-double text-green-600"></i>
        </div>
        <div>
            <p class="text-2xl font-bold text-slate-800"><?= $completedProjects ?></p>
            <p class="text-xs text-slate-500">Completed</p>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 bg-yellow-50 rounded-xl flex items-center justify-center shrink-0">
            <i class="fas fa-tasks text-yellow-600"></i>
        </div>
        <div>
            <p class="text-2xl font-bold text-slate-800"><?= $pendingTasks ?></p>
            <p class="text-xs text-slate-500">Pending Tasks</p>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center shrink-0">
            <i class="fas fa-spinner text-purple-600"></i>
        </div>
        <div>
            <p class="text-2xl font-bold text-slate-800"><?= $inProgressPTasks ?></p>
            <p class="text-xs text-slate-500">Tasks In Progress</p>
        </div>
    </div>
</div>

<!-- ═══════════════════════ TAB BAR ═══════════════════════ -->
<div class="flex gap-1 mb-6 bg-slate-100 p-1 rounded-xl w-fit">
    <button onclick="switchTab('projects')" id="tab-projects" class="tab-btn px-5 py-2 rounded-lg text-sm font-semibold transition-all">
        <i class="fas fa-folder mr-2"></i> Projects
    </button>
    <button onclick="switchTab('tasks')" id="tab-tasks" class="tab-btn px-5 py-2 rounded-lg text-sm font-semibold transition-all">
        <i class="fas fa-calendar-check mr-2"></i> Daily Tasks
    </button>
</div>

<!-- ═══════════════════════ PROJECTS TAB ═══════════════════════ -->
<div id="panel-projects" class="tab-panel">

    <?php if ($action === 'view' && $viewProject): ?>
    <!-- ── PROJECT VIEW ───────────────────────────────────── -->
    <div class="mb-4 flex justify-between items-center">
        <a href="index.php" class="inline-flex items-center text-slate-600 hover:text-blue-600 font-medium text-sm">
            <i class="fas fa-arrow-left mr-2"></i> Back to Projects
        </a>
        <?php if ($canEdit): ?>
        <div class="flex gap-2">
            <button onclick="togglePortfolio(<?= $viewProject['id'] ?>, this)"
                    class="px-4 py-2 rounded-lg text-sm font-medium border transition-colors <?= $viewProject['show_in_portfolio'] ? 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>"
                    title="<?= $viewProject['show_in_portfolio'] ? 'Shown in Portfolio' : 'Hidden from Portfolio' ?>">
                <i class="fas fa-globe mr-1"></i> <?= $viewProject['show_in_portfolio'] ? 'In Portfolio' : 'Add to Portfolio' ?>
            </button>
            <button onclick="openProjectModal(<?= htmlspecialchars(json_encode($viewProject), ENT_QUOTES) ?>)"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                <i class="fas fa-edit mr-1"></i> Edit Project
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Project header card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
        <div class="flex flex-wrap justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest"><?= escape($viewProject['project_code']) ?></span>
                    <?= statusBadge($viewProject['status']) ?>
                    <?= priorityBadge($viewProject['priority']) ?>
                </div>
                <h2 class="text-2xl font-bold text-slate-900 mb-1"><?= escape($viewProject['project_name']) ?></h2>
                <?php if ($viewProject['client_name']): ?>
                <p class="text-slate-500 text-sm"><i class="fas fa-user mr-1"></i> <?= escape($viewProject['client_name']) ?>
                    <?php if ($viewProject['client_email']): ?> &bull; <?= escape($viewProject['client_email']) ?><?php endif; ?>
                </p>
                <?php endif; ?>
                <?php if ($viewProject['description']): ?>
                <p class="text-slate-600 text-sm mt-2 max-w-xl"><?= nl2br(escape($viewProject['description'])) ?></p>
                <?php endif; ?>
            </div>
            <div class="text-sm text-slate-500 space-y-1 shrink-0">
                <?php if ($viewProject['start_date']): ?><p><i class="fas fa-play w-4 text-slate-400"></i> Started: <?= date('M d, Y', strtotime($viewProject['start_date'])) ?></p><?php endif; ?>
                <?php if ($viewProject['due_date']): ?><p><i class="fas fa-flag w-4 text-slate-400"></i> Due: <?= date('M d, Y', strtotime($viewProject['due_date'])) ?></p><?php endif; ?>
                <?php if ($viewProject['budget']): ?><p><i class="fas fa-money-bill w-4 text-slate-400"></i> Budget: ৳ <?= number_format($viewProject['budget']) ?></p><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Task Board / Timeline toggle -->
    <div class="mb-4 flex justify-between items-center">
        <div class="flex gap-1 bg-slate-100 p-1 rounded-xl">
            <button onclick="switchView('board')" id="view-board"
                    class="view-btn px-4 py-1.5 rounded-lg text-sm font-semibold transition-all">
                <i class="fas fa-columns mr-1.5"></i> Board
            </button>
            <button onclick="switchView('timeline')" id="view-timeline"
                    class="view-btn px-4 py-1.5 rounded-lg text-sm font-semibold transition-all">
                <i class="fas fa-stream mr-1.5"></i> Timeline
            </button>
        </div>
        <?php if ($canEdit): ?>
        <button onclick="openProjectTaskModal(null, <?= $viewProject['id'] ?>)"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
            <i class="fas fa-plus mr-1"></i> Add Task
        </button>
        <?php endif; ?>
    </div>

    <?php
    $columns = [
        'todo'        => ['label' => 'To Do',       'color' => 'border-t-slate-400',  'head' => 'bg-slate-50'],
        'in_progress' => ['label' => 'In Progress',  'color' => 'border-t-blue-500',   'head' => 'bg-blue-50'],
        'review'      => ['label' => 'Review',       'color' => 'border-t-yellow-500', 'head' => 'bg-yellow-50'],
        'done'        => ['label' => 'Done',         'color' => 'border-t-green-500',  'head' => 'bg-green-50'],
    ];
    $tasksByStatus = [];
    foreach ($projectTasks as $t) {
        $tasksByStatus[$t['status']][] = $t;
    }

    // ── Timeline calculations ────────────────────────────────────────────────
    $tlStart = $viewProject['start_date'] ?? null;
    $tlEnd   = $viewProject['due_date'] ?? null;
    foreach ($projectTasks as $t) {
        $s = $t['created_at'] ? date('Y-m-d', strtotime($t['created_at'])) : null;
        $e = $t['due_date'] ?? null;
        if ($s && (!$tlStart || $s < $tlStart)) $tlStart = $s;
        if ($e && (!$tlEnd   || $e > $tlEnd))   $tlEnd   = $e;
    }
    // Fallback: use today ± 7 days if no dates exist
    if (!$tlStart) $tlStart = date('Y-m-d', strtotime('-7 days'));
    if (!$tlEnd)   $tlEnd   = date('Y-m-d', strtotime('+30 days'));
    // Pad by 2 days either side
    $tlStartTs = strtotime($tlStart . ' -2 days');
    $tlEndTs   = strtotime($tlEnd   . ' +2 days');
    $tlTotalDays = max(1, round(($tlEndTs - $tlStartTs) / 86400));

    // Build week headers
    $tlWeeks = [];
    $cursor = strtotime(date('Y-m-d', $tlStartTs)); // align to day boundary
    while ($cursor <= $tlEndTs) {
        $tlWeeks[] = $cursor;
        $cursor = strtotime('+7 days', $cursor);
    }

    $tlStatusColors = [
        'todo'        => '#94a3b8',
        'in_progress' => '#3b82f6',
        'review'      => '#f59e0b',
        'done'        => '#22c55e',
    ];
    ?>

    <div id="view-board-panel">
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <?php foreach ($columns as $colKey => $col): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 border-t-4 <?= $col['color'] ?> flex flex-col">
            <div class="px-4 py-3 <?= $col['head'] ?> rounded-t-xl border-b border-slate-100 flex justify-between items-center">
                <span class="font-bold text-sm text-slate-700"><?= $col['label'] ?></span>
                <span class="text-xs bg-white rounded-full px-2 py-0.5 font-bold text-slate-500 border"><?= count($tasksByStatus[$colKey] ?? []) ?></span>
            </div>
            <div class="flex-1 p-3 space-y-2 min-h-[120px]">
                <?php foreach (($tasksByStatus[$colKey] ?? []) as $task): ?>
                <div class="bg-slate-50 rounded-xl border border-slate-100 p-3 group hover:shadow-sm transition-shadow">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <p class="font-semibold text-slate-800 text-sm leading-tight"><?= escape($task['task_title']) ?></p>
                        <?php if ($canEdit): ?>
                        <button onclick="openProjectTaskModal(<?= htmlspecialchars(json_encode($task), ENT_QUOTES) ?>, <?= $viewProject['id'] ?>)"
                                class="w-6 h-6 flex items-center justify-center text-blue-600 hover:bg-blue-100 rounded opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php if ($task['due_date']): ?>
                    <p class="text-xs text-slate-400 mb-1"><i class="fas fa-calendar mr-1"></i><?= date('M d', strtotime($task['due_date'])) ?></p>
                    <?php endif; ?>
                    <?php if ($task['assignee_name']): ?>
                    <p class="text-xs text-slate-400 mb-2"><i class="fas fa-user mr-1"></i><?= escape($task['assignee_name']) ?></p>
                    <?php endif; ?>
                    <div class="flex items-center justify-between">
                        <?= priorityBadge($task['priority']) ?>
                        <div class="flex gap-1">
                            <?php foreach (['todo' => 'T', 'in_progress' => 'P', 'review' => 'R', 'done' => 'D'] as $s => $lbl): ?>
                            <button onclick="changeTaskStatus(<?= $task['id'] ?>, '<?= $s ?>')"
                                    class="text-xs px-1.5 py-0.5 rounded font-bold transition-all <?= $task['status'] === $s ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-500 hover:bg-slate-300' ?>"
                                    title="<?= ucwords(str_replace('_', ' ', $s)) ?>"><?= $lbl ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($canEdit): ?>
            <div class="p-2 border-t border-slate-100">
                <button onclick="openProjectTaskModal(null, <?= $viewProject['id'] ?>, '<?= $colKey ?>')"
                        class="w-full py-2 text-xs text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                    <i class="fas fa-plus mr-1"></i> Add task
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    </div><!-- /view-board-panel -->

    <!-- ── TIMELINE VIEW ──────────────────────────────────── -->
    <div id="view-timeline-panel" class="hidden mb-8">
        <?php if (empty($projectTasks)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-10 text-center text-slate-400 text-sm">
            No tasks yet. Add tasks to see the timeline.
        </div>
        <?php else: ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <!-- Header row: task name col + week labels -->
            <div class="flex border-b border-slate-200 bg-slate-50">
                <div class="w-52 shrink-0 px-4 py-2 text-xs font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200">Task</div>
                <div class="flex-1 relative overflow-hidden">
                    <div class="flex h-full">
                        <?php foreach ($tlWeeks as $wk): ?>
                        <div class="flex-1 min-w-[60px] text-xs text-slate-400 font-semibold px-1 py-2 border-r border-slate-100 truncate">
                            <?= date('M d', $wk) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Today marker line (calculated below via JS) -->
            <!-- Task rows -->
            <?php foreach ($projectTasks as $task):
                $taskStart = $task['created_at'] ? date('Y-m-d', strtotime($task['created_at'])) : date('Y-m-d', $tlStartTs);
                $taskEnd   = $task['due_date'] ?? $taskStart;
                $taskStartTs = strtotime($taskStart);
                $taskEndTs   = strtotime($taskEnd);
                // Clamp to timeline range
                $barLeft  = max(0, ($taskStartTs - $tlStartTs) / ($tlTotalDays * 86400) * 100);
                $barWidth = max(1, ($taskEndTs - $taskStartTs + 86400) / ($tlTotalDays * 86400) * 100);
                if ($barLeft + $barWidth > 100) $barWidth = 100 - $barLeft;
                $barColor = $tlStatusColors[$task['status']] ?? '#94a3b8';
                $isOverdue = ($task['status'] !== 'done' && $taskEndTs < strtotime('today'));
            ?>
            <div class="flex border-b border-slate-100 hover:bg-slate-50/60 transition-colors group">
                <div class="w-52 shrink-0 px-4 py-3 border-r border-slate-200 flex items-center gap-2">
                    <?php if ($canEdit): ?>
                    <button onclick="openProjectTaskModal(<?= htmlspecialchars(json_encode($task), ENT_QUOTES) ?>, <?= $viewProject['id'] ?>)"
                            class="text-blue-500 hover:text-blue-700 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    <?php endif; ?>
                    <span class="text-sm font-medium text-slate-700 truncate" title="<?= escape($task['task_title']) ?>"><?= escape($task['task_title']) ?></span>
                </div>
                <div class="flex-1 relative py-3 px-1 min-h-[44px]">
                    <!-- Week grid lines -->
                    <div class="absolute inset-0 flex pointer-events-none">
                        <?php foreach ($tlWeeks as $wk): ?>
                        <div class="flex-1 min-w-[60px] border-r border-slate-100"></div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Today line -->
                    <?php $todayPct = max(0, min(100, (strtotime('today') - $tlStartTs) / ($tlTotalDays * 86400) * 100)); ?>
                    <div class="absolute top-0 bottom-0 w-px bg-red-400/60 pointer-events-none z-10" style="left:<?= round($todayPct, 2) ?>%"></div>
                    <!-- Task bar -->
                    <div class="absolute top-1/2 -translate-y-1/2 rounded-full h-6 flex items-center px-2 text-white text-xs font-semibold truncate shadow-sm"
                         style="left:<?= round($barLeft, 2) ?>%; width:<?= round($barWidth, 2) ?>%; background:<?= $barColor ?>; <?= $isOverdue ? 'outline:2px solid #ef4444;outline-offset:1px;' : '' ?>"
                         title="<?= escape($task['task_title']) ?> | <?= $taskStart ?> → <?= $taskEnd ?>">
                        <?php if ($barWidth > 8): ?>
                        <?= escape($task['task_title']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="w-28 shrink-0 px-3 py-3 flex items-center">
                    <?= statusBadge($task['status']) ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Legend -->
            <div class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex flex-wrap gap-4 text-xs text-slate-500">
                <?php foreach ($tlStatusColors as $s => $c): ?>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded-full" style="background:<?= $c ?>"></span>
                    <?= ucwords(str_replace('_', ' ', $s)) ?>
                </span>
                <?php endforeach; ?>
                <span class="flex items-center gap-1.5 ml-4">
                    <span class="inline-block w-3 h-3 rounded-full bg-red-400/60"></span> Today
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded-sm border-2 border-red-500"></span> Overdue
                </span>
            </div>
        </div>
        <?php endif; ?>
    </div><!-- /view-timeline-panel -->

    <?php else: ?>
    <!-- ── PROJECTS LIST ──────────────────────────────────── -->
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-slate-800">All Projects</h2>
        <?php if ($canEdit): ?>
        <button onclick="openProjectModal(null)"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 shadow-sm">
            <i class="fas fa-plus mr-2"></i> New Project
        </button>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest text-left">Code</th>
                        <th class="py-3 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest text-left">Project</th>
                        <th class="py-3 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest text-left">Client</th>
                        <th class="py-3 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest text-left">Status</th>
                        <th class="py-3 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest text-left">Priority</th>
                        <th class="py-3 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest text-left">Due Date</th>
                        <th class="py-3 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest text-left">Tasks</th>
                        <th class="py-3 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($projects)): ?>
                    <tr><td colspan="8" class="py-12 text-center text-slate-400"><i class="fas fa-folder-open text-3xl mb-2 block"></i>No projects yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($projects as $p): ?>
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-3 px-5 text-xs font-mono font-bold text-slate-400"><?= escape($p['project_code']) ?></td>
                        <td class="py-3 px-5">
                            <a href="?action=view&id=<?= $p['id'] ?>" class="font-bold text-slate-800 hover:text-blue-600 transition-colors">
                                <?= escape($p['project_name']) ?>
                            </a>
                        </td>
                        <td class="py-3 px-5 text-sm text-slate-500"><?= escape($p['client_name'] ?? '—') ?></td>
                        <td class="py-3 px-5"><?= statusBadge($p['status']) ?></td>
                        <td class="py-3 px-5"><?= priorityBadge($p['priority']) ?></td>
                        <td class="py-3 px-5 text-sm text-slate-500">
                            <?= $p['due_date'] ? date('M d, Y', strtotime($p['due_date'])) : '—' ?>
                        </td>
                        <td class="py-3 px-5">
                            <?php $total = (int)$p['task_total']; $done = (int)$p['task_done']; ?>
                            <?php if ($total > 0): ?>
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-slate-200 rounded-full h-1.5 min-w-[60px]">
                                    <div class="bg-green-500 h-1.5 rounded-full" style="width:<?= $total > 0 ? round($done/$total*100) : 0 ?>%"></div>
                                </div>
                                <span class="text-xs text-slate-500 whitespace-nowrap"><?= $done ?>/<?= $total ?></span>
                            </div>
                            <?php else: ?>
                            <span class="text-xs text-slate-400">No tasks</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-5 text-right">
                            <div class="flex justify-end gap-1">
                                <a href="?action=view&id=<?= $p['id'] ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="View"><i class="fas fa-eye text-sm"></i></a>
                                <?php if ($canEdit): ?>
                                <button onclick="togglePortfolio(<?= $p['id'] ?>, this)"
                                        class="p-2 rounded-lg transition-colors <?= $p['show_in_portfolio'] ? 'text-green-600 hover:bg-green-50 bg-green-50' : 'text-slate-400 hover:bg-slate-100' ?>"
                                        title="<?= $p['show_in_portfolio'] ? 'Shown in Portfolio — click to hide' : 'Hidden from Portfolio — click to show' ?>">
                                    <i class="fas fa-globe text-sm"></i>
                                </button>
                                <button onclick="openProjectModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)" class="p-2 text-slate-500 hover:bg-slate-100 rounded-lg" title="Edit"><i class="fas fa-edit text-sm"></i></button>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                <button onclick="deleteProject(<?= $p['id'] ?>)" class="p-2 text-red-500 hover:bg-red-50 rounded-lg" title="Delete"><i class="fas fa-trash text-sm"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════ DAILY TASKS TAB ═══════════════════════ -->
<div id="panel-tasks" class="tab-panel hidden">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-slate-800">Daily Tasks</h2>
        <button onclick="openDailyTaskModal(null)"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 shadow-sm">
            <i class="fas fa-plus mr-2"></i> Add Task
        </button>
    </div>

    <!-- TODAY -->
    <div class="mb-6">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-3">
            <i class="fas fa-sun mr-2 text-yellow-500"></i> Today — <?= date('M d, Y') ?>
        </h3>
        <?php if (empty($todayTasks)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-8 text-center text-slate-400 text-sm">
            No tasks scheduled for today.
        </div>
        <?php else: ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100">
            <?php foreach ($todayTasks as $t): ?>
            <div class="flex items-center gap-3 px-5 py-3 group hover:bg-slate-50/60 transition-colors">
                <button onclick="toggleDailyTask(<?= $t['id'] ?>, this)"
                        class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-all
                               <?= $t['status'] === 'completed' ? 'bg-green-500 border-green-500 text-white' : 'border-slate-300 hover:border-green-400' ?>">
                    <?php if ($t['status'] === 'completed'): ?><i class="fas fa-check text-xs"></i><?php endif; ?>
                </button>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-800 text-sm <?= $t['status'] === 'completed' ? 'line-through text-slate-400' : '' ?> truncate"><?= escape($t['task_title']) ?></p>
                    <?php if ($t['task_description']): ?><p class="text-xs text-slate-400 truncate"><?= escape($t['task_description']) ?></p><?php endif; ?>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <?php if ($t['assignee_name']): ?>
                    <span class="text-xs text-slate-400"><i class="fas fa-user mr-1"></i><?= escape($t['assignee_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($t['due_time']): ?>
                    <span class="text-xs text-slate-400"><i class="fas fa-clock mr-1"></i><?= date('h:i A', strtotime($t['due_time'])) ?></span>
                    <?php endif; ?>
                    <?= priorityBadge($t['priority']) ?>
                    <button onclick="openDailyTaskModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity"><i class="fas fa-edit text-xs"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- UPCOMING -->
    <?php if (!empty($upcomingTasks)): ?>
    <div class="mb-6">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-3">
            <i class="fas fa-calendar-alt mr-2 text-blue-500"></i> Upcoming
        </h3>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100">
            <?php foreach ($upcomingTasks as $t): ?>
            <div class="flex items-center gap-3 px-5 py-3 group hover:bg-slate-50/60 transition-colors">
                <div class="w-5 h-5 rounded-full border-2 border-slate-300 shrink-0"></div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-800 text-sm truncate"><?= escape($t['task_title']) ?></p>
                    <p class="text-xs text-slate-400"><?= date('M d, Y', strtotime($t['task_date'])) ?></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <?php if ($t['assignee_name']): ?>
                    <span class="text-xs text-slate-400"><i class="fas fa-user mr-1"></i><?= escape($t['assignee_name']) ?></span>
                    <?php endif; ?>
                    <?= priorityBadge($t['priority']) ?>
                    <button onclick="openDailyTaskModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity"><i class="fas fa-edit text-xs"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- OVERDUE -->
    <?php if (!empty($overdueTasks)): ?>
    <div class="mb-6">
        <h3 class="text-sm font-bold text-red-400 uppercase tracking-widest mb-3">
            <i class="fas fa-exclamation-triangle mr-2"></i> Overdue
        </h3>
        <div class="bg-white rounded-2xl border border-red-100 shadow-sm divide-y divide-red-50">
            <?php foreach ($overdueTasks as $t): ?>
            <div class="flex items-center gap-3 px-5 py-3 group hover:bg-red-50/40 transition-colors">
                <button onclick="toggleDailyTask(<?= $t['id'] ?>, this)"
                        class="w-5 h-5 rounded-full border-2 border-red-300 flex items-center justify-center shrink-0 hover:border-green-400">
                </button>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-800 text-sm truncate"><?= escape($t['task_title']) ?></p>
                    <p class="text-xs text-red-400"><?= date('M d, Y', strtotime($t['task_date'])) ?></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <?php if ($t['assignee_name']): ?>
                    <span class="text-xs text-slate-400"><i class="fas fa-user mr-1"></i><?= escape($t['assignee_name']) ?></span>
                    <?php endif; ?>
                    <?= priorityBadge($t['priority']) ?>
                    <button onclick="openDailyTaskModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity"><i class="fas fa-edit text-xs"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- DONE / COMPLETED -->
    <?php if (!empty($doneTasks)): ?>
    <div class="mb-6">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-3">
            <i class="fas fa-check-double mr-2 text-green-500"></i> Completed
        </h3>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100">
            <?php foreach ($doneTasks as $t): ?>
            <div class="flex items-center gap-3 px-5 py-3 group hover:bg-slate-50/60 transition-colors">
                <button onclick="toggleDailyTask(<?= $t['id'] ?>, this)"
                        class="w-5 h-5 rounded-full border-2 bg-green-500 border-green-500 text-white flex items-center justify-center shrink-0 transition-all hover:bg-green-400">
                    <i class="fas fa-check text-xs"></i>
                </button>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-400 text-sm line-through truncate"><?= escape($t['task_title']) ?></p>
                    <p class="text-xs text-slate-400"><?= date('M d, Y', strtotime($t['task_date'])) ?></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <?php if ($t['assignee_name']): ?>
                    <span class="text-xs text-slate-400"><i class="fas fa-user mr-1"></i><?= escape($t['assignee_name']) ?></span>
                    <?php endif; ?>
                    <?= priorityBadge($t['priority']) ?>
                    <button onclick="openDailyTaskModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity"><i class="fas fa-edit text-xs"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════ MODAL: Create/Edit Project ═══════════════════════ -->
<div id="projectModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between z-10">
            <h2 class="text-xl font-bold text-slate-800" id="projectModalTitle">New Project</h2>
            <button onclick="closeProjectModal()" class="w-9 h-9 flex items-center justify-center rounded-xl hover:bg-slate-100 text-slate-400">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="projectForm" class="p-6 space-y-4">
            <input type="hidden" name="project_id" id="pf_project_id">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Project Code</label>
                    <input type="text" name="project_code" id="pf_code" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Project Name *</label>
                    <input type="text" name="project_name" id="pf_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Client Name</label>
                    <input type="text" name="client_name" id="pf_client_name" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Client Email</label>
                    <input type="email" name="client_email" id="pf_client_email" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                    <select name="status" id="pf_status" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="planning">Planning</option>
                        <option value="in_progress">In Progress</option>
                        <option value="review">Review</option>
                        <option value="testing">Testing</option>
                        <option value="completed">Completed</option>
                        <option value="on_hold">On Hold</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Priority</label>
                    <select name="priority" id="pf_priority" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Start Date</label>
                    <input type="date" name="start_date" id="pf_start_date" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Due Date</label>
                    <input type="date" name="due_date" id="pf_due_date" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Budget (৳)</label>
                    <input type="number" name="budget" id="pf_budget" min="0" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                    <textarea name="description" id="pf_description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeProjectModal()" class="px-5 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i> Save Project
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════ MODAL: Create/Edit Project Task ═══════════════════════ -->
<div id="projectTaskModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800" id="ptModalTitle">Add Task</h2>
            <button onclick="closeProjectTaskModal()" class="w-9 h-9 flex items-center justify-center rounded-xl hover:bg-slate-100 text-slate-400">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="projectTaskForm" class="p-6 space-y-4">
            <input type="hidden" name="task_id" id="ptf_task_id">
            <input type="hidden" name="project_id" id="ptf_project_id">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Task Title *</label>
                <input type="text" name="task_title" id="ptf_title" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                <textarea name="task_description" id="ptf_description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                    <select name="status" id="ptf_status" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="todo">To Do</option>
                        <option value="in_progress">In Progress</option>
                        <option value="review">Review</option>
                        <option value="done">Done</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Priority</label>
                    <select name="priority" id="ptf_priority" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Due Date</label>
                    <input type="date" name="due_date" id="ptf_due_date" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Assign To</label>
                    <select name="assigned_to" id="ptf_assigned_to" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= escape($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeProjectTaskModal()" class="px-5 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i> Save Task
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════ MODAL: Create/Edit Daily Task ═══════════════════════ -->
<div id="dailyTaskModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800" id="dtModalTitle">Add Daily Task</h2>
            <button onclick="closeDailyTaskModal()" class="w-9 h-9 flex items-center justify-center rounded-xl hover:bg-slate-100 text-slate-400">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="dailyTaskForm" class="p-6 space-y-4">
            <input type="hidden" name="task_id" id="dtf_task_id">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Task Title *</label>
                <input type="text" name="task_title" id="dtf_title" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                <textarea name="task_description" id="dtf_description" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Date *</label>
                    <input type="date" name="task_date" id="dtf_date" required value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Time</label>
                    <input type="time" name="due_time" id="dtf_time" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Priority</label>
                    <select name="priority" id="dtf_priority" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                    <select name="status" id="dtf_status" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Assign To</label>
                    <select name="assigned_to" id="dtf_assigned_to" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= escape($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeDailyTaskModal()" class="px-5 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i> Save Task
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const CAN_EDIT   = <?= $canEdit ? 'true' : 'false' ?>;
const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;
const IS_PROJECT_VIEW = <?= ($action === 'view' && $viewProject) ? 'true' : 'false' ?>;
const CURRENT_PROJECT_ID = <?= ($action === 'view' && $viewProject) ? (int)$viewProject['id'] : 'null' ?>;

// ── Board / Timeline toggle ────────────────────────────────────────────────
function switchView(name) {
    ['board','timeline'].forEach(v => {
        const panel = document.getElementById('view-'+v+'-panel');
        const btn   = document.getElementById('view-'+v);
        if (!panel || !btn) return;
        panel.classList.toggle('hidden', v !== name);
        btn.classList.toggle('bg-white',      v === name);
        btn.classList.toggle('text-blue-600', v === name);
        btn.classList.toggle('shadow-sm',     v === name);
        btn.classList.toggle('text-slate-500', v !== name);
    });
}
if (IS_PROJECT_VIEW) switchView('board');

// ── Tab switching ──────────────────────────────────────────────────────────
function switchTab(name) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('bg-white', 'text-blue-600', 'shadow-sm');
        b.classList.add('text-slate-500');
    });
    const panel = document.getElementById('panel-' + name);
    const btn   = document.getElementById('tab-'   + name);
    if (!panel || !btn) return;
    panel.classList.remove('hidden');
    btn.classList.add('bg-white', 'text-blue-600', 'shadow-sm');
    btn.classList.remove('text-slate-500');
    history.replaceState(null, '', '#' + name);
}

// Init tab from URL hash
(function() {
    const hash = location.hash.replace('#', '') || 'projects';
    switchTab(['projects','tasks'].includes(hash) ? hash : 'projects');
})();

// ── Project Modal ──────────────────────────────────────────────────────────
function openProjectModal(data) {
    document.getElementById('projectModalTitle').textContent = data ? 'Edit Project' : 'New Project';
    document.getElementById('pf_project_id').value = data ? data.id : '';
    document.getElementById('pf_code').value        = data ? (data.project_code || '') : 'PRJ-AUTO';
    document.getElementById('pf_name').value        = data ? (data.project_name || '') : '';
    document.getElementById('pf_client_name').value = data ? (data.client_name || '') : '';
    document.getElementById('pf_client_email').value= data ? (data.client_email || '') : '';
    document.getElementById('pf_status').value      = data ? (data.status || 'planning') : 'planning';
    document.getElementById('pf_priority').value    = data ? (data.priority || 'medium') : 'medium';
    document.getElementById('pf_start_date').value  = data ? (data.start_date || '') : '';
    document.getElementById('pf_due_date').value    = data ? (data.due_date || '') : '';
    document.getElementById('pf_budget').value      = data ? (data.budget || 0) : 0;
    document.getElementById('pf_description').value = data ? (data.description || '') : '';
    document.getElementById('projectModal').classList.remove('hidden');
    document.getElementById('projectModal').classList.add('flex');
}
function closeProjectModal() {
    document.getElementById('projectModal').classList.add('hidden');
    document.getElementById('projectModal').classList.remove('flex');
}
document.getElementById('projectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    fetch('index.php?action=save_project', {method:'POST', body: new FormData(this)})
    .then(r => r.json()).then(d => {
        if (d.success) { closeProjectModal(); location.reload(); }
        else { alert(d.message); btn.disabled = false; btn.innerHTML = orig; }
    }).catch(() => { btn.disabled = false; btn.innerHTML = orig; });
});

// ── Delete project ─────────────────────────────────────────────────────────
function deleteProject(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if (!confirm('Delete this project and all its tasks? This cannot be undone.')) return;
    fetch('index.php?action=delete_project', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id='+id})
    .then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.message); });
}

// ── Project Task Modal ─────────────────────────────────────────────────────
function openProjectTaskModal(task, projectId, defaultStatus) {
    document.getElementById('ptModalTitle').textContent = task ? 'Edit Task' : 'Add Task';
    document.getElementById('ptf_task_id').value       = task ? task.id : '';
    document.getElementById('ptf_project_id').value    = projectId || (task ? task.project_id : '');
    document.getElementById('ptf_title').value         = task ? (task.task_title || '') : '';
    document.getElementById('ptf_description').value   = task ? (task.task_description || '') : '';
    document.getElementById('ptf_status').value        = task ? (task.status || 'todo') : (defaultStatus || 'todo');
    document.getElementById('ptf_priority').value      = task ? (task.priority || 'medium') : 'medium';
    document.getElementById('ptf_due_date').value      = task ? (task.due_date || '') : '';
    document.getElementById('ptf_assigned_to').value   = task ? (task.assigned_to || '') : '';
    document.getElementById('projectTaskModal').classList.remove('hidden');
    document.getElementById('projectTaskModal').classList.add('flex');
}
function closeProjectTaskModal() {
    document.getElementById('projectTaskModal').classList.add('hidden');
    document.getElementById('projectTaskModal').classList.remove('flex');
}
document.getElementById('projectTaskForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    fetch('index.php?action=save_project_task', {method:'POST', body: new FormData(this)})
    .then(r => r.json()).then(d => {
        if (d.success) { closeProjectTaskModal(); location.reload(); }
        else { alert(d.message); btn.disabled = false; btn.innerHTML = orig; }
    }).catch(() => { btn.disabled = false; btn.innerHTML = orig; });
});

function changeTaskStatus(id, status) {
    fetch('index.php?action=change_task_status', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'id='+id+'&status='+status
    }).then(r => r.json()).then(d => { if (d.success) location.reload(); });
}

// ── Daily Task Modal ───────────────────────────────────────────────────────
function openDailyTaskModal(task) {
    document.getElementById('dtModalTitle').textContent  = task ? 'Edit Task' : 'Add Task';
    document.getElementById('dtf_task_id').value         = task ? task.id : '';
    document.getElementById('dtf_title').value           = task ? (task.task_title || '') : '';
    document.getElementById('dtf_description').value     = task ? (task.task_description || '') : '';
    document.getElementById('dtf_date').value            = task ? (task.task_date || '<?= date('Y-m-d') ?>') : '<?= date('Y-m-d') ?>';
    document.getElementById('dtf_time').value            = task ? (task.due_time || '') : '';
    document.getElementById('dtf_priority').value        = task ? (task.priority || 'medium') : 'medium';
    document.getElementById('dtf_status').value          = task ? (task.status || 'pending') : 'pending';
    document.getElementById('dtf_assigned_to').value     = task ? (task.assigned_to || '') : '';
    document.getElementById('dailyTaskModal').classList.remove('hidden');
    document.getElementById('dailyTaskModal').classList.add('flex');
}
function closeDailyTaskModal() {
    document.getElementById('dailyTaskModal').classList.add('hidden');
    document.getElementById('dailyTaskModal').classList.remove('flex');
}
document.getElementById('dailyTaskForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    fetch('index.php?action=save_daily_task', {method:'POST', body: new FormData(this)})
    .then(r => r.json()).then(d => {
        if (d.success) { closeDailyTaskModal(); location.reload(); }
        else { alert(d.message); btn.disabled = false; btn.innerHTML = orig; }
    }).catch(() => { btn.disabled = false; btn.innerHTML = orig; });
});

function toggleDailyTask(id, btn) {
    fetch('index.php?action=toggle_daily_task', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id='+id})
    .then(r => r.json()).then(d => { if (d.success) location.reload(); });
}

// ── Toggle portfolio visibility ────────────────────────────────────────────
function togglePortfolio(id, btn) {
    fetch('index.php?action=toggle_portfolio', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    }).then(r => r.json()).then(d => {
        if (!d.success) { alert(d.message || 'Error'); return; }
        const on = d.show_in_portfolio === 1;
        // Update button appearance
        btn.classList.toggle('text-green-600', on);
        btn.classList.toggle('bg-green-50', on);
        btn.classList.toggle('text-slate-400', !on);
        btn.classList.toggle('border-green-200', on);
        btn.title = on ? 'Shown in Portfolio — click to hide' : 'Hidden from Portfolio — click to show';
        // If the button has text (detail view button)
        if (btn.querySelector('i')) {
            const icon = btn.querySelector('i');
            const hasText = btn.textContent.trim().length > 0;
            if (hasText) btn.innerHTML = '<i class="fas fa-globe mr-1"></i> ' + (on ? 'In Portfolio' : 'Add to Portfolio');
        }
        showToast('success', on ? 'Added to portfolio' : 'Removed from portfolio');
    }).catch(() => alert('Request failed'));
}

// If we're in project view, default to projects tab
<?php if ($action === 'view' && $viewProject): ?>
switchTab('projects');
<?php endif; ?>
</script>

<?php include __DIR__ . '/../footer.php'; ?>
