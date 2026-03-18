<?php
// homepage_sections/hero.php — Clean banner, no text/buttons
$pdo = getPDO();
$hs  = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('hero_slide_1','hero_slide_2','hero_slide_3')")->fetchAll();
$hs  = array_column($hs, 'value', 'key');

$slides = array_filter([
    $hs['hero_slide_1'] ?? '',
    $hs['hero_slide_2'] ?? '',
    $hs['hero_slide_3'] ?? '',
]);

// Need at least one "slide" — even if blank
if (empty($slides)) $slides = [''];
?>
<section id="hero" aria-label="Hero banner">
  <div class="swiper hero-swiper">
    <div class="swiper-wrapper">
      <?php foreach ($slides as $img): ?>
      <div class="swiper-slide">
        <div class="hero-slide">
          <?php if ($img): ?>
            <div class="hero-bg"
                 style="background-image:url('<?= BASE_URL ?>/uploads/hero/<?= e($img) ?>');"
                 aria-hidden="true"></div>
          <?php else: ?>
            <div class="hero-bg"
                 style="background:linear-gradient(160deg,#0a1f0b 0%,#1a3d1c 50%,#0d2210 100%);opacity:1;"
                 aria-hidden="true"></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if (count($slides) > 1): ?>
    <div class="hero-pagination swiper-pagination" aria-label="Slide navigation"></div>
    <?php endif; ?>
  </div>
</section>
