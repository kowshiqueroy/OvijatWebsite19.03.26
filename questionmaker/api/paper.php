<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$user   = requireLogin();
$db     = getDB();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'list':
        $stmt = $db->prepare(
            'SELECT id, title, created_at, updated_at,
             json_extract(paper_json,\'$.header.institution\') as institution,
             json_extract(paper_json,\'$.header.totalMarks\')  as total_marks
             FROM question_papers WHERE user_id=? ORDER BY updated_at DESC'
        );
        $stmt->execute([$user['id']]);
        echo json_encode(['success' => true, 'papers' => $stmt->fetchAll()]);
        break;

    case 'get':
        $id   = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM question_papers WHERE id=? AND user_id=?');
        $stmt->execute([$id, $user['id']]);
        $paper = $stmt->fetch();
        if (!$paper) { echo json_encode(['success' => false, 'error' => 'Not found']); exit; }
        $paper['paper_json'] = json_decode($paper['paper_json'], true);
        echo json_encode(['success' => true, 'paper' => $paper]);
        break;

    case 'save':
        $input    = json_decode(file_get_contents('php://input'), true) ?? [];
        $id       = (int)($input['id'] ?? 0);
        $title    = trim($input['title'] ?? 'Untitled Paper');
        $pjson    = json_encode($input['paper_json'] ?? []);

        if ($id) {
            $stmt = $db->prepare('UPDATE question_papers SET title=?,paper_json=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?');
            $stmt->execute([$title, $pjson, $id, $user['id']]);
        } else {
            $stmt = $db->prepare('INSERT INTO question_papers (user_id,title,paper_json) VALUES (?,?,?)');
            $stmt->execute([$user['id'], $title, $pjson]);
            $id = $db->lastInsertId();
        }
        echo json_encode(['success' => true, 'id' => $id]);
        break;

    case 'delete':
        $id = (int)($_REQUEST['id'] ?? 0);
        $db->prepare('DELETE FROM question_papers WHERE id=? AND user_id=?')->execute([$id, $user['id']]);
        echo json_encode(['success' => true]);
        break;

    case 'duplicate':
        $id   = (int)($_REQUEST['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM question_papers WHERE id=? AND user_id=?');
        $stmt->execute([$id, $user['id']]);
        $p = $stmt->fetch();
        if (!$p) { echo json_encode(['success' => false, 'error' => 'Not found']); exit; }
        $db->prepare('INSERT INTO question_papers (user_id,title,paper_json) VALUES (?,?,?)')->execute([$user['id'], $p['title'].' (Copy)', $p['paper_json']]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
