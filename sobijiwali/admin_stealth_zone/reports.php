<?php
/**
 * Admin Reports & CSV Export
 */
$pageTitle = 'Accounting Reports';
require_once 'layout_header.php';

$db = Database::getInstance();

if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $filename = $type . "_report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');

    if ($type === 'revenue') {
        fputcsv($output, ['Order ID', 'Customer', 'Amount', 'Tax', 'Shipping', 'Payment Method', 'Date']);
        $data = $db->query("SELECT o.id, IFNULL(u.email, o.guest_email) as customer, o.total_amount, o.tax_amount, o.shipping_fee, o.payment_method, o.created_at 
                           FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.status != 'cancelled' ORDER BY o.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as $row) fputcsv($output, $row);
    } elseif ($type === 'inventory') {
        fputcsv($output, ['SKU', 'Product', 'Variation', 'Total Stock', 'Avg Cost']);
        $data = $db->query("SELECT v.sku, p.name, v.name_modifier, SUM(b.quantity_remaining) as stock, AVG(b.cost_price) as cost 
                           FROM product_variations v 
                           JOIN products p ON v.product_id = p.id 
                           LEFT JOIN inventory_batches b ON v.id = b.product_variation_id 
                           GROUP BY v.id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as $row) fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
?>

<h1>Reports & Analytics</h1>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-top: 2rem;">
    <div class="card">
        <div style="font-size: 2rem; margin-bottom: 1rem;">💰</div>
        <h3>Revenue Export</h3>
        <p style="opacity: 0.6; font-size: 0.9rem; margin-bottom: 2rem;">Full breakdown of all non-cancelled orders for financial auditing.</p>
        <a href="?export=revenue" class="btn btn-primary" style="width: 100%; padding: 1rem;">Download Revenue CSV</a>
    </div>

    <div class="card">
        <div style="font-size: 2rem; margin-bottom: 1rem;">📦</div>
        <h3>Inventory Audit</h3>
        <p style="opacity: 0.6; font-size: 0.9rem; margin-bottom: 2rem;">Real-time stock levels, SKU tracking, and average cost-price analysis.</p>
        <a href="?export=inventory" class="btn btn-primary" style="width: 100%; padding: 1rem; background: #3182ce;">Download Inventory CSV</a>
    </div>
</div>

<?php require_once 'layout_footer.php'; ?>
