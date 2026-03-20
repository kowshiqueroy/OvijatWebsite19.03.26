<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../functions.php';

requireLogin();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'search':
        $q = sanitize($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            $stmt = $conn->query("SELECT * FROM faqs ORDER BY usage_count DESC LIMIT 10");
        } else {
            $like = "%$q%";
            $stmt = $conn->prepare("SELECT * FROM faqs WHERE question LIKE ? OR answer LIKE ? OR tags LIKE ? ORDER BY usage_count DESC LIMIT 20");
            $stmt->bind_param("sss", $like, $like, $like);
            $stmt->execute();
            $stmt = $stmt->get_result();
        }
        $faqs = [];
        while ($row = $stmt->fetch_assoc()) {
            $faqs[] = $row;
        }
        echo json_encode($faqs);
        break;

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM faqs WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $faq = $stmt->get_result()->fetch_assoc();
        
        if ($faq) {
            $conn->query("UPDATE faqs SET usage_count = usage_count + 1 WHERE id = $id");
            echo json_encode($faq);
        } else {
            echo json_encode(['error' => 'FAQ not found']);
        }
        break;

    case 'add':
        requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $question = sanitize($input['question'] ?? '');
        $answer = sanitize($input['answer'] ?? '');
        $category = sanitize($input['category'] ?? '');
        $tags = sanitize($input['tags'] ?? '');

        if (empty($question) || empty($answer)) {
            echo json_encode(['error' => 'Question and answer required']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO faqs (question, answer, category, tags, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $question, $answer, $category, $tags, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['error' => 'Failed to create FAQ']);
        }
        break;

    case 'list':
        $stmt = $conn->query("SELECT * FROM faqs ORDER BY usage_count DESC");
        $faqs = [];
        while ($row = $stmt->fetch_assoc()) {
            $faqs[] = $row;
        }
        echo json_encode($faqs);
        break;

    case 'update':
        requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $question = sanitize($input['question'] ?? '');
        $answer = sanitize($input['answer'] ?? '');
        $category = sanitize($input['category'] ?? '');
        $tags = sanitize($input['tags'] ?? '');

        if (empty($question) || empty($answer) || !$id) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE faqs SET question = ?, answer = ?, category = ?, tags = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $question, $answer, $category, $tags, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Failed to update FAQ']);
        }
        break;

    case 'delete':
        requireAdmin();
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['error' => 'Invalid ID']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM faqs WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Failed to delete FAQ']);
        }
        break;

    case 'create_from_log':
        $input = json_decode(file_get_contents('php://input'), true);
        $question = sanitize($input['question'] ?? '');
        $answer = sanitize($input['answer'] ?? '');
        $category = sanitize($input['category'] ?? '');

        if (empty($question) && empty($answer)) {
            echo json_encode(['error' => 'Question or answer required']);
            exit;
        }

        $question = $question ?: substr($answer, 0, 200);
        $stmt = $conn->prepare("INSERT INTO faqs (question, answer, category, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $question, $answer, $category, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['error' => 'Failed to create FAQ']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
