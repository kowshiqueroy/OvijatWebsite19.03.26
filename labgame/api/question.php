<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Lobby polling endpoint
if (isset($_GET['lobby'])) {
    $roomCode = $_GET['room_code'] ?? '';
    $studentId = $_SESSION['student_id'] ?? 0;
    $db = getDB();
    
    $room = $db->prepare("SELECT * FROM rooms WHERE code = ?");
    $room->execute([$roomCode]);
    $roomData = $room->fetch();
    
    if (!$roomData) {
        echo json_encode(['success' => false, 'error' => 'Room not found']);
        exit;
    }
    
    $players = $db->prepare("SELECT rp.*, s.username, s.avatar_seed FROM race_participants rp JOIN students s ON s.id = rp.student_id WHERE rp.room_id = ? ORDER BY rp.joined_at ASC");
    $players->execute([$roomData['id']]);
    $playerList = $players->fetchAll();
    
    $playerInfo = array_map(function($p) {
        return ['username' => $p['username'], 'avatar_seed' => $p['avatar_seed'], 'is_host' => false];
    }, $playerList);
    
    foreach ($playerInfo as $i => &$pi) {
        if ((int)$playerList[$i]['student_id'] === (int)$roomData['host_id']) $pi['is_host'] = true;
    }
    
    echo json_encode([
        'success' => true,
        'room_status' => $roomData['status'],
        'player_count' => count($playerList),
        'players' => $playerInfo,
        'is_host' => $studentId == $roomData['host_id']
    ]);
    exit;
}

// Fetch a question for this player (one not yet answered in this room)
$roomCode = $_GET['room_code'] ?? '';
$studentId = $_SESSION['student_id'] ?? 0;

if (!$studentId) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$db = getDB();

$room = $db->prepare("SELECT * FROM rooms WHERE code = ?");
$room->execute([$roomCode]);
$roomData = $room->fetch();

if (!$roomData) {
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

$question = null;
for ($attempt = 0; $attempt < 5; $attempt++) {
    $nq = $db->prepare("
        SELECT q.* FROM questions q 
        WHERE q.zone = ? AND q.id NOT IN (
            SELECT question_id FROM race_answers WHERE room_id = ? AND student_id = ?
        ) ORDER BY RANDOM() LIMIT 1
    ");
    $nq->execute([$roomData['zone'], $roomData['id'], $studentId]);
    $question = $nq->fetch();
    if ($question) break;
}

if (!$question) {
    $nq = $db->prepare("SELECT * FROM questions WHERE zone = ? ORDER BY RANDOM() LIMIT 1");
    $nq->execute([$roomData['zone']]);
    $question = $nq->fetch();
}

if (!$question) {
    echo json_encode(['success' => false, 'error' => 'No questions']);
    exit;
}

echo json_encode([
    'success' => true,
    'id' => $question['id'],
    'type' => $question['type'],
    'question_text' => $question['question_text'],
    'answers_json' => $question['answers_json'],
    'correct_index' => (int)$question['correct_index']
]);
