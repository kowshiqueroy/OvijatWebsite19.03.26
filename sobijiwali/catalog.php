<?php
/**
 * Product Catalog Page
 */
require_once 'includes/Database.php';
require_once 'includes/ProductManager.php';

$db = Database::getInstance();
$productManager = new ProductManager();

// Initial data for SEO/No-JS
$catId = $_GET['category'] ?? null;
$products = $productManager->getAllProducts($catId);
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$pageTitle = 'Our Fresh Harvest';
include 'templates/header.php';
?>

<style>
    .catalog-layout { display: grid; grid-template-columns: 280px 1fr; gap: 4rem; align-items: start; }
    .filter-sidebar { background: var(--bg-accent); padding: 2.5rem; border-radius: 30px; box-shadow: var(--card-shadow); border: 1px solid var(--border); position: sticky; top: 120px; }
    .filter-group { margin-bottom: 2.5rem; }
    .filter-title { font-weight: 800; font-size: 0.8rem; color: var(--primary); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 1.2rem; display: block; }
    
    .cat-list { display: flex; flex-direction: column; gap: 1rem; }
    .cat-link { display: flex; align-items: center; gap: 0.8rem; cursor: pointer; font-size: 0.95rem; font-weight: 700; color: var(--text); opacity: 0.7; transition: var(--transition); text-decoration: none; }
    .cat-link:hover, .cat-link.active { opacity: 1; color: var(--primary); }
    .cat-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--border); transition: var(--transition); }
    .cat-link.active .cat-dot { background: var(--primary); transform: scale(1.5); }

    /* Skeleton Loader */
    .skeleton { background: #eee; background: linear-gradient(110deg, #f0f0f0 8%, #f8f8f8 18%, #f0f0f0 33%); border-radius: 20px; background-size: 200% 100%; animation: 1.5s shine linear infinite; height: 350px; }
    @keyframes shine { to { background-position-x: -200%; } }

    @media (max-width: 992px) {
        .catalog-layout { grid-template-columns: 1fr; gap: 2rem; }
        .filter-sidebar { position: static; padding: 1.5rem; border-radius: 20px; }
        .cat-list { flex-direction: row; overflow-x: auto; padding-bottom: 10px; }
        .cat-link { white-space: nowrap; background: white; padding: 0.5rem 1.2rem; border-radius: 50px; border: 1px solid var(--border); }
        .cat-dot { display: none; }
    }
</style>

<div class="section-container">
    
    <div class="catalog-layout">
        
        <!-- Sidebar -->
        <aside>
            <div class="filter-sidebar reveal">
                <div class="filter-group">
                    <span class="filter-title">Search Harvest</span>
                    <input type="text" id="search-input" class="search-bar" placeholder="e.g. Tomato..." style="width: 100%; margin:0;" onkeyup="debounce(fetchProducts)()">
                </div>

                <div class="filter-group">
                    <span class="filter-title">Categories</span>
                    <div class="cat-list">
                        <a href="catalog" class="cat-link <?php echo !$catId ? 'active' : ''; ?>">
                            <div class="cat-dot"></div>
                            All Produce
                        </a>
                        <?php foreach ($categories as $cat): ?>
                            <a href="catalog?category=<?php echo $cat['id']; ?>" class="cat-link <?php echo $catId == $cat['id'] ? 'active' : ''; ?>">
                                <div class="cat-dot"></div>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="filter-group">
                    <span class="filter-title">Price Filter</span>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="number" id="min-price" value="0" style="width: 70px;">
                        <span style="opacity: 0.3;">—</span>
                        <input type="number" id="max-price" value="50" style="width: 70px;">
                    </div>
                    <button class="btn-add-cart" style="margin-top: 1rem; padding: 0.6rem;" onclick="fetchProducts()">Apply Filter</button>
                </div>
            </div>
        </aside>

        <!-- Main Grid -->
        <div id="catalog-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1 style="font-weight: 800; font-size: 2rem; color: var(--text);">Fresh Harvest</h1>
                <div id="results-count" style="font-size: 0.85rem; font-weight: 800; opacity: 0.4;"><?php echo count($products); ?> Items Found</div>
            </div>

            <div class="catalog-grid" id="catalog-grid">
                <?php foreach ($products as $product): ?>
                    <?php 
                        $userRole = $_SESSION['user_role'] ?? 'retail';
                        $price = $product['base_price'];
                        $isWholesale = false;
                        if ($userRole === 'wholesale' && !empty($product['wholesale_price'])) {
                            $price = $product['wholesale_price'];
                            $isWholesale = true;
                        }
                    ?>
                    <div class="product-card reveal">
                        <div class="product-image">
                            <?php if ($product['total_stock'] > 0 && $product['total_stock'] < 10): ?>
                                <div style="position:absolute; top:15px; right:15px; background:var(--error); color:white; font-size:0.6rem; font-weight:800; padding:4px 10px; border-radius:50px; z-index:10;">LIMITED</div>
                            <?php endif; ?>
                            <img src="assets/img/products/<?php echo htmlspecialchars($product['primary_image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </div>
                        <div class="product-info">
                            <a href="product/<?php echo $product['slug']; ?>" class="product-name"><?php echo htmlspecialchars($product['name']); ?></a>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.8rem;">
                                <div>
                                    <span class="product-price">$<?php echo number_format($price, 2); ?></span>
                                    <?php if ($isWholesale): ?>
                                        <div style="font-size: 0.65rem; color: var(--accent); font-weight: 800;">WHOLESALE RATE (Min: <?php echo $product['wholesale_min_qty']; ?>)</div>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-outline" style="padding: 5px 12px; border-color: var(--primary); color: var(--primary);" onclick="Cart.add(<?php echo $product['default_variation_id']; ?>)">+ Add</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<script>
    let timer;
    function debounce(func, timeout = 300) {
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => { func.apply(this, args); }, timeout);
        };
    }

    async function fetchProducts() {
        const grid = document.getElementById('catalog-grid');
        const query = document.getElementById('search-input').value;
        const min = document.getElementById('min-price').value;
        const max = document.getElementById('max-price').value;
        
        // Simple cat detection from URL for now
        const urlParams = new URLSearchParams(window.location.search);
        const cat = urlParams.get('category') || '';

        grid.innerHTML = '<div class="skeleton"></div>'.repeat(6);

        try {
            const res = await fetch(`api_search.php?q=${query}&cat=${cat}&min=${min}&max=${max}`);
            const products = await res.json();

            document.getElementById('results-count').innerText = `${products.length} Items Found`;

            if (products.length === 0) {
                grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:5rem; opacity:0.5;"><h3>No fresh produce matches your search.</h3></div>';
                return;
            }

            grid.innerHTML = products.map(p => {
                const userRole = '<?php echo $_SESSION['user_role'] ?? 'retail'; ?>';
                let price = parseFloat(p.base_price).toFixed(2);
                let wholesaleHtml = '';
                if (userRole === 'wholesale' && p.wholesale_price) {
                    price = parseFloat(p.wholesale_price).toFixed(2);
                    wholesaleHtml = `<div style="font-size: 0.65rem; color: var(--accent); font-weight: 800;">WHOLESALE RATE (Min: ${p.wholesale_min_qty})</div>`;
                }

                return `
                <div class="product-card">
                    <div class="product-image">
                        ${p.total_stock > 0 && p.total_stock < 10 ? `<div style="position:absolute; top:15px; right:15px; background:var(--error); color:white; font-size:0.6rem; font-weight:800; padding:4px 10px; border-radius:50px; z-index:10;">LIMITED</div>` : ''}
                        <img src="assets/img/products/${p.primary_image}" alt="${p.name}">
                    </div>
                    <div class="product-info">
                        <a href="product/${p.slug}" class="product-name">${p.name}</a>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.8rem;">
                            <div>
                                <span class="product-price">$${price}</span>
                                ${wholesaleHtml}
                            </div>
                            <button class="btn btn-outline" style="padding: 5px 12px; border-color: var(--primary); color: var(--primary);" onclick="Cart.add(${p.default_variation_id})">+ Add</button>
                        </div>
                    </div>
                </div>
                `;
            }).join('');

        } catch (e) {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:5rem; color:var(--error);">Connection lost. Harvesting failed.</div>';
        }
    }
</script>

<?php include 'templates/footer.php'; ?>
