<?php
// freewifi/index.php — Public WiFi portal. No auth required.
require_once dirname(__DIR__) . '/config.php';

$pdo = getPDO();

// Fetch settings
$stmtS = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN
    ('site_name','site_logo','contact_phone','facebook_url',
     'wifi_ssid','wifi_password','wifi_banner','wifi_tagline','wifi_gsheet_url')");
$s = array_column($stmtS->fetchAll(), 'value', 'key');

$siteName   = $s['site_name']       ?? 'Ovijat Group';
$logo       = $s['site_logo']       ?? '';
$phone      = $s['contact_phone']   ?? '09647000025';
$facebook   = $s['facebook_url']    ?? 'https://www.facebook.com/ovijatfood';
$wifiSSID   = $s['wifi_ssid']       ?? 'Ovijat_WiFi_Free';
$wifiPass   = $s['wifi_password']   ?? 'ovijat2025';
$wifiBanner = $s['wifi_banner']     ?? '';
$tagline    = $s['wifi_tagline']    ?? 'Ovijat Food - Samsul Haque Auto Rice Mills - বক মার্কা চাল';
$gsheetURL  = $s['wifi_gsheet_url'] ?? '';

// Save submission to DB
$wifiMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['wifi_submit'])) {
    $wName    = trim($_POST['name']    ?? '');
    $wEmail   = trim($_POST['email']   ?? '');
    $wAddress = trim($_POST['address'] ?? '');
    $wPhone   = trim($_POST['phone']   ?? '');
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($wName && $wEmail && $wAddress && $wPhone) {
        try {
            $pdo->prepare(
                "INSERT INTO contact_submissions (name,email,phone,subject,message,ip_address)
                 VALUES (?,?,?,?,?,?)"
            )->execute([$wName,$wEmail,$wPhone,'WiFi Registration','Address: '.$wAddress,$ip]);
        } catch (Exception $e) {}
        $wifiMsg = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($siteName) ?> Free WiFi</title>
  <?php if ($logo): ?>
  <link rel="icon" href="<?= BASE_URL ?>/uploads/logo/<?= e($logo) ?>" type="image/png">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #00c9a7;
      --glass: rgba(255,255,255,.62);
      --radius: 16px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #ffdde1 0%, #ee9ca7 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 28px 16px 48px;
      overflow-x: hidden;
    }
    .container { width: 100%; max-width: 440px; }

    /* Logo */
    .logo-wrap { text-align: center; margin-bottom: 10px; }
    .logo-wrap img {
      width: 80px; height: 80px; border-radius: 50%;
      object-fit: contain; background: #fff;
      box-shadow: 0 4px 16px rgba(0,0,0,.15);
    }
    .logo-fallback {
      width: 80px; height: 80px; border-radius: 50%;
      background: #1a3d1c; display: inline-flex;
      align-items: center; justify-content: center;
      font-size: 1.3rem; font-weight: 800; color: #C9A84C;
    }

    /* Animated brand name */
    .brand-name {
      text-align: center;
      font-size: clamp(2.2rem, 11vw, 3.4rem);
      font-weight: 800;
      background: linear-gradient(90deg, #ff0000, #ff8800, #00cc44, #0066ff, #cc00cc, #ff0000);
      background-size: 400% 100%;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      animation: gradientShift 5s ease-in-out infinite alternate;
      line-height: 1.1; margin-bottom: 4px;
    }
    @keyframes gradientShift {
      0%   { background-position: 0% 50%; }
      100% { background-position: 100% 50%; }
    }
    .subtitle {
      text-align: center; font-size: 1.5rem;
      font-weight: 700; color: #222; margin-bottom: 6px;
    }
    .tagline {
      text-align: center; font-size: .85rem;
      color: #555; margin-bottom: 5px; line-height: 1.5;
    }
    .phone-link { text-align: center; margin-bottom: 16px; }
    .phone-link a {
      font-size: .95rem; font-weight: 600;
      color: #cc0000; text-decoration: none;
    }
    .phone-link a:hover { text-decoration: underline; }

    /* Banner */
    .banner-wrap {
      border-radius: var(--radius); overflow: hidden;
      margin: 14px 0; box-shadow: 0 6px 20px rgba(0,0,0,.2);
      transition: transform .3s;
    }
    .banner-wrap:hover { transform: scale(1.02); }
    .banner-wrap img { width: 100%; display: block; }

    /* WiFi credentials box */
    .wifi-creds {
      background: var(--glass);
      backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
      border-radius: var(--radius); padding: 18px 20px;
      margin-bottom: 16px; box-shadow: 0 4px 18px rgba(0,0,0,.1);
    }
    .wifi-row { margin-bottom: 10px; }
    .wifi-row:last-child { margin-bottom: 0; }
    .wifi-label {
      font-size: .65rem; font-weight: 700; letter-spacing: .12em;
      text-transform: uppercase; color: #777; margin-bottom: 2px;
    }
    .wifi-value {
      font-size: 1.2rem; font-weight: 700; color: #111;
      word-break: break-all;
    }

    /* Form */
    form {
      background: var(--glass);
      backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
      padding: 22px 20px; border-radius: var(--radius);
      box-shadow: 0 8px 24px rgba(0,0,0,.12);
      animation: fadeUp .5s ease;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    form .form-note {
      font-size: .82rem; text-align: center;
      color: #555; margin-bottom: 14px; line-height: 1.5;
    }
    input[type=text], input[type=email], input[type=tel] {
      width: 100%; padding: 13px 14px; margin-bottom: 12px;
      border: none; border-radius: 10px;
      background: #f0f0f0; font-size: 15px;
      font-family: 'Poppins', sans-serif;
      transition: background .25s, box-shadow .25s; outline: none;
    }
    input:focus { background: #e0ffe8; box-shadow: 0 0 0 3px rgba(0,201,167,.25); }
    .btn-submit {
      width: 100%; padding: 14px; border: none; border-radius: 10px;
      background: var(--primary); color: #fff;
      font-size: 1rem; font-weight: 700; font-family: 'Poppins', sans-serif;
      cursor: pointer; transition: background .25s, transform .15s;
    }
    .btn-submit:hover { background: #00b89c; transform: translateY(-1px); }

    /* Facebook button */
    .btn-facebook {
      display: block; width: 100%; padding: 14px; border: none;
      border-radius: 10px; background: #4267B2; color: #fff;
      font-size: 1rem; font-weight: 700; font-family: 'Poppins', sans-serif;
      cursor: pointer; text-align: center; text-decoration: none;
      margin-top: 12px; transition: background .25s;
    }
    .btn-facebook:hover { background: #365899; }

    /* Success / waiting message */
    #wifi-msg {
      display: none; text-align: center; padding: 22px 20px;
      border-radius: var(--radius); background: #fff;
      box-shadow: 0 4px 20px rgba(0,0,0,.12);
      font-size: 1.1rem; margin-top: 16px;
    }
  </style>
</head>
<body>
<div class="container">

  <!-- Logo -->
  <div class="logo-wrap">
    <?php if ($logo): ?>
      <img src="<?= BASE_URL ?>/uploads/logo/<?= e($logo) ?>" alt="<?= e($siteName) ?>">
    <?php else: ?>
      <div class="logo-fallback">OFB</div>
    <?php endif; ?>
  </div>

  <!-- Brand -->
  <div class="brand-name"><?= e($siteName) ?></div>
  <div class="subtitle">🛜 Free WiFi</div>
  <div class="tagline"><?= e($tagline) ?></div>
  <div class="phone-link">
    <a href="tel:<?= e(preg_replace('/\s+/','',$phone)) ?>">📞 Call: <?= e($phone) ?></a>
  </div>

  <!-- Banner image (from settings wifi_banner or any uploaded hero) -->
  <?php
  // Use wifi_banner setting if set, otherwise first hero slide
  $bannerSrc = '';
  if ($wifiBanner) {
    $bannerSrc = BASE_URL . '/uploads/hero/' . e($wifiBanner);
  } else {
    $heroStmt = $pdo->query("SELECT `value` FROM settings WHERE `key`='hero_slide_1' LIMIT 1");
    $heroVal  = $heroStmt->fetchColumn();
    if ($heroVal) $bannerSrc = BASE_URL . '/uploads/hero/' . e($heroVal);
  }
  if ($bannerSrc): ?>
  <div class="banner-wrap">
    <img src="<?= $bannerSrc ?>" alt="Ovijat Banner" loading="lazy">
  </div>
  <?php endif; ?>

  <!-- WiFi Credentials -->
  <div class="wifi-creds">
    <div class="wifi-row">
      <div class="wifi-label">📶 Network Name (SSID)</div>
      <div class="wifi-value"><?= e($wifiSSID) ?></div>
    </div>
    <hr style="border:none;border-top:1px solid rgba(0,0,0,.1);margin:10px 0;">
    <div class="wifi-row">
      <div class="wifi-label">🔑 Password</div>
      <div class="wifi-value"><?= e($wifiPass) ?></div>
    </div>
  </div>

  <!-- Registration Form -->
  <?php if ($wifiMsg === 'success'): ?>
    <div id="wifi-msg" style="display:block;background:#4CAF50;color:#fff;">
      🎉 সফল! Success! আপনি সফলভাবে নিবন্ধিত হয়েছেন।
    </div>
  <?php else: ?>
  <form id="wifi-form" method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="wifi_submit" value="1">
    <p class="form-note">নিরবিচ্ছিন্ন ইন্টারনেট পেতে সব গুলো ঘর অবশ্যই পূরণ করতে হবে<br>
      <em style="font-size:.75rem;color:#888;">Fill all fields for uninterrupted internet access</em>
    </p>
    <input type="email" name="email" placeholder="Email ইমেইল" required>
    <input type="text"  name="name"  placeholder="Name নাম"    required>
    <input type="text"  name="address" placeholder="Address ঠিকানা" required>
    <input type="tel"   name="phone" placeholder="Phone ফোন"   required>
    <button type="submit" class="btn-submit">🚀 Submit সাবমিট</button>
  </form>
  <?php endif; ?>

  <!-- Facebook -->
  <?php if ($facebook): ?>
  <a href="<?= e($facebook) ?>" target="_blank" rel="noopener" class="btn-facebook">
    📱 Visit Facebook Page
  </a>
  <?php endif; ?>

  <div id="wifi-msg-js"></div>

</div>

<?php if ($gsheetURL): ?>
<script>
// Also submit to Google Sheets if URL is configured
(function() {
  var form  = document.getElementById('wifi-form');
  var msgEl = document.getElementById('wifi-msg-js');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    var data = new FormData(form);
    // Add timestamp
    data.append('timedate', new Date().toISOString());

    fetch('<?= e($gsheetURL) ?>', { method: 'POST', body: data })
      .then(function(r) {
        form.style.display = 'none';
        msgEl.style.cssText = 'display:block;text-align:center;padding:20px;border-radius:16px;background:#4CAF50;color:#fff;font-size:1.2rem;margin-top:14px;font-family:Poppins,sans-serif;font-weight:700;';
        msgEl.innerHTML = r.ok ? '🎉 সফল ! Success !' : '❌ Error. Please try again.';
        if (r.ok) {
          // Restore localStorage auto-fill behaviour
          localStorage.clear();
        }
      })
      .catch(function(err) {
        msgEl.style.cssText = 'display:block;text-align:center;padding:14px;border-radius:12px;background:#f44336;color:#fff;margin-top:14px;font-family:Poppins,sans-serif;';
        msgEl.innerHTML = '❌ ' + err.message;
      });
  });

  // Restore saved field values (like original)
  ['email','name','address','phone'].forEach(function(f) {
    var el = form[f];
    if (!el) return;
    if (localStorage.getItem(f)) el.value = localStorage.getItem(f);
    el.addEventListener('input', function() { localStorage.setItem(f, el.value); });
  });
})();
</script>
<?php else: ?>
<script>
// DB-only mode — just let the PHP form POST normally
(function() {
  ['email','name','address','phone'].forEach(function(f) {
    var el = document.querySelector('[name="' + f + '"]');
    if (!el) return;
    if (localStorage.getItem('wifi_' + f)) el.value = localStorage.getItem('wifi_' + f);
    el.addEventListener('input', function() { localStorage.setItem('wifi_' + f, el.value); });
  });
})();
</script>
<?php endif; ?>

</body>
</html>
