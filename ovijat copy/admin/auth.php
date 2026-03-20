<?php
// admin/auth.php — Include at top of every admin page
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}
function requireRole(string $role): void {
    if ($_SESSION['admin_role'] !== 'superadmin' && $_SESSION['admin_role'] !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}
