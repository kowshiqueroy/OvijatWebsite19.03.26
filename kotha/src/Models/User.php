<?php
// src/Models/User.php

namespace Models;

use Database;
use PDO;

class User {
    /**
     * Create a new pending user registration.
     */
    public static function create(
        string $email, 
        string $password, 
        string $pin, 
        string $fullName, 
        string $address, 
        string $dob, 
        string $institute, 
        string $phone
    ): bool {
        $db = Database::getCoreConnection();
        
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $pinHash = password_hash($pin, PASSWORD_BCRYPT);
        
        $stmt = $db->prepare("
            INSERT INTO users (email, password_hash, pin_hash, full_name, address, dob, institute, phone, is_approved, is_admin)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0)
        ");
        
        return $stmt->execute([
            strtolower(trim($email)),
            $passwordHash,
            $pinHash,
            trim($fullName),
            trim($address),
            $dob,
            trim($institute),
            trim($phone)
        ]);
    }

    /**
     * Get a user by email.
     */
    public static function getByEmail(string $email): ?array {
        $db = Database::getCoreConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();
        return $user ? $user : null;
    }

    /**
     * Get a user by ID.
     */
    public static function getById(int $id): ?array {
        $db = Database::getCoreConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ? $user : null;
    }

    /**
     * Verify user credentials.
     */
    public static function verifyCredentials(string $email, string $password): ?array {
        $user = self::getByEmail($email);
        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        return null;
    }

    /**
     * Verify 4-digit PIN.
     */
    public static function verifyPin(int $userId, string $pin): bool {
        $user = self::getById($userId);
        if ($user && password_verify($pin, $user['pin_hash'])) {
            return true;
        }
        return false;
    }

    /**
     * Get all users (except admins and the requesting user) who are approved.
     */
    public static function getAvailableUsersForChat(int $excludeUserId): array {
        $db = Database::getCoreConnection();
        $stmt = $db->prepare("
            SELECT id, email, full_name, institute, phone 
            FROM users 
            WHERE is_approved = 1 AND is_admin = 0 AND id != ?
            ORDER BY full_name ASC
        ");
        $stmt->execute([$excludeUserId]);
        return $stmt->fetchAll();
    }

    /**
     * Get all registered users for admin oversight.
     */
    public static function getAllUsers(): array {
        $db = Database::getCoreConnection();
        $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    /**
     * Update user approval status.
     */
    public static function updateStatus(int $userId, int $status): bool {
        $db = Database::getCoreConnection();
        $stmt = $db->prepare("UPDATE users SET is_approved = ? WHERE id = ?");
        return $stmt->execute([$status, $userId]);
    }

    /**
     * Hard-delete a user from the system.
     */
    public static function delete(int $userId): bool {
        $db = Database::getCoreConnection();
        
        // Find chats this user is in to delete their shards if needed, or simply delete association
        $stmt = $db->prepare("SELECT DISTINCT chat_id FROM chat_participants WHERE user_id = ?");
        $stmt->execute([$userId]);
        $chats = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $db->beginTransaction();
        try {
            // Delete user record (foreign keys cascade will delete chat_participants, nicknames, etc.)
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            // Clean up sharded files if chats are now empty
            foreach ($chats as $chatId) {
                $check = $db->prepare("SELECT COUNT(*) as count FROM chat_participants WHERE chat_id = ?");
                $check->execute([$chatId]);
                $count = $check->fetch()['count'];
                
                if ($count == 0) {
                    // Delete chat index
                    $delIndex = $db->prepare("DELETE FROM chats_index WHERE chat_id = ?");
                    $delIndex->execute([$chatId]);
                    
                    // Delete sharded db file
                    $chatIdClean = preg_replace('/[^a-zA-Z0-9_]/', '', $chatId);
                    $dbFile = CHAT_DB_DIR . '/chat_' . $chatIdClean . '.sqlite';
                    if (file_exists($dbFile)) {
                        @unlink($dbFile);
                    }
                }
            }
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }
}
