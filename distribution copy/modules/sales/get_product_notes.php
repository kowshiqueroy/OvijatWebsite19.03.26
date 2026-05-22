<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();

$product_id = $_GET['product_id'] ?? 0;

if ($product_id) {
    // Get the latest note
    $latest = fetch_one("SELECT note FROM sales_items WHERE product_id = ? AND note != '' ORDER BY id DESC LIMIT 1", [$product_id]);
    
    // Get unique historical notes (limited to top 5)
    $history = fetch_all("SELECT DISTINCT note FROM sales_items WHERE product_id = ? AND note != '' ORDER BY id DESC LIMIT 5", [$product_id]);
    
    echo json_encode([
        'latest' => $latest['note'] ?? '',
        'history' => array_column($history, 'note')
    ]);
} else {
    echo json_encode(['latest' => '', 'history' => []]);
}
?>
