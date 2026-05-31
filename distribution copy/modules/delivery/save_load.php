<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT, ROLE_MANAGER]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        die("CSRF Token Validation Failed.");
    }

    $truck_no = sanitize($_POST['truck_no']);
    $driver_name = sanitize($_POST['driver_name']);
    $load_date = $_POST['load_date'];
    $invoice_ids = $_POST['invoice_ids'] ?? [];

    if (empty($invoice_ids)) {
        redirect('modules/sales/index.php', 'No invoices selected for truck load.', 'danger');
    }

    $conn = get_db_connection();
    $conn->begin_transaction();

    try {
        // 1. Create Truck Load Record
        $source = sanitize($_POST['source_location'] ?? '');
        $dest = sanitize($_POST['destination_location'] ?? '');
        $remarks = sanitize($_POST['remarks'] ?? '');
        
        $stmt = $conn->prepare("INSERT INTO truck_loads (truck_no, driver_name, source_location, destination_location, remarks, status, created_by) VALUES (?, ?, ?, ?, ?, 'Loaded', ?)");
        $user_id = $_SESSION['user_id'];
        $stmt->bind_param("sssssi", $truck_no, $driver_name, $source, $dest, $remarks, $user_id);
        $stmt->execute();
        $load_id = $conn->insert_id;

        // 2. Link Invoices and Update Status
        $stmt_link = $conn->prepare("INSERT INTO truck_load_items (truck_load_id, invoice_id) VALUES (?, ?)");
        $stmt_status = $conn->prepare("UPDATE sales_drafts SET delivery_status = 'Loading', delivery_date = ? WHERE id = ?");

        foreach ($invoice_ids as $inv_id) {
            $stmt_link->bind_param("ii", $load_id, $inv_id);
            $stmt_link->execute();

            $stmt_status->bind_param("si", $load_date, $inv_id);
            $stmt_status->execute();
        }

        $conn->commit();
        log_activity($_SESSION['user_id'], "Created Truck Load #$load_id with " . count($invoice_ids) . " invoices.");
        redirect('modules/delivery/index.php', "Truck Load #$load_id created and status updated to LOADING.", 'success');
    } catch (Exception $e) {
        $conn->rollback();
        die("Critical Error: " . $e->getMessage());
    }
}
?>
