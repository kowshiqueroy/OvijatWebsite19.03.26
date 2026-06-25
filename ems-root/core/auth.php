<?php
// Ensure session is started and user is authenticated.
// Call at the top of every protected page.
function require_auth(array $permissions = []): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(defined('EMS_SESSION_NAME') ? EMS_SESSION_NAME : 'EMS_SESS');
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . ems_base_url() . '/index.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }
    foreach ($permissions as $perm) {
        if (!has_permission($perm)) {
            http_response_code(403);
            include dirname(__DIR__) . '/includes/403.php';
            exit;
        }
    }
}

// Check if the logged-in user has a specific permission key
function has_permission(string $key): bool {
    if (isset($_SESSION['permissions']) && in_array('*', $_SESSION['permissions'], true)) return true;
    return isset($_SESSION['permissions']) && in_array($key, $_SESSION['permissions'], true);
}

// Check if the user has any of the given roles
function has_role(string ...$slugs): bool {
    $userRoles = $_SESSION['roles'] ?? [];
    return !empty(array_intersect($slugs, $userRoles));
}

// Load user session after successful login
function load_user_session(int $userId): void {
    require_once dirname(__DIR__) . '/core/functions.php';
    $pdo = db();

    $user = $pdo->prepare('SELECT id, username, full_name, email, avatar, status FROM users WHERE id = :id');
    $user->execute([':id' => $userId]);
    $u = $user->fetch();
    if (!$u || $u['status'] !== 'active') {
        session_destroy();
        return;
    }

    // Roles
    $roles = $pdo->prepare(
        'SELECT r.role_slug FROM user_roles ur
         JOIN roles r ON r.id = ur.role_id
         WHERE ur.user_id = :uid'
    );
    $roles->execute([':uid' => $userId]);
    $roleSlugs = $roles->fetchAll(PDO::FETCH_COLUMN);

    // Permissions (via role_permissions)
    $perms = $pdo->prepare(
        'SELECT DISTINCT p.permission_key FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id
         JOIN user_roles ur ON ur.role_id = rp.role_id
         WHERE ur.user_id = :uid'
    );
    $perms->execute([':uid' => $userId]);
    $permKeys = $perms->fetchAll(PDO::FETCH_COLUMN);

    // Super admin gets wildcard
    if (in_array('super_admin', $roleSlugs, true)) {
        $permKeys[] = '*';
    }

    $_SESSION['user_id']   = $u['id'];
    $_SESSION['username']  = $u['username'];
    $_SESSION['full_name'] = $u['full_name'];
    $_SESSION['email']     = $u['email'];
    $_SESSION['avatar']    = $u['avatar'];
    $_SESSION['roles']     = $roleSlugs;
    $_SESSION['permissions'] = array_unique($permKeys);
    $_SESSION['login_time']  = time();
}

function ems_base_url(): string {
    return defined('EMS_URL') ? EMS_URL : '';
}

// Current user id
function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}
