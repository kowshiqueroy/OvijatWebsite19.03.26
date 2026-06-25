<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Consumables & Stock';
$breadcrumbs = ['Inventory' => 'assets.php', 'Consumables' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['inventory.view']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('inventory.manage')) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_item') {
        $id   = int_param('id',0,$_POST);
        $cat  = int_param('consumable_category_id',0,$_POST);
        $name = trim($_POST['item_name']??'');
        $code = trim($_POST['item_code']??'') ?: null;
        $unit = trim($_POST['unit']??'piece');
        $min  = int_param('min_threshold',10,$_POST);
        $cost = (float)($_POST['unit_cost']??0);
        if ($cat && $name) {
            try {
                if ($id) {
                    $pdo->prepare('UPDATE consumables SET consumable_category_id=?,item_name=?,item_code=?,unit=?,min_threshold=?,unit_cost=? WHERE id=?')
                        ->execute([$cat,$name,$code,$unit,$min,$cost,$id]);
                    flash('success','Item updated.');
                } else {
                    $pdo->prepare('INSERT INTO consumables (consumable_category_id,item_name,item_code,unit,min_threshold,unit_cost,current_stock) VALUES (?,?,?,?,?,?,0)')
                        ->execute([$cat,$name,$code,$unit,$min,$cost]);
                    flash('success',"Item '$name' added.");
                }
            } catch (Exception $e) { flash('error','Item code already exists.'); }
        }
    } elseif ($action === 'transaction') {
        $cid  = int_param('consumable_id',0,$_POST);
        $type = $_POST['transaction_type']??'purchase';
        $qty  = int_param('quantity',0,$_POST);
        $uprice = (float)($_POST['unit_price']??0);
        $notes  = trim($_POST['notes']??'');
        $issued_to = int_param('issued_to',0,$_POST)?:null;
        $date = $_POST['transaction_date']??date('Y-m-d');

        if ($cid && $qty > 0) {
            $total = $qty * $uprice;
            $pdo->prepare('INSERT INTO consumable_transactions (consumable_id,transaction_type,quantity,unit_price,total_price,issued_to,transaction_date,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$cid,$type,$qty,$uprice,$total,$issued_to,$date,$notes,current_user_id()]);

            // Update stock
            $delta = in_array($type,['purchase','return']) ? $qty : -$qty;
            $pdo->prepare('UPDATE consumables SET current_stock=GREATEST(0,current_stock+?) WHERE id=?')->execute([$delta,$cid]);
            flash('success',"Transaction recorded. Stock updated.");
        }
    }
    header('Location: consumables.php');
    exit;
}

$lowStock  = $pdo->query('SELECT c.*, cc.category_name FROM consumables c JOIN consumable_categories cc ON cc.id=c.consumable_category_id WHERE c.current_stock <= c.min_threshold ORDER BY c.current_stock')->fetchAll();
$items     = $pdo->query('SELECT c.*, cc.category_name FROM consumables c JOIN consumable_categories cc ON cc.id=c.consumable_category_id ORDER BY cc.category_name, c.item_name')->fetchAll();
$cats      = $pdo->query('SELECT id,category_name FROM consumable_categories ORDER BY category_name')->fetchAll();
$staffList = $pdo->query("SELECT sp.user_id as id, CONCAT(sp.first_name,' ',sp.last_name) as name FROM staff_profiles sp WHERE sp.status='active' ORDER BY name LIMIT 100")->fetchAll();

$viewId  = int_param('item',0,$_GET);
$txnList = [];
if ($viewId) {
    $txnList = $pdo->query("SELECT ct.*, u.full_name as issued_to_name, ub.full_name as created_by_name FROM consumable_transactions ct LEFT JOIN users u ON u.id=ct.issued_to LEFT JOIN users ub ON ub.id=ct.created_by WHERE ct.consumable_id=$viewId ORDER BY ct.transaction_date DESC LIMIT 30")->fetchAll();
}

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-box-seam-fill me-2 text-primary"></i>Consumables & Stock</h1>
  <?php if(has_permission('inventory.manage')): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="setItemForm(null)"><i class="bi bi-plus-lg me-1"></i>Add Item</button>
  <?php endif; ?>
</div>

<?php if(!empty($lowStock)): ?>
<div class="alert alert-danger d-flex gap-2 align-items-center mb-3">
  <i class="bi bi-exclamation-triangle-fill fs-5"></i>
  <div><strong><?= count($lowStock) ?> items below minimum stock level!</strong>
    <?php foreach($lowStock as $ls): ?><span class="badge bg-danger ms-1"><?= e($ls['item_name']) ?>: <?= $ls['current_stock'] ?></span><?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- Items list -->
  <div class="col-md-5">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Items <span class="badge bg-secondary"><?= count($items) ?></span></span></div>
      <div class="list-group list-group-flush" style="max-height:500px;overflow-y:auto;">
        <?php if(empty($items)): ?>
          <div class="text-center text-muted py-3 small">No items added yet</div>
        <?php else: foreach($items as $item): $low=$item['current_stock']<=$item['min_threshold']; ?>
        <a href="?item=<?= $item['id'] ?>" class="list-group-item list-group-item-action py-2 <?= $viewId==$item['id']?'active':'' ?>">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <span class="fw-600 <?= $low&&$viewId!=$item['id']?'text-danger':'' ?>"><?= e($item['item_name']) ?></span>
              <small class="text-muted ms-2"><?= e($item['category_name']) ?></small>
            </div>
            <span class="badge bg-<?= $low?'danger':'success' ?>"><?= $item['current_stock'] ?> <?= e($item['unit']) ?></span>
          </div>
          <?php if($low): ?><div class="small text-danger">⚠ Below min (<?= $item['min_threshold'] ?>)</div><?php endif; ?>
        </a>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- Item detail + transactions -->
  <div class="col-md-7">
    <?php if($viewId): ?>
    <?php $curItem = null; foreach($items as $it) if($it['id']==$viewId) { $curItem=$it; break; } ?>
    <?php if($curItem): ?>
    <div class="card mb-3">
      <div class="card-body d-flex align-items-center justify-content-between py-3">
        <div>
          <h5 class="fw-700 mb-0"><?= e($curItem['item_name']) ?></h5>
          <div class="text-muted small"><?= e($curItem['category_name']) ?> · <?= e($curItem['item_code']??'No code') ?> · Unit: <?= e($curItem['unit']) ?></div>
        </div>
        <div class="text-center">
          <div class="fw-700 fs-3 <?= $curItem['current_stock']<=$curItem['min_threshold']?'text-danger':'text-success' ?>"><?= $curItem['current_stock'] ?></div>
          <div class="text-muted small">In Stock</div>
        </div>
        <div class="d-flex gap-2">
          <?php if(has_permission('inventory.manage')): ?>
          <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#txnModal" onclick="setTxn(<?= $viewId ?>, 'purchase')"><i class="bi bi-plus-lg me-1"></i>Purchase</button>
          <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#txnModal" onclick="setTxn(<?= $viewId ?>, 'issue')"><i class="bi bi-box-arrow-up-right me-1"></i>Issue</button>
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="setItemForm(<?= htmlspecialchars(json_encode($curItem),ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card table-card">
      <div class="card-header py-3 px-4"><span class="card-title">Transaction History</span></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Date</th><th>Type</th><th>Qty</th><th>Price</th><th>Issued To</th><th>Notes</th></tr></thead>
          <tbody>
            <?php if(empty($txnList)): ?><tr><td colspan="6" class="text-muted text-center small py-2">No transactions yet</td></tr><?php endif; ?>
            <?php foreach($txnList as $tx): $isIn=in_array($tx['transaction_type'],['purchase','return']); ?>
            <tr>
              <td><?= fmt_date($tx['transaction_date'],'d M Y') ?></td>
              <td><span class="badge bg-<?= $isIn?'success':'warning text-dark' ?>"><?= ucfirst(e($tx['transaction_type'])) ?></span></td>
              <td class="fw-700 <?= $isIn?'text-success':'text-danger' ?>"><?= $isIn?'+':'-' ?><?= $tx['quantity'] ?></td>
              <td><?= money($tx['unit_price']) ?></td>
              <td><?= e($tx['issued_to_name']??'—') ?></td>
              <td><?= e($tx['notes']??'') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-box-seam"></i><p>Select an item from the list to view stock and transactions.</p></div></div></div>
    <?php endif; ?>
  </div>
</div>

<!-- Item modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="save_item"><input type="hidden" name="id" id="ci_id" value="0">
        <div class="modal-header"><h5 class="modal-title" id="itemModalTitle">Add Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Category *</label><select name="consumable_category_id" id="ci_cat" class="form-select" required><option value="">—</option><?php foreach($cats as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Item Name *</label><input type="text" name="item_name" id="ci_name" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Item Code</label><input type="text" name="item_code" id="ci_code" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Unit</label><input type="text" name="unit" id="ci_unit" class="form-control" value="piece"></div>
            <div class="col-md-4"><label class="form-label">Min Stock Alert</label><input type="number" name="min_threshold" id="ci_min" class="form-control" value="10" min="0"></div>
            <div class="col-md-6"><label class="form-label">Unit Cost (৳)</label><input type="number" name="unit_cost" id="ci_cost" class="form-control" step="0.01" value="0"></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Transaction modal -->
<div class="modal fade" id="txnModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="transaction"><input type="hidden" name="consumable_id" id="txn_cid" value=""><input type="hidden" name="transaction_type" id="txn_type" value="purchase">
        <div class="modal-header"><h5 class="modal-title" id="txnModalTitle">Record Transaction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6"><label class="form-label">Quantity *</label><input type="number" name="quantity" class="form-control" min="1" required></div>
            <div class="col-6"><label class="form-label">Unit Price (৳)</label><input type="number" name="unit_price" class="form-control" step="0.01" value="0"></div>
            <div class="col-6"><label class="form-label">Date</label><input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-6" id="issued_to_wrap" style="display:none"><label class="form-label">Issue To (Staff)</label><select name="issued_to" class="form-select"><option value="">— Select —</option><?php foreach($staffList as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control"></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function setItemForm(i){
  document.getElementById('itemModalTitle').textContent=i?'Edit Item':'Add Item';
  document.getElementById('ci_id').value=i?i.id:0;
  document.getElementById('ci_cat').value=i?i.consumable_category_id:'';
  document.getElementById('ci_name').value=i?i.item_name:'';
  document.getElementById('ci_code').value=i?(i.item_code||''):'';
  document.getElementById('ci_unit').value=i?i.unit:'piece';
  document.getElementById('ci_min').value=i?i.min_threshold:10;
  document.getElementById('ci_cost').value=i?i.unit_cost:0;
}
function setTxn(id, type){
  document.getElementById('txn_cid').value=id;
  document.getElementById('txn_type').value=type;
  document.getElementById('txnModalTitle').textContent=type==='purchase'?'Record Purchase':'Issue Stock';
  document.getElementById('issued_to_wrap').style.display=type==='issue'?'block':'none';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
