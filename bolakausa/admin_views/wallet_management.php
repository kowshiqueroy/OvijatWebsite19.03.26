<?php
/**
 * Admin Wallet Management Module
 */
restrict_to(['admin', 'manager']);

$success = '';
$error = '';

// Handle Top-up Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_topup'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action']; // 'approved' or 'rejected'
    $notes = $_POST['admin_notes'] ?? '';

    // Fetch the request
    $stmt = $pdo->prepare("SELECT * FROM wallet_topups WHERE id = ? AND status = 'pending'");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if ($request) {
        $pdo->beginTransaction();
        try {
            // Update request status
            $stmt = $pdo->prepare("UPDATE wallet_topups SET status = ?, admin_notes = ?, processed_at = NOW() WHERE id = ?");
            $stmt->execute([$action, $notes, $request_id]);

            if ($action === 'approved') {
                // Add balance to wallet
                $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
                $stmt->execute([$request['user_id'], $request['amount'], "Wallet Top-up: Approved Request #$request_id"]);
                
                log_action($pdo, $_SESSION['user_id'], "Wallet Top-up Approved", "User ID: {$request['user_id']}, Amount: {$request['amount']}");
            } else {
                log_action($pdo, $_SESSION['user_id'], "Wallet Top-up Rejected", "User ID: {$request['user_id']}, Request ID: $request_id");
            }

            $pdo->commit();
            $success = "Top-up request has been " . $action;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to process request: " . $e->getMessage();
        }
    }
}

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

// Check if viewing a specific user's history
$view_history_user_id = isset($_GET['history']) ? (int)$_GET['history'] : null;
$user_history = [];
$history_user_name = '';

if ($view_history_user_id) {
    // Get user info
    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute([$view_history_user_id]);
    $history_user = $stmt->fetch();
    $history_user_name = $history_user['full_name'] ?: $history_user['username'];

    // Get transactions
    $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$view_history_user_id]);
    $user_history = $stmt->fetchAll();
}

// Fetch Pending Top-ups
$pending_topups = $pdo->query("SELECT t.*, u.username FROM wallet_topups t JOIN users u ON t.user_id = u.id WHERE t.status = 'pending' ORDER BY t.created_at DESC")->fetchAll();

// Fetch Users for Manual Adjustment and Balances List
$users = $pdo->query("SELECT id, username, full_name, status FROM users WHERE role = 'wholesale_user' ORDER BY username ASC")->fetchAll();

// Calculate Balances for all users
$user_balances = [];
$stmt = $pdo->query("SELECT user_id, SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions GROUP BY user_id");
while ($row = $stmt->fetch()) {
    $user_balances[$row['user_id']] = (float)$row['balance'];
}
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

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2.5rem; margin-bottom: 3rem;">
    <!-- LEFT COLUMN: Pending Requests -->
    <div style="min-width: 0;">
        <h3 style="font-weight: 800; color: var(--secondary); margin-bottom: 1rem;"><i class="fas fa-hand-holding-usd" style="color: var(--primary);"></i> Pending Top-up Requests</h3>
        <div class="table-wrap" style="margin-bottom: 0;">
            <table>
                <thead>
                    <tr>
                        <th>User & Amount</th>
                        <th>Details</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$pending_topups): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No pending top-up requests.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pending_topups as $t): ?>
                    <tr>
                        <td>
                            <strong style="color: var(--secondary);"><?php echo e($t['username']); ?></strong><br>
                            <strong style="color: var(--primary); font-size: 1.1rem;">$<?php echo number_format($t['amount'], 2); ?></strong>
                        </td>
                        <td>
                            <small style="color: var(--text-muted);">Method:</small> <strong><?php echo e($t['payment_method']); ?></strong><br>
                            <small style="color: var(--text-muted);">TX ID:</small> <?php echo e($t['transaction_id']); ?><br>
                            <?php if ($t['proof_image']): ?>
                                <a href="/bolakausa/public/uploads/proofs/<?php echo e($t['proof_image']); ?>" target="_blank" style="font-size: 0.8rem; color: var(--primary); font-weight: 700;"><i class="fas fa-image"></i> View Proof</a>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.8rem;">No Proof</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:flex; flex-direction: column; gap: 0.5rem;">
                                <input type="hidden" name="request_id" value="<?php echo $t['id']; ?>">
                                <div style="display: flex; gap: 0.5rem;">
                                    <select name="action" style="padding: 0.5rem; font-size: 0.8rem;">
                                        <option value="approved">Approve</option>
                                        <option value="rejected">Reject</option>
                                    </select>
                                    <button type="submit" name="process_topup" value="1" class="btn btn-green" style="padding: 0.5rem 1rem; font-size: 0.8rem;">Apply</button>
                                </div>
                                <input type="text" name="admin_notes" placeholder="Admin notes..." style="padding: 0.5rem; font-size: 0.8rem;">
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- RIGHT COLUMN: User Balances -->
    <div>
        <h3 style="font-weight: 800; color: var(--secondary); margin-bottom: 1rem;"><i class="fas fa-users" style="color: #3b82f6;"></i> Wholesale Partner Balances</h3>
        <div class="table-wrap" style="margin-bottom: 0;">
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
                            <a href="/bolakausa/admin/wallet?history=<?php echo $u['id']; ?>" class="btn btn-blue" style="padding: 0.5rem 1rem; font-size: 0.8rem;"><i class="fas fa-history"></i> Ledger</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

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

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
