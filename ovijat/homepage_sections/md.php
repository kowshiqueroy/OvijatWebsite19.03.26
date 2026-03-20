<?php // homepage_sections/md.php
$pdo = getPDO();
$s = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('md_message','md_name','md_title')")->fetchAll();
$s = array_column($s, 'value', 'key');

$md = $pdo->query("SELECT * FROM team_members WHERE type='md' AND is_active=1 LIMIT 1")->fetch();
?>
<section class="message-section" style="background:var(--clr-dark-deep);" aria-label="Managing Director's message">
  <div class="container" style="position:relative;z-index:1;">
    <h2 class="section-title light">MD's <em style="font-style:italic;color:var(--clr-gold);">Message</em></h2>
    <span class="title-rule" aria-hidden="true"></span>

    <div class="message-card">
      <blockquote class="message-text">
        <?= e($s['md_message'] ?? 'Innovation and integrity are the twin pillars of Ovijat.') ?>
      </blockquote>
      <div class="message-author">
        <?php if ($md && $md['photo']): ?>
          <img class="message-avatar"
               src="<?= BASE_URL ?>/uploads/team/<?= e($md['photo']) ?>"
               alt="<?= e($md['name']) ?>"
               loading="lazy" width="64" height="64">
        <?php else: ?>
          <div class="message-avatar-placeholder" aria-hidden="true">
            <?= strtoupper(substr($s['md_name'] ?? 'M', 0, 1)) ?>
          </div>
        <?php endif; ?>
        <div>
          <div class="message-name"><?= e($s['md_name'] ?? 'Managing Director') ?></div>
          <div class="message-title"><?= e($s['md_title'] ?? 'Managing Director') ?></div>
        </div>
      </div>
    </div>
  </div>
</section>
