<?php
/**
 * Authentication Manager Class
 * Handles login, registration, and session-based access control.
 */

class AuthManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Register a Retail User
     */
    public function registerRetail($email, $password, $profileData = []) {
        return $this->registerUser($email, $password, 'retail', $profileData);
    }

    /**
     * Register a Wholesale User (Pending)
     */
    public function registerWholesale($email, $password, $profileData = []) {
        return $this->registerUser($email, $password, 'pending_wholesale', $profileData);
    }

    /**
     * Core Registration Logic
     */
    private function registerUser($email, $password, $role, $profileData) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $this->db->beginTransaction();

            $userSql = "INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)";
            $this->db->query($userSql, [$email, $hashedPassword, $role]);
            $userId = $this->db->lastInsertId();

            // Create Profile
            $profileSql = "INSERT INTO user_profiles (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)";
            $this->db->query($profileSql, [
                $userId, 
                $first_name = $profileData['first_name'] ?? null, 
                $last_name = $profileData['last_name'] ?? null, 
                $phone = $profileData['phone'] ?? null
            ]);

            // Create Wallet
            $walletSql = "INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)";
            $this->db->query($walletSql, [$userId]);

            $this->db->commit();
            return $userId;
        } catch (Exception $e) {
            $this->db->rollBack();
            die("Registration Error: " . $e->getMessage());
        }
    }

    /**
     * User Login
     */
    public function login($email, $password) {
        $sql = "SELECT * FROM users WHERE email = ?";
        $user = $this->db->query($sql, [$email])->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if account is active (for wholesalers)
            if ($user['role'] === 'pending_wholesale') {
                return ['success' => false, 'message' => 'Wholesale account pending approval.'];
            }

            // Regenerate session to prevent hijacking
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];

            // Update last_login_at
            $this->db->query("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);

            return ['success' => true, 'user' => $user];
        }

        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    /**
     * Logout
     */
    public function logout() {
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    /**
     * Check if user has required role(s)
     * Supports single role string or array of roles
     */
    public static function hasRole($roles) {
        if (!isset($_SESSION['user_role'])) return false;
        
        if (is_array($roles)) {
            return in_array($_SESSION['user_role'], $roles);
        }
        
        return $_SESSION['user_role'] === $roles;
    }

    /**
     * Require a specific role(s) or redirect
     */
    public static function requireRole($roles, $redirect = 'index.php') {
        if (!self::hasRole($roles)) {
            header("Location: " . SITE_URL . "/" . $redirect);
            exit;
        }
    }

    /**
     * CSRF Protection: Generate Token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * CSRF Protection: Verify Token
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
