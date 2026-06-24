<?php
/**
 * Inventory Insights & Wholesaler Analytics - Premium Interactive Interface
 */
restrict_to(['admin', 'manager']);

$user_role = $_SESSION['user_role'];

// Fetch selected tab
$active_tab = $_GET['tab'] ?? 'low-stock';

// Query data based on tabs
$low_stock = [];
if ($active_tab === 'low-stock') {
    $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.stock_qty <= 5 AND p.is_deleted = 0 ORDER BY p.stock_qty ASC");
    $low_stock = $stmt->fetchAll();
}

$high_stock = [];
if ($active_tab === 'high-stock') {
    $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.stock_qty >= 100 AND p.is_deleted = 0 ORDER BY p.stock_qty DESC");
    $high_stock = $stmt->fetchAll();
}

$fast_moving = [];
if ($active_tab === 'fast-moving') {
    $stmt = $pdo->query("
        SELECT p.id, p.name, c.name as category_name, SUM(oi.qty) as total_sold, SUM(oi.qty * oi.price_at_purchase) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status NOT IN ('Cancelled', 'Rejected') AND oi.is_deleted = 0 AND p.is_deleted = 0
        GROUP BY p.id, p.name, c.name
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $fast_moving = $stmt->fetchAll();
}

$slow_moving = [];
if ($active_tab === 'slow-moving') {
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.stock_qty, c.name as category_name, COALESCE(SUM(oi.qty), 0) as total_sold
        FROM products p
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN order_items oi ON oi.product_id = p.id AND oi.is_deleted = 0 AND oi.order_id IN (
            SELECT id FROM orders WHERE status NOT IN ('Cancelled', 'Rejected')
        )
        WHERE p.is_deleted = 0
        GROUP BY p.id, p.name, p.stock_qty, c.name
        ORDER BY total_sold ASC, p.stock_qty DESC
        LIMIT 10
    ");
    $slow_moving = $stmt->fetchAll();
}

$top_wholesalers = [];
if ($active_tab === 'top-wholesalers') {
    if ($user_role === 'admin') {
        $stmt = $pdo->query("
            SELECT u.id, u.username, u.full_name, COUNT(o.id) as order_count, SUM(o.total_amount) as total_spend
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.status NOT IN ('Cancelled', 'Rejected') AND u.is_deleted = 0
            GROUP BY u.id, u.username, u.full_name
            ORDER BY total_spend DESC
            LIMIT 10
        ");
        $top_wholesalers = $stmt->fetchAll();
    } else {
        // Manager query masks total spend and sorts by count of orders
        $stmt = $pdo->query("
            SELECT u.id, u.username, u.full_name, COUNT(o.id) as order_count
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.status NOT IN ('Cancelled', 'Rejected') AND u.is_deleted = 0
            GROUP BY u.id, u.username, u.full_name
            ORDER BY order_count DESC
            LIMIT 10
        ");
        $top_wholesalers = $stmt->fetchAll();
    }
}
?>

<div class="section-title">
    <i class="fas fa-warehouse" style="color: var(--primary);"></i>
    Inventory & Partner Analytics
</div>

<!-- Tabs Navigation Header -->
<div class="card" style="margin-bottom: 2rem; padding: 1rem;">
    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
        <a href="/bolakausa/admin/inventory-insights?tab=low-stock" class="btn <?php echo ($active_tab === 'low-stock') ? 'btn-blue' : 'btn-outline'; ?>" style="padding: 0.6rem 1.2rem; font-weight: 700; text-decoration: none;">
            <i class="fas fa-exclamation-triangle"></i> Low Stock Alerts
        </a>
        <a href="/bolakausa/admin/inventory-insights?tab=high-stock" class="btn <?php echo ($active_tab === 'high-stock') ? 'btn-blue' : 'btn-outline'; ?>" style="padding: 0.6rem 1.2rem; font-weight: 700; text-decoration: none;">
            <i class="fas fa-cubes"></i> High Stock Volume
        </a>
        <a href="/bolakausa/admin/inventory-insights?tab=fast-moving" class="btn <?php echo ($active_tab === 'fast-moving') ? 'btn-blue' : 'btn-outline'; ?>" style="padding: 0.6rem 1.2rem; font-weight: 700; text-decoration: none;">
            <i class="fas fa-bolt"></i> Fast Moving Items
        </a>
        <a href="/bolakausa/admin/inventory-insights?tab=slow-moving" class="btn <?php echo ($active_tab === 'slow-moving') ? 'btn-blue' : 'btn-outline'; ?>" style="padding: 0.6rem 1.2rem; font-weight: 700; text-decoration: none;">
            <i class="fas fa-snail"></i> Slow Moving Items
        </a>
        <a href="/bolakausa/admin/inventory-insights?tab=top-wholesalers" class="btn <?php echo ($active_tab === 'top-wholesalers') ? 'btn-blue' : 'btn-outline'; ?>" style="padding: 0.6rem 1.2rem; font-weight: 700; text-decoration: none;">
            <i class="fas fa-handshake"></i> Top Wholesale Buyers
        </a>
    </div>
</div>

<!-- Tab Content Panels -->
<div class="card" style="padding: 2rem; background: white; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);">
    
    <?php if ($active_tab === 'low-stock'): ?>
        <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); margin-bottom:0.5rem;"><i class="fas fa-exclamation-triangle" style="color:var(--rose);"></i> Critical Low-Stock Alerts</h3>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">Below is the list of products with 5 or fewer units remaining. Reorder from supplier immediately to prevent stock-out delays.</p>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Stock Remaining</th>
                        <th>Base Price</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($low_stock)): ?>
                        <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:3rem;">No low-stock items. Inventory is fully stocked!</td></tr>
                    <?php endif; ?>
                    <?php foreach ($low_stock as $p): ?>
                        <tr>
                            <td><strong style="color:var(--secondary);">#<?php echo $p['id']; ?></strong></td>
                            <td><strong style="color:var(--secondary);"><?php echo e($p['name']); ?></strong></td>
                            <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:0.8rem; font-weight:700; color:var(--text-muted);"><?php echo e($p['category_name']); ?></span></td>
                            <td>
                                <span style="background:rgba(244, 63, 94, 0.15); color:var(--rose); padding:4px 10px; border-radius:20px; font-size:0.75rem; font-weight:800;">
                                    <?php echo $p['stock_qty']; ?> units left
                                </span>
                            </td>
                            <td><strong style="color:var(--primary-dark);">$<?php echo number_format($p['base_price'], 2); ?></strong></td>
                            <td style="text-align:right;">
                                <a href="/bolakausa/admin/products" class="btn btn-outline" style="padding:0.4rem 0.8rem; font-size:0.75rem;"><i class="fas fa-edit"></i> Restock</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($active_tab === 'high-stock'): ?>
        <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); margin-bottom:0.5rem;"><i class="fas fa-cubes" style="color:var(--primary);"></i> High Stock Volume Items</h3>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">Below is the list of products with 100 or more units in inventory. These represent high-volume products or capital locks.</p>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Stock Remaining</th>
                        <th>Base Price</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($high_stock)): ?>
                        <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:3rem;">No high-stock items. Stock volumes are balanced.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($high_stock as $p): ?>
                        <tr>
                            <td><strong style="color:var(--secondary);">#<?php echo $p['id']; ?></strong></td>
                            <td><strong style="color:var(--secondary);"><?php echo e($p['name']); ?></strong></td>
                            <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:0.8rem; font-weight:700; color:var(--text-muted);"><?php echo e($p['category_name']); ?></span></td>
                            <td>
                                <span style="background:rgba(16, 185, 129, 0.15); color:var(--primary); padding:4px 10px; border-radius:20px; font-size:0.75rem; font-weight:800;">
                                    <?php echo $p['stock_qty']; ?> units
                                </span>
                            </td>
                            <td><strong style="color:var(--primary-dark);">$<?php echo number_format($p['base_price'], 2); ?></strong></td>
                            <td style="text-align:right;">
                                <a href="/bolakausa/admin/promotions" class="btn btn-outline" style="padding:0.4rem 0.8rem; font-size:0.75rem; color:#f59e0b; border-color:#f59e0b;"><i class="fas fa-tag"></i> Create Promotion</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($active_tab === 'fast-moving'): ?>
        <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); margin-bottom:0.5rem;"><i class="fas fa-bolt" style="color:#d97706;"></i> Fast Moving Products</h3>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">Top 10 selling products sorted by total quantity sold. These products generate the highest inventory turnover.</p>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Total Units Sold</th>
                        <?php if ($user_role === 'admin'): ?><th>Gross Revenue</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fast_moving)): ?>
                        <tr><td colspan="<?php echo $user_role === 'admin' ? 5 : 4; ?>" style="text-align:center; color:var(--text-muted); padding:3rem;">No sales recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($fast_moving as $p): ?>
                        <tr>
                            <td><strong style="color:var(--secondary);">#<?php echo $p['id']; ?></strong></td>
                            <td><strong style="color:var(--secondary);"><?php echo e($p['name']); ?></strong></td>
                            <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:0.8rem; font-weight:700; color:var(--text-muted);"><?php echo e($p['category_name']); ?></span></td>
                            <td>
                                <strong style="color:var(--primary-dark);"><?php echo number_format($p['total_sold']); ?> units</strong>
                            </td>
                            <?php if ($user_role === 'admin'): ?>
                                <td><strong style="color:var(--primary);">$<?php echo number_format($p['total_revenue'], 2); ?></strong></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($active_tab === 'slow-moving'): ?>
        <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); margin-bottom:0.5rem;"><i class="fas fa-snail" style="color:var(--text-muted);"></i> Slow Moving Products</h3>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">Top 10 products with lowest turnover, sorted by units sold. High stock levels in these items indicate dead capital.</p>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Available Stock</th>
                        <th>Total Units Sold</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($slow_moving)): ?>
                        <tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:3rem;">No products registered.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($slow_moving as $p): ?>
                        <tr>
                            <td><strong style="color:var(--secondary);">#<?php echo $p['id']; ?></strong></td>
                            <td><strong style="color:var(--secondary);"><?php echo e($p['name']); ?></strong></td>
                            <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:0.8rem; font-weight:700; color:var(--text-muted);"><?php echo e($p['category_name']); ?></span></td>
                            <td><strong style="color:var(--secondary);"><?php echo $p['stock_qty']; ?> units</strong></td>
                            <td>
                                <span style="background:rgba(15,23,42,0.05); color:var(--text-muted); padding:4px 10px; border-radius:20px; font-size:0.75rem; font-weight:800;">
                                    <?php echo number_format($p['total_sold']); ?> sold
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($active_tab === 'top-wholesalers'): ?>
        <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); margin-bottom:0.5rem;"><i class="fas fa-handshake" style="color:var(--primary-dark);"></i> Top Wholesale Buyers</h3>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">Top wholesaler partners sorted by volume of orders and business transactions.</p>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Client</th>
                        <th>Account Name</th>
                        <th>Orders Placed</th>
                        <?php if ($user_role === 'admin'): ?><th>Accumulated Spend</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_wholesalers)): ?>
                        <tr><td colspan="<?php echo $user_role === 'admin' ? 5 : 4; ?>" style="text-align:center; color:var(--text-muted); padding:3rem;">No active wholesalers found.</td></tr>
                    <?php endif; ?>
                    <?php $rank = 1; foreach ($top_wholesalers as $w): ?>
                        <tr>
                            <td><strong style="color:var(--secondary); font-size:1.1rem;">#<?php echo $rank++; ?></strong></td>
                            <td><strong style="color:var(--secondary);">@<?php echo e($w['username']); ?></strong></td>
                            <td style="color:var(--text-main); font-weight:600;"><?php echo e($w['full_name']); ?></td>
                            <td>
                                <span style="background:rgba(59, 130, 246, 0.08); color:#3b82f6; padding:4px 10px; border-radius:20px; font-size:0.75rem; font-weight:800;">
                                    <?php echo $w['order_count']; ?> orders
                                </span>
                            </td>
                            <?php if ($user_role === 'admin'): ?>
                                <td><strong style="color:var(--primary); font-size:1.05rem;">$<?php echo number_format($w['total_spend'], 2); ?></strong></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
</div>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Return to Dashboard</a></p>
