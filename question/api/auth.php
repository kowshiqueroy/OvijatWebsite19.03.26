<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$action = $_GET['action'] ?? '';
$body = jsonBody();

switch ($action) {
    case 'check': {
        $username = trim((string)($body['username'] ?? ''));
        if ($username === '') jsonResponse(['ok' => false, 'error' => 'Username required'], 400);
        jsonResponse(['ok' => true, 'exists' => checkUserExists($username)]);
    }

    case 'login': {
        $username = trim((string)($body['username'] ?? ''));
        $password = (string)($body['password'] ?? '');
        if ($username === '' || $password === '') {
            jsonResponse(['ok' => false, 'error' => 'Username and password required'], 400);
        }
        $result = loginUser($username, $password);
        jsonResponse($result, $result['ok'] ? 200 : 401);
    }

    case 'register': {
        $username = trim((string)($body['username'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $displayName = trim((string)($body['display_name'] ?? ''));
        if ($username === '' || $password === '') {
            jsonResponse(['ok' => false, 'error' => 'Username and password required'], 400);
        }
        if (strlen($password) < 4) {
            jsonResponse(['ok' => false, 'error' => 'Password must be at least 4 characters'], 400);
        }
        $result = registerUser($username, $password, $displayName);
        jsonResponse($result, $result['ok'] ? 200 : 409);
    }

    case 'logout': {
        logoutUser();
        jsonResponse(['ok' => true]);
    }

    case 'me': {
        $user = currentUser();
        jsonResponse(['ok' => true, 'user' => $user]);
    }

    default:
        jsonResponse(['ok' => false, 'error' => 'Unknown action'], 400);
}
