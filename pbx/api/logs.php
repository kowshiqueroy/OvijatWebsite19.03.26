<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../functions.php';

requireLogin();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        $input = json_decode(file_get_contents('php://input'), true);
        $call_id = intval($input['call_id'] ?? 0) ?: null;
        $person_id = intval($input['person_id'] ?? 0) ?: null;
        $type = sanitize($input['type'] ?? 'note');
        $log_status = sanitize($input['log_status'] ?? 'open');
        $priority = sanitize($input['priority'] ?? 'low');
        $category = sanitize($input['category'] ?? '');
        $notes = sanitize($input['notes'] ?? '');
        $drive_link = sanitize($input['drive_link'] ?? '');
        $agent_id = $_SESSION['agent_id'];

        if (empty($notes)) {
            echo json_encode(['error' => 'Notes required']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO logs (call_id, person_id, agent_id, type, log_status, priority, category, notes, drive_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissssss", $call_id, $person_id, $agent_id, $type, $log_status, $priority, $category, $notes, $drive_link);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['error' => 'Failed to create log']);
        }
        break;

    case 'update':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        
        $stmt = $conn->prepare("SELECT * FROM logs WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $log = $stmt->get_result()->fetch_assoc();

        if (!$log) {
            echo json_encode(['error' => 'Log not found']);
            exit;
        }

        if ($log['is_locked']) {
            echo json_encode(['error' => 'This log is locked and cannot be edited']);
            exit;
        }

        $type = sanitize($input['type'] ?? $log['type']);
        $log_status = sanitize($input['log_status'] ?? $log['log_status']);
        $priority = sanitize($input['priority'] ?? $log['priority']);
        $category = sanitize($input['category'] ?? $log['category']);
        $notes = sanitize($input['notes'] ?? $log['notes']);
        $drive_link = sanitize($input['drive_link'] ?? $log['drive_link']);

        $stmt = $conn->prepare("UPDATE logs SET type=?, log_status=?, priority=?, category=?, notes=?, drive_link=? WHERE id=?");
        $stmt->bind_param("ssssssi", $type, $log_status, $priority, $category, $notes, $drive_link, $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Update failed']);
        }
        break;

    case 'lock':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE logs SET is_locked=1, locked_by=?, locked_at=NOW() WHERE id=?");
        $stmt->bind_param("ii", $_SESSION['agent_id'], $id);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
        break;

    case 'unlock':
        $id = intval($_GET['id'] ?? 0);
        requireAdmin();
        $stmt = $conn->prepare("UPDATE logs SET is_locked=0 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
        break;

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT l.*, u.username FROM logs l JOIN users u ON l.agent_id = u.id WHERE l.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $log = $stmt->get_result()->fetch_assoc();
        echo json_encode($log ?: ['error' => 'Log not found']);
        break;

    case 'my_logs':
        $agent_id = $_SESSION['agent_id'];
        $page = intval($_GET['page'] ?? 0);
        $perPage = 20;
        $offset = $page * $perPage;
        $search = sanitize($_GET['search'] ?? '');

        $where = "WHERE l.agent_id = ? AND l.status = 'active' AND (l.parent_id IS NULL OR l.parent_id = 0)";
        $params = [$agent_id];
        $types = "i";

        if ($search) {
            $where .= " AND l.notes LIKE ?";
            $params[] = "%$search%";
            $types .= "s";
        }

        $stmt = $conn->prepare("SELECT l.*, p.name as person_name, p.phone as person_phone, u.username 
            FROM logs l 
            LEFT JOIN persons p ON l.person_id = p.id 
            JOIN users u ON l.agent_id = u.id 
            $where 
            ORDER BY l.created_at DESC LIMIT $perPage OFFSET $offset");
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        echo json_encode($logs);
        break;

    case 'list':
        $person_id = intval($_GET['person_id'] ?? 0);
        $stmt = $conn->prepare("SELECT l.*, u.username FROM logs l JOIN users u ON l.agent_id = u.id WHERE l.person_id = ? AND l.status = 'active' ORDER BY l.created_at DESC");
        $stmt->bind_param("i", $person_id);
        $stmt->execute();
        $logs = [];
        while ($row = $stmt->get_result()->fetch_assoc()) {
            $logs[] = $row;
        }
        echo json_encode($logs);
        break;

    case 'reply':
        $input = json_decode(file_get_contents('php://input'), true);
        $parent_id = intval($input['parent_id'] ?? 0);
        $notes = sanitize($input['notes'] ?? '');
        
        if (!$parent_id || empty($notes)) {
            echo json_encode(['error' => 'Parent log and notes required']);
            exit;
        }

        $stmt = $conn->prepare("SELECT * FROM logs WHERE id = ?");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        $parent = $stmt->get_result()->fetch_assoc();

        if (!$parent) {
            echo json_encode(['error' => 'Parent log not found']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO logs (call_id, person_id, agent_id, type, log_status, priority, category, notes, drive_link, parent_id) VALUES (?, ?, ?, 'reply', ?, ?, ?, ?, ?, ?)");
        $type = 'reply';
        $status = $parent['log_status'];
        $priority = $parent['priority'];
        $category = $parent['category'];
        $stmt->bind_param("iiissssss", $parent['call_id'], $parent['person_id'], $_SESSION['agent_id'], $type, $status, $priority, $category, $notes, $parent_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['error' => 'Failed to create reply']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
