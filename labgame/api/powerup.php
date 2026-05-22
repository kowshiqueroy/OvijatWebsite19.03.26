<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$studentId = $_SESSION['student_id'] ?? null;
if (!$studentId) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$roomId = (int)($_POST['room_id'] ?? 0);
$db = getDB();

$roomOk = $db->prepare("SELECT status FROM rooms WHERE id = ?");
$roomOk->execute([$roomId]);
if ($roomOk->fetchColumn() !== 'racing') {
    echo json_encode(['success' => false, 'error' => 'Race is over']);
    exit;
}

$me = $db->prepare("SELECT * FROM race_participants WHERE room_id = ? AND student_id = ? AND status = 'racing'");
$me->execute([$roomId, $studentId]);
$myData = $me->fetch();

if (!$myData || !$myData['has_fire']) {
    echo json_encode(['success' => false, 'error' => 'No fire power available']);
    exit;
}

// Target: player ahead of me (nearest ahead)
$target = $db->prepare("SELECT * FROM race_participants WHERE room_id = ? AND status = 'racing' AND student_id != ? AND position > ? ORDER BY position ASC LIMIT 1");
$target->execute([$roomId, $studentId, $myData['position']]);
$targetData = $target->fetch();

if (!$targetData) {
    // Nobody ahead, hit the leader
    $target = $db->prepare("SELECT * FROM race_participants WHERE room_id = ? AND status = 'racing' AND student_id != ? ORDER BY position DESC LIMIT 1");
    $target->execute([$roomId, $studentId]);
    $targetData = $target->fetch();
    if (!$targetData) {
        echo json_encode(['success' => false, 'error' => 'No target']);
        exit;
    }
}

// Push target backward 5 positions + set fire visual
$newPos = max(0, (float)$targetData['position'] - 5);
$fireUntil = date('Y-m-d H:i:s', time() + FIRE_DURATION);
$db->prepare("UPDATE race_participants SET position = ?, fire_until = ? WHERE id = ?")
    ->execute([$newPos, $fireUntil, $targetData['id']]);

// Log
$db->prepare("INSERT INTO powerups_log (room_id, from_student_id, to_student_id, type) VALUES (?, ?, ?, 'fire')")
    ->execute([$roomId, $studentId, $targetData['student_id']]);

// Clear my fire
$db->prepare("UPDATE race_participants SET has_fire = 0, streak = 0 WHERE id = ?")->execute([$myData['id']]);

echo json_encode([
    'success' => true,
    'type' => 'fire',
    'target_id' => $targetData['student_id'],
    'target_name' => $targetData['username'] ?? 'Someone'
]);
