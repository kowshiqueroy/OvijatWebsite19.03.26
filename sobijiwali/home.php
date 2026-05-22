<?php
/**
 * Global Home Page
 * Modern, High-End Grocery Aesthetic.
 */
require_once 'includes/Database.php';
require_once 'includes/ProductManager.php';

$db = Database::getInstance();
$pm = new ProductManager();

// Fetch Data
$banners = $db->query("SELECT * FROM hero_slides WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
$categories = $db->query("SELECT * FROM categories LIMIT 6")->fetchAll();
$freshPicks = $pm->getAllProducts();
$freshPicks = array_slice($freshPicks, 0, 4);

$pageTitle = 'Farm to Table Freshness';
include 'templates/header.php';
?>

<style>
    /* Hero Section */
    .hero-wrapper { height: calc(100vh - 110px); width: 100%; position: relative; overflow: hidden; }
    .hero-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 1s ease; display: flex; align-items: center; justify-content: center; text-align: center; }
    .hero-slide.active { opacity: 1; z-index: 10; }
    .hero-overlay { position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(0,0,0,0.1), rgba(0,0,0,0.5)); z-index: 5; }
    .hero-content { position: relative; z-index: 10; color: white; max-width: 800px; padding: 2rem; }
    .hero-content h1 { font-family: 'Inter', sans-serif; font-size: 4rem; font-weight: 800; line-height: 1.1; margin-bottom: 1.5rem; letter-spacing: -2px; }
    .hero-content p { font-size: 1.2rem; opacity: 0.9; margin-bottom: 2.5rem; }

    /* Layout Sections */
    .section-container { max-width: 1200px; margin: 0 auto; padding: 6rem 5%; }
    .section-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 3rem; }
    .section-header h2 { font-size: 2.5rem; font-weight: 800; color: var(--primary); letter-spacing: -1px; }

    /* Features */
    .split-feature { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; margin-bottom: 8rem; }
    .feature-text h3 { font-size: 2rem; font-weight: 800; margin-bottom: 1.5rem; color: var(--text); }
    .feature-text p { font-size: 1.1rem; line-height: 1.8; opacity: 0.7; margin-bottom: 2rem; }
    .feature-img { width: 100%; height: 450px; border-radius: 30px; object-fit: cover; box-shadow: var(--card-shadow); }

    @media (max-width: 768px) {
        .hero-content h1 { font-size: 2.5rem; }
        .split-feature { grid-template-columns: 1fr; gap: 2rem; text-align: center; }
        .split-feature:nth-child(even) .feature-img { order: -1; }
        .feature-img { height: 300px; }
    }
</style>

<!-- Slider Hero -->
<div class="hero-wrapper">
    <?php if (empty($banners)): ?>
        <div class="hero-slide active" style="background: url('https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1950&q=80') center/cover no-repeat;">
            <div class="hero-overlay"></div>
            <div class="hero-content reveal">
                <h1>Freshness Rooted<br>At Your Doorstep</h1>
                <p>Premium, organic vegetables harvested daily and delivered within 24 hours.</p>
                <a href="catalog" class="btn-harvest" style="padding: 1.2rem 3rem;">Shop Fresh Harvest</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($banners as $idx => $b): ?>
            <div class="hero-slide <?php echo $idx === 0 ? 'active' : ''; ?>" style="background: url('<?php echo SITE_URL; ?>/assets/img/banners/<?php echo $b['image_path']; ?>') center/cover no-repeat;">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <h1><?php echo htmlspecialchars($b['title']); ?></h1>
                    <p><?php echo htmlspecialchars($b['subtitle']); ?></p>
                    <a href="catalog" class="btn-harvest">Explore Catalog</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Main Content -->
<div class="section-container">
    
    <!-- Category Quick Grid -->
    <section>
        <h2 style="text-align: center; font-weight: 800; margin-bottom: 4rem; font-size: 2.2rem; color: var(--text);">Our Specialties</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem;">
            <?php foreach ($categories as $cat): ?>
                <a href="catalog?category=<?php echo $cat['id']; ?>" class="reveal" style="text-decoration:none; background:white; padding:2rem 1rem; border-radius:24px; text-align:center; border:1px solid var(--border); transition:var(--transition); box-shadow:var(--card-shadow);">
                    <div style="font-size: 2.5rem; margin-bottom: 1rem;">🥬</div>
                    <div style="font-weight: 800; color: var(--text); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;"><?php echo htmlspecialchars($cat['name']); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Alternating Branded Features -->
    <section style="margin-top: 8rem;">
        <div class="split-feature reveal">
            <div class="feature-text">
                <h3>Direct From<br>Local Soil</h3>
                <p>We work with family farms within 50 miles of your home. By shortening the supply chain, we deliver produce with 40% higher nutrient density than standard supermarkets.</p>
                <a href="about" style="color: var(--primary); font-weight: 800; text-decoration: none;">Our Sustainability Pact &rarr;</a>
            </div>
            <img src="https://images.unsplash.com/photo-1592419044706-39796d40f98c?auto=format&fit=crop&w=800&q=80" class="feature-img" alt="Fresh Farm">
        </div>

        <div class="split-feature reveal">
            <img src="https://images.unsplash.com/photo-1550989460-0adf9ea622e2?auto=format&fit=crop&w=800&q=80" class="feature-img" alt="Organic Care">
            <div class="feature-text">
                <h3>Grown with<br>Pure Love</h3>
                <p>No synthetic chemicals, no pesticides, no rush. Our vegetables are grown the old-fashioned way—with clean water, healthy soil, and the warm morning sun.</p>
                <a href="about" style="color: var(--primary); font-weight: 800; text-decoration: none;">View Organic Certifications &rarr;</a>
            </div>
        </div>
    </section>

    <!-- Today's Harvest -->
    <section>
        <div class="section-header">
            <h2>Today's Harvest</h2>
            <a href="catalog" style="font-weight: 800; color: var(--text-muted); text-decoration: none;">View All &rarr;</a>
        </div>
        <div class="catalog-grid">
            <?php foreach ($freshPicks as $p): ?>
                <div class="product-card reveal">
                    <div class="product-image">
                        <?php if ($p['total_stock'] > 0 && $p['total_stock'] < 10): ?>
                            <div style="position:absolute; top:15px; right:15px; background:var(--error); color:white; font-size:0.65rem; font-weight:800; padding:4px 10px; border-radius:50px; z-index:10;">LIMITED STOCK</div>
                        <?php endif; ?>
                        <img src="assets/img/products/<?php echo $p['primary_image']; ?>" alt="<?php echo $p['name']; ?>">
                    </div>
                    <div class="product-info">
                        <a href="product/<?php echo $p['slug']; ?>" class="product-name"><?php echo $p['name']; ?></a>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem;">
                            <span class="product-price">$<?php echo number_format($p['base_price'], 2); ?></span>
                            <button class="btn btn-outline" style="padding: 6px 12px; border-color: var(--primary); color: var(--primary);" onclick="Cart.add(<?php echo $p['default_variation_id']; ?>)">+ Add</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

</div>

<script>
    // Minimal Slider
    let cur = 0;
    const s = document.querySelectorAll('.hero-slide');
    if (s.length > 1) {
        setInterval(() => {
            s[cur].classList.remove('active');
            cur = (cur + 1) % s.length;
            s[cur].classList.add('active');
        }, 5000);
    }
</script>

<?php include 'templates/footer.php'; ?>
