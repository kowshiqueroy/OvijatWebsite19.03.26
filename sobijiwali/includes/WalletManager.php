<?php
/**
 * Wallet Manager Class
 * Handles internal balances and transactions.
 */

class WalletManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get user's wallet balance
     */
    public function getBalance($userId) {
        $sql = "SELECT balance FROM wallets WHERE user_id = ?";
        $row = $this->db->query($sql, [$userId])->fetch();
        return $row ? (float)$row['balance'] : 0.00;
    }

    /**
     * Add funds to wallet
     */
    public function addFunds($userId, $amount, $description, $type = 'deposit') {
        try {
            $this->db->beginTransaction();

            // 1. Update Balance
            $updateSql = "UPDATE wallets SET balance = balance + ? WHERE user_id = ?";
            $this->db->query($updateSql, [$amount, $userId]);

            // 2. Log Transaction
            $walletId = $this->getWalletId($userId);
            $logSql = "INSERT INTO wallet_transactions (wallet_id, amount, type, description) VALUES (?, ?, ?, ?)";
            $this->db->query($logSql, [$walletId, $amount, $type, $description]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Deduct funds from wallet
     */
    public function deductFunds($userId, $amount, $description, $type = 'purchase') {
        $currentBalance = $this->getBalance($userId);
        if ($currentBalance < $amount) {
            return ['success' => false, 'message' => 'Insufficient wallet balance.'];
        }

        try {
            $this->db->beginTransaction();

            // 1. Update Balance
            $updateSql = "UPDATE wallets SET balance = balance - ? WHERE user_id = ?";
            $this->db->query($updateSql, [$amount, $userId]);

            // 2. Log Transaction
            $walletId = $this->getWalletId($userId);
            $logSql = "INSERT INTO wallet_transactions (wallet_id, amount, type, description) VALUES (?, ?, ?, ?)";
            $this->db->query($logSql, [$walletId, -$amount, $type, $description]);

            $this->db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get wallet ID for a user
     */
    private function getWalletId($userId) {
        $sql = "SELECT id FROM wallets WHERE user_id = ?";
        return $this->db->query($sql, [$userId])->fetch()['id'];
    }
}
