<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT, ROLE_MANAGER]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        die("CSRF Token Validation Failed.");
    }

    $load_id = $_POST['load_id'];
    $status = $_POST['status'];

    db_query("UPDATE truck_loads SET status = ? WHERE id = ?", [$status, $load_id]);

    log_activity($_SESSION['user_id'], "Updated Truck Load #$load_id status to $status.");
    redirect("modules/delivery/view.php?id=$load_id", "Truck load status updated.", 'success');
}
?>
