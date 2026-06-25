<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Staff Loans Management';
$breadcrumbs = ['Finance' => 'ledger.php', 'Staff Loans' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['finance.view']); // Accountants and Admins can manage loans

$pdo = db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('fees.collect')) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Disburse a new loan
    if ($action === 'disburse') {
        $staff_id      = int_param('staff_id', 0, $_POST);
        $amount        = (float)($_POST['loan_amount'] ?? 0);
        $interest      = (float)($_POST['interest_rate'] ?? 0);
        $installment   = (float)($_POST['monthly_installment'] ?? 0);
        $disburse_date = $_POST['disbursed_date'] ?: date('Y-m-d');
        $notes         = trim($_POST['notes'] ?? '');
        $account_id    = int_param('account_id', 0, $_POST);

        if ($staff_id && $amount > 0 && $installment > 0 && $account_id) {
            $pdo->beginTransaction();
            try {
                // Check account balance
                $stmt = $pdo->prepare("SELECT current_balance, account_name FROM accounts WHERE id = ? FOR UPDATE");
                $stmt->execute([$account_id]);
                $acc = $stmt->fetch();

                if (!$acc) {
                    throw new Exception("Source account does not exist.");
                }

                if ($acc['current_balance'] < $amount) {
                    throw new Exception("Insufficient funds in {$acc['account_name']}. Balance: " . setting('currency_symbol', '৳') . number_format($acc['current_balance'], 2));
                }

                $total_repayable = $amount + ($amount * ($interest / 100));
                
                $pdo->prepare(
                    'INSERT INTO staff_loans (staff_id, loan_amount, interest_rate, total_repayable, monthly_installment, amount_repaid, disbursed_date, status, notes)
                     VALUES (?, ?, ?, ?, ?, 0.00, ?, "active", ?)'
                )->execute([$staff_id, $amount, $interest, $total_repayable, $installment, $disburse_date, $notes]);

                $id = $pdo->lastInsertId();

                // Deduct from account balance
                $pdo->prepare("UPDATE accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $account_id]);

                // Write transaction log
                $tx = $pdo->prepare("INSERT INTO account_transactions (account_id, amount, transaction_type, description, reference_table, reference_id, created_by) VALUES (?, ?, 'withdrawal', ?, 'staff_loans', ?, ?)");
                $tx->execute([$account_id, -$amount, "Disbursed staff loan #$id (Amount: $amount)", 'staff_loans', $id, current_user_id()]);

                $pdo->commit();
                log_activity('disburse_staff_loan', 'finance', $id, '', "Amount:$amount, Repayable:$total_repayable");
                flash('success', 'Loan disbursed successfully.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', $e->getMessage());
            }
        } else {
            flash('error', 'Invalid loan configuration. Make sure installment and source account are selected.');
        }
        header('Location: loans.php');
        exit;
    }

    // Record manual loan repayment
    if ($action === 'repay') {
        $loan_id = int_param('loan_id', 0, $_POST);
        $repay_amount = (float)($_POST['repay_amount'] ?? 0);
        $repay_date   = $_POST['repay_date'] ?: date('Y-m-d');
        $notes        = trim($_POST['notes'] ?? '');
        $account_id   = int_param('account_id', 0, $_POST);

        if ($loan_id && $repay_amount > 0 && $account_id) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('SELECT * FROM staff_loans WHERE id = ? FOR UPDATE');
                $stmt->execute([$loan_id]);
                $loan = $stmt->fetch();

                if ($loan) {
                    $new_repaid = $loan['amount_repaid'] + $repay_amount;
                    $status = ($new_repaid >= $loan['total_repayable'] - 0.05) ? 'paid' : 'active';
                    
                    $pdo->prepare('UPDATE staff_loans SET amount_repaid = ?, status = ? WHERE id = ?')
                        ->execute([$new_repaid, $status, $loan_id]);
                    
                    // Track this manual repayment as miscellaneous income in incomes table so it is accounted in reports
                    // Let's find or create a loan repayment category
                    $pdo->exec("INSERT IGNORE INTO income_categories (category_name) VALUES ('Staff Loan Repayments')");
                    $catId = $pdo->query("SELECT id FROM income_categories WHERE category_name = 'Staff Loan Repayments'")->fetchColumn();
                    
                    $sessId = (int)setting('current_session_id', 0);
                    $pdo->prepare(
                        'INSERT INTO incomes (session_id, income_category_id, amount, income_date, description, received_by, account_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    )->execute([
                        $sessId,
                        $catId,
                        $repay_amount,
                        $repay_date,
                        "Loan Repayment — Staff ID: " . $loan['staff_id'] . ". Manual repayment. " . $notes,
                        current_user_id(),
                        $account_id
                    ]);
                    $income_id = $pdo->lastInsertId();

                    // Update account balance
                    $pdo->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$repay_amount, $account_id]);

                    // Write transaction log
                    $tx = $pdo->prepare("INSERT INTO account_transactions (account_id, amount, transaction_type, description, reference_table, reference_id, created_by) VALUES (?, ?, 'deposit', ?, 'incomes', ?, ?)");
                    $tx->execute([$account_id, $repay_amount, "Staff Loan Repayment (Loan #$loan_id)", 'incomes', $income_id, current_user_id()]);

                    $pdo->commit();
                    log_activity('repay_staff_loan', 'finance', $loan_id, '', "Repaid:$repay_amount, NewTotal:$new_repaid");
                    flash('success', 'Loan repayment recorded successfully.');
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', 'Repayment failed: ' . $e->getMessage());
            }
        } else {
            flash('error', 'Repayment amount and destination account are required.');
        }
        header('Location: loans.php');
        exit;
    }

    // Transfer outstanding loan balance to another staff member
    if ($action === 'transfer') {
        $loan_id   = int_param('loan_id', 0, $_POST);
        $to_staff_id = int_param('to_staff_id', 0, $_POST);
        $transfer_amount = (float)($_POST['transfer_amount'] ?? 0);
        $notes     = trim($_POST['notes'] ?? '');

        if ($loan_id && $to_staff_id && $transfer_amount > 0) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('SELECT * FROM staff_loans WHERE id = ? FOR UPDATE');
                $stmt->execute([$loan_id]);
                $loan = $stmt->fetch();

                if ($loan) {
                    $outstanding = $loan['total_repayable'] - $loan['amount_repaid'];
                    if ($transfer_amount > $outstanding) {
                        throw new Exception("Transfer amount cannot exceed the outstanding balance of " . money($outstanding));
                    }
                    if ($loan['staff_id'] == $to_staff_id) {
                        throw new Exception("Cannot transfer loan balance to the same staff member.");
                    }

                    // 1. Create a new active loan for the recipient
                    $installment = $loan['monthly_installment']; 
                    $pdo->prepare(
                        'INSERT INTO staff_loans (staff_id, loan_amount, interest_rate, total_repayable, monthly_installment, amount_repaid, disbursed_date, status, notes)
                         VALUES (?, ?, 0.00, ?, ?, 0.00, CURDATE(), "active", ?)'
                    )->execute([$to_staff_id, $transfer_amount, $transfer_amount, $installment, "Transferred from Loan #$loan_id. " . $notes]);
                    $new_loan_id = $pdo->lastInsertId();

                    // 2. Update status & link details in old loan
                    $new_repaid = $loan['amount_repaid'] + $transfer_amount;
                    $status = ($new_repaid >= $loan['total_repayable'] - 0.05) ? 'paid' : 'active';
                    
                    $pdo->prepare('UPDATE staff_loans SET amount_repaid = ?, status = ?, transferred_to_loan_id = ?, transferred_to_user_id = ? WHERE id = ?')
                        ->execute([$new_repaid, $status, $new_loan_id, $to_staff_id, $loan_id]);

                    $pdo->commit();
                    log_activity('transfer_staff_loan', 'finance', $loan_id, '', "From:" . $loan['staff_id'] . " To:$to_staff_id Amount:$transfer_amount");
                    flash('success', 'Loan balance transferred successfully.');
                } else {
                    throw new Exception("Source loan does not exist.");
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', $e->getMessage());
            }
        } else {
            flash('error', 'All transfer fields are required.');
        }
        header('Location: loans.php');
        exit;
    }
}

// Fetch all staff list for loan dropdown
$staffList = $pdo->query(
    "SELECT sp.user_id, CONCAT(sp.first_name, ' ', sp.last_name) as name, sp.designation 
     FROM staff_profiles sp 
     WHERE sp.status='active' 
     ORDER BY name"
)->fetchAll();

// Fetch all active loans
$activeLoans = $pdo->query(
    "SELECT sl.*, CONCAT(sp.first_name, ' ', sp.last_name) as staff_name, sp.designation 
     FROM staff_loans sl
     JOIN staff_profiles sp ON sp.user_id = sl.staff_id
     ORDER BY sl.status ASC, sl.id DESC"
)->fetchAll();

// Fetch all accounts
$accounts = $pdo->query("SELECT id, account_name, current_balance FROM accounts ORDER BY account_name")->fetchAll(PDO::FETCH_ASSOC);

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-wallet2 me-2 text-primary"></i>Staff Loans Registry</h1>
  <?php if (has_permission('fees.collect')): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#disburseModal"><i class="bi bi-plus-lg me-1"></i>Disburse Loan</button>
  <?php endif; ?>
</div>

<div class="row g-3">
  <!-- Active loans table -->
  <div class="col-md-8">
    <div class="card shadow-sm">
      <div class="card-header py-3 px-4 bg-light"><span class="card-title">Staff Loans & Balances</span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Staff Name</th>
              <th>Disbursed Date</th>
              <th class="text-end">Repayable</th>
              <th class="text-end">Repaid</th>
              <th class="text-end">Outstanding</th>
              <th class="text-end">Installment</th>
              <th class="text-center">Status</th>
              <?php if (has_permission('fees.collect')): ?>
                <th class="text-end">Action</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($activeLoans as $l): 
              $outstanding = $l['total_repayable'] - $l['amount_repaid'];
              $statusClass = $l['status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark';
            ?>
            <tr>
              <td>
                <div class="fw-600"><?= e($l['staff_name']) ?></div>
                <small class="text-muted"><?= e($l['designation']) ?></small>
              </td>
              <td><?= fmt_date($l['disbursed_date']) ?></td>
              <td class="text-end fw-600"><?= money($l['total_repayable']) ?></td>
              <td class="text-end text-success"><?= money($l['amount_repaid']) ?></td>
              <td class="text-end text-danger fw-600"><?= money(max(0, $outstanding)) ?></td>
              <td class="text-end"><?= money($l['monthly_installment']) ?>/mo</td>
              <td class="text-center">
                <span class="badge <?= $statusClass ?>"><?= ucfirst($l['status']) ?></span>
              </td>
              <?php if (has_permission('fees.collect')): ?>
                <td class="text-end">
                  <?php if ($l['status'] === 'active'): ?>
                    <div class="d-flex gap-1 justify-content-end">
                      <button class="btn btn-xs btn-outline-success" onclick="openRepayModal(<?= $l['id'] ?>, '<?= e($l['staff_name']) ?>', <?= max(0, $outstanding) ?>)">
                        Repay
                      </button>
                      <button class="btn btn-xs btn-outline-primary" onclick="openTransferModal(<?= $l['id'] ?>, '<?= e($l['staff_name']) ?>', <?= max(0, $outstanding) ?>)">
                        Transfer
                      </button>
                    </div>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($activeLoans)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">No loans disbursed yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Loan stats summary card -->
  <div class="col-md-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header py-3 px-4 bg-light"><span class="card-title">Loan Portfolio Overview</span></div>
      <div class="card-body">
        <?php
        $tot_disbursed = 0; $tot_repayable = 0; $tot_repaid = 0;
        foreach ($activeLoans as $l) {
            $tot_disbursed += $l['loan_amount'];
            $tot_repayable += $l['total_repayable'];
            $tot_repaid    += $l['amount_repaid'];
        }
        $outstanding = $tot_repayable - $tot_repaid;
        ?>
        <div class="mb-3 border-bottom pb-2">
          <div class="text-muted small">Total Disbursed Principal</div>
          <div class="fw-bold fs-4 text-primary"><?= money($tot_disbursed) ?></div>
        </div>
        <div class="mb-3 border-bottom pb-2">
          <div class="text-muted small">Total Repayable (with Interest)</div>
          <div class="fw-bold fs-5 text-dark"><?= money($tot_repayable) ?></div>
        </div>
        <div class="mb-3 border-bottom pb-2">
          <div class="text-muted small">Collected Repayments</div>
          <div class="fw-bold fs-5 text-success"><?= money($tot_repaid) ?></div>
        </div>
        <div>
          <div class="text-muted small">Total Outstanding Balance</div>
          <div class="fw-bold fs-4 text-danger"><?= money(max(0, $outstanding)) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Disburse Loan Modal -->
<div class="modal fade" id="disburseModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="disburse">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-600">Disburse New Loan</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-600">Select Staff *</label>
            <select name="staff_id" class="form-select form-select-sm" required>
              <option value="">— Choose Staff Member —</option>
              <?php foreach ($staffList as $st): ?>
                <option value="<?= $st['user_id'] ?>"><?= e($st['name']) ?> (<?= e($st['designation']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-600">Principal Amount (৳) *</label>
              <input type="number" name="loan_amount" id="loan_amt_input" class="form-control form-control-sm" min="1" step="any" oninput="calcRepayable()" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Interest Rate (%)</label>
              <input type="number" name="interest_rate" id="loan_int_input" class="form-control form-control-sm" min="0" max="100" step="any" value="0" oninput="calcRepayable()">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-600">Total Repayable (Autocalculated)</label>
            <input type="text" id="loan_repay_val" class="form-control form-control-sm bg-light" readonly value="৳ 0.00">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-600">Monthly Installment (৳) *</label>
              <input type="number" name="monthly_installment" class="form-control form-control-sm" min="1" step="any" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Disbursed Date *</label>
              <input type="date" name="disbursed_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-12">
              <label class="form-label small fw-600">Source Disbursement Account *</label>
              <select name="account_id" class="form-select form-select-sm" required>
                <option value="">— Select Account —</option>
                <?php foreach ($accounts as $acc): ?>
                  <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (<?= setting('currency_symbol','৳').number_format($acc['current_balance'],2) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label small fw-600">Remarks / Notes</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Salary advance for home repairs">
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Disburse</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Manual Repayment Modal -->
<div class="modal fade" id="repayModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="repay">
        <input type="hidden" name="loan_id" id="repay-loan-id">
        <div class="modal-header bg-success text-white py-2">
          <h6 class="modal-title fw-600">Loan Repayment</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="text-success fw-600 mb-2" id="repay-staff-name"></div>
          <div class="text-xs text-muted mb-3">Outstanding Dues: <strong class="text-danger" id="repay-outstanding-label">৳ 0.00</strong></div>
          
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label small fw-600">Repayment Amount (৳) *</label>
              <input type="number" name="repay_amount" id="repay-amount-input" class="form-control form-control-sm" min="1" step="any" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Payment Date *</label>
              <input type="date" name="repay_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-600">Destination Deposit Account *</label>
            <select name="account_id" class="form-select form-select-sm" required>
              <option value="">— Select Account —</option>
              <?php foreach ($accounts as $acc): ?>
                <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (<?= setting('currency_symbol','৳').number_format($acc['current_balance'],2) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-0">
            <label class="form-label small fw-600">Notes / Receipt Ref</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Paid in cash at office">
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm">Record Repayment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Transfer Loan Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="transfer">
        <input type="hidden" name="loan_id" id="transfer-loan-id">
        <div class="modal-header bg-primary text-white py-2">
          <h6 class="modal-title fw-600">Transfer Loan Balance</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="text-primary fw-600 mb-2" id="transfer-staff-name"></div>
          <div class="text-xs text-muted mb-3">Max Transferrable Outstanding: <strong class="text-danger" id="transfer-outstanding-label">৳ 0.00</strong></div>
          
          <div class="mb-3">
            <label class="form-label small fw-600">Transfer Outstanding to *</label>
            <select name="to_staff_id" class="form-select form-select-sm" required>
              <option value="">— Select Recipient Staff —</option>
              <?php foreach ($staffList as $st): ?>
                <option value="<?= $st['user_id'] ?>"><?= e($st['name']) ?> (<?= e($st['designation']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-600">Transfer Amount (৳) *</label>
            <input type="number" name="transfer_amount" id="transfer-amount-input" class="form-control form-control-sm" min="1" step="any" required>
          </div>

          <div class="mb-0">
            <label class="form-label small fw-600">Reason / Transfer Notes</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Employee resignation adjustment">
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Complete Transfer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function calcRepayable() {
  const amt = parseFloat(document.getElementById('loan_amt_input').value) || 0;
  const interest = parseFloat(document.getElementById('loan_int_input').value) || 0;
  const total = amt + (amt * (interest / 100));
  document.getElementById('loan_repay_val').value = '৳ ' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function openRepayModal(id, name, outstanding) {
  document.getElementById('repay-loan-id').value = id;
  document.getElementById('repay-staff-name').innerText = name;
  document.getElementById('repay-outstanding-label').innerText = '৳ ' + outstanding.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
  document.getElementById('repay-amount-input').max = outstanding;
  document.getElementById('repay-amount-input').value = Math.min(outstanding, 5000);
  new bootstrap.Modal(document.getElementById('repayModal')).show();
}

function openTransferModal(id, name, outstanding) {
  document.getElementById('transfer-loan-id').value = id;
  document.getElementById('transfer-staff-name').innerText = 'Transfer balance from: ' + name;
  document.getElementById('transfer-outstanding-label').innerText = '৳ ' + outstanding.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
  document.getElementById('transfer-amount-input').max = outstanding;
  document.getElementById('transfer-amount-input').value = outstanding;
  new bootstrap.Modal(document.getElementById('transferModal')).show();
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
