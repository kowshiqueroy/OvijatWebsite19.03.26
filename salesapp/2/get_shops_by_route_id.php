<?php
/**
 * AJAX: returns <option> tags for shops belonging to a route.
 * Used by orders.php and cash.php Select2 dropdowns.
 */
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit; }

$route_id = (int)($_GET['route_id'] ?? 0);
$cid      = (int)$_SESSION['company_id'];

if (!$route_id) {
    echo '<option value="">Select Shop</option>';
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, shop_name FROM shops WHERE route_id=? AND company_id=? AND status=1 ORDER BY shop_name ASC"
);
$stmt->bind_param("ii", $route_id, $cid);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

echo '<option value="">Select Shop</option>';
if ($res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['shop_name']) . '</option>';
    }
} else {
    echo '<option value="" disabled>No shops in this route</option>';
}
