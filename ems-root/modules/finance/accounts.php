<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Liquid Accounts Registry';
$breadcrumbs = ['Finance' => 'ledger.php', 'Accounts' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['finance.view']);

$pdo = db();
$user_id = current_user_id();

// Handle POST actions (requires fees.collect or payroll.manage permission for modification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (has_permission('fees.collect') || has_permission('payroll.manage'))) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Prevent double submission via simple request token check if needed
    if ($action === 'add_account') {
        $name = trim($_POST['account_name'] ?? '');
        $type = $_POST['account_type'] ?? 'cash';
        $number = trim($_POST['account_number'] ?? '') ?: null;
        $bank = trim($_POST['bank_name'] ?? '') ?: null;
        $balance = (float)($_POST['initial_balance'] ?? 0.00);
        $notes = trim($_POST['notes'] ?? '');

        if ($name) {
            $pdo->beginTransaction();
            try {
                // Check if account name already exists to prevent duplicate
                $chk = $pdo->prepare("SELECT id FROM accounts WHERE account_name = ?");
                $chk->execute([$name]);
                if ($chk->fetch()) {
                    flash('error', 'An account with this name already exists.');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO accounts (account_name, account_type, account_number, bank_name, current_balance, notes) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $type, $number, $bank, $balance, $notes]);
                    $acc_id = $pdo->lastInsertId();

                    if ($balance != 0) {
                        $tx = $pdo->prepare("INSERT INTO account_transactions (account_id, amount, transaction_type, description, created_by) VALUES (?, ?, 'adjustment', 'Initial opening balance', ?)");
                        $tx->execute([$acc_id, $balance, $user_id]);
                    }
                    $pdo->commit();
                    flash('success', 'Account registered successfully.');
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', 'Error: ' . $e->getMessage());
            }
        }
    }

    if ($action === 'transfer') {
        $from = int_param('from_account_id', 0, $_POST);
        $to = int_param('to_account_id', 0, $_POST);
        $amount = (float)($_POST['amount'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? 'Funds Transfer');

        if ($from && $to && $amount > 0) {
            if ($from === $to) {
                flash('error', 'Source and destination accounts must be different.');
            } else {
                $pdo->beginTransaction();
                try {
                    // Check balance of source account
                    $stmt = $pdo->prepare("SELECT current_balance, account_name FROM accounts WHERE id = ? FOR UPDATE");
                    $stmt->execute([$from]);
                    $fromAcc = $stmt->fetch();

                    $stmt = $pdo->prepare("SELECT account_name FROM accounts WHERE id = ? FOR UPDATE");
                    $stmt->execute([$to]);
                    $toAcc = $stmt->fetch();

                    if ($fromAcc['current_balance'] < $amount) {
                        flash('error', "Insufficient funds in {$fromAcc['account_name']}. Balance is " . setting('currency_symbol', '৳') . number_format($fromAcc['current_balance'], 2));
                    } else {
                        // Deduct from source
                        $pdo->prepare("UPDATE accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $from]);
                        // Add to destination
                        $pdo->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$amount, $to]);

                        // Record source transaction
                        $tx = $pdo->prepare("INSERT INTO account_transactions (account_id, amount, transaction_type, description, created_by) VALUES (?, ?, 'transfer', ?, ?)");
                        $tx->execute([$from, -$amount, "Transfer to {$toAcc['account_name']}: $remarks", $user_id]);
                        // Record destination transaction
                        $tx->execute([$to, $amount, "Transfer from {$fromAcc['account_name']}: $remarks", $user_id]);

                        $pdo->commit();
                        flash('success', 'Funds transferred successfully.');
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    flash('error', 'Transfer failed: ' . $e->getMessage());
                }
            }
        }
    }

    if ($action === 'adjust') {
        $acc_id = int_param('account_id', 0, $_POST);
        $amount = (float)($_POST['amount'] ?? 0);
        $type = $_POST['adj_type'] ?? 'add';
        $remarks = trim($_POST['remarks'] ?? '');

        if ($acc_id && $amount > 0 && $remarks) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT current_balance, account_name FROM accounts WHERE id = ? FOR UPDATE");
                $stmt->execute([$acc_id]);
                $acc = $stmt->fetch();

                $diff = ($type === 'add') ? $amount : -$amount;
                
                if ($type === 'deduct' && $acc['current_balance'] < $amount) {
                    flash('error', "Cannot adjust balance below zero. Current balance is " . setting('currency_symbol', '৳') . number_format($acc['current_balance'], 2));
                } else {
                    $pdo->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$diff, $acc_id]);
                    
                    $tx = $pdo->prepare("INSERT INTO account_transactions (account_id, amount, transaction_type, description, created_by) VALUES (?, ?, 'adjustment', ?, ?)");
                    $tx->execute([$acc_id, $diff, "Adjustment ($type): $remarks", $user_id]);

                    $pdo->commit();
                    flash('success', 'Account balance adjusted successfully.');
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', 'Adjustment failed: ' . $e->getMessage());
            }
        } else {
            flash('error', 'Remarks are mandatory for balance adjustments.');
        }
    }

    header('Location: accounts.php');
    exit;
}

// Load accounts list
$accounts = $pdo->query("SELECT * FROM accounts ORDER BY account_name")->fetchAll(PDO::FETCH_ASSOC);

// Load latest transactions
$transactions = $pdo->query("
    SELECT t.*, a.account_name, u.full_name as author_name 
    FROM account_transactions t 
    JOIN accounts a ON a.id = t.account_id 
    LEFT JOIN users u ON u.id = t.created_by 
    ORDER BY t.created_at DESC 
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-wallet2 me-2 text-primary"></i>Liquid Accounts Registry</h1>
  <?php if (has_permission('fees.collect') || has_permission('payroll.manage')): ?>
    <div class="d-flex gap-2">
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAccountModal"><i class="bi bi-plus-lg me-1"></i>New Account</button>
      <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#transferModal"><i class="bi bi-arrow-left-right me-1"></i>Funds Transfer</button>
      <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#adjustModal"><i class="bi bi-sliders me-1"></i>Manual Adjustment</button>
    </div>
  <?php endif; ?>
</div>

<!-- Accounts Summary Cards -->
<div class="row g-3 mb-4">
  <?php foreach ($accounts as $acc): 
    $icon = ($acc['account_type'] === 'cash') ? 'cash-coin text-success' : (($acc['account_type'] === 'bank') ? 'bank text-primary' : 'phone-fill text-danger');
  ?>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: white; border-radius: 12px;">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <span class="text-white-50 small text-uppercase"><?= e($acc['account_type']) ?> Account</span>
              <h4 class="fw-bold mb-1 mt-1"><?= e($acc['account_name']) ?></h4>
              <?php if ($acc['account_number']): ?>
                <code class="text-white-50 small"><?= e($acc['account_number']) ?></code>
              <?php endif; ?>
            </div>
            <i class="bi bi-<?= $icon ?>" style="font-size: 2.5rem; opacity: 0.8;"></i>
          </div>
          <hr class="my-3 opacity-25">
          <div class="d-flex justify-content-between align-items-end">
            <div>
              <span class="text-white-50 small d-block">Current Balance</span>
              <span class="fs-3 fw-bold"><?= setting('currency_symbol', '৳') . number_format($acc['current_balance'], 2) ?></span>
            </div>
            <?php if ($acc['bank_name']): ?>
              <small class="text-white-50"><?= e($acc['bank_name']) ?></small>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Ledger Transactions Table -->
<div class="card shadow-sm border-0">
  <div class="card-header py-3 px-4 bg-light d-flex align-items-center justify-content-between">
    <span class="card-title mb-0"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Accounts Transaction History</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Date/Time</th>
          <th>Account</th>
          <th>Type</th>
          <th>Description</th>
          <th class="text-end">Amount</th>
          <th>Logged By</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($transactions)): ?>
          <tr>
            <td colspan="6" class="text-center py-4 text-muted small">No transactions logged yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($transactions as $tx): 
            $badge = ($tx['transaction_type'] === 'deposit') ? 'bg-success-subtle text-success' : (($tx['transaction_type'] === 'withdrawal') ? 'bg-danger-subtle text-danger' : (($tx['transaction_type'] === 'transfer') ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning'));
            $sign = ($tx['amount'] > 0) ? '+' : '';
          ?>
            <tr>
              <td class="small font-monospace"><?= date(setting('date_format', 'd M Y') . ' H:i', strtotime($tx['created_at'])) ?></td>
              <td class="fw-bold"><?= e($tx['account_name']) ?></td>
              <td><span class="badge <?= $badge ?> text-uppercase" style="font-size: 0.7rem;"><?= e($tx['transaction_type']) ?></span></td>
              <td class="small text-muted"><?= e($tx['description']) ?></td>
              <td class="text-end fw-bold font-monospace <?= ($tx['amount'] > 0) ? 'text-success' : 'text-danger' ?>">
                <?= $sign . setting('currency_symbol', '৳') . number_format($tx['amount'], 2) ?>
              </td>
              <td class="small"><?= e($tx['author_name'] ?: 'System') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Add Account -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_account">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Register New Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small">Account Name <span class="text-danger">*</span></label>
            <input type="text" name="account_name" class="form-control form-control-sm" placeholder="e.g. Main Cash Chest, Prime Bank Account" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small">Account Type</label>
              <select name="account_type" class="form-select form-select-sm">
                <option value="cash">Cash Register</option>
                <option value="bank">Bank Account</option>
                <option value="mobile_banking">Mobile Banking (bKash/Nagad)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small">Initial Opening Balance</label>
              <input type="number" name="initial_balance" class="form-control form-control-sm text-end" value="0.00" step="0.01">
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small">Account / Wallet Number</label>
              <input type="text" name="account_number" class="form-control form-control-sm" placeholder="Optional">
            </div>
            <div class="col-md-6">
              <label class="form-label small">Bank Name</label>
              <input type="text" name="bank_name" class="form-control form-control-sm" placeholder="e.g. Sonali Bank (For Bank account only)">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small">Notes / Remarks</label>
            <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Save Registry</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Funds Transfer -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="transfer">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Inter-Account Funds Transfer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small">From Account (Source)</label>
              <select name="from_account_id" class="form-select form-select-sm" required>
                <?php foreach ($accounts as $acc): ?>
                  <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (<?= setting('currency_symbol', '৳') . number_format($acc['current_balance'], 2) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small">To Account (Destination)</label>
              <select name="to_account_id" class="form-select form-select-sm" required>
                <?php foreach ($accounts as $acc): ?>
                  <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (<?= setting('currency_symbol', '৳') . number_format($acc['current_balance'], 2) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small">Amount to Transfer <span class="text-danger">*</span></label>
            <input type="number" name="amount" class="form-control form-control-sm text-end" step="0.01" min="1" required>
          </div>
          <div class="mb-3">
            <label class="form-label small">Transfer Remarks / Reason <span class="text-danger">*</span></label>
            <input type="text" name="remarks" class="form-control form-control-sm" placeholder="e.g. Bank Deposit, Cash Out" required>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Process Transfer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Manual Adjustment -->
<div class="modal fade" id="adjustModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="adjust">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Manual Balance Adjustment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small">Target Account</label>
            <select name="account_id" class="form-select form-select-sm" required>
              <?php foreach ($accounts as $acc): ?>
                <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (<?= setting('currency_symbol', '৳') . number_format($acc['current_balance'], 2) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small">Adjustment Type</label>
              <select name="adj_type" class="form-select form-select-sm">
                <option value="add">Add (Increment Balance)</option>
                <option value="deduct">Deduct (Decrement Balance)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small">Adjustment Amount <span class="text-danger">*</span></label>
              <input type="number" name="amount" class="form-control form-control-sm text-end" step="0.01" min="0.01" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small">Reason / Remarks <span class="text-danger">*</span></label>
            <input type="text" name="remarks" class="form-control form-control-sm" placeholder="e.g. Audit correction, Bank charges offset" required>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Save Adjustment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
