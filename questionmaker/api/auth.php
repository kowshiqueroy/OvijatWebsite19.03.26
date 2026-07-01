<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'login':
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';
        echo json_encode(loginUser($u, $p));
        break;

    case 'logout':
        logoutUser();
        echo json_encode(['success' => true]);
        break;

    // Save all AI provider keys at once
    case 'update_ai_keys':
        $user = requireLogin();
        $keys = [
            'claude'    => trim($_POST['claude']    ?? ''),
            'openai'    => trim($_POST['openai']    ?? ''),
            'gemini'    => trim($_POST['gemini']    ?? ''),
            'preferred' => trim($_POST['preferred'] ?? 'claude'),
        ];
        $json = json_encode($keys);
        getDB()->prepare('UPDATE users SET ai_keys_json=?, claude_api_key=? WHERE id=?')
               ->execute([$json, $keys['claude'], $user['id']]);
        echo json_encode(['success' => true]);
        break;

    case 'change_password':
        $user = requireLogin();
        $p1   = $_POST['password'] ?? '';
        $p2   = $_POST['confirm']  ?? '';
        if (strlen($p1) < 4) { echo json_encode(['success' => false, 'error' => 'Password must be at least 4 characters']); break; }
        if ($p1 !== $p2)     { echo json_encode(['success' => false, 'error' => 'Passwords do not match']); break; }
        getDB()->prepare('UPDATE users SET password_hash=? WHERE id=?')
               ->execute([password_hash($p1, PASSWORD_DEFAULT), $user['id']]);
        echo json_encode(['success' => true]);
        break;

    // Legacy single-key update (kept for backward compat)
    case 'update_api_key':
        $user = requireLogin();
        $key  = trim($_POST['api_key'] ?? '');
        $db   = getDB();
        // Update both old column and new JSON field
        $stmt = $db->prepare('SELECT ai_keys_json FROM users WHERE id=?');
        $stmt->execute([$user['id']]);
        $row  = $stmt->fetch();
        $keys = json_decode($row['ai_keys_json'] ?? '{}', true) ?: [];
        $keys['claude'] = $key;
        $db->prepare('UPDATE users SET claude_api_key=?, ai_keys_json=? WHERE id=?')
           ->execute([$key, json_encode($keys), $user['id']]);
        echo json_encode(['success' => true]);
        break;

    case 'me':
        $user = getCurrentUser();
        echo json_encode($user ? ['success' => true, 'user' => $user] : ['success' => false]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
