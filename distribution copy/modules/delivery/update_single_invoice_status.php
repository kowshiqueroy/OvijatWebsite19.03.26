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
    $invoice_id = $_POST['invoice_id'];
    $status = $_POST['status'];

    db_query("UPDATE sales_drafts SET delivery_status = ? WHERE id = ?", [$status, $invoice_id]);

    log_activity($_SESSION['user_id'], "Updated Delivery Status of Invoice #$invoice_id to $status (from Load #$load_id)");
    redirect("modules/delivery/view.php?id=$load_id", "Invoice #$invoice_id status updated to $status.", 'success');
}
?>
