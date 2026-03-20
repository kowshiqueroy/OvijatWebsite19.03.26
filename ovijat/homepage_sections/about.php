<?php // homepage_sections/about.php
$pdo = getPDO();
$s   = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('about_short','site_name','about_image')")->fetchAll();
$s   = array_column($s, 'value', 'key');
?>
<section id="about-snapshot" aria-label="About Ovijat">
  <div class="container">
    <div class="about-grid">
      <div class="about-img-wrap">
        <?php if (!empty($s['about_image'])): ?>
          <img src="<?= BASE_URL ?>/uploads/about/<?= e($s['about_image']) ?>"
               alt="Ovijat manufacturing facility" loading="lazy"
               style="width:100%;height:500px;object-fit:cover;border-radius:6px;">
        <?php else: ?>
          <div style="width:100%;height:500px;border-radius:6px;background:linear-gradient(135deg,var(--clr-dark) 0%,#2c5f2e 60%,#1a5c1d 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="70" height="70" fill="none" stroke="rgba(201,168,76,.3)" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <p style="font-family:var(--ff-ui);font-size:.72rem;color:rgba(201,168,76,.45);letter-spacing:.12em;text-transform:uppercase;">Upload via Admin → Settings</p>
          </div>
        <?php endif; ?>
      </div>
      <div class="about-content">
        <h2 class="section-title" style="text-align:left;">
          A Legacy of<br><em style="font-style:italic;color:var(--clr-gold);">Quality &amp; Trust</em>
        </h2>
        <span class="title-rule" style="margin-left:0;" aria-hidden="true"></span>
        <p><?= e($s['about_short'] ?? 'Ovijat Food & Beverage Industries Ltd. has been delivering premium quality food products since 2005.') ?></p>
        <p>Backed by rigorous quality control, HACCP-certified facilities, and a passionate team, we ensure every product meets the highest international standards.</p>
        <div class="about-badges">
          <?php foreach(['HACCP Certified','ISO 22000','Halal Certified','Export Ready'] as $b): ?>
          <span class="about-badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
            <?= $b ?>
          </span>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:2rem;">
          <a href="<?= BASE_URL ?>/about.php" class="btn btn-outline">Discover Our Story</a>
        </div>
      </div>
    </div>
  </div>
</section>
