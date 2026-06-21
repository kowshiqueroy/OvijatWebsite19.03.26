<?php
/**
 * Wholesale User Wallet Dashboard - Premium Redesign
 */
restrict_to(['wholesale_user', 'admin', 'manager', 'executive']);

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Stripe Instant Top-up Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_stripe_topup'])) {
    $amount = (float)$_POST['amount'];
    $cardholder = trim($_POST['stripe_cardholder'] ?? '');
    $card_number = str_replace(' ', '', $_POST['stripe_card_number'] ?? '');
    $expiry = trim($_POST['stripe_expiry'] ?? '');
    $cvc = trim($_POST['stripe_cvc'] ?? '');
    
    $stripe_enabled = (get_setting($pdo, 'payment_stripe_enabled', '0') == '1');

    if (!$stripe_enabled) {
        $error = "Stripe top-up is currently disabled by administrator.";
    } elseif ($amount <= 0) {
        $error = "Please enter a valid amount.";
    } elseif (empty($cardholder) || empty($card_number) || empty($expiry) || empty($cvc)) {
        $error = "All card fields are required for credit card processing.";
    } elseif ($card_number !== '4242424242424242') {
        $error = "Transaction Declined: Only standard Stripe test card (4242 4242 4242 4242) is accepted in demo mode.";
    } else {
        $txn_ref = 'ch_mock_stripe_' . bin2hex(random_bytes(8));
        
        $stmt = $pdo->prepare("INSERT INTO wallet_topups (user_id, amount, payment_method, transaction_id, status) VALUES (?, ?, 'Stripe', ?, 'pending')");
        if ($stmt->execute([$user_id, $amount, $txn_ref])) {
            $success = "Stripe payment simulation succeeded! Top-up request submitted. An admin will review it shortly.";
        } else {
            $error = "Failed to submit Stripe top-up request.";
        }
    }
}

// Handle Top-up Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_topup'])) {
    $amount = (float)$_POST['amount'];
    $payment_method = trim($_POST['payment_method'] ?? '');
    $transaction_id = trim($_POST['transaction_id'] ?? '');

    if ($amount > 0 && $payment_method && $transaction_id) {
        $proof_image = '';
        
        // Handle Proof Upload
        if (!empty($_FILES['proof_image']['name'])) {
            if ($_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
                $error = "File upload failed with error code " . $_FILES['proof_image']['error'];
            } else {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $_FILES['proof_image']['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime_type, $allowed_types)) {
                    $error = "Invalid file type. Only JPG, PNG, GIF, WebP, and PDF are allowed.";
                } else {
                    $target_dir = "public/uploads/proofs/";
                    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                    
                    $orig_name = pathinfo($_FILES["proof_image"]["name"], PATHINFO_FILENAME);
                    $ext = pathinfo($_FILES["proof_image"]["name"], PATHINFO_EXTENSION);
                    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $orig_name);
                    $filename = time() . "_" . substr($safe_name, 0, 60) . "." . $ext;
                    $target_file = $target_dir . $filename;
                    
                    if (move_uploaded_file($_FILES["proof_image"]["tmp_name"], $target_file)) {
                        $proof_image = $filename;
                    }
                }
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

// Date Range Filter
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$show_all = isset($_GET['show_all']);

$q_tx = "SELECT * FROM wallet_transactions WHERE user_id = ?";
$q_top = "SELECT * FROM wallet_topups WHERE user_id = ?";
$params_tx = [$user_id];
$params_top = [$user_id];

if (!$show_all) {
    $q_tx .= " AND DATE(created_at) BETWEEN ? AND ?";
    $q_top .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params_tx[] = $date_from;
    $params_tx[] = $date_to;
    $params_top[] = $date_from;
    $params_top[] = $date_to;
}

$q_tx .= " ORDER BY created_at DESC";
$q_top .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($q_tx);
$stmt->execute($params_tx);
$transactions = $stmt->fetchAll();

$stmt = $pdo->prepare($q_top);
$stmt->execute($params_top);
$topups = $stmt->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-wallet"></i> Digital Wholesale Ledger
</div>

<?php if ($success): ?>
    <div style="background: rgba(16, 185, 129, 0.08); color: #166534; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2); font-size: 0.9rem;">
        <i class="fas fa-check-circle" style="margin-right: 6px;"></i> <?php echo e($success); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background: rgba(244, 63, 94, 0.08); color: #991b1b; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.2); font-size: 0.9rem;">
        <i class="fas fa-exclamation-triangle" style="margin-right: 6px;"></i> <?php echo e($error); ?>
    </div>
<?php endif; ?>

<div class="grid-stack-mobile" style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 3rem; align-items: flex-start; flex-wrap: wrap;">
    <!-- Left Side: Digital Card Mockup & Top-up Request form -->
    <div>
        <!-- Premium Digital Card Graphic -->
        <div class="wallet-card">
            <div style="display:flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                <span style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.85;">Wholesale Pass</span>
                <i class="fas fa-wifi" style="transform: rotate(90deg); font-size: 1.15rem; opacity: 0.8;"></i>
            </div>
            
            <div class="wallet-card-chip"></div>
            
            <div style="margin-bottom: 1.5rem;">
                <span style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: #cbd5e1; display: block; margin-bottom: 0.25rem;">Available Credit Balance</span>
                <strong style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 2.25rem; font-weight: 800; line-height: 1; letter-spacing: -0.5px;">$<?php echo number_format($wallet_balance, 2); ?></strong>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <span style="font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.1em; color: #cbd5e1; display: block;">Card Holder</span>
                    <strong style="font-size: 0.85rem; font-weight: 700; opacity: 0.95;"><?php echo e($_SESSION['username']); ?></strong>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.1em; color: #cbd5e1; display: block;">Authorized Role</span>
                    <strong style="font-size: 0.85rem; font-weight: 700; opacity: 0.95; text-transform: uppercase;">Wholesale</strong>
                </div>
            </div>
        </div>

        <!-- Request Form -->
        <div class="card" style="padding: 2rem;">
            <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1rem;">Fund Top-up Portal</h3>
            
            <?php $stripe_enabled = (get_setting($pdo, 'payment_stripe_enabled', '0') == '1'); ?>
            
            <!-- Tab Layout -->
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); padding-bottom: 0.75rem;">
                <button type="button" onclick="switchWalletTab('stripe')" id="btn-wallet-stripe" class="btn btn-blue" style="background: var(--primary); font-size: 0.85rem; padding: 0.5rem 0.75rem; border-radius: 6px; flex: 1; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                    <i class="fas fa-credit-card"></i> Stripe Instant
                </button>
                <button type="button" onclick="switchWalletTab('manual')" id="btn-wallet-manual" class="btn btn-blue" style="background: rgba(15,23,42,0.05); color: var(--secondary); font-size: 0.85rem; padding: 0.5rem 0.75rem; border-radius: 6px; flex: 1; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                    <i class="fas fa-university"></i> Bank/Cash Wire
                </button>
            </div>

            <!-- Stripe Tab Content -->
            <div id="pane-wallet-stripe" class="wallet-tab-content" style="display: block;">
                <?php if ($stripe_enabled): ?>
                    <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.5rem;">
                        Instant credit top-up using Stripe credit card simulation. Use standard test card <strong>4242 4242 4242 4242</strong>.
                    </p>
                    <form method="POST">
                        <div class="form-group">
                            <label>Top-up Amount ($) *</label>
                            <input type="number" step="0.01" name="amount" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label>Cardholder Name *</label>
                            <input type="text" name="stripe_cardholder" placeholder="e.g. John Doe" required>
                        </div>
                        <div class="form-group">
                            <label>Card Number *</label>
                            <input type="text" name="stripe_card_number" placeholder="4242 4242 4242 4242" maxlength="19" required oninput="formatCardNumber(this)">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.75rem;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Expiration (MM/YY) *</label>
                                <input type="text" name="stripe_expiry" placeholder="MM/YY" maxlength="5" required oninput="formatExpiry(this)">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>CVC *</label>
                                <input type="text" name="stripe_cvc" placeholder="123" maxlength="4" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="request_stripe_topup" class="btn btn-green" style="width: 100%; justify-content: center; padding: 0.95rem; border-radius: 10px; box-shadow: 0 8px 15px -3px var(--primary-glow);">
                            <i class="fas fa-bolt"></i> Pay & Request Top-up
                        </button>
                    </form>
                <?php else: ?>
                    <div style="background: rgba(245, 158, 11, 0.08); color: #92400e; padding: 1rem; border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.85rem; line-height: 1.5;">
                        <i class="fas fa-exclamation-circle" style="margin-right: 6px;"></i> Stripe credit card payments are currently disabled by the administrator. Please use the <strong>Bank/Cash Wire</strong> method instead.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Manual Tab Content -->
            <div id="pane-wallet-manual" class="wallet-tab-content" style="display: none;">
                <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.5rem;">
                    Submit bank wire or cash receipt references to receive wallet credits after administrator review.
                </p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Amount Transferred ($) *</label>
                        <input type="number" step="0.01" name="amount" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label>Payment Channel Method *</label>
                        <select name="payment_method" required style="border-radius: 8px;">
                            <option value="">-- Choose payment channel --</option>
                            <option value="Bank Transfer">Bank Wire Transfer</option>
                            <option value="Wire Transfer">Federal Reserve Wire</option>
                            <option value="Cash Deposit">Direct Cash Deposit</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reference / Transaction ID *</label>
                        <input type="text" name="transaction_id" placeholder="e.g. TXN-89283749" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 1.75rem;">
                        <label>Upload Receipt Proof (Image/Screenshot)</label>
                        <input type="file" name="proof_image" accept="image/*" style="padding: 0.4rem; font-size: 0.85rem;">
                    </div>
                    
                    <button type="submit" name="request_topup" class="btn btn-green" style="width: 100%; justify-content: center; padding: 0.95rem; border-radius: 10px; box-shadow: 0 8px 15px -3px var(--primary-glow);">
                        <i class="fas fa-paper-plane"></i> Submit Request Proof
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Side: Transaction Ledger & Top-up status logs -->
    <div>
        <!-- Date Filter Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <form method="GET" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; margin: 0;">
                <input type="hidden" name="url" value="wallet">
                <div class="form-group" style="margin: 0; flex: 1; min-width: 140px;">
                    <label style="font-weight: 800; font-size: 0.8rem; margin-bottom: 0.35rem; color: var(--secondary); display: block;">From</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" style="padding: 0.4rem; border-radius: 6px; font-size: 0.8rem; border: 1px solid #cbd5e1; width: 100%;">
                </div>
                <div class="form-group" style="margin: 0; flex: 1; min-width: 140px;">
                    <label style="font-weight: 800; font-size: 0.8rem; margin-bottom: 0.35rem; color: var(--secondary); display: block;">To</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" style="padding: 0.4rem; border-radius: 6px; font-size: 0.8rem; border: 1px solid #cbd5e1; width: 100%;">
                </div>
                <div style="display: flex; gap: 0.4rem; margin-top: auto;">
                    <button type="submit" class="btn btn-blue" style="padding: 0.5rem 1rem; font-size: 0.8rem;"><i class="fas fa-filter"></i> Filter</button>
                    <a href="/bolakausa/wallet?show_all=1" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.8rem; font-weight: 700; border-radius: 6px; text-decoration: none;">All</a>
                </div>
            </form>
        </div>

        <div class="card" style="padding: 2rem;">
            <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-history" style="color: var(--accent);"></i> Account Ledger Ledger
            </h3>
            
            <div class="table-wrap" style="box-shadow: none; border-radius: 12px; margin-bottom: 2.5rem;">
                <table style="min-width: 0;">
                    <thead>
                        <tr>
                            <th style="padding: 0.75rem 1rem;">Date</th>
                            <th style="padding: 0.75rem 1rem;">Description</th>
                            <th style="text-align: right; padding: 0.75rem 1rem;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$transactions): ?>
                            <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 2rem; font-size: 0.85rem;">No transaction activities logged in this date range. <a href="/bolakausa/wallet?show_all=1" style="color:var(--primary); font-weight:700;">Show All History</a></td></tr>
                        <?php endif; ?>
                        <?php foreach ($transactions as $txn): ?>
                        <tr>
                            <td style="padding: 1rem;"><small style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600;"><?php echo date('M d, Y', strtotime($txn['created_at'])); ?></small></td>
                            <td style="color: var(--secondary); font-weight: 600; font-size: 0.85rem; padding: 1rem;"><?php echo e($txn['description']); ?></td>
                            <td style="text-align: right; padding: 1rem; font-size: 0.85rem;">
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
            
            <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-file-invoice-dollar" style="color: var(--accent);"></i> Top-up Requests Status
            </h3>
            
            <div class="table-wrap" style="box-shadow: none; border-radius: 12px; margin-bottom: 0;">
                <table style="min-width: 0;">
                    <thead>
                        <tr>
                            <th style="padding: 0.75rem 1rem;">Date</th>
                            <th style="padding: 0.75rem 1rem;">Ref ID</th>
                            <th style="padding: 0.75rem 1rem;">Amount</th>
                            <th style="text-align: right; padding: 0.75rem 1rem;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$topups): ?>
                            <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem; font-size: 0.85rem;">No top-up requests found in this date range. <a href="/bolakausa/wallet?show_all=1" style="color:var(--primary); font-weight:700;">Show All History</a></td></tr>
                        <?php endif; ?>
                        <?php foreach ($topups as $t): ?>
                        <tr>
                            <td style="padding: 1rem;"><small style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600;"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></small></td>
                            <td style="color: var(--secondary); font-weight: 600; font-size: 0.85rem; padding: 1rem;"><?php echo e($t['transaction_id']); ?></td>
                            <td style="font-weight: 700; font-size: 0.85rem; padding: 1rem;">$<?php echo number_format($t['amount'], 2); ?></td>
                            <td style="text-align: right; padding: 1rem;">
                                <?php 
                                    $status_bg = 'rgba(15,23,42,0.05)'; $status_color = 'var(--secondary)';
                                    if ($t['status'] === 'approved') { $status_bg = 'rgba(16,185,129,0.08)'; $status_color = 'var(--primary-dark)'; }
                                    if ($t['status'] === 'pending') { $status_bg = 'rgba(245,158,11,0.08)'; $status_color = '#d97706'; }
                                    if ($t['status'] === 'rejected') { $status_bg = 'rgba(244,63,94,0.08)'; $status_color = '#991b1b'; }
                                ?>
                                <span style="padding: 3px 8px; border-radius: 20px; font-size: 0.65rem; font-weight: 800; background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; text-transform: uppercase; border: 1px solid rgba(0,0,0,0.03);">
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

<script>
function switchWalletTab(tabId) {
    document.querySelectorAll('.wallet-tab-content').forEach(el => el.style.display = 'none');
    
    document.querySelectorAll('[id^="btn-wallet-"]').forEach(btn => {
        btn.style.background = 'rgba(15,23,42,0.05)';
        btn.style.color = 'var(--secondary)';
    });

    document.getElementById('pane-wallet-' + tabId).style.display = 'block';
    
    const activeBtn = document.getElementById('btn-wallet-' + tabId);
    activeBtn.style.background = 'var(--primary)';
    activeBtn.style.color = 'white';
}

function formatCardNumber(input) {
    let value = input.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
    let matches = value.match(/\d{4,16}/g);
    let match = matches && matches[0] || '';
    let parts = [];

    for (let i=0, len=match.length; i<len; i+=4) {
        parts.push(match.substring(i, i+4));
    }

    if (parts.length > 0) {
        input.value = parts.join(' ');
    } else {
        input.value = value;
    }
}

function formatExpiry(input) {
    let value = input.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
    if (value.length >= 2) {
        input.value = value.substring(0, 2) + '/' + value.substring(2, 4);
    } else {
        input.value = value;
    }
}
</script>
