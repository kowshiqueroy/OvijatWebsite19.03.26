<?php
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = getUser();
$is_admin = $user['role'] === 'admin';
$agent_id = $_SESSION['agent_id'] ?? 0;

$action = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$type = $_GET['type'] ?? '';
$group = intval($_GET['group'] ?? 0);
$int_ext = $_GET['int_ext'] ?? '';
$q = trim($_GET['q'] ?? '');
$id = intval($_GET['id'] ?? 0);

function searchContacts($conn, $q, $type, $group, $int_ext, $date_from, $date_to) {
    $where = "p.status = 'active'";
    $params = [];
    $types = "";
    
    if ($q) {
        $where .= " AND (p.name LIKE ? OR p.phone LIKE ? OR p.company LIKE ?)";
        $s = "%$q%";
        $params[] = $s; $params[] = $s; $params[] = $s;
        $types .= "sss";
    }
    
    if ($type) {
        $where .= " AND p.type = ?";
        $params[] = $type;
        $types .= "s";
    }
    
    if ($group) {
        $where .= " AND p.group_id = ?";
        $params[] = $group;
        $types .= "i";
    }
    
    if ($int_ext) {
        $where .= " AND p.internal_external = ?";
        $params[] = $int_ext;
        $types .= "s";
    }
    
    $sql = "SELECT p.*, g.name as group_name,
            (SELECT COUNT(*) FROM calls WHERE caller_number = p.phone) as total_calls,
            (SELECT COALESCE(SUM(duration), 0) FROM calls WHERE caller_number = p.phone AND duration != '') as total_talk_time,
            (SELECT COUNT(*) FROM logs WHERE person_id = p.id) as logs_count,
            (SELECT COUNT(*) FROM tasks WHERE person_id = p.id AND status != 'completed') as tasks_count
            FROM persons p 
            LEFT JOIN contact_groups g ON p.group_id = g.id 
            WHERE $where ORDER BY p.name ASC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function searchCalls($conn, $q, $date_from, $date_to) {
    $where = "1=1";
    $params = [];
    $types = "";
    
    if ($q) {
        $where .= " AND (c.caller_number LIKE ? OR c.caller_name LIKE ? OR p.name LIKE ?)";
        $s = "%$q%";
        $params[] = $s; $params[] = $s; $params[] = $s;
        $types .= "sss";
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
    
    $sql = "SELECT c.*, p.name as person_name, p.phone as person_phone
            FROM calls c 
            LEFT JOIN persons p ON c.caller_number = p.phone 
            WHERE $where ORDER BY c.start_time DESC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function searchLogs($conn, $q, $date_from, $date_to) {
    global $is_admin, $agent_id;
    
    $where = "l.status = 'active'";
    if (!$is_admin) {
        $where .= " AND l.agent_id = $agent_id";
    }
    $params = [];
    $types = "";
    
    if ($q) {
        $where .= " AND l.notes LIKE ?";
        $params[] = "%$q%";
        $types .= "s";
    }
    
    if ($date_from) {
        $where .= " AND DATE(l.created_at) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    if ($date_to) {
        $where .= " AND DATE(l.created_at) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    $sql = "SELECT l.*, p.name as person_name, p.phone as person_phone, u.username as agent_name
            FROM logs l 
            LEFT JOIN persons p ON l.person_id = p.id 
            LEFT JOIN users u ON l.agent_id = u.id 
            WHERE $where ORDER BY l.created_at DESC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function searchTasks($conn, $q, $date_from, $date_to) {
    global $is_admin, $agent_id;
    
    $where = "1=1";
    if (!$is_admin) {
        $where .= " AND (t.assigned_to = $agent_id OR t.assigned_by = $agent_id)";
    }
    $params = [];
    $types = "";
    
    if ($q) {
        $where .= " AND (t.title LIKE ? OR t.description LIKE ?)";
        $s = "%$q%";
        $params[] = $s; $params[] = $s;
        $types .= "ss";
    }
    
    $sql = "SELECT t.*, p.name as person_name, ag.name as assigned_to_name
            FROM tasks t 
            LEFT JOIN persons p ON t.person_id = p.id 
            LEFT JOIN agents ag ON t.assigned_to = ag.id 
            WHERE $where ORDER BY t.created_at DESC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($action === 'search_contacts') {
    echo json_encode(searchContacts($conn, $q, $type, $group, $int_ext, $date_from, $date_to));
    exit;
}

if ($action === 'search_calls') {
    echo json_encode(searchCalls($conn, $q, $date_from, $date_to));
    exit;
}

if ($action === 'search_logs') {
    echo json_encode(searchLogs($conn, $q, $date_from, $date_to));
    exit;
}

if ($action === 'search_tasks') {
    echo json_encode(searchTasks($conn, $q, $date_from, $date_to));
    exit;
}

if ($action === 'get_contact' && $id) {
    $stmt = $conn->prepare("SELECT p.*, g.name as group_name FROM persons p LEFT JOIN contact_groups g ON p.group_id = g.id WHERE p.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $phone = $conn->real_escape_string($result['phone']);
        $result['total_calls'] = $conn->query("SELECT COUNT(*) as c FROM calls WHERE caller_number = '{$result['phone']}'")->fetch_assoc()['c'];
        $result['total_talk_time'] = $conn->query("SELECT COALESCE(SUM(duration), 0) as t FROM calls WHERE caller_number = '{$result['phone']}' AND duration != ''")->fetch_assoc()['t'];
        $result['logs_count'] = $conn->query("SELECT COUNT(*) as c FROM logs WHERE person_id = $id")->fetch_assoc()['c'];
        $result['tasks_count'] = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE person_id = $id AND status != 'completed'")->fetch_assoc()['c'];
    }
    
    echo json_encode($result ?: ['error' => 'Not found']);
    exit;
}

if ($action === 'get_call' && $id) {
    $stmt = $conn->prepare("SELECT c.*, p.name as person_name, p.phone as person_phone FROM calls c LEFT JOIN persons p ON c.caller_number = p.phone WHERE c.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $result['logs'] = [];
        $logs = $conn->query("SELECT l.*, u.username as agent_name FROM logs l LEFT JOIN users u ON l.agent_id = u.id WHERE l.call_id = $id AND l.status = 'active' ORDER BY l.created_at");
        while ($log = $logs->fetch_assoc()) {
            $log['replies'] = [];
            $replies = $conn->query("SELECT lr.*, u.username as agent_name FROM logs lr LEFT JOIN users u ON lr.agent_id = u.id WHERE lr.parent_id = {$log['id']} ORDER BY lr.created_at");
            while ($reply = $replies->fetch_assoc()) {
                $log['replies'][] = $reply;
            }
            $result['logs'][] = $log;
        }
    }
    
    echo json_encode($result ?: ['error' => 'Not found']);
    exit;
}

if ($action === 'get_log' && $id) {
    $stmt = $conn->prepare("SELECT l.*, u.username as agent_name FROM logs l LEFT JOIN users u ON l.agent_id = u.id WHERE l.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $result['replies'] = [];
        $replies = $conn->query("SELECT lr.*, u.username as agent_name FROM logs lr LEFT JOIN users u ON lr.agent_id = u.id WHERE lr.parent_id = $id ORDER BY lr.created_at");
        while ($reply = $replies->fetch_assoc()) {
            $result['replies'][] = $reply;
        }
    }
    
    echo json_encode($result ?: ['error' => 'Not found']);
    exit;
}

if ($action === 'get_task' && $id) {
    $stmt = $conn->prepare("SELECT t.*, p.name as person_name, ag.name as assigned_to_name FROM tasks t LEFT JOIN persons p ON t.person_id = p.id LEFT JOIN agents ag ON t.assigned_to = ag.id WHERE t.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_assoc() ?: ['error' => 'Not found']);
    exit;
}

if ($action === 'get_contact_calls' && $id) {
    $stmt = $conn->prepare("SELECT c.*, p.name as person_name FROM calls c LEFT JOIN persons p ON c.caller_number = p.phone WHERE c.person_id = ? OR c.caller_number = (SELECT phone FROM persons WHERE id = ?) ORDER BY c.start_time DESC LIMIT 20");
    $stmt->bind_param("ii", $id, $id);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($action === 'get_contact_logs' && $id) {
    $stmt = $conn->prepare("SELECT l.*, u.username as agent_name FROM logs l LEFT JOIN users u ON l.agent_id = u.id WHERE l.person_id = ? AND l.status = 'active' AND (l.parent_id IS NULL OR l.parent_id = 0) ORDER BY l.created_at DESC LIMIT 20");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($action === 'get_contact_tasks' && $id) {
    $stmt = $conn->prepare("SELECT t.*, ag.name as assigned_to_name FROM tasks t LEFT JOIN agents ag ON t.assigned_to = ag.id WHERE t.person_id = ? ORDER BY t.created_at DESC LIMIT 20");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

echo json_encode(['error' => 'Invalid action']);
