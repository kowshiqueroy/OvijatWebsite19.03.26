<?php
/**
 * Admin Dashboard with Analytics - Premium Redesign
 */
restrict_to(['admin']);

// Fetch Analytics Stats
$total_sales = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status NOT IN ('Cancelled', 'Pending Payment')")->fetchColumn() ?: 0;
$active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'wholesale_user' AND status = 'active'")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending Payment'")->fetchColumn();
$low_stock_items = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= 5")->fetchColumn();
?>

<div class="section-title">
    <i class="fas fa-chart-pie" style="color: var(--primary);"></i>
    Admin Intelligence
</div>

<div class="stat-grid">
    <div class="stat-box bg-blue-glass">
        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Total Sales</div>
        <div style="font-size: 2.5rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);">$<?php echo number_format($total_sales, 2); ?></div>
        <i class="fas fa-dollar-sign"></i>
    </div>
    
    <div class="stat-box bg-green-glass">
        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Active Partners</div>
        <div style="font-size: 2.5rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);"><?php echo $active_users; ?></div>
        <i class="fas fa-users"></i>
    </div>
    
    <div class="stat-box bg-red-glass" style="border-bottom-color: #f59e0b;">
        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Pending Payments</div>
        <div style="font-size: 2.5rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);"><?php echo $pending_orders; ?></div>
        <i class="fas fa-clock"></i>
    </div>
    
    <div class="stat-box bg-red-glass">
        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Stock Alerts</div>
        <div style="font-size: 2.5rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);"><?php echo $low_stock_items; ?></div>
        <i class="fas fa-exclamation-triangle"></i>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2.5rem;">
    <div class="card">
        <h3 style="font-weight: 800; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <i class="fas fa-store" style="color: var(--primary);"></i> Catalog Control
        </h3>
        <p style="margin-bottom: 2rem; color: var(--text-muted); font-size: 0.9375rem;">Manage your wholesale categories and product offerings with real-time stock tracking.</p>
        <div style="display:flex; gap: 1rem;">
            <a href='/bolakausa/admin/categories' class="btn btn-blue">Categories</a>
            <a href='/bolakausa/admin/products' class="btn btn-green">Products</a>
        </div>
    </div>
    
    <div class="card">
        <h3 style="font-weight: 800; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <i class="fas fa-wallet" style="color: #3b82f6;"></i> Financial Center
        </h3>
        <p style="margin-bottom: 2rem; color: var(--text-muted); font-size: 0.9375rem;">Process orders, verify bank transfers, and manage wholesale user digital wallets.</p>
        <div style="display:flex; gap: 1rem;">
            <a href='/bolakausa/admin/orders' class="btn btn-blue">Orders</a>
            <a href='/bolakausa/admin/wallet' class="btn btn-green">Wallet</a>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 2.5rem;">
    <h3 style="font-weight: 800; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">
        <i class="fas fa-sliders-h" style="color: var(--text-muted);"></i> Operations & Systems
    </h3>
    <div style="display:flex; gap: 1rem; flex-wrap: wrap;">
        <a href='/bolakausa/admin/users' class="btn btn-blue" style="background: rgba(15, 23, 42, 0.05); color: var(--secondary); border: 1px solid var(--glass-border);">Verify Users</a>
        <a href='/bolakausa/admin/chats' class="btn btn-blue" style="background: rgba(15, 23, 42, 0.05); color: var(--secondary); border: 1px solid var(--glass-border);">Support Chat</a>
        <a href='/bolakausa/admin/settings' class="btn btn-blue" style="background: rgba(15, 23, 42, 0.05); color: var(--secondary); border: 1px solid var(--glass-border);">System Settings</a>
        <a href='/bolakausa/admin/logs' class="btn btn-blue" style="background: rgba(15, 23, 42, 0.05); color: var(--secondary); border: 1px solid var(--glass-border);">Audit Logs</a>
    </div>
</div>
