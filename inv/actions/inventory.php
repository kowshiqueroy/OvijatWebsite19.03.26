<?php
/**
 * actions/inventory.php
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'stock_in') {
    requireRole(['Admin', 'Manager']);
    
    $supplier_id = (int)$_POST['supplier_id'];
    $reason = sanitize($_POST['reason']);
    $items = $_POST['items']; 
    $branch_id = $_SESSION['branch_id'];
    $user_id = $_SESSION['user_id'];

    // Get Supplier Name
    $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE id = ?");
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch();
    $person_name = $supplier['name'] ?? 'Unknown Supplier';

    $pdo->beginTransaction();
    try {
        foreach ($items as $item) {
            $product_id = (int)$item['product_id'];
            $unit_type = $item['unit_type'];
            $quantity = (int)$item['quantity'];
            $purchase_price_pack = (float)$item['purchase_price'];

            // Get product info
            $stmt = $pdo->prepare("SELECT conversion_ratio FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            $ratio = $product['conversion_ratio'];

            $quantity_pcs = ($unit_type === 'pack') ? ($quantity * $ratio) : $quantity;
            $unit_purchase_price = ($unit_type === 'pack') ? ($purchase_price_pack / $ratio) : $purchase_price_pack;

            // 1. Fetch current inventory for Average Price Calculation
            $stmt = $pdo->prepare("SELECT quantity_pcs, avg_purchase_price FROM inventory WHERE product_id = ? AND branch_id = ?");
            $stmt->execute([$product_id, $branch_id]);
            $inv = $stmt->fetch();

            $current_qty = $inv['quantity_pcs'] ?? 0;
            $old_avg_price = $inv['avg_purchase_price'] ?? 0;

            // Weighted Average Calculation
            $new_total_qty = $current_qty + $quantity_pcs;
            if ($new_total_qty > 0) {
                $new_avg_price = (($current_qty * $old_avg_price) + ($quantity_pcs * $unit_purchase_price)) / $new_total_qty;
            } else {
                $new_avg_price = $unit_purchase_price;
            }

            // 2. Update Inventory
            $stmt = $pdo->prepare("INSERT INTO inventory (product_id, branch_id, quantity_pcs, avg_purchase_price) 
                                   VALUES (?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE quantity_pcs = quantity_pcs + ?, avg_purchase_price = ?");
            $stmt->execute([$product_id, $branch_id, $quantity_pcs, $new_avg_price, $quantity_pcs, $new_avg_price]);

            // 3. Record in Stock Ledger
            $stmt = $pdo->prepare("INSERT INTO stock_ledger (product_id, branch_id, type, quantity_pcs, purchase_price, person_name, reason, user_id) 
                                   VALUES (?, ?, 'stock_in', ?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $branch_id, $quantity_pcs, $unit_purchase_price, $person_name, $reason, $user_id]);
        }

        $pdo->commit();
        auditLog($pdo, 'Stock IN', "Added stock for " . count($items) . " products from $person_name");
        jsonResponse('success', 'Stock updated successfully.');
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse('error', 'Failed to update stock: ' . $e->getMessage());
    }
}

if ($action === 'manual_out') {
    requireRole(['Admin', 'Manager']);
    
    $product_id = (int)$_POST['product_id'];
    $unit_type = $_POST['unit_type'];
    $quantity = (int)$_POST['quantity'];
    $person_name = sanitize($_POST['person_name']);
    $reason = sanitize($_POST['reason']);
    $branch_id = $_SESSION['branch_id'];
    $user_id = $_SESSION['user_id'];

    $pdo->beginTransaction();
    try {
        // Get conversion ratio
$stmt = $pdo->prepare("SELECT conversion_ratio FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();
$ratio = $product['conversion_ratio'];

        $quantity_pcs = ($unit_type === 'pack') ? ($quantity * $ratio) : $quantity;

        // Check stock
        $current_stock = getStock($pdo, $product_id, $branch_id);
        if ($current_stock < $quantity_pcs) {
            throw new Exception("Insufficient stock. Available: $current_stock pcs.");
        }

        // 1. Update Inventory
        $stmt = $pdo->prepare("UPDATE inventory SET quantity_pcs = quantity_pcs - ? WHERE product_id = ? AND branch_id = ?");
        $stmt->execute([$quantity_pcs, $product_id, $branch_id]);

        // 2. Record in Stock Ledger
        $stmt = $pdo->prepare("INSERT INTO stock_ledger (product_id, branch_id, type, quantity_pcs, person_name, reason, user_id) 
                               VALUES (?, ?, 'manual_out', ?, ?, ?, ?)");
        $stmt->execute([$product_id, $branch_id, $quantity_pcs, $person_name, $reason, $user_id]);

        $pdo->commit();
        auditLog($pdo, 'Stock OUT', "Manual out: $quantity_pcs pcs for product ID: $product_id");
        jsonResponse('success', 'Stock deducted successfully.');
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse('error', $e->getMessage());
    }
}

if ($action === 'transfer') {
    requireRole(['Admin', 'Manager']);
    
    $product_id = (int)$_POST['product_id'];
    $to_branch_id = (int)$_POST['to_branch_id'];
    $quantity_pcs = (int)$_POST['quantity_pcs'];
    $from_branch_id = $_SESSION['branch_id'];
    $user_id = $_SESSION['user_id'];

    if ($from_branch_id == $to_branch_id) {
        jsonResponse('error', 'Cannot transfer to the same branch.');
    }

    $pdo->beginTransaction();
    try {
        // 1. Check stock at source
        $current_stock = getStock($pdo, $product_id, $from_branch_id);
        if ($current_stock < $quantity_pcs) {
            throw new Exception("Insufficient stock at source branch. Available: $current_stock pcs.");
        }

        // 2. Fetch Average Price from source to maintain it at target
        $stmt = $pdo->prepare("SELECT avg_purchase_price FROM inventory WHERE product_id = ? AND branch_id = ?");
        $stmt->execute([$product_id, $from_branch_id]);
        $avg_price = $stmt->fetchColumn() ?: 0;

        // 3. Deduct from Source
        $stmt = $pdo->prepare("UPDATE inventory SET quantity_pcs = quantity_pcs - ? WHERE product_id = ? AND branch_id = ?");
        $stmt->execute([$quantity_pcs, $product_id, $from_branch_id]);

        // 4. Add to Target (with weighted average update)
        $stmt = $pdo->prepare("SELECT quantity_pcs, avg_purchase_price FROM inventory WHERE product_id = ? AND branch_id = ?");
        $stmt->execute([$product_id, $to_branch_id]);
        $target_inv = $stmt->fetch();

        $target_qty = $target_inv['quantity_pcs'] ?? 0;
        $target_avg = $target_inv['avg_purchase_price'] ?? 0;

        $new_target_qty = $target_qty + $quantity_pcs;
        $new_target_avg = (($target_qty * $target_avg) + ($quantity_pcs * $avg_price)) / $new_target_qty;

        $stmt = $pdo->prepare("INSERT INTO inventory (product_id, branch_id, quantity_pcs, avg_purchase_price) 
                               VALUES (?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE quantity_pcs = quantity_pcs + ?, avg_purchase_price = ?");
        $stmt->execute([$product_id, $to_branch_id, $quantity_pcs, $new_target_avg, $quantity_pcs, $new_target_avg]);

        // 5. Record in Transfers table
        $stmt = $pdo->prepare("INSERT INTO stock_transfers (product_id, from_branch_id, to_branch_id, quantity_pcs, status, user_id) 
                               VALUES (?, ?, ?, ?, 'completed', ?)");
        $stmt->execute([$product_id, $from_branch_id, $to_branch_id, $quantity_pcs, $user_id]);

        // 6. Record in Ledger for both branches
        $stmt = $pdo->prepare("INSERT INTO stock_ledger (product_id, branch_id, type, quantity_pcs, reason, user_id) VALUES (?, ?, 'adjustment', ?, ?, ?)");
        $stmt->execute([$product_id, $from_branch_id, -$quantity_pcs, "Transfer to branch ID: $to_branch_id", $user_id]);
        $stmt->execute([$product_id, $to_branch_id, $quantity_pcs, "Transfer from branch ID: $from_branch_id", $user_id]);

        $pdo->commit();
        auditLog($pdo, 'Stock Transfer', "Transferred $quantity_pcs pcs of product ID: $product_id to branch ID: $to_branch_id");
        jsonResponse('success', 'Stock transferred successfully.');
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse('error', $e->getMessage());
    }
}
?>
