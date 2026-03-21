<?php
ini_set('display_errors', 0);
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

$in     = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $in['action'] ?? ($_GET['action'] ?? '');
$aid    = agentId();

function jsonOut($ok, $data = []) { echo json_encode(array_merge(['ok'=>$ok],$data)); exit; }

switch ($action) {

    case 'list': {
        $r = $conn->query("SELECT t.*, a.full_name AS creator_name FROM tags t LEFT JOIN agents a ON a.id=t.created_by ORDER BY t.name");
        $tags = [];
        while ($row = $r->fetch_assoc()) $tags[] = $row;
        jsonOut(true, ['tags' => $tags]);
    }

    case 'list_for_call': {
        $callId = (int)($_GET['call_id'] ?? 0);
        if (!$callId) jsonOut(false, ['error' => 'Missing call_id']);
        $r = $conn->query(
            "SELECT t.* FROM tags t
             JOIN call_tags ct ON ct.tag_id = t.id
             WHERE ct.call_id = $callId
             ORDER BY t.name"
        );
        $tags = [];
        while ($row = $r->fetch_assoc()) $tags[] = $row;
        jsonOut(true, ['tags' => $tags]);
    }

    case 'add': {
        $callId = (int)($in['call_id'] ?? 0);
        $tagId  = (int)($in['tag_id']  ?? 0);
        if (!$callId || !$tagId) jsonOut(false, ['error' => 'Missing call_id or tag_id']);

        $existing = $conn->query("SELECT 1 FROM call_tags WHERE call_id=$callId AND tag_id=$tagId LIMIT 1");
        if (!$existing->num_rows) {
            $conn->query("INSERT INTO call_tags (call_id, tag_id, added_by) VALUES ($callId, $tagId, $aid)");
            logActivity('call_tag_added', 'call_logs', $callId, "Added tag #$tagId");
        }
        jsonOut(true);
    }

    case 'remove': {
        $callId = (int)($in['call_id'] ?? 0);
        $tagId  = (int)($in['tag_id']  ?? 0);
        if (!$callId || !$tagId) jsonOut(false, ['error' => 'Missing call_id or tag_id']);
        $conn->query("DELETE FROM call_tags WHERE call_id=$callId AND tag_id=$tagId");
        logActivity('call_tag_removed', 'call_logs', $callId, "Removed tag #$tagId");
        jsonOut(true);
    }

    case 'create': {
        $name  = trim($in['name']  ?? '');
        $color = trim($in['color'] ?? '#6366f1');
        if (!$name) jsonOut(false, ['error' => 'Tag name required']);
        $en = $conn->real_escape_string($name);
        $ec = $conn->real_escape_string($color);
        $conn->query("INSERT INTO tags (name, color, created_by) VALUES ('$en','$ec',$aid)");
        logActivity('tag_created', 'tags', $conn->insert_id, "Created tag: $name");
        jsonOut(true, ['id' => $conn->insert_id]);
    }

    case 'delete': {
        $tid = (int)($in['id'] ?? 0);
        if (!$tid) jsonOut(false, ['error' => 'Missing id']);
        $conn->query("DELETE FROM tags WHERE id=$tid");
        logActivity('tag_deleted', 'tags', $tid, "Deleted tag #$tid");
        jsonOut(true);
    }

    default:
        jsonOut(false, ['error' => 'Unknown action']);
}
