<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Expenses';
$breadcrumbs = ['Finance' => null, 'Expenses' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['expenses.manage']);

$pdo = db();
$session_id = int_param('session_id',(int)setting('current_session_id',0),$_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id   = int_param('id',0,$_POST);
        $sess = int_param('session_id',$session_id,$_POST);
        $cat  = int_param('expense_category_id',0,$_POST);
        $amt  = (float)($_POST['amount']??0);
        $date = $_POST['expense_date'] ?? date('Y-m-d');
        $desc = trim($_POST['description']??'');
        $vend = trim($_POST['vendor']??'');
        $inv  = trim($_POST['invoice_no']??'');
        $acc_id = int_param('account_id',0,$_POST);

        if ($cat && $amt > 0 && $acc_id) {
            $pdo->beginTransaction();
            try {
                // Verify account balance
                $stmt = $pdo->prepare("SELECT current_balance, account_name FROM accounts WHERE id = ? FOR UPDATE");
                $stmt->execute([$acc_id]);
                $acc = $stmt->fetch();

                if (!$acc) {
                    throw new Exception("Account does not exist.");
                }

                // If editing, we revert old amount first to calculate net balance impact
                $old_amt = 0.00;
                $old_acc_id = 0;
                if ($id) {
                    $oldStmt = $pdo->prepare("SELECT amount, account_id FROM expenses WHERE id = ?");
                    $oldStmt->execute([$id]);
                    $oldExp = $oldStmt->fetch();
                    if ($oldExp) {
                        $old_amt = (float)$oldExp['amount'];
                        $old_acc_id = (int)$oldExp['account_id'];
                    }
                }

                // Check if account has enough balance (accounting for reversion if it's the same account)
                $temp_balance = $acc['current_balance'];
                if ($id && $old_acc_id === $acc_id) {
                    $temp_balance += $old_amt;
                }
                if ($temp_balance < $amt) {
                    throw new Exception("Insufficient funds in {$acc['account_name']}. Available: " . setting('currency_symbol', '৳') . number_format($temp_balance, 2));
                }

                if ($id) {
                    // Revert old balance
                    if ($old_acc_id) {
                        $pdo->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$old_amt, $old_acc_id]);
                        $pdo->prepare("DELETE FROM account_transactions WHERE reference_table = 'expenses' AND reference_id = ?")->execute([$id]);
                    }

                    // Update expense
                    $pdo->prepare('UPDATE expenses SET expense_category_id=?,amount=?,expense_date=?,description=?,vendor=?,invoice_no=?,account_id=? WHERE id=?')
                        ->execute([$cat,$amt,$date,$desc,$vend,$inv,$acc_id,$id]);
                    flash('success','Expense updated.');
                } else {
                    // Insert new expense
                    $pdo->prepare('INSERT INTO expenses (session_id,expense_category_id,amount,expense_date,description,vendor,invoice_no,approved_by,status,account_id) VALUES (?,?,?,?,?,?,?,?,"approved",?)')
                        ->execute([$sess,$cat,$amt,$date,$desc,$vend,$inv,current_user_id(),$acc_id]);
                    $id = $pdo->lastInsertId();
                    flash('success','Expense logged.');
                }

                // Deduct from account balance
                $pdo->prepare("UPDATE accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amt, $acc_id]);

                // Write transaction log
                $tx = $pdo->prepare("INSERT INTO account_transactions (account_id, amount, transaction_type, description, reference_table, reference_id, created_by) VALUES (?, ?, 'withdrawal', ?, 'expenses', ?, ?)");
                $tx->execute([$acc_id, -$amt, "Expense: " . ($desc ?: "Paid expense") . " (Vendor: $vend)", 'expenses', $id, current_user_id()]);

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', $e->getMessage());
            }
        } else {
            flash('error', 'All marked fields are required.');
        }
    } elseif ($action === 'delete') {
        $id = int_param('id',0,$_POST);
        if ($id) {
            $pdo->beginTransaction();
            try {
                $oldStmt = $pdo->prepare("SELECT amount, account_id FROM expenses WHERE id = ?");
                $oldStmt->execute([$id]);
                $oldExp = $oldStmt->fetch();

                if ($oldExp && $oldExp['account_id']) {
                    // Revert balance
                    $pdo->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$oldExp['amount'], $oldExp['account_id']]);
                    // Delete transaction log
                    $pdo->prepare("DELETE FROM account_transactions WHERE reference_table = 'expenses' AND reference_id = ?")->execute([$id]);
                }

                $pdo->prepare('DELETE FROM expenses WHERE id=?')->execute([$id]);
                $pdo->commit();
                flash('success','Expense deleted.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', 'Delete failed: ' . $e->getMessage());
            }
        }
    }
    header('Location: expenses.php?session_id='.$session_id);
    exit;
}

$page   = max(1,int_param('page',1,$_GET));
$total  = 0;
$tStmt  = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE session_id=?');
$tStmt->execute([$session_id]);
$total  = (int)$tStmt->fetchColumn();
$pg     = paginate($total,$page);

$expenses = $pdo->query("SELECT e.*, ec.category_name, a.account_name, u.full_name as approved_by_name FROM expenses e JOIN expense_categories ec ON ec.id=e.expense_category_id LEFT JOIN accounts a ON a.id = e.account_id LEFT JOIN users u ON u.id=e.approved_by WHERE e.session_id=$session_id ORDER BY e.expense_date DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}")->fetchAll();

$monthlyTotal = 0.00;
$mStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE session_id=? AND MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())');
$mStmt->execute([$session_id]);
$monthlyTotal = (float)$mStmt->fetchColumn();

$sessionTotal = 0.00;
$sStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE session_id=?');
$sStmt->execute([$session_id]);
$sessionTotal = (float)$sStmt->fetchColumn();

$categories = $pdo->query('SELECT id,category_name FROM expense_categories ORDER BY category_name')->fetchAll();
$sessions   = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$accounts   = $pdo->query("SELECT id, account_name, current_balance FROM accounts ORDER BY account_name")->fetchAll(PDO::FETCH_ASSOC);

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Expenses</h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" onchange="location='?session_id='+this.value" style="width:auto">
      <?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expModal" onclick="setExpForm(null)"><i class="bi bi-plus-lg me-1"></i>Log Expense</button>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-sm-6"><div class="stat-card warning"><div class="stat-value"><?= money($monthlyTotal) ?></div><div class="stat-label">This Month</div><i class="bi bi-calendar3 stat-icon"></i></div></div>
  <div class="col-sm-6"><div class="stat-card danger"><div class="stat-value"><?= money($sessionTotal) ?></div><div class="stat-label">Session Total</div><i class="bi bi-receipt stat-icon"></i></div></div>
</div>
<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Date</th><th>Category</th><th>Account</th><th>Description</th><th>Vendor</th><th>Invoice</th><th>Amount</th><th>Logged By</th><th></th></tr></thead>
      <tbody>
        <?php if(empty($expenses)): ?>
          <tr><td colspan="9"><div class="empty-state"><i class="bi bi-receipt"></i><p>No expenses logged yet.</p></div></td></tr>
        <?php else: foreach($expenses as $ex): ?>
        <tr>
          <td><?= fmt_date($ex['expense_date']) ?></td>
          <td><?= e($ex['category_name']) ?></td>
          <td><span class="badge bg-secondary"><?= e($ex['account_name'] ?? '—') ?></span></td>
          <td class="fw-600"><?= e($ex['description']??'—') ?></td>
          <td><?= e($ex['vendor']??'—') ?></td>
          <td><code><?= e($ex['invoice_no']??'—') ?></code></td>
          <td class="fw-700"><?= money($ex['amount']) ?></td>
          <td><?= e($ex['approved_by_name']??'—') ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#expModal" onclick="setExpForm(<?= htmlspecialchars(json_encode($ex),ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
              <form method="POST" class="d-inline" onsubmit="this.querySelector('button[type=submit]').disabled=true;"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $ex['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="bi bi-trash"></i></button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="expModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="ex_id" value="0"><input type="hidden" name="session_id" value="<?= $session_id ?>">
        <div class="modal-header"><h5 class="modal-title" id="expModalTitle">Log Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Category *</label>
              <select name="expense_category_id" id="ex_cat" class="form-select" required>
                <option value="">— Select —</option>
                <?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="expense_date" id="ex_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-6"><label class="form-label">Amount (৳) *</label><input type="number" name="amount" id="ex_amt" class="form-control" step="0.01" min="0.01" required></div>
            <div class="col-md-6"><label class="form-label">Vendor</label><input type="text" name="vendor" id="ex_vendor" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Invoice No.</label><input type="text" name="invoice_no" id="ex_inv" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Payment Account *</label>
              <select name="account_id" id="ex_acc" class="form-select" required>
                <option value="">— Choose Account —</option>
                <?php foreach($accounts as $acc): ?>
                  <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (<?= setting('currency_symbol','৳').number_format($acc['current_balance'],2) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="ex_desc" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function setExpForm(e) {
  document.getElementById('expModalTitle').textContent = e?'Edit Expense':'Log Expense';
  document.getElementById('ex_id').value     = e?e.id:0;
  document.getElementById('ex_cat').value    = e?e.expense_category_id:'';
  document.getElementById('ex_date').value   = e?e.expense_date:'<?= date('Y-m-d') ?>';
  document.getElementById('ex_amt').value    = e?e.amount:'';
  document.getElementById('ex_vendor').value = e?(e.vendor||''):'';
  document.getElementById('ex_inv').value    = e?(e.invoice_no||''):'';
  document.getElementById('ex_acc').value    = e?e.account_id:'';
  document.getElementById('ex_desc').value   = e?(e.description||''):'';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
