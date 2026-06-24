<?php
/**
 * Wholesale Catalog - Product Grid Only
 */
global $pdo;
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY c.name ASC, p.name ASC");
$stmt->execute();
$products = $stmt->fetchAll();

$user_role = $_SESSION['user_role'] ?? 'guest';
$now = date('Y-m-d H:i:s');
$q_promos = "SELECT * FROM promotions WHERE is_active = 1 AND (start_date IS NULL OR start_date <= ?) AND (end_date IS NULL OR end_date >= ?) AND (target_wholesalers = 'all' OR target_wholesalers = ?)";
$stmt_promos = $pdo->prepare($q_promos);
$stmt_promos->execute([$now, $now, $user_role]);
$active_promotions = $stmt_promos->fetchAll();

$tiers = $pdo->query("SELECT * FROM product_price_tiers ORDER BY product_id, min_qty ASC")->fetchAll();
$product_tiers = [];
foreach ($tiers as $t) {
    $product_tiers[$t['product_id']][] = $t;
}
?>

<!-- B2B Active Promotions -->
<?php if (!empty($active_promotions)): ?>
    <div style="margin-bottom: 2rem;">
        <?php foreach ($active_promotions as $promo): ?>
            <div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(99, 102, 241, 0.15); padding: 1.25rem; border-radius: 12px; margin-bottom: 0.75rem;">
                <h4 style="margin: 0 0 0.25rem 0; color: var(--secondary); font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-bullhorn" style="color: var(--accent);"></i> <?php echo e($promo['title']); ?>
                </h4>
                <p style="margin: 0; font-size: 0.85rem; color: var(--text-main);"><?php echo nl2br(e($promo['message'])); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Catalog Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.35rem; font-weight: 800; color: var(--secondary); margin: 0;">
            <i class="fas fa-store" style="color: var(--primary);"></i> Wholesale Catalog
        </h2>
        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; background: rgba(15,23,42,0.04); padding: 0.25rem 0.75rem; border-radius: 12px;"><?php echo count($products); ?> products</span>
    </div>
    <div style="position: relative; width: 280px; max-width: 100%;">
        <i class="fas fa-search" style="position: absolute; left: 1rem; top: 0.85rem; color: var(--text-muted); font-size: 0.85rem;"></i>
        <input type="text" id="catalog-search" placeholder="Search products..." style="padding-left: 2.75rem; width: 100%; border-radius: 20px; font-size: 0.85rem; padding-top: 0.55rem; padding-bottom: 0.55rem;">
    </div>
</div>

<!-- Category Tabs -->
<div style="display: flex; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 2rem;">
    <button onclick="filterCatalog('featured')" id="tab-featured" class="btn btn-outline active-tab-btn" style="padding: 0.45rem 1.1rem; font-weight: 700; font-size: 0.8rem; border-radius: 20px; background: var(--primary); color: white; border-color: var(--primary);">
        <i class="fas fa-star" style="color: #eab308; margin-right: 3px; font-size: 0.75rem;"></i> Featured
    </button>
    <button onclick="filterCatalog('all')" id="tab-all" class="btn btn-outline" style="padding: 0.45rem 1.1rem; font-weight: 700; font-size: 0.8rem; border-radius: 20px; color: var(--text-muted); border-color: var(--glass-border); background: transparent;">
        All Products
    </button>
    <?php
    $cats = $pdo->query("SELECT DISTINCT c.id, c.name FROM categories c JOIN products p ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY c.name ASC")->fetchAll();
    foreach ($cats as $cat):
    ?>
        <button onclick="filterCatalog('cat-<?php echo $cat['id']; ?>')" id="tab-cat-<?php echo $cat['id']; ?>" class="btn btn-outline" style="padding: 0.45rem 1.1rem; font-weight: 700; font-size: 0.8rem; border-radius: 20px; color: var(--text-muted); border-color: var(--glass-border); background: transparent;">
            <?php echo e($cat['name']); ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Product Grid -->
<div class="product-grid" id="product-container">
    <?php foreach ($products as $p): ?>
        <div class="product-card" id="product-card-<?php echo $p['id']; ?>" data-name="<?php echo strtolower(e($p['name'])); ?>" data-category="<?php echo strtolower(e($p['category_name'])); ?>" data-category-id="cat-<?php echo $p['category_id']; ?>" data-featured="<?php echo $p['is_featured']; ?>">
            <div class="product-img">
                <?php if (!empty($p['main_image'])): ?>
                    <img src="<?php echo e($p['main_image']); ?>" alt="<?php echo e($p['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <img src="<?php echo $base_path; ?>public/images/default_product.png" alt="Default Product" style="width: 100%; height: 100%; object-fit: cover;">
                <?php endif; ?>
            </div>
            <div class="product-info">
                <div class="product-category"><?php echo e($p['category_name']); ?></div>
                <h3 class="product-name"><?php echo e($p['name']); ?></h3>
                <div class="product-price">
                    <?php 
                    $discount = get_product_discount($pdo, $p['id']);
                    if ($discount):
                        $discounted_price = calculate_discounted_price($p['base_price'], $discount);
                    ?>
                        <span style="color: var(--rose); font-weight: 800;">$<?php echo number_format($discounted_price, 2); ?></span>
                        <span style="font-size: 0.85rem; color: var(--text-muted); text-decoration: line-through; margin-left: 0.35rem;">$<?php echo number_format($p['base_price'], 2); ?></span>
                        <span style="background: rgba(244,63,94,0.1); color: var(--rose); padding: 0.15rem 0.4rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; margin-left: 0.35rem; display: inline-block; vertical-align: middle;">
                            -<?php echo $discount['percent'] > 0 ? (float)$discount['percent'] . '%' : '$' . number_format($discount['amount'], 2); ?>
                        </span>
                    <?php else: ?>
                        $<?php echo number_format($p['base_price'], 2); ?>
                    <?php endif; ?>
                </div>
                <div style="margin-top: auto; padding-top: 0.65rem;">
                    <!-- Meta row: Bulk badge + stock + Details -->
                    <div style="display: flex; gap: 0.35rem; align-items: center; margin-bottom: 0.55rem;">
                        <?php if (isset($product_tiers[$p['id']])): ?>
                        <span style="display: inline-flex; align-items: center; gap: 0.25rem; background: rgba(16,185,129,0.1); color: var(--primary-dark); padding: 0.15rem 0.5rem; border-radius: 6px; font-size: 0.6rem; font-weight: 800; border: 1px solid rgba(16,185,129,0.15);">
                            <i class="fas fa-tags"></i> Bulk
                        </span>
                        <span style="font-size: 0.6rem; color: var(--text-muted); font-weight: 700;"><?php echo count($product_tiers[$p['id']]); ?> tiers</span>
                        <?php endif; ?>
                        <?php if ($p['stock_qty'] > 0): ?>
                            <span style="font-size: 0.6rem; color: var(--success); font-weight: 700; display: inline-flex; align-items: center; gap: 0.25rem;">
                                <i class="fas fa-check-circle" style="font-size: 0.55rem;"></i> In Stock (<?php echo $p['stock_qty']; ?>)
                            </span>
                        <?php else: ?>
                            <span style="font-size: 0.6rem; color: var(--rose); font-weight: 700; display: inline-flex; align-items: center; gap: 0.25rem; background: rgba(244,63,94,0.08); padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid rgba(244,63,94,0.15);">
                                <i class="fas fa-times-circle" style="font-size: 0.55rem;"></i> Out of Stock
                            </span>
                        <?php endif; ?>
                        <span style="flex: 1;"></span>
                        <a href="<?php echo BASE_URL; ?>product/<?php echo $p['id']; ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; border: 1px solid var(--border); color: var(--text-muted); text-decoration: none; font-size: 0.65rem;">
                            <i class="fas fa-info-circle"></i>
                        </a>
                    </div>
                    <!-- Add to Cart / Disabled check -->
                    <?php if (is_logged_in()): ?>
                        <?php if ($p['stock_qty'] > 0): ?>
                            <form action="<?php echo BASE_URL; ?>cart" method="POST">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                <div style="display: flex; gap: 0.3rem; align-items: center;">
                                    <input type="number" name="qty" value="<?php echo $p['min_order_qty']; ?>" min="<?php echo $p['min_order_qty']; ?>" style="width: 52px; padding: 0.35rem; text-align: center; border-radius: 6px; font-size: 0.75rem;">
                                    <button type="submit" class="btn btn-green" style="flex: 1; padding: 0.4rem 0.5rem; border-radius: 8px; font-size: 0.75rem; font-weight: 800; white-space: nowrap;">
                                        <i class="fas fa-cart-plus"></i> Add
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div style="display: flex; gap: 0.3rem; align-items: center;">
                                <input type="number" disabled value="0" style="width: 52px; padding: 0.35rem; text-align: center; border-radius: 6px; font-size: 0.75rem; background: var(--bg-light); color: var(--text-muted); border: 1px solid var(--border-light);">
                                <button type="button" disabled class="btn btn-outline" style="flex: 1; padding: 0.4rem 0.5rem; border-radius: 8px; font-size: 0.75rem; font-weight: 800; white-space: nowrap; color: var(--text-muted); background: var(--bg-light); border: 1px solid var(--border-light); cursor: not-allowed;">
                                    <i class="fas fa-ban"></i> Out of Stock
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="/bolakausa/login" class="btn btn-blue" style="width: 100%; border-radius: 8px;"><i class="fas fa-sign-in-alt"></i> Login to Order</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
let currentFilter = 'featured';

function filterCatalog(filter) {
    currentFilter = filter;
    document.querySelectorAll('.category-tabs-container button, [onclick*="filterCatalog"]').forEach(btn => {
        btn.classList.remove('active-tab-btn');
        btn.style.background = 'transparent';
        btn.style.color = 'var(--text-muted)';
        btn.style.borderColor = 'var(--glass-border)';
    });
    const activeBtn = document.getElementById('tab-' + filter);
    if (activeBtn) {
        activeBtn.classList.add('active-tab-btn');
        activeBtn.style.background = 'var(--primary)';
        activeBtn.style.color = 'white';
        activeBtn.style.borderColor = 'var(--primary)';
    }
    applySearchAndFilter();
}

function applySearchAndFilter() {
    const q = (document.getElementById('catalog-search').value || '').toLowerCase().trim();
    document.querySelectorAll('.product-card').forEach(card => {
        const name = card.getAttribute('data-name');
        const cat = card.getAttribute('data-category');
        const catId = card.getAttribute('data-category-id');
        const featured = card.getAttribute('data-featured') === '1';
        let matchesFilter = currentFilter === 'all' || (currentFilter === 'featured' && featured) || currentFilter === catId;
        card.style.display = (matchesFilter && (name.includes(q) || cat.includes(q))) ? 'flex' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('catalog-search');
    if (el) el.addEventListener('input', applySearchAndFilter);
    filterCatalog('featured');
});
</script>
