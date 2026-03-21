<?php
ini_set('display_errors', 0);
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

$in     = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $in['action'] ?? $_GET['action'] ?? '';
$aid    = agentId();

function jsonOut($ok, $data = []) { echo json_encode(array_merge(['ok'=>$ok],$data)); exit; }

switch ($action) {

    // ── Create task ───────────────────────────────────────────────────────────
    case 'create': {
        $title      = trim($in['title'] ?? '');
        $desc       = trim($in['description'] ?? '');
        $callId     = !empty($in['call_id'])    ? (int)$in['call_id']    : null;
        $contactId  = !empty($in['contact_id']) ? (int)$in['contact_id'] : null;
        $assignedTo = !empty($in['assigned_to']) ? (int)$in['assigned_to'] : $aid;
        $priority   = $in['priority']   ?? 'medium';
        $dueDate    = $in['due_date']   ?: null;

        if (!$title) jsonOut(false, ['error' => 'Title required']);

        $stmt = $conn->prepare(
            "INSERT INTO todos (title, description, call_id, contact_id, created_by, assigned_to, priority, due_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssiiiiss", $title, $desc, $callId, $contactId, $aid, $assignedTo, $priority, $dueDate);
        if (!$stmt->execute()) jsonOut(false, ['error' => $conn->error]);

        $todoId = $conn->insert_id;

        // Log creation
        $conn->query("INSERT INTO todo_logs (todo_id, agent_id, action, new_value)
                      VALUES ($todoId, $aid, 'created', '$priority')");
        logActivity('task_created', 'todos', $todoId, "Task: $title | Assigned to #$assignedTo");

        // Notify assigned agent if not self
        if ($assignedTo && $assignedTo !== $aid) {
            $link = APP_URL . "/todos.php?id=$todoId";
            notify($assignedTo, 'Task Assigned to You', currentAgent()['full_name'] . " assigned: $title",
                   'task_assigned', 'todos', $todoId, $link);
        }

        jsonOut(true, ['id' => $todoId]);
    }

    // ── Update status ─────────────────────────────────────────────────────────
    case 'status': {
        $todoId    = (int)($in['id'] ?? 0);
        $newStatus = $in['status'] ?? '';
        if (!$todoId || !$newStatus) jsonOut(false, ['error' => 'Missing id or status']);

        $old = $conn->query("SELECT status, assigned_to, title, created_by FROM todos WHERE id=$todoId LIMIT 1")->fetch_assoc();
        if (!$old) jsonOut(false, ['error' => 'Task not found']);

        $completedAt = $newStatus === 'done' ? "NOW()" : "NULL";
        $completedBy = $newStatus === 'done' ? $aid : "NULL";
        $conn->query(
            "UPDATE todos SET status='$newStatus', completed_at=$completedAt,
             completed_by=$completedBy, updated_at=NOW() WHERE id=$todoId"
        );

        $conn->query("INSERT INTO todo_logs (todo_id, agent_id, action, old_value, new_value)
                      VALUES ($todoId, $aid, 'status_changed', '{$old['status']}', '$newStatus')");
        logEdit('todos', $todoId, 'status', $old['status'], $newStatus);
        logActivity('task_status_changed', 'todos', $todoId, "{$old['title']}: {$old['status']} → $newStatus");

        // Notify task creator if done by someone else
        if ($newStatus === 'done' && $old['created_by'] && $old['created_by'] != $aid) {
            notify((int)$old['created_by'], 'Task Completed', currentAgent()['full_name'] . " completed: {$old['title']}",
                   'task_assigned', 'todos', $todoId);
        }

        jsonOut(true);
    }

    // ── Reassign task ─────────────────────────────────────────────────────────
    case 'assign': {
        $todoId     = (int)($in['id'] ?? 0);
        $assignedTo = (int)($in['assigned_to'] ?? 0);
        if (!$todoId || !$assignedTo) jsonOut(false, ['error' => 'Missing fields']);

        $old = $conn->query("SELECT assigned_to, title FROM todos WHERE id=$todoId LIMIT 1")->fetch_assoc();
        $conn->query("UPDATE todos SET assigned_to=$assignedTo, updated_at=NOW() WHERE id=$todoId");
        $conn->query("INSERT INTO todo_logs (todo_id, agent_id, action, old_value, new_value)
                      VALUES ($todoId, $aid, 'reassigned', '{$old['assigned_to']}', '$assignedTo')");
        logEdit('todos', $todoId, 'assigned_to', $old['assigned_to'], $assignedTo);
        logActivity('task_reassigned', 'todos', $todoId, "Assigned to #$assignedTo");

        if ($assignedTo !== $aid) {
            notify($assignedTo, 'Task Assigned', currentAgent()['full_name'] . " assigned: {$old['title']}",
                   'task_assigned', 'todos', $todoId, APP_URL . "/todos.php?id=$todoId");
        }

        jsonOut(true);
    }

    // ── Edit task fields ──────────────────────────────────────────────────────
    case 'edit': {
        $todoId  = (int)($in['id'] ?? 0);
        $title   = trim($in['title'] ?? '');
        $desc    = trim($in['description'] ?? '');
        $priority= $in['priority'] ?? '';
        $dueDate = $in['due_date'] ?: null;

        if (!$todoId) jsonOut(false, ['error' => 'Missing id']);

        $old = $conn->query("SELECT title, description, priority, due_date FROM todos WHERE id=$todoId LIMIT 1")->fetch_assoc();

        $sets = [];
        if ($title)    { $sets[] = "title='" . $conn->real_escape_string($title) . "'";    logEdit('todos',$todoId,'title',$old['title'],$title); }
        if ($desc)     { $sets[] = "description='" . $conn->real_escape_string($desc) . "'"; }
        if ($priority) { $sets[] = "priority='$priority'"; logEdit('todos',$todoId,'priority',$old['priority'],$priority); }
        if ($dueDate !== null) { $sets[] = "due_date=" . ($dueDate ? "'$dueDate'" : "NULL"); logEdit('todos',$todoId,'due_date',$old['due_date'],$dueDate); }

        if ($sets) {
            $conn->query("UPDATE todos SET " . implode(',',$sets) . ", updated_at=NOW() WHERE id=$todoId");
            $conn->query("INSERT INTO todo_logs (todo_id, agent_id, action) VALUES ($todoId,$aid,'edited')");
            logActivity('task_edited', 'todos', $todoId);
        }

        jsonOut(true);
    }

    // ── Get single task ───────────────────────────────────────────────────────
    case 'get': {
        $todoId = (int)($_GET['id'] ?? 0);
        $r = $conn->query(
            "SELECT t.*, a.full_name AS assigned_name, cb.full_name AS creator_name
             FROM todos t
             JOIN agents a  ON a.id  = t.assigned_to
             JOIN agents cb ON cb.id = t.created_by
             WHERE t.id=$todoId LIMIT 1"
        );
        if (!$r->num_rows) jsonOut(false, ['error' => 'Not found']);
        jsonOut(true, ['task' => $r->fetch_assoc()]);
    }

    // ── Add comment/log to task ───────────────────────────────────────────────
    case 'comment': {
        $todoId = (int)($in['id'] ?? 0);
        $notes  = trim($in['notes'] ?? '');
        if (!$todoId || !$notes) jsonOut(false, ['error' => 'Missing fields']);

        $task = $conn->query("SELECT assigned_to, created_by, title FROM todos WHERE id=$todoId LIMIT 1")->fetch_assoc();
        $eNotes = $conn->real_escape_string($notes);
        $conn->query("INSERT INTO todo_logs (todo_id, agent_id, action, notes) VALUES ($todoId,$aid,'commented','$eNotes')");
        logActivity('task_commented', 'todos', $todoId, mb_strimwidth($notes, 0, 80, '…'));

        // Notify assigned and creator
        foreach ([$task['assigned_to'], $task['created_by']] as $notifyId) {
            if ($notifyId && $notifyId != $aid) {
                notify((int)$notifyId, 'Comment on Task', currentAgent()['full_name'] . ": $notes",
                       'task_assigned', 'todos', $todoId, APP_URL . "/todos.php?id=$todoId");
            }
        }
        jsonOut(true);
    }

    default:
        jsonOut(false, ['error' => 'Unknown action']);
}
