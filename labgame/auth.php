<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_GET['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $pin = trim($_POST['pin'] ?? '');

    if (strlen($username) < 1 || strlen($username) > 20) {
        echo json_encode(['success' => false, 'error' => 'Name must be 1-20 characters.']);
        exit;
    }
    if (!preg_match('/^[0-9]{4}$/', $pin)) {
        echo json_encode(['success' => false, 'error' => 'PIN must be 4 digits.']);
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM students WHERE username = ?");
    $stmt->execute([$username]);
    $student = $stmt->fetch();

    if ($student) {
        if (!password_verify($pin, $student['pin'])) {
            echo json_encode(['success' => false, 'error' => 'Wrong PIN! Try again. 🤔']);
            exit;
        }
    } else {
        $avatarSeed = preg_replace('/[^a-zA-Z0-9]/', '', $username) . rand(100, 999);
        $pinHash = password_hash($pin, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO students (username, pin, avatar_seed) VALUES (?, ?, ?)");
        $stmt->execute([$username, $pinHash, $avatarSeed]);
        $student = [
            'id' => $db->lastInsertId(),
            'username' => $username,
            'avatar_seed' => $avatarSeed,
            'points' => 0,
            'races_won' => 0,
            'races_played' => 0
        ];
    }

    $_SESSION['student_id'] = (int)$student['id'];
    $_SESSION['username'] = $student['username'];
    $_SESSION['avatar_seed'] = $student['avatar_seed'];

    echo json_encode(['success' => true, 'redirect' => 'index.php']);
    exit;
}

if ($_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}
