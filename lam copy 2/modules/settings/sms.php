<?php
// ============================================================
// modules/sms/sms.php — SMS Management and Campaigns
// ============================================================
requireRole(ROLE_ADMIN);

$action   = $_POST['action'] ?? $_GET['action'] ?? '';
$S        = getAllSettings();
$shopName = $S['shop_name']  ?? 'Our Shop';
$shopPhone= $S['shop_phone'] ?? '';

// ── Banned phrases — prevent phishing / OTP abuse ───────────
$BANNED_PATTERNS = [
    '/\botp\b/i', '/\bpassword\b/i', '/\bverif(y|ication|ying)\b/i',
    '/\bpin\b/i', '/\bsecure code\b/i', '/\bbank\b/i',
    '/\bcredit card\b/i', '/\bdebit card\b/i', '/\baccount number\b/i',
    '/\bclick here\b/i', '/\bfree money\b/i', '/\burgent action\b/i',
    '/\bsuspended\b/i', '/\bblocked account\b/i', '/\bconfirm your\b/i',
    '/\benter your\b/i', '/\byour account has\b/i',
];
function containsBannedContent(string $text, array $patterns): bool {
    foreach ($patterns as $p) { if (preg_match($p, $text)) return true; }
    return false;
}

// ── Templates ────────────────────────────────────────────────
$templates = [
    'en' => [
        'occasion'    => "Wishing you a joyous {occasion}! Celebrate with exclusive offers at {shop}. Call: {phone}",
        'promo'       => "Flash Sale! Get {offer} off selected items today only at {shop}. Call: {phone}",
        'points'      => "Hi {name}, you have {points} reward points waiting! Visit {shop} to redeem. Call: {phone}",
        'new_arrival' => "New arrivals just dropped! Check out our {product} at {shop}. Call: {phone}",
        'reminder'    => "Hi {name}, we miss you at {shop}! Come visit us. Call: {phone}",
        'duration'    => "Hi {name}, you've been our valued customer for {duration}! Thank you for your loyalty. Visit {shop} anytime. Call: {phone}",
    ],
    'bn' => [
        'occasion'    => "আপনাকে {occasion} এর শুভেচ্ছা! {shop} -এ বিশেষ অফার পাচ্ছেন। যোগাযোগ: {phone}",
        'promo'       => "ফ্ল্যাশ সেল! {shop} -এ আজই {offer} ছাড় পান। যোগাযোগ: {phone}",
        'points'      => "হ্যালো {name}, আপনার {points} রিওয়ার্ড পয়েন্ট আছে! {shop} -এ এসে ব্যবহার করুন। যোগাযোগ: {phone}",
        'new_arrival' => "নতুন কালেকশন! {shop} -এ নতুন {product} এসে গেছে। যোগাযোগ: {phone}",
        'reminder'    => "হ্যালো {name}, {shop} আপনাকে মিস করছে! আসুন। যোগাযোগ: {phone}",
        'duration'    => "হ্যালো {name}, আপনি {duration} ধরে আমাদের বিশ্বস্ত গ্রাহক! আপনাকে অনেক ধন্যবাদ। {shop} -এ আসুন। যোগাযোগ: {phone}",
    ],
];

// ── Send SMS ──────────────────────────────────────────────────
if ($S['sms_enabled'] === '1' && !empty($S['api_key_sms'])
    && $action === 'send_sms' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $apiKey       = $S['api_key_sms'];
    $templateType = $_POST['template_type'] ?? '';
    $lang         = $_POST['language'] ?? 'en';
    $numbers      = [];

    // Gather recipients
    if (!empty($_POST['all_customers'])) {
        foreach (dbFetchAll("SELECT phone FROM customers WHERE phone LIKE '01%'") as $c)
            $numbers[] = $c['phone'];
    } elseif (!empty($_POST['customer_phones'])) {
        $numbers = $_POST['customer_phones'];
    }
    if (!empty($_POST['manual_numbers']))
        $numbers = array_merge($numbers, preg_split('/[\s,]+/', $_POST['manual_numbers']));
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $f = fopen($_FILES['csv_file']['tmp_name'], 'r');
        while (($row = fgetcsv($f)) !== false) { if (!empty($row[0])) $numbers[] = trim($row[0]); }
        fclose($f);
    }

    // Validate BD numbers
    $validNumbers = [];
    foreach ($numbers as $num) {
        $num = preg_replace('/[^0-9]/', '', $num);
        if (preg_match('/^01\d{9}$/', $num)) $validNumbers[] = $num;
    }
    $validNumbers = array_unique($validNumbers);
    $count        = count($validNumbers);

    //check if the number is not customer save it in customers table with name sms and phone number and points 0
    foreach ($validNumbers as $num) {
        $exists = dbFetch('SELECT id FROM customers WHERE phone = ?', [$num]);
        if (!$exists) {
            dbInsert('customers', ['name' => 'SMS', 'phone' => $num, 'points' => 0]);
        }
    }

    // Build base message — static placeholders first
    $baseMsg = $templates[$lang][$templateType] ?? '';
    $baseMsg = str_replace(['{shop}', '{phone}'], [$shopName, $shopPhone], $baseMsg);
    if ($templateType === 'occasion')    $baseMsg = str_replace('{occasion}', trim($_POST['f_occasion']   ?? ''), $baseMsg);
    if ($templateType === 'promo')       $baseMsg = str_replace('{offer}',    trim($_POST['f_offer']      ?? ''), $baseMsg);
    if ($templateType === 'new_arrival') $baseMsg = str_replace('{product}',  trim($_POST['f_product']    ?? ''), $baseMsg);
    // {name}, {points}, {duration} resolved per-customer below

    // Security check
    if (containsBannedContent($baseMsg, $BANNED_PATTERNS)) {
        flash('error', '🚫 Message blocked: contains prohibited content. Please revise.');
        redirect('sms');
    }

    function requestsms($url, $params) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    if ($count > 0 && $baseMsg) {
        $logActionType = $count > 1 ? 'BULK_SMS' : 'SINGLE_SMS';
        $successCount  = 0;

        foreach ($validNumbers as $phone) {
            // Fetch customer with created_at for duration
            $custInfo = dbFetch('SELECT name, points, created_at FROM customers WHERE phone = ?', [$phone]);

            // Name: fallback to "Dear Customer" if not found or name empty
            $custName   = ($custInfo && !empty(trim($custInfo['name']))) ? trim($custInfo['name']) : 'Dear Customer';
            $custPoints = $custInfo ? ($custInfo['points'] ?? '0') : '0';

            // Duration: calculate how long they've been a customer
            $duration = $lang === 'bn' ? 'অনেক দিন' : 'a long time';
            if ($custInfo && !empty($custInfo['created_at'])) {
                $diff = (new DateTime($custInfo['created_at']))->diff(new DateTime());
                if ($diff->y > 0)     $duration = $diff->y . ($lang === 'bn' ? ' বছর ' : ' year') . ($diff->y  > 1 ? ($lang === 'bn' ? '' : 's') : '');
                elseif ($diff->m > 0) $duration = $diff->m . ($lang === 'bn' ? ' মাস ' : ' month') . ($diff->m  > 1 ? ($lang === 'bn' ? '' : 's') : '');
                elseif ($diff->d > 0) $duration = $diff->d . ($lang === 'bn' ? ' দিন ' : ' day')   . ($diff->d  > 1 ? ($lang === 'bn' ? '' : 's') : '');
                else                  $duration = $lang === 'bn' ? 'কয়েক দিন' : 'a few days';
            }
            $duration .= $lang === 'bn' ? 'থেবে' : '';

            $personalMsg = str_replace( 
                ['{name}', '{points}', '{duration}'],
                [$custName, $custPoints, $duration],
                $baseMsg
            );

            // Unicode detection — strictly above ASCII (\x7F = 127)
            $isUnicode = (bool) preg_match('/[^\x00-\x7F]/u', $personalMsg);
            $rate      = $isUnicode
                ? ceil(mb_strlen($personalMsg) / 70)
                : ceil(strlen($personalMsg) / 160);
            if ($logActionType === 'BULK_SMS' && !$isUnicode) $rate /= 2;

            if ((float)($S['sms_balance'] ?? 0) < 1) {
                $response = ['error' => 1, 'msg' => 'Insufficient SMS balance'];
            } else {
                $response = json_decode(requestsms('https://api.sms.net.bd/sendsms', [
                    'api_key' => $apiKey, 'msg' => $personalMsg, 'to' => $phone,
                ]), true);
            }

            if (isset($response['error']) && $response['error'] === 0) {
                $successCount++;
                logAction($logActionType, 'SMS', $rate, "To: $phone | $personalMsg");
                $bal = (float)(dbFetch('SELECT value FROM settings WHERE `key` = ?', ['sms_balance'])['value'] ?? 0);
                dbUpdate('settings', ['value' => $bal - $rate], '`key` = ?', ['sms_balance']);
            } else {
                logAction('SMS_FAIL', 'SMS', 0, "To: $phone | Error: " . ($response['msg'] ?? 'Unknown'));
            }
        }
        flash('success', "Campaign done! Sent $successCount of $count valid numbers.");
    } else {
        flash('error', 'No valid numbers or empty message.');
    }
    redirect('sms');
}

// ── Dashboard data ────────────────────────────────────────────
$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d');

$lifetimeStats = dbFetch("SELECT COUNT(*) as qty, SUM(CAST(record_id AS DECIMAL(10,2))) as cost
                          FROM action_logs WHERE module='SMS' AND action IN ('SINGLE_SMS','BULK_SMS')");

$page  = max(1, (int)($_GET['p'] ?? 1));
$paged = paginate(
    "SELECT l.*, u.full_name, u.username FROM action_logs l
     LEFT JOIN users u ON u.id = l.user_id
     WHERE l.module='SMS' AND l.created_at BETWEEN ? AND ? ORDER BY l.id DESC",
    [$from . ' 00:00:00', $to . ' 23:59:59'], $page, 50
);

$allCustomers = dbFetchAll("SELECT id, name, phone FROM customers WHERE phone LIKE '01%' ORDER BY name ASC");
$smsEnabled   = ($S['sms_enabled'] ?? '0') === '1' && !empty($S['api_key_sms']);
$smsBalance   = (float)($S['sms_balance'] ?? 0);

$pageTitle = 'SMS Campaigns';
require_once BASE_PATH . '/includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

.sms-wrap { font-family:'Sora',sans-serif; }

/* Top bar */
.sms-topbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
.sms-topbar h1 { margin:0; font-size:1.25rem; font-weight:800; display:flex; align-items:center; gap:8px; }
.sms-badge { font-size:0.58rem; font-weight:700; padding:3px 8px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px; }
.sms-badge.on  { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
.sms-badge.off { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }

/* Stat cards */
.stat-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:18px; }
.stat-card { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:13px 15px; }
.s-label { font-size:0.58rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); }
.s-val   { font-size:1.25rem; font-weight:800; color:var(--text); line-height:1.2; margin:2px 0; }
.s-sub   { font-size:0.62rem; color:var(--text-muted); }
.c-blue  { border-color:#3b82f6; background:linear-gradient(135deg,rgba(59,130,246,.07),transparent); }
.c-red   { border-color:#ef4444; background:linear-gradient(135deg,rgba(239,68,68,.07),transparent); }
.c-green { border-color:#22c55e; background:linear-gradient(135deg,rgba(34,197,94,.07),transparent); }
.c-amber { border-color:#f59e0b; background:linear-gradient(135deg,rgba(245,158,11,.07),transparent); }

/* Layout */
.sms-layout { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
@media(max-width:860px){ .sms-layout { grid-template-columns:1fr; } }

/* Panel */
.sms-panel { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
.panel-head { padding:11px 15px; border-bottom:1px solid var(--border); background:var(--surface2); display:flex; align-items:center; gap:8px; }
.panel-head .phi { font-size:0.95rem; }
.panel-head h3 { margin:0; font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; flex:1; }
.panel-body { padding:15px; }

/* Tabs */
.rtabs { display:flex; gap:5px; margin-bottom:11px; flex-wrap:wrap; }
.rtab { padding:5px 11px; border-radius:6px; font-size:0.7rem; font-weight:700; border:1px solid var(--border); background:var(--surface2); color:var(--text-muted); cursor:pointer; transition:all .15s; white-space:nowrap; }
.rtab.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.rtab-panel { display:none; }
.rtab-panel.active { display:block; }

/* All customers */
.all-tog { display:flex; align-items:center; gap:10px; padding:11px 13px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; cursor:pointer; }
.all-tog input { width:15px; height:15px; accent-color:#16a34a; }
.all-tog .atl { font-size:0.76rem; font-weight:700; color:#166534; flex:1; }
.all-tog small { font-size:0.6rem; color:#4ade80; }
.all-hint { font-size:0.64rem; color:var(--text-muted); margin-top:7px; line-height:1.5; }

/* Customer picker */
.ctags-box { min-height:38px; padding:5px 7px; border:1px dashed var(--border); border-radius:6px; margin-bottom:7px; display:flex; flex-wrap:wrap; gap:4px; align-items:flex-start; }
.ctag { display:inline-flex; align-items:center; gap:4px; background:var(--primary); color:#fff; padding:3px 8px; border-radius:20px; font-size:0.66rem; font-weight:600; }
.ctag button { background:none; border:none; color:#fff; cursor:pointer; font-size:0.72rem; padding:0; opacity:.7; line-height:1; }
.ctag button:hover { opacity:1; }
.cust-scroll { height:175px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; background:var(--surface2); }
.cust-row { display:flex; align-items:center; justify-content:space-between; padding:6px 10px; border-bottom:1px solid var(--border); cursor:pointer; font-size:0.72rem; transition:background .1s; }
.cust-row:hover { background:rgba(59,130,246,.08); }
.cust-row:last-child { border-bottom:none; }
.cn { font-weight:600; }
.cp { font-size:0.6rem; color:var(--text-muted); }

/* Template grid */
.tpl-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; margin-bottom:11px; }
.tpl-card { padding:8px 6px; border:2px solid var(--border); border-radius:8px; cursor:pointer; text-align:center; transition:all .15s; background:var(--surface2); }
.tpl-card:hover  { border-color:var(--primary); background:rgba(59,130,246,.05); }
.tpl-card.active { border-color:var(--primary); background:rgba(59,130,246,.1); }
.tci { font-size:1.2rem; display:block; margin-bottom:2px; }
.tcn { font-size:0.58rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.3px; }
.tpl-card.active .tcn { color:var(--primary); }

/* Lang */
.lang-btns { display:flex; gap:6px; margin-bottom:12px; }
.lang-btn { flex:1; padding:6px 0; border:1px solid var(--border); border-radius:6px; background:var(--surface2); cursor:pointer; font-size:0.7rem; font-weight:700; text-align:center; transition:all .15s; color:var(--text-muted); }
.lang-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }

/* Field */
.sf { margin-bottom:9px; }
.sf label { display:block; font-size:0.58rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:var(--text-muted); margin-bottom:3px; }
.sf input, .sf textarea {
  width:100%; padding:7px 9px; font-size:0.76rem;
  border:1px solid var(--border); border-radius:6px;
  background:var(--surface2); color:var(--text);
  outline:none; box-sizing:border-box; font-family:'Sora',sans-serif;
  transition:border-color .15s;
}
.sf input:focus, .sf textarea:focus { border-color:var(--primary); }
.sf textarea { resize:vertical; }
.sf .hint { font-size:0.6rem; color:var(--text-muted); margin-top:3px; }

/* Duration info */
.dur-info {
  padding:10px 12px; border-radius:8px;
  background:linear-gradient(135deg,rgba(59,130,246,.06),transparent);
  border:1px solid rgba(59,130,246,.2);
  font-size:0.68rem; color:var(--text-muted); line-height:1.6; margin-bottom:10px;
}
.dur-info strong { color:var(--primary); }

/* Preview */
.msg-preview {
  background:var(--surface2); border:1px solid var(--border); border-radius:8px;
  padding:11px; font-size:0.76rem; line-height:1.6; min-height:56px;
  white-space:pre-wrap; color:var(--text); font-family:'JetBrains Mono',monospace; margin-bottom:7px;
}
.msg-preview.blocked { border-color:#ef4444; background:#fef2f2; color:#991b1b; }
.char-bar { display:flex; align-items:center; gap:7px; font-size:0.6rem; color:var(--text-muted); margin-bottom:11px; flex-wrap:wrap; }
.chip { padding:2px 7px; border-radius:20px; font-weight:700; background:var(--surface2); border:1px solid var(--border); font-size:0.6rem; }
.chip.unicode { background:#fef9c3; color:#854d0e; border-color:#fde047; }
.chip.over    { background:#fee2e2; color:#991b1b; border-color:#fecaca; }
.blocked-lbl { font-size:0.6rem; color:#ef4444; font-weight:700; display:none; margin-top:3px; }

/* Send button */
.send-btn {
  width:100%; padding:11px; border-radius:8px; border:none;
  background:linear-gradient(135deg,#2563eb,#1d4ed8);
  color:#fff; font-size:0.82rem; font-weight:800;
  cursor:pointer; transition:all .2s; letter-spacing:0.3px;
  display:flex; align-items:center; justify-content:center; gap:8px;
  font-family:'Sora',sans-serif;
}
.send-btn:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 4px 16px rgba(37,99,235,.4); }
.send-btn:disabled { opacity:.45; cursor:not-allowed; }

/* Log */
.log-panel { margin-top:14px; }
.log-filter { display:flex; gap:7px; align-items:center; flex-wrap:wrap; margin-left:auto; }
.log-filter input { padding:4px 7px; font-size:0.72rem; border:1px solid var(--border); border-radius:5px; background:var(--surface2); color:var(--text); outline:none; }
.log-tbl { width:100%; border-collapse:collapse; font-size:0.74rem; }
.log-tbl th { text-align:left; padding:7px 10px; font-size:0.58rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; color:var(--text-muted); border-bottom:2px solid var(--border); }
.log-tbl td { padding:7px 10px; border-bottom:1px solid var(--border); vertical-align:top; }
.log-tbl tr:last-child td { border-bottom:none; }
.log-tbl tr:hover td { background:var(--surface2); }
.bsm { font-size:0.56rem; font-weight:800; padding:2px 6px; border-radius:4px; text-transform:uppercase; letter-spacing:0.3px; }
.b-bulk   { background:#dbeafe; color:#1e40af; }
.b-single { background:#d1fae5; color:#065f46; }
.b-fail   { background:#fee2e2; color:#991b1b; }
.b-ok     { background:#dcfce7; color:#166534; }
.sms-off { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; padding:40px 20px; text-align:center; color:var(--text-muted); font-size:0.8rem; }
.sms-off .oi { font-size:2.2rem; }
.search-inp { width:100%; padding:6px 9px; font-size:0.72rem; border:1px solid var(--border); border-radius:6px; background:var(--surface2); color:var(--text); outline:none; box-sizing:border-box; margin-bottom:6px; font-family:'Sora',sans-serif; }
.search-inp:focus { border-color:var(--primary); }
</style>

<div class="sms-wrap">

<div class="sms-topbar">
  <h1>
    📱 SMS Campaigns
    <span class="sms-badge <?= $smsEnabled ? 'on' : 'off' ?>">
      <?= $smsEnabled ? '● Live' : '✕ Disabled' ?>
    </span>
  </h1>
  <?php if (!$smsEnabled): ?>
    <a href="?page=settings" class="btn btn-ghost btn-sm">⚙ Enable in Settings →</a>
  <?php endif ?>
</div>

<div class="stat-row">
  <div class="stat-card c-blue">
    <div class="s-label">Total Sent</div>
    <div class="s-val"><?= number_format($lifetimeStats['qty'] ?? 0) ?></div>
    <div class="s-sub">All-time campaigns</div>
  </div>
  <div class="stat-card c-red">
    <div class="s-label">Total Cost</div>
    <div class="s-val">৳<?= number_format($lifetimeStats['cost'] ?? 0, 2) ?></div>
    <div class="s-sub">Lifetime spend</div>
  </div>
  <div class="stat-card c-green">
    <div class="s-label">Balance</div>
    <div class="s-val">৳<?= number_format($smsBalance, 2) ?></div>
    <div class="s-sub"><?= $smsBalance < 10 ? '⚠ Low balance' : 'Available' ?></div>
  </div>
  <div class="stat-card c-amber">
    <div class="s-label">Customers</div>
    <div class="s-val"><?= count($allCustomers) ?></div>
    <div class="s-sub">With valid phone</div>
  </div>
</div>

<?php if (!$smsEnabled): ?>
<div class="sms-panel">
  <div class="sms-off">
    <div class="oi">🔒</div>
    <strong>SMS is not configured</strong>
    <div>Go to Settings → SMS to add your API key and enable the service.</div>
    <a href="?page=settings" class="btn btn-primary btn-sm">Go to Settings</a>
  </div>
</div>
<?php else: ?>

<form method="POST" enctype="multipart/form-data" id="smsForm" onsubmit="return validateForm()">
  <input type="hidden" name="action" value="send_sms">
  <input type="hidden" name="language" id="hiddenLang" value="en">
  <input type="hidden" name="template_type" id="hiddenTpl" value="">

  <div class="sms-layout mb-3">

    <!-- LEFT: Recipients -->
    <div class="sms-panel">
      <div class="panel-head">
        <span class="phi">👥</span>
        <h3>Step 1 — Recipients</h3>
      </div>
      <div class="panel-body">

        <div class="rtabs">
          <div class="rtab active" onclick="switchTab('all',this)">All Customers</div>
          <div class="rtab" onclick="switchTab('pick',this)">Pick</div>
          <div class="rtab" onclick="switchTab('manual',this)">Manual</div>
          <div class="rtab" onclick="switchTab('csv',this)">CSV</div>
        </div>

        <div class="rtab-panel active" id="tab-all">
          <label class="all-tog">
            <input type="checkbox" name="all_customers" id="allCustChk" value="1" checked>
            <span class="atl">Send to ALL <?= count($allCustomers) ?> customers</span>
            <small>Bulk rate</small>
          </label>
          <div class="all-hint">All customers with a valid 01xxxxxxxxx number. Duplicates removed automatically.</div>
        </div>

        <div class="rtab-panel" id="tab-pick">
          <div style="font-size:0.64rem;color:var(--text-muted);margin-bottom:7px;">Click to add · Click tag to remove.</div>
          <div class="ctags-box" id="custTagsBox">
            <span id="noTagMsg" style="font-size:0.64rem;color:var(--text-muted);">No customers selected yet.</span>
          </div>
          <input type="text" class="search-inp" placeholder="🔍 Search name or phone…" oninput="filterCust(this.value)">
          <div class="cust-scroll" id="custScroll">
            <?php foreach ($allCustomers as $c): ?>
            <div class="cust-row" id="cr-<?= $c['id'] ?>"
                 onclick="addCust(<?= $c['id'] ?>,'<?= e($c['phone']) ?>','<?= e(addslashes($c['name'])) ?>')"
                 data-name="<?= strtolower(e($c['name'])) ?>" data-phone="<?= e($c['phone']) ?>">
              <span class="cn"><?= e($c['name']) ?></span>
              <span class="cp"><?= e($c['phone']) ?> ＋</span>
            </div>
            <?php endforeach ?>
          </div>
        </div>

        <div class="rtab-panel" id="tab-manual">
          <div class="sf">
            <label>Phone numbers (comma or newline separated)</label>
            <textarea name="manual_numbers" rows="5" placeholder="01712345678&#10;01812345678"></textarea>
          </div>
          <div style="font-size:0.62rem;color:var(--text-muted);">Only valid BD numbers (01xxxxxxxxx). Invalid ones skipped.</div>
        </div>

        <div class="rtab-panel" id="tab-csv">
          <div class="sf">
            <label>CSV — 1st column = phone (no header needed)</label>
            <input type="file" name="csv_file" accept=".csv">
          </div>
          <div style="font-size:0.62rem;color:var(--text-muted);margin-top:4px;">Numbers validated automatically. Duplicates merged.</div>
        </div>

      </div>
    </div>

    <!-- RIGHT: Compose -->
    <div class="sms-panel">
      <div class="panel-head">
        <span class="phi">✍️</span>
        <h3>Step 2 — Compose Message</h3>
      </div>
      <div class="panel-body">

        <div style="font-size:0.58rem;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted);margin-bottom:5px;">Language</div>
        <div class="lang-btns">
          <div class="lang-btn active" onclick="setLang('en',this)">🇬🇧 English</div>
          <div class="lang-btn" onclick="setLang('bn',this)">🇧🇩 বাংলা</div>
        </div>

        <div style="font-size:0.58rem;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted);margin-bottom:6px;">Template</div>
        <div class="tpl-grid">
          <div class="tpl-card" onclick="setTpl('occasion',this)"><span class="tci">🎉</span><span class="tcn">Occasion</span></div>
          <div class="tpl-card" onclick="setTpl('promo',this)"><span class="tci">🏷️</span><span class="tcn">Promo</span></div>
          <div class="tpl-card" onclick="setTpl('new_arrival',this)"><span class="tci">📦</span><span class="tcn">New Arrival</span></div>
          <div class="tpl-card" onclick="setTpl('points',this)"><span class="tci">💳</span><span class="tcn">Points</span></div>
          <div class="tpl-card" onclick="setTpl('reminder',this)"><span class="tci">📣</span><span class="tcn">Reminder</span></div>
          <div class="tpl-card" onclick="setTpl('duration',this)"><span class="tci">🕐</span><span class="tcn">Loyalty</span></div>
        </div>

        <div id="dynFields"></div>

        <div style="font-size:0.58rem;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted);margin-bottom:4px;">Live Preview</div>
        <div class="msg-preview" id="msgPreview">Select a template above to preview your message.</div>
        <div class="blocked-lbl" id="blockedLbl">🚫 This message will be blocked — contains prohibited content.</div>
        <div class="char-bar">
          <span class="chip" id="charChip">0 chars</span>
          <span class="chip" id="partChip">0 SMS parts</span>
          <span class="chip" id="encChip">Standard</span>
        </div>

        <button type="submit" class="send-btn" id="sendBtn" disabled>
          ✈️ <span id="sendBtnTxt">Select a template to continue</span>
        </button>

      </div>
    </div>
  </div>
</form>
<?php endif ?>

<!-- Dispatch Logs -->
<div class="sms-panel log-panel">
  <div class="panel-head">
    <span class="phi">📋</span>
    <h3>Dispatch Logs</h3>
    <form method="GET" class="log-filter">
      <input type="hidden" name="page" value="sms">
      <input type="date" name="from" value="<?= e($from) ?>">
      <span style="font-size:0.68rem;color:var(--text-muted);">→</span>
      <input type="date" name="to" value="<?= e($to) ?>">
      <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
    </form>
  </div>
  <div style="overflow-x:auto;">
    <table class="log-tbl">
      <thead>
        <tr>
          <th>Date &amp; Time</th><th>Type</th><th>Cost</th><th>Recipient &amp; Message</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($paged['rows'] ?? [] as $log): ?>
        <tr>
          <td style="white-space:nowrap;font-family:'JetBrains Mono',monospace;font-size:0.68rem;"><?= fmtDateTime($log['created_at']) ?></td>
          <td>
            <span class="bsm <?= $log['action']==='BULK_SMS'?'b-bulk':($log['action']==='SINGLE_SMS'?'b-single':'b-fail') ?>">
              <?= e($log['action']) ?>
            </span>
          </td>
          <td style="font-family:'JetBrains Mono',monospace;font-weight:700;white-space:nowrap;">৳<?= number_format((float)$log['record_id'],2) ?></td>
          <td style="max-width:280px;white-space:normal;line-height:1.4;font-size:0.7rem;"><?= e($log['note']) ?></td>
          <td>
            <?php if (str_contains($log['action'],'FAIL')): ?>
              <span class="bsm b-fail">Failed</span>
            <?php else: ?>
              <span class="bsm b-ok">Sent</span>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
        <?php if (empty($paged['rows'])): ?>
          <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted);font-size:0.78rem;">No logs for this period.</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
  <?php if (($paged['last_page'] ?? 1) > 1): ?>
  <div class="pagination" style="padding:10px 14px;">
    <?php for ($i = 1; $i <= $paged['last_page']; $i++): ?>
      <a href="?page=sms&from=<?= $from ?>&to=<?= $to ?>&p=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor ?>
  </div>
  <?php endif ?>
</div>

</div><!-- /sms-wrap -->

<script>
const SHOP  = <?= json_encode($shopName) ?>;
const PHONE = <?= json_encode($shopPhone) ?>;
const TMPLS = <?= json_encode($templates) ?>;

const BANNED = [
  /\botp\b/i, /\bpassword\b/i, /\bverif(y|ication|ying)\b/i,
  /\bpin\b/i, /\bsecure code\b/i, /\bbank\b/i,
  /\bcredit card\b/i, /\bdebit card\b/i, /\baccount number\b/i,
  /\bclick here\b/i, /\bfree money\b/i, /\burgent action\b/i,
  /\bsuspended\b/i, /\bblocked account\b/i, /\bconfirm your\b/i,
  /\benter your\b/i, /\byour account has\b/i,
];

let lang = 'en', tplKey = '', selectedCust = {}, isBlocked = false;

// Tabs
function switchTab(tab, el) {
  document.querySelectorAll('.rtab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  document.querySelectorAll('.rtab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  const chk = document.getElementById('allCustChk');
  if (chk) chk.checked = (tab === 'all');
}

// Customer picker
function filterCust(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#custScroll .cust-row').forEach(row => {
    row.style.display = (row.dataset.name.includes(q) || row.dataset.phone.includes(q)) ? '' : 'none';
  });
}
function addCust(id, phone, name) {
  if (selectedCust[phone]) return;
  selectedCust[phone] = { id, name };
  document.getElementById('noTagMsg').style.display = 'none';
  const tag = document.createElement('div');
  tag.className = 'ctag';
  tag.id = 'ctag-' + id;
  tag.innerHTML = `${name} <button type="button" onclick="removeCust('${phone}',${id})">✕</button><input type="hidden" name="customer_phones[]" value="${phone}">`;
  document.getElementById('custTagsBox').appendChild(tag);
  document.getElementById('cr-' + id).style.display = 'none';
}
function removeCust(phone, id) {
  delete selectedCust[phone];
  document.getElementById('ctag-' + id)?.remove();
  document.getElementById('cr-' + id).style.display = '';
  if (!Object.keys(selectedCust).length) document.getElementById('noTagMsg').style.display = '';
}

// Language
function setLang(l, btn) {
  lang = l;
  document.getElementById('hiddenLang').value = l;
  document.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  renderDynFields();
  updatePreview();
}

// Template metadata
const TPL_META = {
  occasion:    { fields:[{ key:'f_occasion',    label:'Occasion',     ph:'Eid Mubarak',    hint:'e.g. Eid Mubarak, Pohela Boishakh' }] },
  promo:       { fields:[{ key:'f_offer',        label:'Offer',        ph:'20%',            hint:'e.g. 20%, 500 Taka off' }] },
  new_arrival: { fields:[{ key:'f_product',      label:'Product Name', ph:'Winter Jackets', hint:'e.g. Winter Jackets, Gadgets' }] },
  points:      { fields:[] },
  reminder:    { fields:[] },
  duration:    { fields:[], isDuration:true },
};

function setTpl(key, card) {
  tplKey = key;
  document.getElementById('hiddenTpl').value = key;
  document.querySelectorAll('.tpl-card').forEach(c => c.classList.remove('active'));
  card.classList.add('active');
  renderDynFields();
  updatePreview();
}

function renderDynFields() {
  const meta = TPL_META[tplKey];
  const box  = document.getElementById('dynFields');
  if (!meta) { box.innerHTML = ''; return; }
  let html = '';
  if (meta.isDuration) {
    html = `<div class="dur-info">
      🕐 <strong>Loyalty Duration</strong> — each customer gets a personalised message showing how long they've been with you (e.g. "2 years", "5 months", "3 days"). Numbers not in your customer database are addressed as <strong>Dear Customer</strong>.
    </div>`;
  }
  meta.fields.forEach(f => {
    html += `<div class="sf">
      <label>${f.label} <span style="color:#ef4444;">*</span></label>
      <input type="text" name="${f.key}" id="fld_${f.key}" placeholder="${f.ph}" oninput="updatePreview()">
      ${f.hint ? `<div class="hint">${f.hint}</div>` : ''}
    </div>`;
  });
  box.innerHTML = html;
}

// Preview
function buildMessage() {
  if (!tplKey || !TMPLS[lang]?.[tplKey]) return '';
  let msg = TMPLS[lang][tplKey];
  msg = msg.replace('{shop}', SHOP).replace('{phone}', PHONE);
  if (tplKey === 'occasion')    msg = msg.replace('{occasion}', document.getElementById('fld_f_occasion')?.value  || (lang === 'bn' ? 'উৎসব' : 'Festive'));
  if (tplKey === 'promo')       msg = msg.replace('{offer}',    document.getElementById('fld_f_offer')?.value     || (lang === 'bn' ? 'বিশেষ' : 'Special'));
  if (tplKey === 'new_arrival') msg = msg.replace('{product}',  document.getElementById('fld_f_product')?.value   || (lang === 'bn' ? 'পণ্য' : 'Product'));
  // Simulate per-customer tokens
  msg = msg
    .replace('{name}',     lang === 'bn' ? 'গ্রাহক' : 'Customer')
    .replace('{points}',   '150')
    .replace('{duration}', lang === 'bn' ? '২ বছর' : '2 years');
  return msg;
}

function updatePreview() {
  const msg     = buildMessage();
  const blocked = msg ? BANNED.some(rx => rx.test(msg)) : false;
  isBlocked     = blocked;

  const preview = document.getElementById('msgPreview');
  preview.textContent = msg || 'Select a template above to preview your message.';
  preview.className   = 'msg-preview' + (blocked ? ' blocked' : '');
  document.getElementById('blockedLbl').style.display = blocked ? 'block' : 'none';

  // Char count — strict ASCII check (fixes always-unicode bug)
  const len       = msg.length;
  const isUnicode = /[^\x00-\x7F]/.test(msg);
  const limit     = isUnicode ? 70 : 160;
  const parts     = len ? Math.ceil(len / limit) : 0;

  document.getElementById('charChip').textContent = `${len} chars`;
  document.getElementById('charChip').className   = 'chip' + (len > limit * 2 ? ' over' : '');
  document.getElementById('partChip').textContent = `${parts} SMS part${parts !== 1 ? 's' : ''}`;
  document.getElementById('partChip').className   = 'chip' + (parts > 2 ? ' over' : '');
  document.getElementById('encChip').textContent  = isUnicode ? 'Unicode (Bengali)' : 'Standard (English)';
  document.getElementById('encChip').className    = 'chip' + (isUnicode ? ' unicode' : '');

  const sendBtn = document.getElementById('sendBtn');
  const sendTxt = document.getElementById('sendBtnTxt');
  const ready   = tplKey && msg && !blocked;
  sendBtn.disabled = !ready;
  if (!tplKey)      sendTxt.textContent = 'Select a template to continue';
  else if (!msg)    sendTxt.textContent = 'Fill in the required fields';
  else if (blocked) sendTxt.textContent = '🚫 Message blocked — prohibited content';
  else              sendTxt.textContent = `Send Campaign · ${parts} SMS part${parts !== 1 ? 's' : ''} per recipient`;
}

function validateForm() {
  if (!tplKey)   { alert('Please select a message template.'); return false; }
  if (isBlocked) { alert('Message contains prohibited content and cannot be sent.'); return false; }
  const isAll     = document.getElementById('allCustChk')?.checked;
  const hasPick   = Object.keys(selectedCust).length > 0;
  const hasManual = document.querySelector('textarea[name="manual_numbers"]')?.value?.trim();
  const hasCsv    = document.querySelector('input[name="csv_file"]')?.value;
  if (!isAll && !hasPick && !hasManual && !hasCsv) {
    alert('Please select at least one recipient source.');
    return false;
  }
  const msg = buildMessage();
  return confirm(`Ready to send?\n\n"${msg.substring(0,120)}${msg.length>120?'…':''}"\n\nThis will send real SMS and deduct from your balance.`);
}

updatePreview();
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>