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

$action = $_POST['action'] ?? '';
$db = getDB();

if ($action === 'create') {
    $zone = $_POST['zone'] ?? 'candyland';
    $questionType = $_POST['question_type'] ?? 'random';
    $durationMinutes = max(1, min(10, (int)($_POST['duration'] ?? 3)));

    if (!in_array($zone, ZONES)) {
        echo json_encode(['success' => false, 'error' => 'Invalid zone']);
        exit;
    }
    if (!in_array($questionType, QUESTION_TYPES)) {
        echo json_encode(['success' => false, 'error' => 'Invalid question type']);
        exit;
    }

    $code = '';
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
        $check = $db->prepare("SELECT id FROM rooms WHERE code = ? AND status = 'waiting'");
        $check->execute([$code]);
        if (!$check->fetch()) break;
    }

    $stmt = $db->prepare("INSERT INTO rooms (code, zone, host_id, question_type, duration_minutes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$code, $zone, $studentId, $questionType, $durationMinutes]);
    $roomId = $db->lastInsertId();

    $stmt = $db->prepare("INSERT INTO race_participants (room_id, student_id) VALUES (?, ?)");
    $stmt->execute([$roomId, $studentId]);

    echo json_encode(['success' => true, 'code' => $code, 'zone' => $zone]);
    exit;
}

if ($action === 'join') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $stmt = $db->prepare("SELECT * FROM rooms WHERE code = ? AND status = 'waiting'");
    $stmt->execute([$code]);
    $room = $stmt->fetch();

    if (!$room) {
        echo json_encode(['success' => false, 'error' => 'Room not found or already started']);
        exit;
    }

    $check = $db->prepare("SELECT id FROM race_participants WHERE room_id = ? AND student_id = ?");
    $check->execute([$room['id'], $studentId]);
    if (!$check->fetch()) {
        $count = $db->prepare("SELECT COUNT(*) as c FROM race_participants WHERE room_id = ?");
        $count->execute([$room['id']]);
        $cnt = $count->fetch()['c'];
        if ($cnt >= $room['max_players']) {
            echo json_encode(['success' => false, 'error' => 'Room is full']);
            exit;
        }
        $stmt = $db->prepare("INSERT INTO race_participants (room_id, student_id) VALUES (?, ?)");
        $stmt->execute([$room['id'], $studentId]);
    }

    echo json_encode(['success' => true, 'code' => $code, 'zone' => $room['zone']]);
    exit;
}

if ($action === 'start') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $room = $db->prepare("SELECT * FROM rooms WHERE code = ? AND status = 'waiting'");
    $room->execute([$code]);
    $roomData = $room->fetch();

    if (!$roomData) {
        echo json_encode(['success' => false, 'error' => 'Room not found']);
        exit;
    }

    if ((int)$roomData['host_id'] !== $studentId) {
        echo json_encode(['success' => false, 'error' => 'Only the host can start']);
        exit;
    }

    $count = $db->prepare("SELECT COUNT(*) as c FROM race_participants WHERE room_id = ?");
    $count->execute([$roomData['id']]);
    $playerCount = $count->fetch()['c'];

    if ($playerCount < MIN_PLAYERS) {
        echo json_encode(['success' => false, 'error' => "Need at least " . MIN_PLAYERS . " players"]);
        exit;
    }

    $db->prepare("UPDATE rooms SET status = 'racing', started_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$roomData['id']]);

    echo json_encode(['success' => true, 'code' => $code]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
