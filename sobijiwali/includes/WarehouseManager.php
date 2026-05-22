<?php
/**
 * Warehouse Manager Class
 * Handles FIFO inventory deduction and order lifecycle management.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/WalletManager.php';
require_once __DIR__ . '/StripeClient.php';
require_once __DIR__ . '/NotificationManager.php';

class WarehouseManager {
    private $db;
    private $stripe;
    private $wallet;
    private $notif;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->stripe = new StripeClient(STRIPE_SECRET_KEY);
        $this->wallet = new WalletManager();
        $this->notif = new NotificationManager();
    }

    /**
     * Start processing an order (Deduct Inventory FIFO)
     */
    public function processOrder($orderId) {
        try {
            $this->db->beginTransaction();

            // Fetch order items
            $sql = "SELECT * FROM order_items WHERE order_id = ?";
            $items = $this->db->query($sql, [$orderId])->fetchAll();

            // Perform FIFO inventory deduction for each item
            foreach ($items as $item) {
                $this->deductInventoryFIFO($item['product_variation_id'], $item['quantity']);
            }

            // Update order status
            $updateSql = "UPDATE orders SET status = 'processing' WHERE id = ?";
            $this->db->query($updateSql, [$orderId]);

            // Notify Customer
            $userId = $this->db->query("SELECT user_id FROM orders WHERE id = ?", [$orderId])->fetchColumn();
            if ($userId) {
                $this->notif->send($userId, 'order_status', $orderId, "🚜 Order #$orderId Processing", "We have started picking your fresh harvest!");
            }

            $this->db->commit();
            return ['success' => true];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Deduct inventory based on FIFO (First-In-First-Out)
     */
    private function deductInventoryFIFO($variationId, $quantityToDeduct) {
        // Fetch active batches for this variation ordered by received_date (oldest first)
        $sql = "SELECT id, quantity_remaining FROM inventory_batches 
                WHERE product_variation_id = ? AND quantity_remaining > 0 
                ORDER BY received_date ASC";
        $batches = $this->db->query($sql, [$variationId])->fetchAll();

        $remaining = $quantityToDeduct;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            if ($batch['quantity_remaining'] <= $remaining) {
                // Batch is fully consumed
                $deduct = $batch['quantity_remaining'];
                $remaining -= $deduct;
                $updateBatch = "UPDATE inventory_batches SET quantity_remaining = 0 WHERE id = ?";
                $this->db->query($updateBatch, [$batch['id']]);
            } else {
                // Batch is partially consumed
                $updateBatch = "UPDATE inventory_batches SET quantity_remaining = quantity_remaining - ? WHERE id = ?";
                $this->db->query($updateBatch, [$remaining, $batch['id']]);
                $remaining = 0;
            }
        }

        if ($remaining > 0) {
            throw new Exception("Insufficient inventory for variation ID: $variationId. Shortfall: $remaining");
        }
    }

    /**
     * Ship order and Capture Stripe payment
     */
    public function shipOrder($orderId) {
        $sql = "SELECT user_id, stripe_payment_intent_id, total_amount, status FROM orders WHERE id = ?";
        $order = $this->db->query($sql, [$orderId])->fetch();

        if (!$order) return ['success' => false, 'message' => 'Order not found.'];
        if ($order['status'] !== 'processing') return ['success' => false, 'message' => 'Order must be in processing state to ship.'];

        // Capture payment if Stripe
        if ($order['stripe_payment_intent_id']) {
            $capture = $this->stripe->capturePaymentIntent($order['stripe_payment_intent_id']);
            if (!$capture['success']) {
                return ['success' => false, 'message' => 'Payment capture failed: ' . $capture['error']['message']];
            }
        }

        // Update status
        $updateSql = "UPDATE orders SET status = 'shipped' WHERE id = ?";
        $this->db->query($updateSql, [$orderId]);

        // Notify Customer
        if ($order['user_id']) {
            $this->notif->send($order['user_id'], 'order_status', $orderId, "🚚 Order #$orderId Shipped!", "Your harvest is on its way to you!");
        }

        // Phase 7: VIP Loyalty Reward (1% Cashback)
        $reward = $order['total_amount'] * 0.01;
        if ($reward > 0) {
            $this->wallet->addFunds($order['user_id'], $reward, "Cashback for Order #$orderId", 'reward');
        }

        return ['success' => true];
    }

    /**
     * Cancel an authorized order with inventory disposition
     */
    public function cancelOrder($orderId, $disposition = 'restock') {
        try {
            $this->db->beginTransaction();

            // Fetch order items
            $sql = "SELECT product_variation_id, quantity FROM order_items WHERE order_id = ?";
            $items = $this->db->query($sql, [$orderId])->fetchAll();

            // Handle Inventory
            if ($disposition === 'restock') {
                foreach ($items as $item) {
                    // Add back as a new "Restocked" batch
                    $this->db->query("INSERT INTO inventory_batches (product_variation_id, quantity_initial, quantity_remaining, cost_price, received_date) 
                                     VALUES (?, ?, ?, 0.00, NOW())", 
                                     [$item['product_variation_id'], $item['quantity'], $item['quantity']]);
                }
            }

            // Update order status
            $this->db->query("UPDATE orders SET status = 'cancelled' WHERE id = ?", [$orderId]);

            // Notify Customer
            $userId = $this->db->query("SELECT user_id FROM orders WHERE id = ?", [$orderId])->fetchColumn();
            if ($userId) {
                $this->notif->send($userId, 'order_status', $orderId, "❌ Order #$orderId Cancelled", "Your order has been cancelled. Details: $disposition");
            }

            $this->db->commit();
            return ['success' => true];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
