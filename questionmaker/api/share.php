<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$user   = requireLogin();
$db     = getDB();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'create':
        $input      = json_decode(file_get_contents('php://input'), true) ?? [];
        $paperId    = (int)($input['paper_id']     ?? 0);
        $showAns    = (int)($input['show_answers'] ?? 0);
        $expHours   = (int)($input['expires_hours']?? 0);

        $stmt = $db->prepare('SELECT id FROM question_papers WHERE id=? AND user_id=?');
        $stmt->execute([$paperId, $user['id']]);
        if (!$stmt->fetch()) { echo json_encode(['success' => false, 'error' => 'Paper not found']); exit; }

        $token     = bin2hex(random_bytes(16));
        $expiresAt = $expHours ? date('Y-m-d H:i:s', time() + $expHours * 3600) : null;
        $db->prepare('INSERT INTO shared_papers (paper_id,share_token,show_answers,expires_at) VALUES (?,?,?,?)')
           ->execute([$paperId, $token, $showAns, $expiresAt]);

        $proto  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $origin = $proto . '://' . $_SERVER['HTTP_HOST'];
        echo json_encode(['success' => true, 'url' => $origin . _appBaseUrl() . "view.php?token=$token", 'token' => $token]);
        break;

    case 'list':
        $paperId = (int)($_GET['paper_id'] ?? 0);
        $stmt    = $db->prepare('SELECT sp.* FROM shared_papers sp
            JOIN question_papers qp ON qp.id=sp.paper_id
            WHERE sp.paper_id=? AND qp.user_id=?');
        $stmt->execute([$paperId, $user['id']]);
        echo json_encode(['success' => true, 'links' => $stmt->fetchAll()]);
        break;

    case 'delete':
        $token = $_REQUEST['token'] ?? '';
        $db->prepare('DELETE FROM shared_papers WHERE share_token=? AND paper_id IN (SELECT id FROM question_papers WHERE user_id=?)')
           ->execute([$token, $user['id']]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
