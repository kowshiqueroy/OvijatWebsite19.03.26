<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$user   = requireLogin();
$db     = getDB();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'list':
        $type   = $_GET['type'] ?? '';
        $search = trim($_GET['search'] ?? '');
        $sql    = 'SELECT * FROM question_bank WHERE user_id=?';
        $params = [$user['id']];
        if ($type)   { $sql .= ' AND question_type=?'; $params[] = $type; }
        if ($search) { $sql .= ' AND (content_json LIKE ? OR tags LIKE ? OR topic LIKE ? OR subject LIKE ?)';
                       $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $sql .= ' ORDER BY use_count DESC, created_at DESC LIMIT 200';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) $r['content'] = json_decode($r['content_json'], true);
        echo json_encode(['success' => true, 'questions' => $rows]);
        break;

    case 'get':
        $id   = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM question_bank WHERE id=? AND user_id=?');
        $stmt->execute([$id, $user['id']]);
        $row  = $stmt->fetch();
        if (!$row) { echo json_encode(['success' => false, 'error' => 'Not found']); exit; }
        $row['content'] = json_decode($row['content_json'], true);
        echo json_encode(['success' => true, 'question' => $row]);
        break;

    case 'save':
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $id     = (int)($input['id'] ?? 0);
        $type   = $input['question_type'] ?? 'short';
        $subj   = trim($input['subject'] ?? '');
        $topic  = trim($input['topic']   ?? '');
        $cont   = json_encode($input['content'] ?? []);
        $tags   = trim($input['tags']    ?? '');
        if ($id) {
            $db->prepare('UPDATE question_bank SET question_type=?,subject=?,topic=?,content_json=?,tags=? WHERE id=? AND user_id=?')
               ->execute([$type, $subj, $topic, $cont, $tags, $id, $user['id']]);
        } else {
            $db->prepare('INSERT INTO question_bank (user_id,question_type,subject,topic,content_json,tags) VALUES (?,?,?,?,?,?)')
               ->execute([$user['id'], $type, $subj, $topic, $cont, $tags]);
            $id = $db->lastInsertId();
        }
        echo json_encode(['success' => true, 'id' => $id]);
        break;

    case 'delete':
        $id = (int)($_REQUEST['id'] ?? 0);
        $db->prepare('DELETE FROM question_bank WHERE id=? AND user_id=?')->execute([$id, $user['id']]);
        echo json_encode(['success' => true]);
        break;

    case 'increment_use':
        $id = (int)($_REQUEST['id'] ?? 0);
        $db->prepare('UPDATE question_bank SET use_count=use_count+1 WHERE id=? AND user_id=?')->execute([$id, $user['id']]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
