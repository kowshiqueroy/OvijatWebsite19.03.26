<?php
/**
 * Checkout Manager Class
 * Handles order creation and payment orchestration.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/StripeClient.php';

class CheckoutManager {
    private $db;
    private $stripe;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->stripe = new StripeClient(); // Automatically pulls from settings
    }

    /**
     * Create an Order from a Synced Cart
     */
    public function createOrder($userId, $syncedCart, $totals, $paymentMethod, $paymentDetails = null, $guestData = null) {
        try {
            // 1. Create Order Record
            $hash = bin2hex(random_bytes(8));
            $orderSql = "INSERT INTO orders (user_id, guest_email, guest_name, guest_phone, shipping_address, shipping_state, billing_name, billing_email, billing_phone, billing_address, billing_state, order_note, total_amount, shipping_fee, tax_amount, status, payment_method, payment_details, order_hash) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)";
            
            $this->db->query($orderSql, [
                $userId,
                $guestData['email'] ?? null,
                $guestData['name'] ?? null,
                $guestData['phone'] ?? null,
                $guestData['address'] ?? null,
                $guestData['state'] ?? null,
                $guestData['billing_name'] ?? null,
                $guestData['billing_email'] ?? null,
                $guestData['billing_phone'] ?? null,
                $guestData['billing_address'] ?? null,
                $guestData['billing_state'] ?? null,
                $guestData['note'] ?? null,
                $totals['total'],
                $totals['shipping'],
                $totals['tax'],
                $paymentMethod,
                $paymentDetails,
                $hash
            ]);

            $orderId = $this->db->lastInsertId();

            // 2. Create Order Items
            $itemSql = "INSERT INTO order_items (order_id, product_variation_id, quantity, unit_price, total_price) 
                        VALUES (?, ?, ?, ?, ?)";
            
            foreach ($syncedCart['items'] as $item) {
                $this->db->query($itemSql, [
                    $orderId,
                    $item['variation_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price']
                ]);
            }

            return $orderId;

        } catch (Exception $e) {
            die("Order Creation Failed: " . $e->getMessage());
        }
    }

    /**
     * Handle Manual Payment submission
     */
    public function handleManualPayment($orderId, $transactionDetails) {
        $sql = "UPDATE orders SET payment_details = ? WHERE id = ?";
        return $this->db->query($sql, [$transactionDetails, $orderId]);
    }

    /**
     * Authorize Stripe Payment
     */
    public function authorizePayment($orderId, $paymentMethodId) {
        // Fetch order total
        $sql = "SELECT total_amount FROM orders WHERE id = ?";
        $order = $this->db->query($sql, [$orderId])->fetch();

        if (!$order) return ['success' => false, 'message' => 'Order not found.'];

        $result = $this->stripe->createAuthPaymentIntent(
            $order['total_amount'],
            STRIPE_CURRENCY,
            $paymentMethodId,
            ['order_id' => $orderId]
        );

        if ($result['success']) {
            // Update order with authorization details
            $updateSql = "UPDATE orders SET status = 'authorized', stripe_payment_intent_id = ? WHERE id = ?";
            $this->db->query($updateSql, [$result['data']['id'], $orderId]);
            
            return ['success' => true, 'payment_intent' => $result['data']['id']];
        }

        return ['success' => false, 'message' => $result['error']['message'] ?? 'Payment authorization failed.'];
    }
}
