<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../functions.php';

requireLogin();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'search':
        $q = sanitize($_GET['q'] ?? '');
        $results = [];
        if (strlen($q) >= 2) {
            $like = "%$q%";
            $stmt = $conn->prepare("SELECT p.*, g.name as group_name, g.color as group_color,
                (SELECT COUNT(*) FROM calls WHERE caller_number = p.phone) as call_count
                FROM persons p 
                LEFT JOIN contact_groups g ON p.group_id = g.id
                WHERE (p.phone LIKE ? OR p.name LIKE ?) AND p.status = 'active'
                ORDER BY call_count DESC LIMIT 20");
            $stmt->bind_param("ss", $like, $like);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
        }
        echo json_encode($results);
        break;

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT p.*, g.name as group_name, g.color as group_color,
            (SELECT COUNT(*) FROM calls WHERE caller_number = p.phone) as call_count
            FROM persons p 
            LEFT JOIN contact_groups g ON p.group_id = g.id
            WHERE p.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $person = $result->fetch_assoc();

        if ($person) {
            $stmtCalls = $conn->query("SELECT c.*, a.name as agent_name FROM calls c LEFT JOIN agents a ON c.agent_id = a.id WHERE c.caller_number = '{$person['phone']}' ORDER BY c.start_time DESC LIMIT 50");
            $calls = [];
            while ($c = $stmtCalls->fetch_assoc()) $calls[] = $c;

            $stmtLogs = $conn->prepare("SELECT l.*, u.username FROM logs l JOIN users u ON l.agent_id = u.id WHERE l.person_id = ? AND l.status = 'active' AND (l.parent_id IS NULL OR l.parent_id = 0) ORDER BY l.created_at DESC LIMIT 50");
            $stmtLogs->bind_param("i", $id);
            $stmtLogs->execute();
            $logs = [];
            while ($l = $stmtLogs->get_result()->fetch_assoc()) {
                $stmtReplies = $conn->prepare("SELECT r.*, u.username FROM logs r JOIN users u ON r.agent_id = u.id WHERE r.parent_id = ? ORDER BY r.created_at");
                $stmtReplies->bind_param("i", $l['id']);
                $stmtReplies->execute();
                $l['replies'] = [];
                while ($r = $stmtReplies->get_result()->fetch_assoc()) {
                    $l['replies'][] = $r;
                }
                $logs[] = $l;
            }

            echo json_encode(['person' => $person, 'calls' => $calls, 'logs' => $logs]);
        } else {
            echo json_encode(['error' => 'Person not found']);
        }
        break;

    case 'add':
        $input = json_decode(file_get_contents('php://input'), true);
        $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
        $name = sanitize($input['name'] ?? '');
        $type = sanitize($input['type'] ?? 'customer');
        $person_type = sanitize($input['person_type'] ?? 'external');
        $group_id = intval($input['group_id'] ?? 0) ?: null;
        $email = sanitize($input['email'] ?? '');
        $company = sanitize($input['company'] ?? '');
        $address = sanitize($input['address'] ?? '');
        $notes = sanitize($input['notes'] ?? '');

        if (empty($phone)) {
            echo json_encode(['error' => 'Phone required']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM persons WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($existingId);
            $stmt->fetch();
            echo json_encode(['status' => 'exists', 'id' => $existingId]);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO persons (phone, name, type, person_type, group_id, email, company, address, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $phone, $name, $type, $person_type, $group_id, $email, $company, $address, $notes);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['error' => 'Failed to add person']);
        }
        break;

    case 'update':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
        $name = sanitize($input['name'] ?? '');
        $type = sanitize($input['type'] ?? '');
        $internal_external = sanitize($input['internal_external'] ?? 'external');
        $group_id = intval($input['group_id'] ?? 0);
        $email = sanitize($input['email'] ?? '');
        $company = sanitize($input['company'] ?? '');
        $address = sanitize($input['address'] ?? '');
        $notes = sanitize($input['notes'] ?? '');

        $stmt = $conn->prepare("UPDATE persons SET phone=?, name=?, type=?, internal_external=?, group_id=?, email=?, company=?, address=?, notes=? WHERE id=?");
        $stmt->bind_param("ssssissssi", $phone, $name, $type, $internal_external, $group_id, $email, $company, $address, $notes, $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Update failed']);
        }
        break;
    
    case 'toggle_favorite':
        $id = intval($_GET['id'] ?? 0);
        $conn->query("UPDATE persons SET is_favorite = NOT is_favorite WHERE id = $id");
        echo json_encode(['status' => 'success']);
        break;

    case 'list':
        $search = sanitize($_GET['search'] ?? '');
        $type = sanitize($_GET['type'] ?? '');
        $group_id = intval($_GET['group_id'] ?? 0);
        $page = intval($_GET['page'] ?? 0);
        $perPage = 20;
        $offset = $page * $perPage;

        $where = "WHERE p.status = 'active'";
        $params = [];
        $types = "";

        if ($search) {
            $where .= " AND (p.phone LIKE ? OR p.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ss";
        }
        if ($type) {
            $where .= " AND p.type = ?";
            $params[] = $type;
            $types .= "s";
        }
        if ($group_id) {
            $where .= " AND p.group_id = ?";
            $params[] = $group_id;
            $types .= "i";
        }

        $sql = "SELECT p.*, g.name as group_name, g.color as group_color,
            (SELECT COUNT(*) FROM calls WHERE caller_number = p.phone) as call_count
            FROM persons p LEFT JOIN contact_groups g ON p.group_id = g.id $where ORDER BY call_count DESC LIMIT $perPage OFFSET $offset";

        if ($params) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }

        $persons = [];
        while ($row = $result->fetch_assoc()) $persons[] = $row;
        echo json_encode($persons);
        break;

    case 'update_field':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $field = sanitize($input['field'] ?? '');
        $value = sanitize($input['value'] ?? '');
        
        $allowed = ['company', 'email', 'address', 'notes', 'name'];
        if (!$id || !in_array($field, $allowed)) {
            echo json_encode(['error' => 'Invalid']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE persons SET $field = ? WHERE id = ?");
        $stmt->bind_param("si", $value, $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Failed']);
        }
        break;

    case 'delete':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['error' => 'Invalid ID']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE persons SET status = 'deleted' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Delete failed']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
