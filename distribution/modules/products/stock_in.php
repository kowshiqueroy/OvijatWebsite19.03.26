<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'];
    $qty = intval($_POST['qty']);

    if ($qty > 0) {
        $conn = get_db_connection();
        $conn->begin_transaction();
        try {
            db_query("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?", [$qty, $product_id]);
            db_query("INSERT INTO stock_entries (product_id, user_id, quantity) VALUES (?, ?, ?)", [$product_id, $_SESSION['user_id'], $qty]);
            $conn->commit();
            log_activity($_SESSION['user_id'], "Stock IN: Added $qty to Product ID: $product_id");
            redirect('modules/products/index.php', "Stock updated successfully (+$qty).");
        } catch (Exception $e) {
            $conn->rollback();
            redirect('modules/products/index.php', "Error updating stock.", "danger");
        }
    } else {
        redirect('modules/products/index.php', "Invalid quantity.", "danger");
    }
}
?>
