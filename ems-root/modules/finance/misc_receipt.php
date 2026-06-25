<?php
/**
 * misc_receipt.php — Print receipt for walk-in / non-student collections
 */
define('EMS_ROOT', dirname(dirname(__DIR__)));
require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['fees.collect']);

$rcp = trim($_GET['rcp'] ?? '');
$mr  = $_SESSION['misc_receipt'] ?? null;

if (!$mr || ($rcp && $mr['receipt_no'] !== $rcp)) {
    http_response_code(404);
    die('<p style="font:14px sans-serif;padding:2rem;color:red">Misc receipt not found or session expired. Please redo the collection.</p>');
}

$xe  = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
$cur = $mr['cur'];
$logo    = setting('school_logo','');
$hasLogo = $logo && file_exists(UPLOAD_LOGOS.$logo);
$logoSrc = $hasLogo ? '../../uploads/logos/'.$xe($logo) : null;
$initials = substr(implode('', array_map(fn($w) => strtoupper($w[0]),
    array_filter(explode(' ', preg_replace('/[^a-zA-Z ]/', '', $mr['school'])), fn($w) => strlen($w) > 2))), 0, 3) ?: 'SCH';
$method = ['cash'=>'Cash','bank'=>'Bank Transfer','mobile_banking'=>'Mobile Banking',
           'cheque'=>'Cheque','online'=>'Online'][$mr['method']] ?? ucfirst($mr['method']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Misc Receipt <?= $xe($mr['receipt_no']) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Courier New',Courier,monospace;font-size:8.5pt;color:#000;
     background:#9ca3af;display:flex;flex-direction:column;align-items:center;padding:16px;gap:10px;}
.toolbar{display:flex;gap:8px;}
.tbtn{padding:7px 18px;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-weight:700;
      display:inline-flex;align-items:center;gap:5px;text-decoration:none;}
.tp{background:#1a56db;color:#fff;} .tb{background:#4b5563;color:#fff;}
.a4{width:210mm;background:#fff;box-shadow:0 6px 28px rgba(0,0,0,.22);}
.rct{width:100%;padding:8mm 10mm;}
/* Office + Accounts stubs */
.stub-row{display:flex;border:1pt solid #000;border-bottom:none;}
.stub{flex:1;padding:3mm 4mm;}
.stub+.stub{border-left:1pt dashed #000;}
.stub-lbl{font-size:6.5pt;font-weight:900;text-transform:uppercase;letter-spacing:.1em;
          border-bottom:.75pt solid #000;padding-bottom:1mm;margin-bottom:1.5mm;
          display:flex;justify-content:space-between;}
.sr{display:flex;gap:2mm;padding:.4mm 0;line-height:1.4;}
.sr .k{color:#555;font-size:6.5pt;min-width:18mm;flex-shrink:0;}
.sr .v{font-size:6.5pt;font-weight:700;}
.stub-total{margin-top:1.5mm;border-top:.75pt solid #000;padding-top:1mm;
            display:flex;justify-content:space-between;font-size:8pt;font-weight:900;}
.stub-sig{margin-top:1mm;border-top:.5pt solid #aaa;padding-top:.5mm;font-size:5.5pt;color:#555;text-align:center;}
/* Student / recipient copy */
.full-copy{border:1pt solid #000;padding:0;}
.sc-head{background:none;display:flex;align-items:center;gap:3mm;
         border-bottom:1.5pt solid #000;padding:3mm 4mm;}
.sc-logo{width:12mm;height:12mm;flex-shrink:0;border:1.5pt solid #000;border-radius:1.5mm;
         overflow:hidden;display:flex;align-items:center;justify-content:center;}
.sc-logo img{width:100%;height:100%;object-fit:contain;}
.sc-logo .ini{font-size:7pt;font-weight:900;}
.sc-school{flex:1;}
.sc-name{font-size:11pt;font-weight:900;text-transform:uppercase;letter-spacing:.04em;}
.sc-addr{font-size:6.5pt;color:#444;margin-top:1px;}
.sc-rct{text-align:right;flex-shrink:0;}
.sc-rct-title{font-size:9pt;font-weight:900;text-transform:uppercase;letter-spacing:.06em;border-bottom:1.5pt solid #000;padding-bottom:1mm;margin-bottom:1mm;}
.sc-rct-no{font-size:7pt;font-weight:700;}
.sc-rct-date{font-size:6.5pt;color:#444;}
.sc-body{padding:3mm 4mm;}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 6mm;border-bottom:.75pt solid #000;padding-bottom:1.5mm;margin-bottom:2mm;}
.si{display:flex;gap:1.5mm;padding:.6mm 0;}
.si .k{font-size:7pt;color:#555;min-width:22mm;flex-shrink:0;}
.si .v{font-weight:700;font-size:7.5pt;}
.fee-table{width:100%;border-collapse:collapse;margin-bottom:2mm;}
.fee-table th{font-size:6.5pt;font-weight:900;text-transform:uppercase;border-bottom:.75pt solid #000;padding:.7mm 1mm;text-align:left;}
.fee-table th.r,.fee-table td.r{text-align:right;}
.fee-table td{font-size:8pt;padding:.8mm 1mm;border-bottom:.4pt dotted #aaa;}
.fee-table .tf td{border-top:1pt solid #000;border-bottom:none;font-weight:900;font-size:9pt;padding-top:1.5mm;}
.sc-bottom{display:flex;align-items:flex-end;justify-content:space-between;
           border-top:.75pt solid #000;padding-top:1.5mm;margin-top:1mm;gap:4mm;}
.sc-meta{font-size:7pt;line-height:1.9;}
.sc-track{font-size:6pt;color:#555;background:#f5f5f5;border:.5pt solid #ccc;
          padding:.8mm 2mm;margin-top:1mm;font-family:'Courier New',monospace;letter-spacing:.06em;}
.sc-sigs{display:flex;gap:6mm;}
.sig-blk{text-align:center;min-width:38mm;}
.sig-line{border-top:.75pt solid #000;height:7mm;margin-bottom:.8mm;}
.sig-lbl{font-size:6pt;color:#444;}
.notice{font-size:5.5pt;color:#777;font-style:italic;margin-top:.5mm;}
@media print{body{background:#fff;padding:0;display:block;}.toolbar{display:none!important;}.a4{box-shadow:none;}@page{size:A4;margin:0;}}
</style>
</head>
<body>
<div class="toolbar">
  <button onclick="window.print()" class="tbtn tp">🖨 Print (A4)</button>
  <a href="collect.php" class="tbtn tb">← Back</a>
</div>

<div class="a4"><div class="rct">

  <!-- Office + Accounts stubs -->
  <div class="stub-row">
    <?php foreach (['OFFICE COPY','ACCOUNTS COPY'] as $lbl): ?>
    <div class="stub">
      <div class="stub-lbl"><span><?= $lbl ?></span><span style="font-weight:400;font-style:italic;font-size:6pt;"><?= $xe($mr['receipt_no']) ?></span></div>
      <div class="sr"><span class="k">Date   :</span><span class="v"><?= $xe(fmt_date($mr['date'],'d M Y')) ?></span></div>
      <?php if ($mr['name']): ?><div class="sr"><span class="k">Payer  :</span><span class="v"><?= $xe($mr['name']) ?></span></div><?php endif; ?>
      <div class="sr"><span class="k">Desc   :</span><span class="v"><?= $xe(mb_strimwidth($mr['desc'],0,35,'…')) ?></span></div>
      <div class="sr"><span class="k">Method :</span><span class="v"><?= $xe($method) ?></span></div>
      <div class="stub-total"><span>TOTAL</span><span><?= $xe($cur.' '.number_format($mr['amount'],2)) ?></span></div>
      <div class="stub-sig">Accounts _________________ Auth _________________</div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Full recipient copy -->
  <div class="full-copy" style="margin-top:3mm;">

    <div class="sc-head">
      <div class="sc-logo">
        <?php if ($logoSrc): ?><img src="<?= $logoSrc ?>" alt=""><?php else: ?><div class="ini"><?= $xe($initials) ?></div><?php endif; ?>
      </div>
      <div class="sc-school">
        <div class="sc-name"><?= $xe($mr['school']) ?></div>
        <?php $addrLine = implode('  |  ', array_filter([$mr['addr'], $mr['tel'] ? 'Tel: '.$mr['tel'] : ''])); ?>
        <?php if ($addrLine): ?><div class="sc-addr"><?= $xe($addrLine) ?></div><?php endif; ?>
      </div>
      <div class="sc-rct">
        <div class="sc-rct-title">Collection Receipt</div>
        <div class="sc-rct-no"><?= $xe($mr['receipt_no']) ?></div>
        <div class="sc-rct-date"><?= $xe(fmt_date($mr['date'],'d M Y')) ?></div>
      </div>
    </div>

    <div class="sc-body">
      <div class="info-grid">
        <div>
          <?php if ($mr['name']): ?>
          <div class="si"><span class="k">Received From</span><span class="v"><?= $xe($mr['name']) ?></span></div>
          <?php endif; ?>
          <div class="si"><span class="k">Description</span><span class="v"><?= $xe($mr['desc']) ?></span></div>
        </div>
        <div>
          <div class="si"><span class="k">Receipt No.</span><span class="v"><?= $xe($mr['receipt_no']) ?></span></div>
          <div class="si"><span class="k">Date</span><span class="v"><?= $xe(fmt_date($mr['date'],'d M Y')) ?></span></div>
          <div class="si"><span class="k">Payment Method</span><span class="v"><?= $xe($method) ?></span></div>
        </div>
      </div>

      <table class="fee-table">
        <thead><tr><th>#</th><th>Description</th><th class="r">Amount</th></tr></thead>
        <tbody>
          <tr><td>1</td><td><?= $xe($mr['desc']) ?></td><td class="r"><?= $xe($cur.' '.number_format($mr['amount'],2)) ?></td></tr>
        </tbody>
        <tfoot><tr class="tf"><td colspan="2" style="text-align:right;padding-right:2mm;">TOTAL AMOUNT RECEIVED</td><td class="r"><?= $xe($cur.' '.number_format($mr['amount'],2)) ?></td></tr></tfoot>
      </table>

      <div class="sc-bottom">
        <div class="sc-meta">
          <div><strong>Collected By :</strong> <?= $xe($mr['by']) ?></div>
          <div class="sc-track">TRACK ID: <?= $xe($mr['receipt_no']) ?></div>
          <div class="notice">Computer-generated receipt — valid without wet signature.</div>
        </div>
        <div class="sc-sigs">
          <div class="sig-blk"><div class="sig-line"></div><div class="sig-lbl">Accounts In-Charge</div></div>
          <div class="sig-blk"><div class="sig-line"></div><div class="sig-lbl">Authorised By</div></div>
        </div>
      </div>
    </div>
  </div>

</div></div>
</body>
</html>
