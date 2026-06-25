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
        if ($cat && $amt > 0) {
            if ($id) {
                $pdo->prepare('UPDATE incomes SET income_category_id=?,amount=?,income_date=?,description=? WHERE id=?')->execute([$cat,$amt,$date,$desc,$id]);
                flash('success','Income updated.');
            } else {
                $pdo->prepare('INSERT INTO incomes (session_id,income_category_id,amount,income_date,description,received_by) VALUES (?,?,?,?,?,?)')->execute([$sess,$cat,$amt,$date,$desc,current_user_id()]);
                flash('success','Income recorded.');
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM incomes WHERE id=?')->execute([int_param('id',0,$_POST)]);
        flash('success','Deleted.');
    }
    header('Location: income.php?session_id='.$session_id);
    exit;
}

$incomes  = $pdo->query("SELECT i.*,ic.category_name,u.full_name as by_name FROM incomes i JOIN income_categories ic ON ic.id=i.income_category_id LEFT JOIN users u ON u.id=i.received_by WHERE i.session_id=$session_id ORDER BY i.income_date DESC")->fetchAll();
$total    = array_sum(array_column($incomes,'amount'));
$cats     = $pdo->query('SELECT id,category_name FROM income_categories ORDER BY category_name')->fetchAll();
$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();

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
      <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Received By</th><th></th></tr></thead>
      <tbody>
        <?php if(empty($incomes)): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-piggy-bank"></i><p>No income recorded yet.</p></div></td></tr><?php endif; ?>
        <?php foreach($incomes as $inc): ?>
        <tr>
          <td><?= fmt_date($inc['income_date']) ?></td>
          <td><?= e($inc['category_name']) ?></td>
          <td class="fw-600"><?= e($inc['description']??'—') ?></td>
          <td class="fw-700 text-success"><?= money($inc['amount']) ?></td>
          <td><?= e($inc['by_name']??'—') ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#incModal" onclick="setIncForm(<?= htmlspecialchars(json_encode($inc),ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
              <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $inc['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="bi bi-trash"></i></button></form>
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
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="in_id" value="0"><input type="hidden" name="session_id" value="<?= $session_id ?>">
        <div class="modal-header"><h5 class="modal-title" id="incModalTitle">Add Income</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Category *</label><select name="income_category_id" id="in_cat" class="form-select" required><option value="">—</option><?php foreach($cats as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="income_date" id="in_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-6"><label class="form-label">Amount (৳) *</label><input type="number" name="amount" id="in_amt" class="form-control" step="0.01" min="0.01" required></div>
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
  document.getElementById('in_desc').value=i?(i.description||''):'';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
