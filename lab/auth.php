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

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'check-user':
        $username = trim($_POST['username'] ?? '');
        if (strlen($username) < 1) {
            echo json_encode(['success' => false, 'error' => 'Username required']);
            exit;
        }
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username FROM students WHERE username = ?");
        $stmt->execute([$username]);
        echo json_encode(['success' => true, 'exists' => (bool)$stmt->fetch()]);
        exit;

    case 'login':
        $username = trim($_POST['username'] ?? '');
        $pin = $_POST['pin'] ?? '';
        if (!$username || !$pin) {
            echo json_encode(['success' => false, 'error' => 'Username and PIN required']);
            exit;
        }
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM students WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }
        if ($user['pin'] !== $pin) {
            echo json_encode(['success' => false, 'error' => 'Incorrect PIN']);
            exit;
        }
        if ($user['is_blocked']) {
            echo json_encode(['success' => false, 'error' => 'Account blocked by teacher']);
            exit;
        }
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_teacher'] = (bool)$user['is_teacher'];
        $db->prepare("UPDATE students SET is_online = 1, last_active = datetime('now','localtime') WHERE id = ?")
           ->execute([$user['id']]);
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'is_teacher' => (bool)$user['is_teacher'],
                'is_blocked' => (bool)$user['is_blocked'],
                'points_academic' => (int)$user['total_academic_points'],
                'points_arcade' => (int)$user['total_arcade_points']
            ]
        ]);
        exit;

    case 'register':
        $username = trim($_POST['username'] ?? '');
        $pin = $_POST['pin'] ?? '';
        if (!$username || strlen($username) < 2) {
            echo json_encode(['success' => false, 'error' => 'Username must be at least 2 characters']);
            exit;
        }
        if (!$pin || strlen($pin) < 4) {
            echo json_encode(['success' => false, 'error' => 'PIN must be at least 4 characters']);
            exit;
        }
        if (strtolower($username) === 'teacher') {
            echo json_encode(['success' => false, 'error' => 'Username not available']);
            exit;
        }
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM students WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Username already taken']);
            exit;
        }
        $stmt = $db->prepare("INSERT INTO students (username, pin, is_online) VALUES (?, ?, 1)");
        $stmt->execute([$username, $pin]);
        $userId = (int)$db->lastInsertId();
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['is_teacher'] = false;
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userId,
                'username' => $username,
                'is_teacher' => false,
                'is_blocked' => false,
                'points_academic' => 0,
                'points_arcade' => 0
            ]
        ]);
        exit;

    case 'logout':
        if (isset($_SESSION['user_id'])) {
            $db = getDB();
            $db->prepare("UPDATE students SET is_online = 0 WHERE id = ?")
               ->execute([$_SESSION['user_id']]);
        }
        $_SESSION = [];
        session_destroy();
        echo json_encode(['success' => true]);
        exit;

    case 'check-session':
        if (isset($_SESSION['user_id'])) {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user) {
                echo json_encode([
                    'active' => true,
                    'user' => [
                        'id' => (int)$user['id'],
                        'username' => $user['username'],
                        'is_teacher' => (bool)$user['is_teacher'],
                        'is_blocked' => (bool)$user['is_blocked'],
                        'points_academic' => (int)$user['total_academic_points'],
                        'points_arcade' => (int)$user['total_arcade_points']
                    ]
                ]);
                exit;
            }
        }
        echo json_encode(['active' => false]);
        exit;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
