<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Non-Fee Income';
$breadcrumbs = ['Finance' => null, 'Income' => null];

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
        $cat  = int_param('income_category_id',0,$_POST);
        $amt  = (float)($_POST['amount']??0);
        $date = $_POST['income_date']??date('Y-m-d');
        $desc = trim($_POST['description']??'');
        $acc_id = int_param('account_id',0,$_POST);

        if ($cat && $amt > 0 && $acc_id) {
            $pdo->beginTransaction();
            try {
                if ($id) {
                    // Fetch old details
                    $oldStmt = $pdo->prepare("SELECT amount, account_id FROM incomes WHERE id = ?");
                    $oldStmt->execute([$id]);
                    $oldInc = $oldStmt->fetch();

                    if ($oldInc) {
                        // Revert old account balance
                        $pdo->prepare("UPDATE accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$oldInc['amount'], $oldInc['account_id']]);
                        // Delete old transaction log
                        $pdo->prepare("DELETE FROM account_transactions WHERE reference_table = 'incomes' AND reference_id = ?")->execute([$id]);
                    }

                    // Update income
                    $pdo->prepare('UPDATE incomes SET income_category_id=?,amount=?,income_date=?,description=?,account_id=? WHERE id=?')
                        ->execute([$cat,$amt,$date,$desc,$acc_id,$id]);
                    
                    flash('success','Income updated.');
                } else {
                    // Insert new income
                    $pdo->prepare('INSERT INTO incomes (session_id,income_category_id,amount,income_date,description,received_by,account_id) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$sess,$cat,$amt,$date,$desc,current_user_id(),$acc_id]);
                    $id = $pdo->lastInsertId();
                    flash('success','Income recorded.');
                }

                // Apply new balance
                $pdo->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$amt, $acc_id]);

                // Insert transaction log
                $tx = $pdo->prepare("INSERT INTO account_transactions (account_id, amount, transaction_type, description, reference_table, reference_id, created_by) VALUES (?, ?, 'deposit', ?, 'incomes', ?, ?)");
                $tx->execute([$acc_id, $amt, "Non-fee income: " . ($desc ?: "Received income"), 'incomes', $id, current_user_id()]);

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', 'Error saving income: ' . $e->getMessage());
            }
        } else {
            flash('error', 'All marked fields are required.');
        }
    } elseif ($action === 'delete') {
        $id = int_param('id',0,$_POST);
        if ($id) {
            $pdo->beginTransaction();
            try {
                $oldStmt = $pdo->prepare("SELECT amount, account_id FROM incomes WHERE id = ?");
                $oldStmt->execute([$id]);
                $oldInc = $oldStmt->fetch();

                if ($oldInc) {
                    // Revert balance
                    $pdo->prepare("UPDATE accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$oldInc['amount'], $oldInc['account_id']]);
                    // Delete transaction log
                    $pdo->prepare("DELETE FROM account_transactions WHERE reference_table = 'incomes' AND reference_id = ?")->execute([$id]);
                }

                $pdo->prepare('DELETE FROM incomes WHERE id=?')->execute([$id]);
                $pdo->commit();
                flash('success','Deleted.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', 'Delete failed: ' . $e->getMessage());
            }
        }
    }
    header('Location: income.php?session_id='.$session_id);
    exit;
}

$incomes  = $pdo->query("SELECT i.*,ic.category_name,a.account_name,u.full_name as by_name FROM incomes i JOIN income_categories ic ON ic.id=i.income_category_id LEFT JOIN accounts a ON a.id = i.account_id LEFT JOIN users u ON u.id=i.received_by WHERE i.session_id=$session_id ORDER BY i.income_date DESC")->fetchAll();
$total    = array_sum(array_column($incomes,'amount'));
$cats     = $pdo->query('SELECT id,category_name FROM income_categories ORDER BY category_name')->fetchAll();
$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$accounts = $pdo->query("SELECT id, account_name, current_balance FROM accounts ORDER BY account_name")->fetchAll(PDO::FETCH_ASSOC);

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-piggy-bank-fill me-2 text-primary"></i>Non-Fee Income</h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" onchange="location='?session_id='+this.value" style="width:auto">
      <?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#incModal" onclick="setIncForm(null)"><i class="bi bi-plus-lg me-1"></i>Add Income</button>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-sm-4"><div class="stat-card success"><div class="stat-value"><?= money($total) ?></div><div class="stat-label">Total Income (Session)</div><i class="bi bi-piggy-bank stat-icon"></i></div></div>
</div>
<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Date</th><th>Category</th><th>Account</th><th>Description</th><th>Amount</th><th>Received By</th><th></th></tr></thead>
      <tbody>
        <?php if(empty($incomes)): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-piggy-bank"></i><p>No income recorded yet.</p></div></td></tr><?php endif; ?>
        <?php foreach($incomes as $inc): ?>
        <tr>
          <td><?= fmt_date($inc['income_date']) ?></td>
          <td><?= e($inc['category_name']) ?></td>
          <td><span class="badge bg-secondary"><?= e($inc['account_name'] ?? '—') ?></span></td>
          <td class="fw-600"><?= e($inc['description']??'—') ?></td>
          <td class="fw-700 text-success"><?= money($inc['amount']) ?></td>
          <td><?= e($inc['by_name']??'—') ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#incModal" onclick="setIncForm(<?= htmlspecialchars(json_encode($inc),ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
              <form method="POST" class="d-inline" onsubmit="this.querySelector('button[type=submit]').disabled=true;"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $inc['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="bi bi-trash"></i></button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="incModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="in_id" value="0"><input type="hidden" name="session_id" value="<?= $session_id ?>">
        <div class="modal-header"><h5 class="modal-title" id="incModalTitle">Add Income</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Category *</label><select name="income_category_id" id="in_cat" class="form-select" required><option value="">—</option><?php foreach($cats as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="income_date" id="in_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-6"><label class="form-label">Amount (৳) *</label><input type="number" name="amount" id="in_amt" class="form-control" step="0.01" min="0.01" required></div>
            <div class="col-md-6"><label class="form-label">Deposit Account *</label>
              <select name="account_id" id="in_acc" class="form-select" required>
                <option value="">— Choose Account —</option>
                <?php foreach($accounts as $acc): ?>
                  <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (<?= setting('currency_symbol','৳').number_format($acc['current_balance'],2) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="in_desc" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function setIncForm(i){
  document.getElementById('incModalTitle').textContent=i?'Edit Income':'Add Income';
  document.getElementById('in_id').value=i?i.id:0;
  document.getElementById('in_cat').value=i?i.income_category_id:'';
  document.getElementById('in_date').value=i?i.income_date:'<?= date('Y-m-d') ?>';
  document.getElementById('in_amt').value=i?i.amount:'';
  document.getElementById('in_acc').value=i?i.account_id:'';
  document.getElementById('in_desc').value=i?(i.description||''):'';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
