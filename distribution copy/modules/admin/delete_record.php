<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN]);

if (isset($_GET['table']) && isset($_GET['id'])) {
    $table = $_GET['table'];
    $id = intval($_GET['id']);
    $conn = get_db_connection();
    $conn->begin_transaction();

    try {
        // Special Handling for Cascading Soft-Deletes
        switch ($table) {
            case 'sales_drafts':
                // Check if the sale was already confirmed
                $draft = fetch_one("SELECT status, customer_id, grand_total FROM sales_drafts WHERE id = ?", [$id]);
                if ($draft && $draft['status'] === 'Confirmed') {
                    // 1. Restore stock
                    $items = fetch_all("SELECT product_id, billed_qty, free_qty FROM sales_items WHERE draft_id = ? AND isDelete = 0", [$id]);
                    foreach ($items as $item) {
                        $total_qty = $item['billed_qty'] + $item['free_qty'];
                        db_query("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?", [$total_qty, $item['product_id']]);
                    }
                    // 2. Refund wallet balance
                    db_query("UPDATE customers SET balance = balance + ? WHERE id = ?", [$draft['grand_total'], $draft['customer_id']]);
                    
                    // 3. Soft delete related ledger transactions
                    db_query("UPDATE transactions SET isDelete = 1 WHERE customer_id = ? AND description LIKE ?", [$draft['customer_id'], "Sales Confirmed (Draft #$id)%"]);
                }

                // Mark Sale Items as deleted too
                db_query("UPDATE sales_items SET isDelete = 1 WHERE draft_id = ?", [$id]);
                // Remove from Truck Loads if any
                db_query("DELETE FROM truck_load_items WHERE invoice_id = ?", [$id]);
                break;
            
            case 'truck_loads':
                // Reset delivery status of linked invoices back to Pending if they were Loading
                db_query("UPDATE sales_drafts SET delivery_status = 'Pending' WHERE id IN (SELECT invoice_id FROM truck_load_items WHERE truck_load_id = ?)", [$id]);
                // Mark Load Items as deleted
                db_query("UPDATE truck_load_items SET isDelete = 1 WHERE truck_load_id = ?", [$id]);
                break;

            case 'customers':
                // Optionally deactivate user account
                $cust = fetch_one("SELECT user_id FROM customers WHERE id = ?", [$id]);
                if ($cust) {
                    db_query("UPDATE users SET is_active = 0, isDelete = 1 WHERE id = ?", [$cust['user_id']]);
                }
                break;
        }

        // Generic Soft Delete
        db_query("UPDATE `$table` SET isDelete = 1 WHERE id = ?", [$id]);

        $conn->commit();
        log_activity($_SESSION['user_id'], "Admin MASTER DELETE: Table: $table, ID: $id");
        
        // Redirect back to referring page or a default
        $redirect = $_SERVER['HTTP_REFERER'] ?? '../../index.php';
        redirect_raw($redirect, "Record ID #$id deleted successfully from $table.");
    } catch (Exception $e) {
        $conn->rollback();
        die("Critical Deletion Error: " . $e->getMessage());
    }
}

function redirect_raw($url, $msg) {
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type'] = 'success';
    header("Location: $url");
    exit();
}
?>
