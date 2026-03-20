<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        $input = json_decode(file_get_contents('php://input'), true);
        $username = sanitize($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $name = sanitize($input['name'] ?? '');
        $extension = sanitize($input['extension'] ?? '');
        $phone = sanitize($input['phone'] ?? '');
        $department = sanitize($input['department'] ?? '');
        
        if (empty($username) || empty($password) || empty($name)) {
            echo json_encode(['error' => 'Username, password, and name required']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['error' => 'Username already exists']);
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'agent')");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $user_id = $conn->insert_id;
        
        $stmt = $conn->prepare("INSERT INTO agents (user_id, name, extension, phone_number, department) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $name, $extension, $phone, $department);
        $stmt->execute();
        
        echo json_encode(['status' => 'success']);
        break;
        
    case 'update':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $name = sanitize($input['name'] ?? '');
        $extension = sanitize($input['extension'] ?? '');
        $phone = sanitize($input['phone'] ?? '');
        $department = sanitize($input['department'] ?? '');
        
        $stmt = $conn->prepare("UPDATE agents SET name = ?, extension = ?, phone_number = ?, department = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $extension, $phone, $department, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Update failed']);
        }
        break;
        
    case 'list':
        $stmt = $conn->prepare("SELECT a.*, u.username FROM agents a JOIN users u ON a.user_id = u.id WHERE a.status = 'active'");
        $stmt->execute();
        $result = $stmt->get_result();
        $agents = [];
        while ($row = $result->fetch_assoc()) {
            $agents[] = $row;
        }
        echo json_encode($agents);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
