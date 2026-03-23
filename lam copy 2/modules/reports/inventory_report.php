<?php
// ============================================================
// modules/products/inventory_report.php — Detailed Variant Report
// ============================================================
requireLogin();

// ── Load Filter Data ──────────────────────────────────────────
$categories = dbFetchAll('SELECT * FROM categories ORDER BY name');
$brands     = dbFetchAll('SELECT * FROM brands ORDER BY name');

$search      = trim($_GET['q'] ?? '');
$catFilter   = (int)($_GET['cat'] ?? 0);
$brandFilter = (int)($_GET['brand'] ?? 0);
$stockFilter = $_GET['stock'] ?? ''; // 'in_stock', 'out_of_stock', or ''

// Handle low stock threshold
$lowStockQty = $_GET['low_stock_qty'] ?? '';
// If accessed via quick link (?page=inventory_report&low_stock=1), default to a threshold of 5
if (isset($_GET['low_stock']) && $_GET['low_stock'] == '1' && $lowStockQty === '') {
    $lowStockQty = 5; 
}
// For visual highlighting in the table
$highlightThreshold = $lowStockQty !== '' ? (int)$lowStockQty : 5;

// ── Build Query ───────────────────────────────────────────────
$where  = '1=1';
$params = [];

if ($search) { 
    $where .= ' AND (p.name LIKE ? OR p.description LIKE ? OR v.barcode LIKE ? OR v.size LIKE ? OR v.color LIKE ?)'; 
    $searchWildcard = "%$search%";
    array_push($params, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard); 
}
if ($catFilter) { 
    $where .= ' AND p.category_id = ?'; 
    $params[] = $catFilter; 
}
if ($brandFilter) { 
    $where .= ' AND p.brand_id = ?'; 
    $params[] = $brandFilter; 
}
if ($stockFilter === 'in_stock') {
    $where .= ' AND v.quantity > 0';
} elseif ($stockFilter === 'out_of_stock') {
    $where .= ' AND v.quantity <= 0';
}
if ($lowStockQty !== '') {
    $where .= ' AND v.quantity <= ?';
    $params[] = (int)$lowStockQty;
}

// Fetch variants joined with their parent products
$sql = "SELECT 
            v.*, 
            p.name AS product_name, 
            p.description AS serial_desc,
            c.name AS category_name, 
            b.name AS brand_name,
            (v.cost * v.quantity) AS total_variant_cost,
            (v.regular * v.quantity) AS total_variant_regular,
            (v.price * v.quantity) AS total_variant_price
        FROM product_variants v
        JOIN products p ON v.product_id = p.id
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE $where
        ORDER BY p.name ASC, v.size ASC, v.color ASC";

$variants = dbFetchAll($sql, $params);

// ── Calculate Grand Totals ────────────────────────────────────
$grandTotalQty = 0;
$grandTotalCost = 0;
$grandTotalRegular = 0;
$grandTotalPrice = 0;
$lowStockCount = 0;

foreach ($variants as $v) {
    $grandTotalQty     += (int)$v['quantity'];
    $grandTotalCost    += (float)$v['total_variant_cost'];
    $grandTotalRegular += (float)$v['total_variant_regular'];
    $grandTotalPrice   += (float)$v['total_variant_price'];
    
    if ((int)$v['quantity'] <= $highlightThreshold && (int)$v['quantity'] > 0) {
        $lowStockCount++;
    }
}

$pageTitle = 'Detailed Inventory Report';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-2 flex-wrap gap-2">
  <h1>📊 Detailed Inventory Report</h1>
  <button class="btn btn-ghost" onclick="window.print()">🖨️ Print Report</button>
</div>

<form method="GET" class="card" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; padding: 15px;">
  <input type="hidden" name="page" value="inventory_report">
  
  <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, barcode, size, color…" class="form-control" style="min-width:200px; flex-grow: 1;">
  
  <select name="cat" class="form-control" style="max-width:160px">
    <option value="">All Categories</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $catFilter == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
    <?php endforeach ?>
  </select>
  
  <select name="brand" class="form-control" style="max-width:160px">
    <option value="">All Brands</option>
    <?php foreach ($brands as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $brandFilter == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
    <?php endforeach ?>
  </select>

  <select name="stock" class="form-control" style="max-width:150px">
    <option value="">All Stock</option>
    <option value="in_stock" <?= $stockFilter === 'in_stock' ? 'selected' : '' ?>>In Stock (>0)</option>
    <option value="out_of_stock" <?= $stockFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock (0)</option>
  </select>

  <div class="d-flex align-center gap-1" style="max-width:200px">
    <label style="white-space:nowrap; font-size:0.9rem; margin-bottom:0;">Max Qty:</label>
    <input type="number" name="low_stock_qty" value="<?= e($lowStockQty) ?>" placeholder="e.g. 5" class="form-control" min="0" style="width: 80px;">
  </div>
  
  <button type="submit" class="btn btn-primary">Filter</button>
  <a href="index.php?page=inventory_report" class="btn btn-ghost">Reset</a>
</form>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="padding: 15px; text-align: center; border-left: 4px solid var(--primary);">
        <h4 style="margin:0; color:var(--text-muted); font-size: 0.9rem;">Total Items</h4>
        <h2 style="margin:5px 0 0 0;"><?= number_format($grandTotalQty) ?></h2>
    </div>
    <div class="card" style="padding: 15px; text-align: center; border-left: 4px solid #f39c12;">
        <h4 style="margin:0; color:var(--text-muted); font-size: 0.9rem;">Low Stock Alerts</h4>
        <h2 style="margin:5px 0 0 0; color: #f39c12;"><?= number_format($lowStockCount) ?></h2>
    </div>
    <div class="card" style="padding: 15px; text-align: center; border-left: 4px solid #e74c3c;">
        <h4 style="margin:0; color:var(--text-muted); font-size: 0.9rem;">Inventory Cost</h4>
        <h2 style="margin:5px 0 0 0;"><?= number_format($grandTotalCost, 2) ?></h2>
    </div>
    <div class="card" style="padding: 15px; text-align: center; border-left: 4px solid #2ecc71;">
        <h4 style="margin:0; color:var(--text-muted); font-size: 0.9rem;">Selling Value</h4>
        <h2 style="margin:5px 0 0 0;"><?= number_format($grandTotalPrice, 2) ?></h2>
    </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Barcode</th>
          <th>Product Name</th>
          <th>Category</th>
          <th>Brand</th>
          <th>Size</th>
          <th>Color</th>
          <th style="text-align: right;">Qty</th>
          <th style="text-align: right;">Cost</th>
          <th style="text-align: right;">Regular</th>
          <th style="text-align: right;">Price</th>
          <th style="text-align: right; background: var(--surface2);">Total Cost</th>
          <th style="text-align: right; background: var(--surface2);">Total Value</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$variants): ?>
          <tr><td colspan="12" class="text-muted text-center">No variants match your search.</td></tr>
        <?php endif ?>
        
        <?php foreach ($variants as $v): 
            // Determine quantity color class
            $qtyClass = 'text-success';
            if ($v['quantity'] <= 0) {
                $qtyClass = 'text-danger'; // Out of stock (Red)
            } elseif ($v['quantity'] <= $highlightThreshold) {
                $qtyClass = 'text-warning'; // Low stock (Orange/Yellow depending on your CSS)
            }
        ?>
        <tr>
          <td style="font-family: monospace; font-size: 0.9em;"><?= e($v['barcode'] ?? '—') ?></td>
          <td>
            <strong><?= e($v['product_name']) ?></strong>
            <?php if (!empty($v['serial_desc'])): ?>
                <br><small class="text-muted">SN: <?= e($v['serial_desc']) ?></small>
            <?php endif; ?>
          </td>
          <td><?= e($v['category_name'] ?? '—') ?></td>
          <td><?= e($v['brand_name'] ?? '—') ?></td>
          <td><span class="badge badge-info"><?= e($v['size'] ?: '—') ?></span></td>
          <td><?= e($v['color'] ?: '—') ?></td>
          
          <td style="text-align: right;">
            <strong class="<?= $qtyClass ?>" <?= $qtyClass === 'text-warning' ? 'style="color: #f39c12;"' : '' ?>>
                <?= (int)$v['quantity'] ?>
            </strong>
          </td>
          <td style="text-align: right;"><?= number_format($v['cost'], 2) ?></td>
          <td style="text-align: right;"><?= number_format($v['regular'], 2) ?></td>
          <td style="text-align: right;"><?= number_format($v['price'], 2) ?></td>
          
          <td style="text-align: right; background: var(--surface2);"><?= number_format($v['total_variant_cost'], 2) ?></td>
          <td style="text-align: right; background: var(--surface2);"><?= number_format($v['total_variant_price'], 2) ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
      <?php if ($variants): ?>
      <tfoot>
        <tr style="font-weight: bold; background: var(--surface2);">
            <td colspan="6" style="text-align: right;">GRAND TOTALS:</td>
            <td style="text-align: right;"><?= number_format($grandTotalQty) ?></td>
            <td colspan="3"></td>
            <td style="text-align: right;"><?= number_format($grandTotalCost, 2) ?></td>
            <td style="text-align: right;"><?= number_format($grandTotalPrice, 2) ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>