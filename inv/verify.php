<?php
/**
 * verify.php - Invoice Verification
 */
require_once 'includes/config.php';
require_once 'includes/functions.php';

$sale_id = (int)$_GET['id'] ?? 0;

if (!$sale_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid invoice ID']);
    exit;
}

// Fetch Sale
$stmt = $pdo->prepare("
    SELECT s.*, c.name as customer_name, c.type as customer_type, b.name as branch_name
    FROM sales s 
    JOIN customers c ON s.customer_id = c.id 
    JOIN branches b ON s.branch_id = b.id
    WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

header('Content-Type: application/json');

if (!$sale) {
    http_response_code(404);
    echo json_encode(['error' => 'Invoice not found']);
    exit;
}

// Fetch items count
$stmtItems = $pdo->prepare("SELECT COUNT(*) as cnt FROM sale_items WHERE sale_id = ?");
$stmtItems->execute([$sale_id]);
$items_count = $stmtItems->fetch()['cnt'];

echo json_encode([
    'valid' => true,
    'invoice' => 'INV-' . str_pad($sale['id'], 5, '0', STR_PAD_LEFT),
    'status' => $sale['status'],
    'customer' => $sale['customer_name'],
    'customer_type' => $sale['customer_type'],
    'branch' => $sale['branch_name'],
    'date' => $sale['created_at'],
    'total' => $sale['total_amount'],
    'items' => $items_count
]);