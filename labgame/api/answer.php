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
$questionId = (int)($_POST['question_id'] ?? 0);
$answerIndex = (int)($_POST['answer_index'] ?? -1);

$db = getDB();

$room = $db->prepare("SELECT status FROM rooms WHERE id = ?");
$room->execute([$roomId]);
$roomStatus = $room->fetchColumn();
if ($roomStatus !== 'racing') {
    echo json_encode(['success' => false, 'error' => 'Race is over']);
    exit;
}

// Check time expiry
$roomFull = $db->prepare("SELECT started_at, duration_minutes FROM rooms WHERE id = ?");
$roomFull->execute([$roomId]);
$roomFullData = $roomFull->fetch();
if ($roomFullData['started_at']) {
    $endTime = strtotime($roomFullData['started_at']) + (int)$roomFullData['duration_minutes'] * 60;
    if (time() >= $endTime) {
        $db->prepare("UPDATE rooms SET status = 'finished', finished_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'racing'")->execute([$roomId]);
        echo json_encode(['success' => false, 'error' => 'Time expired']);
        exit;
    }
}

$participant = $db->prepare("SELECT * FROM race_participants WHERE room_id = ? AND student_id = ? AND status = 'racing'");
$participant->execute([$roomId, $studentId]);
$me = $participant->fetch();

if (!$me) {
    echo json_encode(['success' => false, 'error' => 'Not in this race']);
    exit;
}

// Check already answered this question
$already = $db->prepare("SELECT id FROM race_answers WHERE room_id = ? AND student_id = ? AND question_id = ?");
$already->execute([$roomId, $studentId, $questionId]);
if ($already->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Already answered']);
    exit;
}

$question = $db->prepare("SELECT * FROM questions WHERE id = ?");
$question->execute([$questionId]);
$q = $question->fetch();

if (!$q) {
    echo json_encode(['success' => false, 'error' => 'Invalid question']);
    exit;
}

$isCorrect = ($answerIndex === (int)$q['correct_index']) ? 1 : 0;

$ans = $db->prepare("INSERT INTO race_answers (room_id, student_id, question_id, answer_index, is_correct) VALUES (?, ?, ?, ?, ?)");
$ans->execute([$roomId, $studentId, $questionId, $answerIndex, $isCorrect]);

$newPosition = (float)$me['position'];
$newStreak = 0;
$hasFire = (int)$me['has_fire'];
$finished = false;

if ($isCorrect) {
    $newStreak = (int)$me['streak'] + 1;
    $newPosition = min(RACE_LENGTH, (float)$me['position'] + 3);
    $totalCorrect = (int)$me['total_correct'] + 1;
    $hasFire = ($newStreak > 0 && $newStreak % POWERUP_STREAK === 0) ? 1 : (int)$me['has_fire'];

    $db->prepare("UPDATE race_participants SET position = ?, streak = ?, has_fire = ?, total_correct = ?, status = CASE WHEN ? >= ? THEN 'finished' ELSE 'racing' END WHERE id = ?")
        ->execute([$newPosition, $newStreak, $hasFire, $totalCorrect, $newPosition, RACE_LENGTH, $me['id']]);

    // Points
    $db->prepare("UPDATE students SET points = points + 1 WHERE id = ?")->execute([$studentId]);

    if ($newPosition >= RACE_LENGTH) {
        $finished = true;
        $db->prepare("UPDATE students SET races_played = races_played + 1, races_won = races_won + 1, points = points + 10 WHERE id = ?")->execute([$studentId]);
        // Check if all players finished
        $remaining = $db->prepare("SELECT COUNT(*) as c FROM race_participants WHERE room_id = ? AND status = 'racing'");
        $remaining->execute([$roomId]);
        if ($remaining->fetch()['c'] == 0) {
            $db->prepare("UPDATE rooms SET status = 'finished', finished_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$roomId]);
        }
    }
} else {
    $newPosition = max(0, (float)$me['position'] - 0.5); // Slight backward on wrong
    $db->prepare("UPDATE race_participants SET position = ?, streak = 0, total_wrong = total_wrong + 1 WHERE id = ?")->execute([$newPosition, $me['id']]);
}

// Pick next question (one not yet answered by this player in this room)
$nextQ = null;
for ($attempt = 0; $attempt < 5; $attempt++) {
    $zone = $db->prepare("SELECT zone FROM rooms WHERE id = ?");
    $zone->execute([$roomId]);
    $roomZone = $zone->fetch()['zone'];

    $nq = $db->prepare("
        SELECT q.* FROM questions q 
        WHERE q.zone = ? AND q.id NOT IN (
            SELECT question_id FROM race_answers WHERE room_id = ? AND student_id = ?
        ) ORDER BY RANDOM() LIMIT 1
    ");
    $nq->execute([$roomZone, $roomId, $studentId]);
    $nextQ = $nq->fetch();
    if ($nextQ) break;
}
if (!$nextQ) {
    // All answered - pick any
    $nq = $db->prepare("SELECT * FROM questions WHERE zone = (SELECT zone FROM rooms WHERE id = ?) ORDER BY RANDOM() LIMIT 1");
    $nq->execute([$roomId]);
    $nextQ = $nq->fetch();
}

echo json_encode([
    'success' => true,
    'is_correct' => !!$isCorrect,
    'new_position' => $newPosition,
    'new_streak' => $newStreak,
    'has_fire' => $hasFire,
    'finished' => $finished,
    'next_question' => $nextQ ? [
        'id' => $nextQ['id'],
        'type' => $nextQ['type'],
        'question_text' => $nextQ['question_text'],
        'answers_json' => $nextQ['answers_json'],
        'correct_index' => (int)$nextQ['correct_index']
    ] : null
]);
