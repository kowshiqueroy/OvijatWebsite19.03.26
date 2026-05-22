<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $customer = fetch_one("SELECT is_active, user_id FROM customers WHERE id = ?", [$id]);
    
    if ($customer) {
        $new_status = $customer['is_active'] ? 0 : 1;
        
        $conn = get_db_connection();
        $conn->begin_transaction();
        
        try {
            db_query("UPDATE customers SET is_active = ? WHERE id = ?", [$new_status, $id]);
            db_query("UPDATE users SET is_active = ? WHERE id = ?", [$new_status, $customer['user_id']]);
            
            $conn->commit();
            $action = $new_status ? "Activated" : "Deactivated";
            log_activity($_SESSION['user_id'], "$action Customer ID: $id");
            redirect('modules/customers/index.php', "Customer $action successfully.");
        } catch (Exception $e) {
            $conn->rollback();
            redirect('modules/customers/index.php', "Error updating status.", 'danger');
        }
    }
}
redirect('modules/customers/index.php');
?>
