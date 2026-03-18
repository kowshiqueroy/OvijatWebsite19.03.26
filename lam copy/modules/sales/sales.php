<?php 
// modules/sales/sales.php — Sales list & management
// ============================================================

$search = trim($_GET['q'] ?? '');
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? today();
$status = $_GET['status'] ?? '';
$pm     = $_GET['pm']     ?? ''; // Payment Method
$page   = max(1, (int)($_GET['p'] ?? 1));

$where  = 'DATE(s.created_at) BETWEEN ? AND ?';
$params = [$from, $to];

if ($status) { $where .= ' AND s.status = ?'; $params[] = $status; }
if ($pm)     { $where .= ' AND s.payment_method LIKE ?'; $params[] = "%$pm%"; }
if ($search) { 
    $where .= ' AND (s.invoice_no LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR s.notes LIKE ? OR EXISTS (SELECT 1 FROM sale_items si WHERE si.sale_id = s.id AND si.product_name LIKE ?))'; 
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]); 
}

// Summary Query for the filtered set
$summarySql = "SELECT SUM(s.total) as total_sales, SUM(s.discount_amount) as total_disc, SUM(s.vat_amount) as total_vat, SUM(s.subtotal) as total_sub, COUNT(*) as count 
               FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE $where";
$summary = dbFetch($summarySql, $params);

// Total Items (Qty)
$qtySql = "SELECT SUM(si.qty) as total_qty FROM sale_items si WHERE si.sale_id IN (SELECT s.id FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE $where)";
$qtyData = dbFetch($qtySql, $params);
$summary['total_qty'] = $qtyData['total_qty'] ?? 0;

// Total Profit (Revenue - Cost)
$profitSql = "SELECT SUM(si.total_price - (COALESCE(v.cost, 0) * si.qty)) as total_profit 
              FROM sale_items si 
              LEFT JOIN product_variants v ON v.id = si.variant_id
              WHERE si.sale_id IN (SELECT s.id FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE $where)";
$profitData = dbFetch($profitSql, $params);
$summary['total_profit'] = $profitData['total_profit'] ?? 0;

$paged = paginate(
    "SELECT s.*, u.full_name AS staff, c.name AS customer_name, c.phone AS customer_phone
     FROM sales s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN customers c ON c.id = s.customer_id
     WHERE $where ORDER BY s.id DESC",
    $params, $page, 50
);

// --- Fetch Items for the current page ---
$allSaleItems = [];
if (!empty($paged['rows'])) {
    $saleIds = array_column($paged['rows'], 'id');
    $placeholders = implode(',', array_fill(0, count($saleIds), '?'));
    $itemsRaw = dbFetchAll("SELECT si.*, v.variant_name 
                            FROM sale_items si 
                            LEFT JOIN product_variants v ON v.id = si.variant_id 
                            WHERE si.sale_id IN ($placeholders)", $saleIds);
    foreach ($itemsRaw as $si) {
        $allSaleItems[$si['sale_id']][] = $si;
    }
}

$pageTitle = 'Sales';
require_once BASE_PATH . '/includes/header.php';
?>

<style>
@media print {
  .no-print, .app-header, .side-nav, .nav-overlay, .pagination, .btn { display: none !important; }
  .app-main { margin: 0 !important; padding: 0 !important; }
  .card { border: none !important; box-shadow: none !important; }
  table { font-size: 8pt !important; width: 100% !important; }
  th, td { padding: 3px !important; border-bottom: 1px solid #eee !important; }
  .badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
  .print-only { display: block !important; }
  
  form.print-show-filters { display: flex !important; gap: 5px !important; margin-bottom: 5px !important; }
  .sales-summary-bar { background: #fff !important; border: 1px solid #000 !important; color: #000 !important; padding: 5px !important; display: flex !important; justify-content: space-around !important; font-size: 9pt !important; }
}
.print-only { display: none; margin-bottom: 15px; border-bottom: 2px solid #333; padding-bottom: 8px; }
.print-header-grid { display: grid; grid-template-columns: 1fr 1fr; font-size: 10pt; }

.sales-summary-bar { 
    display: flex; gap: 20px; flex-wrap: wrap; background: var(--surface2); 
    border: 1px solid var(--border); border-radius: 8px; padding: 12px 20px; 
    margin-bottom: 15px; align-items: center; justify-content: space-between;
}
.summary-stat { display: flex; flex-direction: column; }
.summary-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
.summary-val { font-size: 1rem; font-weight: 800; }
</style>

<div class="print-only">
  <div style="font-size: 16pt; font-weight: 800; text-transform: uppercase; margin-bottom: 5px;"><?= e(getSetting('shop_name', APP_NAME)) ?> — Sales Report</div>
  <div class="print-header-grid">
    <div>
      <strong>Date:</strong> 
      <?php if ($from === $to): ?>
        <?= fmtDate($from) ?>
      <?php else: ?>
        <?= fmtDate($from) ?> to <?= fmtDate($to) ?>
      <?php endif; ?>
    </div>
    <div style="text-align: right;">
      <?php 
        $activeFilters = [];
        if ($search) $activeFilters[] = "Search: '$search'";
        if ($status) $activeFilters[] = "Status: " . strtoupper($status);
        if ($pm)     $activeFilters[] = "Payment: " . strtoupper($pm);
        echo $activeFilters ? implode(' | ', $activeFilters) : 'All Records';
      ?>
    </div>
  </div>
</div>

<div class="d-flex justify-between align-center mb-2 flex-wrap gap-2">
  <h1>🧾 Sales List</h1>
  <div class="no-print">
    <button class="btn btn-ghost" onclick="window.print()">🖨️ Print Report</button>
    <a href="index.php?page=pos" class="btn btn-primary">+ New Sale</a>
  </div>
</div>

<!-- ── Summary Stats ────────────────────────────────────────── -->
<div class="sales-summary-bar">
  <div class="summary-stat">
    <span class="summary-label">Orders</span>
    <span class="summary-val"><?= (int)$summary['count'] ?></span>
  </div>
  <div class="summary-stat">
    <span class="summary-label">Items Sold</span>
    <span class="summary-val"><?= (int)$summary['total_qty'] ?></span>
  </div>
  <div class="summary-stat">
    <span class="summary-label">Total Sales</span>
    <span class="summary-val text-success"><?= money($summary['total_sales'] ?: 0) ?></span>
  </div>
  <div class="summary-stat">
    <span class="summary-label">Discount</span>
    <span class="summary-val text-danger"><?= money($summary['total_disc'] ?: 0) ?></span>
  </div>
  <div class="summary-stat">
    <span class="summary-label">VAT</span>
    <span class="summary-val"><?= money($summary['total_vat'] ?: 0) ?></span>
  </div>
  <div class="summary-stat">
    <span class="summary-label">Net Profit</span>
    <span class="summary-val" style="color:#2ed573"><?= money($summary['total_profit'] ?: 0) ?></span>
  </div>
</div>

<!-- ── Filter Form ─────────────────────────────────────────── -->
<form method="GET" class="print-show-filters" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <input type="hidden" name="page" value="sales">
  <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search invoice, customer, phone…" class="form-control" style="max-width:220px">
  <input type="date" name="from" value="<?= e($from) ?>" class="form-control" style="max-width:145px">
  <input type="date" name="to"   value="<?= e($to) ?>"   class="form-control" style="max-width:145px">
  <select name="status" class="form-control" style="max-width:130px">
    <option value="">All Statuses</option>
    <option value="completed" <?= $status==='completed'?'selected':'' ?>>Completed</option>
    <option value="draft"     <?= $status==='draft'    ?'selected':'' ?>>Draft</option>
    <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
  </select>
  <select name="pm" class="form-control" style="max-width:130px">
    <option value="">Payment...</option>
    <option value="cash"     <?= $pm==='cash'    ?'selected':'' ?>>Cash</option>
    <option value="card"     <?= $pm==='card'    ?'selected':'' ?>>Card</option>
    <option value="transfer" <?= $pm==='transfer'?'selected':'' ?>>Bank</option>
  </select>
  <button type="submit" class="btn btn-ghost no-print">Filter</button>
  <a href="index.php?page=sales" class="btn btn-ghost no-print">Reset</a>
</form>

<?php 
$activeFilters = [];
if ($search) $activeFilters[] = "Keyword: <strong>'".e($search)."'</strong>";
if ($from || $to) {
    $dateStr = ($from === $to) ? fmtDate($from) : fmtDate($from) . " to " . fmtDate($to);
    $activeFilters[] = "Date: <strong>$dateStr</strong>";
}
if ($status) $activeFilters[] = "Status: <strong>".strtoupper($status)."</strong>";
if ($pm)     $activeFilters[] = "Payment: <strong>".strtoupper($pm)."</strong>";
?>

<?php if ($activeFilters): ?>
<div style="background:var(--surface2); border:1px solid var(--border); padding:8px 12px; border-radius:8px; margin-bottom:12px; font-size:0.85rem; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
  <span style="color:var(--text-muted); font-weight:600; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.5px;">Active Filters:</span>
  <?= implode(' <span style="color:var(--border)">|</span> ', $activeFilters) ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table style="font-size: 0.82rem;">
      <thead>
        <tr>
          <th>Invoice / Date</th>
          <th>Customer & Items</th>
          <th class="text-right">Financials</th>
          <th>Status / Pay</th>
          <th class="no-print">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($paged['rows'] as $s): ?>
        <tr>
          <td style="white-space:nowrap">
            <strong><?= e($s['invoice_no']) ?></strong>
            <div class="detail-small"><?= fmtDateTime($s['created_at']) ?></div>
            <div class="detail-small" style="color:var(--accent)"><?= e($s['staff']) ?></div>
          </td>
          <td>
            <div style="font-weight:600; margin-bottom:2px">
              <?= e($s['customer_name'] ?? 'Walk-in') ?> 
              <span style="font-weight:400; font-size:0.7rem; color:var(--text-muted)"><?= e($s['customer_phone'] ? '('.$s['customer_phone'].')' : '') ?></span>
            </div>
            
            <table style="width:100%; font-size:0.68rem; border-collapse:collapse; line-height:1.1; margin-top:4px;">
              <thead>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.1); color:var(--text-muted); text-align:left;">
                  <th style="padding:2px 0;">Item</th>
                  <th style="padding:2px 0;">Var</th>
                  <th class="text-right" style="padding:2px 0;">Qty</th>
                  <th class="text-right" style="padding:2px 0;">Price</th>
                  <th class="text-right" style="padding:2px 0;">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                $items = $allSaleItems[$s['id']] ?? [];
                foreach ($items as $si): 
                  $variantStr = array_filter([$si['variant_name'], $si['size'], $si['color']]);
                  $variantStr = implode(' / ', $variantStr);
                ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                  <td style="padding:2px 0; max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($si['product_name']) ?></td>
                  <td style="padding:2px 0; color:var(--text-muted);"><?= e($variantStr ?: '—') ?></td>
                  <td class="text-right" style="padding:2px 0; font-weight:600;"><?= $si['qty'] ?></td>
                  <td class="text-right" style="padding:2px 0;"><?= number_format($si['unit_price'], 0) ?></td>
                  <td class="text-right" style="padding:2px 0; font-weight:600;"><?= number_format($si['total_price'], 0) ?></td>
                </tr>
                <?php if ($si['notes']): ?>
                <tr>
                  <td colspan="5" style="padding:1px 0 3px 8px; font-style:italic; color:var(--accent); font-size:0.62rem;">
                    ↳ <?= e($si['notes']) ?>
                  </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
              </tbody>
            </table>

            <?php if ($s['notes']): ?>
              <div class="detail-small" style="font-style:italic; margin-top:4px; padding-top:2px; border-top:1px dashed var(--border);">📝 <?= e($s['notes']) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-right">
            <div style="font-weight:800; font-size:0.95rem; color:var(--text);"><?= money($s['total']) ?></div>
            <div class="money-small">
              Sub: <?= number_format($s['subtotal'],0) ?> 
              <?php if($s['discount_amount'] > 0): ?> | <span class="text-danger">Disc: -<?= number_format($s['discount_amount'],0) ?></span><?php endif; ?>
              <?php if($s['vat_amount'] > 0): ?> | <span class="text-success">VAT: +<?= number_format($s['vat_amount'],0) ?></span><?php endif; ?>
            </div>
          </td>
          <td>
            <span class="badge badge-<?= ['completed'=>'success','draft'=>'warning','cancelled'=>'danger'][$s['status']] ?? 'grey' ?>" style="font-size:0.62rem; padding:1px 4px; display:block; text-align:center; margin-bottom:3px;">
              <?= strtoupper($s['status']) ?>
            </span>
            <div class="detail-small" style="text-align:center; text-transform:uppercase; font-weight:600;"><?= e($s['payment_method']) ?></div>
          </td>
          <td class="no-print" style="white-space:nowrap">
            <a href="index.php?page=invoice&id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm" style="padding:2px 6px; font-size:0.75rem">View</a>
            <?php if ($s['status'] === 'draft'): ?>
              <a href="index.php?page=pos&id=<?= $s['id'] ?>" class="btn btn-primary btn-sm" style="padding:2px 6px; font-size:0.75rem">POS</a>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
        <?php if (!$paged['rows']): ?>
          <tr><td colspan="5" class="text-muted text-center">No sales found.</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>

  <?php if ($paged['last_page'] > 1): ?>
  <div class="pagination no-print">
    <?php for ($i = 1; $i <= $paged['last_page']; $i++): ?>
      <a href="?page=sales&p=<?= $i ?>&from=<?= $from ?>&to=<?= $to ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($search) ?>&pm=<?= urlencode($pm) ?>"
         class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor ?>
  </div>
  <?php endif ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
