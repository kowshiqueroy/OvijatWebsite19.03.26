<?php
function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function formatDuration($seconds) {
    if (empty($seconds) || !is_numeric($seconds)) return '-';
    $mins = floor($seconds / 60);
    $secs = $seconds % 60;
    return sprintf("%02d:%02d", $mins, $secs);
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M', $time);
}

function saveEditHistory($table, $recordId, $field, $old, $new, $userId) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO edit_history (table_name, record_id, field_name, old_value, new_value, edited_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisssi", $table, $recordId, $field, $old, $new, $userId);
    $stmt->execute();
}

function logActivity($agentId, $action, $details = '') {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO activity_log (agent_id, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $agentId, $action, $details);
    $stmt->execute();
}

function generatePbxId($record) {
    return md5(($record['Caller Number'] ?? '') . ($record['Date'] ?? '') . ($record['Time'] ?? '') . ($record['Destination'] ?? ''));
}

function findOrCreatePerson($phone) {
    global $conn;
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (empty($phone)) return null;
    
    $stmt = $conn->prepare("SELECT id FROM persons WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    $stmt = $conn->prepare("INSERT INTO persons (phone, name, type) VALUES (?, 'Unknown', 'customer')");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    return $conn->insert_id;
}

function formatCopyText($data) {
    $parts = [];
    if (!empty($data['name'])) $parts[] = "Name: " . $data['name'];
    if (!empty($data['phone'])) $parts[] = "Phone: " . $data['phone'];
    if (!empty($data['date'])) $parts[] = "Date: " . $data['date'];
    if (!empty($data['duration'])) $parts[] = "Duration: " . $data['duration'];
    if (!empty($data['status'])) $parts[] = "Status: " . $data['status'];
    if (!empty($data['note'])) $parts[] = "Note: " . $data['note'];
    if (!empty($data['recording'])) $parts[] = "Recording: " . $data['recording'];
    if (!empty($data['drive'])) $parts[] = "Drive: " . $data['drive'];
    return implode(" | ", $parts);
}
?>
