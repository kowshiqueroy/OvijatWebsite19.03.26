<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        redirect('modules/products/damages.php', 'CSRF Token Validation Failed.', 'danger');
    }
    
    $product_id = $_POST['product_id'];
    $qty = intval($_POST['qty']);
    $reason = sanitize($_POST['reason']);

    if ($qty > 0) {
        $product = fetch_one("SELECT stock_qty FROM products WHERE id = ?", [$product_id]);
        
        if ($product['stock_qty'] < $qty) {
            redirect('modules/products/damages.php', "Insufficient stock to record this damage.", 'danger');
        }

        $conn = get_db_connection();
        $conn->begin_transaction();
        try {
            // Deduct from Stock
            db_query("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?", [$qty, $product_id]);
            
            // Log Damage
            db_query("INSERT INTO stock_damages (product_id, user_id, quantity, reason) VALUES (?, ?, ?, ?)", 
                     [$product_id, $_SESSION['user_id'], $qty, $reason]);
            
            $conn->commit();
            log_activity($_SESSION['user_id'], "Stock DAMAGE: Deducted $qty from Product ID: $product_id. Reason: $reason");
            redirect('modules/products/damages.php', "Damage record added successfully.");
        } catch (Exception $e) {
            $conn->rollback();
            redirect('modules/products/damages.php', "Error recording damage.", "danger");
        }
    } else {
        redirect('modules/products/damages.php', "Invalid quantity.", "danger");
    }
}
?>
