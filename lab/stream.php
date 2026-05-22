<?php
require_once __DIR__ . '/database.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

set_time_limit(0);

$db = getDB();

while (true) {
    $db->exec("UPDATE students SET is_online = 0 WHERE last_active < datetime('now','localtime','-30 seconds') AND is_online = 1");

    $online = $db->query("SELECT id, username, is_blocked, total_academic_points, total_arcade_points, current_screen FROM students WHERE is_online = 1 ORDER BY username")->fetchAll();

    $academic = $db->query("SELECT id, username, total_academic_points FROM students WHERE is_teacher = 0 ORDER BY total_academic_points DESC LIMIT 10")->fetchAll();

    $arcade = $db->query("SELECT id, username, total_arcade_points FROM students WHERE is_teacher = 0 ORDER BY total_arcade_points DESC LIMIT 3")->fetchAll();

    $matches = $db->query("SELECT m.*, s1.username as p1_name, s2.username as p2_name FROM matches m LEFT JOIN students s1 ON m.player1_id = s1.id LEFT JOIN students s2 ON m.player2_id = s2.id WHERE m.is_active = 1")->fetchAll();

    foreach ($matches as &$m) {
        $m['state'] = json_decode($m['state_json'], true);
        unset($m['state_json']);
    }

    $data = json_encode([
        'online'        => $online,
        'academic_lb'   => $academic,
        'arcade_podium' => $arcade,
        'matches'       => $matches,
        'timestamp'     => time()
    ]);

    echo "data: {$data}\n\n";

    if (ob_get_level() > 0) ob_flush();
    flush();
    if (connection_aborted()) break;

    sleep(1);
}
