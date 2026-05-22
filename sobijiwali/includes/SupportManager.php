<?php
/**
 * Support & B2B Communication Manager
 * Handles multi-role messaging and popup chat logic.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/NotificationManager.php';

class SupportManager {
    private $db;
    private $notif;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->notif = new NotificationManager();
    }

    /**
     * Start a new chat thread (usually by customer)
     */
    public function startThread($customerId, $subject) {
        $sql = "INSERT INTO chat_threads (customer_id, subject) VALUES (?, ?)";
        $this->db->query($sql, [$customerId, $subject]);
        $threadId = $this->db->lastInsertId();

        // Automated Greeting
        $greeting = $this->db->query("SELECT setting_value FROM site_settings WHERE setting_key = 'auto_greeting'")->fetchColumn();
        if ($greeting) {
            $this->sendMessage($threadId, 0, 'admin', $greeting); // 0 = System/Auto
        }

        return $threadId;
    }

    /**
     * Get all canned responses
     */
    public function getCannedResponses() {
        return $this->db->query("SELECT * FROM canned_responses ORDER BY title ASC")->fetchAll();
    }

    /**
     * Send a message in a thread
     */
    public function sendMessage($threadId, $senderId, $senderType, $message) {
        $sql = "INSERT INTO chat_messages (thread_id, sender_id, sender_type, message) VALUES (?, ?, ?, ?)";
        $this->db->query($sql, [$threadId, $senderId, $senderType, $message]);
        
        // Update thread metadata
        $updateSql = "UPDATE chat_threads SET last_message_at = NOW()";
        if ($senderType === 'admin') {
            $updateSql .= ", last_admin_id = $senderId";
        }
        $updateSql .= " WHERE id = ?";
        $this->db->query($updateSql, [$threadId]);

        // Notify appropriate party
        $thread = $this->db->query("SELECT customer_id FROM chat_threads WHERE id = ?", [$threadId])->fetch();
        if ($senderType === 'customer') {
            $this->notif->notifyStaff('new_message', $threadId, "💬 New Support Message", "A customer sent a message in thread #$threadId");
        } else {
            $this->notif->send($thread['customer_id'], 'new_message', $threadId, "💬 Support Replied", "You have a new message from the Sobjiwali team.");
        }

        return true;
    }

    /**
     * Update Thread Metadata (Internal)
     */
    public function updateThread($threadId, $data) {
        $sql = "UPDATE chat_threads SET priority = ?, status = ?, internal_notes = ? WHERE id = ?";
        return $this->db->query($sql, [$data['priority'], $data['status'], $data['internal_notes'], $threadId]);
    }

    /**
     * Get all messages in a thread
     */
    public function getMessages($threadId) {
        $sql = "SELECT * FROM chat_messages WHERE thread_id = ? ORDER BY created_at ASC";
        return $this->db->query($sql, [$threadId])->fetchAll();
    }

    /**
     * Get unread message count for a user (as customer)
     */
    public function getUnreadCount($userId) {
        $sql = "SELECT COUNT(*) FROM chat_messages m 
                JOIN chat_threads t ON m.thread_id = t.id 
                WHERE t.customer_id = ? AND m.sender_type = 'admin' AND m.is_read = 0";
        return (int)$this->db->query($sql, [$userId])->fetchColumn();
    }

    /**
     * Mark thread as read
     */
    public function markAsRead($threadId, $type = 'customer') {
        $userId = $_SESSION['user_id'];
        
        // 1. Mark actual messages as read
        $sql = "UPDATE chat_messages SET is_read = 1 WHERE thread_id = ? AND sender_type != ?";
        $this->db->query($sql, [$threadId, $type]);

        // 2. Mark corresponding notifications as read for THIS user
        $notifSql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = 'new_message' AND target_id = ?";
        $this->db->query($notifSql, [$userId, $threadId]);
        
        return true;
    }

    /**
     * Get all threads for admin
     */
    public function getAdminThreads() {
        $sql = "SELECT t.*, u.email as customer_email, 
                (SELECT COUNT(*) FROM chat_messages WHERE thread_id = t.id AND sender_type = 'customer' AND is_read = 0) as unread_count
                FROM chat_threads t
                JOIN users u ON t.customer_id = u.id
                ORDER BY t.last_message_at DESC";
        return $this->db->query($sql)->fetchAll();
    }
}
