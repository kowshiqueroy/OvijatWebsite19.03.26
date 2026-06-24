<?php
/**
 * Database Migration: Separate Order Fulfillment and Payment Statuses
 */
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->beginTransaction();

    // Check if columns already exist
    $columns = $pdo->query("DESCRIBE orders")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('payment_status', $columns)) {
        echo "Adding payment_status column...\n";
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_status ENUM('Unpaid', 'Paid', 'Refunded') DEFAULT 'Unpaid' AFTER status");
    } else {
        echo "payment_status column already exists.\n";
    }

    if (!in_array('fulfillment_status', $columns)) {
        echo "Adding fulfillment_status column...\n";
        $pdo->exec("ALTER TABLE orders ADD COLUMN fulfillment_status ENUM('Pending', 'Processing', 'Hold', 'Stock Out', 'Ready to Ship', 'Shipped', 'Out for Delivery', 'Delivered', 'Cancelled', 'Rejected', 'Pending Customer Approval') DEFAULT 'Pending' AFTER payment_status");
    } else {
        echo "fulfillment_status column already exists.\n";
    }

    echo "Backfilling existing orders based on legacy status...\n";
    
    // Fetch all existing orders
    $orders = $pdo->query("SELECT id, status FROM orders")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($orders as $ord) {
        $legacy = $ord['status'];
        $oid = $ord['id'];
        
        $p_status = 'Unpaid';
        $f_status = 'Pending';
        
        switch ($legacy) {
            case 'Pending Payment':
                $p_status = 'Unpaid';
                $f_status = 'Pending';
                break;
            case 'Pending Customer Approval':
                $p_status = 'Unpaid';
                $f_status = 'Pending Customer Approval';
                break;
            case 'Payment Verified':
                $p_status = 'Paid';
                $f_status = 'Pending';
                break;
            case 'Confirmed':
            case 'Processing':
                $p_status = 'Paid';
                $f_status = 'Processing';
                break;
            case 'Hold':
                $p_status = 'Paid'; // assume paid or unpaid based on context
                $f_status = 'Hold';
                break;
            case 'Stock Out':
                $p_status = 'Paid';
                $f_status = 'Stock Out';
                break;
            case 'Ready to Ship':
                $p_status = 'Paid';
                $f_status = 'Ready to Ship';
                break;
            case 'Shipped':
                $p_status = 'Paid';
                $f_status = 'Shipped';
                break;
            case 'Out for Delivery':
                $p_status = 'Paid';
                $f_status = 'Out for Delivery';
                break;
            case 'Delivered':
                $p_status = 'Paid';
                $f_status = 'Delivered';
                break;
            case 'Cancelled':
                // Check if refunded in wallet transactions to distinguish
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE user_id = (SELECT user_id FROM orders WHERE id = ?) AND type = 'credit' AND description LIKE ?");
                $stmt->execute([$oid, "Refund for Cancelled Order #$oid%"]);
                $refunded = (int)$stmt->fetchColumn() > 0;
                $p_status = $refunded ? 'Refunded' : 'Unpaid';
                $f_status = 'Cancelled';
                break;
            case 'Rejected':
                $stmt = $pdo->prepare("SELECT refund_approved FROM orders WHERE id = ?");
                $stmt->execute([$oid]);
                $refund_approved = (int)$stmt->fetchColumn();
                $p_status = $refund_approved ? 'Refunded' : 'Unpaid';
                $f_status = 'Rejected';
                break;
            default:
                $p_status = 'Unpaid';
                $f_status = 'Pending';
        }
        
        $up = $pdo->prepare("UPDATE orders SET payment_status = ?, fulfillment_status = ? WHERE id = ?");
        $up->execute([$p_status, $f_status, $oid]);
    }

    $pdo->commit();
    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
}
