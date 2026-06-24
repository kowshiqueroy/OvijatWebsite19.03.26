<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) redirect('modules/returns/index.php', 'CSRF failed.', 'danger');

    if (isset($_POST['process_return'])) {
        $return_id = intval($_POST['return_id']);
        $action    = $_POST['action']; // approve or reject
        $ret       = fetch_one("SELECT * FROM sales_returns WHERE id = ? AND status = 'Pending'", [$return_id]);
        if (!$ret) redirect('modules/returns/index.php', 'Return not found.', 'danger');

        $conn = get_db_connection();
        $conn->begin_transaction();
        try {
            $now = date('Y-m-d H:i:s');
            if ($action === 'approve') {
                db_query("UPDATE sales_returns SET status='Approved', processed_by=?, processed_at=? WHERE id=?",
                    [$_SESSION['user_id'], $now, $return_id]);

                // Restock if requested
                if ($ret['restock']) {
                    $items = fetch_all("SELECT * FROM sales_return_items WHERE return_id = ? AND isDelete = 0", [$return_id]);
                    foreach ($items as $item) {
                        db_query("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?", [$item['quantity'], $item['product_id']]);
                        db_query("INSERT INTO stock_movements (product_id,movement_type,quantity,reference_type,reference_id,notes,created_by) VALUES (?,'RETURN',?,'return',?,?,?)",
                            [$item['product_id'], $item['quantity'], $return_id, "Return #$return_id approved", $_SESSION['user_id']]);
                    }
                }

                // Refund customer balance
                db_query("UPDATE customers SET balance = balance + ? WHERE id = ?", [$ret['total_amount'], $ret['customer_id']]);
                db_query("INSERT INTO transactions (customer_id,type,amount,description) VALUES (?,?,'Credit',?)",
                    [$ret['customer_id'], $ret['total_amount'], "Return #$return_id approved — credit issued"]);

                $msg = 'Return approved. Stock restocked and customer credited.';
            } else {
                db_query("UPDATE sales_returns SET status='Rejected', processed_by=?, processed_at=? WHERE id=?",
                    [$_SESSION['user_id'], $now, $return_id]);
                $msg = 'Return rejected.';
            }

            $conn->commit();
            log_activity($_SESSION['user_id'], "$action Return #$return_id");
            redirect('modules/returns/index.php', $msg, $action === 'approve' ? 'success' : 'warning');
        } catch (Exception $e) {
            $conn->rollback();
            redirect('modules/returns/index.php', "Error: " . $e->getMessage(), 'danger');
        }
    }

    // New return request
    if (isset($_POST['create_return'])) {
        $sale_id     = intval($_POST['sale_id']);
        $customer_id = intval($_POST['customer_id']);
        $reason      = sanitize($_POST['reason'] ?? '');
        $restock     = isset($_POST['restock']) ? 1 : 0;
        $product_ids = $_POST['return_product_id'] ?? [];
        $qtys        = $_POST['return_qty'] ?? [];
        $rates       = $_POST['return_rate'] ?? [];

        $total = 0;
        foreach ($product_ids as $i => $pid) {
            $total += floatval($qtys[$i] ?? 0) * floatval($rates[$i] ?? 0);
        }

        $conn = get_db_connection();
        $conn->begin_transaction();
        try {
            db_query("INSERT INTO sales_returns (sale_id,customer_id,reason,total_amount,status,restock) VALUES (?,?,?,?,'Pending',?)",
                [$sale_id, $customer_id, $reason, $total, $restock]);
            $rid = $conn->insert_id;
            foreach ($product_ids as $i => $pid) {
                $pid = intval($pid);
                if (!$pid) continue;
                $qty  = intval($qtys[$i] ?? 0);
                $rate = floatval($rates[$i] ?? 0);
                if ($qty <= 0) continue;
                db_query("INSERT INTO sales_return_items (return_id,product_id,quantity,unit_rate,total) VALUES (?,?,?,?,?)",
                    [$rid, $pid, $qty, $rate, $qty*$rate]);
            }
            $conn->commit();
            log_activity($_SESSION['user_id'], "Created return request #$rid for Invoice #$sale_id");
            redirect('modules/returns/index.php', "Return request #$rid created, pending approval.");
        } catch (Exception $e) {
            $conn->rollback();
            redirect('modules/returns/index.php', "Error: " . $e->getMessage(), 'danger');
        }
    }
}

$status_filter = $_GET['status'] ?? '';
$sql = "SELECT sr.*, c.name as customer_name, sd.id as invoice_no, u.username as processor_name
        FROM sales_returns sr
        LEFT JOIN customers c ON sr.customer_id = c.id
        LEFT JOIN sales_drafts sd ON sr.sale_id = sd.id
        LEFT JOIN users u ON sr.processed_by = u.id
        WHERE sr.isDelete = 0";
$params = [];
if ($status_filter) { $sql .= " AND sr.status = ?"; $params[] = $status_filter; }
$sql .= " ORDER BY sr.created_at DESC";
$returns = fetch_all($sql, $params);
$confirmed_sales = fetch_all("SELECT s.id, c.name as customer_name FROM sales_drafts s JOIN customers c ON s.customer_id = c.id WHERE s.status='Confirmed' AND s.isDelete=0 ORDER BY s.id DESC LIMIT 100");
$products = fetch_all("SELECT id, name, retail_rate FROM products WHERE isDelete=0 AND is_active=1 ORDER BY name");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3>Return Requests</h3>
        <p class="text-muted small mb-0">Manage customer sales returns and restocking.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReturnModal">
        <i class="fa-solid fa-rotate-left me-2"></i>New Return
    </button>
</div>

<!-- Status filter tabs -->
<div class="card mb-3">
    <div class="card-body py-2 d-flex gap-2">
        <?php foreach ([''=>'All','Pending'=>'Pending','Approved'=>'Approved','Rejected'=>'Rejected'] as $val=>$lbl): ?>
            <a href="?status=<?php echo $val; ?>" class="btn btn-sm <?php echo $status_filter===$val ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $lbl; ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Date</th><th>Customer</th><th>Invoice</th><th class="text-end">Amount</th><th>Restock</th><th>Status</th><th>Processed By</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($returns as $r): ?>
                    <tr>
                        <td><strong>#<?php echo $r['id']; ?></strong></td>
                        <td><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($r['customer_name'] ?? '—'); ?></td>
                        <td>#<?php echo str_pad($r['invoice_no'], 6, '0', STR_PAD_LEFT); ?></td>
                        <td class="text-end fw-bold"><?php echo format_currency($r['total_amount']); ?></td>
                        <td><?php echo $r['restock'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                        <td>
                            <?php $sc = ['Pending'=>'warning','Approved'=>'success','Rejected'=>'danger'][$r['status']] ?? 'secondary'; ?>
                            <span class="badge bg-<?php echo $sc; ?>"><?php echo $r['status']; ?></span>
                        </td>
                        <td class="small"><?php echo htmlspecialchars($r['processor_name'] ?? '—'); ?></td>
                        <td>
                            <?php if ($r['status'] === 'Pending'): ?>
                            <form method="POST" class="d-inline">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="return_id" value="<?php echo $r['id']; ?>">
                                <button type="submit" name="process_return" value="process_return" onclick="this.form.action.value='approve'" class="btn btn-sm btn-success" onclick="return confirm('Approve this return?')"
                                    formaction="?action=do" style="display:none"></button>
                                <button type="submit" name="process_return" class="btn btn-sm btn-success" onclick="this.form.querySelector('[name=action]').value='approve';return confirm('Approve?')">
                                    <input type="hidden" name="action" value="approve">
                                    <i class="fa-solid fa-check"></i> Approve
                                </button>
                                <button type="submit" name="process_return" class="btn btn-sm btn-outline-danger" onclick="this.form.querySelector('[name=action]').value='reject';return confirm('Reject?')">
                                    <i class="fa-solid fa-times"></i> Reject
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No return requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- New Return Modal -->
<div class="modal fade" id="newReturnModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title">Create Return Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Invoice <span class="text-danger">*</span></label>
                        <select name="sale_id" class="form-select select2" required id="ret_invoice">
                            <option value="">— Select Invoice —</option>
                            <?php foreach ($confirmed_sales as $cs): ?>
                                <option value="<?php echo $cs['id']; ?>" data-customer="<?php echo $cs['customer_name']; ?>">
                                    #<?php echo str_pad($cs['id'],6,'0',STR_PAD_LEFT); ?> — <?php echo htmlspecialchars($cs['customer_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Customer ID <span class="text-danger">*</span></label>
                        <input type="number" name="customer_id" class="form-control" required placeholder="Customer ID">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g. Damaged goods">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="restock" id="ret_restock" checked>
                            <label class="form-check-label" for="ret_restock">Restock inventory</label>
                        </div>
                    </div>
                </div>
                <table class="table table-sm table-bordered">
                    <thead class="table-light"><tr><th>Product</th><th>Qty</th><th>Rate</th><th></th></tr></thead>
                    <tbody id="ret-body">
                        <tr class="ret-row">
                            <td><select name="return_product_id[]" class="form-select form-select-sm" required>
                                <option value="">— Product —</option>
                                <?php foreach ($products as $p): ?><option value="<?php echo $p['id']; ?>" data-rate="<?php echo $p['retail_rate']; ?>"><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?>
                            </select></td>
                            <td><input type="number" name="return_qty[]" class="form-control form-control-sm" min="1" value="1" required></td>
                            <td><input type="number" step="0.01" name="return_rate[]" class="form-control form-control-sm" value="0" required></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-ret-row"><i class="fa-solid fa-times"></i></button></td>
                        </tr>
                    </tbody>
                    <tfoot><tr><td colspan="4"><button type="button" id="add-ret-row" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-plus me-1"></i>Add Row</button></td></tr></tfoot>
                </table>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="create_return" class="btn btn-primary">Submit Return</button></div>
        </form>
    </div>
</div>
<script>
document.getElementById('ret-body').addEventListener('click', function(e) {
    if (e.target.closest('.remove-ret-row') && document.querySelectorAll('.ret-row').length > 1)
        e.target.closest('tr').remove();
});
document.getElementById('add-ret-row').addEventListener('click', function() {
    const first = document.querySelector('.ret-row');
    const clone = first.cloneNode(true);
    clone.querySelectorAll('input,select').forEach(el => el.value = el.tagName==='SELECT' ? '' : (el.type==='number' ? (el.name.includes('qty') ? '1' : '0') : ''));
    document.getElementById('ret-body').appendChild(clone);
});
</script>
<?php require_once '../../templates/footer.php'; ?>
