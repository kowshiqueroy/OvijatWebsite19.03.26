<?php
require_once __DIR__ . '/config.php';

$roomCode = $_GET['room'] ?? '';
if (!$roomCode) { header('Location: index.php'); exit; }

$studentId = $_SESSION['student_id'] ?? null;
if (!$studentId) { header('Location: index.php'); exit; }

$db = getDB();
$room = $db->prepare("SELECT * FROM rooms WHERE code = ?");
$room->execute([$roomCode]);
$roomData = $room->fetch();
if (!$roomData) { echo "Room not found"; exit; }

$me = $db->prepare("SELECT * FROM race_participants WHERE room_id = ? AND student_id = ?");
$me->execute([$roomData['id'], $studentId]);
$myData = $me->fetch();
if (!$myData) { header('Location: index.php'); exit; }

$zoneJson = file_get_contents(__DIR__ . '/zones/' . $roomData['zone'] . '.json');
$zoneConfig = json_decode($zoneJson, true);

$participants = $db->prepare("SELECT rp.*, s.username, s.avatar_seed FROM race_participants rp JOIN students s ON s.id = rp.student_id WHERE rp.room_id = ? ORDER BY rp.joined_at ASC");
$participants->execute([$roomData['id']]);
$allPlayers = $participants->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= SITE_NAME ?> - Race</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="race-page" data-zone="<?= htmlspecialchars($roomData['zone']) ?>" data-room-id="<?= $roomData['id'] ?>" data-room-code="<?= htmlspecialchars($roomCode) ?>" data-student-id="<?= $studentId ?>" data-duration="<?= (int)$roomData['duration_minutes'] ?>">

    <!-- LEFT: CANVAS TRACK -->
    <div id="trackPanel">
        <canvas id="raceCanvas"></canvas>
        <div id="trackTimer"></div>
        <div id="raceLeaderboard"></div>
    </div>

    <!-- RIGHT: QUESTION & ANSWERS -->
    <div id="sidePanel">
        <div id="sideHeader">
            <div id="sideZone"><?= htmlspecialchars($zoneConfig['emoji']) ?> <?= htmlspecialchars($zoneConfig['name']) ?></div>
            <div id="sideStreak">🔥 0</div>
        </div>

        <div id="questionArea">
            <div id="qTimer">⏱️ 30</div>
            <div id="qVisual">Loading...</div>
            <div id="qTypeBadge"></div>
        </div>

        <div id="answersArea">
            <button class="ans-btn ans-a" data-index="0">A</button>
            <button class="ans-btn ans-b" data-index="1">B</button>
            <button class="ans-btn ans-c" data-index="2">C</button>
            <button class="ans-btn ans-d" data-index="3">D</button>
        </div>

        <div id="fireArea">
            <button id="btnFire" class="hidden">🔥🔥 FIRE! 🔥🔥</button>
        </div>

        <div id="sideStatus">
            <span id="sideQuestionNum">Q1</span>
            <span id="sideTimeLeft"></span>
        </div>
    </div>

    <!-- OVERLAYS -->
    <div id="countdownOverlay" class="hidden">
        <div id="countdownText">3</div>
    </div>

    <div id="finishOverlay" class="hidden">
        <div id="finishContent">
            <div id="finishEmoji">🏆</div>
            <div id="finishTitle">You Win!</div>
            <div id="finishSub">#1 of 4</div>
            <button id="btnBackToLobby" class="btn btn-primary btn-large">🏠 Back to Lobby</button>
        </div>
    </div>

    <div id="feedbackFlash" class="hidden"></div>
    <div id="effectFlash" class="hidden"></div>

    <script>
        const ZONE_CONFIG = <?= json_encode($zoneConfig) ?>;
        const ROOM_ID = <?= $roomData['id'] ?>;
        const ROOM_CODE = '<?= htmlspecialchars($roomCode) ?>';
        const STUDENT_ID = <?= $studentId ?>;
        const RACE_LENGTH = <?= RACE_LENGTH ?>;
        const BASE_SPEED = <?= BASE_SPEED ?>;
        const BOOST_SPEED = <?= BOOST_SPEED ?>;
        const SLIME_SPEED = <?= SLIME_SPEED ?>;
        const FIRE_SPEED = <?= FIRE_SPEED ?>;
        const BOOST_DURATION = <?= BOOST_DURATION ?>;
        const SLIME_DURATION = <?= SLIME_DURATION ?>;
        const FIRE_DURATION = <?= FIRE_DURATION ?>;
        const POWERUP_STREAK = <?= POWERUP_STREAK ?>;
        const QUESTION_TIME = <?= QUESTION_TIME ?>;
        const DURATION_MINUTES = <?= (int)$roomData['duration_minutes'] ?>;
        const ALL_PLAYERS = <?= json_encode($allPlayers) ?>;
    </script>
    <script src="assets/js/polyfill.js"></script>
    <script src="assets/js/audio.js"></script>
    <script src="assets/js/speech.js"></script>
    <script src="assets/js/canvas.js"></script>
    <script src="assets/js/powerups.js"></script>
    <script src="assets/js/sse-client.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
