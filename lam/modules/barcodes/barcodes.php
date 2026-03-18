<?php
// ============================================================
// modules/barcodes/barcodes.php — Smart Label Printer v5
// ============================================================

if (($_GET['action'] ?? '') === 'search_variants') {
    header('Content-Type: application/json');

    $q           = trim($_GET['q']       ?? '');
    $catFilter   = (int)($_GET['cat']    ?? 0);
    $brandFilter = (int)($_GET['brand']  ?? 0);
    $onlyStock   = (int)($_GET['stock']  ?? 0);
    $prodId      = (int)($_GET['pid']    ?? 0);
    $minPrice    = (float)($_GET['minp'] ?? 0);
    $maxPrice    = (float)($_GET['maxp'] ?? 0);
    $dateFrom    = trim($_GET['dfrom']   ?? '');
    $dateTo      = trim($_GET['dto']     ?? '');
    $datePreset  = trim($_GET['dpreset'] ?? '');
    $hasDiscount = (int)($_GET['disc']   ?? 0);
    $sortBy      = trim($_GET['sort']    ?? 'name');

    // Resolve date preset
    if ($datePreset) {
        $today = date('Y-m-d');
        switch ($datePreset) {
            case 'today':
                $dateFrom = $dateTo = $today; break;
            case 'yesterday':
                $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day')); break;
            case 'week':
                $dateFrom = date('Y-m-d', strtotime('monday this week'));
                $dateTo   = $today; break;
            case 'month':
                $dateFrom = date('Y-m-01');
                $dateTo   = $today; break;
            case 'last7':
                $dateFrom = date('Y-m-d', strtotime('-7 days'));
                $dateTo   = $today; break;
            case 'last30':
                $dateFrom = date('Y-m-d', strtotime('-30 days'));
                $dateTo   = $today; break;
        }
    }

    $where  = 'p.active = 1';
    $params = [];

    if ($prodId)     { $where .= ' AND p.id = ?';             $params[] = $prodId; }
    if ($q) {
        $s = "%$q%";
        $where .= ' AND (p.name LIKE ? OR p.description LIKE ? OR p.memo_number LIKE ?
                      OR c.name LIKE ? OR b.name LIKE ?
                      OR v.variant_name LIKE ? OR v.size LIKE ? OR v.color LIKE ? OR v.barcode LIKE ?)';
        $params = array_merge($params, [$s,$s,$s,$s,$s,$s,$s,$s,$s]);
    }
    if ($catFilter)   { $where .= ' AND p.category_id = ?';   $params[] = $catFilter; }
    if ($brandFilter) { $where .= ' AND p.brand_id = ?';       $params[] = $brandFilter; }
    if ($onlyStock)   { $where .= ' AND v.quantity > 0'; }
    if ($minPrice > 0){ $where .= ' AND v.price >= ?';         $params[] = $minPrice; }
    if ($maxPrice > 0){ $where .= ' AND v.price <= ?';         $params[] = $maxPrice; }
    if ($hasDiscount) { $where .= ' AND v.regular > v.price + 0.01'; }
    if ($dateFrom)    { $where .= ' AND DATE(p.created_at) >= ?'; $params[] = $dateFrom; }
    if ($dateTo)      { $where .= ' AND DATE(p.created_at) <= ?'; $params[] = $dateTo; }

    $orderMap = [
        'name'       => 'p.name, v.variant_name',
        'price_asc'  => 'v.price ASC, p.name',
        'price_desc' => 'v.price DESC, p.name',
        'newest'     => 'p.created_at DESC, p.name',
        'oldest'     => 'p.created_at ASC, p.name',
        'stock_desc' => 'v.quantity DESC, p.name',
        'stock_asc'  => 'v.quantity ASC, p.name',
    ];
    $order = $orderMap[$sortBy] ?? 'p.name, v.variant_name';

    $rows = dbFetchAll(
        "SELECT v.id AS vid, p.id AS pid, p.name, p.description, p.memo_number,
                v.variant_name, v.size, v.color,
                v.price, v.regular, v.quantity, v.barcode,
                c.name AS category_name, b.name AS brand_name,
                DATE(p.created_at) AS created_date, p.created_at
         FROM product_variants v
         JOIN products p ON p.id = v.product_id
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN brands b     ON b.id = p.brand_id
         WHERE $where
         ORDER BY $order
         LIMIT 2000",
        $params
    );
    echo json_encode(['items' => $rows, 'total' => count($rows)]); exit;
}

$productId   = (int)($_GET['id'] ?? 0);
$S           = getAllSettings();
$cur         = $S['currency_symbol']  ?? '৳';
$shopName    = $S['shop_name']        ?? '';
$shopAddress = $S['shop_address']     ?? '';
$shopPhone   = $S['shop_phone']       ?? '';
$shopEmail   = $S['shop_email']       ?? '';
$shopLogo    = $S['logo_url']         ?? '';

$categories  = dbFetchAll('SELECT id, name FROM categories ORDER BY name');
$brands      = dbFetchAll('SELECT id, name FROM brands ORDER BY name');

$pageTitle = 'Print Labels';
require_once BASE_PATH . '/includes/header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════════════════════
   ROOT & THEME
═══════════════════════════════════════════════════════════ */
:root {
  --lp-accent: #2563eb;
  --lp-accent2: #7c3aed;
  --lp-danger: #dc2626;
  --lp-success: #16a34a;
  --lp-warn: #d97706;
}

/* ═══════════════════════════════════════════════════════════
   LAYOUT
═══════════════════════════════════════════════════════════ */
.lp-wrap {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 10px;
  align-items: start;
}
@media(max-width:960px){ .lp-wrap { grid-template-columns:1fr; } }

/* ── LEFT PANEL ── */
.lp-panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  position: sticky;
  top: 8px;
  max-height: calc(100vh - 64px);
  display: flex;
  flex-direction: column;
  box-shadow: 0 2px 12px rgba(0,0,0,.07);
}
.lp-body { overflow-y: auto; flex: 1; }

/* Accordion */
.ah {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 7px 12px;
  cursor: pointer;
  user-select: none;
  font-size: 0.62rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  color: var(--text-muted);
  border-bottom: 1px solid var(--border);
  background: var(--surface2);
  transition: color .15s;
}
.ah:hover { color: var(--lp-accent); }
.ah .ar { font-size: 0.45rem; transition: .2s; opacity: .5; }
.ah.open .ar { transform: rotate(90deg); }
.ab { border-bottom: 1px solid var(--border); display: none; }
.ab.open { display: block; }

/* Form elements inside panels */
.ab-inner { padding: 10px 12px; }
.fr { display: flex; gap: 6px; margin-bottom: 6px; }
.fr:last-child { margin-bottom: 0; }
.ff { flex: 1; min-width: 0; }
.ff label {
  display: block;
  font-size: 0.54rem;
  color: var(--text-muted);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-bottom: 2px;
}
.ff input, .ff select {
  width: 100%;
  font-size: 0.74rem;
  padding: 4px 6px;
  border: 1px solid var(--border);
  border-radius: 5px;
  background: var(--surface2);
  color: var(--text);
  outline: none;
  box-sizing: border-box;
  font-family: inherit;
  transition: border-color .15s;
}
.ff input:focus, .ff select:focus { border-color: var(--lp-accent); }

/* Toggle rows */
.tg-sect {
  font-size: 0.54rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--lp-accent);
  margin: 9px 0 4px;
  padding-bottom: 2px;
  border-bottom: 1px solid var(--border);
}
.tg-sect:first-child { margin-top: 0; }
.tog {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 3px;
  font-size: 0.73rem;
}
.tog input[type=checkbox] { width: 13px; height: 13px; cursor: pointer; flex-shrink: 0; margin: 0; accent-color: var(--lp-accent); }
.tog label { cursor: pointer; margin: 0; color: var(--text); flex: 1; }
.fsz {
  width: 36px !important;
  font-size: 0.69rem !important;
  padding: 2px 3px !important;
  flex-shrink: 0;
  text-align: center;
}
.fzu { font-size: 0.54rem; color: var(--text-muted); white-space: nowrap; }

/* ── FILTER SECTION ── */
.fs-wrap {
  padding: 9px 11px;
  background: var(--surface2);
  border-bottom: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.fs-inp {
  width: 100%;
  font-size: 0.75rem;
  padding: 5px 8px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--surface);
  color: var(--text);
  outline: none;
  box-sizing: border-box;
  font-family: inherit;
  transition: border-color .15s, box-shadow .15s;
}
.fs-inp:focus { border-color: var(--lp-accent); box-shadow: 0 0 0 2px rgba(37,99,235,.12); }
.fsr { display: flex; gap: 5px; }
.fsr > * { flex: 1; min-width: 0; }

/* Date presets */
.date-presets {
  display: flex;
  flex-wrap: wrap;
  gap: 3px;
}
.dp-btn {
  font-size: 0.6rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 4px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-muted);
  cursor: pointer;
  transition: all .15s;
  white-space: nowrap;
}
.dp-btn:hover, .dp-btn.active { background: var(--lp-accent); color: #fff; border-color: var(--lp-accent); }

/* Filter chips row */
.filter-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 3px;
  min-height: 0;
}
.f-chip {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 0.58rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 20px;
  background: rgba(37,99,235,.1);
  color: var(--lp-accent);
  border: 1px solid rgba(37,99,235,.2);
}
.f-chip button {
  background: none;
  border: none;
  cursor: pointer;
  color: inherit;
  padding: 0;
  font-size: 0.65rem;
  line-height: 1;
  opacity: .6;
}
.f-chip button:hover { opacity: 1; }

/* ── ITEM LIST ── */
.il { overflow-y: auto; max-height: 280px; }
.il-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 10px;
  font-size: 0.58rem;
  color: var(--text-muted);
  background: var(--surface2);
  border-bottom: 1px solid var(--border);
  gap: 6px;
}
.il-sort {
  font-size: 0.62rem;
  padding: 2px 4px;
  border: 1px solid var(--border);
  border-radius: 4px;
  background: var(--surface);
  color: var(--text);
  outline: none;
}
.ir {
  display: flex;
  align-items: stretch;
  border-bottom: 1px solid var(--border);
  font-size: 0.71rem;
  transition: background .1s;
}
.ir:hover { background: var(--surface2); }
.ir.js-hidden { display: none !important; }
.ir.is-new .in::after {
  content: 'NEW';
  font-size: 0.44rem;
  font-weight: 800;
  background: var(--lp-accent);
  color: #fff;
  padding: 0 3px;
  border-radius: 3px;
  margin-left: 4px;
  vertical-align: middle;
}
.ick {
  display: flex;
  align-items: center;
  padding: 5px 7px;
  border-right: 1px solid var(--border);
  flex-shrink: 0;
}
.ick input { width: 13px; height: 13px; cursor: pointer; margin: 0; accent-color: var(--lp-accent); }
.ii { flex: 1; min-width: 0; padding: 4px 8px; cursor: pointer; }
.in {
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--text);
}
.is {
  font-size: 0.61rem;
  color: var(--text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ip { font-size: 0.63rem; margin-top: 1px; }
.ip-price { color: var(--lp-success); font-weight: 700; }
.ip-reg { color: var(--text-muted); text-decoration: line-through; margin-left: 3px; }
.ip-disc { color: var(--lp-danger); font-weight: 700; margin-left: 3px; }
.ip-stk { color: var(--text-muted); font-weight: 400; margin-left: 3px; font-size: 0.58rem; }
.ip-date { color: var(--text-muted); font-size: 0.56rem; margin-left: 3px; opacity: .7; }

.iqw {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3px 5px;
  border-left: 1px solid var(--border);
  gap: 2px;
  flex-shrink: 0;
}
.iqs {
  width: 56px;
  font-size: 0.59rem;
  padding: 2px 3px;
  border: 1px solid var(--border);
  border-radius: 4px;
  background: var(--surface2);
  color: var(--text);
  outline: none;
}
.iqi {
  width: 56px;
  text-align: center;
  font-size: 0.7rem;
  padding: 2px 3px;
  border: 1px solid var(--border);
  border-radius: 4px;
  background: var(--surface2);
  color: var(--text);
  outline: none;
  font-family: 'JetBrains Mono', monospace;
}
.iqi:focus, .iqs:focus { border-color: var(--lp-accent); }
.iqi.readonly { background: var(--surface); opacity: .65; }

.ie {
  padding: 28px 16px;
  text-align: center;
  color: var(--text-muted);
  font-size: 0.78rem;
}

/* Count bar */
.item-cnt-bar {
  padding: 4px 10px;
  font-size: 0.59rem;
  color: var(--text-muted);
  background: var(--surface2);
  border-top: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

/* ── ACTION BAR ── */
.lp-bar {
  padding: 7px 10px;
  border-top: 1px solid var(--border);
  background: var(--surface2);
  display: flex;
  gap: 5px;
  flex-shrink: 0;
}
.lp-bar button { flex: 1; font-size: 0.66rem; padding: 5px 3px; }

/* ── RIGHT PANEL ── */
.lp-right {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,.07);
}
.lp-rh {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 9px 14px;
  border-bottom: 1px solid var(--border);
  background: var(--surface2);
  gap: 10px;
}
.lp-rh h2 { font-size: 0.84rem; font-weight: 800; margin: 0; white-space: nowrap; }

/* Preview stats */
.pv-stats {
  display: flex;
  gap: 12px;
  font-size: 0.62rem;
  color: var(--text-muted);
  flex: 1;
  padding: 0 6px;
  flex-wrap: wrap;
  align-items: center;
}
.pv-stat { display: flex; flex-direction: column; align-items: center; }
.pv-stat strong { font-size: 0.82rem; color: var(--text); font-family: 'JetBrains Mono', monospace; }

/* ── PREVIEW AREA ── */
.lp-pv {
  background: #6b7280;
  background-image:
    radial-gradient(circle at 20% 50%, rgba(0,0,0,.06) 0, transparent 60%),
    repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0,0,0,.02) 10px, rgba(0,0,0,.02) 11px);
  padding: 20px;
  overflow: auto;
  max-height: calc(100vh - 110px);
  min-height: 340px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
}

/* ── LOADING SPINNER ── */
.lp-spinner {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
  color: #eee;
  gap: 8px;
  font-size: 0.8rem;
}
.spin {
  width: 18px; height: 18px;
  border: 2px solid rgba(255,255,255,.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── ZOOM CONTROLS ── */
.zoom-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.65rem;
  color: var(--text-muted);
}
.zoom-bar button {
  width: 22px; height: 22px;
  border: 1px solid var(--border);
  border-radius: 4px;
  background: var(--surface);
  cursor: pointer;
  font-size: 0.9rem;
  display: flex; align-items: center; justify-content: center;
  padding: 0;
  transition: all .15s;
}
.zoom-bar button:hover { background: var(--lp-accent); color: #fff; border-color: var(--lp-accent); }
#zoomVal { font-family: 'JetBrains Mono', monospace; font-size: 0.68rem; min-width: 34px; text-align: center; }

/* ── PRINT AREA ── */
#printArea { display: none; }

@media print {
  body, html { background: #fff !important; margin: 0 !important; padding: 0 !important; }
  .no-print, .app-header, .app-footer, .side-nav, nav, .lp-wrap { display: none !important; }
  .app-main { margin: 0 !important; padding: 0 !important; }
  #printArea { display: block !important; }
  .lp-page { box-shadow: none !important; }
  .lp-lbl { outline: none !important; }
}
</style>

<!-- ── PAGE HEADER ── -->
<div class="d-flex justify-between align-center mb-2 flex-wrap gap-2 no-print">
  <h1 style="margin:0;font-size:1.25rem;">🏷️ Label Printer</h1>
  <div style="display:flex;gap:6px;align-items:center;">
    <span id="hdrInfo" style="font-size:0.7rem;color:var(--text-muted);"></span>
    <button class="btn btn-primary" onclick="window.print()">🖨️ Print</button>
  </div>
</div>

<div class="lp-wrap no-print">

  <!-- ═══════════════════════════════════════
       LEFT PANEL
  ═══════════════════════════════════════ -->
  <div class="lp-panel">
    <div class="lp-body">

      <!-- ── Paper & Layout ── -->
      <div class="ah open" onclick="acc(this)">📄 Paper &amp; Layout <span class="ar">▶</span></div>
      <div class="ab open">
        <div class="ab-inner">
          <div class="fr">
            <div class="ff">
              <label>Paper Size</label>
              <select id="paperSize" onchange="onPC();rl()">
                <option value="a4">A4 (210×297mm)</option>
                <option value="a5">A5 (148×210mm)</option>
                <option value="letter">Letter (216×279mm)</option>
                <option value="custom">Custom / Roll</option>
              </select>
            </div>
            <div class="ff">
              <label>Orientation</label>
              <select id="orientation" onchange="rl()">
                <option value="portrait">Portrait</option>
                <option value="landscape">Landscape</option>
              </select>
            </div>
          </div>
          <div class="fr" id="cpRow" style="display:none;">
            <div class="ff"><label>Width mm</label><input type="number" id="paperW" value="80" min="10" oninput="rl()"></div>
            <div class="ff"><label>Height mm</label><input type="number" id="paperH" value="200" min="10" oninput="rl()"></div>
          </div>
          <div class="fr">
            <div class="ff">
              <label>Columns</label>
              <select id="cols" onchange="rl()">
                <option>1</option><option>2</option><option selected>3</option>
                <option>4</option><option>5</option><option>6</option>
              </select>
            </div>
            <div class="ff"><label>Col Gap mm</label><input type="number" id="colGap" value="2" min="0" step="0.5" oninput="rl()"></div>
            <div class="ff"><label>Row Gap mm</label><input type="number" id="rowGap" value="2" min="0" step="0.5" oninput="rl()"></div>
          </div>
          <div class="fr">
            <div class="ff"><label>Margin Top</label><input type="number" id="mT" value="5" min="0" step="0.5" oninput="rl()"></div>
            <div class="ff"><label>Right</label><input type="number" id="mR" value="5" min="0" step="0.5" oninput="rl()"></div>
            <div class="ff"><label>Bottom</label><input type="number" id="mB" value="5" min="0" step="0.5" oninput="rl()"></div>
            <div class="ff"><label>Left</label><input type="number" id="mL" value="5" min="0" step="0.5" oninput="rl()"></div>
          </div>
          <div class="fr">
            <div class="ff"><label>Label H-Pad mm</label><input type="number" id="padH" value="2" min="0" step="0.5" oninput="rl()"></div>
            <div class="ff"><label>Label V-Pad mm</label><input type="number" id="padV" value="2" min="0" step="0.5" oninput="rl()"></div>
            <div class="ff"><label>Border px (0=off)</label><input type="number" id="lblBorder" value="0" min="0" max="5" step="1" oninput="rl()"></div>
          </div>
          <div class="fr">
            <div class="ff">
              <label>Label Style</label>
              <select id="lblStyle" onchange="rl()">
                <option value="plain">Plain white</option>
                <option value="rounded">Rounded corners</option>
                <option value="shadow">Shadow</option>
                <option value="border">Border box</option>
              </select>
            </div>
            <div class="ff">
              <label>Text Align</label>
              <select id="txtAlign" onchange="rl()">
                <option value="center">Center</option>
                <option value="left">Left</option>
                <option value="right">Right</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Show Elements ── -->
      <div class="ah open" onclick="acc(this)">🔤 Label Content <span class="ar">▶</span></div>
      <div class="ab open">
        <div class="ab-inner">
          <div style="font-size:0.57rem;color:var(--text-muted);margin-bottom:7px;background:var(--surface2);padding:4px 6px;border-radius:4px;">All font sizes auto-fit to label width. Toggle elements on/off below.</div>

          <?php if($shopLogo||$shopName||$shopPhone||$shopEmail||$shopAddress): ?>
          <div class="tg-sect">Shop Info</div>
          <?php if($shopLogo):    ?><div class="tog"><input type="checkbox" id="shLogo"  onchange="rl()"><label for="shLogo">Logo</label></div><?php endif ?>
          <?php if($shopName):    ?><div class="tog"><input type="checkbox" id="shShop"  checked onchange="rl()"><label for="shShop">Shop Name</label></div><?php endif ?>
          <?php if($shopPhone):   ?><div class="tog"><input type="checkbox" id="shPhone" onchange="rl()"><label for="shPhone">Phone</label></div><?php endif ?>
          <?php if($shopEmail):   ?><div class="tog"><input type="checkbox" id="shEmail" onchange="rl()"><label for="shEmail">Email</label></div><?php endif ?>
          <?php if($shopAddress): ?><div class="tog"><input type="checkbox" id="shAddr"  onchange="rl()"><label for="shAddr">Address</label></div><?php endif ?>
          <?php endif ?>

          <div class="tg-sect">Product</div>
          <div class="tog"><input type="checkbox" id="shName"    checked onchange="rl()"><label for="shName">Product Name</label></div>
          <div class="tog"><input type="checkbox" id="shVariant" checked onchange="rl()"><label for="shVariant">Variant · Size · Color</label></div>
          <div class="tog"><input type="checkbox" id="shMeta"    onchange="rl()"><label for="shMeta">Category · Brand · Memo</label></div>
          <div class="tog"><input type="checkbox" id="shDesc"    onchange="rl()"><label for="shDesc">Description</label></div>

          <div class="tg-sect">Pricing</div>
          <div class="tog"><input type="checkbox" id="shPrice"   checked onchange="rl()"><label for="shPrice">Sale Price</label></div>
          <div class="tog"><input type="checkbox" id="shReg"     checked onchange="rl()"><label for="shReg">Regular Price (strikethrough)</label></div>
          <div class="tog"><input type="checkbox" id="shDisc"    checked onchange="rl()"><label for="shDisc">Discount % badge</label></div>

          <div class="tg-sect">Barcode</div>
          <div class="tog"><input type="checkbox" id="shBc"      checked onchange="rl()"><label for="shBc">Barcode graphic</label></div>
          <div class="tog"><input type="checkbox" id="shBcTxt"   checked onchange="rl()"><label for="shBcTxt">Barcode number</label></div>

          <div class="tg-sect">Dividers</div>
          <div class="tog"><input type="checkbox" id="shTopDiv"  onchange="rl()"><label for="shTopDiv">Top divider line</label></div>
          <div class="tog"><input type="checkbox" id="shBotDiv"  onchange="rl()"><label for="shBotDiv">Bottom divider line</label></div>
        </div>
      </div>

      <!-- ── Search & Filter ── -->
      <div class="ah open" onclick="acc(this)">🔍 Search &amp; Filter <span id="selCnt" style="font-weight:500;font-size:0.64rem;color:var(--lp-accent);margin-left:4px;"></span><span class="ar">▶</span></div>
      <div class="ab open" style="padding:0;">

        <div class="fs-wrap">
          <!-- Main search -->
          <input type="text" class="fs-inp" id="fQ" placeholder="🔍 Name, variant, size, color, barcode, memo…" oninput="debS()">

          <!-- Row: Category + Brand -->
          <div class="fsr">
            <select class="fs-inp" id="fCat" onchange="doSearch()">
              <option value="">All Categories</option>
              <?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach ?>
            </select>
            <select class="fs-inp" id="fBrand" onchange="doSearch()">
              <option value="">All Brands</option>
              <?php foreach($brands as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option><?php endforeach ?>
            </select>
          </div>

          <!-- Row: Stock + Discount -->
          <div class="fsr">
            <select class="fs-inp" id="fStock" onchange="doSearch()">
              <option value="0">All items</option>
              <option value="1">In stock only</option>
              <option value="-1">Out of stock</option>
            </select>
            <select class="fs-inp" id="fDisc" onchange="doSearch()">
              <option value="0">All prices</option>
              <option value="1">Discounted only</option>
            </select>
          </div>

          <!-- Row: Price range -->
          <div class="fsr">
            <input type="number" class="fs-inp" id="fMinP" placeholder="Min price" oninput="debS()">
            <input type="number" class="fs-inp" id="fMaxP" placeholder="Max price" oninput="debS()">
          </div>

          <!-- Date filter section -->
          <div style="font-size:0.56rem;font-weight:800;text-transform:uppercase;letter-spacing:0.4px;color:var(--lp-accent);padding:2px 0;">📅 Date Added</div>
          <div class="date-presets" id="datePresets">
            <button class="dp-btn" onclick="setDatePreset('today')">Today</button>
            <button class="dp-btn" onclick="setDatePreset('yesterday')">Yesterday</button>
            <button class="dp-btn" onclick="setDatePreset('last7')">Last 7d</button>
            <button class="dp-btn" onclick="setDatePreset('week')">This week</button>
            <button class="dp-btn" onclick="setDatePreset('last30')">Last 30d</button>
            <button class="dp-btn" onclick="setDatePreset('month')">This month</button>
            <button class="dp-btn" onclick="setDatePreset('')">All time</button>
          </div>
          <div class="fsr">
            <input type="date" class="fs-inp" id="fDateFrom" onchange="onDateChange()" title="From date">
            <input type="date" class="fs-inp" id="fDateTo"   onchange="onDateChange()" title="To date">
          </div>

          <!-- Sort -->
          <div class="fsr">
            <select class="fs-inp" id="fSort" onchange="doSearch()">
              <option value="name">Sort: Name A-Z</option>
              <option value="newest">Sort: Newest first</option>
              <option value="oldest">Sort: Oldest first</option>
              <option value="price_asc">Sort: Price low→high</option>
              <option value="price_desc">Sort: Price high→low</option>
              <option value="stock_desc">Sort: Stock high→low</option>
              <option value="stock_asc">Sort: Stock low→high</option>
            </select>
          </div>

          <!-- Active filter chips -->
          <div class="filter-chips" id="filterChips"></div>

          <!-- Quick JS filter on loaded results -->
          <div style="font-size:0.56rem;font-weight:800;text-transform:uppercase;letter-spacing:0.4px;color:var(--text-muted);padding:2px 0;">⚡ Quick filter (instant)</div>
          <input type="text" class="fs-inp" id="jsQ" placeholder="Filter by name/variant in results…" oninput="jsFilter()">
        </div>

        <!-- List header -->
        <div class="il-head">
          <span id="itemCnt" style="flex:1;">—</span>
          <label style="display:flex;align-items:center;gap:3px;cursor:pointer;">
            <input type="checkbox" id="showDates" onchange="renderList()" style="width:11px;height:11px;accent-color:var(--lp-accent);">
            <span style="font-size:0.58rem;">Dates</span>
          </label>
        </div>

        <div id="itemList" class="il"></div>
        <div class="item-cnt-bar">
          <span id="selLblCnt" style="color:var(--lp-accent);font-weight:700;"></span>
          <span id="totalQtyDisplay"></span>
        </div>
      </div>

    </div><!-- /lp-body -->

    <div class="lp-bar">
      <button class="btn btn-ghost btn-sm" onclick="selAll(true)" title="Select all visible">✓ All</button>
      <button class="btn btn-ghost btn-sm" onclick="selAll(false)" title="Deselect all">✗ None</button>
      <button class="btn btn-ghost btn-sm" onclick="selInvert()" title="Invert selection">⇄</button>
      <button class="btn btn-ghost btn-sm" onclick="useStock()" title="Set qty from stock">📦 Stock Qty</button>
      <button class="btn btn-primary btn-sm" onclick="window.print()">🖨 Print</button>
    </div>
  </div>

  <!-- ═══════════════════════════════════════
       RIGHT PREVIEW
  ═══════════════════════════════════════ -->
  <div class="lp-right">
    <div class="lp-rh">
      <h2>🖨 Live Preview</h2>
      <div class="pv-stats" id="pvStats"></div>
      <div class="zoom-bar">
        <button onclick="zoomAdj(-0.1)" title="Zoom out">−</button>
        <span id="zoomVal">100%</span>
        <button onclick="zoomAdj(+0.1)" title="Zoom in">+</button>
        <button onclick="zoomFit()" title="Fit to window" style="width:auto;padding:0 5px;font-size:0.58rem;">Fit</button>
      </div>
      <button class="btn btn-primary btn-sm" onclick="window.print()" style="margin-left:6px;">🖨 Print</button>
    </div>
    <div class="lp-pv" id="previewArea">
      <div style="color:#ddd;font-size:0.9rem;padding:80px 20px;text-align:center;opacity:.7;">
        Search and select items to preview labels
      </div>
    </div>
  </div>

</div><!-- /lp-wrap -->

<!-- Hidden print target -->
<div id="printArea"></div>

<script>
// ══════════════════════════════════════════════════════════
// CONSTANTS
// ══════════════════════════════════════════════════════════
const CURRENCY = <?= json_encode($cur) ?>;
const SH_NAME  = <?= json_encode($shopName) ?>;
const SH_ADDR  = <?= json_encode($shopAddress) ?>;
const SH_PHONE = <?= json_encode($shopPhone) ?>;
const SH_EMAIL = <?= json_encode($shopEmail) ?>;
const SH_LOGO  = <?= json_encode($shopLogo) ?>;
const INIT_PID = <?= $productId ?>;
const PAPER_MM = { a4:{w:210,h:297}, a5:{w:148,h:210}, letter:{w:216,h:279}, custom:{w:80,h:200} };
const MM       = 3.7795; // px per mm at 96dpi

// ══════════════════════════════════════════════════════════
// STATE
// ══════════════════════════════════════════════════════════
let allItems   = [];
let sel        = {};   // vid → { checked, qty, mode }
let sTimer, rTimer;
let zoomLevel  = 1;
let activeDatePreset = '';
let isLoading  = false;

// ══════════════════════════════════════════════════════════
// PERSIST (localStorage)
// ══════════════════════════════════════════════════════════
const PIDS = [
  'paperSize','orientation','paperW','paperH',
  'cols','colGap','rowGap','mT','mR','mB','mL','padH','padV','lblBorder','lblStyle','txtAlign',
  'shLogo','shShop','shPhone','shEmail','shAddr',
  'shName','shVariant','shMeta','shDesc',
  'shPrice','shReg','shDisc',
  'shBc','shBcTxt',
  'shTopDiv','shBotDiv'
];

function saveS() {
  const o = {};
  PIDS.forEach(k => {
    const el = document.getElementById(k);
    if (!el) return;
    o[k] = el.type === 'checkbox' ? el.checked : el.value;
  });
  localStorage.setItem('lpv8', JSON.stringify(o));
}
function loadS() {
  try {
    const o = JSON.parse(localStorage.getItem('lpv8') || '{}');
    PIDS.forEach(k => {
      const el = document.getElementById(k);
      if (!el || o[k] === undefined) return;
      if (el.type === 'checkbox') el.checked = o[k];
      else el.value = o[k];
    });
  } catch(e) {}
}

// ══════════════════════════════════════════════════════════
// ACCORDION
// ══════════════════════════════════════════════════════════
function acc(h) {
  h.classList.toggle('open');
  h.nextElementSibling.classList.toggle('open');
}

// ══════════════════════════════════════════════════════════
// PAPER HELPERS
// ══════════════════════════════════════════════════════════
function onPC() {
  const isCustom = document.getElementById('paperSize').value === 'custom';
  document.getElementById('cpRow').style.display = isCustom ? 'flex' : 'none';
}
function getPaper() {
  const ps  = document.getElementById('paperSize').value;
  const ori = document.getElementById('orientation').value;
  let w, h;
  if (ps === 'custom') {
    w = +document.getElementById('paperW').value || 80;
    h = +document.getElementById('paperH').value || 200;
  } else {
    const b = PAPER_MM[ps] || PAPER_MM.a4;
    w = b.w; h = b.h;
  }
  return ori === 'landscape' ? { w:h, h:w } : { w, h };
}

// ══════════════════════════════════════════════════════════
// MINI HELPERS
// ══════════════════════════════════════════════════════════
function cb(id)  { const el = document.getElementById(id); return el ? el.checked : false; }
function nv(id)  { return parseFloat(document.getElementById(id)?.value) || 0; }
function sv(id)  { return document.getElementById(id)?.value || ''; }
function clamp(v,a,b) { return Math.max(a, Math.min(b, v)); }
function esc(s)  { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function af(id, def) { const u = nv(id); return u > 0 ? u : def; }

function bcCode(barcode, vid) {
  const raw = (barcode && barcode) ? barcode : String(vid).padStart(8,'0');
  return raw;
  
}

// ══════════════════════════════════════════════════════════
// DATE PRESETS
// ══════════════════════════════════════════════════════════
function setDatePreset(preset) {
  activeDatePreset = preset;
  document.querySelectorAll('.dp-btn').forEach(b => b.classList.remove('active'));
  if (preset) {
    event.target.classList.add('active');
    // Clear manual date inputs if using a preset
    document.getElementById('fDateFrom').value = '';
    document.getElementById('fDateTo').value   = '';
  }
  doSearch();
  updateChips();
}

function onDateChange() {
  // If user types a date manually, clear preset
  activeDatePreset = '';
  document.querySelectorAll('.dp-btn').forEach(b => b.classList.remove('active'));
  doSearch();
  updateChips();
}

function updateChips() {
  const chips = [];
  const q = document.getElementById('fQ').value.trim();
  const cat = document.getElementById('fCat');
  const brand = document.getElementById('fBrand');
  const stock = document.getElementById('fStock');
  const disc = document.getElementById('fDisc');
  const minP = document.getElementById('fMinP').value;
  const maxP = document.getElementById('fMaxP').value;
  const dFrom = document.getElementById('fDateFrom').value;
  const dTo   = document.getElementById('fDateTo').value;

  if (q) chips.push({ label: `"${q}"`, clear: ()=>{ document.getElementById('fQ').value=''; debS(); } });
  if (cat.value) chips.push({ label: cat.options[cat.selectedIndex].text, clear: ()=>{ cat.value=''; doSearch(); } });
  if (brand.value) chips.push({ label: brand.options[brand.selectedIndex].text, clear: ()=>{ brand.value=''; doSearch(); } });
  if (stock.value !== '0') chips.push({ label: stock.options[stock.selectedIndex].text, clear: ()=>{ stock.value='0'; doSearch(); } });
  if (disc.value === '1') chips.push({ label: 'Discounted', clear: ()=>{ disc.value='0'; doSearch(); } });
  if (minP) chips.push({ label: `≥${CURRENCY}${minP}`, clear: ()=>{ document.getElementById('fMinP').value=''; debS(); } });
  if (maxP) chips.push({ label: `≤${CURRENCY}${maxP}`, clear: ()=>{ document.getElementById('fMaxP').value=''; debS(); } });
  if (activeDatePreset) chips.push({ label: '📅 '+activeDatePreset, clear: ()=>{ setDatePreset(''); } });
  if (!activeDatePreset && (dFrom||dTo)) {
    chips.push({ label: `📅 ${dFrom||'…'}→${dTo||'…'}`, clear: ()=>{ document.getElementById('fDateFrom').value=''; document.getElementById('fDateTo').value=''; doSearch(); } });
  }

  const container = document.getElementById('filterChips');
  if (!chips.length) { container.innerHTML=''; return; }
  container.innerHTML = chips.map((c,i) =>
    `<span class="f-chip">${esc(c.label)}<button onclick="filterChipClear(${i})" title="Remove">×</button></span>`
  ).join('');
  // Store clear callbacks
  window.__chipClears = chips.map(c => c.clear);
}
function filterChipClear(i) { window.__chipClears[i]?.(); updateChips(); }

// ══════════════════════════════════════════════════════════
// SEARCH — server-side
// ══════════════════════════════════════════════════════════
function debS() {
  clearTimeout(sTimer);
  sTimer = setTimeout(doSearch, 380);
}

function doSearch() {
  updateChips();
  if (isLoading) return;
  isLoading = true;
  document.getElementById('itemList').innerHTML = '<div class="ie"><div class="lp-spinner"><div class="spin"></div> Searching…</div></div>';

  const p = new URLSearchParams({
    action:   'search_variants',
    page:     'barcodes',
    q:        document.getElementById('fQ').value.trim(),
    cat:      document.getElementById('fCat').value,
    brand:    document.getElementById('fBrand').value,
    stock:    document.getElementById('fStock').value === '-1' ? '0' : document.getElementById('fStock').value,
    minp:     document.getElementById('fMinP').value.trim(),
    maxp:     document.getElementById('fMaxP').value.trim(),
    disc:     document.getElementById('fDisc').value,
    dfrom:    document.getElementById('fDateFrom').value,
    dto:      document.getElementById('fDateTo').value,
    dpreset:  activeDatePreset,
    sort:     document.getElementById('fSort').value,
    pid:      INIT_PID || 0,
  });

  fetch('?' + p)
    .then(r => r.json())
    .then(data => {
      isLoading = false;
      allItems = data.items || data; // handle both response shapes

      // For out-of-stock filter (client-side since server returns stock≥0)
      if (document.getElementById('fStock').value === '-1') {
        allItems = allItems.filter(i => parseInt(i.quantity) <= 0);
      }

      // Init selection state for new items
      const today = new Date().toISOString().split('T')[0];
      allItems.forEach(i => {
        if (!(i.vid in sel)) {
          const q = Math.max(1, parseInt(i.quantity) || 1);
          sel[i.vid] = { checked: true, qty: q, mode: 'stock', isNew: i.created_date === today };
        } else {
          sel[i.vid].isNew = i.created_date === today;
        }
      });
      renderList();
      jsFilter();
      rlD();
    })
    .catch(() => {
      isLoading = false;
      document.getElementById('itemList').innerHTML = '<div class="ie">⚠ Search failed. Please retry.</div>';
    });
}

// ══════════════════════════════════════════════════════════
// JS QUICK FILTER (no server round-trip)
// ══════════════════════════════════════════════════════════
function jsFilter() {
  const q = (document.getElementById('jsQ').value || '').toLowerCase().trim();
  document.querySelectorAll('.ir').forEach(row => {
    const searchable = ((row.dataset.name||'') + ' ' + (row.dataset.sub||'')).toLowerCase();
    row.classList.toggle('js-hidden', q ? !searchable.includes(q) : false);
  });
  updateFooterCounts();
}

// ══════════════════════════════════════════════════════════
// ITEM LIST RENDER
// ══════════════════════════════════════════════════════════
function renderList() {
  const list  = document.getElementById('itemList');
  const today = new Date().toISOString().split('T')[0];
  const showDates = document.getElementById('showDates')?.checked;
  const total = allItems.length;

  document.getElementById('itemCnt').textContent = `${total} variant${total !== 1 ? 's' : ''} found`;

  if (!allItems.length) {
    list.innerHTML = '<div class="ie">No items match your filters</div>';
    return;
  }

  list.innerHTML = allItems.map(item => {
    const st   = sel[item.vid] || { checked: false, qty: 1, mode: 'custom' };
    const chk  = st.checked ? 'checked' : '';
    const price = parseFloat(item.price);
    const reg   = parseFloat(item.regular);
    const hasSale = reg > price + 0.01;
    const pct  = hasSale ? Math.round((reg - price) / reg * 100) : 0;
    const varMeta = [item.variant_name, item.size, item.color].filter(Boolean).join(' · ');
    const subParts = [varMeta, item.category_name, item.brand_name].filter(Boolean);
    const isNew = item.created_date === today;
    const readonly = st.mode !== 'custom';

    return `<div class="ir${isNew ? ' is-new' : ''}"
      data-vid="${esc(item.vid)}"
      data-name="${esc(item.name)}"
      data-sub="${esc(subParts.join(' '))}">
      <div class="ick">
        <input type="checkbox" ${chk} onchange="togItem('${item.vid}',this.checked)">
      </div>
      <div class="ii" onclick="togRowClick(event,'${item.vid}')">
        <div class="in">${esc(item.name)}</div>
        ${varMeta ? `<div class="is">${esc(varMeta)}</div>` : ''}
        <div class="ip">
          <span class="ip-price">${CURRENCY}${price.toFixed(2)}</span>
          ${hasSale ? `<span class="ip-reg">${CURRENCY}${reg.toFixed(2)}</span><span class="ip-disc">−${pct}%</span>` : ''}
          <span class="ip-stk">stk:${item.quantity}</span>
          ${showDates && item.created_date ? `<span class="ip-date">${item.created_date}</span>` : ''}
        </div>
      </div>
      <div class="iqw">
        <select class="iqs" onchange="setMode('${item.vid}',this.value,'${item.quantity}')">
          <option value="stock"  ${st.mode==='stock'  ?'selected':''}>Stk(${item.quantity})</option>
          <option value="custom" ${st.mode==='custom' ?'selected':''}>Custom</option>
          <option value="1"      ${st.mode==='1'      ?'selected':''}>×1</option>
          <option value="2"      ${st.mode==='2'      ?'selected':''}>×2</option>
          <option value="5"      ${st.mode==='5'      ?'selected':''}>×5</option>
          <option value="10"     ${st.mode==='10'     ?'selected':''}>×10</option>
          <option value="25"     ${st.mode==='25'     ?'selected':''}>×25</option>
          <option value="50"     ${st.mode==='50'     ?'selected':''}>×50</option>
        </select>
        <input type="number" class="iqi${readonly ? ' readonly' : ''}" value="${st.qty}" min="1" max="9999"
          ${readonly ? 'readonly' : ''}
          onchange="setQty('${item.vid}',this.value)"
          oninput="setQty('${item.vid}',this.value)">
      </div>
    </div>`;
  }).join('');

  updateFooterCounts();
}

function togRowClick(e, vid) {
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
  const row = document.querySelector(`.ir[data-vid="${vid}"]`);
  const chk = row?.querySelector('.ick input');
  if (chk) togItem(vid, !chk.checked);
}

function updateFooterCounts() {
  const visible = [...document.querySelectorAll('.ir:not(.js-hidden)')];
  const checkedRows = visible.filter(r => r.querySelector('.ick input')?.checked);
  const checkedCount = checkedRows.length;
  let totalQty = 0;
  checkedRows.forEach(r => {
    const vid = r.dataset.vid;
    totalQty += sel[vid]?.qty || 1;
  });
  document.getElementById('selCnt').textContent = checkedCount ? `${checkedCount} sel` : '';
  document.getElementById('selLblCnt').textContent = checkedCount ? `${checkedCount} items selected` : '';
  document.getElementById('totalQtyDisplay').textContent = totalQty > 0 ? `${totalQty} labels total` : '';
}

function togItem(vid, on) {
  if (!sel[vid]) {
    const item = allItems.find(i => i.vid == vid);
    const q = Math.max(1, parseInt(item?.quantity) || 1);
    sel[vid] = { checked: false, qty: q, mode: 'stock' };
  }
  sel[vid].checked = on;
  const row = document.querySelector(`.ir[data-vid="${vid}"]`);
  if (row) { const chk = row.querySelector('.ick input'); if (chk) chk.checked = on; }
  updateFooterCounts();
  rlD();
}

function setMode(vid, mode, stockQty) {
  if (!sel[vid]) sel[vid] = { checked: true, qty: 1, mode: 'custom' };
  sel[vid].mode = mode;
  if (mode === 'stock')         sel[vid].qty = Math.max(1, parseInt(stockQty) || 1);
  else if (mode !== 'custom')   sel[vid].qty = parseInt(mode) || 1;
  renderList();
  rlD();
}

function setQty(vid, q) {
  q = Math.max(1, parseInt(q) || 1);
  if (!sel[vid]) sel[vid] = { checked: true, qty: 1, mode: 'custom' };
  sel[vid].qty  = q;
  sel[vid].mode = 'custom';
  updateFooterCounts();
  rlD();
}

function selAll(on) {
  const visible = [...document.querySelectorAll('.ir:not(.js-hidden)')];
  visible.forEach(row => {
    const vid = row.dataset.vid;
    if (!sel[vid]) { const item = allItems.find(i=>i.vid==vid); const q=Math.max(1,parseInt(item?.quantity)||1); sel[vid]={checked:false,qty:q,mode:'stock'}; }
    sel[vid].checked = on;
    const chk = row.querySelector('.ick input');
    if (chk) chk.checked = on;
  });
  updateFooterCounts();
  rlD();
}

function selInvert() {
  const visible = [...document.querySelectorAll('.ir:not(.js-hidden)')];
  visible.forEach(row => {
    const vid = row.dataset.vid;
    const chk = row.querySelector('.ick input');
    const cur = chk?.checked || false;
    if (!sel[vid]) { sel[vid] = { checked: !cur, qty: 1, mode: 'custom' }; }
    else sel[vid].checked = !cur;
    if (chk) chk.checked = sel[vid].checked;
  });
  updateFooterCounts();
  rlD();
}

function useStock() {
  allItems.forEach(i => {
    const q = Math.max(1, parseInt(i.quantity) || 1);
    if (!sel[i.vid]) sel[i.vid] = { checked: true, qty: q, mode: 'stock' };
    else { sel[i.vid].qty = q; sel[i.vid].mode = 'stock'; }
  });
  renderList();
  rlD();
}

// ══════════════════════════════════════════════════════════
// ZOOM
// ══════════════════════════════════════════════════════════
function zoomAdj(delta) {
  zoomLevel = clamp(zoomLevel + delta, 0.2, 2.0);
  applyZoom();
  rl();
}
function zoomFit() {
  const paper    = getPaper();
  const pvArea   = document.getElementById('previewArea');
  const available = pvArea.clientWidth - 40;
  zoomLevel = clamp(available / (paper.w * MM), 0.2, 2.0);
  applyZoom();
  rl();
}
function applyZoom() {
  document.getElementById('zoomVal').textContent = Math.round(zoomLevel * 100) + '%';
}

// ══════════════════════════════════════════════════════════
// RENDER — pixel-perfect label builder
// ══════════════════════════════════════════════════════════
function rlD() { clearTimeout(rTimer); rTimer = setTimeout(rl, 80); }

function rl() {
  saveS();

  const paper  = getPaper();
  const mT = nv('mT'), mR = nv('mR'), mB = nv('mB'), mL = nv('mL');
  const cols   = Math.max(1, parseInt(sv('cols')) || 3);
  const colGap = nv('colGap');
  const rowGap = nv('rowGap');
  const padH   = nv('padH');
  const padV   = nv('padV');
  const border = Math.round(nv('lblBorder'));
  const lblStyle = sv('lblStyle');
  const align  = sv('txtAlign') || 'center';

  const useW  = paper.w - mL - mR;
  const lblW  = (useW - colGap * (cols - 1)) / cols;  // mm
  const lblWpx = lblW * MM;
  const iWmm  = lblW - padH * 2;
  const iWpx  = iWmm * MM;

  // ── PURE AUTO-SCALE FONTS ────────────────────────────────────────────────
  // Everything is derived from iWpx (inner label width in pixels).
  // 1pt = 1.333px at 96dpi.  Formula: pt = px_fraction * iWpx / 1.333
  //
  // Fractions are tuned so each element looks balanced at all label sizes:
  //   • Text elements: fraction = desired_char_height / iWpx
  //     e.g. fsName at frac=0.13 means name text is ~13% of inner width tall
  //   • Price: larger fraction so it's the visual anchor of the label
  //   • Barcode: computed from character count so it fills the width exactly
  //
  // Nothing here is user-configurable — it all flows from paper+column settings.

  const PT2PX = 1.3333; // 1pt = 1.333px at 96dpi

  // pt-based fonts: fraction of iWpx → pt size, clamped to readable range
  const fsShop    = clamp(Math.round(iWpx * 0.075 / PT2PX),  4, 9);
  const fsPhone   = clamp(Math.round(iWpx * 0.070 / PT2PX),  4, 8);
  const fsEmail   = clamp(Math.round(iWpx * 0.070 / PT2PX),  4, 8);
  const fsAddr    = clamp(Math.round(iWpx * 0.065 / PT2PX),  3, 8);
  const fsName    = clamp(Math.round(iWpx * 0.130 / PT2PX),  6, 18);
  const fsVariant = clamp(Math.round(iWpx * 0.095 / PT2PX),  5, 13);
  const fsMeta    = clamp(Math.round(iWpx * 0.070 / PT2PX),  4,  9);
  const fsDesc    = clamp(Math.round(iWpx * 0.070 / PT2PX),  4,  9);
  const fsPrice   = clamp(Math.round(iWpx * 0.195 / PT2PX),  8, 26);
  const fsReg     = clamp(Math.round(iWpx * 0.090 / PT2PX),  5, 12);
  const fsDisc    = clamp(Math.round(iWpx * 0.080 / PT2PX),  4, 10);
  const fsBcTxt   = clamp(Math.round(iWpx * 0.065 / PT2PX),  4,  8);

  // Logo height in px — proportional to label width
  const logoHpx = clamp(Math.round(iWpx * 0.20), 12, 48);

  // Discount badge — circle sized relative to fsPrice
  const badgePx = clamp(Math.round(fsPrice * PT2PX * 2.8), 22, 64);
  const bF1 = Math.round(badgePx * 0.30); // pct digits
  const bF2 = Math.round(badgePx * 0.20); // OFF text
  const bF3 = Math.round(badgePx * 0.19); // saved amount

  // ── Label style CSS ──
  let lblExtra = '';
  if (border > 0) lblExtra += `border:${border}px solid #ccc;`;
  if (lblStyle === 'rounded') lblExtra += 'border-radius:6px;';
  if (lblStyle === 'shadow')  lblExtra += 'box-shadow:0 1px 4px rgba(0,0,0,0.15);';
  if (lblStyle === 'border')  lblExtra += 'border:1px solid #333;';

  // ── Build expanded label list ──
  const exp = [];
  allItems.forEach(i => {
    const st = sel[i.vid];
    if (st && st.checked) {
      const q = Math.max(1, st.qty || 1);
      for (let x = 0; x < q; x++) exp.push(i);
    }
  });

  const pvArea = document.getElementById('previewArea');
  const prArea = document.getElementById('printArea');

  if (!exp.length) {
    pvArea.innerHTML = '<div style="color:#ddd;font-size:0.9rem;padding:80px 20px;text-align:center;opacity:.7;">Select items to preview labels</div>';
    prArea.innerHTML = '';
    document.getElementById('pvStats').innerHTML = '';
    document.getElementById('hdrInfo').textContent = '';
    return;
  }

  // ── Build single label HTML ──
  function buildLabel(item) {
    const code  = bcCode(item.barcode, item.vid);
    const price = parseFloat(item.price);
    const reg   = parseFloat(item.regular);
    const hasSale = reg > price + 0.01;
    const pct   = hasSale ? Math.round((reg - price) / reg * 100) : 0;
    const saved = hasSale ? (reg - price).toFixed(2) : '0';
    const varMeta = [item.variant_name, item.size, item.color].filter(Boolean).join(' · ');
    const metaLine = [item.category_name, item.brand_name, item.memo_number ? '' + item.memo_number : ''].filter(Boolean).join(' · ');

    const W  = lblWpx;
    const pH = padH * MM;
    const pV = padV * MM;

    // row(): inline text that wraps naturally, never clips with ellipsis.
    // Each element is sized so it FITS — wrapping is the safety valve.
    const row = (txt, style) =>
      `<div style="width:100%;word-break:break-word;white-space:normal;text-align:${align};${style}">${txt}</div>`;
    // rowNowrap(): for items that must stay on one line (barcode number, variant codes)
    const rowNW = (txt, style) =>
      `<div style="width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:${align};${style}">${txt}</div>`;

    const divider = (top) =>
      `<div style="width:90%;height:0.5px;background:#ccc;margin:${top?'0 auto '+(pV*0.3)+'px':'(pV*0.3)px auto 0'};"></div>`;

    let inner = '';

    if (cb('shTopDiv'))
      inner += `<div style="width:90%;height:0.5px;background:#999;margin:0 auto ${Math.round(pV*0.3)}px;"></div>`;

    if (cb('shLogo') && SH_LOGO)
      inner += `<div style="text-align:${align};margin-bottom:1px;"><img src="${esc(SH_LOGO)}" style="max-height:${logoHpx}px;max-width:100%;object-fit:contain;display:block;${align==='center'?'margin:auto':align==='right'?'margin-left:auto':''}"></div>`;

    if (cb('shShop') && SH_NAME)
      inner += row(esc(SH_NAME), `font-size:${fsShop}pt;font-weight:800;color:#222;`);

    if (cb('shName'))
      inner += row(esc(item.name), `font-size:${fsName}pt;font-weight:900;color:#000;`);

    if (cb('shVariant') && varMeta)
      inner += row(esc(varMeta), `font-size:${fsVariant}pt;color:#333;`);

    if (cb('shMeta') && metaLine)
      inner += row(esc(metaLine), `font-size:${fsMeta}pt;color:#555;`);

    if (cb('shDesc') && item.description)
      inner += row(esc(item.description), `font-size:${fsDesc}pt;color:#666;white-space:normal;word-break:break-word;`);

    // ── Pricing block ──────────────────────────────────────────────────────
    // Layout: regular price (strikethrough) on top, sale price large below.
    // If discount badge shown: badge left, prices stacked right.
    const showAnyPrice = cb('shPrice') || (hasSale && (cb('shReg') || cb('shDisc')));
    if (showAnyPrice) {
      const showBadge = cb('shDisc') && hasSale;

      // Available width for the price text column
      const badgeGap     = showBadge ? Math.round(badgePx * 0.14) : 0;
      const pricePxAvail = showBadge
        ? Math.max(32, iWpx - badgePx - badgeGap - 4)
        : iWpx;

      // Fit sale price: bold sans ~0.62 width-to-height ratio, 1pt = 1.333px
      const priceStr     = CURRENCY + price.toFixed(2);
      const fsPriceFinal = Math.max(6, Math.min(fsPrice,
        Math.floor(pricePxAvail / (priceStr.length * 0.62 * 1.333))));

      // Fit regular/strikethrough price (lighter weight, ~0.55 ratio)
      const regStr       = CURRENCY + reg.toFixed(2);
      const fsRegFinal   = Math.max(4, Math.min(fsReg,
        Math.floor(pricePxAvail / (regStr.length * 0.55 * 1.333))));

      // Stack: regular (strikethrough) ABOVE sale price
      let priceStack = '';
      if (cb('shReg') && hasSale)
        priceStack += `<div style="font-size:${fsRegFinal-5}pt;color:#022;line-height:1.2;text-align:${showBadge?'left':align};">Original Price: </div><div style="font-size:${fsRegFinal+1}pt;color:#100;text-decoration:line-through;line-height:1.2;text-align:${showBadge?'left':align};">${CURRENCY}${reg.toFixed(2)}</div>`;
      if (cb('shPrice'))
        priceStack += `<div style="font-size:${fsPriceFinal}pt;font-weight:900;color:#000;
          line-height:1.1;text-align:${showBadge?'left':align};">${CURRENCY}${price.toFixed(2)}</div>`;

      const badge = showBadge ? `<div style="
          width:${badgePx}px;height:${badgePx}px;border-radius:50%;flex-shrink:0;
          background:#e53935;color:#fff;
          display:flex;flex-direction:column;align-items:center;justify-content:center;
          font-weight:900;line-height:1.05;box-shadow:0 1px 4px rgba(0,0,0,0.2);">
          <span style="font-size:${bF1}px;letter-spacing:-0.5px;">${pct}%</span>
          <span style="font-size:${bF2}px;letter-spacing:0.3px;">OFF</span>
          <span style="font-size:${bF3}px;opacity:.9;">${CURRENCY}${saved}</span>
        </div>` : '';

      inner += `<div style="display:flex;align-items:center;
        ${showBadge ? 'justify-content:flex-start;gap:'+badgeGap+'px;' : 'justify-content:'+align+';'}
        width:100%;padding:${Math.round(pV*0.2)}px 0;">
        ${badge}
        <div style="display:flex;flex-direction:column;
          ${showBadge?'align-items:flex-start;':'align-items:'+align+';'}
          flex:${showBadge?1:0};">
          ${priceStack}
        </div>
      </div>`;
    }

    if (cb('shBc') && code) {
      // ── SVG Code39 barcode — mathematically exact width = iWpx ─────────────
      // Code 39 encodes each character as 5 bars + 4 spaces = 9 modules.
      // Narrow module = 1 unit, Wide module = 3 units.
      // Bars at positions 0,2,4,6,8 (odd-indexed = spaces).
      // Inter-character gap = 1 narrow unit.
      // We compute total units, then unitPx = iWpx / totalUnits.
      // Each bar becomes an SVG <rect> at the exact pixel position — always fills iWpx.

      // Code39 patterns: 9 booleans per char, true=wide(3u) false=narrow(1u)
      // Index:  0=bar 1=sp 2=bar 3=sp 4=bar 5=sp 6=bar 7=sp 8=bar
      const C39 = {
        '0':[0,0,0,1,0,0,1,0,0],'1':[1,0,0,0,0,0,0,0,1],'2':[0,0,1,0,0,0,0,0,1],
        '3':[1,0,1,0,0,0,0,0,0],'4':[0,0,0,0,1,0,0,0,1],'5':[1,0,0,0,1,0,0,0,0],
        '6':[0,0,1,0,1,0,0,0,0],'7':[0,0,0,0,0,0,1,0,1],'8':[1,0,0,0,0,0,1,0,0],
        '9':[0,0,1,0,0,0,1,0,0],'A':[1,0,0,0,0,1,0,0,0],'B':[0,0,1,0,0,1,0,0,0],
        'C':[1,0,1,0,0,1,0,0,0],'D':[0,0,0,0,1,1,0,0,0],'E':[1,0,0,0,1,1,0,0,0],
        'F':[0,0,1,0,1,1,0,0,0],'G':[0,0,0,0,0,1,1,0,0],'H':[1,0,0,0,0,1,1,0,0],
        'I':[0,0,1,0,0,1,1,0,0],'J':[0,0,0,0,1,1,1,0,0],'K':[1,0,0,0,0,0,0,1,0],
        'L':[0,0,1,0,0,0,0,1,0],'M':[1,0,1,0,0,0,0,1,0],'N':[0,0,0,0,1,0,0,1,0],
        'O':[1,0,0,0,1,0,0,1,0],'P':[0,0,1,0,1,0,0,1,0],'Q':[0,0,0,0,0,0,1,1,0],
        'R':[1,0,0,0,0,0,1,1,0],'S':[0,0,1,0,0,0,1,1,0],'T':[0,0,0,0,1,0,1,1,0],
        'U':[1,1,0,0,0,0,0,0,0],'V':[0,1,1,0,0,0,0,0,0],'W':[1,1,1,0,0,0,0,0,0],
        'X':[0,1,0,0,1,0,0,0,0],'Y':[1,1,0,0,1,0,0,0,0],'Z':[0,1,0,0,0,0,1,0,0],
        '-':[0,1,0,0,1,0,1,0,0],'.':[1,1,0,0,0,0,1,0,0],' ':[0,1,0,0,0,1,0,0,0],
        '$':[0,1,0,1,0,1,0,0,0],'/':[0,1,0,1,0,0,0,1,0],'+':[0,1,0,0,0,1,0,1,0],
        '%':[0,0,0,1,0,1,0,1,0],'*':[0,1,0,0,1,0,0,1,0]
      };
      const N=1, W=3;
      const str = ('*'+code.toUpperCase()+'*').replace(/[^0-9A-Z\-\. \$\/\+\%\*]/g,'');

      // Build flat module list: {units, isBar}
      const mods = [];
      for (let ci=0; ci<str.length; ci++) {
        const pat = C39[str[ci]] || C39['0'];
        for (let mi=0; mi<9; mi++) mods.push({u: pat[mi]?W:N, bar: mi%2===0});
        if (ci < str.length-1) mods.push({u:N, bar:false}); // inter-char gap
      }
      const totalU = mods.reduce((s,m)=>s+m.u, 0);
      const uPx    = iWpx / totalU; // exact px per unit
      const bcH    = Math.max(20, Math.round(iWpx * 0.28)); // height proportional to width

      let rects='', x=0;
      mods.forEach(m => {
        const w = m.u * uPx;
        if (m.bar) rects += `<rect x="${x.toFixed(4)}" y="0" width="${w.toFixed(4)}" height="${bcH}" fill="#000"/>`;
        x += w;
      });

      inner += `<svg xmlns="http://www.w3.org/2000/svg"
        width="${iWpx}" height="${bcH}"
        viewBox="0 0 ${iWpx} ${bcH}"
        style="display:block;width:100%;height:${bcH}px;overflow:visible;">
        <rect width="${iWpx}" height="${bcH}" fill="#fff"/>
        ${rects}
      </svg>`;
    }

    if (cb('shBcTxt') && code)
      inner += rowNW(esc(code), `font-size:${fsBcTxt}pt;letter-spacing:1.5px;font-family:monospace;color:#555;`);

    const foot = [
      cb('shPhone') && SH_PHONE ? 'Contact: ' + esc(SH_PHONE) : '',
      cb('shEmail') && SH_EMAIL ? esc(SH_EMAIL) : '',
      cb('shAddr')  && SH_ADDR  ? esc(SH_ADDR)  : '',
    ].filter(Boolean).join(' · ');
    if (foot) inner += row(foot, `font-size:${fsPhone}pt;color:#888;`); 

    if (cb('shBotDiv'))
      inner += `<div style="width:90%;height:0.5px;background:#999;margin:${Math.round(pV*0.3)}px auto 0;"></div>`;
      inner += `<div style="width:100%;text-align:center;font-size:5pt;color:#bbb;margin-top:-2px;font-family:monospace;letter-spacing:0.2px;">Powered by sohojweb.com</div>`;

    return `<div style="
      width:${W}px;box-sizing:border-box;
      padding:${pV}px ${pH}px;
      background:#fff;overflow:hidden;
      break-inside:avoid;page-break-inside:avoid;
      ${lblExtra}
    ">${inner}</div>`;
  }

  // ════════════════════════════════════════════════════════
  // MEASURE-THEN-PAGINATE
  // ════════════════════════════════════════════════════════
  // Strategy:
  //   1. Render ONE label of every UNIQUE item off-screen at exact label width.
  //   2. Measure its real rendered height via getBoundingClientRect().
  //   3. Group label rows greedily: keep adding rows until they won't fit.
  //   4. Build final pages from those groups — zero estimation, zero waste.
  // This guarantees maximum packing with no cuts and no gaps.

  const ps  = sv('paperSize');
  const ori = sv('orientation');
  const pageRule = ps === 'custom'
    ? `@page{size:${paper.w}mm ${paper.h}mm;margin:0;}`
    : `@page{size:${ps} ${ori};margin:${mT}mm ${mR}mm ${mB}mm ${mL}mm;}`;

  const paperPxW = paper.w * MM;
  const paperPxH = paper.h * MM;
  const mTPx = mT * MM, mRPx = mR * MM, mBPx = mB * MM, mLPx = mL * MM;
  const usableHpx = paperPxH - mTPx - mBPx; // usable vertical px per page
  const rowGapPx  = rowGap * MM;
  const colGapPx  = colGap * MM;

  const CSS = `<style>
${pageRule}
@media print {
  * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .lp-page {
    page-break-after: always;
    break-after: page;
    box-shadow: none !important;
    overflow: hidden !important;
  }
  .lp-page:last-child { page-break-after:auto; break-after:auto; }
  .lp-row { page-break-inside:avoid; break-inside:avoid; }
  .lp-lbl { page-break-inside:avoid; break-inside:avoid; outline:none!important; }
}
@media screen {
  .lp-lbl { outline:1px dashed rgba(0,0,0,0.10); }
}
</style>`;

  // ── Step 1: measure real row height for each unique variant ──────────────
  // We render the tallest label in each row (items can differ in name length etc.)
  // Use a single off-screen container, reuse it.
  let probe = document.getElementById('__lp_probe__');
  if (!probe) {
    probe = document.createElement('div');
    probe.id = '__lp_probe__';
    probe.style.cssText = `position:fixed;top:-9999px;left:-9999px;
      visibility:hidden;pointer-events:none;z-index:-1;`;
    document.body.appendChild(probe);
  }

  // Build a lookup: vid → measured height in px (cached across re-renders)
  // Cache key includes label config so cache invalidates when settings change
  const cfgKey = `${cols}|${lblWpx.toFixed(1)}|${padH}|${padV}|${[
    'shLogo','shShop','shPhone','shEmail','shAddr',
    'shName','shVariant','shMeta','shDesc',
    'shPrice','shReg','shDisc','shBc','shBcTxt','shTopDiv','shBotDiv'
  ].map(id=>cb(id)?'1':'0').join('')}`;

  if (!rl._hCache || rl._hCache.key !== cfgKey) {
    rl._hCache = { key: cfgKey, h: {} };
  }
  const hCache = rl._hCache.h;

  // Measure items not yet in cache
  const toMeasure = exp.filter(item => !(item.vid in hCache));
  const seen = new Set();
  const unique = toMeasure.filter(item => {
    if (seen.has(item.vid)) return false;
    seen.add(item.vid);
    return true;
  });

  if (unique.length > 0) {
    // Render all unique labels into probe div at once, then measure
    probe.innerHTML = unique.map(item =>
      `<div data-vid="${item.vid}" style="width:${lblWpx}px;display:inline-block;vertical-align:top;">
        ${buildLabel(item)}
      </div>`
    ).join('');

    // Force layout
    probe.getBoundingClientRect();

    unique.forEach(item => {
      const el = probe.querySelector(`[data-vid="${item.vid}"]`);
      if (el) hCache[item.vid] = el.getBoundingClientRect().height;
    });
    probe.innerHTML = '';
  }

  // ── Step 2: group labels into rows, then rows into pages ─────────────────
  // All items in the same row share the same height (tallest cell in row).
  // We pack rows greedily: accumulate until next row won't fit.

  // Build list of rows: each row = array of items (length ≤ cols)
  const allRows = [];
  for (let i = 0; i < exp.length; i += cols) {
    allRows.push(exp.slice(i, i + cols));
  }

  // For each row, height = max measured height of its items
  function rowHeightPx(row) {
    return Math.max(...row.map(item => hCache[item.vid] || 40));
  }

  // Greedy pack rows into pages
  const pages = []; // array of arrays-of-rows
  let currentPage = [];
  let currentH = 0;

  allRows.forEach(row => {
    const rh = rowHeightPx(row);
    const needed = currentH === 0 ? rh : currentH + rowGapPx + rh;
    if (currentPage.length > 0 && needed > usableHpx) {
      // This row doesn't fit — start a new page
      pages.push(currentPage);
      currentPage = [row];
      currentH    = rh;
    } else {
      currentPage.push(row);
      currentH = needed;
    }
  });
  if (currentPage.length > 0) pages.push(currentPage);

  const pageCount = pages.length;
  const totalRows = allRows.length;

  // Update stats
  document.getElementById('pvStats').innerHTML = `
    <span class="pv-stat"><strong>${exp.length}</strong>labels</span>
    <span class="pv-stat"><strong>${pageCount}</strong>page${pageCount!==1?'s':''}</span>
    <span class="pv-stat"><strong>${cols}</strong>cols</span>
    <span class="pv-stat"><strong>${lblW.toFixed(1)}mm</strong>wide</span>`;
  document.getElementById('hdrInfo').textContent =
    `${exp.length} labels · ${pageCount} page${pageCount!==1?'s':''}`;

  // ── Step 3: build page HTML from packed row groups ────────────────────────
  function buildPageFromRows(pageRows) {
    const rowsHTML = pageRows.map(row => {
      const cells = row.map(i => `<div class="lp-lbl">${buildLabel(i)}</div>`).join('');
      return `<div class="lp-row" style="display:flex;align-items:flex-start;gap:${colGapPx}px;margin-bottom:${rowGapPx}px;">${cells}</div>`;
    }).join('');
    return `<div class="lp-page" style="
      width:${paperPxW}px;height:${paperPxH}px;
      box-sizing:border-box;background:#fff;
      padding:${mTPx}px ${mRPx}px ${mBPx}px ${mLPx}px;
      overflow:hidden;position:relative;">
      ${rowsHTML}
    </div>`;
  }

  // Preview: scale to fit container width
  const pvAreaW = pvArea.clientWidth - 40;
  const scale   = Math.min(zoomLevel, pvAreaW / paperPxW);
  const scaledH = paperPxH * scale;

  let pvHTML = CSS;
  let prHTML = CSS;

  pages.forEach(pageRows => {
    const pageHTML = buildPageFromRows(pageRows);
    pvHTML += `<div style="transform:scale(${scale.toFixed(4)});transform-origin:top center;
      width:${paperPxW}px;height:${paperPxH}px;
      box-shadow:0 8px 32px rgba(0,0,0,0.4),0 2px 8px rgba(0,0,0,0.3);
      flex-shrink:0;margin-bottom:${Math.round(scaledH - paperPxH + 20)}px;">
      ${pageHTML}</div>`;
    prHTML += pageHTML;
  });

  pvArea.innerHTML = pvHTML;
  prArea.innerHTML = prHTML;
}

// ══════════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  loadS();
  onPC();
  applyZoom();

  // Bind all settings controls
  PIDS.forEach(k => {
    const el = document.getElementById(k);
    if (!el) return;
    el.addEventListener('change', () => { saveS(); rlD(); });
    if (el.tagName === 'INPUT' && el.type !== 'checkbox')
      el.addEventListener('input', () => { saveS(); rlD(); });
  });

  // Initial search
  doSearch();

  // Re-render on window resize (zoom fit)
  let resizeT;
  window.addEventListener('resize', () => {
    clearTimeout(resizeT);
    resizeT = setTimeout(() => rl(), 200);
  });
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>