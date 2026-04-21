<?php
/**
 * modules/dashboard.php
 */
include '../includes/header.php';

$branch_id = $_SESSION['branch_id'];

// 1. Total Revenue (Completed Sales)
$stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM sales WHERE branch_id = ? AND (status = 'completed' OR status = 'approved')");
$stmt->execute([$branch_id]);
$total_sales = $stmt->fetch()['total'] ?? 0;

// 2. Cost of Goods Sold (COGS)
$stmt = $pdo->prepare("SELECT SUM(si.quantity * si.purchase_price) as cogs 
                       FROM sale_items si 
                       JOIN sales s ON si.sale_id = s.id 
                       WHERE s.branch_id = ? AND (s.status = 'completed' OR s.status = 'approved')");
$stmt->execute([$branch_id]);
$total_cogs = $stmt->fetch()['cogs'] ?? 0;

// 3. Total Expenses
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE branch_id = ?");
$stmt->execute([$branch_id]);
$total_expenses = $stmt->fetch()['total'] ?? 0;

$net_profit = $total_sales - $total_cogs - $total_expenses;

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales WHERE branch_id = ? AND status = 'pending_approval'");
$stmt->execute([$branch_id]);
$pending_sales = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM inventory i JOIN products p ON i.product_id = p.id WHERE i.branch_id = ? AND i.quantity_pcs < 10");
$stmt->execute([$branch_id]);
$low_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customers WHERE branch_id = ? AND is_deleted = 0");
$stmt->execute([$branch_id]);
$total_customers = $stmt->fetch()['count'] ?? 0;

// Top 5 Products Data
$stmt = $pdo->prepare("SELECT p.name, SUM(si.quantity) as total_qty 
                       FROM sale_items si 
                       JOIN products p ON si.product_id = p.id 
                       JOIN sales s ON si.sale_id = s.id 
                       WHERE s.branch_id = ? AND (s.status = 'completed' OR s.status = 'approved')
                       GROUP BY si.product_id 
                       ORDER BY total_qty DESC LIMIT 5");
$stmt->execute([$branch_id]);
$top_products = $stmt->fetchAll();

$chart_labels = [];
$chart_data = [];
foreach ($top_products as $tp) {
    $chart_labels[] = $tp['name'];
    $chart_data[] = $tp['total_qty'];
}

// Recent Sales for current branch
$stmt = $pdo->prepare("SELECT s.*, c.name as customer_name FROM sales s JOIN customers c ON s.customer_id = c.id WHERE s.branch_id = ? ORDER BY s.created_at DESC LIMIT 5");
$stmt->execute([$branch_id]);
$recent_sales = $stmt->fetchAll();
?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white p-3 shadow-sm border-0">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase small">Total Revenue</h6>
                    <h3 class="fw-bold mb-0"><?php echo formatCurrency($total_sales); ?></h3>
                </div>
                <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white p-3 shadow-sm border-0">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase small">Net Profit</h6>
                    <h3 class="fw-bold mb-0"><?php echo formatCurrency($net_profit); ?></h3>
                </div>
                <i class="fas fa-chart-line fa-2x opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white p-3 shadow-sm border-0">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase small">Low Stock Alerts</h6>
                    <h3 class="fw-bold mb-0"><?php echo $low_stock; ?></h3>
                </div>
                <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark p-3 shadow-sm border-0">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase small">Pending Sales</h6>
                    <h3 class="fw-bold mb-0"><?php echo $pending_sales; ?></h3>
                </div>
                <i class="fas fa-clock fa-2x opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Recent Sales</h5>
                <a href="sales/list.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Invoice ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td>#INV-<?php echo str_pad($sale['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo $sale['customer_name']; ?></td>
                                <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                                <td>
                                    <?php 
                                    $badge = 'bg-secondary';
                                    if ($sale['status'] == 'completed') $badge = 'bg-success';
                                    if ($sale['status'] == 'pending_approval') $badge = 'bg-warning text-dark';
                                    if ($sale['status'] == 'rejected') $badge = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($sale['status']); ?></span>
                                </td>
                                <td><?php echo formatDate($sale['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_sales)): ?>
                            <tr><td colspan="5" class="text-center p-4">No recent sales found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">Top Products</h5>
            </div>
            <div class="card-body">
                <canvas id="topProductsChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    const ctx = document.getElementById('topProductsChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($chart_data); ?>,
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'
                ],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            cutout: '70%',
        }
    });
});
</script>
