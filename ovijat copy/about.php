<?php
require_once 'config.php';
$currentPage = 'about';
$pageTitle   = 'About Us — Ovijat Food & Beverage Industries Ltd.';

$pdo = getPDO();
$s   = $pdo->query("SELECT `key`,`value` FROM settings")->fetchAll();
$s   = array_column($s, 'value', 'key');

require_once 'includes/header.php';
?>

<!-- Page Hero -->
<section style="background:var(--clr-dark);padding:5rem 0 4rem;position:relative;overflow:hidden;">
  <div style="position:absolute;width:400px;height:400px;background:rgba(201,168,76,.07);border-radius:50%;filter:blur(80px);top:-100px;right:-80px;pointer-events:none;"></div>
  <div class="container" style="position:relative;z-index:1;text-align:center;">
    <p style="font-family:var(--ff-ui);font-size:.78rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--clr-gold);margin-bottom:1rem;">Who We Are</p>
    <h1 class="section-title light">About <em style="font-style:italic;color:var(--clr-gold);">Ovijat</em></h1>
    <span class="title-rule" aria-hidden="true"></span>
    <p class="section-subtitle light">A story of passion, quality, and commitment to nourishing communities.</p>
  </div>
</section>

<!-- Our Story -->
<section style="background:var(--clr-white);padding:5rem 0;">
  <div class="container">
    <div class="about-grid">
      <div class="about-img-wrap">
        <?php
        $aboutImg = getPDO()->prepare("SELECT `value` FROM settings WHERE `key`='about_image'");
        $aboutImg->execute();
        $aboutImgVal = $aboutImg->fetchColumn();
        if ($aboutImgVal): ?>
          <img src="<?= BASE_URL ?>/uploads/about/<?= e($aboutImgVal) ?>"
               alt="Ovijat manufacturing facility"
               loading="lazy" width="600" height="500"
               style="width:100%;height:500px;object-fit:cover;border-radius:6px;">
        <?php else: ?>
          <div style="width:100%;height:500px;border-radius:6px;background:linear-gradient(135deg,var(--clr-dark) 0%,#2c5f2e 50%,#1a5c1d 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" fill="none" stroke="rgba(201,168,76,.35)" stroke-width="1" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <p style="font-family:var(--ff-ui);font-size:.8rem;color:rgba(201,168,76,.5);letter-spacing:.1em;text-transform:uppercase;">Upload in Admin → Settings</p>
          </div>
        <?php endif; ?>
      </div>
      <div class="about-content">
        <h2 class="section-title" style="text-align:left;">Our <em style="font-style:italic;color:var(--clr-gold);">Story</em></h2>
        <span class="title-rule" style="margin-left:0;" aria-hidden="true"></span>
        <p><?= e($s['about_short'] ?? 'Ovijat Food & Beverage Industries Ltd. has been delivering premium quality food products across Bangladesh and beyond since 2005.') ?></p>
        <p>From humble beginnings, we have grown into one of Bangladesh's most trusted food manufacturers — exporting to over 28 countries and maintaining the highest standards of hygiene, taste, and nutritional value.</p>
        <p>Our state-of-the-art HACCP-certified facility, combined with a passionate team, ensures every product that reaches your table meets international quality benchmarks.</p>
        <div class="about-badges" style="margin-top:1.75rem;">
          <?php foreach(['HACCP Certified','ISO 22000','Halal Certified','Export Ready','BSTI Approved'] as $badge): ?>
          <span class="about-badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
            <?= e($badge) ?>
          </span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Mission / Vision / Values -->
<section style="background:var(--clr-offwhite);padding:5rem 0;">
  <div class="container">
    <h2 class="section-title"><em style="font-style:italic;color:var(--clr-gold);">Mission, Vision &amp; Values</em></h2>
    <span class="title-rule" aria-hidden="true"></span>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:2rem;margin-top:1rem;">
      <?php
      $mvv = [
        ['Mission',  '#C0150F', 'To deliver premium quality, affordable, and nutritious food & beverage products to consumers across Bangladesh and the globe, while maintaining the highest standards of food safety and hygiene.', 'M'],
        ['Vision',   '#C9A84C', 'To become one of South Asia\'s most respected food and beverage brands — recognized for innovation, integrity, and unwavering commitment to quality.', 'V'],
        ['Values',   '#1a3d1c', 'Quality without compromise. Transparency in every process. Respect for people, communities, and the environment. Innovation driven by consumer needs.', 'Va'],
      ];
      foreach ($mvv as [$title, $color, $text, $initial]):
      ?>
      <div style="background:var(--clr-white);border-radius:8px;padding:2.25rem;box-shadow:var(--shadow-sm);border-top:4px solid <?= $color ?>;">
        <div style="width:52px;height:52px;border-radius:50%;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;margin-bottom:1.25rem;">
          <span style="font-family:var(--ff-ui);font-weight:800;font-size:1.1rem;color:#fff;">
           <?php if ($initial == 'M'): ?>
  <!-- Map Pin -->
  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
       fill="none" stroke="currentColor" stroke-width="2"
       stroke-linecap="round" stroke-linejoin="round"
       class="feather feather-map-pin">
    <path d="M21 10c0 5.5-9 13-9 13s-9-7.5-9-13a9 9 0 1 1 18 0z"></path>
    <circle cx="12" cy="10" r="3"></circle>
  </svg>
<?php elseif ($initial == 'V'): ?>
  <!-- Eye -->
  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
       fill="none" stroke="currentColor" stroke-width="2"
       stroke-linecap="round" stroke-linejoin="round"
       class="feather feather-eye">
    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
    <circle cx="12" cy="12" r="3"></circle>
  </svg>
<?php elseif ($initial == 'Va'): ?>
  <!-- Heart -->
  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
       fill="none" stroke="currentColor" stroke-width="2"
       stroke-linecap="round" stroke-linejoin="round"
       class="feather feather-heart">
    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"></path>
  </svg>
<?php endif; ?>
          </span>
        </div>
        <h3 style="font-family:var(--ff-heading);font-size:1.3rem;color:var(--clr-dark);margin-bottom:.75rem;"><?= $title ?></h3>
        <p style="line-height:1.8;font-size:.93rem;"><?= e($text) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Chairman Message -->
<?php require_once 'homepage_sections/chairman.php'; ?>

<!-- MD Message -->
<?php require_once 'homepage_sections/md.php'; ?>

<!-- Management Team -->
<?php require_once 'homepage_sections/team.php'; ?>

<!-- Stats -->
<?php require_once 'homepage_sections/stats.php'; ?>

<?php require_once 'includes/footer.php'; ?>
