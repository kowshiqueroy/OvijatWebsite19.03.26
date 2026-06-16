<?php
/**
 * Wholesale User Wallet Dashboard
 */
restrict_to(['wholesale_user', 'admin', 'manager']);

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Top-up Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_topup'])) {
    $amount = (float)$_POST['amount'];
    $payment_method = trim($_POST['payment_method'] ?? '');
    $transaction_id = trim($_POST['transaction_id'] ?? '');

    if ($amount > 0 && $payment_method && $transaction_id) {
        $proof_image = '';
        
        // Handle Proof Upload
        if (!empty($_FILES['proof_image']['name'])) {
            $target_dir = "public/uploads/proofs/";
            $filename = time() . "_" . basename($_FILES["proof_image"]["name"]);
            $target_file = $target_dir . $filename;
            
            if (move_uploaded_file($_FILES["proof_image"]["tmp_name"], $target_file)) {
                $proof_image = $filename;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO wallet_topups (user_id, amount, payment_method, transaction_id, proof_image) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $amount, $payment_method, $transaction_id, $proof_image])) {
            $success = "Top-up request submitted. An admin will review it shortly.";
        } else {
            $error = "Failed to submit request.";
        }
    } else {
        $error = "Please fill in all required fields and provide a valid amount.";
    }
}

// Fetch Wallet Balance
$stmt = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet_balance = (float)($stmt->fetch()['balance'] ?? 0);

// Fetch Transaction History
$stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

// Fetch Top-up History
$stmt = $pdo->prepare("SELECT * FROM wallet_topups WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$topups = $stmt->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-wallet" style="color: var(--primary);"></i>
    Digital Wallet
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

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2.5rem; margin-bottom: 3rem;">
    <!-- Balance & Top-up Request Form -->
    <div style="min-width: 0;">
        <div class="card" style="margin-bottom: 2rem; text-align: center; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(15, 23, 42, 0.05) 100%);">
            <h3 style="color: var(--text-muted); font-size: 0.9375rem; font-weight: 700; text-transform: uppercase;">Available Balance</h3>
            <div style="font-size: 3.5rem; font-weight: 900; color: var(--secondary); margin-top: 0.5rem;">
                $<?php echo number_format($wallet_balance, 2); ?>
            </div>
        </div>

        <div class="card">
            <h3 style="font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem;">Request Top-up</h3>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1.5rem;">Transfer funds to our corporate bank and submit your receipt here for wallet credit.</p>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Amount Transferred ($) *</label>
                    <input type="number" step="0.01" name="amount" placeholder="e.g. 500.00" required>
                </div>
                <div class="form-group">
                    <label>Payment Method *</label>
                    <select name="payment_method" required>
                        <option value="">-- Select Method --</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Wire Transfer">Wire Transfer</option>
                        <option value="Cash Deposit">Cash Deposit</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Transaction / Reference ID *</label>
                    <input type="text" name="transaction_id" placeholder="e.g. TXN-987654321" required>
                </div>
                <div class="form-group">
                    <label>Upload Proof (Receipt/Screenshot)</label>
                    <input type="file" name="proof_image" accept="image/*" style="padding: 0.5rem;">
                </div>
                <button type="submit" name="request_topup" class="btn btn-blue" style="width: 100%; justify-content: center; margin-top: 1rem;">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </form>
        </div>
    </div>

    <!-- Transaction History -->
    <div>
        <div class="card">
            <h3 style="font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem;"><i class="fas fa-exchange-alt"></i> Transaction Ledger</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$transactions): ?>
                            <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No wallet activity found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($transactions as $txn): ?>
                        <tr>
                            <td><small style="color: var(--text-muted);"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($txn['created_at'])); ?></small></td>
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
            
            <h3 style="font-weight: 800; color: var(--secondary); margin-top: 3rem; margin-bottom: 1.5rem;"><i class="fas fa-file-invoice-dollar"></i> Top-up Requests</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Ref ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$topups): ?>
                            <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No top-up requests found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($topups as $t): ?>
                        <tr>
                            <td><small style="color: var(--text-muted);"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($t['created_at'])); ?></small></td>
                            <td style="color: var(--secondary); font-weight: 600;"><?php echo e($t['transaction_id']); ?></td>
                            <td style="font-weight: 800;">$<?php echo number_format($t['amount'], 2); ?></td>
                            <td>
                                <?php 
                                    $status_bg = 'rgba(15,23,42,0.1)'; $status_color = 'var(--secondary)';
                                    if ($t['status'] === 'approved') { $status_bg = 'rgba(16,185,129,0.1)'; $status_color = 'var(--primary)'; }
                                    if ($t['status'] === 'pending') { $status_bg = 'rgba(245,158,11,0.1)'; $status_color = '#f59e0b'; }
                                    if ($t['status'] === 'rejected') { $status_bg = 'rgba(244,63,94,0.1)'; $status_color = 'var(--accent)'; }
                                ?>
                                <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; text-transform: uppercase;">
                                    <?php echo $t['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
