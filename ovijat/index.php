<?php
// index.php
require_once 'config.php';
$currentPage = 'home';
$pageTitle   = 'Ovijat Food & Beverage Industries Ltd. — Premium Quality, Global Reach';
require_once 'includes/header.php';
?>

<?php require_once 'homepage_sections/hero.php'; ?>
<?php require_once 'homepage_sections/stats.php'; ?>
<?php require_once 'homepage_sections/about.php'; ?>
<?php require_once 'homepage_sections/products.php'; ?>
<?php require_once 'homepage_sections/chairman.php'; ?>
<?php require_once 'homepage_sections/md.php'; ?>
<?php require_once 'homepage_sections/team.php'; ?>
<?php require_once 'homepage_sections/map.php'; ?>

<!-- Contact CTA Strip -->
<section style="background:var(--clr-offwhite);padding:4rem 0;border-top:1px solid rgba(201,168,76,.2);">
  <div class="container" style="text-align:center;">
    <h2 class="section-title">Ready to <em style="font-style:italic;color:var(--clr-gold);">Partner</em> with Us?</h2>
    <span class="title-rule" aria-hidden="true"></span>
    <p class="section-subtitle">Reach out to our team for export inquiries, partnership opportunities, and product information.</p>
    <a href="<?= BASE_URL ?>/contact.php" class="btn btn-primary" style="margin-top:1rem;">Get In Touch</a>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
