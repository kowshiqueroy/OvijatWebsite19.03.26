<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

$id = $_GET['id'] ?? 0;
$trans = fetch_one("SELECT hide_from_print FROM transactions WHERE id = ?", [$id]);

if ($trans) {
    $new_status = $trans['hide_from_print'] ? 0 : 1;
    db_query("UPDATE transactions SET hide_from_print = ? WHERE id = ?", [$new_status, $id]);
    log_activity($_SESSION['user_id'], "Toggled print visibility for Transaction #$id to $new_status");
}

redirect('modules/reports/transactions.php', 'Transaction visibility updated.', 'success');
?>
