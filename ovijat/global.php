<?php
require_once 'config.php';
$currentPage = 'global';
$pageTitle   = 'Global Presence — Ovijat Food & Beverage Industries Ltd.';

$pdo       = getPDO();
$countries = $pdo->query("SELECT * FROM map_countries WHERE is_active=1 ORDER BY region, country")->fetchAll();
$regions   = array_unique(array_column($countries, 'region'));
sort($regions);

require_once 'includes/header.php';
?>

<!-- Page Hero -->
<section style="background:var(--clr-dark);padding:5rem 0 4rem;position:relative;overflow:hidden;">
  <div style="position:absolute;width:400px;height:400px;background:rgba(201,168,76,.08);border-radius:50%;filter:blur(80px);bottom:-100px;right:-80px;pointer-events:none;"></div>
  <div class="container" style="position:relative;z-index:1;text-align:center;">
    <p style="font-family:var(--ff-ui);font-size:.78rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--clr-gold);margin-bottom:1rem;">Worldwide</p>
    <h1 class="section-title light">Global <em style="font-style:italic;color:var(--clr-gold);">Presence</em></h1>
    <span class="title-rule" aria-hidden="true"></span>
    <p class="section-subtitle light">
      Ovijat products are trusted in <?= count($countries) ?>+ countries across <?= count($regions) ?> regions worldwide.
    </p>
  </div>
</section>

<!-- Map Section (reuse homepage section) -->
<?php require_once 'homepage_sections/map.php'; ?>

<!-- Countries List by Region -->
<section style="background:var(--clr-offwhite);padding:5rem 0;">
  <div class="container">
    <h2 class="section-title">Countries We <em style="font-style:italic;color:var(--clr-gold);">Serve</em></h2>
    <span class="title-rule" aria-hidden="true"></span>

    <?php
    // Group countries by region
    $grouped = [];
    foreach ($countries as $c) {
        $grouped[$c['region']][] = $c;
    }
    ksort($grouped);
    ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:2rem;margin-top:1.5rem;">
      <?php foreach ($grouped as $region => $clist): ?>
      <div style="background:var(--clr-white);border-radius:8px;padding:1.75rem;box-shadow:var(--shadow-sm);">
        <h3 style="font-family:var(--ff-ui);font-size:.75rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--clr-gold);margin-bottom:1.1rem;padding-bottom:.75rem;border-bottom:1px solid rgba(201,168,76,.2);">
          <?= e($region) ?> <span style="color:var(--clr-muted);font-weight:500;">(<?= count($clist) ?>)</span>
        </h3>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:.55rem;">
          <?php foreach ($clist as $country): ?>
          <li style="display:flex;align-items:center;gap:.6rem;font-family:var(--ff-body);font-size:.9rem;color:var(--clr-text);">
            <span style="width:6px;height:6px;border-radius:50%;background:var(--clr-gold);flex-shrink:0;"></span>
            <?= e($country['country']) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>

      <?php if (empty($grouped)): ?>
      <p style="color:var(--clr-muted);text-align:center;grid-column:1/-1;padding:3rem 0;">
        Country data coming soon.
      </p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section style="background:var(--clr-dark);padding:4rem 0;">
  <div class="container" style="text-align:center;">
    <h2 style="font-family:var(--ff-heading);font-size:clamp(1.5rem,3vw,2.2rem);color:var(--clr-white);margin-bottom:1rem;">
      Want to <em style="font-style:italic;color:var(--clr-gold);">Import</em> Our Products?
    </h2>
    <p style="color:rgba(247,245,240,.7);margin-bottom:2rem;max-width:520px;margin-left:auto;margin-right:auto;">
      We work with importers, distributors, and retailers worldwide. Get in touch to discuss export opportunities.
    </p>
    <a href="<?= BASE_URL ?>/contact.php" class="btn btn-primary">Start a Conversation</a>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
