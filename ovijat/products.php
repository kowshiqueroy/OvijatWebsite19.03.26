<?php
require_once 'config.php';
$currentPage = 'products';
$pageTitle   = 'Our Products — Ovijat Food & Beverage Industries Ltd.';

$pdo = getPDO();

// Fetch categories
$cats = $pdo->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();

// Active category filter
$activeSlug = trim($_GET['cat'] ?? '');
$activeCat  = null;
if ($activeSlug) {
    foreach ($cats as $c) {
        if ($c['slug'] === $activeSlug) { $activeCat = $c; break; }
    }
}

// Fetch products
if ($activeCat) {
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
         FROM products p JOIN categories c ON c.id=p.category_id
         WHERE p.is_active=1 AND p.category_id=?
         ORDER BY p.sort_order, p.id DESC"
    );
    $stmt->execute([$activeCat['id']]);
} else {
    $stmt = $pdo->query(
        "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
         FROM products p JOIN categories c ON c.id=p.category_id
         WHERE p.is_active=1
         ORDER BY p.sort_order, p.id DESC"
    );
}
$products = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<!-- Page Hero -->
<section style="background:var(--clr-dark);padding:5rem 0 4rem;position:relative;overflow:hidden;">
  <div style="position:absolute;width:500px;height:500px;background:rgba(192,21,15,.1);border-radius:50%;filter:blur(100px);top:-150px;left:-100px;pointer-events:none;"></div>
  <div class="container" style="position:relative;z-index:1;text-align:center;">
    <p style="font-family:var(--ff-ui);font-size:.78rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--clr-gold);margin-bottom:1rem;">What We Make</p>
    <h1 class="section-title light">Our <em style="font-style:italic;color:var(--clr-gold);">Products</em></h1>
    <span class="title-rule" aria-hidden="true"></span>
    <p class="section-subtitle light">Premium quality food & beverage products crafted for every palate.</p>
  </div>
</section>

<!-- Products -->
<section style="background:var(--clr-offwhite);padding:4rem 0 5rem;">
  <div class="container">

    <!-- Category Filter Pills -->
    <?php if ($cats): ?>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center;margin-bottom:3rem;" role="group" aria-label="Filter by category">
      <a href="<?= BASE_URL ?>/products.php"
         class="region-pill <?= !$activeCat ? 'active' : '' ?>"
         style="text-decoration:none;">All Products</a>
      <?php foreach ($cats as $c): ?>
        <a href="<?= BASE_URL ?>/products.php?cat=<?= e($c['slug']) ?>"
           class="region-pill <?= ($activeCat && $activeCat['id'] === $c['id']) ? 'active' : '' ?>"
           style="text-decoration:none;"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Category heading if filtered -->
    <?php if ($activeCat): ?>
    <div style="margin-bottom:2rem;text-align:center;">
      <h2 style="font-family:var(--ff-heading);font-size:1.8rem;color:var(--clr-dark);">
        <?= e($activeCat['name']) ?>
      </h2>
      <?php if ($activeCat['description']): ?>
        <p style="color:var(--clr-muted);margin-top:.5rem;"><?= e($activeCat['description']) ?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Products Grid -->
    <?php if ($products): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.75rem;">
      <?php foreach ($products as $p): ?>
      <a href="<?= BASE_URL ?>/product.php?slug=<?= e($p['slug']) ?>" style="text-decoration:none;display:block;">
      <article style="background:var(--clr-white);border-radius:8px;overflow:hidden;box-shadow:var(--shadow-sm);transition:transform .3s,box-shadow .3s;position:relative;cursor:pointer;"
               onmouseenter="this.style.transform='translateY(-6px)';this.style.boxShadow='var(--shadow-lg)'"
               onmouseleave="this.style.transform='';this.style.boxShadow='var(--shadow-sm)'">

        <?php if ($p['is_new']): ?>
          <span style="position:absolute;top:12px;left:12px;background:var(--clr-crimson);color:#fff;font-family:var(--ff-ui);font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.25rem .6rem;border-radius:3px;z-index:1;">New</span>
        <?php endif; ?>

        <?php if ($p['image']): ?>
          <img src="<?= BASE_URL ?>/uploads/products/<?= e($p['image']) ?>"
               alt="<?= e($p['name']) ?>"
               loading="lazy" width="280" height="210"
               style="width:100%;height:210px;object-fit:cover;">
        <?php else: ?>
          <div style="width:100%;height:210px;background:linear-gradient(135deg,var(--clr-dark),#2c5f2e);display:flex;align-items:center;justify-content:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" stroke="rgba(201,168,76,.4)" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          </div>
        <?php endif; ?>

        <div style="padding:1.25rem;">
          <p style="font-family:var(--ff-ui);font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--clr-gold);margin-bottom:.4rem;">
            <?= e($p['cat_name']) ?>
          </p>
          <h3 style="font-family:var(--ff-heading);font-size:1.15rem;font-weight:700;color:var(--clr-dark);margin-bottom:.5rem;">
            <?= e($p['name']) ?>
          </h3>
          <?php if ($p['short_desc']): ?>
            <p style="font-size:.875rem;color:var(--clr-muted);line-height:1.6;"><?= e($p['short_desc']) ?></p>
          <?php endif; ?>
        </div>
      </article>
      </a>
      <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div style="text-align:center;padding:5rem 0;">
      <p style="color:var(--clr-muted);font-size:1.1rem;">
        <?= $activeCat ? 'No products in this category yet.' : 'No products available yet. Check back soon!' ?>
      </p>
      <?php if ($activeCat): ?>
        <a href="<?= BASE_URL ?>/products.php" class="btn btn-outline" style="margin-top:1.5rem;">View All Products</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- CTA -->
<section style="background:var(--clr-dark);padding:4rem 0;">
  <div class="container" style="text-align:center;">
    <h2 style="font-family:var(--ff-heading);font-size:clamp(1.5rem,3vw,2.2rem);color:var(--clr-white);margin-bottom:1rem;">
      Interested in <em style="font-style:italic;color:var(--clr-gold);">Bulk Orders</em> or Export?
    </h2>
    <p style="color:rgba(247,245,240,.7);margin-bottom:2rem;max-width:500px;margin-left:auto;margin-right:auto;">
      Contact our sales team for wholesale pricing, export documentation, and partnership opportunities.
    </p>
    <a href="<?= BASE_URL ?>/contact.php" class="btn btn-primary">Contact Our Team</a>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
