<?php
// ============================================================
// modules/invoices/invoice.php — Invoice viewer + A4 print
// ============================================================
$id   = (int)($_GET['id'] ?? 0);
$sale = dbFetch(
    "SELECT s.*, u.full_name AS staff_name, c.name AS customer_name, c.phone AS customer_phone, c.points AS customer_points
     FROM sales s JOIN users u ON u.id = s.user_id
     LEFT JOIN customers c ON c.id = s.customer_id WHERE s.id = ?",
    [$id]
);
if (!$sale) { flash('error', 'Invoice not found.'); redirect('sales'); }

$items  = dbFetchAll('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id', [$id]);
$S      = getAllSettings();
$cur    = $S['currency_symbol'] ?? '$';
$qrData = BASE_URL . '/inv.php?id=' . $id;
$qrUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=' . urlencode($qrData);

// --- Pagination Logic for A4 Print ---
$pages = [];
if (count($items) <= 5) {
    // Fits perfectly on one page with totals
    $pages[] = ['items' => $items, 'is_last' => true];
} else {
    // Exceeds 5 items: Chunk into multiple pages
    $chunks = array_chunk($items, 10);
    foreach ($chunks as $i => $chunk) {
        $isLast = ($i === count($chunks) - 1);
        if ($isLast && count($chunk) > 5) {
            $pages[] = ['items' => $chunk, 'is_last' => false];
            $pages[] = ['items' => [], 'is_last' => true];
        } else {
            $pages[] = ['items' => $chunk, 'is_last' => $isLast];
        }
    }
}

$pageTitle = 'Invoice ' . $sale['invoice_no'];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="no-print">
  <div class="d-flex justify-between align-center mb-2 flex-wrap gap-2">
    <h1>🧾 <?= e($sale['invoice_no']) ?></h1>
    <div style="display:flex;gap:8px">
      <a href="index.php?page=sales" class="btn btn-ghost">← Back</a>
      <a href="index.php?page=thermal&id=<?= $id ?>" class="btn btn-outline" target="_blank">🧾 Thermal Print</a>
      <button class="btn btn-primary" onclick="window.print()">🖨️ A4 Print</button>
    </div>
  </div>
  <div class="card mb-2">
    <div class="form-row cols-2">
      <div><div class="text-muted" style="font-size:.78rem">Customer</div>
        <strong><?= e($sale['customer_name'] ?? 'Walk-in') ?></strong>
        <div class="text-muted" style="font-size:.78rem"><?= e($sale['customer_phone'] ?? '') ?></div></div>
      <div><div class="text-muted" style="font-size:.78rem">Staff / Date</div>
        <strong><?= e($sale['staff_name']) ?></strong>
        <div class="text-muted" style="font-size:.78rem"><?= fmtDateTime($sale['created_at']) ?></div></div>
      <div><span class="badge badge-<?= $sale['status']==='completed'?'success':'warning' ?>"><?= $sale['status'] ?></span></div>
      <div><div class="text-muted" style="font-size:.78rem">Payment</div><?= e($sale['payment_method']) ?></div>
    </div>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Item</th><th>Size</th><th>Color</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Total</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): ?>
          <tr><td><?= e($item['product_name']) ?></td><td><?= e($item['size']) ?></td><td><?= e($item['color']) ?></td>
            <td class="text-right"><?= $item['qty'] ?></td>
            <td class="text-right"><?= $cur . number_format($item['unit_price'],2) ?></td>
            <td class="text-right"><?= $cur . number_format($item['total_price'],2) ?></td></tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <div style="text-align:right;padding-top:10px;border-top:1px solid var(--border);margin-top:10px">
      <div class="summary-row"><span>Subtotal</span><span><?= $cur . number_format($sale['subtotal'],2) ?></span></div>
      <?php if ($sale['discount_amount'] > 0): ?><div class="summary-row"><span>Discount</span><span>−<?= $cur . number_format($sale['discount_amount'],2) ?></span></div><?php endif ?>
      <?php if ($sale['points_value']    > 0): ?><div class="summary-row"><span>Points</span><span>−<?= $cur . number_format($sale['points_value'],2) ?></span></div><?php endif ?>
      <?php if ($sale['vat_amount']      > 0): ?><div class="summary-row"><span>VAT (<?= $sale['vat_rate'] ?>%)</span><span><?= $cur . number_format($sale['vat_amount'],2) ?></span></div><?php endif ?>
      <div class="summary-row total"><span>TOTAL</span><span><?= $cur . number_format($sale['total'],2) ?></span></div>
    </div>
  </div>
</div>

<div id="printArea">
<?php foreach ($pages as $pageNum => $pageData): ?>
  <div class="printInvoiceWrap">
  <?php foreach (['Customer Copy', 'Showroom Copy'] as $copyLabel): ?>
  <div class="inv-copy-page">

    <div class="inv-header">
      <div class="inv-brand">
        <?php if (!empty($S['shop_logo_url'])): ?>
        <img src="<?= e($S['shop_logo_url']) ?>" class="inv-logo" alt="Logo">
        <?php endif ?>
        <div class="inv-shop-name"><?= e($S['shop_name']) ?></div>
        <div class="inv-shop-contact">
          <?= e($S['shop_address']) ?><br>
          <?= e($S['shop_phone']) ?> <?= !empty($S['shop_email'])?'| '.e($S['shop_email']):'' ?>
          <?= !empty($S['shop_tax_no'])?'<br>Tax No: '.e($S['shop_tax_no']):'' ?>
        </div>
      </div>
      <div class="inv-meta">
        <div class="inv-copy-badge"><?= $copyLabel ?> (Page <?= $pageNum + 1 ?> of <?= count($pages) ?>)</div>
        <div class="inv-meta-grid">
          <img src="<?= $qrUrl ?>" class="inv-qr" alt="QR Code">
          <table class="inv-meta-table">
            <tr><td>Invoice:</td><td><strong><?= e($sale['invoice_no']) ?></strong></td></tr>
            <tr><td>Date:</td><td><?= date('d M Y, H:i', strtotime($sale['created_at'])) ?></td></tr>
            <tr><td>Staff:</td><td><?= e($sale['staff_name']) ?></td></tr>
          </table>
        </div>
      </div>
    </div>

    <div class="inv-info-bar">
      <div><strong>Billed To:</strong> <?= e($sale['customer_name'] ?? 'Walk-in') ?> <?= $sale['customer_phone'] ? '('.e($sale['customer_phone']).')' : '' ?></div>
      <div><strong>Payment:</strong> <?= ucfirst(e($sale['payment_method'].' ('.$sale['notes'].')')) ?></div>
    </div>

    <div class="inv-body">
      <table class="inv-items">
        <thead>
          <tr>
            <th width="5%">#</th>
            <th width="45%">Item Description</th>
            <th width="15%" align="center">Qty</th>
            <th width="15%" align="right">Price</th>
            <th width="20%" align="right">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          // Keep continuous numbering across pages
          $startIndex = 0;
          for($i=0; $i<$pageNum; $i++) { $startIndex += count($pages[$i]['items']); }
          
          foreach ($pageData['items'] as $index => $item): 
            $variantInfo = trim(e($item['size']) . ' ' . e($item['color'])); 
          ?>
          <tr>
            <td><?= $startIndex + $index + 1 ?></td>
            <td>
              <div class="item-name"><?= e($item['product_name']) ?></div>
              <?php if ($variantInfo): ?><div class="item-var"><?= $variantInfo ?></div><?php endif ?>
            </td>
            <td align="center"><?= $item['qty'] ?></td>
            <td align="right"><?= number_format($item['unit_price'],2) ?></td>
            <td align="right"><strong><?= number_format($item['total_price'],2) ?></strong></td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <?php if ($pageData['is_last']): ?>
      <div class="inv-summary">
        <div class="inv-notes">
          <?php if ($sale['notes']): ?>
            <strong>Notes:</strong><br><?= nl2br(e($sale['notes'])) ?>
          <?php endif ?>
        </div>
        <div class="inv-totals">
          <div class="tot-row"><span>Subtotal:</span><span><?= number_format($sale['subtotal'],2) ?></span></div>
          <?php if ($sale['discount_amount'] > 0): ?>
          <div class="tot-row disc"><span>Discount <?= $sale['discount_pct']>0?'('.$sale['discount_pct'].'%)':'' ?>:</span><span>−<?= number_format($sale['discount_amount'],2) ?></span></div>
          <?php endif ?>
          <?php if ($sale['points_value'] > 0): ?>
          <div class="tot-row disc"><span>Points (<?= $sale['points_used'] ?> pts):</span><span>−<?= number_format($sale['points_value'],2) ?></span></div>
          <?php endif ?>
          <?php if ($sale['vat_amount'] > 0): ?>
          <div class="tot-row"><span>VAT <?= $sale['vat_rate']>0?'('.$sale['vat_rate'].'%)':'' ?>:</span><span><?= number_format($sale['vat_amount'],2) ?></span></div>
          <?php endif ?>
          <div class="tot-row grand"><span>Total (<?= $cur ?>):</span><span><?= number_format($sale['total'],2) ?></span></div>
        </div>
      </div>

      <div class="inv-signatures">
        <div class="sig-box"><div class="sig-line">Customer Signature</div></div>
        <div class="sig-box"><div class="sig-line">Authorized Signature</div></div>
      </div>
    <?php else: ?>
      <div style="text-align:center; padding: 20px; font-style:italic; color:#666;">
         — Continued on next page —
      </div>
    <?php endif; ?>

    <div class="inv-footer">
      <div class="inv-greet"><?= e($S['invoice_footer'] ?? 'Thank you for your business!') ?></div>
      <div class="inv-credit">Powered by sohojweb.com</div>
    </div>

  </div>
  <?php endforeach; ?>
  </div>
<?php endforeach; ?>
</div>

<style>
#printArea { display: none; }

@media print {
  @page { size: A4 landscape; margin: 0; }
  
  body, html { margin:0; padding:0; background:#fff !important; }
  .no-print, .app-header, .app-footer, .side-nav, .nav-overlay { display:none!important; }
  .app-main { margin:0!important; padding:0!important; background: transparent !important; }

  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

  #printArea { display: block; }
  
  /* Each wrap is one A4 page, forces a page break after */
  .printInvoiceWrap {
    display: flex;
    width: 297mm;
    height: 209mm;
    overflow: hidden;
    box-sizing: border-box;
    page-break-after: always;
  }

  .inv-copy-page {
    width: 50%; height: 100%; padding: 8mm 12mm; box-sizing: border-box;
    display: flex; flex-direction: column; border-right: 1px dashed #ccc;
    font-family: 'Segoe UI', Arial, sans-serif; color: #222;
  }
  .inv-copy-page:last-child { border-right: none; }

  .inv-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4mm; }
  .inv-brand { flex: 1; }
  .inv-logo { max-height: 16mm; max-width: 45mm; object-fit: contain; margin-bottom: 2mm; }
  .inv-shop-name { font-size: 15pt; font-weight: 800; letter-spacing: -0.5px; text-transform: uppercase; color: #111; }
  .inv-shop-contact { font-size: 7.5pt; color: #555; line-height: 1.3; margin-top: 1mm; }

  .inv-meta { text-align: right; }
  .inv-copy-badge { 
    display: inline-block; background: #6c5ce7; color: #fff; border-radius: 4px; 
    padding: 2px 8px; font-size: 7pt; font-weight: bold; text-transform: uppercase; margin-bottom: 2mm; 
  }
  .inv-meta-grid { display: flex; align-items: center; gap: 4mm; justify-content: flex-end; }
  .inv-qr { width: 14mm; height: 14mm; }
  .inv-meta-table { font-size: 7.5pt; border-collapse: collapse; text-align: right; }
  .inv-meta-table td { padding: 1px 0 1px 2px; }
  .inv-meta-table td:first-child { color: #666; padding-right: 2mm; }

  .inv-info-bar { 
    display: flex; justify-content: space-between; background: #f4f6f9; border-left: 3px solid #6c5ce7;
    padding: 2.5mm 3mm; border-radius: 0 4px 4px 0; font-size: 8pt; margin-bottom: 4mm; color: #111;
  }

  .inv-body { flex: 1; overflow: hidden; margin-bottom: 2mm; }
  .inv-items { width: 100%; border-collapse: collapse; font-size: 7.5pt; table-layout: fixed; }
  .inv-items thead th { 
    background: #6c5ce7; color: #ffffff; text-transform: uppercase; 
    font-size: 6.5pt; padding: 2.5mm 2mm; border: none; font-weight: 600;
  }
  .inv-items tbody td { padding: 2mm; border-bottom: 1px solid #eee; vertical-align: middle; }
  .inv-items tbody tr:nth-child(even) { background-color: #fdfdfd; }
  .item-name { font-weight: 600; font-size: 8pt; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #111; }
  .item-var { font-size: 6.5pt; color: #777; margin-top: 1px; }

  .inv-summary { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2mm; }
  .inv-notes { flex: 1; font-size: 7pt; color: #666; padding-right: 6mm; }
  .inv-totals { width: 45%; }
  .tot-row { display: flex; justify-content: space-between; font-size: 8pt; padding: 1mm 0; }
  .tot-row.disc { color: #d63031; font-weight: 500; }
  .tot-row.grand { 
    font-size: 10pt; font-weight: bold; background: #f4f6f9; color: #111;
    border-top: 2px solid #6c5ce7; padding: 2mm; margin-top: 1mm; border-radius: 4px;
  }

  .inv-signatures { display: flex; justify-content: space-between; margin-top: 20mm; margin-bottom: 4mm; }
  .sig-box { width: 40%; text-align: center; }
  .sig-line { 
    border-top: 1px solid #111; padding-top: 2mm; 
    font-size: 7.5pt; font-weight: 600; color: #333; text-transform: uppercase;
  }

  .inv-footer { 
    display: flex; justify-content: space-between; align-items: flex-end;
    border-top: 1px solid #eee; padding-top: 2mm; margin-top: auto;
  }
  .inv-greet { font-size: 8pt; font-style: italic; color: #444; }
  .inv-credit { font-size: 6.5pt; color: #999; }
}
</style>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>