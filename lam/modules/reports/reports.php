<?php
// ============================================================
// modules/reports/reports.php — Custom reports module
// ============================================================

$from     = $_GET['from']   ?? date('Y-m-01');
$to       = $_GET['to']     ?? today();
$type     = $_GET['rtype']  ?? 'sales';
$groupBy  = $_GET['group']  ?? 'day';

$pageTitle = 'Reports';
require_once BASE_PATH . '/includes/header.php';

// ── Sales report ───────────────────────────────────────────────
$salesData = [];
if ($type === 'sales' || $type === 'all') {
    $groupExpr = match($groupBy) {
        'month' => "DATE_FORMAT(s.created_at, '%Y-%m')",
        'week'  => "YEARWEEK(s.created_at, 1)",
        default => "DATE(s.created_at)",
    };
    $salesData = dbFetchAll(
        "SELECT $groupExpr AS period,
                COUNT(s.id) AS sale_count,
                SUM(s.total) AS revenue,
                SUM(s.discount_amount) AS discounts,
                SUM(s.vat_amount) AS vat
         FROM sales s
         WHERE s.status = 'completed'
           AND DATE(s.created_at) BETWEEN ? AND ?
         GROUP BY period ORDER BY period",
        [$from, $to]
    );
}

// ── Expense report ─────────────────────────────────────────────
$expenseData = [];
if ($type === 'expenses' || $type === 'all') {
    $expenseData = dbFetchAll(
        "SELECT category, SUM(amount) AS total, COUNT(*) AS count
         FROM finance_entries WHERE type = 'expense' AND entry_date BETWEEN ? AND ?
         GROUP BY category ORDER BY total DESC",
        [$from, $to]
    );
}

// ── Payment method breakdown ───────────────────────────────────
$paymentBreakdown = [];
if ($type === 'sales' || $type === 'all') {
    // Since payment_method is stored as CSV (e.g. "cash,card"), we use FIND_IN_SET
    $paymentBreakdown = [];
    foreach (['cash', 'card', 'transfer'] as $pm) {
        $row = dbFetch(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS rev
             FROM sales WHERE status='completed'
               AND FIND_IN_SET(?, payment_method) > 0
               AND DATE(created_at) BETWEEN ? AND ?",
            [$pm, $from, $to]
        );
        $paymentBreakdown[$pm] = $row;
    }
}
$topProducts = [];
if ($type === 'products' || $type === 'all') {
    $topProducts = dbFetchAll(
        "SELECT si.product_name, SUM(si.qty) AS qty_sold, SUM(si.total_price) AS revenue
         FROM sale_items si
         JOIN sales s ON s.id = si.sale_id
         WHERE s.status = 'completed' AND DATE(s.created_at) BETWEEN ? AND ?
         GROUP BY si.product_name ORDER BY qty_sold DESC LIMIT 20",
        [$from, $to]
    );
}

// ── Summary KPIs ───────────────────────────────────────────────
$kpi = dbFetch(
    "SELECT COUNT(*) AS total_sales,
            COALESCE(SUM(total), 0) AS total_revenue,
            COALESCE(AVG(total), 0) AS avg_order
     FROM sales WHERE status='completed' AND DATE(created_at) BETWEEN ? AND ?",
    [$from, $to]
);
$totalExpenses = dbFetch(
    "SELECT COALESCE(SUM(amount), 0) AS total FROM finance_entries WHERE type='expense' AND entry_date BETWEEN ? AND ?",
    [$from, $to]
)['total'] ?? 0;
?>

<div class="d-flex justify-between align-center mb-2 flex-wrap gap-2">
  <h1>📈 Reports</h1>
  <button class="btn btn-ghost no-print" onclick="window.print()">🖨️ Print</button>
</div>

<!-- Filter bar -->
<form method="GET" class="no-print" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
  <input type="hidden" name="page" value="reports">
  <input type="date" name="from" value="<?= e($from) ?>" class="form-control" style="max-width:150px">
  <input type="date" name="to"   value="<?= e($to) ?>"   class="form-control" style="max-width:150px">
  <select name="rtype" class="form-control" style="max-width:130px">
    <option value="all"      <?= $type==='all'      ?'selected':'' ?>>All</option>
    <option value="sales"    <?= $type==='sales'    ?'selected':'' ?>>Sales</option>
    <option value="expenses" <?= $type==='expenses' ?'selected':'' ?>>Expenses</option>
    <option value="products" <?= $type==='products' ?'selected':'' ?>>Products</option>
  </select>
  <select name="group" class="form-control" style="max-width:120px">
    <option value="day"   <?= $groupBy==='day'  ?'selected':'' ?>>Daily</option>
    <option value="week"  <?= $groupBy==='week' ?'selected':'' ?>>Weekly</option>
    <option value="month" <?= $groupBy==='month'?'selected':'' ?>>Monthly</option>
  </select>
  <button type="submit" class="btn btn-primary">Generate</button>
</form>

<!-- KPI Stats -->
<div class="stats-grid">
  <div class="stat-card success">
    <div class="stat-label">Revenue</div>
    <div class="stat-value"><?= money($kpi['total_revenue'] ?? 0) ?></div>
  </div>
  <div class="stat-card accent">
    <div class="stat-label">Sales</div>
    <div class="stat-value"><?= number_format($kpi['total_sales'] ?? 0) ?></div>
  </div>
  <div class="stat-card warning">
    <div class="stat-label">Avg Order</div>
    <div class="stat-value"><?= money($kpi['avg_order'] ?? 0) ?></div>
  </div>
  <div class="stat-card danger">
    <div class="stat-label">Expenses</div>
    <div class="stat-value"><?= money($totalExpenses) ?></div>
  </div>
</div>

<!-- Sales over time -->
<?php if ($salesData): ?>
<div class="card">
  <div class="card-title">📊 Sales by <?= ucfirst($groupBy) ?> — <?= fmtDate($from) ?> to <?= fmtDate($to) ?></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Period</th><th class="text-right">Sales</th><th class="text-right">Revenue</th><th class="text-right">Discounts</th><th class="text-right">VAT</th></tr></thead>
      <tbody>
        <?php $grandRevenue = 0; foreach ($salesData as $row): $grandRevenue += $row['revenue']; ?>
        <tr>
          <td><?= e($row['period']) ?></td>
          <td class="text-right"><?= $row['sale_count'] ?></td>
          <td class="text-right"><?= money($row['revenue']) ?></td>
          <td class="text-right"><?= money($row['discounts']) ?></td>
          <td class="text-right"><?= money($row['vat']) ?></td>
        </tr>
        <?php endforeach ?>
        <tr style="font-weight:800;border-top:2px solid var(--border)">
          <td>TOTAL</td>
          <td class="text-right"><?= array_sum(array_column($salesData, 'sale_count')) ?></td>
          <td class="text-right"><?= money($grandRevenue) ?></td>
          <td class="text-right"><?= money(array_sum(array_column($salesData, 'discounts'))) ?></td>
          <td class="text-right"><?= money(array_sum(array_column($salesData, 'vat'))) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php endif ?>

<!-- Expense breakdown -->
<?php if ($expenseData): ?>
<div class="card">
  <div class="card-title">💸 Expenses by Category</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Category</th><th class="text-right">Count</th><th class="text-right">Total</th></tr></thead>
      <tbody>
        <?php foreach ($expenseData as $row): ?>
        <tr>
          <td><?= e($row['category'] ?: 'Uncategorised') ?></td>
          <td class="text-right"><?= $row['count'] ?></td>
          <td class="text-right" style="color:var(--danger)"><?= money($row['total']) ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif ?>

<!-- Payment Method Breakdown -->
<?php if ($paymentBreakdown): ?>
<div class="card">
  <div class="card-title">💳 Revenue by Payment Method</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Method</th><th class="text-right">Sales</th><th class="text-right">Revenue</th></tr></thead>
      <tbody>
        <?php $pmLabels = ['cash'=>'💵 Cash','card'=>'💳 Card','transfer'=>'🏦 Transfer']; ?>
        <?php foreach ($paymentBreakdown as $pm => $row): ?>
        <?php if ($row['cnt'] > 0): ?>
        <tr>
          <td><?= $pmLabels[$pm] ?? $pm ?></td>
          <td class="text-right"><?= $row['cnt'] ?></td>
          <td class="text-right"><?= money($row['rev']) ?></td>
        </tr>
        <?php endif ?>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif ?>

<!-- Top products -->
<?php if ($topProducts): ?>
<div class="card">
  <div class="card-title">🏆 Top Products</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Product</th><th class="text-right">Qty Sold</th><th class="text-right">Revenue</th></tr></thead>
      <tbody>
        <?php foreach ($topProducts as $row): ?>
        <tr>
          <td><?= e($row['product_name']) ?></td>
          <td class="text-right"><?= $row['qty_sold'] ?></td>
          <td class="text-right"><?= money($row['revenue']) ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
