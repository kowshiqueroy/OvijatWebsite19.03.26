<?php
// draw/index.php — Public lucky draw registration. No auth required.
// draw_title        → shown as the main message/announcement
// draw_prize        → shown as "contact for prize" info
// draw_end_date     → shown as "winner announced date"
// draw_description  → last month's winners list (empty = no list shown)
// draw_gsheet_url   → optional Google Sheets submission URL
require_once dirname(__DIR__) . '/config.php';

$pdo = getPDO();

$stmtS = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN
    ('site_name','site_logo','contact_phone','facebook_url',
     'draw_title','draw_prize','draw_end_date','draw_description','draw_gsheet_url')");
$s = array_column($stmtS->fetchAll(), 'value', 'key');

$siteName    = $s['site_name']       ?? 'Ovijat Group';
$logo        = $s['site_logo']       ?? '';
$phone       = $s['contact_phone']   ?? '09647000025';
$facebook    = $s['facebook_url']    ?? 'https://www.facebook.com/ovijatfood';
$drawTitle   = $s['draw_title']      ?? '';   // Main announcement message
$drawPrize   = $s['draw_prize']      ?? '';   // Contact info for prize
$drawEndDate = $s['draw_end_date']   ?? '';   // Winner announcement date
$drawDesc    = $s['draw_description'] ?? '';  // Last winners list (empty = hidden)
$gsheetURL   = $s['draw_gsheet_url'] ?? '';

// Store draw entry in DB
$drawMsg = '';
$drawErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['draw_submit'])) {
    $dEmail = trim($_POST['email']        ?? '');
    $dName  = trim($_POST['name']         ?? '');
    $dAddr  = trim($_POST['address']      ?? '');
    $dPhone = trim($_POST['phone']        ?? '');
    $dCode  = trim($_POST['product_code'] ?? '');
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!$dName || !$dPhone || !$dCode) {
        $drawErr = 'সব গুলো ঘর অবশ্যই পূরণ করতে হবে — Please fill all required fields.';
    } else {
        try {
            $pdo->prepare(
                "INSERT INTO contact_submissions (name,email,phone,subject,message,ip_address)
                 VALUES (?,?,?,?,?,?)"
            )->execute([
                $dName, $dEmail, $dPhone,
                'Draw Entry',
                'Code: ' . $dCode . ' | Address: ' . $dAddr,
                $ip
            ]);
        } catch (Exception $e) {}
        $drawMsg = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($siteName) ?> Raffle Draw</title>
  <?php if ($logo): ?>
  <link rel="icon" href="<?= BASE_URL ?>/uploads/logo/<?= e($logo) ?>" type="image/png">
  <?php endif; ?>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Arial, sans-serif;
      background: #f5f5f5;
      min-height: 100vh;
      padding: 24px 16px 48px;
    }

    /* Logo */
    .logo-wrap { text-align: center; margin-bottom: 12px; }
    .logo-wrap img {
      width: 70px; height: 70px; border-radius: 50%;
      object-fit: contain; background: #fff;
      box-shadow: 0 3px 12px rgba(0,0,0,.15);
    }
    .logo-fallback {
      width: 70px; height: 70px; border-radius: 50%;
      background: #1a3d1c; display: inline-flex;
      align-items: center; justify-content: center;
      font-size: 1.1rem; font-weight: 800; color: #C9A84C;
    }

    /* Title (draw_title as announcement message) */
    .draw-title {
      text-align: center; font-size: clamp(1.5rem, 6vw, 2rem);
      font-weight: 700; color: #4CAF50;
      margin-bottom: 8px; line-height: 1.25;
    }
    .site-sub {
      text-align: center; font-size: .88rem;
      color: rgb(226,194,12); margin-bottom: 6px; font-weight: 600;
    }
    .phone-link { text-align: center; margin-bottom: 6px; }
    .phone-link a { color: #cc0000; text-decoration: none; font-size: .9rem; font-weight: 600; }
    .phone-link a:hover { text-decoration: underline; }

    /* Meta info strip */
    .draw-meta {
      display: flex; flex-wrap: wrap; gap: 10px;
      justify-content: center; margin: 14px 0;
    }
    .draw-meta-item {
      background: #fff; border-radius: 10px;
      padding: 10px 16px; box-shadow: 0 2px 8px rgba(0,0,0,.1);
      font-size: .82rem; text-align: center; flex: 1; min-width: 150px;
    }
    .draw-meta-item .meta-label {
      font-size: .65rem; font-weight: 700; letter-spacing: .1em;
      text-transform: uppercase; color: #888; margin-bottom: 4px;
    }
    .draw-meta-item .meta-value { font-weight: 700; color: #222; font-size: .95rem; }

    /* Last winners panel (only shown if draw_description is filled) */
    .winners-panel {
      background: #fff; border-radius: 10px;
      border-left: 4px solid #4CAF50;
      padding: 14px 16px; margin-bottom: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,.08);
    }
    .winners-panel h3 {
      font-size: .85rem; font-weight: 700; color: #4CAF50;
      text-transform: uppercase; letter-spacing: .08em; margin-bottom: 8px;
    }
    .winners-panel p, .winners-panel pre {
      font-size: .88rem; color: #333; line-height: 1.75;
      white-space: pre-wrap; font-family: Arial, sans-serif;
    }

    /* Form */
    form {
      max-width: 420px; margin: 0 auto;
      background: #fff; padding: 22px 20px;
      border: 1px solid #ddd; border-radius: 12px;
      box-shadow: 0 4px 16px rgba(0,0,0,.1);
    }
    form .form-note {
      font-size: .83rem; text-align: center;
      color: #e88; margin-bottom: 12px; line-height: 1.5;
    }
    form .form-info {
      font-size: .78rem; text-align: center;
      color: #888; margin-bottom: 14px; line-height: 1.6;
    }
    input {
      display: block; width: 100%; padding: 11px 12px;
      margin-bottom: 12px; border: 1px solid #ccc;
      border-radius: 8px; font-size: .95rem;
      font-family: Arial, sans-serif;
      transition: border-color .2s, box-shadow .2s; outline: none;
    }
    input:focus { border-color: #4CAF50; box-shadow: 0 0 0 3px rgba(76,175,80,.15); }
    input.required-highlight { border-color: #e53; }
    .btn-submit {
      display: block; width: auto; margin: 0 auto;
      padding: 11px 32px; background: #4CAF50;
      color: #fff; border: none; border-radius: 8px;
      font-size: 1rem; font-weight: 700; cursor: pointer;
      transition: background .2s, transform .15s;
    }
    .btn-submit:hover { background: #45a049; transform: translateY(-1px); }
    .form-policy {
      font-size: .75rem; color: #888; text-align: center;
      margin-top: 14px; line-height: 1.7;
    }

    /* Facebook button */
    .btn-facebook {
      display: block; max-width: 420px; margin: 14px auto 0;
      padding: 12px 20px; background: #4267B2; color: #fff;
      border: none; border-radius: 8px; font-size: .95rem;
      font-weight: 700; text-align: center; text-decoration: none;
      transition: background .2s; cursor: pointer;
    }
    .btn-facebook:hover { background: #365899; }

    /* Success state */
    #draw-success {
      display: none; max-width: 420px; margin: 0 auto;
      text-align: center; padding: 28px 20px;
      border-radius: 12px; background: #4CAF50;
      color: #fff; font-size: 1.4rem; font-weight: 700;
      box-shadow: 0 4px 20px rgba(0,0,0,.15);
    }
    #draw-success .success-btns {
      display: flex; gap: 10px; justify-content: center;
      margin-top: 18px; flex-wrap: wrap;
    }
    #draw-success .success-btns a,
    #draw-success .success-btns button {
      flex: 1; min-width: 130px; max-width: 200px;
      padding: 10px 14px; border: none; border-radius: 8px;
      font-size: .9rem; font-weight: 700; cursor: pointer;
      text-decoration: none; text-align: center;
    }
    #draw-success .btn-fb-suc { background: #4267B2; color: #fff; }
    #draw-success .btn-more   { background: rgba(255,255,255,.25); color: #fff; border: 2px solid rgba(255,255,255,.5); }

    /* Waiting overlay */
    #draw-wait {
      display: none; position: fixed; inset: 0;
      background: rgba(255,255,255,.85); z-index: 999;
      align-items: center; justify-content: center;
      flex-direction: column; gap: 16px;
      font-size: 1.2rem; font-weight: 700; color: #cc0000;
      text-align: center;
    }
    @keyframes blinker { 50% { opacity: 0; } }
    #draw-wait p { animation: blinker 1.2s linear infinite; }

    @media (max-width: 480px) {
      .draw-title { font-size: 1.4rem; }
      input, .btn-submit { font-size: .9rem; }
    }
  </style>
</head>
<body>

<!-- Waiting overlay -->
<div id="draw-wait" style="display:none;position:fixed;inset:0;background:rgba(255,255,255,.85);z-index:999;display:none;align-items:center;justify-content:center;flex-direction:column;gap:16px;">
  <p style="animation:blinker 1.2s linear infinite;font-size:1.1rem;font-weight:700;color:#cc0000;font-family:Arial,sans-serif;">
    ⏳ Please Wait... দয়া করে অপেক্ষা করুন...
  </p>
</div>

<div style="max-width:460px;margin:0 auto;">

  <!-- Logo -->
  <div class="logo-wrap">
    <?php if ($logo): ?>
      <img src="<?= BASE_URL ?>/uploads/logo/<?= e($logo) ?>" alt="<?= e($siteName) ?>">
    <?php else: ?>
      <div class="logo-fallback">OFB</div>
    <?php endif; ?>
  </div>

  <!-- draw_title as the main announcement message -->
  <?php if ($drawTitle): ?>
  <div class="draw-title"><?= nl2br(e($drawTitle)) ?></div>
  <?php else: ?>
  <div class="draw-title">Ovijat Group Raffle Draw</div>
  <?php endif; ?>

  <div class="site-sub">Ovijat Food · Samsul Haque Auto Rice Mills · বক মার্কা চাল</div>
  <div class="phone-link">
    <a href="tel:<?= e(preg_replace('/\s+/','',$phone)) ?>">Call: <?= e($phone) ?> (Ovijat IT Call Center)</a>
  </div>

  <!-- Meta: prize contact + winner date -->
  <?php if ($drawPrize || $drawEndDate): ?>
  <div class="draw-meta">
    <?php if ($drawPrize): ?>
    <div class="draw-meta-item">
      <div class="meta-label">🏆 Prize / Contact for Prize</div>
      <div class="meta-value"><?= e($drawPrize) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($drawEndDate): ?>
    <div class="draw-meta-item">
      <div class="meta-label">📅 Winner Announced</div>
      <div class="meta-value"><?= e(date('d F Y', strtotime($drawEndDate))) ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Last winners list — only shown if draw_description is NOT empty -->
  <?php if (!empty(trim($drawDesc))): ?>
  <div class="winners-panel" style="max-width:420px;margin:0 auto 16px;">
    <h3>🥇 Last Month's Winners</h3>
    <pre><?= e(trim($drawDesc)) ?></pre>
  </div>
  <?php endif; ?>

  <!-- Form or success -->
  <?php if ($drawMsg === 'success'): ?>
  <div id="draw-success" style="display:block;">
    🎉 সফল! Success!
    <div class="success-btns">
      <a href="<?= e($facebook) ?>" target="_blank" rel="noopener" class="btn-fb-suc">Visit Facebook Page</a>
      <button class="btn-more" onclick="location.reload()">আরও পূরণ করুন</button>
    </div>
  </div>

  <?php else: ?>
  <form id="draw-form" method="POST" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="draw_submit" value="1">
    <p class="form-note">সব গুলো ঘর অবশ্যই পূরণ করতে হবে</p>
    <input type="text"  name="email"        placeholder="Email ইমেইল"                   value="your@gmail.com">
    <input type="text"  name="name"         placeholder="Name নাম"                       required>
    <input type="text"  name="address"      placeholder="Address ঠিকানা"                 required>
    <input type="tel"   name="phone"        placeholder="Phone ফোন"                      required>
    <input type="text"  name="product_code" placeholder="Product Code প্রোডাক্ট কোড"    required>
    <button type="submit" class="btn-submit">Submit সাবমিট</button>
    <p class="form-policy">
      ১ দিনে ১টির বেশি সাবমিট করবেন না<br>
      প্রতি মাসের শেষ তারিখে লাকি ড্র অনুষ্ঠিত হবে এবং Ovijat Food এর ফেসবুক পেজে
      বিজয়ীদের লিস্ট প্রকাশ করা হবে। বিজয়ীদের কল করে জানিয়ে কুরিয়ারের মাধ্যমে
      পুরষ্কার পাঠানো হবে।
    </p>
  </form>
  <?php endif; ?>

  <!-- Facebook -->
  <?php if ($facebook): ?>
  <a href="<?= e($facebook) ?>" target="_blank" rel="noopener" class="btn-facebook">
    Visit Facebook Group
  </a>
  <?php endif; ?>

</div>

<script>
(function() {
  var form   = document.getElementById('draw-form');
  var wait   = document.getElementById('draw-wait');
  var gsheet = <?= json_encode($gsheetURL) ?>;

  if (!form) return;

  // Show waiting overlay on submit
  form.addEventListener('submit', function(e) {

    // Basic validation
    var required = form.querySelectorAll('[required]');
    var allOk = true;
    required.forEach(function(inp) {
      inp.classList.remove('required-highlight');
      if (!inp.value.trim()) { inp.classList.add('required-highlight'); allOk = false; }
    });
    if (!allOk) { e.preventDefault(); alert('সব গুলো ঘর অবশ্যই পূরণ করতে হবে'); return; }

    // Show waiting state
    if (wait) { wait.style.display = 'flex'; }
    var submitBtn = form.querySelector('.btn-submit');
    if (submitBtn) submitBtn.style.display = 'none';

    // If Google Sheets URL is set, also post there then let PHP form submit
    if (gsheet) {
      e.preventDefault();
      var data = new FormData(form);
      data.append('timedate', new Date().toISOString().slice(0,19).replace('T',' '));
      data.append('qr', form.product_code ? form.product_code.value : '');

      fetch(gsheet, { method: 'POST', body: data })
        .then(function() {})
        .catch(function() {})
        .finally(function() {
          // Now submit to PHP regardless
          form.removeEventListener('submit', arguments.callee);
          form.submit();
        });
    }
    // else: normal PHP POST — let it through
  });

  // Success buttons (after PHP success)
  var moreBtn = document.querySelector('.btn-more');
  if (moreBtn) moreBtn.addEventListener('click', function() { location.reload(); });

})();
</script>

</body>
</html>
