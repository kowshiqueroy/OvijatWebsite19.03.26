<?php
// ============================================================
// modules/products/product_entries.php — History of added products
// ============================================================
requireRole(ROLE_ADMIN);

$search      = trim($_GET['q'] ?? '');
$from        = $_GET['from']   ?? date('Y-m-d', strtotime('-30 days'));
$to          = $_GET['to']     ?? today();

$where  = 'e.created_at BETWEEN ? AND ?';
$params = [$from . ' 00:00:00', $to . ' 23:59:59'];

if ($search) {
    $where .= ' AND (e.product_name LIKE ? OR e.memo_number LIKE ? OR u.full_name LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}

$page    = max(1, (int)($_GET['p'] ?? 1));
$paged   = paginate(
    "SELECT e.*, u.full_name as user_name 
     FROM product_entries e 
     LEFT JOIN users u ON u.id = e.user_id 
     WHERE $where 
     ORDER BY e.created_at DESC",
    $params, $page, 50
);

$pageTitle = 'Product Addition History';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-2 flex-wrap gap-2">
  <h1>📜 Product Addition History</h1>
</div>

<!-- ── Filters ───────────────────────────────────────────────── -->
<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <input type="hidden" name="page" value="product_entries">
  <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, memo, user…" class="form-control" style="max-width:220px">
  <input type="date" name="from" value="<?= e($from) ?>" class="form-control" style="max-width:150px">
  <input type="date" name="to"   value="<?= e($to) ?>"   class="form-control" style="max-width:150px">
  <button type="submit" class="btn btn-ghost">Filter</button>
  <a href="index.php?page=product_entries" class="btn btn-ghost">Reset</a>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Product Name</th>
          <th>Variant</th>
          <th>Qty Added</th>
          <th style="text-align:right">Cost</th>
          <th style="text-align:right">Price</th>
          <th>Memo #</th>
          <th>Memo Date</th>
          <th>Added By</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$paged['rows']): ?>
          <tr><td colspan="9" class="text-muted text-center">No history found.</td></tr>
        <?php endif ?>
        <?php foreach ($paged['rows'] as $e): ?>
        <tr>
          <td style="font-size:.8rem"><?= fmtDateTime($e['created_at']) ?></td>
          <td><strong><?= e($e['product_name']) ?></strong></td>
          <td style="font-size:.75rem">
            <?= e($e['variant_name'] ?: '—') ?> / <?= e($e['size'] ?: '—') ?> / <?= e($e['color'] ?: '—') ?>
          </td>
          <td><span class="badge badge-info"><?= (int)$e['qty_added'] ?></span></td>
          <td style="text-align:right"><?= money($e['cost']) ?></td>
          <td style="text-align:right"><?= money($e['price']) ?></td>
          <td><?= e($e['memo_number'] ?: '—') ?></td>
          <td><?= $e['memo_date'] ? fmtDate($e['memo_date']) : '—' ?></td>
          <td><?= e($e['user_name'] ?: 'System') ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($paged['last_page'] > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $paged['last_page']; $i++): ?>
      <a href="?page=product_entries&p=<?= $i ?>&q=<?= urlencode($search) ?>&from=<?= $from ?>&to=<?= $to ?>"
         class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor ?>
  </div>
  <?php endif ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
