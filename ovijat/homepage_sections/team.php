<?php // homepage_sections/team.php
$pdo = getPDO();
$team = $pdo->query(
    "SELECT * FROM team_members WHERE type='management' AND is_active=1 ORDER BY sort_order LIMIT 12"
)->fetchAll();
if (!$team) return;
?>
<section id="management-team" aria-label="Management team">
  <div class="container">
    <h2 class="section-title">Our <em style="font-style:italic;color:var(--clr-gold);">Leadership</em> Team</h2>
    <span class="title-rule" aria-hidden="true"></span>
    <p class="section-subtitle">The experienced professionals driving Ovijat's vision forward.</p>

    <div class="team-grid" role="list">
      <?php foreach ($team as $member): ?>
      <div class="team-card" role="listitem">
        <div class="team-photo-wrap">
          <?php if ($member['photo']): ?>
            <img src="<?= BASE_URL ?>/uploads/team/<?= e($member['photo']) ?>"
                 alt="<?= e($member['name']) ?>"
                 loading="lazy" width="100" height="100">
          <?php else: ?>
            <div class="team-photo-placeholder" aria-hidden="true">
              <?= strtoupper(substr($member['name'], 0, 1)) ?>
            </div>
          <?php endif; ?>
        </div>
        <h3 class="team-name"><?= e($member['name']) ?></h3>
        <p class="team-position"><?= e($member['position']) ?></p>
        <?php if ($member['bio']): ?>
          <p style="font-size:.8rem;color:var(--clr-muted);margin-top:.6rem;line-height:1.6;">
            <?= e($member['bio']) ?>
          </p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
