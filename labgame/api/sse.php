<?php
require_once __DIR__ . '/../config.php';
session_write_close();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (function_exists('ini_set')) {
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', false);
    ini_set('implicit_flush', true);
}

$roomCode = $_GET['room'] ?? '';
$db = getDB();

$room = $db->prepare("SELECT * FROM rooms WHERE code = ?");
$room->execute([$roomCode]);
$roomData = $room->fetch();

if (!$roomData) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Room not found']) . "\n\n";
    ob_flush(); flush();
    exit;
}

$roomId = $roomData['id'];
$durationSeconds = (int)$roomData['duration_minutes'] * 60;

set_time_limit(0);
$tick = 0;

while (true) {
    if (connection_aborted()) break;
    $tick++;
    $now = time();

    // Re-read room data each tick to get accurate started_at and status
    $roomData = $db->prepare("SELECT * FROM rooms WHERE id = ?");
    $roomData->execute([$roomId]);
    $fullRoom = $roomData->fetch();

    if (!$fullRoom) break;

    if ($fullRoom['status'] === 'racing' && $fullRoom['started_at']) {
        $startedAt = strtotime($fullRoom['started_at']);
        $endTime = $startedAt + $durationSeconds;
        if ($now >= $endTime) {
            $db->prepare("UPDATE rooms SET status = 'finished', finished_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'racing'")->execute([$roomId]);
            $fullRoom['status'] = 'finished';
        }
        $timeRemaining = max(0, $endTime - $now);
    } else {
        $timeRemaining = $durationSeconds;
    }

    $participants = $db->prepare("SELECT rp.*, s.username, s.avatar_seed FROM race_participants rp JOIN students s ON s.id = rp.student_id WHERE rp.room_id = ? ORDER BY rp.position DESC");
    $participants->execute([$roomId]);
    $players = $participants->fetchAll();

    $fires = $db->prepare("SELECT pl.*, s.username as from_name, (SELECT username FROM students WHERE id = pl.to_student_id) as to_name FROM powerups_log pl JOIN students s ON s.id = pl.from_student_id WHERE pl.room_id = ? AND pl.landed_at > datetime('now', '-3 seconds')");
    $fires->execute([$roomId]);
    $recentFires = $fires->fetchAll();

    $data = [
        'tick' => $tick,
        'room_status' => $fullRoom['status'],
        'players' => $players,
        'fires' => $recentFires,
        'time_remaining' => $timeRemaining
    ];

    echo "event: race\n";
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush(); flush();

    usleep(200000);
}
