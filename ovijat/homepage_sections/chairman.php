<?php // homepage_sections/chairman.php
$pdo = getPDO();
$s = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('chairman_message','chairman_name','chairman_title')")->fetchAll();
$s = array_column($s, 'value', 'key');

$chair = $pdo->query("SELECT * FROM team_members WHERE type='chairman' AND is_active=1 LIMIT 1")->fetch();
?>
<section class="message-section" aria-label="Chairman's message">
  <div class="container" style="position:relative;z-index:1;">
    <h2 class="section-title light">Chairman's <em style="font-style:italic;color:var(--clr-gold);">Message</em></h2>
    <span class="title-rule" aria-hidden="true"></span>

    <div class="message-card">
      <blockquote class="message-text">
        <?= e($s['chairman_message'] ?? 'Our commitment to quality is unwavering. Every product carries our legacy of excellence.') ?>
      </blockquote>
      <div class="message-author">
        <?php if ($chair && $chair['photo']): ?>
          <img class="message-avatar"
               src="<?= BASE_URL ?>/uploads/team/<?= e($chair['photo']) ?>"
               alt="<?= e($chair['name']) ?>"
               loading="lazy" width="64" height="64">
        <?php else: ?>
          <div class="message-avatar-placeholder" aria-hidden="true">
            <?= strtoupper(substr($s['chairman_name'] ?? 'C', 0, 1)) ?>
          </div>
        <?php endif; ?>
        <div>
          <div class="message-name"><?= e($s['chairman_name'] ?? 'Chairman') ?></div>
          <div class="message-title"><?= e($s['chairman_title'] ?? 'Chairman & Founder') ?></div>
        </div>
      </div>
    </div>
  </div>
</section>
