<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Custom Payments Voucher';
$breadcrumbs = ['Finance' => 'ledger.php', 'Custom Payments' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['fees.collect', 'payroll.manage']);

$pdo = db();
$user_id = current_user_id();

// Handle POST Voucher entry
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_payment') {
        $payee_name   = trim($_POST['payee_name'] ?? '');
        $payee_role   = $_POST['payee_role'] ?? 'other';
        $ref_user_id  = int_param('user_id', 0, $_POST) ?: null;
        $amount       = (float)($_POST['amount'] ?? 0);
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $account_id   = int_param('account_id', 0, $_POST);
        $notes        = trim($_POST['notes'] ?? '');

        if ($payee_name && $amount > 0 && $account_id) {
            $pdo->beginTransaction();
            try {
                // Verify account balance
                $stmt = $pdo->prepare("SELECT current_balance, account_name FROM accounts WHERE id = ? FOR UPDATE");
                $stmt->execute([$account_id]);
                $acc = $stmt->fetch();

                if (!$acc) {
                    throw new Exception("Selected payment account does not exist.");
                }

                if ($acc['current_balance'] < $amount) {
                    flash('error', "Insufficient funds in {$acc['account_name']}. Balance: " . setting('currency_symbol', '৳') . number_format($acc['current_balance'], 2));
                    $pdo->rollBack();
                } else {
                    // 1. Insert Custom Payment Voucher
                    $ins = $pdo->prepare("
                        INSERT INTO custom_payments (payee_name, payee_role, user_id, amount, payment_date, payment_method, account_id, notes, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$payee_name, $payee_role, $ref_user_id, $amount, $payment_date, $payment_method, $account_id, $notes, $user_id]);
                    $payment_id = $pdo->lastInsertId();

                    // 2. Decrement Account Balance
                    $pdo->prepare("UPDATE accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $account_id]);

                    // 3. Record account transaction ledger entry
                    $desc = "Custom Payout Voucher #$payment_id to $payee_name ($payee_role) - $notes";
                    $tx = $pdo->prepare("
                        INSERT INTO account_transactions (account_id, amount, transaction_type, description, reference_table, reference_id, created_by) 
                        VALUES (?, ?, 'withdrawal', ?, 'custom_payments', ?, ?)
                    ");
                    $tx->execute([$account_id, -$amount, $desc, $payment_id, $user_id]);

                    $pdo->commit();
                    flash('success', 'Custom payment voucher recorded successfully.');
                    header('Location: custom_payments.php');
                    exit;
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', 'Error: ' . $e->getMessage());
            }
        } else {
            flash('error', 'Please fill in all mandatory fields.');
        }
    }
}

// Load liquid accounts
$accounts = $pdo->query("SELECT * FROM accounts ORDER BY account_name")->fetchAll(PDO::FETCH_ASSOC);

// Load all active staff/users for payee select autocomplete
$staff_members = $pdo->query("
    SELECT u.id, u.full_name, sp.designation 
    FROM staff_profiles sp 
    JOIN users u ON u.id = sp.user_id 
    WHERE sp.status = 'active' 
    ORDER BY u.full_name
")->fetchAll(PDO::FETCH_ASSOC);

// Load custom payments list
$payments = $pdo->query("
    SELECT p.*, a.account_name, u.full_name as author_name 
    FROM custom_payments p 
    JOIN accounts a ON a.id = p.account_id 
    LEFT JOIN users u ON u.id = p.created_by 
    ORDER BY p.payment_date DESC, p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-file-earmark-check-fill me-2 text-primary"></i>Custom Payments Voucher</h1>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newPaymentModal"><i class="bi bi-plus-lg me-1"></i>New Payout Voucher</button>
</div>

<!-- Payments List -->
<div class="card shadow-sm border-0">
  <div class="card-header py-3 px-4 bg-light">
    <span class="card-title mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Logged Payments Vouchers</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Voucher #</th>
          <th>Date</th>
          <th>Payee Name</th>
          <th>Role / Classification</th>
          <th>Disbursal Account</th>
          <th>Payment Method</th>
          <th class="text-end">Amount</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
          <tr>
            <td colspan="8" class="text-center py-4 text-muted small">No custom payments logged yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td class="small font-monospace fw-bold">#<?= $p['id'] ?></td>
              <td class="small"><?= date(setting('date_format', 'd M Y'), strtotime($p['payment_date'])) ?></td>
              <td class="fw-bold"><?= e($p['payee_name']) ?></td>
              <td><span class="badge bg-secondary text-uppercase" style="font-size: 0.75rem;"><?= e($p['payee_role']) ?></span></td>
              <td><?= e($p['account_name']) ?></td>
              <td class="text-uppercase small"><?= e($p['payment_method']) ?></td>
              <td class="text-end fw-bold font-monospace text-danger">
                -<?= setting('currency_symbol', '৳') . number_format($p['amount'], 2) ?>
              </td>
              <td class="small text-muted" title="<?= e($p['notes']) ?>"><?= e(str_cutoff($p['notes'] ?? '', 45)) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: New Payment Voucher -->
<div class="modal fade" id="newPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_payment">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Disburse Custom Payout Voucher</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small">Payee Classification / Role</label>
              <select name="payee_role" id="payee_role" class="form-select form-select-sm" onchange="toggleUserSelect()">
                <option value="other">Other / General Vendor</option>
                <option value="staff">Active Staff Member</option>
                <option value="management">Management Personnel</option>
                <option value="vendor">Vendor Entity</option>
              </select>
            </div>
            <div class="col-md-6" id="user-select-col" style="display:none;">
              <label class="form-label small">Select Staff Member</label>
              <select name="user_id" id="user_id" class="form-select form-select-sm" onchange="autoFillPayeeName()">
                <option value="0">— Choose Staff —</option>
                <?php foreach ($staff_members as $sm): ?>
                  <option value="<?= $sm['id'] ?>" data-name="<?= e($sm['full_name']) ?>"><?= e($sm['full_name']) ?> (<?= e($sm['designation']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small">Payee / Entity Name <span class="text-danger">*</span></label>
            <input type="text" name="payee_name" id="payee_name" class="form-control form-control-sm" placeholder="e.g. Acme Supplies, Md. Karim" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small">Payout Amount <span class="text-danger">*</span></label>
              <input type="number" name="amount" class="form-control form-control-sm text-end" step="0.01" min="1" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small">Disbursal Date</label>
              <input type="date" name="payment_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small">Source Disbursal Account <span class="text-danger">*</span></label>
              <select name="account_id" class="form-select form-select-sm" required>
                <option value="">— Select Account —</option>
                <?php foreach ($accounts as $acc): ?>
                  <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (<?= setting('currency_symbol', '৳') . number_format($acc['current_balance'], 2) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small">Payment Method</label>
              <select name="payment_method" class="form-select form-select-sm">
                <option value="cash">Cash Handout</option>
                <option value="bank">Bank Transfer</option>
                <option value="mobile_banking">Mobile Wallet (bKash/Nagad)</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small">Voucher Remarks / Notes</label>
            <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Explain the purpose of this payout..."></textarea>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Disburse Payout</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleUserSelect() {
  const role = document.getElementById('payee_role').value;
  const col = document.getElementById('user-select-col');
  if (role === 'staff' || role === 'management') {
    col.style.display = 'block';
  } else {
    col.style.display = 'none';
    document.getElementById('user_id').value = '0';
  }
}

function autoFillPayeeName() {
  const select = document.getElementById('user_id');
  const option = select.options[select.selectedIndex];
  if (select.value !== '0') {
    document.getElementById('payee_name').value = option.dataset.name;
  }
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
