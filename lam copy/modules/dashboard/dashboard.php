<?php
// ============================================================
// modules/dashboard/dashboard.php
// ============================================================

$today = today();
$monthStart = date('Y-m-01');

// KPIs
$todaySales = dbFetch(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS rev
     FROM sales WHERE status='completed' AND DATE(created_at) = ?",
    [$today]
);
$monthSales = dbFetch(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS rev
     FROM sales WHERE status='completed' AND created_at >= ?",
    [$monthStart]
);
$totalProducts = dbFetch("SELECT COUNT(*) AS c FROM products WHERE active=1")['c'];
$lowStock      = dbFetchAll("SELECT p.name, v.size, v.color, v.quantity FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.quantity <= 5 ORDER BY v.quantity LIMIT 10");
$recentSales   = dbFetchAll(
    "SELECT s.invoice_no, s.total, s.status, s.created_at, c.name AS customer_name
     FROM sales s LEFT JOIN customers c ON c.id=s.customer_id
     ORDER BY s.id DESC LIMIT 8"
);
$balance = dbFetch(
    "SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END),0) AS bal
     FROM finance_entries WHERE entry_date >= ?",
    [$monthStart]
)['bal'] ?? 0;

$pageTitle = 'Dashboard';
require_once BASE_PATH . '/includes/header.php';
?>

<h1 style="margin-bottom:16px">📊 Dashboard</h1>

<!-- KPI Grid -->
<div class="stats-grid">
  <div class="stat-card success">
    <div class="stat-label">Today's Revenue</div>
    <div class="stat-value">৳<?= $todaySales['rev'] ?></div>
  </div>
  <div class="stat-card accent">
    <div class="stat-label">Today's Sales</div>
    <div class="stat-value"><?= $todaySales['cnt'] ?></div>
  </div>
  <div class="stat-card warning">
    <div class="stat-label">Month Revenue</div>
    <div class="stat-value">৳<?= $monthSales['rev'] ?></div>
  </div>
  <div class="stat-card <?= $balance >= 0 ? 'accent' : 'danger' ?>">
    <div class="stat-label">Month Balance</div>
    <div class="stat-value">৳<?= $balance ?></div>
  </div>
</div>

<div class="form-row cols-2">

  <!-- Recent Sales -->
  <div class="card">
    <div class="card-title">🧾 Recent Sales</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Invoice</th><th>Customer</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recentSales as $s): ?>
          <tr>
            <td><a href="index.php?page=invoice&id=<?= /* need to get id */ 0 ?>"><?= e($s['invoice_no']) ?></a></td>
            <td><?= e($s['customer_name'] ?? 'Walk-in') ?></td>
            <td class="text-right"><?= money($s['total']) ?></td>
            <td><span class="badge badge-<?= $s['status']==='completed'?'success':'warning' ?>"><?= $s['status'] ?></span></td>
          </tr>
          <?php endforeach ?>
          <?php if (!$recentSales): ?><tr><td colspan="4" class="text-muted text-center">No sales yet.</td></tr><?php endif ?>
        </tbody>
      </table>
    </div>
    <div class="mt-1"><a href="index.php?page=sales" class="btn btn-ghost btn-sm">View All →</a></div>
  </div>

  <!-- Low Stock Alert -->
  <div class="card">
    <div class="card-title">⚠️ Low Stock Alert <span class="badge badge-danger"><?= count($lowStock) ?></span></div>
    <?php if ($lowStock): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Product</th><th>Size</th><th>Color</th><th class="text-right">Qty</th></tr></thead>
        <tbody>
          <?php foreach ($lowStock as $l): ?>
          <tr>
            <td><?= e($l['name']) ?></td>
            <td><?= e($l['size']) ?></td>
            <td><?= e($l['color']) ?></td>
            <td class="text-right" style="color:<?= $l['quantity']==0?'var(--danger)':'var(--warning)' ?>;font-weight:700"><?= $l['quantity'] ?></td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-muted">All products are well-stocked ✅</p>
    <?php endif ?>
    <div class="mt-1"><a href="index.php?page=inventory_report&low_stock=1" class="btn btn-ghost btn-sm">Manage Products →</a></div>
  </div>

</div>

<!-- Quick actions -->
<div class="card">
  <div class="card-title">⚡ Quick Actions</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="index.php?page=pos" class="btn btn-primary">🛒 New Sale</a>
    <a href="index.php?page=products" class="btn btn-ghost">📦 Add Product</a>
    <a href="index.php?page=finance" class="btn btn-ghost">💰 Add Expense</a>
    <a href="index.php?page=reports" class="btn btn-ghost">📈 Reports</a>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
