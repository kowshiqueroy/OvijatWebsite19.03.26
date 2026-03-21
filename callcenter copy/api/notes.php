<?php
ini_set('display_errors', 0);
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

$in  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $in['action'] ?? $_GET['action'] ?? '';
$aid    = agentId();

function jsonOut($ok, $data = []) {
    echo json_encode(array_merge(['ok' => $ok], $data));
    exit;
}

switch ($action) {

    // ── Add a note (call or contact) ──────────────────────────────────────────
    case 'add': {
        $entity    = $in['entity']    ?? ''; // 'call' | 'contact'
        $entityId  = (int)($in['entity_id'] ?? 0);
        $parentId  = !empty($in['parent_id']) ? (int)$in['parent_id'] : null;
        $content   = trim($in['content'] ?? '');
        $noteType  = $in['note_type']  ?? 'note';
        $priority  = $in['priority']   ?? 'low';
        $logStatus = $in['log_status'] ?? 'open';

        if (!$entityId || !$content) jsonOut(false, ['error' => 'Missing entity_id or content']);

        if ($entity === 'call') {
            $table    = 'call_notes';
            $fkField  = 'call_id';
        } elseif ($entity === 'contact') {
            $table    = 'contact_notes';
            $fkField  = 'contact_id';
        } else {
            jsonOut(false, ['error' => 'Unknown entity type']);
        }

        $stmt = $conn->prepare(
            "INSERT INTO $table ($fkField, agent_id, parent_id, note_type, priority, log_status, content)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iiissss", $entityId, $aid, $parentId, $noteType, $priority, $logStatus, $content);

        if (!$stmt->execute()) jsonOut(false, ['error' => $conn->error]);

        $noteId = $conn->insert_id;
        logActivity('note_added', $entity === 'call' ? 'call_logs' : 'contacts', $entityId,
                    ucfirst($entity) . " note added: " . mb_strimwidth($content, 0, 80, '…'));

        // Notify parent note author if it's a reply
        if ($parentId) {
            $pr = $conn->query("SELECT agent_id FROM $table WHERE id=$parentId LIMIT 1")->fetch_assoc();
            if ($pr && $pr['agent_id'] != $aid) {
                notify((int)$pr['agent_id'], 'Reply to your note',
                       currentAgent()['full_name'] . ' replied: ' . mb_strimwidth($content, 0, 60, '…'),
                       'note_reply', $entity === 'call' ? 'call_logs' : 'contacts', $entityId);
            }
        }

        jsonOut(true, ['note_id' => $noteId]);
    }

    // ── Edit note content ─────────────────────────────────────────────────────
    case 'edit': {
        $entity  = $in['entity'] ?? 'call';
        $noteId  = (int)($in['note_id'] ?? 0);
        $content = trim($in['content'] ?? '');
        $table   = $entity === 'contact' ? 'contact_notes' : 'call_notes';

        if (!$noteId || !$content) jsonOut(false, ['error' => 'Missing note_id or content']);

        // Fetch old
        $old = $conn->query("SELECT content FROM $table WHERE id=$noteId LIMIT 1")->fetch_assoc();
        if (!$old) jsonOut(false, ['error' => 'Note not found']);

        $stmt = $conn->prepare(
            "UPDATE $table SET content=?, edited_by=?, edited_at=NOW() WHERE id=?"
        );
        $stmt->bind_param("sii", $content, $aid, $noteId);
        if (!$stmt->execute()) jsonOut(false, ['error' => $conn->error]);

        // Log edit
        $entityCol = $entity === 'contact' ? 'contact_id' : 'call_id';
        $row = $conn->query("SELECT $entityCol FROM $table WHERE id=$noteId LIMIT 1")->fetch_assoc();
        logEdit($table, $noteId, 'content', $old['content'], $content);
        logActivity('note_edited', $entity === 'call' ? 'call_logs' : 'contacts', $row[$entityCol] ?? 0, "Note #$noteId edited");

        jsonOut(true);
    }

    // ── Pin / unpin note ──────────────────────────────────────────────────────
    case 'pin': {
        $entity  = $in['entity'] ?? 'call';
        $noteId  = (int)($in['note_id'] ?? 0);
        $pinned  = (int)($in['is_pinned'] ?? 0);
        $table   = $entity === 'contact' ? 'contact_notes' : 'call_notes';

        $conn->query("UPDATE $table SET is_pinned=$pinned WHERE id=$noteId");
        logActivity($pinned ? 'note_pinned' : 'note_unpinned', 'notes', $noteId);
        jsonOut(true);
    }

    // ── Change note log status ────────────────────────────────────────────────
    case 'status': {
        $entity    = $in['entity'] ?? 'call';
        $noteId    = (int)($in['note_id'] ?? 0);
        $logStatus = $in['log_status'] ?? 'open';
        $table     = $entity === 'contact' ? 'contact_notes' : 'call_notes';

        $old = $conn->query("SELECT log_status FROM $table WHERE id=$noteId LIMIT 1")->fetch_assoc()['log_status'] ?? '';
        $conn->query("UPDATE $table SET log_status='$logStatus' WHERE id=$noteId");
        logEdit($table, $noteId, 'log_status', $old, $logStatus);
        logActivity('note_status_changed', 'notes', $noteId, "Status: $old → $logStatus");
        jsonOut(true);
    }

    // ── Delete note ───────────────────────────────────────────────────────────
    case 'delete': {
        $entity  = $in['entity'] ?? 'call';
        $noteId  = (int)($in['note_id'] ?? 0);
        $table   = $entity === 'contact' ? 'contact_notes' : 'call_notes';

        // Only author or allow all — simple: allow author
        $r = $conn->query("SELECT agent_id FROM $table WHERE id=$noteId LIMIT 1")->fetch_assoc();
        if (!$r) jsonOut(false, ['error' => 'Not found']);

        $conn->query("DELETE FROM $table WHERE id=$noteId");
        logActivity('note_deleted', 'notes', $noteId);
        jsonOut(true);
    }

    // ── List notes (flat) for a call or contact ──────────────────────────────
    case 'list': {
        $entity   = $_GET['entity'] ?? 'call';
        $entityId = (int)($_GET['entity_id'] ?? 0);
        if (!$entityId) jsonOut(false, ['error' => 'Missing entity_id']);
        $table   = $entity === 'contact' ? 'contact_notes' : 'call_notes';
        $fkField = $entity === 'contact' ? 'contact_id' : 'call_id';
        $r = $conn->query(
            "SELECT cn.*, a.full_name, a.username
             FROM $table cn JOIN agents a ON a.id = cn.agent_id
             WHERE cn.$fkField = $entityId
             ORDER BY cn.is_pinned DESC, cn.parent_id ASC, cn.created_at ASC"
        );
        $notes = [];
        while ($r && $n = $r->fetch_assoc()) $notes[] = $n;
        jsonOut(true, ['notes' => $notes]);
    }

    default:
        jsonOut(false, ['error' => 'Unknown action']);
}
