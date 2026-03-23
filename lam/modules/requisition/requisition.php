<?php
// ============================================================
// modules/requisition/requisition.php
// ============================================================

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$statuses = [
    'draft'             => 'Draft',
    'order_confirm'     => 'Order Confirm',
    'edit_confirm'      => 'Edit Confirm',
    'on_design'         => 'On Design',
    'on_production'     => 'On Production',
    'on_packaging'      => 'On Packaging',
    'delivery_details'  => 'Delivery Details',
    'delivered'         => 'Delivered',
    'failed'            => 'Failed'
];

$editableStatuses = ['draft', 'order_confirm'];

if ($action === 'save_requisition' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id               = (int)($_POST['requisition_id'] ?? 0);
    $customer_id      = (int)($_POST['customer_id'] ?? 0);
    $customer_name    = trim($_POST['customer_name'] ?? '');
    $customer_phone   = trim($_POST['customer_phone'] ?? '');
    $customer_email   = trim($_POST['customer_email'] ?? '');
    $school_name      = trim($_POST['school_name'] ?? '');
    $status           = $_POST['status'] ?? 'draft';
    $notes            = trim($_POST['notes'] ?? '');
    
    $data = [
        'customer_id'    => $customer_id ?: null,
        'customer_name'  => $customer_name,
        'customer_phone' => $customer_phone,
        'customer_email' => $customer_email,
        'school_name'    => $school_name,
        'status'         => $status,
        'notes'          => $notes,
    ];

    if ($id) {
        $existing = dbFetch('SELECT status FROM requisitions WHERE id = ?', [$id]);
        if ($existing && !in_array($existing['status'], $editableStatuses)) {
            flash('error', 'Cannot edit - requisition is already confirmed.');
            redirect('requisition');
        }
        dbUpdate('requisitions', $data, 'id = ?', [$id]);
        logAction('UPDATE', 'requisitions', $id, 'Updated requisition: ' . $data['customer_name']);
        flash('success', 'Requisition updated.');
    } else {
        $last = dbFetch('SELECT MAX(id) as max_id FROM requisitions');
        $next = ($last['max_id'] ?? 0) + 1;
        $requisition_no = 'REQ-' . str_pad($next, 6, '0', STR_PAD_LEFT);
        $data['requisition_no'] = $requisition_no;
        $newId = dbInsert('requisitions', $data);
        logAction('CREATE', 'requisitions', $newId, 'Created requisition: ' . $requisition_no);
        flash('success', 'Requisition created.');
    }
    redirect('requisition');
}

if ($action === 'add_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $requisition_id = (int)$_POST['requisition_id'];
    $existing = dbFetch('SELECT status FROM requisitions WHERE id = ?', [$requisition_id]);
    if ($existing && !in_array($existing['status'], $editableStatuses)) {
        flash('error', 'Cannot add items - requisition is already confirmed.');
        redirect('requisition');
    }

    $product_name = trim($_POST['product_name'] ?? '');
    $size         = trim($_POST['size'] ?? '');
    $color        = trim($_POST['color'] ?? '');
    $label        = trim($_POST['label'] ?? '');
    $item_notes   = trim($_POST['item_notes'] ?? '');
    $qty          = (int)$_POST['qty'] ?: 1;
    $unit_price   = (float)$_POST['unit_price'] ?: 0;
    $total_price  = $qty * $unit_price;

    dbInsert('requisition_items', [
        'requisition_id' => $requisition_id,
        'product_name'   => $product_name,
        'size'           => $size,
        'color'          => $color,
        'label'          => $label,
        'notes'          => $item_notes,
        'qty'            => $qty,
        'unit_price'     => $unit_price,
        'total_price'    => $total_price,
    ]);
    logAction('CREATE', 'requisition_items', $requisition_id, 'Added item: ' . $product_name);
    flash('success', 'Item added.');
    redirect('requisition', ['edit' => $requisition_id, 'add_item' => '1']);
}

if ($action === 'delete_item') {
    $item_id = (int)$_GET['item_id'];
    $item = dbFetch('SELECT ri.requisition_id, r.status FROM requisition_items ri JOIN requisitions r ON ri.requisition_id = r.id WHERE ri.id = ?', [$item_id]);
    if ($item && !in_array($item['status'], $editableStatuses)) {
        flash('error', 'Cannot delete item - requisition is already confirmed.');
        redirect('requisition');
    }
    dbDelete('requisition_items', 'id = ?', [$item_id]);
    logAction('DELETE', 'requisition_items', $item_id, 'Deleted item');
    flash('success', 'Item deleted.');
    redirect('requisition', ['edit' => $item['requisition_id']]);
}

if ($action === 'delete_requisition') {
    $id = (int)$_GET['id'];
    $itemCount = dbFetch('SELECT COUNT(*) as c FROM requisition_items WHERE requisition_id = ?', [$id])['c'] ?? 0;
    if ($itemCount > 0) {
        flash('error', 'Cannot delete requisition with items. Remove items first.');
        redirect('requisition');
    }
    dbDelete('requisitions', 'id = ?', [$id]);
    logAction('DELETE', 'requisitions', $id, 'Deleted empty requisition');
    flash('success', 'Requisition deleted.');
    redirect('requisition');
}

if ($action === 'update_status') {
    $id     = (int)$_GET['id'];
    $status = $_GET['status'];
    
    $current = dbFetch('SELECT status FROM requisitions WHERE id = ?', [$id]);
    if ($current) {
        $statusOrder = array_keys($statuses);
        $currentIdx = array_search($current['status'], $statusOrder);
        $newIdx = array_search($status, $statusOrder);
        if ($newIdx < $currentIdx) {
            flash('error', 'Cannot change to a previous status.');
            redirect('requisition');
        }
    }
    
    dbUpdate('requisitions', ['status' => $status], 'id = ?', [$id]);
    logAction('UPDATE', 'requisitions', $id, 'Status changed to: ' . $status);
    flash('success', 'Status updated.');
    redirect('requisition');
}

$search       = trim($_GET['q'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$dateFrom     = $_GET['date_from'] ?: date('Y-m-d');
$dateTo       = $_GET['date_to'] ?: date('Y-m-d');
$params       = [];
$where        = '1=1';

if ($search) {
    $where .= ' AND (r.requisition_no LIKE ? OR r.customer_name LIKE ? OR r.school_name LIKE ? OR r.customer_phone LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterStatus) {
    $where .= ' AND r.status = ?';
    $params[] = $filterStatus;
}
if ($dateFrom) {
    $where .= ' AND DATE(r.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where .= ' AND DATE(r.created_at) <= ?';
    $params[] = $dateTo;
}

$requisitions = dbFetchAll("
    SELECT r.*, 
        (SELECT COUNT(*) FROM requisition_items WHERE requisition_id = r.id) as item_count
    FROM requisitions r 
    WHERE $where 
    ORDER BY r.created_at DESC", $params);

$allItems = [];
foreach ($requisitions as $r) {
    $allItems[$r['id']] = dbFetchAll('SELECT * FROM requisition_items WHERE requisition_id = ? ORDER BY id', [$r['id']]);
}
$customers = dbFetchAll("SELECT id, name, phone, email FROM customers ORDER BY name");
$products = dbFetchAll("SELECT id, name FROM products ORDER BY name");

$customerJson = json_encode($customers);
$productJson = json_encode($products);

$editing = !empty($_GET['edit']) ? dbFetch('SELECT * FROM requisitions WHERE id = ?', [(int)$_GET['edit']]) : null;
if ($editing) {
    $editingItems = dbFetchAll('SELECT * FROM requisition_items WHERE requisition_id = ?', [$editing['id']]);
    $editing['items'] = $editingItems;
}

$openEditModal = isset($_GET['edit_modal']) && $editing;

$pageTitle = 'Requisitions';
require_once BASE_PATH . '/includes/header.php';
?>

<style>
.status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.status-draft { background: #e3f2fd; color: #1565c0; }
.status-order_confirm { background: #fff3e0; color: #e65100; }
.status-edit_confirm { background: #fce4ec; color: #c2185b; }
.status-on_design { background: #e8f5e9; color: #2e7d32; }
.status-on_production { background: #e0f7fa; color: #00838f; }
.status-on_packaging { background: #f3e5f5; color: #6a1b9a; }
.status-delivery_details { background: #fff9c4; color: #f57f17; }
.status-delivered { background: #c8e6c9; color: #1b5e20; }
.status-failed { background: #ffcdd2; color: #b71c1c; }

@media print {
    .no-print, .app-header, .side-nav, .app-footer, .btn, form, .modal-backdrop { display: none !important; }
    .app-main { margin: 0 !important; padding: 10px !important; }
    .card { border: 1px solid #000 !important; box-shadow: none !important; page-break-inside: avoid; }
    .print-header { display: block !important; margin-bottom: 15px; }
    .print-header h2 { margin: 0 0 5px; font-size: 16px; }
    .print-header p { margin: 0; font-size: 11px; }
    table { font-size: 9px; border-collapse: collapse; width: 100%; }
    th, td { padding: 3px 4px !important; border: 1px solid #000 !important; vertical-align: top; }
    .status-badge { border: 1px solid currentColor; background: transparent !important; padding: 1px 3px; font-size: 8px; }
    select.no-print { display: none !important; }
    span.status-badge[style*="display: none"] { display: inline !important; }
    .table-wrap { overflow: visible !important; }
    tr { page-break-inside: avoid; }
}

.print-header { display: none; }
</style>

<div class="d-flex justify-between align-center mb-2 no-print">
  <h1>📋 Requisitions</h1>
  <div>
    <button class="btn btn-secondary" onclick="window.print()">🖨️ Print</button>
    <button class="btn btn-primary" onclick="openModal('requisitionModal')">+ New Requisition</button>
  </div>
</div>

<div class="print-header">
  <h2>Requisitions Report</h2>
  <p>
    <?php if ($search): ?>Search: <strong><?= e($search) ?></strong> | <?php endif ?>
    Date: <strong><?= fmtDate($dateFrom) ?></strong> to <strong><?= fmtDate($dateTo) ?></strong>
    <?php if ($filterStatus): ?> | Status: <strong><?= $statuses[$filterStatus] ?></strong><?php endif ?>
    | Total: <strong><?= money($grandTotal ?? 0) ?></strong>
  </p>
</div>

<form method="GET" class="no-print" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;align-items:end">
  <input type="hidden" name="page" value="requisition">
  <div style="display:flex;flex-direction:column">
    <label style="font-size:11px;color:#666">Search</label>
    <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Req No, Customer, School, Phone..." style="width:200px">
  </div>
  <div style="display:flex;flex-direction:column">
    <label style="font-size:11px;color:#666">From Date</label>
    <input type="date" name="date_from" value="<?= $dateFrom ?>" class="form-control" style="width:150px">
  </div>
  <div style="display:flex;flex-direction:column">
    <label style="font-size:11px;color:#666">To Date</label>
    <input type="date" name="date_to" value="<?= $dateTo ?>" class="form-control" style="width:150px">
  </div>
  <div style="display:flex;flex-direction:column">
    <label style="font-size:11px;color:#666">Status</label>
    <select name="status" class="form-control" style="width:160px">
      <option value="">All Status</option>
      <?php foreach ($statuses as $k => $v): ?>
        <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <button type="submit" class="btn btn-ghost">🔍 Search</button>
  <a href="index.php?page=requisition" class="btn btn-ghost">Reset</a>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:90px">Req No</th>
          <th style="width:120px">Customer / Phone</th>
          <th style="width:100px">School</th>
          <th>Product</th>
          <th style="width:50px">Size</th>
          <th style="width:60px">Color</th>
          <th style="width:70px">Label</th>
          <th style="width:40px">Qty</th>
          <th style="width:70px">Unit Price</th>
          <th style="width:80px">Total</th>
          <th>Notes</th>
          <th style="width:120px">Status</th>
          <th style="width:75px">Date</th>
          <th style="width:90px" class="no-print">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $grandTotal = 0;
        $statusOrder = array_keys($statuses);
        foreach ($requisitions as $r): 
          $reqItems = $allItems[$r['id']] ?? [];
          $totalAmount = array_sum(array_column($reqItems, 'total_price'));
          $grandTotal += $totalAmount;
          $rowCount = count($reqItems);
          $currentIdx = array_search($r['status'], $statusOrder);
        ?>
        <?php if (empty($reqItems)): ?>
        <tr>
          <td><strong><?= e($r['requisition_no']) ?></strong></td>
          <td>
            <?= e($r['customer_name']) ?><br>
            <small class="text-muted"><?= e($r['customer_phone']) ?></small>
          </td>
          <td><?= e($r['school_name']) ?></td>
          <td colspan="5" class="text-muted text-center">No items</td>
          <td><strong><?= money($totalAmount) ?></strong></td>
          <td></td>
          <td>
            <select name="status_disp_<?= $r['id'] ?>" class="form-control no-print" style="width:120px;padding:4px 6px;font-size:11px" onchange="window.location='index.php?page=requisition&action=update_status&id=<?= $r['id'] ?>&status='+this.value">
              <?php foreach ($statuses as $k => $v): 
                $optIdx = array_search($k, $statusOrder);
                if ($optIdx >= $currentIdx):
              ?>
                <option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endif; endforeach ?>
            </select>
            <span class="status-badge status-<?= $r['status'] ?>" style="display:none"><?= $statuses[$r['status']] ?? $r['status'] ?></span>
          </td>
          <td><?= fmtDate($r['created_at']) ?></td>
          <td class="no-print">
            <a href="index.php?page=requisition&edit=<?= $r['id'] ?>" class="btn btn-ghost btn-sm">👁️</a>
            <?php if (in_array($r['status'], $editableStatuses)): ?>
            <a href="index.php?page=requisition&edit=<?= $r['id'] ?>&edit_modal=1" class="btn btn-ghost btn-sm">✏️</a>
            <?php endif ?>
            <a href="index.php?page=requisition&action=delete_requisition&id=<?= $r['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete this empty requisition?">🗑️</a>
          </td>
        </tr>
        <?php else: ?>
          <?php foreach ($reqItems as $idx => $item): ?>
          <tr>
            <?php if ($idx === 0): ?>
            <td rowspan="<?= $rowCount ?>"><strong><?= e($r['requisition_no']) ?></strong></td>
            <td rowspan="<?= $rowCount ?>">
              <?= e($r['customer_name']) ?><br>
              <small class="text-muted"><?= e($r['customer_phone']) ?></small>
            </td>
            <td rowspan="<?= $rowCount ?>"><?= e($r['school_name']) ?></td>
            <?php endif ?>
            <td><?= e($item['product_name']) ?></td>
            <td><?= e($item['size']) ?></td>
            <td><?= e($item['color']) ?></td>
            <td><?= e($item['label']) ?></td>
            <td><?= $item['qty'] ?></td>
            <td><?= money($item['unit_price']) ?></td>
            <td><strong><?= money($item['total_price']) ?></strong></td>
            <td><?= e($item['notes']) ?></td>
            <?php if ($idx === 0): ?>
            <td rowspan="<?= $rowCount ?>">
              <select name="status_disp_<?= $r['id'] ?>" class="form-control no-print" style="width:120px;padding:4px 6px;font-size:11px" onchange="window.location='index.php?page=requisition&action=update_status&id=<?= $r['id'] ?>&status='+this.value">
                <?php foreach ($statuses as $k => $v): 
                  $optIdx = array_search($k, $statusOrder);
                  if ($optIdx >= $currentIdx):
                ?>
                  <option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endif; endforeach ?>
              </select>
              <span class="status-badge status-<?= $r['status'] ?>" style="display:none"><?= $statuses[$r['status']] ?? $r['status'] ?></span>
            </td>
            <td rowspan="<?= $rowCount ?>"><?= fmtDate($r['created_at']) ?></td>
            <td rowspan="<?= $rowCount ?>" class="no-print">
              <a href="index.php?page=requisition&edit=<?= $r['id'] ?>" class="btn btn-ghost btn-sm">👁️</a>
              <?php if (in_array($r['status'], $editableStatuses)): ?>
              <a href="index.php?page=requisition&edit=<?= $r['id'] ?>&edit_modal=1" class="btn btn-ghost btn-sm">✏️</a>
              <?php endif ?>
            </td>
            <?php endif ?>
          </tr>
          <?php endforeach ?>
        <?php endif ?>
        <?php endforeach ?>
        <?php if ($requisitions): ?>
        <tr style="font-weight:bold;background:#f5f5f5">
          <td colspan="9" class="text-right">Grand Total:</td>
          <td><?= money($grandTotal) ?></td>
          <td colspan="4"></td>
        </tr>
        <?php endif ?>
        <?php if (!$requisitions): ?><tr><td colspan="13" class="text-muted text-center">No requisitions found.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('select[name^="status_disp_"]').forEach(function(sel) {
    sel.style.display = 'block';
    sel.nextElementSibling.style.display = 'none';
  });
});
</script>

<?php if ($editing): ?>
<div class="card mt-3">
  <div class="d-flex justify-between align-center mb-2">
    <h3>Requisition Details: <?= e($editing['requisition_no']) ?></h3>
    <?php if (in_array($editing['status'], $editableStatuses)): ?>
    <button class="btn btn-primary btn-sm" onclick="openModal('addItemModal')">+ Add Item</button>
    <?php endif ?>
  </div>
  
  <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
    <div>
      <strong>Customer:</strong> <?= e($editing['customer_name']) ?><br>
      <strong>Phone:</strong> <?= e($editing['customer_phone']) ?><br>
      <strong>Email:</strong> <?= e($editing['customer_email']) ?>
    </div>
    <div>
      <strong>School Name:</strong> <?= e($editing['school_name']) ?><br>
      <strong>Status:</strong> <span class="status-badge status-<?= $editing['status'] ?>"><?= $statuses[$editing['status']] ?? $editing['status'] ?></span><br>
      <strong>Created:</strong> <?= fmtDateTime($editing['created_at']) ?>
    </div>
  </div>

  <?php if ($editing['notes']): ?>
  <div class="mb-2"><strong>Notes:</strong> <?= e($editing['notes']) ?></div>
  <?php endif ?>

  <h4>Items</h4>
  <table>
    <thead><tr><th>Product</th><th>Size</th><th>Color</th><th>Label</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Notes</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($editing['items'] as $item): ?>
      <tr>
        <td><?= e($item['product_name']) ?></td>
        <td><?= e($item['size']) ?></td>
        <td><?= e($item['color']) ?></td>
        <td><?= e($item['label']) ?></td>
        <td><?= $item['qty'] ?></td>
        <td><?= money($item['unit_price']) ?></td>
        <td><strong><?= money($item['total_price']) ?></strong></td>
        <td><?= e($item['notes']) ?></td>
        <td>
          <?php if (in_array($editing['status'], $editableStatuses)): ?>
          <a href="index.php?page=requisition&action=delete_item&item_id=<?= $item['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete this item?">🗑️</a>
          <?php endif ?>
        </td>
      </tr>
      <?php endforeach ?>
      <?php
        $grandTotal = array_sum(array_column($editing['items'] ?? [], 'total_price'));
      ?>
      <tr>
        <td colspan="5" class="text-right"><strong>Grand Total:</strong></td>
        <td colspan="3"><strong class="text-lg"><?= money($grandTotal) ?></strong></td>
      </tr>
    </tbody>
  </table>
</div>
<?php endif ?>

<div class="modal-backdrop <?= $openEditModal ? 'open' : '' ?>" id="requisitionModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title"><?= $editing ? 'Edit Requisition' : 'New Requisition' ?></span>
      <button class="modal-close" onclick="window.location='index.php?page=requisition'">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="requisitionForm">
        <input type="hidden" name="action" value="save_requisition">
        <input type="hidden" name="requisition_id" value="<?= $editing['id'] ?? '' ?>">
        
        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Customer Name *</label>
            <input type="text" name="customer_name" class="form-control" required value="<?= e($editing['customer_name'] ?? '') ?>" id="customerNameInput" list="customerList" autocomplete="off" placeholder="Type to search or enter new...">
            <datalist id="customerList"></datalist>
          </div>
          <div class="form-group">
            <label class="form-label">School Name</label>
            <input type="text" name="school_name" class="form-control" value="<?= e($editing['school_name'] ?? '') ?>">
          </div>
        </div>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Customer Phone</label>
            <input type="text" name="customer_phone" class="form-control" value="<?= e($editing['customer_phone'] ?? '') ?>" id="customerPhoneInput">
          </div>
          <div class="form-group">
            <label class="form-label">Customer Email</label>
            <input type="email" name="customer_email" class="form-control" value="<?= e($editing['customer_email'] ?? '') ?>" id="customerEmailInput">
          </div>
        </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <?php foreach ($statuses as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($editing['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach ?>
            </select>
          </div>

        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"><?= e($editing['notes'] ?? '') ?></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="window.location='index.php?page=requisition'">Cancel</button>
      <button type="submit" form="requisitionForm" class="btn btn-primary"><?= $editing ? 'Update' : 'Create' ?></button>
    </div>
  </div>
</div>

<div class="modal-backdrop <?= (isset($_GET['add_item']) && $editing) ? 'open' : '' ?>" id="addItemModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title">Add Item</span>
      <button class="modal-close" onclick="closeModal('addItemModal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="addItemForm">
        <input type="hidden" name="action" value="add_item">
        <input type="hidden" name="requisition_id" value="<?= $editing['id'] ?? '' ?>">

        <div class="form-group">
          <label class="form-label">Product Name *</label>
          <input type="text" name="product_name" class="form-control" required id="productNameInput" list="productList" autocomplete="off" placeholder="Type to search or enter new...">
          <datalist id="productList"></datalist>
        </div>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Size</label>
            <input type="text" name="size" class="form-control" placeholder="e.g., S, M, L, 30, 32">
          </div>
          <div class="form-group">
            <label class="form-label">Color</label>
            <input type="text" name="color" class="form-control" placeholder="e.g., Red, Blue, Black">
          </div>
        </div>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Label</label>
            <input type="text" name="label" class="form-control" placeholder="Label text">
          </div>
          <div class="form-group">
            <label class="form-label">Quantity *</label>
            <input type="number" name="qty" class="form-control" value="1" min="1" required>
          </div>
        </div>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Unit Price</label>
            <input type="number" name="unit_price" class="form-control" value="0" step="0.01" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Item Notes</label>
            <input type="text" name="item_notes" class="form-control" placeholder="Any special notes">
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button type="submit" form="addItemForm" class="btn btn-primary">Add Item</button>
    </div>
  </div>
</div>

<script>
const customers = <?= $customerJson ?>;
const products = <?= $productJson ?>;

const customerList = document.getElementById('customerList');
const productList = document.getElementById('productList');

customers.forEach(c => {
  const opt = document.createElement('option');
  opt.value = c.name;
  opt.dataset.phone = c.phone || '';
  opt.dataset.email = c.email || '';
  customerList.appendChild(opt);
});

products.forEach(p => {
  const opt = document.createElement('option');
  opt.value = p.name;
  productList.appendChild(opt);
});

document.getElementById('customerNameInput').addEventListener('change', function() {
  const found = customers.find(c => c.name === this.value);
  if (found) {
    document.getElementById('customerPhoneInput').value = found.phone || '';
    document.getElementById('customerEmailInput').value = found.email || '';
  }
});

document.getElementById('customerNameInput').addEventListener('input', function() {
  const found = customers.find(c => c.name === this.value);
  if (found) {
    document.getElementById('customerPhoneInput').value = found.phone || '';
    document.getElementById('customerEmailInput').value = found.email || '';
  }
});

<?php if (isset($_GET['add_item']) && $editing): ?>
document.addEventListener('DOMContentLoaded', function() {
  openModal('addItemModal');
});
<?php endif ?>
</script>

<style>
.dropdown { position: relative; display: inline-block; }
.dropdown-menu {
  display: none; position: absolute; right: 0; top: 100%;
  background: #fff; border: 1px solid #ddd; border-radius: 4px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15); min-width: 150px; z-index: 100;
}
.dropdown-menu.open { display: block; }
.dropdown-menu a {
  display: block; padding: 8px 12px; color: #333; text-decoration: none; font-size: 13px;
}
.dropdown-menu a:hover { background: #f5f5f5; }
</style>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>