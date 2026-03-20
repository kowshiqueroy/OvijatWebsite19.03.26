<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/upload_helper.php';
require_once dirname(__DIR__) . '/layout.php';

$pdo        = getPDO();
$uploadLogo = dirname(__DIR__, 2) . '/uploads/logo/';
$uploadHero = dirname(__DIR__, 2) . '/uploads/hero/';
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $textFields = [
        'site_name','site_tagline','contact_email','contact_phone','contact_address',
        'facebook_url','linkedin_url','youtube_url','about_short',
        'chairman_message','chairman_name','chairman_title',
        'md_message','md_name','md_title',
        'draw_title','draw_description','draw_prize','draw_end_date','draw_gsheet_url',
        'wifi_ssid','wifi_password','wifi_tagline','wifi_gsheet_url',
    ];

    $stmt = $pdo->prepare(
        "INSERT INTO settings (`key`,`value`) VALUES (?,?)
         ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
    );

    foreach ($textFields as $key) {
        $stmt->execute([$key, trim($_POST[$key] ?? '')]);
    }

    // Logo 2 upload
    if (!empty($_FILES['site_logo_2']['name'])) {
        $up2 = uploadImage($_FILES['site_logo_2'], $uploadLogo, 400, 90);
        if (!$up2['success']) {
            $err .= ' Logo 2: ' . $up2['error'];
        } else {
            $old2 = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='site_logo_2'");
            $old2->execute();
            $oldVal2 = $old2->fetchColumn();
            if ($oldVal2) deleteUpload($uploadLogo, $oldVal2);
            $stmt->execute(['site_logo_2', $up2['filename']]);
        }
    }

    // WiFi banner upload
    if (!empty($_FILES['wifi_banner']['name'])) {
        $upW = uploadImage($_FILES['wifi_banner'], $uploadHero, 1200, 85);
        if (!$upW['success']) {
            $err .= ' WiFi Banner: ' . $upW['error'];
        } else {
            $oldW = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='wifi_banner'");
            $oldW->execute();
            $oldWVal = $oldW->fetchColumn();
            if ($oldWVal) deleteUpload($uploadHero, $oldWVal);
            $stmt->execute(['wifi_banner', $upW['filename']]);
        }
    }

    // About image upload
    if (!empty($_FILES['about_image']['name'])) {
        $uploadAbout = dirname(__DIR__, 2) . '/uploads/about/';
        if (!is_dir($uploadAbout)) mkdir($uploadAbout, 0755, true);
        $upA = uploadImage($_FILES['about_image'], $uploadAbout, 1200, 85);
        if (!$upA['success']) {
            $err .= ' About image: ' . $upA['error'];
        } else {
            $oldA = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='about_image'");
            $oldA->execute();
            $oldValA = $oldA->fetchColumn();
            if ($oldValA) deleteUpload($uploadAbout, $oldValA);
            $stmt->execute(['about_image', $upA['filename']]);
        }
    }

    // Logo 1 upload
    if (!empty($_FILES['site_logo']['name'])) {
        $up = uploadImage($_FILES['site_logo'], $uploadLogo, 400, 90);
        if (!$up['success']) {
            $err = 'Logo: ' . $up['error'];
        } else {
            // Delete old logo
            $old = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='site_logo'");
            $old->execute();
            $oldVal = $old->fetchColumn();
            if ($oldVal) deleteUpload($uploadLogo, $oldVal);
            $stmt->execute(['site_logo', $up['filename']]);
        }
    }

    // Hero slide images (up to 3)
    for ($i = 1; $i <= 3; $i++) {
        $field = "hero_slide_{$i}";
        if (!empty($_FILES[$field]['name'])) {
            $up = uploadImage($_FILES[$field], $uploadHero, 1920, 85);
            if (!$up['success']) {
                $err .= " Slide {$i}: " . $up['error'];
            } else {
                $old = $pdo->prepare("SELECT `value` FROM settings WHERE `key`=?");
                $old->execute([$field]);
                $oldVal = $old->fetchColumn();
                if ($oldVal) deleteUpload($uploadHero, $oldVal);
                $stmt->execute([$field, $up['filename']]);
            }
        }
        // Delete hero slide
        if (!empty($_POST["delete_{$field}"])) {
            $old = $pdo->prepare("SELECT `value` FROM settings WHERE `key`=?");
            $old->execute([$field]);
            $oldVal = $old->fetchColumn();
            if ($oldVal) deleteUpload($uploadHero, $oldVal);
            $pdo->prepare("DELETE FROM settings WHERE `key`=?")->execute([$field]);
        }
    }

    if (!$err) $msg = 'Settings saved successfully.';
}

// Reload settings
$all = $pdo->query("SELECT `key`,`value` FROM settings")->fetchAll();
$s   = array_column($all, 'value', 'key');

adminOpen('Site Settings', 'settings');
?>

<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data" novalidate>
  <?= csrfField() ?>
  <div style="display:flex;flex-direction:column;gap:2rem;">

    <!-- ── GENERAL ── -->
    <div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;">
      <h3 style="font-family:var(--ff-heading);font-size:1.2rem;color:var(--clr-dark);margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--clr-gold);">
        General
      </h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
        <div class="form-group">
          <label class="form-label">Site Name</label>
          <input class="form-control" name="site_name" value="<?= e($s['site_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Site Tagline</label>
          <input class="form-control" name="site_tagline" value="<?= e($s['site_tagline'] ?? '') ?>">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">About Us (short paragraph shown on homepage)</label>
          <textarea class="form-control" name="about_short" rows="3"><?= e($s['about_short'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- ── LOGO ── -->
    <div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;">
      <h3 style="font-family:var(--ff-heading);font-size:1.2rem;color:var(--clr-dark);margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--clr-gold);">
        Site Logo
      </h3>
      <div style="display:flex;align-items:center;gap:2rem;flex-wrap:wrap;">
        <?php if (!empty($s['site_logo'])): ?>
          <div style="border:2px solid var(--clr-gold);border-radius:8px;padding:.75rem;background:var(--clr-dark);">
            <img src="<?= BASE_URL ?>/uploads/logo/<?= e($s['site_logo']) ?>"
                 alt="Current logo" style="height:70px;max-width:200px;object-fit:contain;">
          </div>
          <p style="font-size:.82rem;color:var(--clr-muted);">Current logo. Upload a new one to replace it.</p>
        <?php else: ?>
          <div style="width:140px;height:70px;background:var(--clr-dark);border-radius:8px;border:2px dashed rgba(201,168,76,.4);display:flex;align-items:center;justify-content:center;">
            <span style="font-family:var(--ff-ui);font-weight:800;color:var(--clr-gold);font-size:1.1rem;">OFB</span>
          </div>
          <p style="font-size:.82rem;color:var(--clr-muted);">No logo uploaded yet. Uploading a logo will replace the text fallback.</p>
        <?php endif; ?>
      </div>
      <div class="form-group" style="margin-top:1.25rem;">
        <label class="form-label">Upload New Logo (PNG/WebP with transparent background recommended, max 2MB)</label>
        <input class="form-control" type="file" name="site_logo" accept=".jpg,.jpeg,.png,.webp">
      </div>
    </div>

    <!-- ── LOGO 2 ── -->
    <div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;">
      <h3 style="font-family:var(--ff-heading);font-size:1.2rem;color:var(--clr-dark);margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--clr-gold);">
        Second Logo (optional — alternates with Logo 1)
      </h3>
      <div style="display:flex;align-items:center;gap:2rem;flex-wrap:wrap;">
        <?php if (!empty($s['site_logo_2'])): ?>
          <div style="border:2px solid var(--clr-gold);border-radius:8px;padding:.75rem;background:var(--clr-dark);">
            <img src="<?= BASE_URL ?>/uploads/logo/<?= e($s['site_logo_2']) ?>"
                 alt="Logo 2" style="height:70px;max-width:200px;object-fit:contain;">
          </div>
        <?php else: ?>
          <p style="font-size:.82rem;color:var(--clr-muted);">No second logo uploaded. Upload one to enable the slide-between-logos effect in the navbar.</p>
        <?php endif; ?>
      </div>
      <div class="form-group" style="margin-top:1.25rem;">
        <label class="form-label">Upload Second Logo (PNG/WebP recommended, max 2MB)</label>
        <input class="form-control" type="file" name="site_logo_2" accept=".jpg,.jpeg,.png,.webp">
      </div>
    </div>

    <!-- ── ABOUT IMAGE ── -->
    <div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;">
      <h3 style="font-family:var(--ff-heading);font-size:1.2rem;color:var(--clr-dark);margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--clr-gold);">
        About Page — Factory / Facility Image
      </h3>
      <?php if (!empty($s['about_image'])): ?>
        <div style="margin-bottom:1rem;">
          <img src="<?= BASE_URL ?>/uploads/about/<?= e($s['about_image']) ?>"
               alt="About image" style="max-height:160px;border-radius:6px;object-fit:cover;">
        </div>
      <?php else: ?>
        <p style="font-size:.82rem;color:var(--clr-muted);margin-bottom:1rem;">No image uploaded yet. This appears on the About page.</p>
      <?php endif; ?>
      <div class="form-group">
        <label class="form-label">Upload Facility / About Image (JPG/PNG/WebP, max 2MB, recommended 1200×800px)</label>
        <input class="form-control" type="file" name="about_image" accept=".jpg,.jpeg,.png,.webp">
      </div>
    </div>

    <!-- ── HERO SLIDES ── -->
    <div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;">
      <h3 style="font-family:var(--ff-heading);font-size:1.2rem;color:var(--clr-dark);margin-bottom:.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--clr-gold);">
        Hero / Banner Slides
      </h3>
      <p style="font-size:.82rem;color:var(--clr-muted);margin-bottom:1.5rem;">
        Upload up to 3 banner images for the homepage hero slider. Recommended size: 1920×900px. Images are auto-compressed.
      </p>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.5rem;">
        <?php for ($i = 1; $i <= 3; $i++):
          $key = "hero_slide_{$i}";
          $current = $s[$key] ?? '';
        ?>
        <div style="border:1px solid rgba(0,0,0,.1);border-radius:8px;overflow:hidden;">
          <!-- Preview -->
          <div style="height:140px;background:var(--clr-dark);display:flex;align-items:center;justify-content:center;position:relative;">
            <?php if ($current): ?>
              <img src="<?= BASE_URL ?>/uploads/hero/<?= e($current) ?>"
                   alt="Slide <?= $i ?>" style="width:100%;height:140px;object-fit:cover;">
              <div style="position:absolute;top:8px;right:8px;">
                <label style="background:rgba(192,21,15,.9);color:#fff;font-size:.7rem;padding:.3rem .6rem;border-radius:4px;cursor:pointer;font-family:var(--ff-ui);font-weight:700;display:flex;align-items:center;gap:.3rem;">
                  <input type="checkbox" name="delete_<?= $key ?>" value="1" style="margin:0;"> Remove
                </label>
              </div>
            <?php else: ?>
              <div style="text-align:center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" stroke="rgba(201,168,76,.4)" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <p style="font-size:.72rem;color:rgba(247,245,240,.4);margin-top:.5rem;font-family:var(--ff-ui);">No image</p>
              </div>
            <?php endif; ?>
          </div>
          <!-- Upload -->
          <div style="padding:1rem;">
            <label class="form-label">Slide <?= $i ?></label>
            <input class="form-control" type="file" name="<?= $key ?>" accept=".jpg,.jpeg,.png,.webp" style="font-size:.8rem;">
          </div>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- ── CONTACT ── -->
    <div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;">
      <h3 style="font-family:var(--ff-heading);font-size:1.2rem;color:var(--clr-dark);margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--clr-gold);">
        Contact Information
      </h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="contact_email" value="<?= e($s['contact_email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input class="form-control" name="contact_phone" value="<?= e($s['contact_phone'] ?? '') ?>">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Address</label>
          <input class="form-control" name="contact_address" value="<?= e($s['contact_address'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- ── SOCIAL ── -->
    <div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;">
      <h3 style="font-family:var(--ff-heading);font-size:1.2rem;color:var(--clr-dark);margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--clr-gold);">
        Social Media Links
      </h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
        <div class="form-group">
          <label class="form-label">Facebook URL</label>
          <input class="form-control" name="facebook_url" placeholder="https://facebook.com/..." value="<?= e($s['facebook_url'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">LinkedIn URL</label>
          <input class="form-control" name="linkedin_url" placeholder="https://linkedin.com/..." value="<?= e($s['linkedin_url'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">YouTube URL</label>
          <input class="form-control" name="youtube_url" placeholder="https://youtube.com/..." value="<?= e($s['youtube_url'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- ── CHAIRMAN ── -->
    <div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;">
      <h3 style="font-family:var(--ff-heading);font-size:1.2rem;color:var(--clr-dark);margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--clr-gold);">
        Chairman's Message
      </h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
        <div class="form-group">
          <label class="form-label">Chairman Name</label>
          <input class="form-control" name="chairman_name" value="<?= e($s['chairman_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Title / Designation</label>
          <input class="form-control" name="chairman_title" value="<?= e($s['chairman_title'] ?? '') ?>">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Message Text</label>
          <textarea class="form-control" name="chairman_message" rows="4"><?= e($s['chairman_message'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- ── MD ── -->
    <div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;">
      <h3 style="font-family:var(--ff-heading);font-size:1.2rem;color:var(--clr-dark);margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--clr-gold);">
        Managing Director's Message
      </h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
        <div class="form-group">
          <label class="form-label">MD Name</label>
          <input class="form-control" name="md_name" value="<?= e($s['md_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Title / Designation</label>
          <input class="form-control" name="md_title" value="<?= e($s['md_title'] ?? '') ?>">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Message Text</label>
          <textarea class="form-control" name="md_message" rows="4"><?= e($s['md_message'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

  <!-- ── FREE WIFI ── -->
    <div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;">
      <h3 style="font-family:var(--ff-heading);font-size:1.2rem;color:var(--clr-dark);margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--clr-gold);">
        Free WiFi Portal
        <small style="font-size:.7rem;font-weight:400;font-family:var(--ff-ui);color:var(--clr-muted);margin-left:.5rem;">( /freewifi/ )</small>
      </h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
        <div class="form-group">
          <label class="form-label">WiFi Network Name (SSID)</label>
          <input class="form-control" name="wifi_ssid" value="<?= e($s['wifi_ssid'] ?? 'Ovijat_WiFi_Free') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">WiFi Password</label>
          <input class="form-control" name="wifi_password" value="<?= e($s['wifi_password'] ?? 'ovijat2025') ?>">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">WiFi Page Tagline</label>
          <input class="form-control" name="wifi_tagline" value="<?= e($s['wifi_tagline'] ?? 'Ovijat Food - Samsul Haque Auto Rice Mills - বক মার্কা চাল') ?>"
                 placeholder="Shown below the site name on WiFi page">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">WiFi Page Banner Image</label>
          <?php if (!empty($s['wifi_banner'])): ?>
            <div style="margin-bottom:.75rem;">
              <img src="<?= BASE_URL ?>/uploads/hero/<?= e($s['wifi_banner']) ?>"
                   alt="WiFi Banner" style="max-height:100px;border-radius:6px;object-fit:cover;">
            </div>
          <?php endif; ?>
          <input class="form-control" type="file" name="wifi_banner" accept=".jpg,.jpeg,.png,.webp"
                 style="margin-bottom:.4rem;">
          <p style="font-size:.72rem;color:var(--clr-muted);">Shown on the WiFi portal page. If empty, first hero slide is used.</p>
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Google Sheets URL for WiFi Registrations <small style="font-weight:400;color:var(--clr-muted)">(optional — also saves to DB)</small></label>
          <input class="form-control" name="wifi_gsheet_url" value="<?= e($s['wifi_gsheet_url'] ?? '') ?>"
                 placeholder="https://script.google.com/macros/s/...">
          <p style="font-size:.72rem;color:var(--clr-muted);margin-top:.35rem;">Leave blank to save registrations to DB only.</p>
        </div>
      </div>
    </div>

  <!-- ── LUCKY DRAW ── -->
    <div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;">
      <h3 style="font-family:var(--ff-heading);font-size:1.2rem;color:var(--clr-dark);margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--clr-gold);">
        Lucky Draw Settings
        <small style="font-size:.7rem;font-weight:400;font-family:var(--ff-ui);color:var(--clr-muted);margin-left:.5rem;">
          ( /draw/index.php )
        </small>
      </h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
        <div class="form-group">
          <label class="form-label">Draw Title / Main Announcement Message</label>
          <input class="form-control" name="draw_title" value="<?= e($s['draw_title'] ?? 'Lucky') ?>" placeholder="Lucky">
        </div>
        <div class="form-group">
          <label class="form-label">Prize / Contact Info for Prize Winners</label>
          <input class="form-control" name="draw_prize" value="<?= e($s['draw_prize'] ?? '') ?>" placeholder="Amazing Prizes">
        </div>
        <div class="form-group">
          <label class="form-label">Winner Announcement Date</label>
          <input class="form-control" type="date" name="draw_end_date" value="<?= e($s['draw_end_date'] ?? '') ?>">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Last Month's Winners List <small style="font-weight:400;color:var(--clr-muted)">(leave empty to hide the winners panel)</small></label>
          <textarea class="form-control" name="draw_description" rows="4" placeholder="e.g.&#10;1. Abdul Karim - Dhaka&#10;2. Fatema Begum - Chittagong"><?= e($s['draw_description'] ?? '') ?></textarea>
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Google Sheets URL for Draw Entries <small style="font-weight:400;color:var(--clr-muted)">(optional — also saves to DB)</small></label>
          <input class="form-control" name="draw_gsheet_url" value="<?= e($s['draw_gsheet_url'] ?? '') ?>"
                 placeholder="https://script.google.com/macros/s/...">
        </div>
      </div>
    </div>

  </div><!-- /flex column -->

  <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid rgba(0,0,0,.08);">
    <button type="submit" class="btn btn-primary">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
      Save All Settings
    </button>
  </div>
</form>

<?php adminClose(); ?>
