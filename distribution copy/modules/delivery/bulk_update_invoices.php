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
    $new_status = $_POST['new_delivery_status'];

    $conn = get_db_connection();
    $conn->begin_transaction();

    try {
        // Get all invoice IDs for this load
        $invoices = fetch_all("SELECT invoice_id FROM truck_load_items WHERE truck_load_id = ?", [$load_id]);
        $ids = array_column($invoices, 'invoice_id');

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE sales_drafts SET delivery_status = ? WHERE id IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s" . str_repeat("i", count($ids)), $new_status, ...$ids);
            $stmt->execute();
        }

        $conn->commit();
        log_activity($_SESSION['user_id'], "Bulk updated delivery status to $new_status for Truck Load #$load_id.");
        redirect("modules/delivery/view.php?id=$load_id", "Delivery status updated for all invoices.", 'success');
    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}
?>
