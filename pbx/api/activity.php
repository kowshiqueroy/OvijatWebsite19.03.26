<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../functions.php';

requireLogin();

$action = $_GET['action'] ?? '';
$limit = intval($_GET['limit'] ?? 10);
$agent_id = $_SESSION['agent_id'];

switch ($action) {
    case 'recent':
        $activity = [];
        
        $stmt = $conn->prepare("SELECT l.id, l.notes, l.type, l.created_at, p.name as person_name, u.username 
            FROM logs l 
            LEFT JOIN persons p ON l.person_id = p.id 
            JOIN users u ON l.agent_id = u.id 
            WHERE l.agent_id = ? 
            ORDER BY l.created_at DESC 
            LIMIT ?");
        $stmt->bind_param("ii", $agent_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $activity[] = [
                'text' => "Added {$row['type']}: " . substr($row['notes'], 0, 50),
                'created_at' => $row['created_at']
            ];
        }
        
        $stmt = $conn->prepare("SELECT t.id, t.title, t.created_at 
            FROM tasks t 
            WHERE t.assigned_to = ? OR t.assigned_by = ? 
            ORDER BY t.created_at DESC 
            LIMIT ?");
        $stmt->bind_param("iii", $agent_id, $agent_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $activity[] = [
                'text' => "Task created: " . substr($row['title'], 0, 40),
                'created_at' => $row['created_at']
            ];
        }
        
        usort($activity, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        $activity = array_slice($activity, 0, $limit);
        
        echo json_encode($activity);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
