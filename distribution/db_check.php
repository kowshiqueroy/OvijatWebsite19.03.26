<?php
/**
 * DB Schema Checker — distribution system
 * Upload to your live server root and open in browser.
 * DELETE this file after use — it reveals DB structure.
 *
 * Access control: change this password before uploading.
 */
define('CHECK_PASSWORD', '5877');

// ── Simple password gate ──────────────────────────────────────────────────────
session_start();
if (!isset($_SESSION['db_check_auth'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pw'] ?? '') === CHECK_PASSWORD) {
        $_SESSION['db_check_auth'] = true;
    } else {
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:400px;margin:60px auto">
        <h3>DB Check — Authentication</h3>
        <form method="POST">
          <input type="password" name="pw" placeholder="Password" style="padding:8px;width:100%;margin-bottom:8px" autofocus>
          <button type="submit" style="padding:8px 20px">Enter</button>
        </form></body></html>';
        exit;
    }
}

// ── Load DB connection from project config ────────────────────────────────────
require_once __DIR__ . '/config/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('<p style="color:red">Connection failed: ' . htmlspecialchars($conn->connect_error) . '</p>');
}

// ── Expected schema (generated from local DB 2026-06-29) ─────────────────────
$expected = [
    'accounts' => ['id','group_id','name','code','description','opening_balance','opening_balance_type','is_system','entity_type','entity_id','is_active','isDelete','created_at'],
    'account_groups' => ['id','name','parent_id','nature','is_system','isDelete'],
    'activity_logs' => ['id','user_id','action','isDelete','created_at'],
    'categories' => ['id','name','isDelete'],
    'company_settings' => ['id','name','logo_url','phone','email','address','isDelete','updated_at','default_cash_account_id','default_bank_account_id'],
    'customers' => ['id','user_id','name','phone','address','type','opening_balance','balance','credit_limit','is_active','isDelete'],
    'expenses' => ['id','category','description','amount','expense_date','account_id','recorded_by','isDelete','created_at'],
    'journal_entries' => ['id','entry_no','date','narration','reference_type','reference_id','created_by','is_posted','is_verified','verified_by','verified_at','isDelete','created_at'],
    'journal_lines' => ['id','journal_id','account_id','dr_amount','cr_amount','narration','isDelete'],
    'products' => ['id','category_id','name','tp_rate','dp_rate','retail_rate','stock_qty','is_active','isDelete','market_type','sku','barcode','unit','low_stock_threshold'],
    'product_batches' => ['id','product_id','batch_no','expiry_date','quantity_in','quantity_remaining','purchase_id','source','isDelete','created_at'],
    'product_visibility_rules' => ['id','product_id','user_id','hide_from_ui','hide_from_reports','created_by','created_at','isDelete'],
    'purchase_items' => ['id','purchase_id','product_id','batch_no','expiry_date','quantity','unit_cost','total','isDelete'],
    'purchase_orders' => ['id','supplier_id','invoice_no','total_amount','paid_amount','status','notes','received_by','received_at','isDelete','created_at'],
    'sales_drafts' => ['id','customer_id','created_by','total_amount','discount','vat','grand_total','general_note','status','delivery_status','delivery_date','hide_from_print','confirmed_by','confirmed_at','isDelete','created_at','order_type'],
    'sales_items' => ['id','draft_id','product_id','note','rate','billed_qty','free_qty','total','isDelete'],
    'sales_item_lots' => ['id','sales_item_id','batch_id','quantity','isDelete'],
    'sales_returns' => ['id','sale_id','customer_id','reason','total_amount','status','restock','processed_by','processed_at','isDelete','created_at'],
    'sales_return_items' => ['id','return_id','product_id','quantity','unit_rate','total','isDelete'],
    'sales_targets' => ['id','target_level','user_id','group_id','division_id','target_period','period_type','target_revenue','target_qty','isDelete','created_at'],
    'sales_target_categories' => ['id','target_id','category_id','target_qty','target_revenue','isDelete'],
    'sr_divisions' => ['id','name','code','region','isDelete','created_at'],
    'sr_groups' => ['id','name','division_id','leader_id','isDelete','created_at'],
    'stock_damages' => ['id','product_id','user_id','quantity','reason','created_at','isDelete'],
    'stock_entries' => ['id','product_id','user_id','quantity','isDelete','created_at','batch_no','expiry_date','purchase_id','notes'],
    'stock_movements' => ['id','product_id','batch_id','movement_type','quantity','reference_type','reference_id','notes','created_by','created_at','isDelete'],
    'suppliers' => ['id','name','phone','email','address','balance','opening_balance','is_active','isDelete','created_at'],
    'supplier_transactions' => ['id','supplier_id','purchase_id','type','amount','description','isDelete','created_at'],
    'transactions' => ['id','customer_id','type','amount','description','hide_from_print','created_at','isDelete'],
    'truck_loads' => ['id','truck_no','driver_name','source_location','destination_location','remarks','status','created_by','created_at','isDelete','driver_phone','expected_delivery'],
    'truck_load_items' => ['id','truck_load_id','invoice_id','isDelete'],
    'users' => ['id','username','password','phone','role','is_active','last_active','force_password_change','isDelete','created_at'],
    'user_view_permissions' => ['id','user_id','show_local','show_export','show_custom','show_sales_kpis','show_inventory_section','show_delivery_section','show_accounts_section','can_see_stock_report','can_see_inventory_report','can_see_comprehensive_report','can_see_transactions','can_see_dmd_dashboard','show_rates','show_customer_balances','updated_at'],
];

// ── Read live schema ──────────────────────────────────────────────────────────
$live = [];
$res = $conn->query("SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION");
while ($row = $res->fetch_assoc()) {
    $live[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
}

// ── Compare ───────────────────────────────────────────────────────────────────
$missing_tables  = [];
$missing_columns = []; // table => [col, ...]
$extra_tables    = [];

foreach ($expected as $table => $cols) {
    if (!isset($live[$table])) {
        $missing_tables[] = $table;
    } else {
        $live_cols = $live[$table];
        foreach ($cols as $col) {
            if (!in_array($col, $live_cols)) {
                $missing_columns[$table][] = $col;
            }
        }
    }
}

foreach (array_keys($live) as $table) {
    if (!isset($expected[$table])) {
        $extra_tables[] = $table;
    }
}

$all_ok = empty($missing_tables) && empty($missing_columns);

// ── Build fix SQL ─────────────────────────────────────────────────────────────
$fix_sql = [];

// Column type hints for ALTER TABLE (for the columns we know are missing)
$col_defs = [
    'default_cash_account_id' => 'INT(11) DEFAULT 1',
    'default_bank_account_id' => 'INT(11) DEFAULT 2',
    'order_type'              => "VARCHAR(50) DEFAULT 'Local'",
];

foreach ($missing_tables as $t) {
    $fix_sql[] = "-- ⚠ Table `$t` is missing entirely — run setup.php or restore from your local dump.";
}
foreach ($missing_columns as $table => $cols) {
    foreach ($cols as $col) {
        $def = $col_defs[$col] ?? 'VARCHAR(255) NULL  -- ⚠ verify type before running';
        $fix_sql[] = "ALTER TABLE `$table` ADD COLUMN `$col` $def;";
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DB Schema Check</title>
<style>
  body { font-family: sans-serif; max-width: 900px; margin: 30px auto; padding: 0 20px; }
  h1 { font-size: 1.4rem; }
  .ok    { color: #198754; font-weight: bold; }
  .warn  { color: #dc3545; font-weight: bold; }
  .info  { color: #6c757d; }
  table  { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
  th, td { border: 1px solid #dee2e6; padding: 7px 12px; text-align: left; font-size: 0.9rem; }
  th     { background: #f8f9fa; }
  pre    { background: #212529; color: #f8f9fa; padding: 16px; border-radius: 6px; overflow-x: auto; font-size: 0.85rem; line-height: 1.6; }
  .badge-ok   { background:#d1e7dd; color:#0a3622; border-radius:4px; padding:2px 8px; font-size:.8rem; }
  .badge-fail { background:#f8d7da; color:#58151c; border-radius:4px; padding:2px 8px; font-size:.8rem; }
  .badge-info { background:#fff3cd; color:#664d03; border-radius:4px; padding:2px 8px; font-size:.8rem; }
</style>
</head>
<body>

<h1>DB Schema Check</h1>
<p class="info">Comparing live DB (<strong><?php echo htmlspecialchars(DB_NAME); ?></strong> on <strong><?php echo htmlspecialchars(DB_HOST); ?></strong>) against local reference schema.</p>

<?php if ($all_ok): ?>
<p class="ok">✓ All <?php echo count($expected); ?> tables and all columns match. Live DB is in sync.</p>
<?php else: ?>
<p class="warn">⚠ Differences found — see details below.</p>
<?php endif; ?>

<!-- Missing Tables -->
<?php if ($missing_tables): ?>
<h2 style="color:#dc3545">Missing Tables (<?php echo count($missing_tables); ?>)</h2>
<table>
  <tr><th>Table</th><th>Status</th></tr>
  <?php foreach ($missing_tables as $t): ?>
  <tr><td><code><?php echo htmlspecialchars($t); ?></code></td><td><span class="badge-fail">MISSING</span></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- Missing Columns -->
<?php if ($missing_columns): ?>
<h2 style="color:#dc3545">Missing Columns (<?php echo array_sum(array_map('count', $missing_columns)); ?>)</h2>
<table>
  <tr><th>Table</th><th>Missing Column</th><th>Status</th></tr>
  <?php foreach ($missing_columns as $table => $cols): ?>
    <?php foreach ($cols as $col): ?>
    <tr>
      <td><code><?php echo htmlspecialchars($table); ?></code></td>
      <td><code><?php echo htmlspecialchars($col); ?></code></td>
      <td><span class="badge-fail">MISSING</span></td>
    </tr>
    <?php endforeach; ?>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- Extra Tables (on live but not local) -->
<?php if ($extra_tables): ?>
<h2 style="color:#856404">Extra Tables on Live (<?php echo count($extra_tables); ?>)</h2>
<p class="info">These exist on live but not locally — may be safe to ignore, or may indicate schema drift.</p>
<table>
  <tr><th>Table</th><th>Status</th></tr>
  <?php foreach ($extra_tables as $t): ?>
  <tr><td><code><?php echo htmlspecialchars($t); ?></code></td><td><span class="badge-info">EXTRA</span></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- Table Summary -->
<h2>All Tables Summary</h2>
<table>
  <tr><th>Table</th><th>Expected Columns</th><th>Live Columns</th><th>Status</th></tr>
  <?php foreach ($expected as $table => $cols): ?>
  <?php
    $live_count   = isset($live[$table]) ? count($live[$table]) : 0;
    $missing_here = $missing_columns[$table] ?? [];
    if (!isset($live[$table])) {
        $badge = '<span class="badge-fail">TABLE MISSING</span>';
    } elseif ($missing_here) {
        $badge = '<span class="badge-fail">'.count($missing_here).' col(s) missing</span>';
    } else {
        $badge = '<span class="badge-ok">OK</span>';
    }
  ?>
  <tr>
    <td><code><?php echo htmlspecialchars($table); ?></code></td>
    <td><?php echo count($cols); ?></td>
    <td><?php echo $live_count ?: '—'; ?></td>
    <td><?php echo $badge; ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<!-- Fix SQL -->
<?php if ($fix_sql): ?>
<h2>Fix SQL</h2>
<p class="info">Run these statements on your live DB (via phpMyAdmin → SQL tab, or cPanel MySQL):</p>
<pre><?php echo htmlspecialchars(implode("\n", $fix_sql)); ?></pre>
<?php endif; ?>

<hr>
<p class="info" style="font-size:.8rem">⚠ Delete <code>db_check.php</code> from the server after use.</p>
</body>
</html>
<?php $conn->close(); ?>
