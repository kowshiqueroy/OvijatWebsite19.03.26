<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role(ROLE_ADMIN);

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    if ($id == $_SESSION['user_id']) {
        redirect('modules/users/index.php', 'You cannot block yourself.', 'danger');
    }

    $user = fetch_one("SELECT is_active FROM users WHERE id = ?", [$id]);
    if ($user) {
        $new_status = $user['is_active'] ? 0 : 1;
        db_query("UPDATE users SET is_active = ? WHERE id = ?", [$new_status, $id]);
        
        $action = $new_status ? "Activated" : "Blocked";
        log_activity($_SESSION['user_id'], "$action User ID: $id");
        redirect('modules/users/index.php', "User $action successfully.");
    }
}
redirect('modules/users/index.php');
?>
