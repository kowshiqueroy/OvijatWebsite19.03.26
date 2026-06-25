<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Stock Ledger';
$breadcrumbs = ['Inventory' => 'assets.php', 'Stock Ledger' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['inventory.view']);

$pdo      = db();
$item_id  = int_param('item_id',0,$_GET);
$type_f   = $_GET['type']??'';
$from     = $_GET['from']??date('Y-m-01');
$to       = $_GET['to']??date('Y-m-d');
$page     = max(1,int_param('page',1,$_GET));

$where    = ['ct.transaction_date BETWEEN :f AND :t'];
$params   = [':f'=>$from,':t'=>$to];
if ($item_id) { $where[]='ct.consumable_id=:iid'; $params[':iid']=$item_id; }
if ($type_f)  { $where[]='ct.transaction_type=:tp'; $params[':tp']=$type_f; }
$whereStr = implode(' AND ',$where);

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM consumable_transactions ct WHERE $whereStr");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pg    = paginate($total,$page);

$txns = $pdo->prepare("SELECT ct.*,c.item_name,c.unit,cc.category_name,u.full_name as issued_to_name,ub.full_name as created_by_name FROM consumable_transactions ct JOIN consumables c ON c.id=ct.consumable_id JOIN consumable_categories cc ON cc.id=c.consumable_category_id LEFT JOIN users u ON u.id=ct.issued_to LEFT JOIN users ub ON ub.id=ct.created_by WHERE $whereStr ORDER BY ct.transaction_date DESC,ct.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$txns->execute($params);
$txns = $txns->fetchAll();

$items = $pdo->query('SELECT id,item_name FROM consumables ORDER BY item_name')->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-clipboard-data-fill me-2 text-primary"></i>Stock Ledger</h1>
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label small">Item</label><select name="item_id" class="form-select form-select-sm"><option value="0">All Items</option><?php foreach($items as $i): ?><option value="<?= $i['id'] ?>" <?= $item_id==$i['id']?'selected':'' ?>><?= e($i['item_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label small">Type</label><select name="type" class="form-select form-select-sm"><option value="">All</option><?php foreach(['purchase','issue','return','disposal','adjustment'] as $t): ?><option value="<?= $t ?>" <?= $type_f===$t?'selected':'' ?>><?= ucfirst($t) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label small">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
    <div class="col-md-2"><label class="form-label small">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
    <div class="col-auto d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
      <a href="stock.php" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </form>
</div></div>

<div class="card table-card">
  <div class="card-header py-3 px-4"><span class="card-title">Transactions <span class="badge bg-secondary"><?= $total ?></span></span></div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 small">
      <thead><tr><th>Date</th><th>Item</th><th>Category</th><th>Type</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Issued To</th><th>Notes</th></tr></thead>
      <tbody>
        <?php if(empty($txns)): ?><tr><td colspan="9"><div class="empty-state"><i class="bi bi-clipboard-x"></i><p>No transactions in this period.</p></div></td></tr><?php endif; ?>
        <?php foreach($txns as $tx): $isIn=in_array($tx['transaction_type'],['purchase','return']); ?>
        <tr>
          <td><?= fmt_date($tx['transaction_date']) ?></td>
          <td class="fw-600"><?= e($tx['item_name']) ?></td>
          <td><?= e($tx['category_name']) ?></td>
          <td><span class="badge bg-<?= $isIn?'success':'warning text-dark' ?>"><?= ucfirst(e($tx['transaction_type'])) ?></span></td>
          <td class="fw-700 <?= $isIn?'text-success':'text-danger' ?>"><?= $isIn?'+':'-' ?><?= $tx['quantity'] ?> <?= e($tx['unit']) ?></td>
          <td><?= money($tx['unit_price']) ?></td>
          <td class="fw-600"><?= money($tx['total_price']) ?></td>
          <td><?= e($tx['issued_to_name']??'—') ?></td>
          <td class="text-muted"><?= e($tx['notes']??'') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if($pg['total_pages']>1): ?>
  <div class="card-footer d-flex align-items-center justify-content-between py-2 px-4">
    <small class="text-muted">Showing <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'],$total) ?> of <?= $total ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for($p=max(1,$pg['page']-2);$p<=min($pg['total_pages'],$pg['page']+2);$p++): ?>
        <li class="page-item <?= $p===$pg['page']?'active':'' ?>"><a class="page-link" href="?item_id=<?= $item_id ?>&type=<?= urlencode($type_f) ?>&from=<?= $from ?>&to=<?= $to ?>&page=<?= $p ?>"><?= $p ?></a></li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
