<?php
/**
 * System Audit Logger
 * Tracks admin actions and system events.
 */

class Logger {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Log an admin action
     */
    public function log($action, $targetType = null, $targetId = null, $details = null) {
        $adminId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $sql = "INSERT INTO system_logs (admin_id, action, target_type, target_id, details, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        try {
            $this->db->query($sql, [$adminId, $action, $targetType, $targetId, $details, $ip]);
        } catch (Exception $e) {
            // Silently fail to not block main execution
        }
    }
}
