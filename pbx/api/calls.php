<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../functions.php';

requireLogin();

$user = getUser();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'my_calls':
        $agent_id = $_SESSION['agent_id'];
        $limit = intval($_GET['limit'] ?? 50);
        $filter = sanitize($_GET['filter'] ?? '');
        
        $answered = [];
        $nonAnswered = [];
        
        $stmt = $conn->prepare("SELECT c.*, p.name as person_name, p.type as person_type 
            FROM calls c 
            LEFT JOIN persons p ON c.caller_number = p.phone 
            WHERE c.agent_id = ? 
            ORDER BY c.start_time DESC");
        $stmt->bind_param("i", $agent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (stripos($row['status'], 'answer') !== false) {
                $answered[] = $row;
            } else {
                $nonAnswered[] = $row;
            }
        }
        
        if ($filter === 'answered') {
            $answered = array_slice($answered, 0, $limit);
            $nonAnswered = [];
        } elseif ($filter === 'non_answered') {
            $nonAnswered = array_slice($nonAnswered, 0, $limit);
            $answered = [];
        } else {
            $answered = array_slice($answered, 0, $limit);
            $nonAnswered = array_slice($nonAnswered, 0, $limit);
        }
        
        echo json_encode([
            'answered' => $answered,
            'non_answered' => $nonAnswered,
            'total_answered' => count($answered),
            'total_non_answered' => count($nonAnswered)
        ]);
        break;
        
    case 'list':
        $agent_id = $_SESSION['agent_id'];
        $page = intval($_GET['page'] ?? 0);
        $perPage = 20;
        $filter = sanitize($_GET['filter'] ?? 'today');
        $date_from = sanitize($_GET['date_from'] ?? '');
        $date_to = sanitize($_GET['date_to'] ?? '');
        
        $where = "WHERE c.agent_id = ?";
        $params = [$agent_id];
        $types = "i";
        
        if ($filter === 'today') {
            $where .= " AND DATE(c.start_time) = CURDATE()";
        } elseif ($filter === 'answered') {
            $where .= " AND c.status LIKE '%answer%'";
        } elseif ($filter === 'missed') {
            $where .= " AND c.status LIKE '%miss%'";
        } elseif ($filter === 'all') {
            // Show all
        }
        
        if ($date_from) {
            $where .= " AND DATE(c.start_time) >= ?";
            $params[] = $date_from;
            $types .= "s";
        }
        if ($date_to) {
            $where .= " AND DATE(c.start_time) <= ?";
            $params[] = $date_to;
            $types .= "s";
        }
        
        $offset = $page * $perPage;
        
        $stmt = $conn->prepare("SELECT c.*, p.name as person_name, p.type as person_type 
            FROM calls c 
            LEFT JOIN persons p ON c.caller_number = p.phone 
            $where
            ORDER BY c.start_time DESC 
            LIMIT $perPage OFFSET $offset");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $calls = [];
        while ($row = $result->fetch_assoc()) {
            $calls[] = $row;
        }
        
        echo json_encode([
            'calls' => $calls,
            'page' => $page,
            'has_more' => count($calls) === $perPage
        ]);
        break;
        
    case 'get':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT c.*, p.name as person_name, p.type as person_type, p.id as person_id 
            FROM calls c 
            LEFT JOIN persons p ON c.caller_number = p.phone 
            WHERE c.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $call = $result->fetch_assoc();
        
        if ($call) {
            $stmtLogs = $conn->prepare("SELECT l.*, u.username FROM logs l JOIN users u ON l.agent_id = u.id WHERE l.call_id = ? AND l.status = 'active' ORDER BY l.created_at DESC");
            $stmtLogs->bind_param("i", $id);
            $stmtLogs->execute();
            $logsResult = $stmtLogs->get_result();
            $logs = [];
            while ($log = $logsResult->fetch_assoc()) {
                $stmtReplies = $conn->prepare("SELECT r.*, u.username FROM logs r JOIN users u ON r.agent_id = u.id WHERE r.parent_id = ? ORDER BY r.created_at");
                $stmtReplies->bind_param("i", $log['id']);
                $stmtReplies->execute();
                $repliesResult = $stmtReplies->get_result();
                $log['replies'] = [];
                while ($reply = $repliesResult->fetch_assoc()) {
                    $log['replies'][] = $reply;
                }
                $logs[] = $log;
            }
            echo json_encode(['call' => $call, 'logs' => $logs]);
        } else {
            echo json_encode(['error' => 'Call not found']);
        }
        break;

    case 'mark':
        $id = intval($_GET['id'] ?? 0);
        $mark = sanitize($_GET['mark'] ?? '');
        $validMarks = ['successful', 'problem', 'need_action', 'urgent', 'failed'];
        
        if (!$id) {
            echo json_encode(['error' => 'Invalid ID']);
            exit;
        }
        
        if (!in_array($mark, $validMarks)) {
            $mark = '';
        }
        
        $stmt = $conn->prepare("UPDATE calls SET call_mark = ? WHERE id = ?");
        $stmt->bind_param("si", $mark, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Failed to mark call']);
        }
        break;
    
    case 'update_drive_link':
        $id = intval($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true);
        $link = sanitize($input['drive_link'] ?? '');
        
        $stmt = $conn->prepare("UPDATE calls SET drive_link = ? WHERE id = ?");
        $stmt->bind_param("si", $link, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Failed to update']);
        }
        break;

    case 'manual':
        $input = json_decode(file_get_contents('php://input'), true);
        $phone = sanitize($input['phone'] ?? '');
        $name = sanitize($input['name'] ?? '');
        $direction = sanitize($input['direction'] ?? 'Outbound');
        $status = sanitize($input['status'] ?? 'Completed');
        $duration = sanitize($input['duration'] ?? '0');
        $date = sanitize($input['date'] ?? '');
        $notes = sanitize($input['notes'] ?? '');
        $call_mark = sanitize($input['call_mark'] ?? '');
        $agent_id = $_SESSION['agent_id'];

        if (empty($phone)) {
            echo json_encode(['error' => 'Phone number required']);
            exit;
        }

        $person_id = findOrCreatePerson($phone);
        if (!empty($name) && $person_id) {
            $stmtUpdate = $conn->prepare("UPDATE persons SET name = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $name, $person_id);
            $stmtUpdate->execute();
        }

        $pbx_id = 'manual_' . md5($phone . time());
        $start_time = !empty($date) ? date('Y-m-d H:i:s', strtotime($date)) : date('Y-m-d H:i:s');

        $stmt = $conn->prepare("INSERT INTO calls (pbx_id, caller_number, caller_name, direction, status, duration, start_time, agent_id, person_id, call_mark, is_manual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssssssiss", $pbx_id, $phone, $name, $direction, $status, $duration, $start_time, $agent_id, $person_id, $call_mark);
        
        if ($stmt->execute()) {
            $call_id = $conn->insert_id;
            if (!empty($notes)) {
                $stmtLog = $conn->prepare("INSERT INTO logs (call_id, person_id, agent_id, type, notes) VALUES (?, ?, ?, 'note', ?)");
                $stmtLog->bind_param("iiis", $call_id, $person_id, $agent_id, $notes);
                $stmtLog->execute();
            }
            echo json_encode(['status' => 'success', 'id' => $call_id]);
        } else {
            echo json_encode(['error' => 'Failed to save call']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
