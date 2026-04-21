<?php
/**
 * actions/sales.php
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'get_customer_type') {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT type, balance FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse('success', 'Data fetched', $stmt->fetch());
}

if ($action === 'get_product_data') {
    $product_id = (int)$_GET['product_id'];
    $customer_type = $_GET['customer_type'];

    // Fetch product info
    $stmt = $pdo->prepare("SELECT name, unit_name, conversion_ratio, min_sale_price FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    // Fetch prices for this customer type
    $stmtPrice = $pdo->prepare("SELECT pack_price, piece_price FROM product_prices WHERE product_id = ? AND customer_type = ?");
    $stmtPrice->execute([$product_id, $customer_type]);
    $prices = $stmtPrice->fetch();

    jsonResponse('success', 'Data fetched', [
        'product' => $product,
        'prices' => $prices
    ]);
}

if ($action === 'create_sale') {
    requireRole(['Admin', 'Manager']);
    
    $customer_id = (int)$_POST['customer_id'];
    $items = $_POST['items'] ?? [];
    $total_amount = (float)$_POST['total_amount'];
    $discount_amount = (float)$_POST['discount_amount'];
    $status = $_POST['sale_status'] ?? 'pending_approval';
    $branch_id = $_SESSION['branch_id'];
    $user_id = $_SESSION['user_id'];

    if (!$customer_id) jsonResponse('error', 'Please select a customer.');
    if (empty($items)) jsonResponse('error', 'Please add at least one product.');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO sales (customer_id, user_id, branch_id, total_amount, discount_amount, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$customer_id, $user_id, $branch_id, $total_amount, $discount_amount, $status]);
        $sale_id = $pdo->lastInsertId();

        $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, unit_type, quantity, unit_price, purchase_price, is_free, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $free_total = 0;
        foreach ($items as $item) {
            if (empty($item['product_id'])) continue;
            $product_id = (int)$item['product_id'];
            $unit_type = $item['unit_type'] ?? 'pack';
            $quantity = (int)$item['quantity'];
            $price = (float)$item['price'];
            $is_free = !empty($item['is_free']) ? 1 : 0;
            $subtotal = $is_free ? 0 : ($quantity * $price);
            if ($is_free) $free_total += ($quantity * $price);

            // Fetch average purchase price from inventory
            $stmtInv = $pdo->prepare("SELECT avg_purchase_price, conversion_ratio FROM products p JOIN inventory i ON p.id = i.product_id WHERE p.id = ? AND i.branch_id = ?");
            $stmtInv->execute([$product_id, $branch_id]);
            $invData = $stmtInv->fetch();
            $purchase_price_pcs = $invData['avg_purchase_price'] ?? 0;
            $ratio = $invData['conversion_ratio'] ?? 1;

            $purchase_price = ($unit_type === 'pack') ? ($purchase_price_pcs * $ratio) : $purchase_price_pcs;

            $stmtItem->execute([$sale_id, $product_id, $unit_type, $quantity, $price, $purchase_price, $is_free, $subtotal]);
        }
        
        // Update discount to include free items value
        $discount_amount = $discount_amount + $free_total;
        $stmt = $pdo->prepare("UPDATE sales SET discount_amount = ? WHERE id = ?");
        $stmt->execute([$discount_amount, $sale_id]);

        if ($status === 'pending_approval') {
            $stmtC = $pdo->prepare("SELECT balance FROM customers WHERE id = ?");
            $stmtC->execute([$customer_id]);
            $balance = $stmtC->fetch()['balance'];

            // If balance is sufficient (Debt-based: positive means they owe us. 
            // Wait, let's say they have a credit limit? 
            // Prompt says: "If customer balance is sufficient -> auto approved"
            // Usually means if they have enough prepaid balance OR if they are within limit.
            // For now, let's assume if balance >= 0 (no debt) it auto-approves.
            // Or just check if total_amount <= balance (prepaid).
            
            // Let's implement: If (customer balance - total_amount) >= -5000 (limit) auto-approve.
            // Or as requested: "If customer balance is sufficient"
            // I'll assume sufficient means balance >= total_amount.
            
            if ($balance >= $total_amount) {
                // Auto Approve call
                approveSale($pdo, $sale_id, $user_id);
                $status = 'approved';
            }
        }

        $pdo->commit();
        auditLog($pdo, 'Create Sale', "Created sale ID: $sale_id, Status: $status");
        jsonResponse('success', 'Sale created successfully. Status: ' . $status, ['sale_id' => $sale_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse('error', 'Failed to create sale: ' . $e->getMessage());
    }
}

/**
 * Helper to approve a sale
 */
function approveSale($pdo, $sale_id, $approver_id) {
    // 1. Fetch sale data
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();

    if ($sale['status'] === 'approved') return;

    // 2. Update Sale Status
    $stmtU = $pdo->prepare("UPDATE sales SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
    $stmtU->execute([$approver_id, $sale_id]);

    // 3. Update Customer Balance
    $stmtC = $pdo->prepare("UPDATE customers SET balance = balance - ? WHERE id = ?");
    $stmtC->execute([$sale['total_amount'], $sale['customer_id']]);

    // 4. Record in Customer Ledger
    $stmtL = $pdo->prepare("INSERT INTO customer_ledger (customer_id, type, amount, reference_id, description, branch_id) VALUES (?, 'debit', ?, ?, 'Sale Invoice', ?)");
    $stmtL->execute([$sale['customer_id'], $sale['total_amount'], $sale_id, $sale['branch_id']]);

    // 5. Deduct Stock for each item
    $stmtItems = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
    $stmtItems->execute([$sale_id]);
    $items = $stmtItems->fetchAll();

    foreach ($items as $item) {
        // Get conversion ratio
        $stmtP = $pdo->prepare("SELECT conversion_ratio FROM products WHERE id = ?");
        $stmtP->execute([$item['product_id']]);
        $ratio = $stmtP->fetch()['conversion_ratio'];

        $quantity_pcs = ($item['unit_type'] === 'pack') ? ($item['quantity'] * $ratio) : $item['quantity'];

        // Update Inventory
        $stmtInv = $pdo->prepare("UPDATE inventory SET quantity_pcs = quantity_pcs - ? WHERE product_id = ? AND branch_id = ?");
        $stmtInv->execute([$quantity_pcs, $item['product_id'], $sale['branch_id']]);

        // Record in Stock Ledger
        $stmtSL = $pdo->prepare("INSERT INTO stock_ledger (product_id, branch_id, type, quantity_pcs, reference_id, user_id) 
                                 VALUES (?, ?, 'sale_out', ?, ?, ?)");
        $stmtSL->execute([$item['product_id'], $sale['branch_id'], $quantity_pcs, $sale_id, $approver_id]);
    }
}

if ($action === 'approve_sale') {
    requireRole(['Admin', 'Accountant']);
    $sale_id = (int)$_POST['id'];
    $user_id = $_SESSION['user_id'];

    $pdo->beginTransaction();
    try {
        approveSale($pdo, $sale_id, $user_id);
        $pdo->commit();
        auditLog($pdo, 'Approve Sale', "Approved sale ID: $sale_id");
        jsonResponse('success', 'Sale approved and stock/ledger updated.');
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse('error', $e->getMessage());
    }
}

if ($action === 'reject_sale') {
    requireRole(['Admin', 'Accountant']);
    $sale_id = (int)$_POST['id'];
    
    $stmt = $pdo->prepare("UPDATE sales SET status = 'rejected' WHERE id = ?");
    if ($stmt->execute([$sale_id])) {
        auditLog($pdo, 'Reject Sale', "Rejected sale ID: $sale_id");
        jsonResponse('success', 'Sale rejected.');
    } else {
        jsonResponse('error', 'Failed to reject sale.');
    }
}
?>
