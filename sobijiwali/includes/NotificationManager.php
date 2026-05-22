<?php
/**
 * Notification Manager Class
 * Handles system-wide alerts for customers and admins.
 */

require_once __DIR__ . '/Database.php';

class NotificationManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Send a notification to a specific user
     */
    public function send($userId, $type, $targetId, $title, $message) {
        $sql = "INSERT INTO notifications (user_id, type, target_id, title, message) VALUES (?, ?, ?, ?, ?)";
        return $this->db->query($sql, [$userId, $type, $targetId, $title, $message]);
    }

    /**
     * Send a notification to all administrators / staff
     */
    public function notifyStaff($type, $targetId, $title, $message) {
        $staff = $this->db->query("SELECT id FROM users WHERE role IN ('admin', 'editor', 'warehouse', 'support')")->fetchAll();
        foreach ($staff as $s) {
            $this->send($s['id'], $type, $targetId, $title, $message);
        }
    }

    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount($userId) {
        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0";
        return (int)$this->db->query($sql, [$userId])->fetchColumn();
    }

    /**
     * Fetch recent notifications
     */
    public function getRecent($userId, $limit = 10) {
        $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT " . (int)$limit;
        return $this->db->query($sql, [$userId])->fetchAll();
    }

    /**
     * Mark all as read
     */
    public function markAllAsRead($userId) {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
        return $this->db->query($sql, [$userId]);
    }
}
