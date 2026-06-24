<?php
require_once '../../templates/header.php';
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_SR, ROLE_CUSTOMER, ROLE_VIEWER]);

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Filters
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$delivery_status = $_GET['delivery_status'] ?? '';
$customer_id = $_GET['customer_id'] ?? '';
$order_type_filter = $_GET['order_type'] ?? '';

// Build Query
$where_clauses = ["s.isDelete = 0", "c.isDelete = 0"];
$params = [];

// Viewer order type restriction
$ot_filter = get_order_type_filter('s');
if ($ot_filter) $where_clauses[] = $ot_filter;

if ($start_date) {
    $where_clauses[] = "DATE(s.created_at) >= ?";
    $params[] = $start_date;
}
if ($end_date) {
    $where_clauses[] = "DATE(s.created_at) <= ?";
    $params[] = $end_date;
}
if ($status) {
    $where_clauses[] = "s.status = ?";
    $params[] = $status;
}
if ($delivery_status) {
    $where_clauses[] = "s.delivery_status = ?";
    $params[] = $delivery_status;
}
if ($order_type_filter) {
    $where_clauses[] = "s.order_type = ?";
    $params[] = $order_type_filter;
}
if ($customer_id) {
    $where_clauses[] = "s.customer_id = ?";
    $params[] = $customer_id;
}

// Role-based constraints
if ($role == ROLE_CUSTOMER) {
    $cust = fetch_one("SELECT id FROM customers WHERE user_id = ? AND isDelete = 0", [$user_id]);
    $where_clauses[] = "s.customer_id = ?";
    $params[] = $cust['id'];
} elseif ($role == ROLE_SR) {
    $where_clauses[] = "s.created_by = ?";
    $params[] = $user_id;
}

$where_sql = implode(" AND ", $where_clauses);
$sql = "SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address, u.username as creator_name 
        FROM sales_drafts s 
        JOIN customers c ON s.customer_id = c.id 
        JOIN users u ON s.created_by = u.id 
        WHERE $where_sql 
        ORDER BY s.created_at DESC";

$sales = fetch_all($sql, $params);

// Fetch customers for filter (Admin/Manager/SR only)
$all_customers = [];
if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_SR, ROLE_VIEWER])) {
    $all_customers = fetch_all("SELECT id, name FROM customers WHERE isDelete = 0 ORDER BY name ASC");
}

// For Global Copy: Fetch items for each sale in the list
foreach ($sales as $key => $s) {
    $sales[$key]['items'] = fetch_all("SELECT i.*, p.name as product_name FROM sales_items i JOIN products p ON i.product_id = p.id WHERE i.draft_id = ? AND i.isDelete = 0", [$s['id']]);
}
?>

<div class="row no-print">
    <div class="col-12 d-flex justify-content-between align-items-center mb-4">
        <h3>Sales Records</h3>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-success btn-sm" onclick="copyGlobalSummary(this)"><i class="fa-brands fa-whatsapp me-1"></i> WhatsApp</button>
            <button onclick="downloadTableCSV('sales_<?php echo date('Y-m-d'); ?>.csv', '#sales-table')" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-file-csv me-1"></i> CSV</button>
            <?php if ($role != ROLE_VIEWER): ?>
            <a href="pos.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i> New Sale</a>
            <?php endif; ?>
            <?php if (in_array($role, [ROLE_ADMIN, ROLE_ACCOUNTANT, ROLE_MANAGER])): ?>
            <button type="button" id="create-truck-load" class="btn btn-dark d-none"><i class="fas fa-truck me-2"></i> Create Truck Load</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="card shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label small">Start Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">End Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Invoice Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="Draft" <?php echo $status == 'Draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="Confirmed" <?php echo $status == 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Delivery</label>
                <select name="delivery_status" class="form-select form-select-sm">
                    <option value="">All Delivery</option>
                    <?php foreach (['Pending', 'Loading', 'In Transit', 'Delivered', 'Failed', 'Returned'] as $ds): ?>
                        <option value="<?php echo $ds; ?>" <?php echo $delivery_status == $ds ? 'selected' : ''; ?>><?php echo $ds; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($all_customers): ?>
            <div class="col-md-3">
                <label class="form-label small">Customer</label>
                <select name="customer_id" class="form-select form-select-sm select2">
                    <option value="">All Customers</option>
                    <?php foreach ($all_customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $customer_id == $c['id'] ? 'selected' : ''; ?>><?php echo $c['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label small">Order Type</label>
                <select name="order_type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <?php foreach (['Local','Export','Custom','DMD'] as $ot): ?>
                        <option value="<?php echo $ot; ?>" <?php echo $order_type_filter == $ot ? 'selected' : ''; ?>><?php echo $ot; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<form id="bulk-actions-form" action="../delivery/create.php" method="POST">
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle export-table" id="sales-table">
                <thead class="table-light">
                    <tr>
                        <th data-no-export style="width: 40px;"><input type="checkbox" id="select-all"></th>
                        <th>Draft #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Delivery Status</th>
                        <th data-no-export>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $s): ?>
                    <tr>
                        <td>
                            <?php if ($s['status'] == 'Confirmed' && $s['delivery_status'] == 'Pending'): ?>
                                <input type="checkbox" name="invoice_ids[]" value="<?php echo $s['id']; ?>" class="invoice-checkbox">
                            <?php endif; ?>
                        </td>
                        <td><strong>#<?php echo $s['id']; ?></strong></td>
                        <td><?php echo date('d M Y', strtotime($s['created_at'])); ?></td>
                        <td><?php echo $s['customer_name']; ?></td>
                        <td>
                            <?php
                            $ot_colors = ['Local'=>'bg-primary','Export'=>'bg-success','Custom'=>'bg-warning text-dark','DMD'=>'bg-info'];
                            $ot = $s['order_type'] ?? 'Local';
                            echo '<span class="badge ' . ($ot_colors[$ot] ?? 'bg-secondary') . '">' . $ot . '</span>';
                            ?>
                        </td>
                        <td><strong><?php echo format_currency($s['grand_total']); ?></strong></td>
                        <td>
                            <?php if ($s['status'] == 'Draft'): ?>
                                <span class="badge bg-warning text-dark">DRAFT</span>
                            <?php else: ?>
                                <span class="badge bg-success">CONFIRMED</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($s['status'] == 'Confirmed'): ?>
                                <?php 
                                    $delivery_color = [
                                        'Pending' => 'bg-secondary',
                                        'Loading' => 'bg-info',
                                        'In Transit' => 'bg-primary',
                                        'Delivered' => 'bg-success',
                                        'Failed' => 'bg-danger',
                                        'Returned' => 'bg-warning text-dark'
                                    ][$s['delivery_status']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?php echo $delivery_color; ?>"><?php echo strtoupper($s['delivery_status']); ?></span>
                                <?php if ($s['delivery_date']): ?>
                                    <div class="small text-muted mt-1"><?php echo date('d M y', strtotime($s['delivery_date'])); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="view.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-info" title="View"><i class="fas fa-eye"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="copyInvoiceDetails(<?php echo $s['id']; ?>)" title="Copy for WhatsApp"><i class="fab fa-whatsapp"></i></button>
                                <?php if ($s['status'] == 'Draft'): ?>
                                    <a href="edit.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                                <?php if ($s['status'] == 'Draft' && (in_array($role, [ROLE_ADMIN, ROLE_ACCOUNTANT]))): ?>
                                    <a href="confirm.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to confirm this sale? This will deduct stock and update customer balance.')">
                                        Confirm
                                    </a>
                                <?php endif; ?>
                                <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                                    <a href="../admin/delete_record.php?table=sales_drafts&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-danger" title="Master Delete" onclick="return confirm('Delete this sale permanently?')"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</form>

<script>
// Store sales data for JavaScript copy functions
const salesData = <?php echo json_encode($sales); ?>;

function copyInvoiceDetails(id) {
    const sale = salesData.find(s => s.id == id);
    if (!sale) return;

    let text = `*OFFICIAL SALES INVOICE* 📄\n`;
    text += `*Invoice #:* ${String(sale.id).padStart(6, '0')}\n`;
    text += `*Date:* ${new Date(sale.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}\n\n`;

    text += `*CUSTOMER INFORMATION*\n`;
    text += `👤 Name: ${sale.customer_name}\n`;
    text += `📞 Phone: ${sale.customer_phone}\n`;
    text += `📍 Address: ${sale.customer_address}\n\n`;

    const billedItems = sale.items.filter(i => parseInt(i.billed_qty) > 0);
    const freeItems = sale.items.filter(i => parseInt(i.free_qty) > 0);

    if (billedItems.length > 0) {
        text += `*--- BILLED ITEMS ---*\n`;
        billedItems.forEach(item => {
            text += `• ${item.product_name}\n`;
            if (item.note) text += `  _Note: ${item.note}_\n`;
            text += `  Qty: ${item.billed_qty} | Rate: ${parseFloat(item.rate).toFixed(2)} | Total: ${parseFloat(item.total).toLocaleString()}\n`;
        });
        text += `\n`;
    }

    if (freeItems.length > 0) {
        text += `*--- FREE ITEMS ---*\n`;
        freeItems.forEach(item => {
            text += `• ${item.product_name}\n`;
            if (item.note) text += `  _Note: ${item.note}_\n`;
            text += `  Qty: ${item.free_qty}\n`;
        });
        text += `\n`;
    }

    text += `*FINANCIAL SUMMARY*\n`;
    text += `--------------------------\n`;
    text += `Sub-Total: ${parseFloat(sale.total_amount).toLocaleString()}\n`;
    if (parseFloat(sale.discount) > 0) text += `Discount: -${parseFloat(sale.discount).toLocaleString()}\n`;
    text += `*GRAND TOTAL: ৳ ${parseFloat(sale.grand_total).toLocaleString()}*\n`;
    text += `--------------------------\n`;
    text += `*Delivery:* ${sale.delivery_status.toUpperCase()}\n`;
    text += `\n_Thank you for your business!_`;

    copyText(text);
}

function copyGlobalSummary(btn) {
    if (salesData.length === 0) {
        alert("No records to copy.");
        return;
    }

    let text = `*SALES DISTRIBUTION SUMMARY REPORT* 📊\n`;
    text += `*Period:* ${new Date('<?php echo $start_date; ?>').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })} to ${new Date('<?php echo $end_date; ?>').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}\n`;
    text += `*Total Invoices:* ${salesData.length}\n`;
    text += `==========================\n\n`;

    let totalRevenue = 0;
    salesData.forEach(sale => {
        text += `*INV #${String(sale.id).padStart(6, '0')} | ${sale.customer_name.toUpperCase()}*\n`;
        text += `📞 ${sale.customer_phone} | 📍 ${sale.customer_address}\n`;
        
        const billed = sale.items.filter(i => parseInt(i.billed_qty) > 0).map(i => `${i.product_name} (${i.billed_qty})`).join(', ');
        const free = sale.items.filter(i => parseInt(i.free_qty) > 0).map(i => `${i.product_name} (${i.free_qty})`).join(', ');

        if (billed) text += `📦 *Billed:* ${billed}\n`;
        if (free) text += `🎁 *Free:* ${free}\n`;
        
        text += `💰 *Amount:* ৳ ${parseFloat(sale.grand_total).toLocaleString()}\n`;
        text += `🚚 *Delivery:* ${sale.delivery_status}\n`;
        text += `--------------------------\n\n`;
        totalRevenue += parseFloat(sale.grand_total);
    });

    text += `==========================\n`;
    text += `*TOTAL NET REVENUE: ৳ ${totalRevenue.toLocaleString()}*\n`;
    text += `==========================`;
    
    copyText(text, btn);
}

document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    const truckLoadBtn = document.getElementById('create-truck-load');
    const form = document.getElementById('bulk-actions-form');

    function toggleBtn() {
        const checkedCount = document.querySelectorAll('.invoice-checkbox:checked').length;
        if (checkedCount > 0) {
            truckLoadBtn.classList.remove('d-none');
        } else {
            truckLoadBtn.classList.add('d-none');
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            toggleBtn();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', toggleBtn);
    });

    truckLoadBtn.addEventListener('click', function() {
        form.submit();
    });
});
</script>

<?php require_once '../../templates/footer.php'; ?>
