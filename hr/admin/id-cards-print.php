<?php
define('IS_ADMIN_PAGE', true);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$ids = $_GET['id'] ?? '';
if (empty($ids)) die('No employees selected');

$idArray = array_filter(array_map('intval', explode(',', $ids)));
if (empty($idArray)) die('Invalid IDs');

$conn    = getDBConnection();
$idsList = implode(',', $idArray);
$result  = $conn->query("SELECT * FROM employees WHERE id IN ($idsList)");
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[$row['id']] = $row;
}
if (empty($employees)) die('No employees found');

$companyName    = getSetting('company_name')    ?? 'My Company';
$companyLogo    = getSetting('company_logo')    ?? '';
$companyPhone   = getSetting('company_phone')   ?? '';
$companyAddress = getSetting('company_address') ?? '';
$companyEmail   = getSetting('company_email')   ?? '';
$includeQR      = isset($_GET['qr']) && $_GET['qr'] == '1';

function generateEmpID($emp, $prefix = 'EMP') {
    return $prefix . '-' . str_pad($emp['id'], 4, '0', STR_PAD_LEFT);
}

/*
 * DUPLEX ALIGNMENT — long-edge flip (standard portrait duplex)
 * Each row reversed so back cards align with front cards.
 * Front [e1][e2][e3]  →  Back [e3][e2][e1]
 */
$cols     = 3;
$empArray = array_values($employees);
$backEmps = [];
foreach (array_chunk($empArray, $cols) as $chunk) {
    while (count($chunk) < $cols) $chunk[] = null;
    $backEmps = [...$backEmps, ...array_reverse($chunk)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ID Cards Print</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Inter', 'Noto Sans Bengali', sans-serif;
    background: #d1d9e6;
    padding: 6mm;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* ── Screen toolbar ── */
.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 13px 20px;
    border-radius: 10px;
    margin-bottom: 14px;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
}
.toolbar.front { background: #111827; }
.toolbar.back  { background: #7f1d1d; }
.toolbar-sub   { font-size: 11px; font-weight: 400; opacity: 0.6; margin-top: 3px; }
.toolbar button {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    color: #fff; padding: 7px 20px;
    border-radius: 6px; font-size: 12px;
    font-weight: 600; cursor: pointer;
}
.toolbar button:hover { background: rgba(255,255,255,0.28); }

/* ── Print page ── */
@page { size: A4 portrait; margin: 3mm; }

@media print {
    body { padding: 0; background: #fff; }
    .toolbar { display: none; }
    .page { page-break-after: always; page-break-inside: avoid; }
    .page:last-of-type { page-break-after: auto; }
}

.page {
    width: 210mm;
    min-height: 287mm;
    margin: 0 auto;
    padding: 4mm;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-auto-rows: 97mm;
    gap: 3mm;
    justify-items: center;
    align-items: center;
    background: #fff;
}

.card-empty { width: 63mm; height: 95mm; visibility: hidden; }


/* ═══════════════════════════════════════════════════════
   FRONT CARD
   Colors used: #111827 (near-black), #fff, #2563eb (1 blue)
═══════════════════════════════════════════════════════ */
.card {
    width: 63mm;
    height: 95mm;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.18);
}

/* ── Full-bleed dark header — fills top 40% ── */
.card-header {
    width: 100%;
    height: 38mm;
    flex-shrink: 0;
    background: #111827;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.company-logo {
    width: 16mm;
    height: 16mm;
    object-fit: contain;
}

.logo-placeholder {
    width: 16mm;
    height: 16mm;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    border: 1.5px solid rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 5px; font-weight: 700;
    color: rgba(255,255,255,0.4);
    letter-spacing: 1px; text-transform: uppercase;
}

.company-name {
    font-size: 7px;
    font-weight: 600;
    color: rgba(255,255,255,0.75);
    letter-spacing: 1.8px;
    text-transform: uppercase;
    text-align: center;
    padding: 0 6px;
}

/* ── Photo: circular, bridges header ── */
/* header 38mm, photo 24mm (r=12mm), top = 38-12 = 26mm  */
.photo-wrap {
    position: absolute;
    top: 26mm;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
}

.photo-circle {
    width: 24mm;
    height: 24mm;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,0.25);
    overflow: hidden;
    background: #e5e7eb;
}

.photo-circle img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}

.photo-circle .no-photo {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #9ca3af;
}

/* ── Card body ── */
/* padding-top: 12mm photo below boundary + 3mm gap = 15mm */
.card-body {
    width: 100%;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15mm 7px 0;
    text-align: center;
}

.emp-name {
    font-size: 13px;
    font-weight: 800;
    color: #111827;
    line-height: 1.1;
    letter-spacing: -0.3px;
    margin-bottom: 3px;
}

.emp-position {
    font-size: 7px;
    font-weight: 600;
    color: #2563eb;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 6px;
}

.emp-id {
    font-size: 7px;
    font-weight: 700;
    color: #374151;
    background: #f3f4f6;
    padding: 3px 10px;
    border-radius: 4px;
    letter-spacing: 0.8px;
    margin-bottom: 3px;
}

/* ── Status line ── */
.status-line {
    font-size: 6px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 0;
}
.s-active     { color: #16a34a; }
.s-inactive   { color: #6b7280; }
.s-resigned   { color: #d97706; }
.s-terminated { color: #dc2626; }

/* ── Info block ── */
.info-block {
    width: 100%;
    margin-top: auto;
    padding: 7px 8px 6px;
    border-top: 1px solid #e5e7eb;
}

.info-row {
    display: flex;
    align-items: baseline;
    gap: 5px;
    font-size: 6.5px;
    line-height: 1.4;
    color: #374151;
    margin-bottom: 3.5px;
}
.info-row:last-child { margin-bottom: 0; }

.info-label {
    font-weight: 700;
    color: #111827;
    flex-shrink: 0;
    width: 11mm;
}

.info-val { color: #6b7280; font-weight: 500; }

/* ── Footer ── */
.card-footer {
    width: 100%;
    height: 8mm;
    flex-shrink: 0;
    background: #111827;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 6px;
    font-weight: 600;
    color: rgba(255,255,255,0.65);
    letter-spacing: 0.8px;
    text-transform: uppercase;
}


/* ═══════════════════════════════════════════════════════
   BACK CARD
   Same palette: #111827 + #fff + #2563eb
═══════════════════════════════════════════════════════ */
.card-back {
    width: 63mm;
    height: 95mm;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.18);
}

/* ── Back header ── */
.back-header {
    width: 100%;
    height: 22mm;
    flex-shrink: 0;
    background: #111827;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
}

.back-logo {
    width: 11mm;
    height: 11mm;
    object-fit: contain;
}

.back-company-name {
    font-size: 6px;
    font-weight: 600;
    color: rgba(255,255,255,0.7);
    letter-spacing: 1.8px;
    text-transform: uppercase;
    text-align: center;
    padding: 0 6px;
}

/* ── Back body ── */
.back-body {
    width: 100%;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 9px 0;
}

.verify-label {
    font-size: 6px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #9ca3af;
    margin-bottom: 6px;
}

/* QR code — large, clean, full-width feel */
.qr-wrap {
    width: 100%;
    display: flex;
    justify-content: center;
    margin-bottom: 7px;
}

.qr-wrap img {
    width: 32mm;
    height: 32mm;
    display: block;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 3px;
    background: #fff;
}

.scan-text {
    font-size: 6px;
    color: #9ca3af;
    font-weight: 500;
    text-align: center;
    margin-bottom: 8px;
    line-height: 1.4;
}

/* Return info */
.return-section {
    width: 100%;
    border-top: 1px solid #e5e7eb;
    padding-top: 7px;
}

.return-heading {
    font-size: 5.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #6b7280;
    margin-bottom: 4px;
}

.return-name {
    font-size: 8px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 2px;
}

.return-address {
    font-size: 6px;
    color: #6b7280;
    line-height: 1.5;
    font-weight: 500;
    margin-bottom: 6px;
}

.contact-item {
    font-size: 6.5px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 2.5px;
    display: flex;
    align-items: center;
    gap: 3px;
}

/* ── Back footer ── */
.back-footer {
    width: 100%;
    height: 8mm;
    flex-shrink: 0;
    background: #111827;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    margin-top: auto;
}

.back-footer-name {
    font-size: 6.5px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.3px;
}

.back-footer-id {
    font-size: 6px;
    font-weight: 500;
    color: rgba(255,255,255,0.5);
    letter-spacing: 0.5px;
}

.back-footer-sep {
    width: 1px; height: 8px;
    background: rgba(255,255,255,0.2);
}
</style>
</head>
<body>

<!-- ══════════════════════════ FRONT PAGE ══════════════════════════ -->
<div class="toolbar front">
    <div>
        <div>Front Side<?= $includeQR ? ' — Page 1 of 2' : '' ?></div>
        <div class="toolbar-sub">
            <?php if ($includeQR): ?>
                Enable <strong>duplex (two-sided)</strong> in your printer dialog — back side prints automatically.
            <?php else: ?>
                Add <code style="background:rgba(255,255,255,.12);padding:1px 6px;border-radius:3px;">?qr=1</code> to the URL to also generate the back side.
            <?php endif; ?>
        </div>
    </div>
    <button onclick="window.print()">Print</button>
</div>

<div class="page">
<?php foreach ($empArray as $emp):
    $sc = match(strtolower($emp['status'] ?? '')) {
        'active'   => 'active',   'inactive'   => 'inactive',
        'resigned' => 'resigned', default       => 'terminated',
    };
?>
    <div class="card">

        <!-- Dark header: logo + company name -->
        <div class="card-header">
            <?php if (!empty($companyLogo)): ?>
                <img src="../uploads/<?= htmlspecialchars($companyLogo) ?>" class="company-logo" alt="">
            <?php else: ?>
                <div class="logo-placeholder">Logo</div>
            <?php endif; ?>
            <div class="company-name"><?= htmlspecialchars($companyName) ?></div>
        </div>

        <!-- Photo bridges header/body -->
        <div class="photo-wrap">
            <div class="photo-circle">
                <?php if (!empty($emp['photo'])): ?>
                    <img src="../uploads/photos/<?= htmlspecialchars($emp['photo']) ?>" alt="">
                <?php else: ?>
                    <div class="no-photo">👤</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Body -->
        <div class="card-body">
            <div class="emp-name"><?= htmlspecialchars($emp['emp_name']) ?></div>
            <div class="emp-position"><?= htmlspecialchars($emp['position'] ?? '') ?></div>
            <div class="emp-id"><?= generateEmpID($emp) ?></div>
            <div class="status-line s-<?= $sc ?>"><?= ucfirst($emp['status'] ?? '') ?></div>
        </div>

        <!-- Info -->
        <div class="info-block">
            <?php if (!empty($emp['department'])): ?>
                <div class="info-row">
                    <span class="info-label">Dept</span>
                    <span class="info-val"><?= htmlspecialchars($emp['department']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($emp['office_name'])): ?>
                <div class="info-row">
                    <span class="info-label">Office</span>
                    <span class="info-val"><?= htmlspecialchars($emp['office_name']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($emp['joining_date'])): ?>
                <div class="info-row">
                    <span class="info-label">Joined</span>
                    <span class="info-val"><?= date('d M Y', strtotime($emp['joining_date'])) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="card-footer">
            <?= htmlspecialchars($companyPhone ?: 'Employee Identity Card') ?>
        </div>

    </div>
<?php endforeach; ?>
</div>


<?php if ($includeQR): ?>
<!-- ══════════════════════════ BACK PAGE ══════════════════════════
     Columns reversed per row for long-edge duplex alignment.
     Front [e1][e2][e3] → Back [e3][e2][e1]
══════════════════════════════════════════════════════════════════ -->
<div class="toolbar back">
    <div>
        <div>Back Side — Page 2 of 2</div>
        <div class="toolbar-sub">Cards mirrored per row for long-edge duplex — use printer duplex option.</div>
    </div>
    <button onclick="window.print()">Print</button>
</div>

<div class="page">
<?php foreach ($backEmps as $emp):
    if ($emp === null): ?>
        <div class="card-empty"></div>
    <?php continue; endif;

    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
             . '://' . $_SERVER['HTTP_HOST']
             . '/verify/?id=' . (int)$emp['id'];
    $qrUrl   = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($baseUrl);
?>
    <div class="card-back">

        <!-- Matching header -->
        <div class="back-header">
            <?php if (!empty($companyLogo)): ?>
                <img src="../uploads/<?= htmlspecialchars($companyLogo) ?>" class="back-logo" alt="">
            <?php endif; ?>
            <div class="back-company-name"><?= htmlspecialchars($companyName) ?></div>
        </div>

        <!-- QR + return info -->
        <div class="back-body">

            <div class="verify-label">Scan to Verify</div>

            <div class="qr-wrap">
                <img src="<?= $qrUrl ?>" alt="QR Code">
            </div>

            <div class="scan-text">Point your camera at the code above<br>to verify this employee's identity</div>

            <div class="return-section">
                <div class="return-heading">If found, please return to:</div>
                <div class="return-name"><?= htmlspecialchars($companyName) ?></div>
                <?php if (!empty($companyAddress)): ?>
                    <div class="return-address"><?= nl2br(htmlspecialchars($companyAddress)) ?></div>
                <?php endif; ?>
                <?php if (!empty($companyPhone)): ?>
                    <div class="contact-item">
                        <span>📞</span><?= htmlspecialchars($companyPhone) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($companyEmail)): ?>
                    <div class="contact-item">
                        <span>✉</span><?= htmlspecialchars($companyEmail) ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Footer -->
        <div class="back-footer">
            <span class="back-footer-name"><?= htmlspecialchars($emp['emp_name']) ?></span>
            <span class="back-footer-sep"></span>
            <span class="back-footer-id"><?= generateEmpID($emp) ?></span>
        </div>

    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</body>
</html>
