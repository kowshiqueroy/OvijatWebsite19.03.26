<?php
require_once __DIR__ . '/database.php';

header('Content-Type: application/json');

session_set_cookie_params([
    'lifetime' => 86400 * 7,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function getUser($db, $id) {
    $s = $db->prepare("SELECT * FROM students WHERE id = ?");
    $s->execute([$id]);
    return $s->fetch();
}

function requireTeacher($db, $id) {
    $u = getUser($db, $id);
    if (!$u || !$u['is_teacher']) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    return $u;
}

switch ($action) {
    // ─── TASKS (Teacher) ──────────────────────────────
    case 'import-tasks':
        $teacher = requireTeacher($db, $userId);
        $tasks = json_decode($_POST['tasks'] ?? '[]', true);
        if (!is_array($tasks)) {
            echo json_encode(['error' => 'Invalid JSON']); break;
        }
        $cnt = 0;
        $st = $db->prepare("INSERT INTO lab_tasks (type, content_json, time_limit, created_by) VALUES (?, ?, ?, ?)");
        foreach ($tasks as $t) {
            $type = $t['type'] ?? 'mcq';
            $content = json_encode($t['content'] ?? $t);
            $tl = (int)($t['time_limit'] ?? 0);
            $st->execute([$type, $content, $tl, $userId]);
            $cnt++;
        }
        echo json_encode(['success' => true, 'imported' => $cnt]);
        break;

    case 'get-tasks':
        $type = $_GET['type'] ?? '';
        $sql = "SELECT * FROM lab_tasks";
        $params = [];
        if ($type) { $sql .= " WHERE type = ?"; $params[] = $type; }
        $sql .= " ORDER BY created_at DESC";
        $st = $db->prepare($sql);
        $st->execute($params);
        $tasks = $st->fetchAll();
        foreach ($tasks as &$t) {
            $t['content'] = json_decode($t['content_json'], true);
            unset($t['content_json']);
        }
        echo json_encode(['tasks' => $tasks]);
        break;

    case 'get-pushed-task':
        $st = $db->prepare("SELECT pt.*, lt.type, lt.content_json, lt.time_limit FROM pushed_tasks pt JOIN lab_tasks lt ON pt.task_id = lt.id WHERE pt.student_id = ? AND pt.is_active = 1 LIMIT 1");
        $st->execute([$userId]);
        $task = $st->fetch();
        if ($task) {
            $task['content'] = json_decode($task['content_json'], true);
            unset($task['content_json']);
        }
        echo json_encode(['task' => $task ?: null]);
        break;

    case 'dismiss-pushed-task':
        $st = $db->prepare("UPDATE pushed_tasks SET is_active = 0 WHERE student_id = ? AND is_active = 1");
        $st->execute([$userId]);
        echo json_encode(['success' => true]);
        break;

    // ─── MCQ ──────────────────────────────────────────
    case 'submit-mcq':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $score  = (int)($_POST['score'] ?? 0);
        $total  = (int)($_POST['total'] ?? 0);
        $points = (int)($_POST['points'] ?? 0);
        if ($taskId > 0) {
            $db->prepare("INSERT INTO mcq_attempts (student_id, task_id, score, total) VALUES (?, ?, ?, ?)")
               ->execute([$userId, $taskId, $score, $total]);
        }
        if ($points > 0)
            $db->prepare("UPDATE students SET total_academic_points = total_academic_points + ? WHERE id = ?")
               ->execute([$points, $userId]);
        echo json_encode(['success' => true, 'points_earned' => $points]);
        break;

    // ─── TYPING ───────────────────────────────────────
    case 'submit-typing':
        $taskId   = (int)($_POST['task_id'] ?? 0);
        $wpm      = (float)($_POST['wpm'] ?? 0);
        $accuracy = (float)($_POST['accuracy'] ?? 0);
        $points   = max(1, (int)($wpm * $accuracy / 100));
        $db->prepare("INSERT INTO typing_results (student_id, task_id, wpm, accuracy, points_earned) VALUES (?, ?, ?, ?, ?)")
           ->execute([$userId, $taskId, $wpm, $accuracy, $points]);
        $db->prepare("UPDATE students SET total_academic_points = total_academic_points + ? WHERE id = ?")
           ->execute([$points, $userId]);
        echo json_encode(['success' => true, 'points_earned' => $points]);
        break;

    // ─── MATCHES ──────────────────────────────────────
    case 'create-match':
        $gameType = $_POST['game_type'] ?? '';
        $opponentId = (int)($_POST['opponent_id'] ?? 0);
        if (!in_array($gameType, ['sprint','tictactoe'])) {
            echo json_encode(['error' => 'Invalid game type']); break;
        }

        $db->exec("UPDATE matches SET is_active = 0 WHERE is_active = 1 AND created_at < datetime('now','localtime','-2 minutes')");

        // Atomic: find or create a match inside a transaction
        $db->beginTransaction();

        // Check if already in a match (prevents duplicates)
        $st = $db->prepare("SELECT m.*, s.username as p1_name, s2.username as p2_name FROM matches m JOIN students s ON m.player1_id = s.id JOIN students s2 ON m.player2_id = s2.id WHERE (m.player1_id = ? OR m.player2_id = ?) AND m.is_active = 1 AND m.game_type = ? LIMIT 1");
        $st->execute([$userId, $userId, $gameType]);
        $existing = $st->fetch();
        if ($existing) {
            $db->commit();
            $existing['state'] = json_decode($existing['state_json'], true);
            unset($existing['state_json']);
            echo json_encode(['success' => true, 'match' => $existing, 'is_opponent' => $existing['player2_id'] == $userId]);
            break;
        }

        // If specific opponent requested
        if ($opponentId) {
            $st = $db->prepare("SELECT id, username, points_arcade FROM students WHERE id = ? AND is_online = 1 AND is_teacher = 0 AND is_blocked = 0");
            $st->execute([$opponentId]);
            $opponent = $st->fetch();
            if (!$opponent) {
                $db->rollBack();
                echo json_encode(['error' => 'Opponent not available']); break;
            }
        } else {
            // Find an opponent randomly
            $st = $db->prepare("SELECT id, username, points_arcade FROM students WHERE is_online = 1 AND is_teacher = 0 AND id != ? AND is_blocked = 0 AND id NOT IN (SELECT player1_id FROM matches WHERE is_active = 1 AND game_type = ? UNION SELECT player2_id FROM matches WHERE is_active = 1 AND game_type = ?) LIMIT 1");
            $st->execute([$userId, $gameType, $gameType]);
            $opponent = $st->fetch();
            if (!$opponent) {
                $db->rollBack();
                echo json_encode(['error' => 'No opponent available']); break;
            }
        }
        $default = $gameType === 'tictactoe'
            ? json_encode(['board' => ['','','','','','','','',''], 'turn' => $opponent['id'], 'last_move' => null, 'over' => false])
            : json_encode(['p1_dist' => 0, 'p2_dist' => 0, 'finished' => false, 'winner' => 0]);
        $st = $db->prepare("INSERT INTO matches (game_type, player1_id, player2_id, state_json) VALUES (?, ?, ?, ?)");
        $st->execute([$gameType, $userId, $opponent['id'], $default]);
        $matchId = (int)$db->lastInsertId();
        $db->commit();
        echo json_encode(['success' => true, 'match_id' => $matchId, 'opponent' => $opponent]);
        break;

    case 'update-match':
        $matchId = (int)($_POST['match_id'] ?? 0);
        $state   = $_POST['state'] ?? '{}';
        $st = $db->prepare("SELECT * FROM matches WHERE id = ? AND (player1_id = ? OR player2_id = ?) AND is_active = 1");
        $st->execute([$matchId, $userId, $userId]);
        if (!$st->fetch()) { echo json_encode(['error' => 'Match not found']); break; }
        $db->prepare("UPDATE matches SET state_json = ? WHERE id = ?")->execute([$state, $matchId]);
        echo json_encode(['success' => true]);
        break;

    case 'end-match':
        $matchId  = (int)($_POST['match_id'] ?? 0);
        $winnerId = (int)($_POST['winner_id'] ?? 0);
        $points   = (int)($_POST['points'] ?? 10);
        $st = $db->prepare("SELECT * FROM matches WHERE id = ? AND (player1_id = ? OR player2_id = ?)");
        $st->execute([$matchId, $userId, $userId]);
        if (!$st->fetch()) { echo json_encode(['error' => 'Match not found']); break; }
        $db->prepare("UPDATE matches SET winner_id = ?, is_active = 0 WHERE id = ?")->execute([$winnerId, $matchId]);
        if ($winnerId > 0)
            $db->prepare("UPDATE students SET total_arcade_points = total_arcade_points + ? WHERE id = ?")
               ->execute([$points, $winnerId]);
        echo json_encode(['success' => true]);
        break;

    // ─── TEACHER CONTROLS ────────────────────────────
    case 'block-student':
        requireTeacher($db, $userId);
        $db->prepare("UPDATE students SET is_blocked = 1 WHERE id = ?")->execute([(int)($_POST['student_id'] ?? 0)]);
        echo json_encode(['success' => true]);
        break;

    case 'unblock-student':
        requireTeacher($db, $userId);
        $db->prepare("UPDATE students SET is_blocked = 0 WHERE id = ?")->execute([(int)($_POST['student_id'] ?? 0)]);
        echo json_encode(['success' => true]);
        break;

    case 'push-task':
        requireTeacher($db, $userId);
        $sid  = (int)($_POST['student_id'] ?? 0);
        $tid  = (int)($_POST['task_id'] ?? 0);
        $db->prepare("INSERT INTO pushed_tasks (student_id, task_id) VALUES (?, ?)")->execute([$sid, $tid]);
        echo json_encode(['success' => true]);
        break;

    case 'get-warnings':
        requireTeacher($db, $userId);
        $rows = $db->query("SELECT sw.*, s.username FROM student_warnings sw JOIN students s ON sw.student_id = s.id ORDER BY sw.created_at DESC LIMIT 50")->fetchAll();
        echo json_encode(['warnings' => $rows]);
        break;

    // ─── ANTI-CHEAT ──────────────────────────────────
    case 'log-warning':
        $type    = $_POST['type'] ?? 'tab_switch';
        $details = $_POST['details'] ?? '';
        $db->prepare("INSERT INTO student_warnings (student_id, warning_type, details) VALUES (?, ?, ?)")
           ->execute([$userId, $type, $details]);
        echo json_encode(['success' => true]);
        break;

    // ─── SCREEN / HEARTBEAT ──────────────────────────
    case 'update-screen':
        $screen = $_POST['screen'] ?? 'dashboard';
        $db->prepare("UPDATE students SET current_screen = ?, last_active = datetime('now','localtime') WHERE id = ?")
           ->execute([$screen, $userId]);
        echo json_encode(['success' => true]);
        break;

    case 'heartbeat':
        $db->prepare("UPDATE students SET last_active = datetime('now','localtime') WHERE id = ?")
           ->execute([$userId]);

        $db->exec("UPDATE matches SET is_active = 0 WHERE is_active = 1 AND created_at < datetime('now','localtime','-10 minutes')");

        $user = getUser($db, $userId);
        echo json_encode(['success' => true, 'is_blocked' => $user ? (bool)$user['is_blocked'] : false]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
