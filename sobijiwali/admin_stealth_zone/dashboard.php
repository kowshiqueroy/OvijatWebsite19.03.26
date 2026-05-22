<?php
/**
 * Admin Dashboard Module
 */
$pageTitle = 'Dashboard';
require_once 'layout_header.php';

$warehouse = new WarehouseManager();
$logger = new Logger();
$message = '';
if (isset($_GET['action']) && isset($_GET['order_id'])) {
    $action = $_GET['action'];
    $orderId = (int)$_GET['order_id'];

    switch ($action) {
        case 'process':
            $result = $warehouse->processOrder($orderId);
            if ($result['success']) {
                $logger->log('process_order', 'order', $orderId, "Deducted FIFO stock");
                $message = "Order #$orderId is now being processed.";
            } else { $message = "Error: " . $result['message']; }
            break;
        case 'ship':
            $result = $warehouse->shipOrder($orderId);
            if ($result['success']) {
                $logger->log('ship_order', 'order', $orderId, "Captured payment & reward issued");
                $message = "Order #$orderId marked as Shipped.";
            } else { $message = "Error: " . $result['message']; }
            break;
        case 'cancel':
            $disposition = $_GET['disp'] ?? 'restock';
            $warehouse->cancelOrder($orderId, $disposition);
            $logger->log('cancel_order', 'order', $orderId, "Order cancelled. Disposition: $disposition");
            $message = "Order #$orderId has been cancelled ($disposition).";
            break;
    }
}

// 📊 KPI Metrics
$db = Database::getInstance();
$kpis = [
    'pending_approvals' => $db->query("SELECT COUNT(*) FROM users WHERE role = 'pending_wholesale'")->fetchColumn(),
    'orders_to_ship' => $db->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn(),
    'today_revenue' => $db->query("SELECT SUM(total_amount) FROM orders WHERE status != 'cancelled' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0,
    'low_stock' => $db->query("SELECT COUNT(*) FROM (SELECT SUM(quantity_remaining) as total FROM inventory_batches GROUP BY product_variation_id HAVING total < 10) as low")->fetchColumn()
];

$statusFilter = $_GET['status'] ?? 'all';
$sql = "SELECT o.*, u.email as user_email FROM orders o LEFT JOIN users u ON o.user_id = u.id";
if ($statusFilter !== 'all') $sql .= " WHERE o.status = " . $db->quote($statusFilter);
$sql .= " ORDER BY o.created_at DESC";
$orders = $db->query($sql)->fetchAll();

// 📈 Chart Data: Last 7 Days Sales
$chartData = $db->query("SELECT DATE(created_at) as day, SUM(total_amount) as total 
                        FROM orders 
                        WHERE status != 'cancelled' 
                        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                        GROUP BY DATE(created_at) 
                        ORDER BY day ASC")->fetchAll();

$labels = [];
$values = [];
foreach ($chartData as $d) {
    $labels[] = date('D, M d', strtotime($d['day']));
    $values[] = $d['total'];
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Fulfillment Center</h1>
    <input type="text" id="barcode-scanner" placeholder="📟 Scan Box to Ship..." style="width: 300px; padding: 10px; border-radius: 8px; border: 2px solid var(--border);" autofocus>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2.5rem;">
    <div class="card" style="padding: 1.5rem;">
        <div style="font-size: 0.75rem; font-weight: 800; opacity: 0.5; text-transform: uppercase; margin-bottom: 1rem;">Sales Trend (Last 7 Days)</div>
        <canvas id="salesChart" height="100"></canvas>
    </div>
    <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
        <div class="card" style="border-top: 4px solid var(--primary); padding: 1rem;">
            <div style="font-size: 0.7rem; font-weight: 800; opacity: 0.5; text-transform: uppercase;">Pending Approval</div>
            <div style="font-size: 1.5rem; font-weight: 800; margin-top: 0.3rem;"><?php echo $kpis['pending_approvals']; ?></div>
        </div>
        <div class="card" style="border-top: 4px solid #3182ce; padding: 1rem;">
            <div style="font-size: 0.7rem; font-weight: 800; opacity: 0.5; text-transform: uppercase;">Orders to Ship</div>
            <div style="font-size: 1.5rem; font-weight: 800; margin-top: 0.3rem;"><?php echo $kpis['orders_to_ship']; ?></div>
        </div>
        <?php if (in_array($userRole, ['admin', 'reports'])): ?>
        <div class="card" style="border-top: 4px solid var(--accent); padding: 1rem;">
            <div style="font-size: 0.7rem; font-weight: 800; opacity: 0.5; text-transform: uppercase;">Today Revenue</div>
            <div style="font-size: 1.5rem; font-weight: 800; margin-top: 0.3rem;">$<?php echo number_format($kpis['today_revenue'], 2); ?></div>
        </div>
        <?php endif; ?>
        <div class="card" style="border-top: 4px solid var(--error); padding: 1rem;">
            <div style="font-size: 0.7rem; font-weight: 800; opacity: 0.5; text-transform: uppercase;">Low Stock</div>
            <div style="font-size: 1.5rem; font-weight: 800; margin-top: 0.3rem;"><?php echo $kpis['low_stock']; ?></div>
        </div>
    </div>
</div>

<div style="display: flex; gap: 10px; margin-bottom: 1.5rem;">
    <a href="?status=all" class="btn <?php echo $statusFilter == 'all' ? 'btn-primary' : 'btn-outline'; ?>">All Orders</a>
    <a href="?status=pending" class="btn <?php echo $statusFilter == 'pending' ? 'btn-primary' : 'btn-outline'; ?>">Pending</a>
    <a href="?status=processing" class="btn <?php echo $statusFilter == 'processing' ? 'btn-primary' : 'btn-outline'; ?>">Processing</a>
    <a href="?status=shipped" class="btn <?php echo $statusFilter == 'shipped' ? 'btn-primary' : 'btn-outline'; ?>">Shipped</a>
</div>

<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Method</th>
                <th>Total</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><strong>#<?php echo $o['id']; ?></strong></td>
                <td>
                    <?php if ($o['user_email']): ?>
                        <?php echo htmlspecialchars($o['user_email']); ?>
                    <?php else: ?>
                        <?php echo htmlspecialchars($o['guest_name']); ?> <span class="badge" style="background:#eee;">Guest</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge" style="background: <?php echo $o['status'] === 'shipped' ? '#c6f6d5; color: #22543d;' : '#feebc8; color: #744210;'; ?>"><?php echo $o['status']; ?></span></td>
                <td><small><?php echo strtoupper($o['payment_method']); ?></small></td>
                <td><strong>$<?php echo number_format($o['total_amount'], 2); ?></strong></td>
                <td>
                    <div style="display: flex; gap: 5px;">
                        <a href="order_detail.php?order_id=<?php echo $o['id']; ?>" class="btn btn-outline">View</a>
                        <?php if ($o['status'] === 'pending' && in_array($userRole, ['admin', 'warehouse'])): ?>
                            <a href="?action=process&order_id=<?php echo $o['id']; ?>" class="btn btn-primary">Process</a>
                        <?php elseif ($o['status'] === 'processing' && in_array($userRole, ['admin', 'warehouse'])): ?>
                            <a href="?action=ship&order_id=<?php echo $o['id']; ?>" class="btn btn-primary" style="background:#3182ce;">Ship</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Revenue ($)',
                data: <?php echo json_encode($values); ?>,
                borderColor: '#2D5A27',
                backgroundColor: 'rgba(45, 90, 39, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#2D5A27'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { display: false } },
                x: { grid: { display: false } }
            }
        }
    });

    const scannerInput = document.getElementById('barcode-scanner');
    scannerInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            const val = scannerInput.value.replace('#', '');
            if (!isNaN(val)) {
                window.location.href = `?action=ship&order_id=${val}`;
            }
        }
    });
</script>

<?php require_once 'layout_footer.php'; ?>