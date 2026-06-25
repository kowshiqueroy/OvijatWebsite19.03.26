<?php
/**
 * receipt.php
 * Layout per A4: 2 identical receipt blocks stacked.
 * Each block = top strip (Office | Accounts side-by-side, minimal) + bottom Student Copy (full, half-page).
 */
define('EMS_ROOT', dirname(dirname(__DIR__)));
require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['finance.view']);

$pdo = db();
$xe  = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');

// ── Load receipt data ────────────────────────────────────────────────────────
$rcp        = trim($_GET['rcp'] ?? '');
$student_id = int_param('student_id', 0, $_GET);

if (!$rcp && !$student_id) {
    http_response_code(404);
    die('<p style="font:14px sans-serif;padding:2rem;color:red">No receipt specified.</p>');
}

// If only student_id, get their latest receipt
if (!$rcp && $student_id) {
    $lr = $pdo->prepare('SELECT receipt_number FROM fee_payments WHERE student_id=:s ORDER BY id DESC LIMIT 1');
    $lr->execute([':s' => $student_id]);
    $rcp = $lr->fetchColumn();
    if (!$rcp) { http_response_code(404); die('<p style="font:14px sans-serif;padding:2rem;color:red">No payments found.</p>'); }
}

$stmt = $pdo->prepare(
    "SELECT fp.id, fp.amount, fp.payment_date, fp.payment_method, fp.receipt_number, fp.notes,
            fc.category_name, fl.notes AS ledger_notes,
            fl.amount_due, fl.waiver_amount, fl.month, fl.year,
            sp.first_name, sp.last_name, sp.student_id_no, sp.guardian_phone,
            c.class_name, sec.section_name, se.roll_number,
            ass.session_name,
            col.full_name AS collected_by_name
     FROM fee_payments fp
     JOIN fee_ledgers fl      ON fl.id  = fp.ledger_id
     JOIN fee_categories fc   ON fc.id  = fl.fee_category_id
     JOIN student_profiles sp ON sp.user_id = fp.student_id
     LEFT JOIN student_enrollments se ON se.student_id=fp.student_id AND se.session_id=fl.session_id AND se.status='active'
     LEFT JOIN classes c      ON c.id  = se.class_id
     LEFT JOIN sections sec   ON sec.id = se.section_id
     LEFT JOIN academic_sessions ass ON ass.id = fl.session_id
     LEFT JOIN users col      ON col.id = fp.collected_by
     WHERE fp.receipt_number = :rcp
     ORDER BY fp.id"
);
$stmt->execute([':rcp' => $rcp]);
$payments = $stmt->fetchAll();

if (empty($payments)) {
    http_response_code(404);
    die('<p style="font:14px sans-serif;padding:2rem;color:red">Receipt not found: <b>'.$xe($rcp).'</b></p>');
}

// ── Aggregate ────────────────────────────────────────────────────────────────
$f         = $payments[0];
$total     = array_sum(array_column($payments,'amount'));
$cur       = setting('currency_symbol','৳');
$school    = setting('school_name','School');
$addr      = setting('school_address','');
$tel       = setting('school_phone','');
$logo      = setting('school_logo','');
$hasLogo   = $logo && file_exists(UPLOAD_LOGOS.$logo);
$logoSrc   = $hasLogo ? '../../uploads/logos/'.$xe($logo) : null;
$initials  = substr(implode('',array_map(fn($w)=>strtoupper($w[0]),
               array_filter(explode(' ',preg_replace('/[^a-zA-Z ]/','',$school)),fn($w)=>strlen($w)>2))),0,3)?:'SCH';
$method    = ['cash'=>'Cash','bank'=>'Bank','mobile_banking'=>'Mobile Banking',
              'cheque'=>'Cheque','online'=>'Online'][$f['payment_method']] ?? ucfirst($f['payment_method']);
$date      = fmt_date($f['payment_date'],'d M Y');
$rcpNo     = $xe($f['receipt_number']);
$student   = $xe($f['first_name'].' '.$f['last_name']);
$sid       = $xe($f['student_id_no']??'—');
$cls       = $xe(($f['class_name']??'').($f['section_name']?' / '.$f['section_name']:''));
$roll      = $xe($f['roll_number']??'—');
$sess      = $xe($f['session_name']??'');
$by        = $xe($f['collected_by_name']??'Accounts');
$phone     = $xe($f['guardian_phone']??'');
$feeCount  = count($payments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt <?= $rcpNo ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}

/* ── Screen ─────────────────────────────────────────── */
body{
  font-family:'Courier New',Courier,monospace;
  font-size:8.5pt;color:#000;
  background:#9ca3af;
  display:flex;flex-direction:column;align-items:center;
  padding:16px;gap:10px;
}
.toolbar{display:flex;gap:8px;}
.tbtn{padding:7px 18px;border:none;border-radius:6px;cursor:pointer;
      font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:5px;text-decoration:none;}
.tprint{background:#1a56db;color:#fff;}
.tback{background:#4b5563;color:#fff;}

/* ── A4 shell ────────────────────────────────────────── */
.a4{width:210mm;background:#fff;box-shadow:0 6px 28px rgba(0,0,0,.25);}

/* ══════════════════════════════════════════════════════
   ONE RECEIPT BLOCK = exactly half of A4 (148.5mm)
   ┌──────────────────────────────────────────────────┐
   │  [STUB: Office | Accounts]  ≈ 42mm              │
   ├──────────────────────────────────────────────────┤
   │  STUDENT COPY (full)        ≈ 106mm             │
   └──────────────────────────────────────────────────┘
══════════════════════════════════════════════════════ */
.rct-block{
  width:100%;
  height:148.5mm;
  display:flex;
  flex-direction:column;
  border-bottom:1.5pt solid #000;
}
.rct-block:last-child{border-bottom:none;}

/* ── STUB ROW (Office + Accounts side-by-side) ───────── */
.stub-row{
  display:flex;
  height:42mm;
  border-bottom:1pt solid #000;
  flex-shrink:0;
}
.stub{
  flex:1;
  padding:2mm 3mm;
  display:flex;
  flex-direction:column;
  gap:0;
  position:relative;
}
.stub + .stub{border-left:1pt dashed #000;}

/* Stub copy label */
.stub-label{
  font-size:6.5pt;font-weight:900;
  text-transform:uppercase;letter-spacing:.1em;
  border-bottom:.75pt solid #000;
  padding-bottom:1mm;margin-bottom:1.5mm;
  display:flex;justify-content:space-between;
}
.stub-label .rno{font-weight:400;font-style:italic;font-size:6pt;}

/* Stub info rows */
.sr{display:flex;gap:1.5mm;padding:.35mm 0;line-height:1.3;}
.sr .k{color:#444;font-size:6.5pt;flex-shrink:0;min-width:14mm;}
.sr .v{font-weight:700;font-size:6.5pt;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.sr.fee-line .k{min-width:8mm;color:#000;}
.sr.fee-line .v{margin-left:auto;font-weight:700;}

/* Stub total line */
.stub-total{
  margin-top:auto;
  border-top:.75pt solid #000;
  padding-top:1mm;
  display:flex;justify-content:space-between;
  font-weight:900;font-size:8pt;
}

/* Stub signature */
.stub-sig{
  margin-top:1mm;
  border-top:.5pt solid #888;
  padding-top:.5mm;
  font-size:5.5pt;color:#555;
  text-align:center;
}

/* ── STUDENT COPY (full receipt) ─────────────────────── */
.student-copy{
  flex:1;
  display:flex;
  flex-direction:column;
  padding:3mm 5mm 2mm;
  overflow:hidden;
}

/* School header */
.sc-head{
  display:flex;align-items:center;gap:3mm;
  border-bottom:1.5pt solid #000;
  padding-bottom:2mm;margin-bottom:2mm;
}
.sc-logo{
  width:12mm;height:12mm;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  border:1.5pt solid #000;border-radius:1.5mm;overflow:hidden;
}
.sc-logo img{width:100%;height:100%;object-fit:contain;}
.sc-logo .ini{font-size:7pt;font-weight:900;}
.sc-school{flex:1;}
.sc-name{font-size:11pt;font-weight:900;text-transform:uppercase;letter-spacing:.04em;line-height:1.1;}
.sc-addr{font-size:6.5pt;color:#444;margin-top:1px;line-height:1.5;}
.sc-rct-block{text-align:right;flex-shrink:0;}
.sc-rct-title{font-size:9pt;font-weight:900;text-transform:uppercase;letter-spacing:.06em;
              border-bottom:1.5pt solid #000;padding-bottom:1mm;margin-bottom:1mm;}
.sc-rct-no{font-size:7pt;font-weight:700;}
.sc-rct-date{font-size:6.5pt;color:#444;}

/* Student info grid */
.sc-info{
  display:grid;grid-template-columns:1fr 1fr;
  gap:0 6mm;
  border-bottom:.75pt solid #000;
  padding-bottom:1.5mm;margin-bottom:1.5mm;
}
.si{display:flex;gap:1.5mm;padding:.6mm 0;}
.si .k{font-size:7pt;color:#555;min-width:20mm;flex-shrink:0;}
.si .v{font-weight:700;font-size:7.5pt;}

/* Fee table */
.fee-hdr{
  font-size:6.5pt;text-transform:uppercase;letter-spacing:.07em;font-weight:900;
  border-bottom:.75pt solid #000;padding-bottom:.8mm;margin-bottom:.5mm;
}
.fee-tbl{width:100%;border-collapse:collapse;}
.fee-tbl th{
  font-size:6.5pt;font-weight:900;text-transform:uppercase;letter-spacing:.04em;
  border-bottom:.75pt solid #000;padding:.7mm 1mm;text-align:left;
}
.fee-tbl th.r{text-align:right;}
.fee-tbl td{font-size:7.5pt;padding:.65mm 1mm;border-bottom:.4pt dotted #aaa;}
.fee-tbl td.r{text-align:right;}
.fee-tbl td.bold{font-weight:700;}
.fee-tbl .tfoot td{
  border-top:1pt solid #000;border-bottom:none;
  font-weight:900;font-size:8.5pt;padding-top:1mm;
}

/* Bottom strip */
.sc-bottom{
  display:flex;align-items:flex-end;justify-content:space-between;
  margin-top:auto;padding-top:1.5mm;
  border-top:.75pt solid #000;
  gap:4mm;
}
.sc-meta{font-size:7pt;line-height:1.8;}
.sc-track{font-size:6pt;color:#555;
  background:#f5f5f5;border:.5pt solid #ccc;
  padding:.8mm 2mm;margin-top:1mm;
  font-family:'Courier New',monospace;letter-spacing:.06em;}
.sc-sigs{display:flex;gap:6mm;}
.sig-blk{text-align:center;min-width:38mm;}
.sig-line{border-top:.75pt solid #000;height:7mm;margin-bottom:.8mm;}
.sig-lbl{font-size:6pt;color:#444;}
.validity{font-size:5.5pt;color:#777;font-style:italic;margin-top:.5mm;}

/* ── Print ────────────────────────────────────────────── */
@media print{
  body{background:#fff;padding:0;display:block;}
  .toolbar{display:none!important;}
  .a4{box-shadow:none;}
  @page{size:A4 portrait;margin:0;}
}
</style>
</head>
<body>

<div class="toolbar">
  <button onclick="window.print()" class="tbtn tprint">🖨 Print (A4)</button>
  <a href="collect.php" class="tbtn tback">← Collect Fee</a>
  <a href="ledger.php" class="tbtn tback">📋 Ledger</a>
</div>

<div class="a4">
<?php
// ── Build fee rows once ────────────────────────────────
$feeRowsHtml = '';
foreach ($payments as $idx => $pay) {
    $period = $pay['month']
        ? date('M Y', mktime(0,0,0,$pay['month'],1,$pay['year']))
        : $xe($pay['session_name'] ?? '');
    // For custom/ad-hoc fees, ledger_notes holds the description
    $customDesc = trim($pay['ledger_notes'] ?? '');
    $feeName    = $customDesc ?: $xe($pay['category_name']);
    // Show custom tag when it's an ad-hoc fee
    $customTag  = $customDesc ? ' <span style="font-size:6pt;background:#fef9c3;border:.4pt solid #d97706;padding:0 2pt;border-radius:1pt;color:#92400e;">Custom</span>' : '';
    $feeRowsHtml .= '<tr>
      <td>'.($idx+1).'</td>
      <td class="bold">'.$feeName.$customTag.'</td>
      <td>'.$xe($period).'</td>
      <td class="r">'.$xe($cur.' '.number_format($pay['amount_due'],2)).'</td>';
    if ($pay['waiver_amount'] > 0) {
        $feeRowsHtml .= '<td class="r">-'.$xe($cur.' '.number_format($pay['waiver_amount'],2)).'</td>';
    } else {
        $feeRowsHtml .= '<td class="r" style="color:#999;">—</td>';
    }
    $feeRowsHtml .= '<td class="r bold">'.$xe($cur.' '.number_format($pay['amount'],2)).'</td>
    </tr>';
}

// ── Stub fee summary (compact) ─────────────────────────
$stubFeeHtml = '';
foreach ($payments as $idx => $pay) {
    $period = $pay['month'] ? date('M\'y', mktime(0,0,0,$pay['month'],1,$pay['year'])) : '';
    $label  = mb_strimwidth($pay['category_name'],0,18,'…');
    $stubFeeHtml .= '<div class="sr fee-line">
      <span class="k">'.($idx+1).'. '.$xe($label).($period?' ('.$xe($period).')':'').'</span>
      <span class="v">'.$xe($cur.' '.number_format($pay['amount'],2)).'</span>
    </div>';
}

// ── Render 2 identical receipt blocks ─────────────────
for ($n = 0; $n < 1; $n++):
?>

<div class="rct-block">

  <!-- ════ STUB ROW: Office | Accounts (minimal) ════ -->
  <div class="stub-row">

    <!-- Office Copy stub -->
    <div class="stub">
      <div class="stub-label">
        <span>OFFICE COPY</span>
        <span class="rno"><?= $rcpNo ?></span>
      </div>
      <div class="sr"><span class="k">Date  :</span><span class="v"><?= $xe($date) ?></span></div>
      <div class="sr"><span class="k">Name  :</span><span class="v"><?= $student ?></span></div>
      <div class="sr"><span class="k">Class :</span><span class="v"><?= $cls ?></span></div>
      <div class="sr"><span class="k">Roll  :</span><span class="v"><?= $roll ?> &nbsp;&nbsp; Sess: <?= $sess ?></span></div>
      <div class="sr"><span class="k">Method:</span><span class="v"><?= $xe($method) ?></span></div>
      <?= $stubFeeHtml ?>
      <div class="stub-total">
        <span>TOTAL</span>
        <span><?= $xe($cur.' '.number_format($total,2)) ?></span>
      </div>
      <div class="stub-sig">Accounts _________________ Auth _________________</div>
    </div>

    <!-- Accounts Copy stub -->
    <div class="stub">
      <div class="stub-label">
        <span>ACCOUNTS COPY</span>
        <span class="rno"><?= $rcpNo ?></span>
      </div>
      <div class="sr"><span class="k">Date  :</span><span class="v"><?= $xe($date) ?></span></div>
      <div class="sr"><span class="k">Name  :</span><span class="v"><?= $student ?></span></div>
      <div class="sr"><span class="k">Class :</span><span class="v"><?= $cls ?></span></div>
      <div class="sr"><span class="k">Roll  :</span><span class="v"><?= $roll ?> &nbsp;&nbsp; Sess: <?= $sess ?></span></div>
      <div class="sr"><span class="k">Method:</span><span class="v"><?= $xe($method) ?></span></div>
      <?= $stubFeeHtml ?>
      <div class="stub-total">
        <span>TOTAL</span>
        <span><?= $xe($cur.' '.number_format($total,2)) ?></span>
      </div>
      <div class="stub-sig">Accounts _________________ Auth _________________</div>
    </div>

  </div><!-- /stub-row -->

  <!-- ════ STUDENT COPY (full receipt, remaining space) ════ -->
  <div class="student-copy">

    <!-- School header -->
    <div class="sc-head">
      <div class="sc-logo">
        <?php if ($logoSrc): ?>
          <img src="<?= $logoSrc ?>" alt="">
        <?php else: ?>
          <div class="ini"><?= $xe($initials) ?></div>
        <?php endif; ?>
      </div>
      <div class="sc-school">
        <div class="sc-name"><?= $xe($school) ?></div>
        <?php
        $meta = array_filter([$addr, $tel ? 'Tel: '.$tel : '']);
        if ($meta): ?>
        <div class="sc-addr"><?= $xe(implode('  |  ', $meta)) ?></div>
        <?php endif; ?>
      </div>
      <div class="sc-rct-block">
        <div class="sc-rct-title">Fee Receipt</div>
        <div class="sc-rct-no"><?= $rcpNo ?></div>
        <div class="sc-rct-date"><?= $xe($date) ?></div>
      </div>
    </div>

    <!-- Student info -->
    <div class="sc-info">
      <div>
        <div class="si"><span class="k">Student Name</span><span class="v"><?= $student ?></span></div>
        <div class="si"><span class="k">Student ID</span><span class="v"><?= $sid ?></span></div>
        <div class="si"><span class="k">Class / Section</span><span class="v"><?= $cls ?></span></div>
        <div class="si"><span class="k">Roll No.</span><span class="v"><?= $roll ?></span></div>
      </div>
      <div>
        <div class="si"><span class="k">Receipt No.</span><span class="v"><?= $rcpNo ?></span></div>
        <div class="si"><span class="k">Payment Date</span><span class="v"><?= $xe($date) ?></span></div>
        <div class="si"><span class="k">Session</span><span class="v"><?= $sess ?></span></div>
        <div class="si"><span class="k">Payment Method</span><span class="v"><?= $xe($method) ?></span></div>
      </div>
    </div>

    <!-- Fee table -->
    <div class="fee-hdr">Fee Details</div>
    <table class="fee-tbl">
      <thead>
        <tr>
          <th style="width:6mm">#</th>
          <th>Fee Category</th>
          <th>Period</th>
          <th class="r">Charged</th>
          <th class="r">Waiver</th>
          <th class="r">Paid</th>
        </tr>
      </thead>
      <tbody>
        <?= $feeRowsHtml ?>
      </tbody>
      <tfoot>
        <tr class="tfoot">
          <td colspan="5" style="text-align:right;letter-spacing:.05em;padding-right:2mm;">
            TOTAL AMOUNT PAID
          </td>
          <td class="r"><?= $xe($cur.' '.number_format($total,2)) ?></td>
        </tr>
      </tfoot>
    </table>

    <!-- Bottom: meta + signatures -->
    <div class="sc-bottom">
      <div class="sc-meta">
        <div><strong>Collected By :</strong> <?= $by ?></div>
        <div class="sc-track">TRACK ID: <?= $rcpNo ?></div>
        <div class="validity">Computer-generated receipt — valid without wet signature.</div>
      </div>
      <div class="sc-sigs">
        <div class="sig-blk">
          <div class="sig-line"></div>
          <div class="sig-lbl">Accounts In-Charge</div>
        </div>
        <div class="sig-blk">
          <div class="sig-line"></div>
          <div class="sig-lbl">Authorised By</div>
        </div>
      </div>
    </div>

  </div><!-- /student-copy -->

</div><!-- /rct-block -->

<?php endfor; ?>

</div><!-- /a4 -->
</body>
</html>
