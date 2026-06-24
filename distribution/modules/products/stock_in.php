<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        redirect('modules/products/index.php', 'CSRF Token Validation Failed.', 'danger');
    }
    $product_id      = intval($_POST['product_id']);
    $qty             = intval($_POST['qty']);
    $batch_no        = sanitize($_POST['batch_no'] ?? '');
    $manufacture_date = $_POST['manufacture_date'] ?: null;
    $expiry_date     = $_POST['expiry_date'] ?: null;
    $location        = sanitize($_POST['location'] ?? '');
    $unit_cost       = floatval($_POST['unit_cost'] ?? 0);
    $notes           = sanitize($_POST['notes'] ?? '');

    if ($qty > 0) {
        $conn = get_db_connection();
        $conn->begin_transaction();
        try {
            // Update main stock
            db_query("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?", [$qty, $product_id]);

            // Legacy stock_entries record
            db_query("INSERT INTO stock_entries (product_id, user_id, quantity, batch_no, expiry_date, notes) VALUES (?, ?, ?, ?, ?, ?)",
                [$product_id, $_SESSION['user_id'], $qty, $batch_no, $expiry_date, $notes]);

            // Create batch record if batch_no provided
            $batch_id = null;
            if ($batch_no) {
                db_query("INSERT INTO product_batches (product_id, batch_no, location, manufacture_date, expiry_date, quantity_in, quantity_remaining, unit_cost, source) VALUES (?,?,?,?,?,?,?,?,'Manual')",
                    [$product_id, $batch_no, $location, $manufacture_date, $expiry_date, $qty, $qty, $unit_cost]);
                $batch_id = $conn->insert_id;
            }

            // Unified stock movement log
            db_query("INSERT INTO stock_movements (product_id, batch_id, movement_type, quantity, reference_type, notes, created_by) VALUES (?,?,'IN',?,'manual',?,?)",
                [$product_id, $batch_id, $qty, $notes, $_SESSION['user_id']]);

            $conn->commit();
            log_activity($_SESSION['user_id'], "Stock IN: +{$qty} to Product #{$product_id}" . ($batch_no ? " [Batch: $batch_no]" : ""));
            redirect('modules/products/index.php', "Stock updated successfully (+$qty)" . ($batch_no ? " — Batch: $batch_no" : "") . ".");
        } catch (Exception $e) {
            $conn->rollback();
            redirect('modules/products/index.php', "Error updating stock: " . $e->getMessage(), "danger");
        }
    } else {
        redirect('modules/products/index.php', "Invalid quantity.", "danger");
    }
}
?>
