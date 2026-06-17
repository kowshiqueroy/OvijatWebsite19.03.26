<?php
/**
 * DMD Viewer Dashboard for Ovijat Food Distribution Management System
 * ------------------------------------------------------------------
 * File path suggestion: /modules/dmd_dashboard/index.php
 * Access: View only. No insert/update/delete operation is used here.
 *
 * Assumption:
 * - Existing project already has templates/header.php, templates/footer.php
 * - Existing project already has fetch_all($sql,$params), fetch_row($sql,$params)
 *   OR a PDO / MySQLi connection available as $pdo / $conn / $db.
 * - This file auto-detects common table/column names to reduce integration work.
 */

$basePath = dirname(__DIR__, 2);
$headerPath = $basePath . '/templates/header.php';
$footerPath = $basePath . '/templates/footer.php';

if (file_exists($headerPath)) {
    require_once $headerPath;
} else {
    // Fallback if you place this file somewhere else during testing.
    @require_once __DIR__ . '/../../templates/header.php';
}

if (function_exists('check_login')) {
    check_login();
}

// -----------------------------------------------------------------------------
// DMD VIEWER ACCESS CHECK
// -----------------------------------------------------------------------------
$sessionRole = $_SESSION['role'] ?? '';

$allowedRoles = [ROLE_ADMIN, ROLE_MANAGER, ROLE_VIEWER];
$isAllowed = in_array($sessionRole, $allowedRoles, true) || strtolower($sessionRole) === 'dmd';

if (!$isAllowed) {
    http_response_code(403);
    echo '<div class="container mt-4"><div class="alert alert-danger">Access denied. DMD viewer permission required.</div></div>';
    if (file_exists($footerPath)) { require_once $footerPath; }
    exit;
}

// -----------------------------------------------------------------------------
// SMALL DATABASE HELPER LAYER
// -----------------------------------------------------------------------------
function dmd_bind_params(mysqli_stmt $stmt, array $params): void
{
    if (!$params) {
        return;
    }
    $types = '';
    foreach ($params as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    $stmt->bind_param($types, ...$params);
}

function dmd_all(string $sql, array $params = []): array
{
    global $conn;
    $mysqli = $conn ?? get_db_connection();

    if ($mysqli) {
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($params) {
            dmd_bind_params($stmt, $params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? ($result->fetch_all(MYSQLI_ASSOC) ?: []) : [];
    }

    return [];
}

function dmd_one(string $sql, array $params = []): array
{
    $rows = dmd_all($sql, $params);
    return $rows[0] ?? [];
}

function dmd_value(string $sql, array $params = [], $default = 0)
{
    $row = dmd_one($sql, $params);
    if (!$row) {
        return $default;
    }
    $value = reset($row);
    return $value ?? $default;
}

function dmd_ident(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        return '';
    }
    return '`' . $name . '`';
}

function dmd_col(string $alias, string $column): string
{
    $aliasSql = preg_match('/^[a-zA-Z0-9_]+$/', $alias) ? '`' . $alias . '`' : '';
    $columnSql = dmd_ident($column);
    return $aliasSql ? $aliasSql . '.' . $columnSql : $columnSql;
}

function dmd_money($amount): string
{
    $amount = (float)$amount;
    return '৳ ' . number_format($amount, 2);
}

function dmd_num($number): string
{
    return number_format((float)$number, 0);
}

function dmd_safe($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dmd_date_ok(?string $date): bool
{
    return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}

// -----------------------------------------------------------------------------
// INPUT FILTERS
// -----------------------------------------------------------------------------
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

$startDate = $_GET['start_date'] ?? $monthStart;
$endDate   = $_GET['end_date'] ?? $today;

if (!dmd_date_ok($startDate)) { $startDate = $monthStart; }
if (!dmd_date_ok($endDate))   { $endDate   = $today; }
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$customerType   = trim((string)($_GET['customer_type'] ?? ''));
$productCat     = trim((string)($_GET['category'] ?? ''));

// -----------------------------------------------------------------------------
// TABLE + COLUMN MAPPINGS (Fixed for current DB)
// -----------------------------------------------------------------------------
$invoiceTable     = 'sales_drafts';
$invoiceItemTable = 'sales_items';
$customerTable    = 'customers';
$productTable     = 'products';
$categoryTable    = 'categories';
$transactionTable = 'transactions';

$invIdCol       = 'id';
$invNoCol       = 'id';
$invDateCol     = 'created_at';
$invCustomerCol = 'customer_id';
$invAmountCol   = 'grand_total';
$invDiscountCol = 'discount';
$invStatusCol   = 'status';
$invDeliveryCol = 'delivery_status';
$invDeliveredAt = 'delivery_date';

$custIdCol      = 'id';
$custNameCol    = 'name';
$custPhoneCol   = 'phone';
$custAddressCol = 'address';
$custTypeCol    = 'type';
$custBalanceCol = 'balance';

$prodIdCol      = 'id';
$prodNameCol    = 'name';
$prodCatCol     = 'category_id';
$prodStockCol   = 'stock_qty';
$prodMinCol     = null; // Will use default 10
$prodTpCol      = 'tp_rate';
$prodDpCol      = 'dp_rate';
$prodRetailCol  = 'retail_rate';
$prodStatusCol  = 'is_active';

$itemInvoiceCol = 'draft_id';
$itemProductCol = 'product_id';
$itemQtyCol     = 'billed_qty';
$itemTotalCol   = 'total';
$itemRateCol    = 'rate';

$trxAmountCol   = 'amount';
$trxDateCol     = 'created_at';
$trxTypeCol     = 'type';
$trxCustomerCol = 'customer_id';

// -----------------------------------------------------------------------------
// COMMON FILTER SQL FOR INVOICE REPORTS
// -----------------------------------------------------------------------------
$invoiceWhere = ' WHERE i.isDelete = 0 ';
$invoiceParams = [];
$invoiceJoinCustomer = ' LEFT JOIN ' . dmd_ident($customerTable) . ' `c` ON ' . dmd_col('c', $custIdCol) . ' = ' . dmd_col('i', $invCustomerCol) . ' ';

$invoiceWhere .= ' AND DATE(' . dmd_col('i', $invDateCol) . ') BETWEEN ? AND ? ';
$invoiceParams[] = $startDate;
$invoiceParams[] = $endDate;

$invoiceWhere .= " AND LOWER(COALESCE(" . dmd_col('i', $invStatusCol) . ",'')) = 'confirmed' ";

if ($customerType !== '') {
    $invoiceWhere .= ' AND ' . dmd_col('c', $custTypeCol) . ' = ? ';
    $invoiceParams[] = $customerType;
}

// -----------------------------------------------------------------------------
// OPTION LISTS
// -----------------------------------------------------------------------------
$customerTypeOptions = dmd_all('SELECT DISTINCT ' . dmd_col('c', $custTypeCol) . ' AS val FROM ' . dmd_ident($customerTable) . ' `c` WHERE isDelete = 0 ORDER BY val ASC');
$productCategoryOptions = dmd_all('SELECT id, name AS val FROM ' . dmd_ident($categoryTable) . ' WHERE isDelete = 0 ORDER BY val ASC');

// -----------------------------------------------------------------------------
// KPI CALCULATION
// -----------------------------------------------------------------------------
$totalSales = 0.0;
$totalInvoices = 0;
$totalCollection = 0.0;
$totalReceivable = 0.0;
$stockValue = 0.0;
$pendingDeliveryCount = 0;
$pendingDeliveryValue = 0.0;
$lowStockSku = 0;
$negativeStockSku = 0;
$zeroStockSku = 0;
$activeCustomers = 0;
$inactiveCustomers = 0;
$avgOrderValue = 0.0;
$collectionRatio = 0.0;

// Sales KPI
$salesSql = 'SELECT COALESCE(SUM(' . dmd_col('i', $invAmountCol) . '),0) AS total_sales, COUNT(*) AS total_invoices
             FROM ' . dmd_ident($invoiceTable) . ' `i` ' . $invoiceJoinCustomer . $invoiceWhere;
$salesRow = dmd_one($salesSql, $invoiceParams);
$totalSales = (float)($salesRow['total_sales'] ?? 0);
$totalInvoices = (int)($salesRow['total_invoices'] ?? 0);
$avgOrderValue = $totalInvoices > 0 ? ($totalSales / $totalInvoices) : 0;

// Collection KPI
$trxWhere = ' WHERE DATE(' . dmd_col('t', $trxDateCol) . ') BETWEEN ? AND ? AND t.isDelete = 0 ';
$trxParams = [$startDate, $endDate];
$trxWhere .= " AND LOWER(" . dmd_col('t', $trxTypeCol) . ") = 'credit' "; // Credit is collection in this system
$totalCollection = (float)dmd_value('SELECT COALESCE(SUM(' . dmd_col('t', $trxAmountCol) . '),0) AS total_collection FROM ' . dmd_ident($transactionTable) . ' `t` ' . $trxWhere, $trxParams, 0);
$collectionRatio = $totalSales > 0 ? round(($totalCollection / $totalSales) * 100, 2) : 0;

// Receivable KPI
$totalReceivable = (float)dmd_value('SELECT COALESCE(SUM(' . dmd_col('c', $custBalanceCol) . '),0) AS receivable FROM ' . dmd_ident($customerTable) . ' `c` WHERE isDelete = 0', [], 0);

// Stock KPI
$stockValue = (float)dmd_value('SELECT COALESCE(SUM(GREATEST(' . dmd_col('p', $prodStockCol) . ',0) * ' . dmd_col('p', $prodTpCol) . '),0) AS stock_value FROM ' . dmd_ident($productTable) . ' `p` WHERE isDelete = 0', [], 0);
$lowStockSku = (int)dmd_value('SELECT COUNT(*) AS cnt FROM ' . dmd_ident($productTable) . ' `p` WHERE ' . dmd_col('p', $prodStockCol) . ' <= 10 AND ' . dmd_col('p', $prodStockCol) . ' >= 0 AND isDelete = 0', [], 0);
$negativeStockSku = (int)dmd_value('SELECT COUNT(*) AS cnt FROM ' . dmd_ident($productTable) . ' `p` WHERE ' . dmd_col('p', $prodStockCol) . ' < 0 AND isDelete = 0', [], 0);
$zeroStockSku = (int)dmd_value('SELECT COUNT(*) AS cnt FROM ' . dmd_ident($productTable) . ' `p` WHERE ' . dmd_col('p', $prodStockCol) . ' = 0 AND isDelete = 0', [], 0);

// Customer KPI
$activeCustomers = (int)dmd_value('SELECT COUNT(DISTINCT ' . dmd_col('i', $invCustomerCol) . ') AS cnt FROM ' . dmd_ident($invoiceTable) . ' `i` ' . $invoiceJoinCustomer . $invoiceWhere, $invoiceParams, 0);
$inactiveCustomers = (int)dmd_value(
    'SELECT COUNT(*) AS cnt FROM ' . dmd_ident($customerTable) . ' `c`
     WHERE isDelete = 0 AND NOT EXISTS (
         SELECT 1 FROM ' . dmd_ident($invoiceTable) . ' `i`
         WHERE ' . dmd_col('i', $invCustomerCol) . ' = ' . dmd_col('c', $custIdCol) . '
         AND DATE(' . dmd_col('i', $invDateCol) . ') >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         AND i.isDelete = 0
     )', [], 0
);

// Pending Delivery KPI
$pendingSql = 'SELECT COUNT(*) AS cnt, COALESCE(SUM(' . dmd_col('i', $invAmountCol) . '),0) AS value
               FROM ' . dmd_ident($invoiceTable) . ' `i` ' . $invoiceJoinCustomer . $invoiceWhere .
               " AND LOWER(" . dmd_col('i', $invDeliveryCol) . ") NOT IN ('delivered','complete','completed','returned') ";
$pendingRow = dmd_one($pendingSql, $invoiceParams);
$pendingDeliveryCount = (int)($pendingRow['cnt'] ?? 0);
$pendingDeliveryValue = (float)($pendingRow['value'] ?? 0);

// -----------------------------------------------------------------------------
// CHART DATA
// -----------------------------------------------------------------------------
$dailySalesRows = dmd_all(
    'SELECT DATE(' . dmd_col('i', $invDateCol) . ') AS d, COALESCE(SUM(' . dmd_col('i', $invAmountCol) . '),0) AS sales, COUNT(*) AS invoice_count
     FROM ' . dmd_ident($invoiceTable) . ' `i` ' . $invoiceJoinCustomer . $invoiceWhere . '
     GROUP BY DATE(' . dmd_col('i', $invDateCol) . ') ORDER BY d ASC',
    $invoiceParams
);

$dailyCollectionRows = dmd_all(
    'SELECT DATE(' . dmd_col('t', $trxDateCol) . ') AS d, COALESCE(SUM(' . dmd_col('t', $trxAmountCol) . '),0) AS collection
     FROM ' . dmd_ident($transactionTable) . ' `t` ' . $trxWhere . '
     GROUP BY DATE(' . dmd_col('t', $trxDateCol) . ') ORDER BY d ASC',
    $trxParams
);

$categorySalesRows = [];
if ($productCat !== '') {
    $catWhere = $invoiceWhere . ' AND ' . dmd_col('p', $prodCatCol) . ' = ? ';
    $catParams = array_merge($invoiceParams, [$productCat]);
} else {
    $catWhere = $invoiceWhere;
    $catParams = $invoiceParams;
}

$categorySalesRows = dmd_all(
    'SELECT cat.name AS category, COALESCE(SUM(' . dmd_col('ii', $itemTotalCol) . '),0) AS sales
     FROM ' . dmd_ident($invoiceTable) . ' `i`
     INNER JOIN ' . dmd_ident($invoiceItemTable) . ' `ii` ON ' . dmd_col('ii', $itemInvoiceCol) . ' = ' . dmd_col('i', $invIdCol) . '
     LEFT JOIN ' . dmd_ident($productTable) . ' `p` ON ' . dmd_col('p', $prodIdCol) . ' = ' . dmd_col('ii', $itemProductCol) . '
     LEFT JOIN ' . dmd_ident($categoryTable) . ' cat ON cat.id = ' . dmd_col('p', $prodCatCol) . '
     ' . $invoiceJoinCustomer . $catWhere . '
     GROUP BY cat.name ORDER BY sales DESC LIMIT 12',
    $catParams
);

// -----------------------------------------------------------------------------
// TABLE DATA
// -----------------------------------------------------------------------------
$topCustomers = dmd_all(
    'SELECT ' . dmd_col('c', $custNameCol) . ' AS customer_name,
            ' . dmd_col('c', $custPhoneCol) . ' AS phone,
            COALESCE(SUM(' . dmd_col('i', $invAmountCol) . '),0) AS sales,
            COUNT(*) AS invoice_count
     FROM ' . dmd_ident($invoiceTable) . ' `i`
     LEFT JOIN ' . dmd_ident($customerTable) . ' `c` ON ' . dmd_col('c', $custIdCol) . ' = ' . dmd_col('i', $invCustomerCol) . '
     ' . $invoiceWhere . '
     GROUP BY ' . dmd_col('i', $invCustomerCol) . ', ' . dmd_col('c', $custNameCol) . ', ' . dmd_col('c', $custPhoneCol) . '
     ORDER BY sales DESC LIMIT 10',
    $invoiceParams
);

$topDueCustomers = dmd_all(
    'SELECT ' . dmd_col('c', $custNameCol) . ' AS customer_name,
            ' . dmd_col('c', $custPhoneCol) . ' AS phone,
            ' . dmd_col('c', $custTypeCol) . ' AS customer_type,
            ' . dmd_col('c', $custBalanceCol) . ' AS due_amount
     FROM ' . dmd_ident($customerTable) . ' `c`
     WHERE isDelete = 0 AND ' . dmd_col('c', $custBalanceCol) . ' > 0
     ORDER BY due_amount DESC LIMIT 10'
);

$lowStockProducts = dmd_all(
    'SELECT ' . dmd_col('p', $prodNameCol) . ' AS product_name,
            cat.name AS category,
            ' . dmd_col('p', $prodStockCol) . ' AS stock_qty,
            10 AS min_qty,
            ' . dmd_col('p', $prodTpCol) . ' AS tp_rate
     FROM ' . dmd_ident($productTable) . ' `p`
     LEFT JOIN ' . dmd_ident($categoryTable) . ' cat ON cat.id = ' . dmd_col('p', $prodCatCol) . '
     WHERE ' . dmd_col('p', $prodStockCol) . ' <= 10 AND p.isDelete = 0
     ORDER BY ' . dmd_col('p', $prodStockCol) . ' ASC LIMIT 12'
);

$deliveryFunnel = dmd_all(
    'SELECT LOWER(COALESCE(' . dmd_col('i', $invDeliveryCol) . ', "pending")) AS delivery_status,
            COUNT(*) AS count_items,
            COALESCE(SUM(' . dmd_col('i', $invAmountCol) . '),0) AS value
     FROM ' . dmd_ident($invoiceTable) . ' `i` ' . $invoiceJoinCustomer . $invoiceWhere . '
     GROUP BY LOWER(COALESCE(' . dmd_col('i', $invDeliveryCol) . ', "pending")) ORDER BY count_items DESC',
    $invoiceParams
);

$recentStockMoves = dmd_all(
    'SELECT se.created_at AS movement_date,
            "Stock In" AS movement_type,
            se.quantity AS qty,
            p.name AS product_name
     FROM stock_entries se
     JOIN products p ON p.id = se.product_id
     WHERE DATE(se.created_at) BETWEEN ? AND ? AND se.isDelete = 0
     ORDER BY se.created_at DESC LIMIT 10',
    [$startDate, $endDate]
);

// -----------------------------------------------------------------------------
// RED FLAG ALERTS
// -----------------------------------------------------------------------------
$redFlags = [];

if ($negativeStockSku > 0) {
    $redFlags[] = [
        'level' => 'Critical',
        'title' => 'Negative stock found',
        'details' => $negativeStockSku . ' SKU currently shows negative stock. Inventory posting or sales deduction needs checking.',
        'action' => 'Inventory audit and stock movement review'
    ];
}
if ($pendingDeliveryCount > 0) {
    $redFlags[] = [
        'level' => 'Warning',
        'title' => 'Pending delivery invoices',
        'details' => $pendingDeliveryCount . ' invoice(s) pending. Pending value: ' . dmd_money($pendingDeliveryValue),
        'action' => 'Dispatch follow-up'
    ];
}
if ($totalSales > 0 && $collectionRatio < 40) {
    $redFlags[] = [
        'level' => 'Warning',
        'title' => 'Collection ratio below safe level',
        'details' => 'Collection ratio is only ' . $collectionRatio . '%. Sales is moving but cash recovery is weak.',
        'action' => 'Accounts and Sales Admin follow-up'
    ];
}
if (!empty($topDueCustomers)) {
    $highestDue = $topDueCustomers[0];
    if ((float)$highestDue['due_amount'] > 500000) {
        $redFlags[] = [
            'level' => 'Critical',
            'title' => 'High outstanding customer',
            'details' => ($highestDue['customer_name'] ?? 'Customer') . ' due amount: ' . dmd_money($highestDue['due_amount']),
            'action' => 'Credit hold review'
        ];
    }
}
if ($lowStockSku > 0) {
    $redFlags[] = [
        'level' => 'Info',
        'title' => 'Low stock alert',
        'details' => $lowStockSku . ' SKU is at or below minimum stock level.',
        'action' => 'Production / procurement planning'
    ];
}

// -----------------------------------------------------------------------------
// JAVASCRIPT DATA
// -----------------------------------------------------------------------------
$chartLabels = array_map(fn($r) => $r['d'], $dailySalesRows);
$chartSales = array_map(fn($r) => (float)$r['sales'], $dailySalesRows);

$collectionMap = [];
foreach ($dailyCollectionRows as $r) {
    $collectionMap[$r['d']] = (float)$r['collection'];
}
$chartCollection = array_map(fn($d) => (float)($collectionMap[$d] ?? 0), $chartLabels);

$catLabels = array_map(fn($r) => $r['category'], $categorySalesRows);
$catSales  = array_map(fn($r) => (float)$r['sales'], $categorySalesRows);

$summaryText = "Ovijat Distribution DMD Summary\n";
$summaryText .= "Period: {$startDate} to {$endDate}\n";
$summaryText .= "Total Sales: " . dmd_money($totalSales) . "\n";
$summaryText .= "Collection: " . dmd_money($totalCollection) . "\n";
$summaryText .= "Collection Ratio: {$collectionRatio}%\n";
$summaryText .= "Receivable: " . dmd_money($totalReceivable) . "\n";
$summaryText .= "Stock Value: " . dmd_money($stockValue) . "\n";
$summaryText .= "Pending Delivery: {$pendingDeliveryCount}\n";
$summaryText .= "Low Stock SKU: {$lowStockSku}\n";
$summaryText .= "Negative Stock SKU: {$negativeStockSku}\n";
$summaryText .= "Active Customers: {$activeCustomers}\n";
$summaryText .= "Inactive Customers: {$inactiveCustomers}\n";
$summaryText .= "Action Needed: Collection follow-up, stock correction, pending delivery clearance.";
?>

<style>
    .dmd-page { padding: 22px 22px 40px; background: #f5f7fb; min-height: calc(100vh - 70px); }
    .dmd-title-row { display:flex; justify-content:space-between; gap:15px; align-items:flex-start; margin-bottom:16px; }
    .dmd-title h1 { margin:0; font-size:28px; font-weight:800; color:#1f2937; }
    .dmd-title p { margin:6px 0 0; color:#6b7280; }
    .dmd-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
    .dmd-btn { border:0; border-radius:8px; padding:10px 14px; font-size:14px; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .dmd-btn-primary { background:#0d6efd; color:#fff; }
    .dmd-btn-dark { background:#111827; color:#fff; }
    .dmd-btn-light { background:#fff; color:#111827; border:1px solid #d1d5db; }
    .dmd-filter-card, .dmd-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 4px 16px rgba(15,23,42,.05); }
    .dmd-filter-card { padding:14px; margin-bottom:16px; }
    .dmd-filter-grid { display:grid; grid-template-columns: repeat(7, minmax(130px,1fr)); gap:10px; align-items:end; }
    .dmd-filter-grid label { display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:5px; }
    .dmd-filter-grid input, .dmd-filter-grid select { width:100%; border:1px solid #d1d5db; border-radius:8px; padding:9px 10px; min-height:39px; background:#fff; }
    .dmd-kpi-grid { display:grid; grid-template-columns: repeat(5, minmax(160px,1fr)); gap:14px; margin-bottom:16px; }
    .dmd-kpi { padding:16px; color:#fff; border-radius:16px; position:relative; overflow:hidden; min-height:118px; }
    .dmd-kpi small { display:block; opacity:.9; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
    .dmd-kpi strong { display:block; margin-top:8px; font-size:25px; line-height:1.1; }
    .dmd-kpi span { display:block; margin-top:7px; opacity:.9; font-size:13px; }
    .bg-blue { background:linear-gradient(135deg,#0d6efd,#043c94); }
    .bg-green { background:linear-gradient(135deg,#198754,#0f5132); }
    .bg-red { background:linear-gradient(135deg,#dc3545,#8b1020); }
    .bg-cyan { background:linear-gradient(135deg,#06b6d4,#0e7490); }
    .bg-dark { background:linear-gradient(135deg,#374151,#111827); }
    .bg-orange { background:linear-gradient(135deg,#f59e0b,#b45309); }
    .bg-purple { background:linear-gradient(135deg,#7c3aed,#4c1d95); }
    .bg-slate { background:linear-gradient(135deg,#64748b,#334155); }
    .dmd-grid-2 { display:grid; grid-template-columns: 1.5fr 1fr; gap:16px; margin-bottom:16px; }
    .dmd-grid-3 { display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-bottom:16px; }
    .dmd-card { padding:16px; }
    .dmd-card h3 { margin:0 0 12px; font-size:18px; font-weight:800; color:#1f2937; display:flex; justify-content:space-between; align-items:center; }
    .dmd-card .hint { color:#6b7280; font-size:13px; font-weight:400; }
    .dmd-insight { line-height:1.7; color:#374151; background:#f9fafb; border-left:5px solid #0d6efd; padding:14px; border-radius:10px; }
    .dmd-table-wrap { overflow:auto; max-height:430px; }
    .dmd-table { width:100%; border-collapse:collapse; font-size:13px; }
    .dmd-table th { position:sticky; top:0; background:#f3f4f6; text-align:left; padding:10px; border-bottom:1px solid #e5e7eb; white-space:nowrap; }
    .dmd-table td { padding:10px; border-bottom:1px solid #eef2f7; vertical-align:top; }
    .dmd-table tr:hover td { background:#f8fafc; }
    .text-right { text-align:right !important; }
    .text-danger { color:#dc3545; }
    .text-success { color:#198754; }
    .badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:800; }
    .badge-red { background:#fee2e2; color:#991b1b; }
    .badge-orange { background:#ffedd5; color:#9a3412; }
    .badge-green { background:#dcfce7; color:#166534; }
    .badge-blue { background:#dbeafe; color:#1e40af; }
    .dmd-alert { border-left:5px solid #e5e7eb; padding:12px; border-radius:10px; background:#fff; margin-bottom:10px; }
    .dmd-alert.critical { border-color:#dc3545; background:#fff5f5; }
    .dmd-alert.warning { border-color:#f59e0b; background:#fffbeb; }
    .dmd-alert.info { border-color:#0d6efd; background:#eff6ff; }
    .dmd-alert strong { display:block; color:#111827; }
    .dmd-alert p { margin:5px 0 0; color:#4b5563; }
    .dmd-funnel { display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; }
    .dmd-funnel-box { background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:14px; text-align:center; }
    .dmd-funnel-box strong { display:block; font-size:24px; color:#111827; }
    .dmd-funnel-box span { display:block; color:#6b7280; font-size:12px; margin-top:5px; }
    canvas { max-height:320px; }
    .dmd-empty { padding:18px; color:#6b7280; text-align:center; background:#f9fafb; border-radius:10px; }
    @media (max-width: 1200px) {
        .dmd-filter-grid { grid-template-columns: repeat(3, 1fr); }
        .dmd-kpi-grid { grid-template-columns: repeat(3, 1fr); }
        .dmd-grid-3 { grid-template-columns:1fr; }
    }
    @media (max-width: 768px) {
        .dmd-title-row, .dmd-grid-2 { grid-template-columns:1fr; display:block; }
        .dmd-actions { justify-content:flex-start; margin-top:12px; }
        .dmd-filter-grid, .dmd-kpi-grid { grid-template-columns:1fr; }
        .dmd-grid-2 { display:grid; grid-template-columns:1fr; }
        .dmd-funnel { grid-template-columns: repeat(2, 1fr); }
    }
    @media print {
        .sidebar, .dmd-filter-card, .dmd-actions, .navbar, .topbar { display:none !important; }
        .dmd-page { background:#fff; padding:0; }
        .dmd-card, .dmd-filter-card { box-shadow:none; break-inside:avoid; }
    }
</style>

<div class="dmd-page">
    <div class="dmd-title-row">
        <div class="dmd-title">
            <h1>DMD Viewer Dashboard</h1>
            <p>Executive control tower for sales, collection, receivable, stock and delivery monitoring.</p>
        </div>
        <div class="dmd-actions">
            <button type="button" class="dmd-btn dmd-btn-light" onclick="copyDmdSummary()">Copy Summary</button>
            <button type="button" class="dmd-btn dmd-btn-light" onclick="exportDashboardCSV()">Export CSV</button>
            <button type="button" class="dmd-btn dmd-btn-dark" onclick="window.print()">Print</button>
        </div>
    </div>

    <form method="get" class="dmd-filter-card">
        <div class="dmd-filter-grid">
            <div>
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?= dmd_safe($startDate) ?>">
            </div>
            <div>
                <label>End Date</label>
                <input type="date" name="end_date" value="<?= dmd_safe($endDate) ?>">
            </div>
            <div>
                <label>Division</label>
                <select name="division">
                    <option value="">All Division</option>
                    <?php foreach ($divisionOptions as $row): $v = (string)$row['val']; ?>
                        <option value="<?= dmd_safe($v) ?>" <?= $divisionFilter === $v ? 'selected' : '' ?>><?= dmd_safe($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Area</label>
                <select name="area">
                    <option value="">All Area</option>
                    <?php foreach ($areaOptions as $row): $v = (string)$row['val']; ?>
                        <option value="<?= dmd_safe($v) ?>" <?= $areaFilter === $v ? 'selected' : '' ?>><?= dmd_safe($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Depot</label>
                <select name="depot">
                    <option value="">All Depot</option>
                    <?php foreach ($depotOptions as $row): $v = (string)$row['val']; ?>
                        <option value="<?= dmd_safe($v) ?>" <?= $depotFilter === $v ? 'selected' : '' ?>><?= dmd_safe($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Customer Type</label>
                <select name="customer_type">
                    <option value="">All Type</option>
                    <?php foreach ($customerTypeOptions as $row): $v = (string)$row['val']; ?>
                        <option value="<?= dmd_safe($v) ?>" <?= $customerType === $v ? 'selected' : '' ?>><?= dmd_safe($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Category</label>
                <select name="category">
                    <option value="">All Category</option>
                    <?php foreach ($productCategoryOptions as $row): $v = (string)$row['val']; ?>
                        <option value="<?= dmd_safe($v) ?>" <?= $productCat === $v ? 'selected' : '' ?>><?= dmd_safe($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button class="dmd-btn dmd-btn-primary" type="submit" style="width:100%; justify-content:center;">Filter</button>
            </div>
        </div>
    </form>

    <div class="dmd-kpi-grid">
        <div class="dmd-kpi bg-blue"><small>Total Sales</small><strong><?= dmd_money($totalSales) ?></strong><span><?= dmd_num($totalInvoices) ?> invoices in selected period</span></div>
        <div class="dmd-kpi bg-green"><small>Total Collection</small><strong><?= dmd_money($totalCollection) ?></strong><span>Collection ratio: <?= dmd_safe($collectionRatio) ?>%</span></div>
        <div class="dmd-kpi bg-red"><small>Receivable</small><strong><?= dmd_money($totalReceivable) ?></strong><span>Current market outstanding</span></div>
        <div class="dmd-kpi bg-cyan"><small>Stock Valuation</small><strong><?= dmd_money($stockValue) ?></strong><span>Calculated from available stock</span></div>
        <div class="dmd-kpi bg-orange"><small>Pending Delivery</small><strong><?= dmd_num($pendingDeliveryCount) ?></strong><span><?= dmd_money($pendingDeliveryValue) ?> pending value</span></div>
        <div class="dmd-kpi bg-purple"><small>Low Stock SKU</small><strong><?= dmd_num($lowStockSku) ?></strong><span>At or below minimum stock</span></div>
        <div class="dmd-kpi bg-dark"><small>Negative Stock SKU</small><strong><?= dmd_num($negativeStockSku) ?></strong><span>Inventory mismatch risk</span></div>
        <div class="dmd-kpi bg-slate"><small>Zero Stock SKU</small><strong><?= dmd_num($zeroStockSku) ?></strong><span>Unavailable product count</span></div>
        <div class="dmd-kpi bg-green"><small>Active Customers</small><strong><?= dmd_num($activeCustomers) ?></strong><span>Ordered in selected period</span></div>
        <div class="dmd-kpi bg-blue"><small>Average Order</small><strong><?= dmd_money($avgOrderValue) ?></strong><span>Sales quality indicator</span></div>
    </div>

    <div class="dmd-grid-2">
        <div class="dmd-card">
            <h3>Executive Summary <span class="hint">Auto generated</span></h3>
            <div class="dmd-insight">
                Selected period sales is <strong><?= dmd_money($totalSales) ?></strong> and collection is <strong><?= dmd_money($totalCollection) ?></strong>.
                Collection ratio is <strong><?= dmd_safe($collectionRatio) ?>%</strong>.
                Current receivable exposure is <strong><?= dmd_money($totalReceivable) ?></strong>.
                Stock value is <strong><?= dmd_money($stockValue) ?></strong>.
                There are <strong><?= dmd_num($pendingDeliveryCount) ?></strong> pending delivery invoice(s),
                <strong><?= dmd_num($lowStockSku) ?></strong> low stock SKU and
                <strong><?= dmd_num($negativeStockSku) ?></strong> negative stock SKU.
                <?php if ($negativeStockSku > 0): ?>Inventory audit should be treated as a priority.<?php endif; ?>
            </div>
        </div>
        <div class="dmd-card">
            <h3>Red Flag Alerts <span class="hint">Priority control</span></h3>
            <?php if (!$redFlags): ?>
                <div class="dmd-empty">No critical red flag detected from available data.</div>
            <?php else: ?>
                <?php foreach ($redFlags as $flag):
                    $class = strtolower($flag['level']) === 'critical' ? 'critical' : (strtolower($flag['level']) === 'warning' ? 'warning' : 'info');
                ?>
                    <div class="dmd-alert <?= dmd_safe($class) ?>">
                        <span class="badge <?= $class === 'critical' ? 'badge-red' : ($class === 'warning' ? 'badge-orange' : 'badge-blue') ?>"><?= dmd_safe($flag['level']) ?></span>
                        <strong><?= dmd_safe($flag['title']) ?></strong>
                        <p><?= dmd_safe($flag['details']) ?></p>
                        <p><b>Action:</b> <?= dmd_safe($flag['action']) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="dmd-grid-2">
        <div class="dmd-card">
            <h3>Sales vs Collection Trend</h3>
            <?php if (!$chartLabels): ?>
                <div class="dmd-empty">No sales chart data found for selected period.</div>
            <?php else: ?>
                <canvas id="salesCollectionChart"></canvas>
            <?php endif; ?>
        </div>
        <div class="dmd-card">
            <h3>Category Wise Sales</h3>
            <?php if (!$catLabels): ?>
                <div class="dmd-empty">Invoice item table/category mapping not found or no data available.</div>
            <?php else: ?>
                <canvas id="categorySalesChart"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <div class="dmd-grid-3">
        <div class="dmd-card">
            <h3>Delivery Funnel</h3>
            <?php if (!$deliveryFunnel): ?>
                <div class="dmd-empty">Delivery status data not found.</div>
            <?php else: ?>
                <div class="dmd-funnel">
                    <?php foreach ($deliveryFunnel as $row): ?>
                        <div class="dmd-funnel-box">
                            <strong><?= dmd_num($row['count_items']) ?></strong>
                            <span><?= dmd_safe(ucwords(str_replace('_', ' ', $row['delivery_status']))) ?></span>
                            <span><?= dmd_money($row['value']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="dmd-card">
            <h3>Top Sales Customers</h3>
            <div class="dmd-table-wrap">
                <?php if (!$topCustomers): ?>
                    <div class="dmd-empty">No customer sales data found.</div>
                <?php else: ?>
                    <table class="dmd-table dmd-export-table" data-title="Top Sales Customers">
                        <thead><tr><th>Customer</th><th>Phone</th><th class="text-right">Sales</th></tr></thead>
                        <tbody>
                            <?php foreach ($topCustomers as $row): ?>
                                <tr>
                                    <td><?= dmd_safe($row['customer_name']) ?></td>
                                    <td><?= dmd_safe($row['phone']) ?></td>
                                    <td class="text-right"><strong><?= dmd_money($row['sales']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="dmd-card">
            <h3>Top Due Customers</h3>
            <div class="dmd-table-wrap">
                <?php if (!$topDueCustomers): ?>
                    <div class="dmd-empty">No due customer data found.</div>
                <?php else: ?>
                    <table class="dmd-table dmd-export-table" data-title="Top Due Customers">
                        <thead><tr><th>Customer</th><th>Type</th><th class="text-right">Due</th></tr></thead>
                        <tbody>
                            <?php foreach ($topDueCustomers as $row): ?>
                                <tr>
                                    <td><?= dmd_safe($row['customer_name']) ?><br><small><?= dmd_safe($row['phone']) ?></small></td>
                                    <td><span class="badge badge-blue"><?= dmd_safe($row['customer_type'] ?: 'N/A') ?></span></td>
                                    <td class="text-right text-danger"><strong><?= dmd_money($row['due_amount']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dmd-grid-2">
        <div class="dmd-card">
            <h3>Low / Negative Stock Products</h3>
            <div class="dmd-table-wrap">
                <?php if (!$lowStockProducts): ?>
                    <div class="dmd-empty">No low stock product detected.</div>
                <?php else: ?>
                    <table class="dmd-table dmd-export-table" data-title="Low Stock Products">
                        <thead><tr><th>Product</th><th>Category</th><th class="text-right">Stock</th><th class="text-right">Min</th><th class="text-right">Value</th></tr></thead>
                        <tbody>
                            <?php foreach ($lowStockProducts as $row):
                                $stock = (float)$row['stock_qty'];
                                $value = max($stock, 0) * (float)$row['tp_rate'];
                                $badge = $stock < 0 ? 'badge-red' : ($stock == 0 ? 'badge-orange' : 'badge-blue');
                            ?>
                                <tr>
                                    <td><?= dmd_safe($row['product_name']) ?></td>
                                    <td><?= dmd_safe($row['category']) ?></td>
                                    <td class="text-right"><span class="badge <?= $badge ?>"><?= dmd_num($stock) ?></span></td>
                                    <td class="text-right"><?= dmd_num($row['min_qty']) ?></td>
                                    <td class="text-right"><?= dmd_money($value) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="dmd-card">
            <h3>Recent Stock Movement</h3>
            <div class="dmd-table-wrap">
                <?php if (!$recentStockMoves): ?>
                    <div class="dmd-empty">Stock movement table not found or no recent movement.</div>
                <?php else: ?>
                    <table class="dmd-table dmd-export-table" data-title="Recent Stock Movement">
                        <thead><tr><th>Date</th><th>Type</th><th class="text-right">Qty</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentStockMoves as $row): ?>
                                <tr>
                                    <td><?= dmd_safe($row['movement_date']) ?></td>
                                    <td><span class="badge badge-blue"><?= dmd_safe($row['movement_type']) ?></span></td>
                                    <td class="text-right <?= (float)$row['qty'] < 0 ? 'text-danger' : 'text-success' ?>"><strong><?= dmd_num($row['qty']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dmd-card">
        <h3>System Integration Note <span class="hint">For IT team</span></h3>
        <div class="dmd-insight">
            If any section shows empty data, check table and column names in the top part of this file.
            This dashboard auto-detects common names, but your database may use different names.
            For ERP-level accuracy, make sure invoice, payment, customer balance, stock movement and product stock tables are updated in real time.
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const dmdSummaryText = <?= json_encode($summaryText, JSON_UNESCAPED_UNICODE) ?>;
const chartLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
const chartSales = <?= json_encode($chartSales, JSON_NUMERIC_CHECK) ?>;
const chartCollection = <?= json_encode($chartCollection, JSON_NUMERIC_CHECK) ?>;
const categoryLabels = <?= json_encode($catLabels, JSON_UNESCAPED_UNICODE) ?>;
const categorySales = <?= json_encode($catSales, JSON_NUMERIC_CHECK) ?>;

function copyDmdSummary() {
    navigator.clipboard.writeText(dmdSummaryText).then(() => {
        alert('DMD summary copied successfully.');
    }).catch(() => {
        const textarea = document.createElement('textarea');
        textarea.value = dmdSummaryText;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
        alert('DMD summary copied successfully.');
    });
}

function csvEscape(value) {
    value = String(value ?? '').replace(/\s+/g, ' ').trim();
    if (value.includes(',') || value.includes('"') || value.includes('\n')) {
        return '"' + value.replace(/"/g, '""') + '"';
    }
    return value;
}

function exportDashboardCSV() {
    const tables = document.querySelectorAll('.dmd-export-table');
    let csv = [];
    csv.push('DMD Viewer Dashboard');
    csv.push('Period,<?= dmd_safe($startDate) ?> to <?= dmd_safe($endDate) ?>');
    csv.push('');

    tables.forEach(table => {
        csv.push(table.dataset.title || 'Table');
        table.querySelectorAll('tr').forEach(tr => {
            const cells = Array.from(tr.querySelectorAll('th,td')).map(td => csvEscape(td.innerText));
            csv.push(cells.join(','));
        });
        csv.push('');
    });

    const blob = new Blob([csv.join('\n')], {type: 'text/csv;charset=utf-8;'});
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'dmd-dashboard-<?= dmd_safe($startDate) ?>-to-<?= dmd_safe($endDate) ?>.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

if (document.getElementById('salesCollectionChart')) {
    new Chart(document.getElementById('salesCollectionChart'), {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [
                { label: 'Sales', data: chartSales, tension: 0.35, borderWidth: 2 },
                { label: 'Collection', data: chartCollection, tension: 0.35, borderWidth: 2 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

if (document.getElementById('categorySalesChart')) {
    new Chart(document.getElementById('categorySalesChart'), {
        type: 'bar',
        data: {
            labels: categoryLabels,
            datasets: [{ label: 'Category Sales', data: categorySales, borderWidth: 1 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}
</script>

<?php
if (file_exists($footerPath)) {
    require_once $footerPath;
}
?>
