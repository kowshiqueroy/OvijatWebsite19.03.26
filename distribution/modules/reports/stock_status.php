<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER]);

$company = get_company_settings();
$category_id  = $_GET['category_id'] ?? '';
$search_query = $_GET['search'] ?? '';
$stock_level  = $_GET['stock_level'] ?? '';
$hide_zero     = $_GET['hide_zero'] ?? '';
$sort_by       = $_GET['sort_by'] ?? 'low_stock';
$export       = $_GET['export'] ?? '';

$categories = fetch_all("SELECT * FROM categories WHERE isDelete = 0 ORDER BY name ASC");

// Exclude products hidden from current user in reports
$hidden_prod_ids = get_hidden_product_ids(null, 'reports');
$hidden_prod_sql = $hidden_prod_ids ? "AND p.id NOT IN (" . implode(',', array_map('intval', $hidden_prod_ids)) . ")" : "";

// Construct SQL
$sql = "SELECT p.*, c.name as category_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.isDelete = 0 AND c.isDelete = 0 $hidden_prod_sql";
$params = [];

if ($category_id) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}

if ($search_query) {
    $sql .= " AND p.name LIKE ?";
    $params[] = "%$search_query%";
}

if ($hide_zero == '1') {
    $sql .= " AND p.stock_qty > 0";
}

if ($stock_level === 'low') {
    $sql .= " AND p.stock_qty <= 10 AND p.stock_qty > 0";
} elseif ($stock_level === 'high') {
    $sql .= " AND p.stock_qty > 10";
} elseif ($stock_level === 'out') {
    $sql .= " AND p.stock_qty <= 0";
}

// Sorting logic
if ($sort_by === 'high_stock') {
    $sql .= " ORDER BY p.stock_qty DESC, c.name ASC, p.name ASC";
} elseif ($sort_by === 'name') {
    $sql .= " ORDER BY p.name ASC";
} else {
    // Default: low stock first
    $sql .= " ORDER BY p.stock_qty ASC, c.name ASC, p.name ASC";
}

$products = fetch_all($sql, $params);

// ── CSV export (before any HTML) ────────────────────────────────
if ($export === 'csv') {
    $can_see_rates = !isset($_SESSION['role']) || $_SESSION['role'] !== ROLE_VIEWER || can_see_section('rates');
    $headers = ['#', 'Product', 'Category', 'Unit', 'Stock Qty'];
    if ($can_see_rates) $headers = array_merge($headers, ['TP Rate', 'DP Rate', 'Retail Rate', 'TP Value']);
    $rows = [];
    foreach ($products as $i => $p) {
        $row = [$i+1, $p['name'], $p['category_name'], $p['unit'] ?? 'pcs', $p['stock_qty']];
        if ($can_see_rates) $row = array_merge($row, [
            number_format($p['tp_rate'],2), number_format($p['dp_rate'],2),
            number_format($p['retail_rate'],2), number_format($p['stock_qty']*$p['tp_rate'],2),
        ]);
        $rows[] = $row;
    }
    output_csv('stock_status_' . date('Y-m-d') . '.csv', $headers, $rows);
}

$stock_data = json_encode($products);

// Now include header
$page_title = "Stock Status " . date('d-m-Y') . " — " . ($company['name'] ?? '');
require_once '../../templates/header.php';
?>

<style>
    /* A4 Print Optimization */
    @page { size: A4; margin: 10mm; }
    
    .excel-table { border: 1px solid #000; width: 100%; border-collapse: collapse; }
    .excel-table th { background: #eee !important; border: 1px solid #000 !important; color: #000; font-weight: bold; text-transform: uppercase; font-size: 11px; padding: 6px; }
    .excel-table td { border: 1px solid #000 !important; font-size: 11px; padding: 5px; vertical-align: middle; }
    
    .low-stock { background-color: #fff3f3 !important; font-weight: bold; color: #d63384; }
    
    @media print {
        html, body, #wrapper, #page-content-wrapper, .container-fluid, .card, .card-body { 
            background: #fff !important; 
            color: #000 !important;
        }
        #sidebar-wrapper, .navbar, .btn, .alert, .no-print, .report-filter-box { display: none !important; }
        #page-content-wrapper { width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .container-fluid { padding: 0 !important; }
        .card { border: none !important; box-shadow: none !important; }
        .excel-table { width: 100% !important; }
        .report-header-print { display: block !important; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
    }
    .report-header-print { display: none; }
</style>

<div class="row mb-4 no-print">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h3>Stock Status Report</h3>
            <p class="text-muted mb-0">Current inventory levels sorted by low stock first.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button onclick="copyStockForWhatsApp()" class="btn btn-outline-success btn-sm">
                <i class="fa-brands fa-whatsapp me-1"></i> WhatsApp
            </button>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-file-csv me-1"></i> Export CSV
            </a>
            <button onclick="window.print()" class="btn btn-dark btn-sm">
                <i class="fa-solid fa-print me-1"></i> Print
            </button>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="card shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-bold">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">-- All Categories --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $category_id == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo $c['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Search Product</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Product name..." value="<?php echo $search_query; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Stock Level</label>
                <select name="stock_level" class="form-select form-select-sm">
                    <option value="">-- All Levels --</option>
                    <option value="low" <?php echo $stock_level === 'low' ? 'selected' : ''; ?>>Low Stock (&le; 10)</option>
                    <option value="high" <?php echo $stock_level === 'high' ? 'selected' : ''; ?>>High Stock (&gt; 10)</option>
                    <option value="out" <?php echo $stock_level === 'out' ? 'selected' : ''; ?>>Out of Stock (&le; 0)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Sort By</label>
                <select name="sort_by" class="form-select form-select-sm">
                    <option value="low_stock" <?php echo $sort_by === 'low_stock' ? 'selected' : ''; ?>>Low Stock First</option>
                    <option value="high_stock" <?php echo $sort_by === 'high_stock' ? 'selected' : ''; ?>>High Stock First</option>
                    <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Alphabetical</option>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <div class="form-check form-switch small">
                    <input class="form-check-input" type="checkbox" name="hide_zero" id="hideZeroCheck" value="1" <?php echo $hide_zero == '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-bold" for="hideZeroCheck">Hide Zero Stock</label>
                </div>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="stock_status.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Print Header -->
<div class="report-header-print text-center">
    <h2 class="mb-1 text-primary"><?php echo $company['name']; ?></h2>
    <p class="mb-2 small"><?php echo $company['address']; ?> | <?php echo $company['phone']; ?></p>
    <h4 class="text-uppercase border-bottom pb-2">Stock Status Report</h4>
    <p class="small mt-2">
        <strong>As of:</strong> <?php echo date('d M Y, h:i A'); ?>
        <?php if($category_id) echo " | <strong>Category ID:</strong> $category_id"; ?>
    </p>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered excel-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 15%;">Category</th>
                        <th style="width: 25%;">Product Name</th>
                        <th style="width: 10%; text-align: center;">Stock Qty</th>
                        <th style="width: 10%; text-align: right;">TP Rate</th>
                        <th style="width: 10%; text-align: right;">DP Rate</th>
                        <th style="width: 10%; text-align: right;">Retail Rate</th>
                        <th style="width: 15%; text-align: right;">Value (TP)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_stock = 0;
                    $total_tp_value = 0;
                    $total_dp_value = 0;
                    $total_retail_value = 0;

                    foreach ($products as $index => $p): 
                        // Treat negative stock as 0
                        $display_qty = max(0, $p['stock_qty']);
                        
                        $tp_val = $display_qty * $p['tp_rate'];
                        $dp_val = $display_qty * $p['dp_rate'];
                        $retail_val = $display_qty * $p['retail_rate'];

                        $total_stock += $display_qty;
                        $total_tp_value += $tp_val;
                        $total_dp_value += $dp_val;
                        $total_retail_value += $retail_val;

                        $is_low = $p['stock_qty'] <= 10;
                    ?>
                        <tr class="<?php echo $is_low ? 'low-stock' : ''; ?>">
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo $p['category_name']; ?></td>
                            <td><strong><?php echo $p['name']; ?></strong></td>
                            <td class="text-center <?php echo $is_low ? 'text-danger' : ''; ?>">
                                <?php echo $display_qty; ?>
                                <?php if($p['stock_qty'] < 0): ?>
                                    <small class="d-block text-muted" style="font-size: 8px;">(Actual: <?php echo $p['stock_qty']; ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?php echo number_format($p['tp_rate'], 2); ?></td>
                            <td class="text-end"><?php echo number_format($p['dp_rate'], 2); ?></td>
                            <td class="text-end"><?php echo number_format($p['retail_rate'], 2); ?></td>
                            <td class="text-end fw-bold"><?php echo number_format($tp_val, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-light fw-bold">
                    <tr>
                        <td colspan="3" class="text-end">TOTALS:</td>
                        <td class="text-center"><?php echo number_format($total_stock); ?></td>
                        <td colspan="3" class="text-end">GRAND TOTAL VALUE:</td>
                        <td class="text-end text-primary"><?php echo number_format($total_tp_value, 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border-0"></td>
                        <td colspan="3" class="text-end text-muted small">Total DP Value:</td>
                        <td class="text-end text-muted small"><?php echo number_format($total_dp_value, 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border-0"></td>
                        <td colspan="3" class="text-end text-muted small">Total Retail Value:</td>
                        <td class="text-end text-muted small"><?php echo number_format($total_retail_value, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="report-signatures mt-5 pt-5 d-none d-print-block">
    <div class="row text-center">
        <div class="col-4">
            <div class="border-top pt-2 mx-3">Store Keeper</div>
        </div>
        <div class="col-4">
            <div class="border-top pt-2 mx-3">Verified By</div>
        </div>
        <div class="col-4">
            <div class="border-top pt-2 mx-3">Authorized By</div>
        </div>
    </div>
</div>

<script>
const stockData = <?php echo $stock_data; ?>;

function copyStockForWhatsApp() {
    if (stockData.length === 0) {
        alert("No data to copy.");
        return;
    }

    let text = `*📦 STOCK STATUS REPORT*\n`;
    text += `*Date:* ${new Date().toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}\n`;
    text += `==========================\n\n`;

    // Group by category for better readability
    const grouped = {};
    stockData.forEach(p => {
        if (!grouped[p.category_name]) grouped[p.category_name] = [];
        grouped[p.category_name].push(p);
    });

    let totalStock = 0;
    let totalTPValue = 0;

    for (const cat in grouped) {
        text += `*📂 ${cat.toUpperCase()}*\n`;
        grouped[cat].forEach(p => {
            const displayQty = Math.max(0, parseInt(p.stock_qty));
            const lowTag = p.stock_qty <= 10 ? ' ⚠️ *LOW*' : '';
            text += `• ${p.name}: *${displayQty}*${lowTag}\n`;
            
            totalStock += displayQty;
            totalTPValue += (displayQty * parseFloat(p.tp_rate));
        });
        text += `\n`;
    }

    text += `==========================\n`;
    text += `*Total Items:* ${stockData.length}\n`;
    text += `*Total Stock:* ${totalStock.toLocaleString()}\n`;
    text += `*Total TP Value:* ৳ ${totalTPValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}\n`;
    text += `==========================\n`;
    text += `_Generated by ${window.location.hostname}_`;

    copyToClipboard(text);
}

function copyToClipboard(text) {
    const tempInput = document.createElement("textarea");
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand("copy");
    document.body.removeChild(tempInput);
    
    alert("Stock report copied to clipboard! You can now paste it in WhatsApp.");
}
</script>

<?php require_once '../../templates/footer.php'; ?>
