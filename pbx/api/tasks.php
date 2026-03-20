<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../functions.php';

requireLogin();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'my_tasks':
        $agent_id = $_SESSION['agent_id'];
        $status = sanitize($_GET['status'] ?? '');
        $assigned = sanitize($_GET['assigned'] ?? 'me');

        $where = "";
        if ($assigned === 'me') {
            $where = "WHERE (t.assigned_to = $agent_id OR t.assigned_by = $agent_id)";
        }

        if ($status) {
            $where .= ($where ? " AND " : "WHERE ") . "t.status = '$status'";
        }

        $stmt = $conn->query("SELECT t.*, p.name as person_name, p.phone as person_phone, 
            a.name as assigned_to_name, ab.name as assigned_by_name
            FROM tasks t 
            LEFT JOIN persons p ON t.person_id = p.id 
            LEFT JOIN agents a ON t.assigned_to = a.id
            LEFT JOIN agents ab ON t.assigned_by = ab.id
            $where
            ORDER BY 
                CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
                t.due_date ASC");
        $tasks = [];
        while ($row = $stmt->fetch_assoc()) {
            $tasks[] = $row;
        }
        echo json_encode($tasks);
        break;

    case 'list':
        $agent_id = $_SESSION['agent_id'];
        $stmt = $conn->query("SELECT t.*, p.name as person_name, 
            a.name as assigned_to_name
            FROM tasks t 
            LEFT JOIN persons p ON t.person_id = p.id 
            LEFT JOIN agents a ON t.assigned_to = a.id
            WHERE t.assigned_to = $agent_id OR t.assigned_by = $agent_id
            ORDER BY t.status ASC, t.due_date ASC");
        $tasks = [];
        while ($row = $stmt->fetch_assoc()) {
            $tasks[] = $row;
        }
        echo json_encode($tasks);
        break;

    case 'create':
        $input = json_decode(file_get_contents('php://input'), true);
        $title = sanitize($input['title'] ?? '');
        $description = sanitize($input['description'] ?? '');
        $person_id = intval($input['person_id'] ?? 0) ?: null;
        $log_id = intval($input['log_id'] ?? 0) ?: null;
        $call_id = intval($input['call_id'] ?? 0) ?: null;
        $priority = sanitize($input['priority'] ?? 'medium');
        $due_date = sanitize($input['due_date'] ?? '');
        $assigned_to = intval($input['assigned_to'] ?? 0) ?: $_SESSION['agent_id'];
        $agent_id = $_SESSION['agent_id'];

        if (empty($title)) {
            echo json_encode(['error' => 'Title required']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO tasks (title, description, person_id, call_id, log_id, assigned_to, assigned_by, priority, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiiisss", $title, $description, $person_id, $call_id, $log_id, $assigned_to, $agent_id, $priority, $due_date);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['error' => 'Failed to create task']);
        }
        break;

    case 'toggle':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT status FROM tasks WHERE id = ? AND (assigned_to = ? OR assigned_by = ?)");
        $stmt->bind_param("iii", $id, $_SESSION['agent_id'], $_SESSION['agent_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $task = $result->fetch_assoc();

        if ($task) {
            $newStatus = $task['status'] === 'completed' ? 'pending' : 'completed';
            $completed = $newStatus === 'completed' ? date('Y-m-d H:i:s') : null;
            $stmt = $conn->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?");
            $stmt->bind_param("ssi", $newStatus, $completed, $id);
            $stmt->execute();
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Task not found']);
        }
        break;

    case 'delete':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND assigned_by = ?");
        $stmt->bind_param("ii", $id, $_SESSION['agent_id']);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Cannot delete']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
