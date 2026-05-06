<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

if (isset($_GET['id'])) {
    $draft_id = $_GET['id'];
    $draft = fetch_one("SELECT * FROM sales_drafts WHERE id = ? AND status = 'Draft'", [$draft_id]);

    if (!$draft) {
        redirect('modules/sales/index.php', 'Invalid or already confirmed draft.', 'danger');
    }

    $items = fetch_all("SELECT * FROM sales_items WHERE draft_id = ?", [$draft_id]);
    $conn = get_db_connection();
    $conn->begin_transaction();

    try {
        // 1. Update Draft Status
        $stmt = $conn->prepare("UPDATE sales_drafts SET status = 'Confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?");
        $confirmed_by = $_SESSION['user_id'];
        $stmt->bind_param("ii", $confirmed_by, $draft_id);
        $stmt->execute();

        // 2. Deduct Stock for each item
        $stmt_stock = $conn->prepare("UPDATE products SET stock_qty = stock_qty - (?) WHERE id = ?");
        foreach ($items as $item) {
            $total_qty = $item['billed_qty'] + $item['free_qty'];
            $stmt_stock->bind_param("ii", $total_qty, $item['product_id']);
            $stmt_stock->execute();
        }

        // 3. Update Customer Balance (Increase Debt)
        $stmt_balance = $conn->prepare("UPDATE customers SET balance = balance + (?) WHERE id = ?");
        $stmt_balance->bind_param("di", $draft['grand_total'], $draft['customer_id']);
        $stmt_balance->execute();

        // 4. Create Transaction Record
        $stmt_trans = $conn->prepare("INSERT INTO transactions (customer_id, type, amount, description) VALUES (?, 'Debit', ?, ?)");
        $desc = "Sales Confirmed (Draft #$draft_id)";
        $stmt_trans->bind_param("ids", $draft['customer_id'], $draft['grand_total'], $desc);
        $stmt_trans->execute();

        $conn->commit();
        log_activity($_SESSION['user_id'], "Confirmed Sales Draft #$draft_id and updated accounts.");
        redirect('modules/sales/index.php', "Sales Draft #$draft_id confirmed and accounts updated successfully.");
    } catch (Exception $e) {
        $conn->rollback();
        die("Critical Error during confirmation: " . $e->getMessage());
    }
}
?>
