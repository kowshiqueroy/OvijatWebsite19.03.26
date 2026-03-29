<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireRole('member');
$user = currentUser();
validateCsrf();

header('Content-Type: application/json');

$projectId = (int)($_POST['project_id'] ?? 0);
$title     = trim($_POST['title'] ?? '');
$priority  = in_array($_POST['priority'] ?? '', ['low','medium','high']) ? $_POST['priority'] : 'medium';
$due       = $_POST['due_date'] ?: null;

if (!$projectId || !$title) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Project and title required']);
    exit;
}

// Access check
$access = $user['role'] === 'admin'
    || dbFetch("SELECT id FROM project_members WHERE project_id=? AND user_id=?", [$projectId, $user['id']]);
if (!$access) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$id = dbInsert('tasks', [
    'project_id'  => $projectId,
    'title'       => $title,
    'priority'    => $priority,
    'status'      => 'todo',
    'due_date'    => $due,
    'created_by'  => $user['id'],
]);
// Auto-assign to creator
try { dbInsert('task_assignees', ['task_id' => $id, 'user_id' => $user['id']]); } catch (Throwable $e) {}
dbInsert('updates', ['project_id' => $projectId, 'user_id' => $user['id'], 'type' => 'task', 'message' => "Added task \"$title\"."]);

echo json_encode(['success' => true, 'data' => ['task_id' => $id], 'message' => 'Task created']);
