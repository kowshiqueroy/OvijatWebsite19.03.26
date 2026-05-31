<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        die("CSRF Token Validation Failed.");
    }

    $id = $_POST['id'];
    $delivery_date = $_POST['delivery_date'] ?: null;
    $delivery_status = $_POST['delivery_status'];
    $hide_from_print = isset($_POST['hide_from_print']) ? 1 : 0;

    // Fetch current status to check if date can be edited
    $current = fetch_one("SELECT delivery_status FROM sales_drafts WHERE id = ?", [$id]);

    if ($current['delivery_status'] == 'Pending') {
        db_query("UPDATE sales_drafts SET delivery_date = ?, delivery_status = ?, hide_from_print = ? WHERE id = ?", 
                 [$delivery_date, $delivery_status, $hide_from_print, $id]);
    } else {
        // Only update status and hide toggle if not pending
        db_query("UPDATE sales_drafts SET delivery_status = ?, hide_from_print = ? WHERE id = ?", 
                 [$delivery_status, $hide_from_print, $id]);
    }

    log_activity($_SESSION['user_id'], "Admin update on Invoice #$id (Status: $delivery_status, Hidden: $hide_from_print)");
    redirect("modules/sales/view.php?id=$id", "Administrative fields updated successfully.", 'success');
}
?>
