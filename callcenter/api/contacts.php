<?php
ini_set('display_errors', 0);
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

$in     = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $in['action'] ?? ($_GET['action'] ?? '');
$aid    = agentId();

function jsonOut($ok, $data = []) { echo json_encode(array_merge(['ok'=>$ok],$data)); exit; }

// Resolve a type/group name to its ID, creating it if it doesn't exist
function resolveOrCreate(mysqli $conn, string $table, string $name): ?int {
    $name = trim($name);
    if ($name === '') return null;
    $en = $conn->real_escape_string($name);
    $r  = $conn->query("SELECT id FROM $table WHERE name='$en' LIMIT 1");
    if ($r->num_rows) return (int)$r->fetch_assoc()['id'];
    $conn->query("INSERT INTO $table (name, color) VALUES ('$en', '#6c757d')");
    return $conn->insert_id ?: null;
}

switch ($action) {

    // ── Suggest (company / type / group autocomplete) ─────────────────────────
    case 'suggest': {
        $field = $_GET['field'] ?? '';
        $q     = '%' . $conn->real_escape_string(trim($_GET['q'] ?? '')) . '%';
        $results = [];
        if ($field === 'company') {
            $r = $conn->query("SELECT DISTINCT company FROM contacts WHERE company LIKE '$q' AND company != '' ORDER BY company LIMIT 12");
            while ($row = $r->fetch_row()) $results[] = $row[0];
        } elseif ($field === 'type') {
            $r = $conn->query("SELECT name FROM contact_types WHERE name LIKE '$q' ORDER BY name LIMIT 12");
            while ($row = $r->fetch_assoc()) $results[] = $row['name'];
        } elseif ($field === 'group') {
            $r = $conn->query("SELECT name FROM contact_groups WHERE name LIKE '$q' ORDER BY name LIMIT 12");
            while ($row = $r->fetch_assoc()) $results[] = $row['name'];
        }
        jsonOut(true, ['results' => $results]);
    }

    // ── Get single contact (for edit modal) ───────────────────────────────────
    case 'get': {
        $id = (int)($_GET['id'] ?? 0);
        $r  = $conn->query(
            "SELECT c.*, ct.name AS type_name, cg.name AS group_name
             FROM contacts c
             LEFT JOIN contact_types  ct ON ct.id = c.type_id
             LEFT JOIN contact_groups cg ON cg.id = c.group_id
             WHERE c.id=$id LIMIT 1"
        );
        if (!$r->num_rows) jsonOut(false, ['error' => 'Not found']);
        jsonOut(true, $r->fetch_assoc());
    }

    // ── Autocomplete / search contacts by phone or name ───────────────────────
    case 'search': {
        $q = '%' . $conn->real_escape_string(trim($_GET['q'] ?? '')) . '%';
        $r = $conn->query(
            "SELECT id, phone, name, company, scope, is_favorite, is_blocked,
                    ct.name AS type_name, cg.name AS group_name
             FROM contacts c
             LEFT JOIN contact_types  ct ON ct.id = c.type_id
             LEFT JOIN contact_groups cg ON cg.id = c.group_id
             WHERE c.status='active' AND (c.phone LIKE '$q' OR c.name LIKE '$q' OR c.company LIKE '$q')
             ORDER BY c.is_favorite DESC, c.name ASC LIMIT 15"
        );
        $rows = [];
        while ($row = $r->fetch_assoc()) $rows[] = $row;
        jsonOut(true, ['results' => $rows]);
    }

    // ── Create contact ────────────────────────────────────────────────────────
    case 'create': {
        $phone = normalizePhone($in['phone'] ?? '');
        if (!$phone) jsonOut(false, ['error' => 'Phone required']);

        $existing = $conn->query("SELECT id FROM contacts WHERE phone='" . $conn->real_escape_string($phone) . "' LIMIT 1");
        if ($existing->num_rows) jsonOut(false, ['error' => 'Contact with this phone already exists', 'id' => $existing->fetch_assoc()['id']]);

        $name      = $conn->real_escape_string(trim($in['name'] ?? $phone));
        $company   = $conn->real_escape_string(trim($in['company'] ?? ''));
        $email     = $conn->real_escape_string(trim($in['email'] ?? ''));
        $scope     = in_array($in['scope'] ?? '', ['internal','external','unknown']) ? $in['scope'] : 'unknown';
        $typeId    = resolveOrCreate($conn, 'contact_types',  $in['type_name']  ?? '')
                  ?? ($in['type_id']  ? (int)$in['type_id']  : null);
        $groupId   = resolveOrCreate($conn, 'contact_groups', $in['group_name'] ?? '')
                  ?? ($in['group_id'] ? (int)$in['group_id'] : null);
        $typeId  = $typeId  ?? 'NULL';
        $groupId = $groupId ?? 'NULL';
        $assignedTo= $in['assigned_to']? (int)$in['assigned_to']: 'NULL';
        $ePhone    = $conn->real_escape_string($phone);

        $conn->query(
            "INSERT INTO contacts (phone,name,company,email,scope,type_id,group_id,assigned_to,created_by)
             VALUES ('$ePhone','$name','$company','$email','$scope',$typeId,$groupId,$assignedTo,$aid)"
        );
        $contactId = $conn->insert_id;

        // Link to call if provided
        if (!empty($in['call_id'])) {
            $callId = (int)$in['call_id'];
            $conn->query("UPDATE call_logs SET contact_id=$contactId WHERE id=$callId AND contact_id IS NULL");
        }

        logActivity('contact_created', 'contacts', $contactId, "Created: $name ($phone)");
        jsonOut(true, ['id' => $contactId]);
    }

    // ── Update contact ────────────────────────────────────────────────────────
    case 'update': {
        $id = (int)($in['id'] ?? 0);
        if (!$id) jsonOut(false, ['error' => 'Missing id']);

        $old = $conn->query("SELECT * FROM contacts WHERE id=$id LIMIT 1")->fetch_assoc();
        if (!$old) jsonOut(false, ['error' => 'Contact not found']);

        $fields = [
            'name'        => ['s', trim($in['name']    ?? $old['name'])],
            'company'     => ['s', trim($in['company'] ?? $old['company'])],
            'email'       => ['s', trim($in['email']   ?? $old['email'])],
            'address'     => ['s', trim($in['address'] ?? $old['address'])],
            'notes'       => ['s', trim($in['notes']   ?? $old['notes'])],
            'scope'       => ['s', $in['scope']       ?? $old['scope']],
            'office_type' => ['s', $in['office_type'] ?? $old['office_type']],
            'type_id'     => ['i', resolveOrCreate($conn, 'contact_types',  $in['type_name']  ?? '')
                              ?? ($in['type_id']  ? (int)$in['type_id']  : null)],
            'group_id'    => ['i', resolveOrCreate($conn, 'contact_groups', $in['group_name'] ?? '')
                              ?? ($in['group_id'] ? (int)$in['group_id'] : null)],
            'assigned_to' => ['i', $in['assigned_to']? (int)$in['assigned_to'] : null],
            'is_favorite' => ['i', isset($in['is_favorite']) ? (int)$in['is_favorite'] : (int)$old['is_favorite']],
            'is_blocked'  => ['i', isset($in['is_blocked'])  ? (int)$in['is_blocked']  : (int)$old['is_blocked']],
        ];

        $setParts = [];
        foreach ($fields as $col => [$type, $val]) {
            $oldVal = $old[$col] ?? '';
            if ((string)$val !== (string)$oldVal) {
                logEdit('contacts', $id, $col, $oldVal, $val);
            }
            if ($type === 'i') {
                $setParts[] = "$col=" . ($val !== null ? (int)$val : 'NULL');
            } else {
                $setParts[] = "$col='" . $conn->real_escape_string((string)$val) . "'";
            }
        }
        $setParts[] = "updated_by=$aid";

        $conn->query("UPDATE contacts SET " . implode(',', $setParts) . " WHERE id=$id");
        logActivity('contact_updated', 'contacts', $id, "Updated contact #$id");
        jsonOut(true);
    }

    // ── Delete contact ───────────────────────────────────────────────────────
    case 'delete': {
        $id = (int)($in['id'] ?? 0);
        if (!$id) jsonOut(false, ['error' => 'Missing id']);
        $old = $conn->query("SELECT phone FROM contacts WHERE id=$id LIMIT 1")->fetch_assoc();
        if (!$old) jsonOut(false, ['error' => 'Contact not found']);
        $conn->query("UPDATE call_logs SET contact_id=NULL WHERE contact_id=$id");
        $conn->query("DELETE FROM contact_notes WHERE contact_id=$id");
        $conn->query("DELETE FROM todos WHERE contact_id=$id");
        $conn->query("DELETE FROM contacts WHERE id=$id");
        logActivity('contact_deleted', 'contacts', $id, "Deleted contact: " . ($old['phone'] ?? ''));
        jsonOut(true);
    }

    // ── Toggle favorite ───────────────────────────────────────────────────────
    case 'favorite': {
        $id  = (int)($in['id'] ?? 0);
        $fav = (int)($in['is_favorite'] ?? 0);
        if (!$id) jsonOut(false, ['error' => 'Missing id']);
        $conn->query("UPDATE contacts SET is_favorite=$fav, updated_by=$aid WHERE id=$id");
        logActivity($fav ? 'contact_favorited' : 'contact_unfavorited', 'contacts', $id);
        jsonOut(true);
    }

    // ── Lookup by phone (used during calls for autofill) ─────────────────────
    case 'lookup': {
        $raw = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? '');
        if (!$raw) jsonOut(false, ['error' => 'Phone required']);
        $er = $conn->real_escape_string($raw);
        $r  = $conn->query(
            "SELECT c.*, ct.name AS type_name, cg.name AS group_name
             FROM contacts c
             LEFT JOIN contact_types  ct ON ct.id = c.type_id
             LEFT JOIN contact_groups cg ON cg.id = c.group_id
             WHERE c.phone REGEXP '$er'
             LIMIT 1"
        );
        if (!$r->num_rows) jsonOut(false, ['error' => 'Not found']);
        jsonOut(true, ['contact' => $r->fetch_assoc()]);
    }

    // ── Bulk update contacts ──────────────────────────────────────────────────
    case 'bulk_update': {
        $ids     = array_filter(array_map('intval', $in['ids'] ?? []));
        $updates = $in['updates'] ?? [];
        if (!$ids || !$updates) jsonOut(false, ['error' => 'Missing ids or updates']);

        $setParts = [];
        // Resolve type_name / group_name to IDs
        if (!empty($updates['type_name'])) {
            $tid = resolveOrCreate($conn, 'contact_types', $updates['type_name']);
            if ($tid) $setParts[] = "type_id=$tid";
        } elseif (isset($updates['type_id']) && $updates['type_id'] !== '') {
            $setParts[] = "type_id=" . (int)$updates['type_id'];
        }
        if (!empty($updates['group_name'])) {
            $gid = resolveOrCreate($conn, 'contact_groups', $updates['group_name']);
            if ($gid) $setParts[] = "group_id=$gid";
        } elseif (isset($updates['group_id']) && $updates['group_id'] !== '') {
            $setParts[] = "group_id=" . (int)$updates['group_id'];
        }
        foreach (['scope','company','assigned_to'] as $col) {
            if (!isset($updates[$col]) || $updates[$col] === '') continue;
            $val = $updates[$col];
            if ($col === 'assigned_to') {
                $setParts[] = "assigned_to=" . (int)$val;
            } else {
                $setParts[] = "$col='" . $conn->real_escape_string($val) . "'";
            }
        }
        if (!$setParts) jsonOut(false, ['error' => 'No valid fields']);

        $idList = implode(',', $ids);
        $setParts[] = "updated_by=$aid";
        $conn->query("UPDATE contacts SET " . implode(',', $setParts) . " WHERE id IN ($idList)");
        logActivity('contacts_bulk_updated', 'contacts', 0, "Bulk updated " . count($ids) . " contacts by #$aid");
        jsonOut(true, ['updated' => count($ids)]);
    }

    default:
        jsonOut(false, ['error' => 'Unknown action']);
}
