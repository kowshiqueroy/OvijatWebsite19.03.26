<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

if (isset($_GET['id'])) {
    $draft_id = $_GET['id'];
    $draft = fetch_one("SELECT s.*, c.name as customer_name FROM sales_drafts s JOIN customers c ON s.customer_id = c.id WHERE s.id = ? AND s.isDelete = 0 AND s.status = 'Draft'", [$draft_id]);

    if (!$draft) {
        redirect('modules/sales/index.php', 'Invalid or already confirmed draft.', 'danger');
    }

    $items = fetch_all("SELECT i.*, p.name as product_name, p.stock_qty FROM sales_items i JOIN products p ON i.product_id = p.id WHERE i.isDelete = 0 AND i.draft_id = ?", [$draft_id]);
    
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

        // 3. Update Customer Balance (Deduct from Wallet)
        $stmt_balance = $conn->prepare("UPDATE customers SET balance = balance - (?) WHERE id = ?");
        $stmt_balance->bind_param("di", $draft['grand_total'], $draft['customer_id']);
        $stmt_balance->execute();

        // 4. Create Transaction Record (Debit = Deduction)
        $stmt_trans = $conn->prepare("INSERT INTO transactions (customer_id, type, amount, description) VALUES (?, 'Debit', ?, ?)");
        $user_info = fetch_one("SELECT username FROM users WHERE id = ?", [$_SESSION['user_id']]);
        $acting_user = $user_info['username'] ?? 'System';
        $desc = "Sales Confirmed (Draft #$draft_id) [Posted by: $acting_user]";
        $stmt_trans->bind_param("ids", $draft['customer_id'], $draft['grand_total'], $desc);
        $stmt_trans->execute();

        $conn->commit();

        // Auto-post journal: DR Customer AR / CR Sales Revenue
        $ar_account  = get_customer_ar_account($draft['customer_id']);
        $sal_account = get_system_account_id('SALES');
        if ($ar_account && $sal_account) {
            post_journal(
                date('Y-m-d'),
                "Sales Invoice #" . str_pad($draft_id, 6, '0', STR_PAD_LEFT) . " — " . ($draft['customer_name'] ?? 'Customer #' . $draft['customer_id']),
                'Invoice',
                $draft_id,
                [
                    ['account_id' => $ar_account,  'dr' => $draft['grand_total'], 'cr' => 0, 'note' => 'Trade Receivable'],
                    ['account_id' => $sal_account,  'dr' => 0, 'cr' => $draft['grand_total'], 'note' => 'Sales Revenue'],
                ]
            );
        }

        log_activity($_SESSION['user_id'], "Confirmed Sales Draft #$draft_id and updated accounts.");
        redirect('modules/sales/index.php', "Sales Draft #$draft_id confirmed and accounts updated successfully.");
    } catch (Exception $e) {
        $conn->rollback();
        die("Critical Error during confirmation: " . $e->getMessage());
    }
}
?>
