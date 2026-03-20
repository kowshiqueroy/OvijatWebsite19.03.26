<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../functions.php';

requireLogin();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'my_stats':
        $agent_id = $_SESSION['agent_id'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM calls WHERE agent_id = ? AND DATE(start_time) = CURDATE() AND status LIKE '%answer%'");
        $stmt->bind_param("i", $agent_id);
        $stmt->execute();
        $answered = $stmt->get_result()->fetch_assoc()['c'];

        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM calls WHERE agent_id = ? AND DATE(start_time) = CURDATE() AND status LIKE '%miss%'");
        $stmt->bind_param("i", $agent_id);
        $stmt->execute();
        $missed = $stmt->get_result()->fetch_assoc()['c'];

        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM logs WHERE agent_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->bind_param("i", $agent_id);
        $stmt->execute();
        $logs = $stmt->get_result()->fetch_assoc()['c'];

        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM logs WHERE agent_id = ? AND log_status = 'open'");
        $stmt->bind_param("i", $agent_id);
        $stmt->execute();
        $open = $stmt->get_result()->fetch_assoc()['c'];

        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM tasks WHERE (assigned_to = ? OR assigned_by = ?) AND status = 'pending'");
        $stmt->bind_param("ii", $agent_id, $agent_id);
        $stmt->execute();
        $pending_tasks = $stmt->get_result()->fetch_assoc()['c'];

        echo json_encode([
            'answered' => $answered,
            'missed' => $missed,
            'logs' => $logs,
            'open' => $open,
            'pending_tasks' => $pending_tasks
        ]);
        break;

    case 'all':
        $stats = [];
        $stats['total_calls'] = $conn->query("SELECT COUNT(*) as c FROM calls")->fetch_assoc()['c'];
        $stats['today_calls'] = $conn->query("SELECT COUNT(*) as c FROM calls WHERE DATE(start_time) = CURDATE()")->fetch_assoc()['c'];
        $stats['total_persons'] = $conn->query("SELECT COUNT(*) as c FROM persons")->fetch_assoc()['c'];
        $stats['total_logs'] = $conn->query("SELECT COUNT(*) as c FROM logs")->fetch_assoc()['c'];
        $stats['total_tasks'] = $conn->query("SELECT COUNT(*) as c FROM tasks")->fetch_assoc()['c'];
        $stats['pending_tasks'] = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE status = 'pending'")->fetch_assoc()['c'];
        echo json_encode($stats);
        break;

    case 'call_markings':
        $agent_id = $_SESSION['agent_id'];
        $result = $conn->query("SELECT call_mark, COUNT(*) as c FROM calls WHERE agent_id = $agent_id AND call_mark IS NOT NULL AND call_mark != '' GROUP BY call_mark");
        $markings = [];
        while ($row = $result->fetch_assoc()) {
            $markings[$row['call_mark']] = $row['c'];
        }
        echo json_encode($markings);
        break;

    case 'log_status':
        $agent_id = $_SESSION['agent_id'];
        $result = $conn->query("SELECT log_status, COUNT(*) as c FROM logs WHERE agent_id = $agent_id GROUP BY log_status");
        $statuses = [];
        while ($row = $result->fetch_assoc()) {
            $statuses[$row['log_status']] = $row['c'];
        }
        echo json_encode($statuses);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
