<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = $_POST['customer_id'];
    $created_by = $_SESSION['user_id'];
    $total_amount = $_POST['sub_total'];
    $discount = $_POST['discount'];
    $vat = $_POST['vat_percent'];
    $grand_total = $_POST['grand_total'];

    $product_ids = $_POST['product_id'];
    $notes = $_POST['note'];
    $rates = $_POST['rate'];
    $billed_qtys = $_POST['billed_qty'];
    $free_qtys = $_POST['free_qty'];
    $totals = $_POST['total'];

    $conn = get_db_connection();
    $conn->begin_transaction();

    try {
        // Insert into sales_drafts
        $stmt = $conn->prepare("INSERT INTO sales_drafts (customer_id, created_by, total_amount, discount, vat, grand_total, status) VALUES (?, ?, ?, ?, ?, ?, 'Draft')");
        $stmt->bind_param("iiddid", $customer_id, $created_by, $total_amount, $discount, $vat, $grand_total);
        $stmt->execute();
        $draft_id = $conn->insert_id;

        // Insert into sales_items
        $stmt = $conn->prepare("INSERT INTO sales_items (draft_id, product_id, note, rate, billed_qty, free_qty, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < count($product_ids); $i++) {
            if (empty($product_ids[$i])) continue;
            $stmt->bind_param("iisdiid", $draft_id, $product_ids[$i], $notes[$i], $rates[$i], $billed_qtys[$i], $free_qtys[$i], $totals[$i]);
            $stmt->execute();
        }

        $conn->commit();
        log_activity($_SESSION['user_id'], "Saved sales draft #$draft_id");
        redirect('modules/sales/index.php', "Sales Draft #$draft_id saved successfully.");
    } catch (Exception $e) {
        $conn->rollback();
        die("Error saving draft: " . $e->getMessage());
    }
}
?>
