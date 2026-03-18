<?php // homepage_sections/map.php
$pdo       = getPDO();
$countries = $pdo->query("SELECT * FROM map_countries WHERE is_active=1 ORDER BY region, country")->fetchAll();
$regions   = array_unique(array_column($countries, 'region'));
sort($regions);
?>
<section id="global-presence" class="section-dark" aria-label="Global presence">
  <div class="map-grid-overlay" aria-hidden="true"></div>
  <div class="container" style="position:relative;z-index:1;">
    <h2 class="section-title light">Our <em style="font-style:italic;color:var(--clr-gold);">Global</em> Presence</h2>
    <span class="title-rule" aria-hidden="true"></span>
    <p class="section-subtitle light">Ovijat products are trusted across <?= count($countries) ?> countries worldwide.</p>

    <!-- Region Filter Pills -->
    <div class="region-filters" role="group" aria-label="Filter by region">
      <button class="region-pill active" data-region="all" type="button">All Regions</button>
      <?php foreach ($regions as $region): ?>
        <button class="region-pill" data-region="<?= e($region) ?>" type="button"><?= e($region) ?></button>
      <?php endforeach; ?>
    </div>

    <!-- Map with inline SVG so no image file needed -->
    <div class="map-container" style="position:relative;max-width:900px;margin:0 auto;user-select:none;">

      <!-- Inline SVG world map -->
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 500"
           style="width:100%;display:block;opacity:.5;" aria-hidden="true">
        <rect width="1000" height="500" fill="none"/>
        <!-- North America -->
        <path d="M80,60 L200,55 L230,80 L220,120 L200,140 L190,170 L170,190 L150,200 L130,220 L110,240 L90,250 L70,240 L60,210 L50,180 L55,140 L65,110 L75,85 Z" fill="rgba(201,168,76,0.2)" stroke="rgba(201,168,76,0.55)" stroke-width="1.5"/>
        <!-- Central America -->
        <path d="M130,220 L150,215 L160,230 L155,250 L140,260 L125,255 L120,240 Z" fill="rgba(201,168,76,0.18)" stroke="rgba(201,168,76,0.4)" stroke-width="1"/>
        <!-- South America -->
        <path d="M160,270 L210,255 L240,265 L260,290 L265,330 L255,370 L240,410 L210,440 L180,450 L155,440 L140,410 L135,370 L140,330 L148,300 Z" fill="rgba(201,168,76,0.2)" stroke="rgba(201,168,76,0.55)" stroke-width="1.5"/>
        <!-- Greenland -->
        <path d="M190,20 L250,15 L265,35 L255,55 L225,60 L195,50 Z" fill="rgba(201,168,76,0.1)" stroke="rgba(201,168,76,0.3)" stroke-width="1"/>
        <!-- Europe -->
        <path d="M430,55 L480,50 L510,65 L520,85 L505,105 L490,115 L470,120 L450,115 L435,100 L425,80 Z" fill="rgba(201,168,76,0.2)" stroke="rgba(201,168,76,0.55)" stroke-width="1.5"/>
        <!-- Scandinavia -->
        <path d="M460,30 L490,25 L500,40 L488,55 L470,58 L455,48 Z" fill="rgba(201,168,76,0.18)" stroke="rgba(201,168,76,0.4)" stroke-width="1"/>
        <!-- Africa -->
        <path d="M440,140 L500,130 L540,140 L560,170 L565,210 L560,260 L545,310 L520,350 L490,370 L460,360 L435,330 L420,290 L415,250 L418,210 L425,170 Z" fill="rgba(201,168,76,0.2)" stroke="rgba(201,168,76,0.55)" stroke-width="1.5"/>
        <!-- Middle East -->
        <path d="M530,120 L580,115 L610,130 L615,160 L595,180 L565,185 L540,175 L525,155 Z" fill="rgba(201,168,76,0.18)" stroke="rgba(201,168,76,0.4)" stroke-width="1.5"/>
        <!-- Russia / Central Asia -->
        <path d="M510,40 L700,30 L760,50 L770,80 L720,100 L660,110 L600,105 L555,95 L520,80 L508,60 Z" fill="rgba(201,168,76,0.15)" stroke="rgba(201,168,76,0.4)" stroke-width="1.5"/>
        <!-- South Asia -->
        <path d="M610,140 L660,135 L680,155 L685,185 L670,215 L645,230 L620,220 L605,195 L600,165 Z" fill="rgba(201,168,76,0.28)" stroke="rgba(201,168,76,0.65)" stroke-width="1.5"/>
        <!-- Southeast Asia -->
        <path d="M700,150 L750,140 L775,160 L770,190 L745,205 L715,200 L695,180 Z" fill="rgba(201,168,76,0.18)" stroke="rgba(201,168,76,0.45)" stroke-width="1.5"/>
        <!-- China / East Asia -->
        <path d="M680,80 L780,70 L820,90 L825,130 L800,155 L760,160 L720,150 L690,135 L672,110 Z" fill="rgba(201,168,76,0.18)" stroke="rgba(201,168,76,0.45)" stroke-width="1.5"/>
        <!-- Japan -->
        <path d="M840,95 L860,88 L870,105 L858,118 L842,112 Z" fill="rgba(201,168,76,0.18)" stroke="rgba(201,168,76,0.4)" stroke-width="1"/>
        <!-- Australia -->
        <path d="M750,290 L840,280 L880,300 L890,340 L875,385 L840,410 L790,415 L750,395 L725,360 L720,320 Z" fill="rgba(201,168,76,0.2)" stroke="rgba(201,168,76,0.55)" stroke-width="1.5"/>
      </svg>

      <!-- Pulsing dots overlay -->
      <?php foreach ($countries as $c): ?>
      <div class="map-dot"
           style="left:<?= e($c['pos_x']) ?>%;top:<?= e($c['pos_y']) ?>%;"
           data-region="<?= e($c['region']) ?>"
           data-country="<?= e($c['country']) ?>"
           role="img"
           aria-label="<?= e($c['country']) ?>">
        <span class="map-tooltip"><?= e($c['country']) ?></span>
      </div>
      <?php endforeach; ?>

    </div><!-- /.map-container -->

    <p class="presence-count" style="margin-top:2rem;">
      Exporting to <strong><?= count($countries) ?>+</strong> countries across
      <strong><?= count($regions) ?></strong> regions
    </p>
  </div>
</section>
