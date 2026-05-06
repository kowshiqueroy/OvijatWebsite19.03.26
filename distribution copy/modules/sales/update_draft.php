<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $draft_id = $_POST['draft_id'];
    $customer_id = $_POST['customer_id'];
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
        // Update sales_drafts
        $stmt = $conn->prepare("UPDATE sales_drafts SET customer_id = ?, total_amount = ?, discount = ?, vat = ?, grand_total = ? WHERE id = ?");
        $stmt->bind_param("iddddi", $customer_id, $total_amount, $discount, $vat, $grand_total, $draft_id);
        $stmt->execute();

        // Delete old items
        db_query("DELETE FROM sales_items WHERE draft_id = ?", [$draft_id]);

        // Insert new items
        $stmt = $conn->prepare("INSERT INTO sales_items (draft_id, product_id, note, rate, billed_qty, free_qty, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < count($product_ids); $i++) {
            if (empty($product_ids[$i])) continue;
            $stmt->bind_param("iisdiid", $draft_id, $product_ids[$i], $notes[$i], $rates[$i], $billed_qtys[$i], $free_qtys[$i], $totals[$i]);
            $stmt->execute();
        }

        $conn->commit();
        log_activity($_SESSION['user_id'], "Updated sales draft #$draft_id");
        redirect('modules/sales/view.php?id=' . $draft_id, "Sales Draft updated successfully.");
    } catch (Exception $e) {
        $conn->rollback();
        die("Error updating draft: " . $e->getMessage());
    }
}
?>
