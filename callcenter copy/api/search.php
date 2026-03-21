<?php
ini_set('display_errors', 0);
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

$q    = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';

if (strlen($q) < 2) { echo json_encode(['ok'=>true,'results'  =>[]]); exit; }

$eq = '%' . $conn->real_escape_string($q) . '%';
$results = [];

// ── FAQ search ────────────────────────────────────────────────────────────────
if ($type === 'faq' || $type === 'all') {
    $r = $conn->query(
        "SELECT id, question, answer, category FROM faqs
         WHERE question LIKE '$eq' OR answer LIKE '$eq' OR keywords LIKE '$eq'
         ORDER BY usage_count DESC LIMIT 8"
    );
    while ($r && $row = $r->fetch_assoc()) {
        $results[] = ['type'=>'faq', 'id'=>$row['id'],
                      'question'=>$row['question'], 'answer'=>$row['answer'], 'category'=>$row['category']];
        // Increment usage
        $conn->query("UPDATE faqs SET usage_count=usage_count+1 WHERE id={$row['id']}");
    }
}

if ($type === 'faq') { echo json_encode(['ok'=>true,'results'=>$results]); exit; }

// ── Contacts ──────────────────────────────────────────────────────────────────
$r = $conn->query(
    "SELECT c.id, c.phone, c.name, c.company, c.scope, c.is_favorite,
            (SELECT COUNT(DISTINCT cl.id) FROM call_logs cl
             WHERE cl.contact_id=c.id OR cl.src=c.phone OR cl.dst=c.phone) AS call_count,
            (SELECT MAX(cl.calldate) FROM call_logs cl
             WHERE cl.contact_id=c.id OR cl.src=c.phone OR cl.dst=c.phone) AS last_call,
            (SELECT COUNT(DISTINCT cl.id) FROM call_logs cl
             WHERE (cl.contact_id=c.id OR cl.src=c.phone OR cl.dst=c.phone)
               AND cl.disposition='NO ANSWER') AS missed_count,
            (SELECT COUNT(DISTINCT cl.id) FROM call_logs cl
             WHERE (cl.contact_id=c.id OR cl.src=c.phone OR cl.dst=c.phone)
               AND cl.disposition='ANSWERED') AS answered_count
     FROM contacts c
     WHERE (c.phone LIKE '$eq' OR c.name LIKE '$eq' OR c.company LIKE '$eq')
       AND c.status='active'
     ORDER BY c.is_favorite DESC, c.name LIMIT 6"
);
while ($r && $row = $r->fetch_assoc()) {
    $results[] = [
        'type'           => 'contact',
        'id'             => $row['id'],
        'label'          => ($row['name'] ?: $row['phone']),
        'phone'          => $row['phone'],
        'company'        => $row['company'] ?? '',
        'scope'          => $row['scope'] ?? '',
        'is_favorite'    => (int)$row['is_favorite'],
        'call_count'     => (int)$row['call_count'],
        'missed_count'   => (int)$row['missed_count'],
        'answered_count' => (int)$row['answered_count'],
        'last_call'      => $row['last_call'] ? date('d M y, h:i A', strtotime($row['last_call'])) : '',
        'url_profile'    => APP_URL.'/contact_detail.php?id='.$row['id'],
        'url_calls'      => APP_URL.'/calls.php?contact='.$row['id'],
        'url_missed'     => APP_URL.'/calls.php?contact='.$row['id'].'&disposition=NO+ANSWER',
        'url_answered'   => APP_URL.'/calls.php?contact='.$row['id'].'&disposition=ANSWERED',
    ];
}

// ── Calls ─────────────────────────────────────────────────────────────────────
$r = $conn->query(
    "SELECT cl.id, cl.src, cl.dst, cl.disposition, cl.calldate, c.name AS contact_name
     FROM call_logs cl LEFT JOIN contacts c ON c.id=cl.contact_id
     WHERE (cl.src LIKE '$eq' OR cl.dst LIKE '$eq' OR cl.cnam LIKE '$eq' OR c.name LIKE '$eq')
     ORDER BY cl.calldate DESC LIMIT 5"
);
while ($r && $row = $r->fetch_assoc()) {
    $results[] = ['type'=>'call', 'id'=>$row['id'],
                  'label'=>($row['contact_name'] ?: $row['src']) . ' → ' . $row['dst'],
                  'sub'  =>$row['disposition'] . ' · ' . date('d M H:i', strtotime($row['calldate'])),
                  'url'  =>APP_URL.'/call_detail.php?id='.$row['id']];
}

// ── Call notes ────────────────────────────────────────────────────────────────
$r = $conn->query(
    "SELECT cn.id, cn.call_id, cn.content, cn.note_type, a.full_name
     FROM call_notes cn JOIN agents a ON a.id=cn.agent_id
     WHERE cn.content LIKE '$eq'
     ORDER BY cn.created_at DESC LIMIT 5"
);
while ($r && $row = $r->fetch_assoc()) {
    $results[] = ['type'=>'note', 'id'=>$row['id'],
                  'label'=>mb_strimwidth($row['content'], 0, 60, '…'),
                  'sub'  =>$row['note_type'] . ' by ' . $row['full_name'],
                  'url'  =>APP_URL.'/call_detail.php?id='.$row['call_id'].'#note-'.$row['id']];
}

// ── Todos ─────────────────────────────────────────────────────────────────────
$r = $conn->query(
    "SELECT t.id, t.title, t.status, t.priority, a.full_name AS assigned_name
     FROM todos t JOIN agents a ON a.id=t.assigned_to
     WHERE t.title LIKE '$eq' OR t.description LIKE '$eq'
     ORDER BY t.created_at DESC LIMIT 5"
);
while ($r && $row = $r->fetch_assoc()) {
    $results[] = ['type'=>'task', 'id'=>$row['id'],
                  'label'=>$row['title'],
                  'sub'  =>$row['status'] . ' · ' . $row['assigned_name'],
                  'url'  =>APP_URL.'/todos.php?id='.$row['id']];
}

$phoneAction = null;
if (preg_match('/^[\d+\s\-().]+$/', $q)) {
    $norm = preg_replace('/[^0-9+]/', '', $q);
    $en   = $conn->real_escape_string($norm);
    $cr   = $conn->query("SELECT id, name, phone FROM contacts WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')','') = '$en' OR phone = '$en' LIMIT 1");
    if ($cr && $cr->num_rows) {
        $c = $cr->fetch_assoc();
        $phoneAction = ['phone'=>$c['phone'], 'contactId'=>$c['id'], 'contactName'=>$c['name'] ?: $c['phone']];
    } else {
        $phoneAction = ['phone'=>$norm, 'contactId'=>null, 'contactName'=>null];
    }
}

echo json_encode(['ok'=>true, 'results'=>$results, 'q'=>$q, 'phoneAction'=>$phoneAction]);
