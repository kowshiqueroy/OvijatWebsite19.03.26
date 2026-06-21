<?php
/**
 * Admin Wallet Management Module
 */
restrict_to(['admin', 'manager']);

$success = '';
$error = '';
$view_history_user_id = isset($_GET['history']) ? (int)$_GET['history'] : null;

// Note: Incoming Bank/Stripe payment approvals have been moved to the dedicated Payment Approvals view.

// Handle Manual Credit/Debit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_adjustment'])) {
    $user_id = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];
    $type = $_POST['type']; // 'credit' or 'debit'
    $desc = $_POST['description'] ?? 'Manual adjustment by admin';

    if ($user_id && $amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $type, $amount, $desc])) {
            $success = "Wallet successfully " . ($type === 'credit' ? 'credited' : 'debited') . ".";
            log_action($pdo, $_SESSION['user_id'], "Manual Wallet Adjustment ($type)", "User ID: $user_id, Amount: $amount");
        }
    }
}

// Date Range Filter
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$show_all = isset($_GET['show_all']);

if ($view_history_user_id) {
    // Get user info
    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute([$view_history_user_id]);
    $history_user = $stmt->fetch();
    $history_user_name = $history_user['full_name'] ?: $history_user['username'];

    // Get transactions
    $q_user_tx = "SELECT * FROM wallet_transactions WHERE user_id = ?";
    $user_tx_params = [$view_history_user_id];
    if (!$show_all) {
        $q_user_tx .= " AND DATE(created_at) BETWEEN ? AND ?";
        $user_tx_params[] = $date_from;
        $user_tx_params[] = $date_to;
    }
    $q_user_tx .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($q_user_tx);
    $stmt->execute($user_tx_params);
    $user_history = $stmt->fetchAll();
}

// Fetch Pending Top-ups Count for notifications/links
$pending_topups_count = (int)$pdo->query("SELECT COUNT(*) FROM wallet_topups WHERE status = 'pending'")->fetchColumn();

// Fetch Users for Manual Adjustment and Balances List
$users = $pdo->query("SELECT id, username, full_name, status FROM users WHERE role = 'wholesale_user' ORDER BY username ASC")->fetchAll();

// Calculate Balances for all users
$user_balances = [];
$stmt = $pdo->query("SELECT user_id, SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions GROUP BY user_id");
while ($row = $stmt->fetch()) {
    $user_balances[$row['user_id']] = (float)$row['balance'];
}

// Fetch all transactions for Master Ledger
$q_all_tx = "SELECT t.*, u.username, u.full_name FROM wallet_transactions t JOIN users u ON t.user_id = u.id";
$tx_params = [];
if (!$show_all) {
    $q_all_tx .= " WHERE DATE(t.created_at) BETWEEN ? AND ?";
    $tx_params[] = $date_from;
    $tx_params[] = $date_to;
}
$q_all_tx .= " ORDER BY t.created_at DESC";
$stmt_all_tx = $pdo->prepare($q_all_tx);
$stmt_all_tx->execute($tx_params);
$master_transactions = $stmt_all_tx->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-wallet" style="color: var(--primary);"></i>
    Partner Wallet Management
</div>

<?php if ($success): ?>
    <div style="background: rgba(16, 185, 129, 0.1); color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2);">
        <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background: rgba(244, 63, 94, 0.1); color: var(--accent); padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.2);">
        <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
    </div>
<?php endif; ?>

<!-- Date filter -->
<div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <form method="GET" style="display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="url" value="admin/wallet">
        <?php if ($view_history_user_id): ?>
            <input type="hidden" name="history" value="<?php echo $view_history_user_id; ?>">
        <?php endif; ?>
        <div class="form-group" style="margin: 0; flex: 1; min-width: 180px;">
            <label style="font-weight: 800; font-size: 0.8rem; margin-bottom: 0.35rem; color: var(--secondary); display: block;">From Date</label>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>" style="padding: 0.5rem; border-radius: 8px; font-size: 0.85rem; border: 1px solid #cbd5e1; width: 100%;">
        </div>
        <div class="form-group" style="margin: 0; flex: 1; min-width: 180px;">
            <label style="font-weight: 800; font-size: 0.8rem; margin-bottom: 0.35rem; color: var(--secondary); display: block;">To Date</label>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>" style="padding: 0.5rem; border-radius: 8px; font-size: 0.85rem; border: 1px solid #cbd5e1; width: 100%;">
        </div>
        <div style="display: flex; gap: 0.5rem; margin-top: auto;">
            <button type="submit" class="btn btn-blue" style="padding: 0.6rem 1.25rem;"><i class="fas fa-filter"></i> Filter</button>
            <a href="/bolakausa/admin/wallet<?php echo $view_history_user_id ? '?history='.$view_history_user_id.'&' : '?'; ?>show_all=1" class="btn btn-outline" style="padding: 0.6rem 1.25rem; font-weight: 700; border-radius: 8px; text-decoration: none;">Show All</a>
        </div>
    </form>
</div>

<!-- TAB NAVIGATION -->
<div style="display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid var(--glass-border); padding-bottom: 1rem; flex-wrap: wrap;">
    <button onclick="switchTab('tab-balances')" id="btn-balances" class="btn btn-blue" style="background: var(--primary);"><i class="fas fa-users"></i> Partner Balances</button>
    <button onclick="switchTab('tab-ledger')" id="btn-ledger" class="btn btn-blue" style="background: rgba(15,23,42,0.05); color: var(--secondary); box-shadow: none;"><i class="fas fa-history"></i> Master Finance Ledger</button>
    <button onclick="switchTab('tab-adjustments')" id="btn-adjustments" class="btn btn-blue" style="background: rgba(15,23,42,0.05); color: var(--secondary); box-shadow: none;"><i class="fas fa-sliders-h"></i> Manual Adjustments</button>
</div>

<?php if ($view_history_user_id): ?>
    <!-- VIEWING SPECIFIC USER HISTORY -->
    <div class="card" style="margin-bottom: 3rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-weight: 800; color: var(--secondary); margin: 0;"><i class="fas fa-history"></i> Ledger: <?php echo e($history_user_name); ?></h3>
            <a href="/bolakausa/admin/wallet" class="btn btn-blue" style="padding: 0.5rem 1rem;"><i class="fas fa-times"></i> Close Ledger</a>
        </div>
        
        <div class="table-wrap" style="margin: 0;">
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Description</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$user_history): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No wallet activity found for this user.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($user_history as $txn): ?>
                    <tr>
                        <td><small style="color: var(--text-muted);"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y H:i', strtotime($txn['created_at'])); ?></small></td>
                        <td style="color: var(--secondary); font-weight: 600;"><?php echo e($txn['description']); ?></td>
                        <td style="text-align: right;">
                            <?php if ($txn['type'] === 'credit'): ?>
                                <strong style="color: var(--primary);">+$<?php echo number_format($txn['amount'], 2); ?></strong>
                            <?php else: ?>
                                <strong style="color: var(--accent);">-$<?php echo number_format($txn['amount'], 2); ?></strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- TAB CONTENT CONTAINERS -->

<!-- Pending Requests Notification (Admins Only) -->
<?php if ($pending_topups_count > 0 && $_SESSION['user_role'] === 'admin'): ?>
    <div style="background: rgba(59, 130, 246, 0.08); color: var(--primary-dark); padding: 1.25rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(59, 130, 246, 0.15); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <strong style="font-weight: 800; font-size: 0.95rem; color: var(--secondary);"><i class="fas fa-info-circle" style="color: var(--primary);"></i> Inbound Payments Awaiting Action</strong>
            <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--text-muted);">There are currently <?php echo $pending_topups_count; ?> pending bank/stripe payment verifications needing administrator confirmation.</p>
        </div>
        <a href="/bolakausa/admin/payments" class="btn btn-blue" style="padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.85rem; text-decoration: none;"><i class="fas fa-credit-card"></i> Open Payment Approvals</a>
    </div>
<?php endif; ?>

<!-- User Balances Tab -->
<div id="tab-balances" class="tab-content" style="display: block;">
    <h3 style="font-weight: 800; color: var(--secondary); margin-bottom: 1rem;"><i class="fas fa-users" style="color: #3b82f6;"></i> Wholesale Partner Balances</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Partner</th>
                    <th style="text-align: right;">Current Balance</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <?php $bal = $user_balances[$u['id']] ?? 0.00; ?>
                <tr>
                    <td>
                        <strong style="color: var(--secondary);"><?php echo e($u['full_name'] ?: $u['username']); ?></strong>
                        <?php if ($u['status'] !== 'active'): ?>
                            <span style="background: rgba(244,63,94,0.1); color: var(--accent); padding: 2px 6px; border-radius: 8px; font-size: 0.65rem; text-transform: uppercase; font-weight: 800; margin-left: 0.5rem;">Inactive</span>
                        <?php endif; ?>
                        <br><small style="color: var(--text-muted);">@<?php echo e($u['username']); ?></small>
                    </td>
                    <td style="text-align: right;">
                        <strong style="font-size: 1.1rem; color: <?php echo ($bal >= 0) ? 'var(--primary)' : 'var(--accent)'; ?>;">
                            $<?php echo number_format($bal, 2); ?>
                        </strong>
                    </td>
                    <td style="text-align: right;">
                        <a href="/bolakausa/admin/wallet?history=<?php echo $u['id']; ?>#tab-balances" class="btn btn-blue" style="padding: 0.5rem 1rem; font-size: 0.8rem;"><i class="fas fa-history"></i> Ledger</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Master Ledger Tab -->
<div id="tab-ledger" class="tab-content" style="display: none;">
    <h3 style="font-weight: 800; color: var(--secondary); margin-bottom: 1rem;"><i class="fas fa-history" style="color: var(--primary);"></i> Master Financial Ledger</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Partner / User</th>
                    <th>Description</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$master_transactions): ?>
                    <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 3rem;">No transactions logged in this date range. <a href="/bolakausa/admin/wallet?show_all=1" style="color:var(--primary); font-weight:700;">Show All History</a></td></tr>
                <?php endif; ?>
                <?php foreach ($master_transactions as $txn): ?>
                <tr>
                    <td><small style="color: var(--text-muted); font-weight: 600;"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y H:i', strtotime($txn['created_at'])); ?></small></td>
                    <td><strong><?php echo e($txn['full_name'] ?: $txn['username']); ?></strong> <small style="color: var(--text-muted);">@<?php echo e($txn['username']); ?></small></td>
                    <td style="color: var(--secondary); font-weight: 600;"><?php echo e($txn['description']); ?></td>
                    <td style="text-align: right;">
                        <?php if ($txn['type'] === 'credit'): ?>
                            <strong style="color: var(--primary);">+$<?php echo number_format($txn['amount'], 2); ?></strong>
                        <?php else: ?>
                            <strong style="color: var(--rose);">-$<?php echo number_format($txn['amount'], 2); ?></strong>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Manual Adjustments Tab -->
<div id="tab-adjustments" class="tab-content" style="display: none;">
    <div class="card" style="margin-bottom: 2rem;">
        <h3 style="font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem;"><i class="fas fa-sliders-h" style="color: #3b82f6;"></i> Manual Adjustment (Credit/Debit)</h3>
        <form method="POST" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                <label>Select User</label>
                <select name="user_id" required>
                    <option value="">-- Choose User --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo e($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
                <label>Action</label>
                <select name="type">
                    <option value="credit">Credit (+)</option>
                    <option value="debit">Debit (-)</option>
                </select>
            </div>
            <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
                <label>Amount ($)</label>
                <input type="number" step="0.01" name="amount" placeholder="0.00" required>
            </div>
            <div class="form-group" style="margin: 0; flex: 2; min-width: 250px;">
                <label>Reason / Description</label>
                <input type="text" name="description" placeholder="e.g. Refund for damaged goods">
            </div>
            <button type="submit" name="manual_adjustment" class="btn btn-blue" style="padding: 1.15rem 1.5rem;"><i class="fas fa-bolt"></i> Process</button>
        </form>
    </div>
</div>

<script>
function switchTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    
    // Reset all buttons to inactive style
    document.querySelectorAll('[id^="btn-"]').forEach(btn => {
        btn.style.background = 'rgba(15,23,42,0.05)';
        btn.style.color = 'var(--secondary)';
        btn.style.boxShadow = 'none';
    });

    // Show selected tab
    document.getElementById(tabId).style.display = 'block';
    
    // Highlight active button
    const activeBtn = document.getElementById('btn-' + tabId.replace('tab-', ''));
    if (activeBtn) {
        activeBtn.style.background = 'var(--primary)';
        activeBtn.style.color = 'white';
    }
}

// Auto-switch if URL hash is present (useful for Ledger returns)
if (window.location.hash) {
    const hash = window.location.hash.substring(1);
    if (document.getElementById(hash)) {
        switchTab(hash);
    }
}
</script>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
