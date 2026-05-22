<?php
/**
 * Subscription Manager Class
 * Handles recurring 'Subscribe & Save' orders.
 */

class SubscriptionManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new subscription
     */
    public function createSubscription($userId, $variationId, $quantity, $frequencyDays) {
        $sql = "INSERT INTO subscriptions (user_id, product_variation_id, quantity, frequency_days, next_run_date) 
                VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))";
        
        $this->db->query($sql, [$userId, $variationId, $quantity, $frequencyDays, $frequencyDays]);
        return $this->db->lastInsertId();
    }

    /**
     * Process due subscriptions
     * (Normally triggered by a cron job)
     */
    public function processDueSubscriptions() {
        $sql = "SELECT * FROM subscriptions WHERE next_run_date <= NOW() AND status = 'active'";
        $due = $this->db->query($sql)->fetchAll();

        $processedCount = 0;
        foreach ($due as $sub) {
            // In a real application, we would call CheckoutManager here to create a new order.
            // For Phase 7 MVP, we simulate the order creation and update the next run date.
            
            $updateSql = "UPDATE subscriptions 
                          SET next_run_date = DATE_ADD(NOW(), INTERVAL frequency_days DAY) 
                          WHERE id = ?";
            $this->db->query($updateSql, [$sub['id']]);
            $processedCount++;
        }

        return $processedCount;
    }

    /**
     * Get user subscriptions
     */
    public function getUserSubscriptions($userId) {
        $sql = "SELECT s.*, v.sku, p.name as product_name 
                FROM subscriptions s 
                JOIN product_variations v ON s.product_variation_id = v.id 
                JOIN products p ON v.product_id = p.id 
                WHERE s.user_id = ?";
        return $this->db->query($sql, [$userId])->fetchAll();
    }
}
