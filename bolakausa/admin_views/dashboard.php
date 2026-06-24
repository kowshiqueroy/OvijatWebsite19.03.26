<?php
/**
 * Admin Dashboard with Analytics - Premium Role-Based Redesign
 */
restrict_to(['admin', 'manager', 'editor', 'viewer']);

$user_role = $_SESSION['user_role'];

// 1. Gather Catalog Stats (Visible to Admin, Manager, Editor)
$total_products = $pdo->query("SELECT COUNT(*) FROM products WHERE is_deleted = 0")->fetchColumn() ?: 0;
$total_categories = $pdo->query("SELECT COUNT(*) FROM categories WHERE is_deleted = 0")->fetchColumn() ?: 0;
$low_stock_items = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= 5 AND is_deleted = 0")->fetchColumn() ?: 0;

// 2. Gather Operational Stats (Visible to Admin, Manager)
$active_users = 0;
$pending_orders = 0;
$total_orders = 0;
if (in_array($user_role, ['admin', 'manager'])) {
    $active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'wholesale_user' AND status = 'active' AND is_deleted = 0")->fetchColumn() ?: 0;
    $pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'Unpaid' AND fulfillment_status NOT IN ('Cancelled', 'Rejected')")->fetchColumn() ?: 0;
    $total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?: 0;
}

// 3. Gather Financial Stats (Visible to Admin Only)
$total_sales = 0;
$avg_order_value = 0;
$wallet_credits_held = 0;
if ($user_role === 'admin') {
    $total_sales = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE payment_status = 'Paid'")->fetchColumn() ?: 0;
    $avg_order_value = $pdo->query("SELECT AVG(total_amount) FROM orders WHERE fulfillment_status NOT IN ('Cancelled', 'Rejected')")->fetchColumn() ?: 0;
    $wallet_credits_held = $pdo->query("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) FROM wallet_transactions")->fetchColumn() ?: 0;
}

// Query chart data: Stock by category (Admin, Manager, Editor)
$cat_stock_data = [];
if (in_array($user_role, ['admin', 'manager', 'editor'])) {
    $stmt = $pdo->query("SELECT c.name, SUM(p.stock_qty) as total_stock FROM products p JOIN categories c ON p.category_id = c.id WHERE p.is_deleted = 0 AND c.is_deleted = 0 GROUP BY c.name");
    $cat_stock_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Query chart data: Orders by status (Admin, Manager)
$order_status_data = [];
if (in_array($user_role, ['admin', 'manager'])) {
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $order_status_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Query chart data: Sales Trend (Admin Only)
$sales_trend_data = [];
if ($user_role === 'admin') {
    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%b %Y') as month, SUM(total_amount) as total FROM orders WHERE status NOT IN ('Cancelled', 'Rejected') GROUP BY month ORDER BY MIN(created_at) ASC LIMIT 6");
    $sales_trend_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
?>

<!-- Load Chart.js CDN for interactive visual graphs -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="section-title">
    <i class="fas fa-chart-pie" style="color: var(--primary);"></i>
    Business Intelligence Dashboard <span style="font-size:0.9rem; font-weight:600; color:var(--text-muted); margin-left:1rem; background:rgba(0,0,0,0.04); padding:3px 10px; border-radius:20px; text-transform:uppercase;">Role: <?php echo $user_role; ?></span>
</div>

<!-- Stats widgets grid based on role permissions -->
<div class="stat-grid" style="margin-bottom: 2.5rem;">
    <?php if ($user_role === 'admin'): ?>
        <!-- Admin financial stats cards -->
        <div class="stat-box bg-blue-glass" style="border-left: 4px solid var(--primary);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total Sales Revenue</div>
            <div style="font-size: 1.8rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);">$<?php echo number_format($total_sales, 2); ?></div>
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="stat-box bg-blue-glass" style="border-left: 4px solid var(--accent);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Avg Order Value</div>
            <div style="font-size: 1.8rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);">$<?php echo number_format($avg_order_value, 2); ?></div>
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-box bg-blue-glass" style="border-left: 4px solid var(--primary-dark);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Credits in System</div>
            <div style="font-size: 1.8rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);">$<?php echo number_format($wallet_credits_held, 2); ?></div>
            <i class="fas fa-wallet"></i>
        </div>
    <?php endif; ?>

    <?php if (in_array($user_role, ['admin', 'manager'])): ?>
        <!-- Operational stats cards -->
        <div class="stat-box bg-green-glass" style="border-left: 4px solid var(--primary);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Active Wholesale Partners</div>
            <div style="font-size: 1.8rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);"><?php echo $active_users; ?></div>
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-box bg-green-glass" style="border-left: 4px solid var(--accent);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total B2B Orders</div>
            <div style="font-size: 1.8rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);"><?php echo $total_orders; ?></div>
            <i class="fas fa-shopping-bag"></i>
        </div>
        <div class="stat-box bg-red-glass" style="border-left: 4px solid #f59e0b;">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Pending Payments</div>
            <div style="font-size: 1.8rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);"><?php echo $pending_orders; ?></div>
            <i class="fas fa-clock"></i>
        </div>
    <?php endif; ?>

    <!-- Catalog stats cards (visible to Admin, Manager, Editor) -->
    <?php if (in_array($user_role, ['admin', 'manager', 'editor'])): ?>
        <div class="stat-box bg-blue-glass" style="border-left: 4px solid var(--secondary);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Wholesale Products</div>
            <div style="font-size: 1.8rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);"><?php echo $total_products; ?></div>
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-box bg-red-glass" style="border-left: 4px solid var(--rose);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Critical Stock Alerts</div>
            <div style="font-size: 1.8rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);"><?php echo $low_stock_items; ?></div>
            <i class="fas fa-exclamation-triangle"></i>
        </div>
    <?php endif; ?>
</div>

<!-- Charts Container Grid -->
<div class="grid-stack-mobile" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
    <?php if ($user_role === 'admin'): ?>
        <!-- Admin-only Sales Trend Chart -->
        <div class="card" style="padding: 1.5rem; background: white; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);">
            <h4 style="margin:0 0 1.5rem 0; font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); display:flex; align-items:center; gap:0.5rem;"><i class="fas fa-chart-line" style="color:var(--primary);"></i> Revenue Performance (Sales Trend)</h4>
            <canvas id="salesTrendChart" style="max-height: 260px;"></canvas>
        </div>
    <?php endif; ?>

    <?php if (in_array($user_role, ['admin', 'manager'])): ?>
        <!-- Admin/Manager Order Status Chart -->
        <div class="card" style="padding: 1.5rem; background: white; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);">
            <h4 style="margin:0 0 1.5rem 0; font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); display:flex; align-items:center; gap:0.5rem;"><i class="fas fa-chart-pie" style="color:#d97706;"></i> Orders Status Distribution</h4>
            <canvas id="orderStatusChart" style="max-height: 260px;"></canvas>
        </div>
    <?php endif; ?>

    <?php if (in_array($user_role, ['admin', 'manager', 'editor'])): ?>
        <!-- Category Stock Chart -->
        <div class="card" style="padding: 1.5rem; background: white; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);">
            <h4 style="margin:0 0 1.5rem 0; font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); display:flex; align-items:center; gap:0.5rem;"><i class="fas fa-barcode" style="color:var(--primary);"></i> Stock Volume by Category</h4>
            <canvas id="categoryStockChart" style="max-height: 260px;"></canvas>
        </div>
    <?php endif; ?>
</div>

<!-- Shortcuts Panel depending on role -->
<div class="grid-stack-mobile" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 2rem;">
    <?php if (in_array($user_role, ['admin', 'manager'])): ?>
        <div class="card" style="padding: 1.5rem; background: white; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);">
            <h4 style="margin: 0 0 1rem 0; font-family:'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--secondary);"><i class="fas fa-tasks"></i> Operational Shortcuts</h4>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem;">
                <a href="/bolakausa/admin/orders" class="btn btn-blue">Fulfillment Desk</a>
                <a href="/bolakausa/admin/wallet" class="btn btn-green">Wallet & Finances</a>
                <a href="/bolakausa/admin/inventory-insights" class="btn btn-outline" style="font-weight:700;"><i class="fas fa-warehouse"></i> Inventory Insights</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (in_array($user_role, ['admin', 'manager', 'editor'])): ?>
        <div class="card" style="padding: 1.5rem; background: white; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);">
            <h4 style="margin: 0 0 1rem 0; font-family:'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--secondary);"><i class="fas fa-edit"></i> Catalog Control Panel</h4>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem;">
                <a href="/bolakausa/admin/products" class="btn btn-blue">Manage Products</a>
                <a href="/bolakausa/admin/categories" class="btn btn-green">Manage Categories</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Render Sales Trend Chart (Admin Only)
    <?php if ($user_role === 'admin' && !empty($sales_trend_data)): ?>
        const trendCtx = document.getElementById('salesTrendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($sales_trend_data)); ?>,
                datasets: [{
                    label: 'Monthly Revenue ($)',
                    data: <?php echo json_encode(array_values($sales_trend_data)); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    <?php endif; ?>

    // 2. Render Order Status Chart (Admin, Manager)
    <?php if (in_array($user_role, ['admin', 'manager']) && !empty($order_status_data)): ?>
        const statusCtx = document.getElementById('orderStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($order_status_data)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($order_status_data)); ?>,
                    backgroundColor: [
                        'rgba(244, 63, 94, 0.75)',  // Pending Payment
                        'rgba(16, 185, 129, 0.75)', // Payment Verified
                        'rgba(59, 130, 246, 0.75)',  // Confirmed
                        'rgba(99, 102, 241, 0.75)', // Processing
                        'rgba(245, 158, 11, 0.75)',  // Hold
                        'rgba(100, 116, 139, 0.75)'  // Others
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'right' } }
            }
        });
    <?php endif; ?>

    // 3. Render Category Stock Chart (Admin, Manager, Editor)
    <?php if (in_array($user_role, ['admin', 'manager', 'editor']) && !empty($cat_stock_data)): ?>
        const stockCtx = document.getElementById('categoryStockChart').getContext('2d');
        new Chart(stockCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($cat_stock_data)); ?>,
                datasets: [{
                    label: 'Available Stock Units',
                    data: <?php echo json_encode(array_values($cat_stock_data)); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    <?php endif; ?>
});
</script>
