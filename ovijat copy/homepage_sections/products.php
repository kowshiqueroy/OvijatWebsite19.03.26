<?php // homepage_sections/products.php
$pdo = getPDO();
$products = $pdo->query(
    "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.is_active=1 AND p.is_featured=1
     ORDER BY p.sort_order
     LIMIT 8"
)->fetchAll();
if (!$products): ?>
<?php endif; ?>
<section id="products-section" class="section-offwhite" aria-label="Featured products">
  <div class="container">
    <h2 class="section-title">Our <em style="font-style:italic;color:var(--clr-gold);">Featured</em> Products</h2>
    <span class="title-rule" aria-hidden="true"></span>
    <p class="section-subtitle">Discover our range of premium quality food & beverage products crafted for every palate.</p>

    <div class="products-scroll" role="list">
      <?php foreach ($products as $p): ?>
      <a href="<?= BASE_URL ?>/product.php?slug=<?= e($p['slug']) ?>" style="text-decoration:none;">
      <article class="product-card" role="listitem">
        <?php if ($p['is_new']): ?>
          <span class="product-badge-new" aria-label="New product">New</span>
        <?php endif; ?>

        <?php if ($p['image']): ?>
          <img class="product-card-img"
               src="<?= BASE_URL ?>/uploads/products/<?= e($p['image']) ?>"
               alt="<?= e($p['name']) ?>"
               loading="lazy"
               width="260" height="210">
        <?php else: ?>
          <div class="product-card-img-placeholder" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" stroke="rgba(201,168,76,.4)" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          </div>
        <?php endif; ?>

        <div class="product-card-body">
          <p class="product-category"><?= e($p['cat_name']) ?></p>
          <h3 class="product-name"><?= e($p['name']) ?></h3>
          <?php if ($p['short_desc']): ?>
            <p class="product-desc"><?= e($p['short_desc']) ?></p>
          <?php endif; ?>
        </div>
      </article>
      </a>
      <?php endforeach; ?>

      <?php if (empty($products)): ?>
      <p style="color:var(--clr-muted);text-align:center;width:100%;padding:3rem 0;">Products coming soon. Check back shortly!</p>
      <?php endif; ?>
    </div>

    <div style="text-align:center;margin-top:3rem;">
      <a href="<?= BASE_URL ?>/products.php" class="btn btn-outline">View All Products</a>
    </div>
  </div>
</section>
