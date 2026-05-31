<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        die("CSRF Token Validation Failed.");
    }
    $customer_id = $_POST['customer_id'];
    $type = $_POST['trans_type']; // Credit or Debit
    $amount = floatval($_POST['amount']);
    $user_info = fetch_one("SELECT username FROM users WHERE id = ?", [$_SESSION['user_id']]);
    $acting_user = $user_info['username'] ?? 'Unknown';
    $desc = sanitize($_POST['description']) . " [Posted by: $acting_user]";

    $conn = get_db_connection();
    $conn->begin_transaction();

    try {
        // Adjust Balance (Wallet Model)
        if ($type == 'Credit') {
            // Customer pays money -> Wallet balance increases
            db_query("UPDATE customers SET balance = balance + ? WHERE id = ?", [$amount, $customer_id]);
        } else {
            // Manual deduction from wallet
            db_query("UPDATE customers SET balance = balance - ? WHERE id = ?", [$amount, $customer_id]);
        }

        // Log Transaction
        db_query("INSERT INTO transactions (customer_id, type, amount, description) VALUES (?, ?, ?, ?)", 
                 [$customer_id, $type, $amount, $desc]);

        $conn->commit();
        log_activity($_SESSION['user_id'], "Manual $type of $amount for Customer ID: $customer_id");
        redirect("modules/customers/view.php?id=$customer_id", "Balance updated successfully.");
    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}
?>
