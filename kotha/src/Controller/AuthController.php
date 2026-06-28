<?php
// src/Controller/AuthController.php

namespace Controller;

use Models\User;

class AuthController {
    /**
     * Helper to render views with optional parameters.
     */
    protected function render(string $view, array $data = []): void {
        extract($data);
        $viewPath = BASE_DIR . "/src/Views/{$view}.php";
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            echo "Error: View {$view} not found.";
        }
    }

    /**
     * Redirect helper.
     */
    protected function redirect(string $path): void {
        // Strip leading slash and prepend base URL if running in a subdirectory
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        $scriptDirClean = rtrim($scriptDir, '/\\');
        
        $location = $scriptDirClean . '/' . ltrim($path, '/');
        header("Location: " . $location);
        exit;
    }

    /**
     * Root URL landing page.
     */
    public function landing(): void {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }
        $this->render('landing');
    }

    /**
     * Show login form.
     */
    public function showLogin(): void {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }
        $this->render('login');
    }

    /**
     * Process login submission.
     */
    public function login(): void {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $error = '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $user = User::verifyCredentials($email, $password);
            if ($user) {
                if ($user['is_approved'] == 0 && $user['is_admin'] == 0) {
                    $error = 'Your registration is pending administrator approval.';
                } elseif ($user['is_approved'] == 2) {
                    $error = 'Your account has been blocked by the administrator.';
                } else {
                    // Success! Rotate session ID to prevent fixation attacks
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['is_admin'] = $user['is_admin'];
                    
                    // PIN status (user must verify PIN to unlock messages)
                    $_SESSION['pin_verified']      = false;
                    $_SESSION['last_activity']     = time();
                    // Trigger location prompt on the next page load
                    $_SESSION['location_prompt']   = true;

                    if ($user['is_admin'] == 1) {
                        $this->redirect('/admin');
                    } else {
                        $this->redirect('/dashboard');
                    }
                }
            } else {
                $error = 'Invalid email or password.';
            }
        }

        $this->render('login', ['error' => $error, 'email' => $email]);
    }

    /**
     * Show registration form.
     */
    public function showRegistration(): void {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }
        $this->render('registration');
    }

    /**
     * Process registration submission.
     */
    public function register(): void {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }

        $fullName = $_POST['full_name'] ?? '';
        $address = $_POST['address'] ?? '';
        $dob = $_POST['dob'] ?? '';
        $institute = $_POST['institute'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $pin = $_POST['pin'] ?? '';

        $error = '';
        $success = '';

        // Validation
        if (empty($fullName) || empty($address) || empty($dob) || empty($institute) || empty($phone) || empty($email) || empty($password) || empty($pin)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address format.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/^\d{4}$/', $pin)) {
            $error = 'PIN must be exactly 4 digits.';
        } elseif (User::getByEmail($email) !== null) {
            $error = 'Email is already registered.';
        } else {
            // Save user
            $created = User::create($email, $password, $pin, $fullName, $address, $dob, $institute, $phone);
            if ($created) {
                // Check auto-approve setting (try-catch guards against schema migration lag)
                $autoApprove = false;
                try {
                    $db = \Database::getCoreConnection();
                    $settingStmt = $db->prepare("SELECT value FROM app_settings WHERE key='auto_approve_registration'");
                    $settingStmt->execute();
                    $autoApprove = ($settingStmt->fetchColumn() === '1');
                } catch (\PDOException $e) { /* table not yet created — default off */ }

                if ($autoApprove) {
                    $newUser = User::getByEmail($email);
                    if ($newUser) {
                        User::updateStatus($newUser['id'], 1); // immediately approved
                    }
                    $success = 'Registration approved! You can sign in now.';
                } else {
                    $success = 'Registration submitted successfully! Please wait for admin approval before logging in.';
                }
            } else {
                $error = 'An error occurred during registration. Please try again.';
            }
        }

        $this->render('registration', [
            'error' => $error,
            'success' => $success,
            'fields' => $_POST
        ]);
    }

    /**
     * Handle logging out.
     */
    public function logout(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        $this->redirect('/login');
    }

    /**
     * Change password.
     */
    public function changePassword(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $userId = $_SESSION['user_id'];
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Password fields cannot be empty']);
            exit;
        }

        if (strlen($newPass) < 6) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters']);
            exit;
        }

        $db = \Database::getCoreConnection();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPass, $user['password_hash'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Incorrect current password']);
            exit;
        }

        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $update = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $success = $update->execute([$newHash, $userId]);

        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }

    /**
     * Change 4-digit PIN.
     */
    public function changePin(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $userId = $_SESSION['user_id'];
        $currentPin = $_POST['current_pin'] ?? '';
        $newPin = $_POST['new_pin'] ?? '';

        if (empty($currentPin) || empty($newPin)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'PIN fields cannot be empty']);
            exit;
        }

        if (!preg_match('/^\d{4}$/', $newPin)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'New PIN must be exactly 4 digits']);
            exit;
        }

        $db = \Database::getCoreConnection();
        $stmt = $db->prepare("SELECT pin_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPin, $user['pin_hash'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Incorrect current PIN']);
            exit;
        }

        $newHash = password_hash($newPin, PASSWORD_BCRYPT);
        $update = $db->prepare("UPDATE users SET pin_hash = ? WHERE id = ?");
        $success = $update->execute([$newHash, $userId]);

        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }
}
