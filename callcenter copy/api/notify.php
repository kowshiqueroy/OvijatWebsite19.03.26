<?php
ini_set('display_errors', 0);
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

$in     = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $in['action'] ?? $_GET['action'] ?? '';
$aid    = agentId();

function jsonOut($ok, $d=[]) { echo json_encode(array_merge(['ok'=>$ok],$d)); exit; }

switch ($action) {
    case 'mark_all_read':
        $conn->query("UPDATE notifications SET is_read=1 WHERE agent_id=$aid");
        jsonOut(true);

    case 'mark_read':
        $id = (int)($in['id'] ?? 0);
        $conn->query("UPDATE notifications SET is_read=1 WHERE id=$id AND agent_id=$aid");
        jsonOut(true);

    case 'count':
        $c = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE agent_id=$aid AND is_read=0")->fetch_assoc()['c'];
        jsonOut(true, ['count' => (int)$c]);

    default:
        jsonOut(false, ['error' => 'Unknown action']);
}
