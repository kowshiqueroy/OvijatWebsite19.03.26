<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER]);

// Handle new purchase order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purchase'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) redirect('modules/purchase/index.php', 'CSRF failed.', 'danger');

    $supplier_id = intval($_POST['supplier_id']);
    $invoice_no  = sanitize($_POST['invoice_no'] ?? '');
    $notes       = sanitize($_POST['notes'] ?? '');
    $product_ids = $_POST['product_id'] ?? [];
    $qtys        = $_POST['qty'] ?? [];
    $costs       = $_POST['unit_cost'] ?? [];
    $batches     = $_POST['batch_no'] ?? [];
    $expiries    = $_POST['expiry_date'] ?? [];

    if (empty($product_ids)) redirect('modules/purchase/index.php', 'Add at least one product.', 'danger');

    $total = 0;
    foreach ($product_ids as $i => $pid) {
        if (empty($pid)) continue;
        $total += floatval($qtys[$i] ?? 0) * floatval($costs[$i] ?? 0);
    }

    $conn = get_db_connection();
    $conn->begin_transaction();
    try {
        db_query("INSERT INTO purchase_orders (supplier_id, invoice_no, total_amount, status, notes) VALUES (?,?,?,'Received',?)",
            [$supplier_id, $invoice_no, $total, $notes]);
        $po_id = $conn->insert_id;

        foreach ($product_ids as $i => $pid) {
            $pid = intval($pid);
            if (!$pid) continue;
            $qty     = intval($qtys[$i] ?? 0);
            $cost    = floatval($costs[$i] ?? 0);
            $batch   = sanitize($batches[$i] ?? '');
            $expiry  = $expiries[$i] ?: null;
            if ($qty <= 0) continue;

            db_query("INSERT INTO purchase_items (purchase_id,product_id,batch_no,expiry_date,quantity,unit_cost,total) VALUES (?,?,?,?,?,?,?)",
                [$po_id, $pid, $batch, $expiry, $qty, $cost, $qty*$cost]);

            // Update stock
            db_query("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?", [$qty, $pid]);

            // Create batch record if batch given
            if ($batch) {
                db_query("INSERT INTO product_batches (product_id,batch_no,expiry_date,quantity_in,quantity_remaining,purchase_id,source) VALUES (?,?,?,?,?,?,'Purchase')",
                    [$pid, $batch, $expiry, $qty, $qty, $po_id]);
            }
            // Stock movement
            db_query("INSERT INTO stock_movements (product_id,movement_type,quantity,reference_type,reference_id,notes,created_by) VALUES (?,'IN',?,'purchase',?,?,?)",
                [$pid, $qty, $po_id, "Purchase Order #$po_id", $_SESSION['user_id']]);
        }

        // Update supplier balance
        db_query("UPDATE suppliers SET balance = balance + ? WHERE id = ?", [$total, $supplier_id]);
        db_query("INSERT INTO supplier_transactions (supplier_id,purchase_id,type,amount,description) VALUES (?,?,'Payable',?,?)",
            [$supplier_id, $po_id, $total, "Purchase Order #$po_id"]);

        $conn->commit();
        log_activity($_SESSION['user_id'], "Created Purchase Order #$po_id — ৳$total");
        redirect('modules/purchase/index.php', "Purchase Order #$po_id created and stock updated.");
    } catch (Exception $e) {
        $conn->rollback();
        redirect('modules/purchase/index.php', "Error: " . $e->getMessage(), 'danger');
    }
}

$purchases  = fetch_all("SELECT po.*, s.name as supplier_name FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id=s.id WHERE po.isDelete=0 ORDER BY po.created_at DESC");
$suppliers  = fetch_all("SELECT id,name FROM suppliers WHERE isDelete=0 AND is_active=1 ORDER BY name");
$products   = fetch_all("SELECT id,name,tp_rate FROM products WHERE isDelete=0 AND is_active=1 ORDER BY name");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>Purchase Orders</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#poModal">
        <i class="fa-solid fa-plus me-2"></i>New Purchase Order
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Date</th><th>Supplier</th><th>Invoice No</th><th class="text-end">Total</th><th>Status</th><th>Notes</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td><strong>#<?php echo $p['id']; ?></strong></td>
                        <td><?php echo date('d M Y', strtotime($p['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($p['supplier_name'] ?? '—'); ?></td>
                        <td><code><?php echo htmlspecialchars($p['invoice_no'] ?? '—'); ?></code></td>
                        <td class="text-end fw-bold"><?php echo format_currency($p['total_amount']); ?></td>
                        <td>
                            <?php $sc = ['Received'=>'success','Partial'=>'warning','Paid'=>'info','Draft'=>'secondary'][$p['status']] ?? 'secondary'; ?>
                            <span class="badge bg-<?php echo $sc; ?>"><?php echo $p['status']; ?></span>
                        </td>
                        <td class="small text-muted"><?php echo htmlspecialchars($p['notes'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($purchases)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No purchase orders yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- New PO Modal -->
<div class="modal fade" id="poModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title">New Purchase Order</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-select select2" required>
                            <option value="">— Select Supplier —</option>
                            <?php foreach ($suppliers as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Supplier Invoice No</label>
                        <input type="text" name="invoice_no" class="form-control" placeholder="e.g. INV-001">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control">
                    </div>
                </div>
                <hr>
                <table class="table table-bordered" id="po-items-table">
                    <thead class="table-light">
                        <tr><th>Product</th><th>Batch No</th><th>Expiry Date</th><th>Qty</th><th>Unit Cost</th><th>Total</th><th></th></tr>
                    </thead>
                    <tbody id="po-body">
                        <tr class="po-row">
                            <td><select name="product_id[]" class="form-select form-select-sm select2 po-product" required>
                                <option value="">— Product —</option>
                                <?php foreach ($products as $p): ?><option value="<?php echo $p['id']; ?>" data-cost="<?php echo $p['tp_rate']; ?>"><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?>
                            </select></td>
                            <td><input type="text" name="batch_no[]" class="form-control form-control-sm" placeholder="LOT-001"></td>
                            <td><input type="date" name="expiry_date[]" class="form-control form-control-sm"></td>
                            <td><input type="number" name="qty[]" class="form-control form-control-sm po-qty" min="1" value="1"></td>
                            <td><input type="number" step="0.01" name="unit_cost[]" class="form-control form-control-sm po-cost" value="0"></td>
                            <td><input type="number" step="0.01" class="form-control form-control-sm po-line-total" readonly value="0"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-po-row"><i class="fa-solid fa-times"></i></button></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="7"><button type="button" class="btn btn-sm btn-outline-primary" id="add-po-row"><i class="fa-solid fa-plus me-1"></i>Add Row</button></td></tr>
                    </tfoot>
                </table>
                <div class="text-end fw-bold fs-5 mt-2">Grand Total: <span id="po-grand-total">৳ 0.00</span></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="save_purchase" class="btn btn-primary">Save Purchase Order</button></div>
        </form>
    </div>
</div>
<template id="po-row-template">
    <tr class="po-row">
        <td><select name="product_id[]" class="form-select form-select-sm po-product" required>
            <option value="">— Product —</option>
            <?php foreach ($products as $p): ?><option value="<?php echo $p['id']; ?>" data-cost="<?php echo $p['tp_rate']; ?>"><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?>
        </select></td>
        <td><input type="text" name="batch_no[]" class="form-control form-control-sm" placeholder="LOT-001"></td>
        <td><input type="date" name="expiry_date[]" class="form-control form-control-sm"></td>
        <td><input type="number" name="qty[]" class="form-control form-control-sm po-qty" min="1" value="1"></td>
        <td><input type="number" step="0.01" name="unit_cost[]" class="form-control form-control-sm po-cost" value="0"></td>
        <td><input type="number" step="0.01" class="form-control form-control-sm po-line-total" readonly value="0"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-po-row"><i class="fa-solid fa-times"></i></button></td>
    </tr>
</template>
<script>
function calcPO() {
    let grand = 0;
    document.querySelectorAll('.po-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.po-qty')?.value) || 0;
        const cost = parseFloat(row.querySelector('.po-cost')?.value) || 0;
        const total = qty * cost;
        const lt = row.querySelector('.po-line-total');
        if (lt) lt.value = total.toFixed(2);
        grand += total;
    });
    document.getElementById('po-grand-total').textContent = '৳ ' + grand.toLocaleString('en-BD', {minimumFractionDigits:2});
}
document.getElementById('po-body').addEventListener('input', function(e) {
    if (e.target.classList.contains('po-product')) {
        const opt = e.target.options[e.target.selectedIndex];
        const row = e.target.closest('tr');
        if (opt && row) row.querySelector('.po-cost').value = opt.dataset.cost || '0';
    }
    calcPO();
});
document.getElementById('po-body').addEventListener('click', function(e) {
    if (e.target.closest('.remove-po-row')) {
        if (document.querySelectorAll('.po-row').length > 1) {
            e.target.closest('tr').remove();
            calcPO();
        }
    }
});
document.getElementById('add-po-row').addEventListener('click', function() {
    const clone = document.getElementById('po-row-template').content.cloneNode(true);
    document.getElementById('po-body').appendChild(clone);
});
</script>
<?php require_once '../../templates/footer.php'; ?>
