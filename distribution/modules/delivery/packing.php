<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER]);

$load_id = intval($_GET['load_id'] ?? 0);
if (!$load_id) redirect('modules/delivery/index.php', 'Invalid truck load.', 'danger');

$load = fetch_one("SELECT * FROM truck_loads WHERE id = ? AND isDelete = 0", [$load_id]);
if (!$load) redirect('modules/delivery/index.php', 'Truck load not found.', 'danger');

// Handle FEFO allocation save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        redirect("modules/delivery/packing.php?load_id=$load_id", 'CSRF failed.', 'danger');
    }

    $conn = get_db_connection();
    $conn->begin_transaction();
    try {
        // allocation: sales_item_id => [batch_id => qty, ...]
        $allocs = $_POST['alloc'] ?? []; // alloc[sales_item_id][batch_id] = qty

        foreach ($allocs as $item_id => $batch_qtys) {
            $item_id = intval($item_id);
            // Remove old allocations for this item
            db_query("UPDATE sales_item_lots SET isDelete = 1 WHERE sales_item_id = ?", [$item_id]);

            foreach ($batch_qtys as $batch_id => $qty) {
                $batch_id = intval($batch_id);
                $qty      = intval($qty);
                if ($qty <= 0) continue;

                // Insert picking record
                db_query("INSERT INTO sales_item_lots (sales_item_id, batch_id, quantity) VALUES (?,?,?)",
                    [$item_id, $batch_id, $qty]);

                // Deduct from batch
                db_query("UPDATE product_batches SET quantity_remaining = quantity_remaining - ? WHERE id = ?",
                    [$qty, $batch_id]);

                // Log movement — fetch item info first, then insert cleanly
                $item_info = fetch_one("SELECT product_id, draft_id FROM sales_items WHERE id = ?", [$item_id]);
                if ($item_info) {
                    db_query("INSERT INTO stock_movements (product_id, batch_id, movement_type, quantity, reference_type, reference_id, notes, created_by) VALUES (?,?,'OUT',?,'sale',?,?,?)",
                        [$item_info['product_id'], $batch_id, $qty, $item_info['draft_id'], "Packed for Load #$load_id", $_SESSION['user_id']]);
                }
            }
        }

        $conn->commit();
        log_activity($_SESSION['user_id'], "Packing complete for Truck Load #$load_id");
        redirect("modules/delivery/view.php?id=$load_id", "Packing confirmed! Lot allocations saved.", 'success');
    } catch (Exception $e) {
        $conn->rollback();
        redirect("modules/delivery/packing.php?load_id=$load_id", "Error saving packing: " . $e->getMessage(), 'danger');
    }
}

// Fetch invoices in this load
$invoice_ids_raw = fetch_all("SELECT invoice_id FROM truck_load_items WHERE truck_load_id = ? AND isDelete = 0", [$load_id]);
$invoice_ids = array_column($invoice_ids_raw, 'invoice_id');

if (empty($invoice_ids)) {
    redirect("modules/delivery/view.php?id=$load_id", 'No invoices in this load.', 'warning');
}

// Fetch all items across all invoices in this load
$placeholders = implode(',', array_fill(0, count($invoice_ids), '?'));
$items = fetch_all(
    "SELECT si.*, p.name as product_name, sd.id as invoice_id,
            c.name as customer_name
     FROM sales_items si
     JOIN products p ON si.product_id = p.id
     JOIN sales_drafts sd ON si.draft_id = sd.id
     JOIN customers c ON sd.customer_id = c.id
     WHERE si.draft_id IN ($placeholders) AND si.isDelete = 0 AND p.isDelete = 0
     ORDER BY sd.id, p.name",
    $invoice_ids
);

// For each item, fetch available batches (FEFO order: oldest expiry first)
$today = date('Y-m-d');
foreach ($items as &$item) {
    $item['batches'] = fetch_all(
        "SELECT * FROM product_batches
         WHERE product_id = ? AND quantity_remaining > 0 AND isDelete = 0
           AND (expiry_date IS NULL OR expiry_date >= ?)
         ORDER BY expiry_date ASC, created_at ASC",
        [$item['product_id'], $today]
    );

    // Auto-allocate FEFO: fill from oldest lot first
    $needed = $item['billed_qty'] + $item['free_qty'];
    $remaining = $needed;
    foreach ($item['batches'] as &$b) {
        $b['auto_alloc'] = 0;
        if ($remaining <= 0) continue;
        $take = min($b['quantity_remaining'], $remaining);
        $b['auto_alloc'] = $take;
        $remaining -= $take;
    }
    unset($b);
    $item['unallocated'] = $remaining; // > 0 means stock shortage
}
unset($item);
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h3><i class="fas fa-box-open me-2"></i>Packing Screen — Truck Load #<?php echo $load_id; ?></h3>
        <p class="text-muted small mb-0">
            Truck: <strong><?php echo htmlspecialchars($load['truck_no']); ?></strong> |
            Driver: <strong><?php echo htmlspecialchars($load['driver_name']); ?></strong> |
            System auto-allocated lots using <span class="badge bg-info">FEFO</span> (First Expired, First Out). You can override.
        </p>
    </div>
    <div class="col-auto">
        <a href="view.php?id=<?php echo $load_id; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Load
        </a>
    </div>
</div>

<form method="POST">
    <?php csrf_field(); ?>

<?php
$current_invoice = null;
foreach ($items as $item):
    if ($current_invoice !== $item['invoice_id']):
        if ($current_invoice !== null) echo '</div></div></div>'; // close previous invoice
        $current_invoice = $item['invoice_id'];
?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <strong>Invoice #<?php echo str_pad($item['invoice_id'], 6, '0', STR_PAD_LEFT); ?></strong>
            <span class="text-muted small"><?php echo htmlspecialchars($item['customer_name']); ?></span>
        </div>
        <div class="card-body p-0">
<?php endif; ?>

    <div class="border-bottom p-3">
        <div class="row align-items-start">
            <div class="col-md-3">
                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                <div class="small text-muted mt-1">
                    Billed: <span class="badge bg-primary"><?php echo $item['billed_qty']; ?></span>
                    <?php if ($item['free_qty']): ?>
                        Free: <span class="badge bg-success"><?php echo $item['free_qty']; ?></span>
                    <?php endif; ?>
                    Total needed: <strong><?php echo $item['billed_qty'] + $item['free_qty']; ?></strong>
                </div>
                <?php if ($item['unallocated'] > 0): ?>
                    <div class="badge bg-danger mt-1">⚠ <?php echo $item['unallocated']; ?> units short!</div>
                <?php endif; ?>
            </div>
            <div class="col-md-9">
                <?php if (empty($item['batches'])): ?>
                    <div class="alert alert-warning py-2 mb-0">No tracked batches available. Stock will be deducted from general stock.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Batch / Lot</th>
                                <th>Location</th>
                                <th>Expiry</th>
                                <th class="text-center">Available</th>
                                <th style="width:110px;">Pick Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($item['batches'] as $b):
                            $days_left = $b['expiry_date'] ? ceil((strtotime($b['expiry_date']) - strtotime($today)) / 86400) : null;
                            $exp_class = '';
                            if ($days_left !== null && $days_left <= 30) $exp_class = 'text-warning fw-bold';
                        ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($b['batch_no']); ?></code></td>
                                <td class="small"><?php echo htmlspecialchars($b['location'] ?? '—'); ?></td>
                                <td class="small <?php echo $exp_class; ?>">
                                    <?php echo $b['expiry_date'] ? date('d M Y', strtotime($b['expiry_date'])) : '—'; ?>
                                    <?php if ($days_left !== null && $days_left <= 30): ?>
                                        <span class="badge bg-warning text-dark ms-1"><?php echo $days_left; ?>d</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo $b['quantity_remaining']; ?></td>
                                <td>
                                    <input type="number"
                                           name="alloc[<?php echo $item['id']; ?>][<?php echo $b['id']; ?>]"
                                           class="form-control form-control-sm text-center"
                                           value="<?php echo $b['auto_alloc']; ?>"
                                           min="0"
                                           max="<?php echo $b['quantity_remaining']; ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php endforeach; ?>
<?php if ($current_invoice !== null) echo '</div></div>'; ?>

    <div class="d-flex justify-content-end mt-4 gap-3">
        <a href="view.php?id=<?php echo $load_id; ?>" class="btn btn-secondary btn-lg">Cancel</a>
        <button type="submit" class="btn btn-success btn-lg">
            <i class="fas fa-check me-2"></i> Confirm Packing & Save Lot Allocations
        </button>
    </div>
</form>

<?php require_once '../../templates/footer.php'; ?>
