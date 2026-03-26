<?php
require_once __DIR__ . '/../../includes/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    ob_clean();
    header('Content-Type: application/json');
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) { jsonResponse(['success' => false, 'message' => 'CSRF token mismatch'], 403); }
    try {
        if (!empty($_POST['project_id'])) {
            $data = [
                'project_id' => (int)$_POST['project_id'],
                'task_title' => sanitize($_POST['task_title']),
                'task_description' => sanitize($_POST['task_description']) ?: null,
                'status' => sanitize($_POST['status'] ?? 'todo'),
                'priority' => sanitize($_POST['priority'] ?? 'medium'),
                'due_date' => sanitize($_POST['task_date']) ?: null,
                'assigned_to' => $_SESSION['user_id'] ?? null
            ];
            if ($id) {
                db()->update('project_tasks', $data, 'id = :id', ['id' => $id]);
                jsonResponse(['success' => true, 'message' => 'Project task updated']);
            }
            db()->insert('project_tasks', $data);
            jsonResponse(['success' => true, 'message' => 'Project task created']);
        } else {
            $data = [
                'task_title' => sanitize($_POST['task_title']),
                'task_description' => sanitize($_POST['task_description']) ?: null,
                'task_date' => sanitize($_POST['task_date']),
                'due_time' => sanitize($_POST['due_time']) ?: null,
                'status' => sanitize($_POST['status'] ?? 'pending'),
                'priority' => sanitize($_POST['priority'] ?? 'medium'),
                'assigned_to' => $_SESSION['user_id'] ?? null,
                'created_by' => $_SESSION['user_id'] ?? null
            ];
            
            if ($id) {
                db()->update('tasks', $data, 'id = :id', ['id' => $id]);
                jsonResponse(['success' => true, 'message' => 'Task updated']);
            } else {
                db()->insert('tasks', $data);
                jsonResponse(['success' => true, 'message' => 'Task created']);
            }
        }
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle') {
    ob_clean();
    header('Content-Type: application/json');
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) { jsonResponse(['success' => false, 'message' => 'CSRF token mismatch'], 403); }
    try {
        $task = db()->selectOne("SELECT status FROM tasks WHERE id = ?", [(int)$_POST['id']]);
        $newStatus = $task['status'] === 'completed' ? 'pending' : 'completed';
        db()->update('tasks', ['status' => $newStatus], 'id = :id', ['id' => (int)$_POST['id']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    ob_clean();
    header('Content-Type: application/json');
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) { jsonResponse(['success' => false, 'message' => 'CSRF token mismatch'], 403); }
    try {
        db()->delete('tasks', 'id = :id', ['id' => (int)$_POST['id']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_project_task') {
    ob_clean();
    header('Content-Type: application/json');
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) { jsonResponse(['success' => false, 'message' => 'CSRF token mismatch'], 403); }
    try {
        $task = db()->selectOne("SELECT status FROM project_tasks WHERE id = ?", [(int)$_POST['id']]);
        $newStatus = $task['status'] === 'done' ? 'todo' : 'done';
        if ($newStatus === 'done') {
            db()->update('project_tasks', ['status' => 'done', 'completed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int)$_POST['id']]);
        } else {
            db()->update('project_tasks', ['status' => 'todo', 'completed_at' => null], 'id = :id', ['id' => (int)$_POST['id']]);
        }
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_project_task') {
    ob_clean();
    header('Content-Type: application/json');
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) { jsonResponse(['success' => false, 'message' => 'CSRF token mismatch'], 403); }
    try {
        db()->delete('project_tasks', 'id = :id', ['id' => (int)$_POST['id']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

$today = date('Y-m-d');
$tasks = db()->select("SELECT * FROM tasks WHERE task_date >= ? ORDER BY task_date, due_time", [$today]);
$todayTasks = array_filter($tasks, fn($t) => $t['task_date'] === $today);
$upcomingTasks = array_filter($tasks, fn($t) => $t['task_date'] > $today);

$projectTasks = db()->select("SELECT pt.*, p.project_name FROM project_tasks pt LEFT JOIN projects p ON pt.project_id = p.id ORDER BY pt.created_at DESC");
$projects = db()->select("SELECT id, project_name FROM projects ORDER BY project_name");

$pageTitle = 'Tasks | SohojWeb Admin';
include __DIR__ . '/../header.php';
?>

<main class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Tasks</h1>
        <button onclick="openModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            <i class="fas fa-plus mr-2"></i> Add Task
        </button>
    </div>
    
    <!-- Today's Tasks -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Today - <?= date('F d, Y') ?></h2>
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <?php if (empty($todayTasks)): ?>
            <div class="p-8 text-center text-gray-500">No tasks for today</div>
            <?php else: ?>
            <div class="divide-y">
                <?php foreach ($todayTasks as $task): ?>
                <div class="flex items-center gap-4 p-4 hover:bg-gray-50">
                    <input type="checkbox" onchange="toggleTask(<?= $task['id'] ?>)" <?= $task['status'] === 'completed' ? 'checked' : '' ?> class="w-5 h-5 rounded border-gray-300 text-blue-600">
                    <div class="flex-1">
                        <p class="<?= $task['status'] === 'completed' ? 'line-through text-gray-400' : 'text-gray-800' ?> font-medium"><?= escape($task['task_title']) ?></p>
                        <?php if ($task['task_description']): ?>
                        <p class="text-sm text-gray-500"><?= escape($task['task_description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($task['due_time']): ?>
                    <span class="text-sm text-gray-500"><i class="fas fa-clock mr-1"></i><?= date('g:i A', strtotime($task['due_time'])) ?></span>
                    <?php endif; ?>
                    <span class="px-2 py-1 text-xs rounded-full <?= $task['priority'] === 'high' ? 'bg-red-100 text-red-700' : ($task['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') ?>"><?= ucfirst($task['priority']) ?></span>
                    <button onclick="deleteTask(<?= $task['id'] ?>)" class="p-2 text-gray-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Upcoming Tasks -->
    <div>
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Upcoming</h2>
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <?php if (empty($upcomingTasks)): ?>
            <div class="p-8 text-center text-gray-500">No upcoming tasks</div>
            <?php else: ?>
            <div class="divide-y">
                <?php foreach ($upcomingTasks as $task): ?>
                <div class="flex items-center gap-4 p-4 hover:bg-gray-50">
                    <input type="checkbox" onchange="toggleTask(<?= $task['id'] ?>)" <?= $task['status'] === 'completed' ? 'checked' : '' ?> class="w-5 h-5 rounded border-gray-300 text-blue-600">
                    <div class="flex-1">
                        <p class="<?= $task['status'] === 'completed' ? 'line-through text-gray-400' : 'text-gray-800' ?> font-medium"><?= escape($task['task_title']) ?></p>
                    </div>
                    <span class="text-sm text-gray-500"><?= date('M d', strtotime($task['task_date'])) ?></span>
                    <?php if ($task['due_time']): ?>
                    <span class="text-sm text-gray-500"><?= date('g:i A', strtotime($task['due_time'])) ?></span>
                    <?php endif; ?>
                    <button onclick="deleteTask(<?= $task['id'] ?>)" class="p-2 text-gray-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Project Tasks -->
    <div>
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Project Tasks</h2>
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <?php if (empty($projectTasks)): ?>
            <div class="p-8 text-center text-gray-500">No project tasks</div>
            <?php else: ?>
            <div class="divide-y">
                <?php foreach ($projectTasks as $task): ?>
                <div class="flex items-center gap-4 p-4 hover:bg-gray-50">
                    <input type="checkbox" onchange="toggleProjectTask(<?= $task['id'] ?>)" <?= $task['status'] === 'done' ? 'checked' : '' ?> class="w-5 h-5 rounded border-gray-300 text-blue-600">
                    <div class="flex-1">
                        <p class="<?= $task['status'] === 'done' ? 'line-through text-gray-400' : 'text-gray-800' ?> font-medium"><?= escape($task['task_title']) ?></p>
                        <?php if ($task['project_name']): ?>
                        <span class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded"><i class="fas fa-folder mr-1"></i><?= escape($task['project_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($task['due_date']): ?>
                    <span class="text-sm text-gray-500"><?= date('M d', strtotime($task['due_date'])) ?></span>
                    <?php endif; ?>
                    <span class="px-2 py-1 text-xs rounded-full <?= $task['priority'] === 'high' ? 'bg-red-100 text-red-700' : ($task['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') ?>"><?= ucfirst($task['priority']) ?></span>
                    <button onclick="deleteProjectTask(<?= $task['id'] ?>)" class="p-2 text-gray-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal -->
    <div id="taskModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
            <div class="border-b px-6 py-4 flex justify-between items-center">
                <h2 class="text-xl font-bold">Add Task</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <form id="taskForm" class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Task Title *</label>
                    <input type="text" name="task_title" required class="w-full px-3 py-2 border rounded-lg" placeholder="What needs to be done?">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea name="task_description" rows="2" class="w-full px-3 py-2 border rounded-lg" placeholder="Optional details"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Date *</label>
                        <input type="date" name="task_date" required value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Time</label>
                        <input type="time" name="due_time" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Project (Optional)</label>
                    <select name="project_id" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">No Project</option>
                        <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>"><?= escape($proj['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Priority</label>
                    <select name="priority" class="w-full px-3 py-2 border rounded-lg">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Save Task</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';
function openModal() { document.getElementById('taskModal').classList.remove('hidden'); document.getElementById('taskModal').classList.add('flex'); }
function closeModal() { document.getElementById('taskModal').classList.add('hidden'); document.getElementById('taskModal').classList.remove('flex'); }
document.getElementById('taskForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Saving...';
    const fd = new FormData(this);
    fd.append('csrf_token', CSRF_TOKEN);
    fetch('index.php?action=save', {method: 'POST', body: fd})
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            closeModal();
            location.reload();
        } else {
            alert(data.message);
            btn.disabled = false;
            btn.innerText = originalText;
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
        btn.disabled = false;
        btn.innerText = originalText;
    });
});
function toggleTask(id) { fetch('index.php?action=toggle', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'csrf_token='+encodeURIComponent(CSRF_TOKEN)+'&id='+id}).then(() => location.reload()); }
function deleteTask(id) { if(confirm('Delete this task?')) fetch('index.php?action=delete', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'csrf_token='+encodeURIComponent(CSRF_TOKEN)+'&id='+id}).then(() => location.reload()); }

function toggleProjectTask(id) { fetch('index.php?action=toggle_project_task', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'csrf_token='+encodeURIComponent(CSRF_TOKEN)+'&id='+id}).then(() => location.reload()); }
function deleteProjectTask(id) { if(confirm('Delete this task?')) fetch('index.php?action=delete_project_task', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'csrf_token='+encodeURIComponent(CSRF_TOKEN)+'&id='+id}).then(() => location.reload()); }
</script>
<?php include __DIR__ . '/../footer.php'; ?>
