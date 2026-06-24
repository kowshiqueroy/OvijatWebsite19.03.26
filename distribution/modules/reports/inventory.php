<?php
require_once '../../templates/header.php';
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER]);

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$product_ids = $_GET['product_ids'] ?? [];

$products = fetch_all("SELECT id, name FROM products WHERE isDelete = 0 ORDER BY name ASC");

// Prepare Product Filter for SQL
$product_filter_se = "";
$product_filter_si = "";
$product_filter_d = "";

if (!empty($product_ids)) {
    $ids_string = implode(',', array_map('intval', $product_ids));
    $product_filter_se = " AND se.product_id IN ($ids_string)";
    $product_filter_si = " AND si.product_id IN ($ids_string)";
    $product_filter_d = " AND d.product_id IN ($ids_string)";
}

// Construct the Master Stock Movement Query
$movements = [];

// 1. Stock In (Entries)
$stock_in = fetch_all("SELECT 'Stock IN' as type, se.quantity, se.created_at, p.name as product_name, 'Manual Entry' as reference, u.username as user
                       FROM stock_entries se 
                       JOIN products p ON se.product_id = p.id 
                       JOIN users u ON se.user_id = u.id
                       WHERE se.isDelete = 0 AND DATE(se.created_at) BETWEEN ? AND ? $product_filter_se", [$start_date, $end_date]);

// 2. Stock Out (Sales)
$stock_out = fetch_all("SELECT 'Stock OUT' as type, (si.billed_qty + si.free_qty) as quantity, s.confirmed_at as created_at, p.name as product_name, CONCAT('Invoice #', s.id) as reference, u.username as user
                        FROM sales_items si 
                        JOIN sales_drafts s ON si.draft_id = s.id 
                        JOIN products p ON si.product_id = p.id 
                        JOIN users u ON s.confirmed_by = u.id
                        WHERE s.isDelete = 0 AND s.status = 'Confirmed' AND DATE(s.confirmed_at) BETWEEN ? AND ? $product_filter_si", [$start_date, $end_date]);

// 3. Damages
$damages = fetch_all("SELECT 'Damage' as type, d.quantity, d.created_at, p.name as product_name, d.reason as reference, u.username as user
                      FROM stock_damages d 
                      JOIN products p ON d.product_id = p.id 
                      JOIN users u ON d.user_id = u.id
                      WHERE d.isDelete = 0 AND DATE(d.created_at) BETWEEN ? AND ? $product_filter_d", [$start_date, $end_date]);

// Merge and Sort
$movements = array_merge($stock_in, $stock_out, $damages);
usort($movements, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h3>Inventory Movement Report</h3>
        <p class="text-muted small mb-0">Consolidated view of all stock arrivals, sales, and damages.</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="copyInventoryWhatsApp(this)" class="btn btn-outline-success btn-sm"><i class="fa-brands fa-whatsapp me-1"></i>WhatsApp</button>
        <button onclick="downloadTableCSV('inventory_movements_<?php echo date('Y-m-d'); ?>.csv','#inv-table')" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
        <button onclick="window.print()" class="btn btn-dark btn-sm"><i class="fa-solid fa-print me-1"></i>Print</button>
    </div>
</div>

<div class="card shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Products (Optional)</label>
                <select name="product_ids[]" class="form-select select2" multiple data-placeholder="Select Products">
                    <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo (in_array($p['id'], $product_ids)) ? 'selected' : ''; ?>><?php echo $p['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="text-end mb-3 no-print">
    <button onclick="window.print()" class="btn btn-dark"><i class="fas fa-print me-1"></i> Print Report</button>
</div>

<div class="report-print-wrap bg-white p-4 shadow-sm">
    <div class="row border-bottom pb-3 mb-4 d-none d-print-flex">
        <div class="col-6">
            <h2 class="text-primary"><?php echo $company['name']; ?></h2>
            <p class="mb-0"><?php echo $company['address']; ?></p>
        </div>
        <div class="col-6 text-end">
            <h4>INVENTORY MOVEMENT REPORT</h4>
            <p class="mb-0"><strong>Period:</strong> <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle export-table" id="inv-table">
            <thead class="table-light">
                <tr>
                    <th>Date & Time</th>
                    <th>Type</th>
                    <th>Product</th>
                    <th class="text-center">Qty</th>
                    <th>Reference / Reason</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr><td colspan="6" class="text-center py-4">No inventory movements recorded for this period.</td></tr>
                <?php endif; ?>
                <?php foreach ($movements as $m): ?>
                <tr>
                    <td><?php echo date('d M Y, h:i A', strtotime($m['created_at'])); ?></td>
                    <td>
                        <?php 
                            $badge = [
                                'Stock IN' => 'bg-success',
                                'Stock OUT' => 'bg-primary',
                                'Damage' => 'bg-danger'
                            ][$m['type']] ?? 'bg-secondary';
                        ?>
                        <span class="badge <?php echo $badge; ?>"><?php echo $m['type']; ?></span>
                    </td>
                    <td><strong><?php echo $m['product_name']; ?></strong></td>
                    <td class="text-center fw-bold <?php echo ($m['type'] == 'Stock IN' ? 'text-success' : 'text-danger'); ?>">
                        <?php echo ($m['type'] == 'Stock IN' ? '+' : '-'); ?><?php echo $m['quantity']; ?>
                    </td>
                    <td><small><?php echo $m['reference']; ?></small></td>
                    <td><?php echo $m['user']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    @media print {
        #sidebar-wrapper, .navbar, .no-print, .alert { display: none !important; }
        #page-content-wrapper { padding: 0 !important; width: 100% !important; margin: 0 !important; }
        .container-fluid { padding: 0 !important; }
        .report-print-wrap { box-shadow: none !important; padding: 0 !important; }
        .table-light { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; }
    }
</style>

<script>
const INV_MOVEMENTS = <?php echo json_encode(array_map(fn($m) => [
    'type'    => $m['type'],
    'product' => $m['product_name'],
    'qty'     => $m['quantity'],
    'ref'     => $m['reference'],
    'user'    => $m['user'],
    'date'    => $m['created_at'],
], $movements)); ?>;

function copyInventoryWhatsApp(btn) {
    const period = '<?php echo $start_date; ?> to <?php echo $end_date; ?>';
    let in_qty = 0, out_qty = 0, dmg_qty = 0;
    INV_MOVEMENTS.forEach(m => {
        if (m.type === 'Stock IN') in_qty += parseInt(m.qty);
        else if (m.type === 'Stock OUT') out_qty += parseInt(m.qty);
        else if (m.type === 'Damage') dmg_qty += parseInt(m.qty);
    });
    let text = '*Inventory Movement — ' + period + '*\n';
    text += '─'.repeat(32) + '\n';
    text += '📦 Stock IN:  ' + in_qty.toLocaleString() + ' units\n';
    text += '📤 Stock OUT: ' + out_qty.toLocaleString() + ' units\n';
    text += '⚠ Damage:    ' + dmg_qty.toLocaleString() + ' units\n';
    text += '─'.repeat(32) + '\n';
    text += 'Net Change: ' + (in_qty - out_qty - dmg_qty).toLocaleString() + ' units';
    copyText(text, btn);
}
</script>
<?php require_once '../../templates/footer.php'; ?>
