<?php
require_once 'config.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: ' . BASE_URL . '/products.php'); exit; }

$pdo  = getPDO();
$stmt = $pdo->prepare(
    "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.slug = ? AND p.is_active = 1 LIMIT 1"
);
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) { header('Location: ' . BASE_URL . '/products.php'); exit; }

// Related products
$related = $pdo->prepare(
    "SELECT p.*, c.name AS cat_name FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
     ORDER BY p.sort_order LIMIT 4"
);
$related->execute([$product['category_id'], $product['id']]);
$relatedProducts = $related->fetchAll();

$currentPage = 'products';
$pageTitle   = e($product['name']) . ' — Ovijat Food & Beverage';
require_once 'includes/header.php';
?>

<!-- Breadcrumb -->
<div style="background:var(--clr-offwhite);border-bottom:1px solid rgba(201,168,76,.15);padding:.75rem 0;">
  <div class="container">
    <nav aria-label="Breadcrumb" style="font-family:var(--ff-ui);font-size:.78rem;color:var(--clr-muted);">
      <a href="<?= BASE_URL ?>/" style="color:var(--clr-muted);">Home</a>
      <span style="margin:0 .5rem;color:var(--clr-gold);">/</span>
      <a href="<?= BASE_URL ?>/products.php" style="color:var(--clr-muted);">Products</a>
      <span style="margin:0 .5rem;color:var(--clr-gold);">/</span>
      <a href="<?= BASE_URL ?>/products.php?cat=<?= e($product['cat_slug']) ?>" style="color:var(--clr-muted);">
        <?= e($product['cat_name']) ?>
      </a>
      <span style="margin:0 .5rem;color:var(--clr-gold);">/</span>
      <span style="color:var(--clr-dark);font-weight:600;"><?= e($product['name']) ?></span>
    </nav>
  </div>
</div>

<!-- Product Detail -->
<section style="background:var(--clr-white);padding:4rem 0;">
  <div class="container">
    <div class="product-detail-grid">

      <!-- Image -->
      <div class="product-detail-img">
        <?php if ($product['image']): ?>
          <img src="<?= BASE_URL ?>/uploads/products/<?= e($product['image']) ?>"
               alt="<?= e($product['name']) ?>"
               style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <div style="text-align:center;padding:3rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" fill="none" stroke="rgba(201,168,76,.35)" stroke-width="1" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <p style="color:rgba(201,168,76,.4);font-family:var(--ff-ui);font-size:.8rem;margin-top:1rem;">No image available</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div>
        <?php if ($product['is_new']): ?>
          <span style="background:var(--clr-crimson);color:#fff;font-family:var(--ff-ui);font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.3rem .75rem;border-radius:3px;display:inline-block;margin-bottom:1rem;">New Product</span>
        <?php endif; ?>

        <p class="product-detail-cat"><?= e($product['cat_name']) ?></p>
        <h1 class="product-detail-name"><?= e($product['name']) ?></h1>

        <?php if ($product['short_desc']): ?>
          <p style="font-size:1.05rem;color:var(--clr-dark);font-weight:500;margin-bottom:1.25rem;line-height:1.7;">
            <?= e($product['short_desc']) ?>
          </p>
        <?php endif; ?>

        <?php if ($product['description']): ?>
          <div class="product-detail-desc"><?= nl2br(e($product['description'])) ?></div>
        <?php endif; ?>

        <!-- Certifications -->
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;margin:1.5rem 0;padding:1.25rem;background:var(--clr-offwhite);border-radius:8px;border-left:3px solid var(--clr-gold);">
          <?php foreach(['HACCP Certified','Halal Certified','ISO 22000','BSTI Approved'] as $cert): ?>
          <span style="font-family:var(--ff-ui);font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--clr-dark);display:flex;align-items:center;gap:.3rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" stroke="var(--clr-gold)" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <?= $cert ?>
          </span>
          <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:2rem;">
          <a href="<?= BASE_URL ?>/contact.php" class="btn btn-primary">
            Enquire Now
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          </a>
          <a href="<?= BASE_URL ?>/products.php?cat=<?= e($product['cat_slug']) ?>" class="btn btn-outline">
            ← Back to <?= e($product['cat_name']) ?>
          </a>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- Related Products -->
<?php if ($relatedProducts): ?>
<section style="background:var(--clr-offwhite);padding:4rem 0;">
  <div class="container">
    <h2 class="section-title">Related <em style="font-style:italic;color:var(--clr-gold);">Products</em></h2>
    <span class="title-rule"></span>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.5rem;margin-top:1.5rem;">
      <?php foreach ($relatedProducts as $rp): ?>
      <a href="<?= BASE_URL ?>/product.php?slug=<?= e($rp['slug']) ?>"
         style="text-decoration:none;"
         onmouseenter="this.style.transform='translateY(-5px)'"
         onmouseleave="this.style.transform=''">
        <article style="background:var(--clr-white);border-radius:8px;overflow:hidden;box-shadow:var(--shadow-sm);transition:transform .3s,box-shadow .3s;">
          <?php if ($rp['image']): ?>
            <img src="<?= BASE_URL ?>/uploads/products/<?= e($rp['image']) ?>"
                 alt="<?= e($rp['name']) ?>" loading="lazy"
                 style="width:100%;height:180px;object-fit:cover;">
          <?php else: ?>
            <div style="width:100%;height:180px;background:linear-gradient(135deg,var(--clr-dark),#2c5f2e);display:flex;align-items:center;justify-content:center;">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" stroke="rgba(201,168,76,.4)" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
          <?php endif; ?>
          <div style="padding:1rem;">
            <p style="font-family:var(--ff-ui);font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--clr-gold);margin-bottom:.3rem;"><?= e($rp['cat_name']) ?></p>
            <h3 style="font-family:var(--ff-heading);font-size:1rem;color:var(--clr-dark);"><?= e($rp['name']) ?></h3>
          </div>
        </article>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
