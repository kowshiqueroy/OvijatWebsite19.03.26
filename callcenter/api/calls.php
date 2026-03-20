<?php
ini_set('display_errors', 0);
require_once '../config.php';
requireLogin();

$in     = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $in['action'] ?? ($_GET['action'] ?? '');
$aid    = agentId();

function jsonOut($ok, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['ok'=>$ok],$data)); exit;
}

// ── CSV Export (GET request) ──────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="calls_' . date('Ymd_His') . '.csv"');

    $where  = ['1=1'];
    $params = []; $types = '';

    if ($df = $_GET['date_from'] ?? '') { $where[] = "DATE(cl.calldate) >= ?"; $params[] = $df; $types .= 's'; }
    if ($dt = $_GET['date_to']   ?? '') { $where[] = "DATE(cl.calldate) <= ?"; $params[] = $dt; $types .= 's'; }
    if ($d  = $_GET['disposition']?? '') { $where[] = "cl.disposition = ?";    $params[] = $d;  $types .= 's'; }
    if ($dir= $_GET['direction'] ?? '') { $where[] = "cl.call_direction = ?";  $params[] = $dir;$types .= 's'; }
    if ($m  = $_GET['mark']      ?? '') { $where[] = "cl.call_mark = ?";       $params[] = $m;  $types .= 's'; }
    if ($q  = trim($_GET['q']    ?? '')){ $where[] = "(cl.src LIKE ? OR cl.dst LIKE ? OR c.name LIKE ?)";
        $sq = "%$q%"; $params=array_merge($params,[$sq,$sq,$sq]); $types.='sss'; }

    $whereSQL = implode(' AND ', $where);
    $stmt = $conn->prepare(
        "SELECT cl.calldate,cl.clid,cl.src,cl.dst,cl.dcontext,cl.channel,cl.dstchannel,
                cl.lastapp,cl.lastdata,cl.duration,cl.billsec,cl.disposition,
                cl.accountcode,cl.uniqueid,cl.recordingfile,
                cl.cnum,cl.cnam,cl.outbound_cnum,cl.outbound_cnam,cl.dst_cnam,cl.linkedid,cl.sequence,
                cl.call_direction,cl.call_mark,cl.is_manual,
                c.name AS contact_name, c.phone AS contact_phone,
                a.full_name AS agent_name
         FROM call_logs cl
         LEFT JOIN contacts c ON c.id=cl.contact_id
         LEFT JOIN agents a   ON a.id=cl.agent_id
         WHERE $whereSQL ORDER BY cl.calldate DESC LIMIT 10000"
    );
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Caller ID','From','To','Context','Channel','Dst Channel',
                   'Last App','Last Data','Duration(s)','Talk(s)','Status',
                   'Account','Unique ID','Recording','CNum','CName','Out CNum','Out CName','Dst CName','Linked ID','Seq',
                   'Direction','Mark','Manual','Contact','Contact Phone','Agent']);
    while ($r = $res->fetch_assoc()) fputcsv($out, array_values($r));
    fclose($out);

    logActivity('calls_exported', 'call_logs', null, "CSV export: $whereSQL");
    exit;
}

// ── JSON POST actions ─────────────────────────────────────────────────────────
switch ($action) {

    // ── Mark a call ───────────────────────────────────────────────────────────
    case 'mark': {
        $id   = (int)($in['id'] ?? 0);
        $mark = $in['mark'] ?? 'normal';
        if (!$id) jsonOut(false, ['error' => 'Missing id']);

        $old = $conn->query("SELECT call_mark FROM call_logs WHERE id=$id LIMIT 1")->fetch_assoc()['call_mark'] ?? '';
        $eMark = $conn->real_escape_string($mark);
        $conn->query("UPDATE call_logs SET call_mark='$eMark', updated_by=$aid WHERE id=$id");

        logEdit('call_logs', $id, 'call_mark', $old, $mark);
        logActivity('call_marked', 'call_logs', $id, "Marked: $old → $mark");
        jsonOut(true);
    }

    // ── Assign agent to call ──────────────────────────────────────────────────
    case 'assign': {
        $id       = (int)($in['id'] ?? 0);
        $agentId  = $in['agent_id'] ? (int)$in['agent_id'] : 'NULL';
        if (!$id) jsonOut(false, ['error' => 'Missing id']);

        $old = $conn->query("SELECT agent_id FROM call_logs WHERE id=$id LIMIT 1")->fetch_assoc()['agent_id'];
        $conn->query("UPDATE call_logs SET agent_id=$agentId, updated_by=$aid WHERE id=$id");
        logEdit('call_logs', $id, 'agent_id', $old, $agentId);
        logActivity('call_assigned', 'call_logs', $id, "Agent: $old → $agentId");

        if (is_int($agentId) && $agentId !== $aid) {
            $call = $conn->query("SELECT src FROM call_logs WHERE id=$id LIMIT 1")->fetch_assoc();
            notify($agentId, 'Call Assigned', currentAgent()['full_name'] . " assigned call from {$call['src']}",
                   'task_assigned', 'call_logs', $id, APP_URL . "/call_detail.php?id=$id");
        }
        jsonOut(true);
    }

    // ── Manual call entry ─────────────────────────────────────────────────────
    case 'manual': {
        $src         = $conn->real_escape_string(normalizePhone($in['src'] ?? ''));
        $dst         = $conn->real_escape_string($in['dst'] ?? '');
        $direction   = in_array($in['call_direction']??'',['inbound','outbound','internal']) ? $in['call_direction'] : 'outbound';
        $disposition = in_array($in['disposition']??'',['ANSWERED','NO ANSWER','BUSY','FAILED']) ? $in['disposition'] : 'ANSWERED';
        $duration    = (int)($in['duration'] ?? 0);
        $calldate    = $in['calldate'] ? $conn->real_escape_string($in['calldate']) : date('Y-m-d H:i:s');
        $mark        = $in['call_mark'] ?? 'normal';
        $notes       = $conn->real_escape_string($in['manual_notes'] ?? '');

        if (!$src) jsonOut(false, ['error' => 'Source number required']);

        // Find or create contact
        $contactId = findOrCreateContact($src, '', $aid);

        $uid = 'MAN-' . $aid . '-' . time() . '-' . rand(100,999);
        $conn->query(
            "INSERT IGNORE INTO call_logs
             (calldate, src, dst, call_direction, disposition, duration, billsec, uniqueid,
              call_mark, is_manual, manual_notes, contact_id, agent_id, created_by)
             VALUES ('$calldate','$src','$dst','$direction','$disposition',$duration,$duration,'$uid',
                     '$mark',1,'$notes'," . ($contactId ?: 'NULL') . ",$aid,$aid)"
        );
        $callId = $conn->insert_id;

        logActivity('manual_call_entry', 'call_logs', $callId,
                    "Manual call: $src → $dst | $disposition | $duration s");
        jsonOut(true, ['id' => $callId]);
    }

    // ── Link a contact to a call ──────────────────────────────────────────────
    case 'link_contact': {
        $callId    = (int)($in['call_id'] ?? 0);
        $contactId = (int)($in['contact_id'] ?? 0);
        if (!$callId || !$contactId) jsonOut(false, ['error' => 'Missing fields']);

        $old = $conn->query("SELECT contact_id FROM call_logs WHERE id=$callId LIMIT 1")->fetch_assoc()['contact_id'];
        $conn->query("UPDATE call_logs SET contact_id=$contactId, updated_by=$aid WHERE id=$callId");
        logEdit('call_logs', $callId, 'contact_id', $old, $contactId);
        logActivity('call_contact_linked', 'call_logs', $callId, "Linked contact #$contactId");
        jsonOut(true);
    }

    default:
        jsonOut(false, ['error' => 'Unknown action']);
}
