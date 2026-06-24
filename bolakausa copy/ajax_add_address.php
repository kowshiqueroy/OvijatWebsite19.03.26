<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_helper.php';

// Define BASE_URL locally if not already defined
if (!defined('BASE_URL')) {
    $script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    define('BASE_URL', rtrim($script_path, '/') . '/');
}

restrict_to(['wholesale_user', 'executive']);

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_checkout_address'])) {
    $address_line = trim($_POST['new_address_line'] ?? '');
    $city = trim($_POST['new_city'] ?? '');
    $location_id = !empty($_POST['new_location_id']) ? (int)$_POST['new_location_id'] : 0;

    if ($address_line && $city && $location_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ? AND is_deleted = 0");
        $stmt->execute([$user_id]);
        $is_default = ($stmt->fetchColumn() == 0) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, address_line, city, location_id, is_default) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $address_line, $city, $location_id, $is_default])) {
            $response['success'] = true;
            $response['message'] = 'Address added!';
            $response['address_id'] = $pdo->lastInsertId();
            log_action($pdo, $user_id, 'Added Address during Checkout', null, "$address_line, $city");
        } else {
            $response['message'] = 'Failed to add address.';
        }
    } else {
        $response['message'] = 'Address line, city, and US Delivery State are required.';
    }
} else {
    $response['message'] = 'Invalid request.';
}

// Discard any buffered output from PHP
while (ob_get_level()) ob_end_clean();

header('Content-Type: application/json');
echo json_encode($response);
exit;
