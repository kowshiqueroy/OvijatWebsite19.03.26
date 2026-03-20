<?php // homepage_sections/stats.php
$pdo = getPDO();
$stats = $pdo->query("SELECT * FROM stats WHERE is_active=1 ORDER BY sort_order")->fetchAll();
if (!$stats) return;
?>
<section id="stats-strip" aria-label="Company statistics">
  <div class="container">
    <div class="stats-grid">
      <?php foreach ($stats as $stat): ?>
      <div class="stat-item">
        <div class="stat-number"
             data-countup="<?= (int)$stat['value'] ?>"
             data-suffix="<?= e($stat['suffix']) ?>"
             aria-label="<?= e($stat['value'] . $stat['suffix'] . ' ' . $stat['label']) ?>">
          0
        </div>
        <div class="stat-label"><?= e($stat['label']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
