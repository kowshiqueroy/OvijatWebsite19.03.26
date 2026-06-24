<?php
restrict_to(['wholesale_user', 'executive']);

$user_id = $_SESSION['user_id'];
$now = date('Y-m-d H:i:s');

// Dashboard summary stats
$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_spend FROM orders WHERE user_id = ?");
$stmt->execute([$user_id]);
$order_stats = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('Pending Payment', 'Confirmed', 'Processing')");
$stmt->execute([$user_id]);
$pending_orders = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet_balance = $stmt->fetchColumn() ?? 0.00;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'Delivered'");
$stmt->execute([$user_id]);
$delivered_count = $stmt->fetchColumn();

// Recent orders
$stmt = $pdo->prepare("SELECT id, status, total_amount, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_orders = $stmt->fetchAll();

// Monthly spend (last 6 months)
$stmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as total FROM orders WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY month ORDER BY month ASC");
$stmt->execute([$user_id]);
$monthly_data = $stmt->fetchAll();

// Active promotions
$stmt = $pdo->prepare("SELECT * FROM promotions WHERE is_active = 1 AND (start_date IS NULL OR start_date <= ?) AND (end_date IS NULL OR end_date >= ?) AND (target_wholesalers = 'all' OR target_wholesalers = ?)");
$stmt->execute([$now, $now, $_SESSION['user_role'] ?? 'wholesale_user']);
$promotions = $stmt->fetchAll();

// Pay-later outstanding
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = ? AND payment_method = 'Pay Later' AND status NOT IN ('Cancelled', 'Rejected')");
$stmt->execute([$user_id]);
$pay_later_outstanding = $stmt->fetchColumn();
?>

<!-- Dashboard Welcome -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 0.5rem;">
    <div>
        <h1 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.65rem; font-weight: 800; color: var(--secondary); margin: 0;">
            Welcome back, <?php echo e(explode(' ', $_SESSION['full_name'] ?? $_SESSION['username'])[0]); ?>!
        </h1>
        <p style="color: var(--text-muted); margin: 0.25rem 0 0 0; font-size: 0.9rem;">
            <i class="far fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?>
        </p>
    </div>
    <a href="/bolakausa/catalog" class="btn btn-green" style="padding: 0.75rem 1.5rem; border-radius: 10px;">
        <i class="fas fa-store"></i> Browse Catalog
    </a>
</div>

<!-- Summary Cards -->
<div class="grid-stack-mobile" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem;">
    <div class="card" style="padding: 1.5rem; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -10px; right: -10px; font-size: 3.5rem; opacity: 0.06; color: var(--primary);"><i class="fas fa-shopping-bag"></i></div>
        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Total Orders</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--secondary); margin-top: 0.25rem;"><?php echo $order_stats['total_orders']; ?></div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;"><span style="color: var(--primary); font-weight: 700;"><?php echo $delivered_count; ?></span> delivered</div>
    </div>
    <div class="card" style="padding: 1.5rem; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -10px; right: -10px; font-size: 3.5rem; opacity: 0.06; color: var(--primary);"><i class="fas fa-dollar-sign"></i></div>
        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Total Spend</div>
        <div style="font-size: 1.6rem; font-weight: 800; color: var(--secondary); margin-top: 0.25rem;">$<?php echo number_format($order_stats['total_spend'], 2); ?></div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Lifetime value</div>
    </div>
    <div class="card" style="padding: 1.5rem; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -10px; right: -10px; font-size: 3.5rem; opacity: 0.06; color: var(--primary);"><i class="fas fa-clock"></i></div>
        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Pending Orders</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--secondary); margin-top: 0.25rem;"><?php echo $pending_orders; ?></div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Awaiting fulfillment</div>
    </div>
    <div class="card" style="padding: 1.5rem; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -10px; right: -10px; font-size: 3.5rem; opacity: 0.06; color: var(--primary);"><i class="fas fa-wallet"></i></div>
        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Wallet</div>
        <div style="font-size: 1.6rem; font-weight: 800; color: var(--secondary); margin-top: 0.25rem;">$<?php echo number_format($wallet_balance, 2); ?></div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            <a href="/bolakausa/wallet" style="color: var(--primary); font-weight: 700; text-decoration: none;">Manage <i class="fas fa-arrow-right" style="font-size: 0.65rem;"></i></a>
        </div>
    </div>
    <div class="card" style="padding: 1.5rem; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -10px; right: -10px; font-size: 3.5rem; opacity: 0.06; color: var(--primary);"><i class="fas fa-receipt"></i></div>
        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Pay Later</div>
        <div style="font-size: 1.4rem; font-weight: 800; color: var(--secondary); margin-top: 0.25rem;">$<?php echo number_format($pay_later_outstanding, 2); ?></div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
            <a href="/bolakausa/pay-later" style="color: var(--primary); font-weight: 700; text-decoration: none;">View Invoices <i class="fas fa-arrow-right" style="font-size: 0.65rem;"></i></a>
        </div>
    </div>
</div>

<div class="grid-stack-mobile" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 2rem; margin-bottom: 2.5rem; align-items: start;">
    <!-- Monthly Spend Graph -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.05rem; font-weight: 800; color: var(--secondary); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-chart-bar" style="color: var(--primary);"></i> Monthly Spend (Last 6 Months)
        </h3>
        <?php
        $months = [];
        $values = [];
        $max_val = 0;
        foreach ($monthly_data as $m) {
            $months[] = date('M', strtotime($m['month'] . '-01'));
            $values[] = (float)$m['total'];
            if ((float)$m['total'] > $max_val) $max_val = (float)$m['total'];
        }
        // Ensure we show at least 6 months even if no data
        $labels = [];
        $data_points = [];
        for ($i = 5; $i >= 0; $i--) {
            $label = date('M', strtotime("-{$i} months"));
            $labels[] = $label;
            $key = date('Y-m', strtotime("-{$i} months"));
            $found = false;
            foreach ($monthly_data as $m) {
                if ($m['month'] === $key) {
                    $data_points[] = (float)$m['total'];
                    $found = true;
                    break;
                }
            }
            if (!$found) $data_points[] = 0;
        }
        if ($max_val == 0) $max_val = 1;
        ?>
        <div style="display: flex; align-items: flex-end; gap: 0.75rem; height: 140px; padding: 0.5rem 0;">
            <?php foreach ($data_points as $i => $val): ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end;">
                    <div style="width: 100%; max-width: 48px; background: linear-gradient(180deg, var(--primary), var(--primary-dark)); border-radius: 6px 6px 0 0; height: <?php echo max(4, ($val / $max_val) * 120); ?>px; transition: height 0.3s; min-height: 4px;"></div>
                    <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 0.35rem; font-weight: 700;"><?php echo $labels[$i]; ?></div>
                    <div style="font-size: 0.6rem; color: var(--text-muted); font-weight: 600;">$<?php echo number_format($val, 0); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Active Promotions -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.05rem; font-weight: 800; color: var(--secondary); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-bullhorn" style="color: var(--accent);"></i> Active Promotions
        </h3>
        <?php if (empty($promotions)): ?>
            <div style="text-align: center; padding: 2rem 0; color: var(--text-muted); font-size: 0.9rem;">
                <i class="fas fa-tag" style="font-size: 2rem; opacity: 0.2; margin-bottom: 0.75rem; display: block;"></i>
                No promotions at this time.
            </div>
        <?php else: ?>
            <?php foreach ($promotions as $p): ?>
                <div style="padding: 1rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.04), rgba(16, 185, 129, 0.04)); border: 1px solid rgba(99, 102, 241, 0.1); border-radius: 10px; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                        <i class="fas fa-bolt" style="color: var(--accent); font-size: 0.9rem;"></i>
                        <strong style="font-size: 0.9rem; color: var(--secondary);"><?php echo e($p['title']); ?></strong>
                    </div>
                    <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted); line-height: 1.4;"><?php echo nl2br(e($p['message'])); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Orders & Quick Actions -->
<div class="grid-stack-mobile" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 2rem; align-items: start;">
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.05rem; font-weight: 800; color: var(--secondary); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-history" style="color: var(--primary);"></i> Recent Orders
            </h3>
            <a href="/bolakausa/orders" class="btn btn-outline" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; border-radius: 6px; text-decoration: none;">View All</a>
        </div>
        <?php if (empty($recent_orders)): ?>
            <div style="text-align: center; padding: 3rem 0; color: var(--text-muted);">
                <i class="fas fa-box-open" style="font-size: 2.5rem; opacity: 0.15; margin-bottom: 1rem; display: block;"></i>
                <p style="font-size: 0.9rem; margin: 0;">No orders yet. Start with the catalog!</p>
                <a href="/bolakausa/catalog" class="btn btn-green" style="margin-top: 1rem; border-radius: 8px;"><i class="fas fa-store"></i> Browse Catalog</a>
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-light);">
                        <th style="text-align: left; padding: 0.5rem 0.5rem 0.5rem 0; font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Order</th>
                        <th style="text-align: left; padding: 0.5rem; font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Status</th>
                        <th style="text-align: right; padding: 0.5rem 0 0.5rem 0.5rem; font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $o): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.75rem 0.5rem 0.75rem 0;">
                            <a href="/bolakausa/orders" style="font-weight: 800; color: var(--secondary); text-decoration: none; font-size: 0.85rem;">#<?php echo $o['id']; ?></a>
                            <div style="font-size: 0.65rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($o['created_at'])); ?></div>
                        </td>
                        <td style="padding: 0.75rem 0.5rem;">
                            <?php
                                $bg = 'rgba(15,23,42,0.05)'; $color = 'var(--secondary)';
                                if ($o['status'] === 'Pending Payment') { $bg = 'rgba(244, 63, 94, 0.08)'; $color = 'var(--rose)'; }
                                if ($o['status'] === 'Delivered') { $bg = 'rgba(16, 185, 129, 0.08)'; $color = 'var(--primary)'; }
                                if ($o['status'] === 'Processing') { $bg = 'rgba(59, 130, 246, 0.08)'; $color = '#3b82f6'; }
                            ?>
                            <span style="padding: 2px 8px; border-radius: 10px; font-size: 0.65rem; font-weight: 800; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; text-transform: uppercase; border: 1px solid rgba(0,0,0,0.02);"><?php echo $o['status']; ?></span>
                        </td>
                        <td style="padding: 0.75rem 0 0.75rem 0.5rem; text-align: right; font-weight: 800; color: var(--secondary); font-size: 0.9rem;">$<?php echo number_format($o['total_amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.05rem; font-weight: 800; color: var(--secondary); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-bolt" style="color: var(--accent);"></i> Quick Actions
        </h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
            <a href="/bolakausa/catalog" class="btn btn-green" style="padding: 1rem; border-radius: 10px; text-decoration: none; text-align: center; flex-direction: column; gap: 0.35rem; height: auto;">
                <i class="fas fa-store" style="font-size: 1.2rem;"></i>
                <span style="font-size: 0.8rem;">Browse Catalog</span>
            </a>
            <a href="/bolakausa/orders" class="btn btn-blue" style="padding: 1rem; border-radius: 10px; text-decoration: none; text-align: center; flex-direction: column; gap: 0.35rem; height: auto;">
                <i class="fas fa-box-open" style="font-size: 1.2rem;"></i>
                <span style="font-size: 0.8rem;">My Orders</span>
            </a>
            <a href="/bolakausa/wallet" class="btn btn-outline" style="padding: 1rem; border-radius: 10px; text-decoration: none; text-align: center; flex-direction: column; gap: 0.35rem; height: auto; font-weight: 700;">
                <i class="fas fa-wallet" style="font-size: 1.2rem;"></i>
                <span style="font-size: 0.8rem;">Wallet</span>
            </a>
            <a href="/bolakausa/pay-later" class="btn btn-outline" style="padding: 1rem; border-radius: 10px; text-decoration: none; text-align: center; flex-direction: column; gap: 0.35rem; height: auto; font-weight: 700;">
                <i class="fas fa-receipt" style="font-size: 1.2rem;"></i>
                <span style="font-size: 0.8rem;">Pay Later</span>
            </a>
            <a href="/bolakausa/chats" class="btn btn-outline" style="padding: 1rem; border-radius: 10px; text-decoration: none; text-align: center; flex-direction: column; gap: 0.35rem; height: auto; font-weight: 700;">
                <i class="fas fa-comments" style="font-size: 1.2rem;"></i>
                <span style="font-size: 0.8rem;">Support Chat</span>
            </a>
            <a href="/bolakausa/account" class="btn btn-outline" style="padding: 1rem; border-radius: 10px; text-decoration: none; text-align: center; flex-direction: column; gap: 0.35rem; height: auto; font-weight: 700;">
                <i class="fas fa-user-cog" style="font-size: 1.2rem;"></i>
                <span style="font-size: 0.8rem;">My Account</span>
            </a>
        </div>
    </div>
</div>
