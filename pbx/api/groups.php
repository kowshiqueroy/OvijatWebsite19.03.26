<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../functions.php';

requireLogin();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $stmt = $conn->query("SELECT * FROM contact_groups ORDER BY name");
        $groups = [];
        while ($row = $stmt->fetch_assoc()) {
            $groups[] = $row;
        }
        echo json_encode($groups);
        break;

    case 'add':
        requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $name = sanitize($input['name'] ?? '');
        $color = sanitize($input['color'] ?? '#6366f1');

        if (empty($name)) {
            echo json_encode(['error' => 'Name required']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO contact_groups (name, color) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $color);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['error' => 'Failed to create group']);
        }
        break;

    case 'delete':
        requireAdmin();
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE persons SET group_id = NULL WHERE group_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $conn->query("DELETE FROM contact_groups WHERE id = $id");
        echo json_encode(['status' => 'success']);
        break;

    case 'update':
        requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $name = sanitize($input['name'] ?? '');
        $color = sanitize($input['color'] ?? '#6366f1');

        if (empty($name) || !$id) {
            echo json_encode(['error' => 'Name required']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE contact_groups SET name = ?, color = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $color, $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Failed to update group']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
