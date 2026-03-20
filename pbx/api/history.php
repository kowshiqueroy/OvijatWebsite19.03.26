<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../functions.php';

requireLogin();

$table = $_GET['table'] ?? '';
$record_id = intval($_GET['record_id'] ?? 0);

if (empty($table) || $record_id === 0) {
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

$stmt = $conn->prepare("SELECT h.*, u.username FROM edit_history h JOIN users u ON h.edited_by = u.id WHERE h.table_name = ? AND h.record_id = ? ORDER BY h.edited_at DESC");
$stmt->bind_param("si", $table, $record_id);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

echo json_encode($history);
?>
