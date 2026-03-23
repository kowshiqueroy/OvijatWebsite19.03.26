<?php
// ============================================================
// modules/products/products.php — Product management module
// ============================================================

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'save_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    verify_csrf();

    $id   = (int)($_POST['product_id_db'] ?? 0);
    $data = [
        'category_id' => (int)$_POST['category_id'] ?: null,
        'brand_id'    => (int)$_POST['brand_id'] ?: null,
        'name'        => trim($_POST['name']),
        'description' => trim($_POST['description'] ?? ''),
        'memo_number' => trim($_POST['memo_number'] ?? ''),
        'memo_date'   => $_POST['memo_date'] ?: null,
        'notes'       => trim($_POST['notes'] ?? ''),
        'active'      => 1,
    ];

    // Handle inline new category
    if (!empty($_POST['category_new'])) {
        $catName = trim($_POST['category_new']);
        $existing = dbFetch('SELECT id FROM categories WHERE name = ?', [$catName]);
        $data['category_id'] = $existing ? $existing['id'] : dbInsert('categories', ['name' => $catName]);
    }

    // Handle inline new brand
    if (!empty($_POST['brand_new'])) {
        $brandName = trim($_POST['brand_new']);
        $existing = dbFetch('SELECT id FROM brands WHERE name = ?', [$brandName]);
        $data['brand_id'] = $existing ? $existing['id'] : dbInsert('brands', ['name' => $brandName]);
    }

    if ($id) {
        dbUpdate('products', $data, 'id = ?', [$id]);
        logAction('UPDATE', 'products', $id, 'Updated product: ' . $data['name']);
        flash('success', 'Product updated.');
    } else {
        $data['product_id'] = generateProductId();
        $data['created_at'] = now();
        $id = dbInsert('products', $data);
        logAction('CREATE', 'products', $id, 'Created product: ' . $data['name']);
        flash('success', 'Product created.');
    }

    // Save variants
    $variantIds   = $_POST['variant_id']       ?? [];
    $variantNames = $_POST['variant_name']     ?? [];
    $sizes        = $_POST['variant_size']     ?? [];
    $colors       = $_POST['variant_color']    ?? [];
    $costs        = $_POST['variant_cost']     ?? [];
    $regulars     = $_POST['variant_regular']  ?? [];
    $prices       = $_POST['variant_price']    ?? [];
    $quantities   = $_POST['variant_qty']      ?? [];

    $existingVids = array_filter(array_map('intval', $variantIds));
    if ($existingVids) {
        $in = implode(',', $existingVids);
        dbQuery("DELETE FROM product_variants WHERE product_id = ? AND id NOT IN ($in)", [$id]);
    } else {
        dbQuery("DELETE FROM product_variants WHERE product_id = ?", [$id]);
    }

    foreach ($sizes as $i => $size) {
        $vid  = (int)($variantIds[$i] ?? 0);
        $cost = (float)($costs[$i] ?? 0);

        $vdata = [
            'product_id'   => $id,
            'variant_name' => trim($variantNames[$i] ?? ''),
            'size'         => trim($size),
            'color'        => trim($colors[$i] ?? ''),
            'cost'         => $cost,
            'regular'      => (float)($regulars[$i] ?? 0),
            'price'        => (float)($prices[$i] ?? 0),
            'quantity'     => (int)($quantities[$i] ?? 0),
        ];

        if ($vid) {
            dbUpdate('product_variants', $vdata, 'id = ?', [$vid]);
        } else {
            $vdata['created_at'] = now();
            $newVid = dbInsert('product_variants', $vdata);
            if ($newVid) {
                $barcode = str_pad((string)($newVid % 10000000000), 10, '0', STR_PAD_LEFT);
                dbUpdate('product_variants', ['barcode' => $barcode], 'id = ?', [$newVid]);
                $vid = $newVid; // Update $vid for the entry log
            }
        }

        // ── Log the entry (History of what was added) ──
        if ($vdata['quantity'] > 0) {
            dbInsert('product_entries', [
                'product_id'   => $id,
                'variant_id'   => $vid,
                'product_name' => $data['name'],
                'variant_name' => $vdata['variant_name'],
                'size'         => $vdata['size'],
                'color'        => $vdata['color'],
                'cost'         => $vdata['cost'],
                'price'        => $vdata['price'],
                'regular'      => $vdata['regular'],
                'qty_added'    => $vdata['quantity'],
                'memo_number'  => $data['memo_number'],
                'memo_date'    => $data['memo_date'],
                'user_id'      => currentUser()['id'],
                'created_at'   => now(),
            ]);
        }
    }

    redirect('products');
}

if ($action === 'delete' && canDelete()) {
    $id = (int)($_GET['id'] ?? 0);
    $prod = dbFetch('SELECT name FROM products WHERE id = ?', [$id]);
    dbDelete('products', 'id = ?', [$id]);
    logAction('DELETE', 'products', $id, 'Deleted product: ' . ($prod['name'] ?? ''));
    flash('success', 'Product deleted.');
    redirect('products');
}

// ── Load data ─────────────────────────────────────────────────
$categories  = dbFetchAll('SELECT * FROM categories ORDER BY name');
$brands      = dbFetchAll('SELECT * FROM brands ORDER BY name');
$productNames = dbFetchAll('SELECT DISTINCT name FROM products ORDER BY name');
$search      = trim($_GET['q'] ?? '');
$catFilter   = (int)($_GET['cat'] ?? 0);
$brandFilter = (int)($_GET['brand'] ?? 0);

$where  = '1=1';
$params = [];
if ($search)     { $where .= ' AND (p.name LIKE ? OR p.description LIKE ? OR p.memo_number LIKE ? OR c.name LIKE ? OR b.name LIKE ?)';
                   $s = "%$search%"; $params = array_merge($params, [$s,$s,$s,$s,$s]); }
if ($catFilter)  { $where .= ' AND p.category_id = ?';    $params[] = $catFilter; }
if ($brandFilter){ $where .= ' AND p.brand_id = ?';       $params[] = $brandFilter; }

$products = dbFetchAll(
    "SELECT p.*, c.name AS category_name, b.name AS brand_name,
            COUNT(v.id) AS variant_count,
            SUM(v.quantity) AS total_stock,
            SUM(v.cost * v.quantity) AS total_cost_val,
            SUM(v.price * v.quantity) AS total_price_val,
            SUM(v.regular * v.quantity) AS total_regular_val
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN brands b ON b.id = p.brand_id
     LEFT JOIN product_variants v ON v.product_id = p.id
     WHERE $where
     GROUP BY p.id
     ORDER BY p.created_at DESC",
    $params
);

// For table: load all variants grouped by product
$allVariants = [];
if ($products) {
    $pids = implode(',', array_column($products, 'id'));
    $rows = dbFetchAll("SELECT * FROM product_variants WHERE product_id IN ($pids) ORDER BY product_id, id");
    foreach ($rows as $r) {
        $allVariants[$r['product_id']][] = $r;
    }
}

// Edit mode
$editing  = null;
$editVars = [];
if (!empty($_GET['edit'])) {
    $editing  = dbFetch('SELECT * FROM products WHERE id = ?', [(int)$_GET['edit']]);
    $editVars = dbFetchAll('SELECT * FROM product_variants WHERE product_id = ? ORDER BY id', [(int)$_GET['edit']]);
}

$pageTitle = 'Products';
require_once BASE_PATH . '/includes/header.php';
?>

<style>
.variant-mismatch { outline: 2px solid #e74c3c !important; background: #fff5f5 !important; }
.copy-product-btn { cursor: pointer; }
</style>

<!-- ── Page header ───────────────────────────────────────────── -->
<div class="d-flex justify-between align-center mb-2 flex-wrap gap-2">
  <h1>📦 Products</h1>
  <button class="btn btn-primary" onclick="openModal('productModal')">+ Add Product</button>
</div>

<!-- ── Filters ───────────────────────────────────────────────── -->
<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <input type="hidden" name="page" value="products">
  <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, serial, memo, category, brand…" class="form-control" style="max-width:280px">
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
  <button type="submit" class="btn btn-ghost">Filter</button>
  <a href="index.php?page=products" class="btn btn-ghost">Reset</a>
</form>

<!-- ── Products table ────────────────────────────────────────── -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Category</th>
          <th>Brand</th>
          <th>Serial</th>
          <th>Memo #</th>
          <th>Variants & Stock</th>
          <th style="text-align:right">Total Cost</th>
          <th style="text-align:right">Total Price</th>
          <th style="text-align:right">Total Regular</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$products): ?>
          <tr><td colspan="11" class="text-muted text-center">No products found.</td></tr>
        <?php endif ?>
        <?php foreach ($products as $p): ?>
        <tr>
          <td><?= e($p['id']) ?></td>
          <td><strong><?= e($p['name']) ?></strong></td>
          <td><?= e($p['category_name'] ?? '—') ?></td>
          <td><?= e($p['brand_name'] ?? '—') ?></td>
          <td><?= e($p['description'] ?? '—') ?></td>
          <td><?= e($p['memo_number'] ?? '—') ?></td>
          <td>
            <?php
            $pvars = $allVariants[$p['id']] ?? [];
            if ($pvars):
            ?>
            <div style="display:flex;flex-wrap:wrap;gap:3px">
              <?php foreach ($pvars as $pv):
                $label = array_filter([
                    $pv['variant_name'] ?? '',
                    $pv['size'] ?? '',
                    $pv['color'] ?? '',
                ]);
                $label = implode(' / ', $label) ?: '—';
              ?>
              <span class="badge badge-info" title="Cost: <?= $pv['cost'] ?> | Price: <?= $pv['price'] ?> | Regular: <?= $pv['regular'] ?> | Qty: <?= $pv['quantity'] ?>"
                    style="font-size:.7rem;cursor:default">
                <?= e($label) ?> ×<?= (int)$pv['quantity'] ?>
              </span>
              <?php endforeach ?>
            </div>
            <div style="font-size:.72rem;color:var(--text-muted);margin-top:2px">
              <?= $p['variant_count'] ?> variant<?= $p['variant_count'] != 1 ? 's' : '' ?> · <?= (int)$p['total_stock'] ?> total
            </div>
            <?php else: ?>
              <span class="text-muted" style="font-size:.8rem">No variants</span>
            <?php endif ?>
          </td>
          <td style="text-align:right;font-size:.82rem">
            <?php if ($p['total_cost_val'] > 0): ?>৳<?= number_format($p['total_cost_val'], 0) ?><?php else: ?>—<?php endif ?>
          </td>
          <td style="text-align:right;font-size:.82rem">
            <?php if ($p['total_price_val'] > 0): ?>৳<?= number_format($p['total_price_val'], 0) ?><?php else: ?>—<?php endif ?>
          </td>
          <td style="text-align:right;font-size:.82rem">
            <?php if ($p['total_regular_val'] > 0): ?>৳<?= number_format($p['total_regular_val'], 0) ?><?php else: ?>—<?php endif ?>
          </td>
          <td>
            <!-- Copy to form button -->
            <button type="button" class="btn btn-ghost btn-sm copy-product-btn"
              title="Copy to new product"
              onclick='copyProductToForm(<?= htmlspecialchars(json_encode([
                'name'        => $p['name'],
                'category_id' => $p['category_id'] ?? '',
                'category_name'=> $p['category_name'] ?? '',
                'brand_id'    => $p['brand_id'] ?? '',
                'brand_name'  => $p['brand_name'] ?? '',
                'description' => $p['description'] ?? '',
                'memo_number' => $p['memo_number'] ?? '',
                'memo_date'   => $p['memo_date'] ?? '',
                'notes'       => $p['notes'] ?? '',
                'variants'    => $allVariants[$p['id']] ?? [],
              ]), ENT_QUOTES) ?>)'>📋</button>
            <a href="index.php?page=barcodes&id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">🖨️</a>
            <?php if (canDelete()): ?>
            <a href="index.php?page=products&edit=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">✏️</a>
            <a href="index.php?page=products&action=delete&id=<?= $p['id'] ?>"
               class="btn btn-danger btn-sm"
               data-confirm="Delete this product and all variants?">🗑️</a>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Add / Edit Product Modal ──────────────────────────────── -->
<div class="modal-backdrop <?= $editing ? 'open' : '' ?>" id="productModal">
  <div class="modal" style="max-width:920px">
    <div class="modal-header">
      <span class="modal-title" id="modalTitle"><?= $editing ? 'Edit Product' : 'Add Product' ?></span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="productForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="save_product">
        <input type="hidden" name="product_id_db" id="productIdDb" value="<?= $editing['id'] ?? '' ?>">

        <!-- ── Row 1: Name ── -->
        <div class="form-group">
          <label class="form-label">Product Name *</label>
          <input type="text" name="name" id="productNameInput" class="form-control" required
                 value="<?= e($editing['name'] ?? '') ?>"
                 autocomplete="off" list="productNameList">
          <datalist id="productNameList">
            <?php foreach ($productNames as $pn): ?>
              <option value="<?= e($pn['name']) ?>">
            <?php endforeach ?>
          </datalist>
        </div>

        <!-- ── Row 2: Category / Brand / Serial ── -->
        <div class="form-row cols-3">
          <div class="form-group">
            <label class="form-label">Category</label>
            <input type="text" id="categoryInput" name="category_new" class="form-control"
                   list="categoryList" autocomplete="off"
                   value="<?= e($editing ? ($editing['category_id'] ? (dbFetch('SELECT name FROM categories WHERE id=?', [$editing['category_id']])['name'] ?? '') : '') : '') ?>"
                   placeholder="Type or select…">
            <input type="hidden" name="category_id" id="categoryIdHidden" value="<?= $editing['category_id'] ?? '' ?>">
            <datalist id="categoryList">
              <?php foreach ($categories as $c): ?>
                <option data-id="<?= $c['id'] ?>" value="<?= e($c['name']) ?>">
              <?php endforeach ?>
            </datalist>
          </div>
          <div class="form-group">
            <label class="form-label">Brand</label>
            <input type="text" id="brandInput" name="brand_new" class="form-control"
                   list="brandList" autocomplete="off"
                   value="<?= e($editing ? ($editing['brand_id'] ? (dbFetch('SELECT name FROM brands WHERE id=?', [$editing['brand_id']])['name'] ?? '') : '') : '') ?>"
                   placeholder="Type or select…">
            <input type="hidden" name="brand_id" id="brandIdHidden" value="<?= $editing['brand_id'] ?? '' ?>">
            <datalist id="brandList">
              <?php foreach ($brands as $b): ?>
                <option data-id="<?= $b['id'] ?>" value="<?= e($b['name']) ?>">
              <?php endforeach ?>
            </datalist>
          </div>
          <div class="form-group">
            <label class="form-label">Serial</label>
            <input type="text" name="description" class="form-control" value="<?= e($editing['description'] ?? '') ?>">
          </div>
        </div>

        <!-- ── Row 3: Memo Number / Memo Date / Notes ── -->
        <div class="form-row cols-3">
          <div class="form-group">
            <label class="form-label">Memo Number</label>
            <input type="text" name="memo_number" class="form-control" value="<?= e($editing['memo_number'] ?? '') ?>" placeholder="e.g. MEM-2025-001">
          </div>
          <div class="form-group">
            <label class="form-label">Memo Date</label>
            <input type="date" name="memo_date" class="form-control" value="<?= e($editing['memo_date'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Notes</label>
            <input type="text" name="notes" class="form-control" value="<?= e($editing['notes'] ?? '') ?>" placeholder="Optional notes…">
          </div>
        </div>

        <!-- ── Cost / Price Helper ── -->
        <div style="background:var(--surface2);border-radius:8px;padding:10px;margin-bottom:10px">
          <div style="font-size:.8rem;font-weight:600;margin-bottom:8px;color:var(--text-muted)">⚙️ Quick Fill Helper</div>

          <!-- Row 1: Total cost split -->
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">
            <label class="form-label" style="margin:0;white-space:nowrap;min-width:140px">Total cost to split:</label>
            <input type="number" id="totalCostInput" class="form-control" step="0.01" min="0" placeholder="e.g. 1000" style="max-width:130px" oninput="checkTotalCost()">
            <button type="button" class="btn btn-ghost btn-sm" onclick="applyTotalCost()">Split equally</button>
            <span id="totalCostWarning" style="display:none;color:#e67e22;font-size:.78rem">⚠️ Costs don't match total</span>
          </div>

          <!-- Row 2: Fill all prices from cost -->
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">
            <label class="form-label" style="margin:0;white-space:nowrap;min-width:140px">Profit on cost → Price:</label>
            <input type="number" id="profitAmount" class="form-control" step="0.01" min="0" placeholder="Amount" style="max-width:100px">
            <select id="profitType" class="form-control" style="max-width:90px">
              <option value="taka">৳ Taka</option>
              <option value="percent">% Percent</option>
            </select>
            <button type="button" class="btn btn-ghost btn-sm" onclick="applyPriceFill()">Fill all prices</button>
          </div>

          <!-- Row 3: Fill all regulars from price -->
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <label class="form-label" style="margin:0;white-space:nowrap;min-width:140px">Markup on price → Regular:</label>
            <input type="number" id="regularAmount" class="form-control" step="0.01" min="0" placeholder="Amount" style="max-width:100px">
            <select id="regularType" class="form-control" style="max-width:90px">
              <option value="taka">৳ Taka</option>
              <option value="percent">% Percent</option>
            </select>
            <button type="button" class="btn btn-ghost btn-sm" onclick="applyRegularFill()">Fill all regulars</button>
          </div>
        </div>

        <!-- ── Variants ── -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
          <div class="card-title" style="margin:0">Variants</div>
          <div style="display:flex;gap:6px">
            <button type="button" class="btn btn-ghost btn-sm" onclick="openBulkAdd()">⚡ Bulk Add</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addVariantRow()">+ Single</button>
          </div>
        </div>

        <!-- Bulk add panel -->
        <div id="bulkAddPanel" style="display:none;background:var(--surface2);border-radius:8px;padding:10px;margin-bottom:10px">
          <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:6px">Quick-add combinations from names × sizes × colors</div>
          <div class="form-row cols-3" style="margin-bottom:6px">
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Variant Names (comma-sep)</label>
              <input type="text" id="bulkNames" class="form-control" placeholder="Type A, Type B">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Sizes (comma-sep)</label>
              <input type="text" id="bulkSizes" class="form-control" placeholder="S, M, L, XL">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Colors (comma-sep)</label>
              <input type="text" id="bulkColors" class="form-control" placeholder="Red, Blue">
            </div>
          </div>
          <div class="form-row cols-2" style="margin-bottom:8px">
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Default Cost</label>
              <input type="number" id="bulkCost" class="form-control" step="0.01" value="0">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Profit (৳ or %)</label>
              <div style="display:flex;gap:4px">
                <input type="number" id="bulkProfit" class="form-control" step="0.01" value="0">
                <select id="bulkProfitType" class="form-control" style="max-width:70px">
                  <option value="taka">৳</option>
                  <option value="percent">%</option>
                </select>
              </div>
            </div>
          </div>
          <div class="form-row cols-2" style="margin-bottom:8px">
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Default Regular</label>
              <input type="number" id="bulkRegular" class="form-control" step="0.01" value="0">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Default Qty</label>
              <input type="number" id="bulkQty" class="form-control" value="0" min="0">
            </div>
          </div>
          <button type="button" class="btn btn-primary btn-sm" onclick="generateBulk()">Generate Variants</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('bulkAddPanel').style.display='none'">Cancel</button>
        </div>

        <!-- Variants header row -->
        <!--
          Columns: VARIANT NAME | SIZE | COLOR | COST | PROFIT(৳/%) | PRICE | MARKUP(৳/%) | REGULAR | QTY | ✕
          The profit type and markup type are remembered per-row.
          Mismatch: if price != cost+profit → price red; if regular != price+markup → regular red
        -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 80px 110px 80px 110px 80px 60px 34px;gap:4px;padding:0 4px;margin-bottom:2px">
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600">VARIANT NAME</div>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600">SIZE</div>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600">COLOR</div>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600">COST</div>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600">PROFIT (৳/%)</div>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600">PRICE</div>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600">MARKUP (৳/%)</div>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600">REGULAR</div>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600">QTY</div>
          <div></div>
        </div>

        <div id="variantsContainer">
          <?php
          $initVars = $editVars ?: [['id'=>'','variant_name'=>'','size'=>'','color'=>'','cost'=>'','regular'=>'','price'=>'','quantity'=>'']];
          foreach ($initVars as $v): ?>
          <?= variantRowHtmlPHP($v) ?>
          <?php endforeach ?>
        </div>

        <datalist id="sizeList">
          <option>XS</option><option>S</option><option>M</option><option>L</option>
          <option>XL</option><option>XXL</option><option>Free Size</option>
          <option>36</option><option>37</option><option>38</option><option>39</option>
          <option>40</option><option>41</option><option>42</option>
        </datalist>
        <datalist id="colorList">
          <option>Black</option><option>White</option><option>Red</option><option>Blue</option>
          <option>Green</option><option>Yellow</option><option>Pink</option><option>Grey</option>
          <option>Brown</option><option>Navy</option><option>Beige</option>
        </datalist>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost modal-close">Cancel</button>
      <button type="submit" form="productForm" class="btn btn-primary">Save Product</button>
    </div>
  </div>
</div>

<?php
function variantRowHtmlPHP(array $v): string {
    $id      = $v['id'] ?? '';
    $vname   = htmlspecialchars($v['variant_name'] ?? '', ENT_QUOTES);
    $size    = htmlspecialchars($v['size'] ?? '', ENT_QUOTES);
    $color   = htmlspecialchars($v['color'] ?? '', ENT_QUOTES);
    $cost    = (float)($v['cost'] ?? 0);
    $regular = (float)($v['regular'] ?? 0);
    $price   = (float)($v['price'] ?? 0);
    $qty     = (int)($v['quantity'] ?? 0);
    // Compute profit (cost→price): default taka
    $profit  = round($price - $cost, 2);
    $profitPct = $cost > 0 ? round(($price - $cost) / $cost * 100, 2) : 0;
    // Compute markup (price→regular): default taka
    $markup  = round($regular - $price, 2);
    $markupPct = $price > 0 ? round(($regular - $price) / $price * 100, 2) : 0;
    return <<<HTML
<div class="variant-row" style="background:var(--surface2);border-radius:6px;padding:6px 8px;margin-bottom:4px;display:grid;grid-template-columns:1fr 1fr 1fr 80px 110px 80px 110px 80px 60px 34px;gap:4px;align-items:center">
  <input type="hidden" name="variant_id[]" value="$id">
  <input type="text"   name="variant_name[]"  class="form-control" value="$vname" placeholder="e.g. Standard" style="padding:4px 6px;font-size:.82rem">
  <input type="text"   name="variant_size[]"  class="form-control" value="$size"  list="sizeList"  style="padding:4px 6px;font-size:.82rem">
  <input type="text"   name="variant_color[]" class="form-control" value="$color" list="colorList" style="padding:4px 6px;font-size:.82rem">
  <input type="number" name="variant_cost[]"  class="form-control variant-cost"  step="0.01" min="0" value="$cost"
         oninput="onCostChange(this)" style="padding:4px 6px;font-size:.82rem">
  <div style="display:flex;gap:2px;align-items:center">
    <input type="number" class="form-control variant-profit" step="0.01" value="$profit"
           oninput="onProfitChange(this)" style="padding:4px 4px;font-size:.82rem;width:56px">
    <select class="form-control variant-profit-type" onchange="onProfitTypeChange(this)" style="padding:4px 2px;font-size:.78rem;width:46px">
      <option value="taka">৳</option>
      <option value="percent">%</option>
    </select>
  </div>
  <input type="number" name="variant_price[]" class="form-control variant-price" step="0.01" min="0" value="$price"
         oninput="onPriceChange(this)" style="padding:4px 6px;font-size:.82rem">
  <div style="display:flex;gap:2px;align-items:center">
    <input type="number" class="form-control variant-markup" step="0.01" value="$markup"
           oninput="onMarkupChange(this)" style="padding:4px 4px;font-size:.82rem;width:56px">
    <select class="form-control variant-markup-type" onchange="onMarkupTypeChange(this)" style="padding:4px 2px;font-size:.78rem;width:46px">
      <option value="taka">৳</option>
      <option value="percent">%</option>
    </select>
  </div>
  <input type="number" name="variant_regular[]" class="form-control variant-regular" step="0.01" min="0" value="$regular"
         oninput="onRegularChange(this)" style="padding:4px 6px;font-size:.82rem">
  <input type="number" name="variant_qty[]"   class="form-control" min="0" value="$qty" style="padding:4px 6px;font-size:.82rem">
  <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeVariant(this)" title="Remove" style="padding:4px 6px;font-size:.8rem">✕</button>
</div>
HTML;
}
?>

<script>
// ── Category / Brand: resolve id from datalist ────────────────
(function(){
  function wireCombo(inputId, datalistId, hiddenId) {
    const input  = document.getElementById(inputId);
    const hidden = document.getElementById(hiddenId);
    const dl     = document.getElementById(datalistId);
    if (!input) return;
    input.addEventListener('input', function() {
      const val = this.value.trim().toLowerCase();
      hidden.value = '';
      dl.querySelectorAll('option').forEach(opt => {
        if (opt.value.trim().toLowerCase() === val)
          hidden.value = opt.getAttribute('data-id') || '';
      });
    });
  }
  wireCombo('categoryInput', 'categoryList', 'categoryIdHidden');
  wireCombo('brandInput',    'brandList',    'brandIdHidden');
})();

// ══════════════════════════════════════════════════════════════
// Per-row pricing logic
// Fields: cost, profit (with type ৳/%), price, markup (with type ৳/%), regular
//
// Flow A (forward): cost → profit → price → markup → regular
// Flow B (backward): regular → markup → price → profit → cost (partial)
//
// Each field change updates the NEXT field using the CURRENT type selection.
// Mismatch detection: highlight red if computed ≠ entered (non-blocking).
// ══════════════════════════════════════════════════════════════

function getRow(el) { return el.closest('.variant-row'); }

function computePrice(cost, profit, ptype) {
  return ptype === 'percent' ? cost + cost * profit / 100 : cost + profit;
}
function computeRegular(price, markup, mtype) {
  return mtype === 'percent' ? price + price * markup / 100 : price + markup;
}
function computeProfitTaka(cost, price) { return price - cost; }
function computeProfitPct(cost, price) { return cost > 0 ? (price - cost) / cost * 100 : 0; }
function computeMarkupTaka(price, regular) { return regular - price; }
function computeMarkupPct(price, regular) { return price > 0 ? (regular - price) / price * 100 : 0; }

function checkPriceMismatch(row) {
  const cost   = parseFloat(row.querySelector('.variant-cost').value)   || 0;
  const profit = parseFloat(row.querySelector('.variant-profit').value) || 0;
  const ptype  = row.querySelector('.variant-profit-type').value;
  const price  = parseFloat(row.querySelector('.variant-price').value)  || 0;
  const expected = computePrice(cost, profit, ptype);
  const mismatch = Math.abs(price - expected) > 0.02;
  row.querySelector('.variant-price').classList.toggle('variant-mismatch', mismatch);
  row.querySelector('.variant-profit').classList.toggle('variant-mismatch', mismatch);
}

function checkRegularMismatch(row) {
  const price   = parseFloat(row.querySelector('.variant-price').value)   || 0;
  const markup  = parseFloat(row.querySelector('.variant-markup').value)  || 0;
  const mtype   = row.querySelector('.variant-markup-type').value;
  const regular = parseFloat(row.querySelector('.variant-regular').value) || 0;
  const expected = computeRegular(price, markup, mtype);
  const mismatch = Math.abs(regular - expected) > 0.02;
  row.querySelector('.variant-regular').classList.toggle('variant-mismatch', mismatch);
  row.querySelector('.variant-markup').classList.toggle('variant-mismatch', mismatch);
}

// Cost changed → recalc price, then regular
function onCostChange(el) {
  const row    = getRow(el);
  const cost   = parseFloat(el.value) || 0;
  const profit = parseFloat(row.querySelector('.variant-profit').value) || 0;
  const ptype  = row.querySelector('.variant-profit-type').value;
  const price  = computePrice(cost, profit, ptype);
  row.querySelector('.variant-price').value = price.toFixed(2);
  // cascade to regular
  cascadeToRegular(row, price);
  checkPriceMismatch(row);
  checkRegularMismatch(row);
  checkTotalCost();
}

// Profit changed → recalc price, cascade regular
function onProfitChange(el) {
  const row    = getRow(el);
  const cost   = parseFloat(row.querySelector('.variant-cost').value) || 0;
  const profit = parseFloat(el.value) || 0;
  const ptype  = row.querySelector('.variant-profit-type').value;
  const price  = computePrice(cost, profit, ptype);
  row.querySelector('.variant-price').value = price.toFixed(2);
  cascadeToRegular(row, price);
  checkPriceMismatch(row);
  checkRegularMismatch(row);
}

// Profit type changed → convert profit value to new type, keep price
function onProfitTypeChange(el) {
  const row   = getRow(el);
  const cost  = parseFloat(row.querySelector('.variant-cost').value)  || 0;
  const price = parseFloat(row.querySelector('.variant-price').value) || 0;
  const ptype = el.value;
  // Recalculate profit in new type from existing price
  const newProfit = ptype === 'percent' ? computeProfitPct(cost, price) : computeProfitTaka(cost, price);
  row.querySelector('.variant-profit').value = newProfit.toFixed(2);
  checkPriceMismatch(row);
}

// Price changed → back-calc profit, then cascade to regular
function onPriceChange(el) {
  const row   = getRow(el);
  const cost  = parseFloat(row.querySelector('.variant-cost').value) || 0;
  const price = parseFloat(el.value) || 0;
  const ptype = row.querySelector('.variant-profit-type').value;
  const newProfit = ptype === 'percent' ? computeProfitPct(cost, price) : computeProfitTaka(cost, price);
  row.querySelector('.variant-profit').value = newProfit.toFixed(2);
  // cascade to regular
  cascadeToRegular(row, price);
  checkPriceMismatch(row);
  checkRegularMismatch(row);
}

// Markup changed → recalc regular
function onMarkupChange(el) {
  const row    = getRow(el);
  const price  = parseFloat(row.querySelector('.variant-price').value)  || 0;
  const markup = parseFloat(el.value) || 0;
  const mtype  = row.querySelector('.variant-markup-type').value;
  const regular = computeRegular(price, markup, mtype);
  row.querySelector('.variant-regular').value = regular.toFixed(2);
  checkRegularMismatch(row);
}

// Markup type changed → convert markup value to new type, keep regular
function onMarkupTypeChange(el) {
  const row     = getRow(el);
  const price   = parseFloat(row.querySelector('.variant-price').value)   || 0;
  const regular = parseFloat(row.querySelector('.variant-regular').value) || 0;
  const mtype   = el.value;
  const newMarkup = mtype === 'percent' ? computeMarkupPct(price, regular) : computeMarkupTaka(price, regular);
  row.querySelector('.variant-markup').value = newMarkup.toFixed(2);
  checkRegularMismatch(row);
}

// Regular changed → back-calc markup
function onRegularChange(el) {
  const row     = getRow(el);
  const price   = parseFloat(row.querySelector('.variant-price').value) || 0;
  const regular = parseFloat(el.value) || 0;
  const mtype   = row.querySelector('.variant-markup-type').value;
  const newMarkup = mtype === 'percent' ? computeMarkupPct(price, regular) : computeMarkupTaka(price, regular);
  row.querySelector('.variant-markup').value = newMarkup.toFixed(2);
  checkRegularMismatch(row);
}

// Cascade from price → regular using current markup settings
function cascadeToRegular(row, price) {
  const markup = parseFloat(row.querySelector('.variant-markup').value) || 0;
  const mtype  = row.querySelector('.variant-markup-type').value;
  const regular = computeRegular(price, markup, mtype);
  row.querySelector('.variant-regular').value = regular.toFixed(2);
}

// ── Variant row HTML (JS version) ────────────────────────────
function variantRowHtml(vname, size, color, cost, profit, ptype, price, markup, mtype, regular, qty) {
  vname   = vname   || '';
  size    = size    || '';
  color   = color   || '';
  cost    = parseFloat(cost)    || 0;
  profit  = parseFloat(profit)  || 0;
  ptype   = ptype   || 'taka';
  price   = parseFloat(price)   || 0;
  markup  = parseFloat(markup)  || 0;
  mtype   = mtype   || 'taka';
  regular = parseFloat(regular) || 0;
  qty     = parseInt(qty)       || 0;
  return `<div class="variant-row" style="background:var(--surface2);border-radius:6px;padding:6px 8px;margin-bottom:4px;display:grid;grid-template-columns:1fr 1fr 1fr 80px 110px 80px 110px 80px 60px 34px;gap:4px;align-items:center">
    <input type="hidden" name="variant_id[]" value="">
    <input type="text"   name="variant_name[]"  class="form-control" value="${esc(vname)}" placeholder="e.g. Standard" style="padding:4px 6px;font-size:.82rem">
    <input type="text"   name="variant_size[]"  class="form-control" value="${esc(size)}"  list="sizeList"  style="padding:4px 6px;font-size:.82rem">
    <input type="text"   name="variant_color[]" class="form-control" value="${esc(color)}" list="colorList" style="padding:4px 6px;font-size:.82rem">
    <input type="number" name="variant_cost[]"  class="form-control variant-cost"  step="0.01" min="0" value="${cost.toFixed(2)}"
           oninput="onCostChange(this)" style="padding:4px 6px;font-size:.82rem">
    <div style="display:flex;gap:2px;align-items:center">
      <input type="number" class="form-control variant-profit" step="0.01" value="${profit.toFixed(2)}"
             oninput="onProfitChange(this)" style="padding:4px 4px;font-size:.82rem;width:56px">
      <select class="form-control variant-profit-type" onchange="onProfitTypeChange(this)" style="padding:4px 2px;font-size:.78rem;width:46px">
        <option value="taka" ${ptype==='taka'?'selected':''}>৳</option>
        <option value="percent" ${ptype==='percent'?'selected':''}>%</option>
      </select>
    </div>
    <input type="number" name="variant_price[]" class="form-control variant-price" step="0.01" min="0" value="${price.toFixed(2)}"
           oninput="onPriceChange(this)" style="padding:4px 6px;font-size:.82rem">
    <div style="display:flex;gap:2px;align-items:center">
      <input type="number" class="form-control variant-markup" step="0.01" value="${markup.toFixed(2)}"
             oninput="onMarkupChange(this)" style="padding:4px 4px;font-size:.82rem;width:56px">
      <select class="form-control variant-markup-type" onchange="onMarkupTypeChange(this)" style="padding:4px 2px;font-size:.78rem;width:46px">
        <option value="taka" ${mtype==='taka'?'selected':''}>৳</option>
        <option value="percent" ${mtype==='percent'?'selected':''}>%</option>
      </select>
    </div>
    <input type="number" name="variant_regular[]" class="form-control variant-regular" step="0.01" min="0" value="${regular.toFixed(2)}"
           oninput="onRegularChange(this)" style="padding:4px 6px;font-size:.82rem">
    <input type="number" name="variant_qty[]"   class="form-control" min="0" value="${qty}" style="padding:4px 6px;font-size:.82rem">
    <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeVariant(this)" title="Remove" style="padding:4px 6px;font-size:.8rem">✕</button>
  </div>`;
}

function addVariantRow() {
  document.getElementById('variantsContainer').insertAdjacentHTML('beforeend', variantRowHtml());
}

function removeVariant(btn) {
  if (document.querySelectorAll('.variant-row').length > 1) {
    btn.closest('.variant-row').remove();
    checkTotalCost();
  }
}

function openBulkAdd() {
  document.getElementById('bulkAddPanel').style.display = '';
}

function generateBulk() {
  const names  = document.getElementById('bulkNames').value.split(',').map(s=>s.trim()).filter(Boolean);
  const sizes  = document.getElementById('bulkSizes').value.split(',').map(s=>s.trim()).filter(Boolean);
  const colors = document.getElementById('bulkColors').value.split(',').map(s=>s.trim()).filter(Boolean);
  const cost   = parseFloat(document.getElementById('bulkCost').value)   || 0;
  const profit = parseFloat(document.getElementById('bulkProfit').value) || 0;
  const ptype  = document.getElementById('bulkProfitType').value;
  const regular= parseFloat(document.getElementById('bulkRegular').value)|| 0;
  const qty    = parseInt(document.getElementById('bulkQty').value)       || 0;

  const price  = computePrice(cost, profit, ptype);
  const markup = computeMarkupTaka(price, regular);

  if (!names.length && !sizes.length && !colors.length) { alert('Enter at least one field.'); return; }
  const nArr = names.length  ? names  : [''];
  const sArr = sizes.length  ? sizes  : [''];
  const cArr = colors.length ? colors : [''];
  const container = document.getElementById('variantsContainer');

  nArr.forEach(n => sArr.forEach(s => cArr.forEach(c => {
    container.insertAdjacentHTML('beforeend', variantRowHtml(n, s, c, cost, profit, ptype, price, markup, 'taka', regular, qty));
  })));

  // Remove blank placeholder rows
  document.querySelectorAll('.variant-row').forEach(row => {
    const vid   = row.querySelector('input[name="variant_id[]"]').value;
    const vname = row.querySelector('input[name="variant_name[]"]').value.trim();
    const vsize = row.querySelector('input[name="variant_size[]"]').value.trim();
    const color = row.querySelector('input[name="variant_color[]"]').value.trim();
    if (!vid && !vname && !vsize && !color) row.remove();
  });

  document.getElementById('bulkAddPanel').style.display = 'none';
  checkTotalCost();
}

// ── Total Cost split ─────────────────────────────────────────
function applyTotalCost() {
  const total = parseFloat(document.getElementById('totalCostInput').value);
  if (!total || total <= 0) return;
  const rows = document.querySelectorAll('.variant-row');
  if (!rows.length) return;
  const per = total / rows.length;
  rows.forEach(row => {
    row.querySelector('.variant-cost').value = per.toFixed(2);
    onCostChange(row.querySelector('.variant-cost'));
  });
  document.getElementById('totalCostWarning').style.display = 'none';
}

function checkTotalCost() {
  const totalInput = document.getElementById('totalCostInput');
  const warning    = document.getElementById('totalCostWarning');
  const total = parseFloat(totalInput.value);
  if (!total) { warning.style.display = 'none'; return; }
  let sum = 0;
  document.querySelectorAll('.variant-cost').forEach(inp => { sum += parseFloat(inp.value) || 0; });
  warning.style.display = Math.abs(sum - total) > 0.01 ? '' : 'none';
}

// ── Fill all prices from cost + profit ───────────────────────
function applyPriceFill() {
  const amount = parseFloat(document.getElementById('profitAmount').value);
  const type   = document.getElementById('profitType').value;
  if (isNaN(amount)) return;
  document.querySelectorAll('.variant-row').forEach(row => {
    const cost  = parseFloat(row.querySelector('.variant-cost').value) || 0;
    const price = computePrice(cost, amount, type);
    row.querySelector('.variant-profit').value = amount.toFixed(2);
    row.querySelector('.variant-profit-type').value = type;
    row.querySelector('.variant-price').value = price.toFixed(2);
    cascadeToRegular(row, price);
    checkPriceMismatch(row);
    checkRegularMismatch(row);
  });
}

// ── Fill all regulars from price + markup ────────────────────
function applyRegularFill() {
  const amount = parseFloat(document.getElementById('regularAmount').value);
  const type   = document.getElementById('regularType').value;
  if (isNaN(amount)) return;
  document.querySelectorAll('.variant-row').forEach(row => {
    const price   = parseFloat(row.querySelector('.variant-price').value) || 0;
    const regular = computeRegular(price, amount, type);
    row.querySelector('.variant-markup').value = amount.toFixed(2);
    row.querySelector('.variant-markup-type').value = type;
    row.querySelector('.variant-regular').value = regular.toFixed(2);
    checkRegularMismatch(row);
  });
}

// ── Copy product to form ──────────────────────────────────────
function copyProductToForm(data) {
  // Reset to "Add" mode (clear product_id_db so it creates new)
  document.getElementById('productIdDb').value = '';
  document.getElementById('modalTitle').textContent = 'Add Product (copied)';

  // Fill fields
  document.getElementById('productNameInput').value = data.name || '';
  document.getElementById('categoryInput').value    = data.category_name || '';
  document.getElementById('categoryIdHidden').value = data.category_id || '';
  document.getElementById('brandInput').value       = data.brand_name || '';
  document.getElementById('brandIdHidden').value    = data.brand_id || '';
  document.querySelector('[name="description"]').value  = data.description || '';
  document.querySelector('[name="memo_number"]').value  = data.memo_number || '';
  document.querySelector('[name="memo_date"]').value    = data.memo_date || '';
  document.querySelector('[name="notes"]').value        = data.notes || '';

  // Clear and refill variants
  const container = document.getElementById('variantsContainer');
  container.innerHTML = '';

  const variants = data.variants || [];
  if (variants.length === 0) {
    container.insertAdjacentHTML('beforeend', variantRowHtml());
  } else {
    variants.forEach(v => {
      const cost    = parseFloat(v.cost)     || 0;
      const price   = parseFloat(v.price)    || 0;
      const regular = parseFloat(v.regular)  || 0;
      const qty     = parseInt(v.quantity)   || 0;
      // Derive profit and markup in taka by default
      const profit  = price - cost;
      const markup  = regular - price;
      container.insertAdjacentHTML('beforeend',
        variantRowHtml(
          v.variant_name || '', v.size || '', v.color || '',
          cost, profit, 'taka',
          price, markup, 'taka',
          regular, qty
        )
      );
    });
  }

  openModal('productModal');
  checkTotalCost();
}

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

document.addEventListener('wheel', function(e) {
  if (document.activeElement.type === 'number') {
    document.activeElement.blur();
  }
}, { passive: false });
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>