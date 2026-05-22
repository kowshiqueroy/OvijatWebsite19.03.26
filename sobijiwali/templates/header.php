<?php
/**
 * Global Customer Header
 * High-performance, Fresh Organic theme with Full Responsiveness.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/AuthManager.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();

// Fetch Site Settings
$rawSettings = $db->query("SELECT * FROM site_settings")->fetchAll();
$s = [];
foreach ($rawSettings as $rs) $s[$rs['setting_key']] = $rs['setting_value'];

$isLoggedIn = AuthManager::isLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME; ?></title>
    
    <?php if ($s['favicon'] ?? ''): ?>
        <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/img/site/<?php echo $s['favicon']; ?>">
    <?php endif; ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;600;700&family=Inter:wght@400;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2D5A27; 
            --primary-light: #4A8B42;
            --accent: #F59E0B; 
            --bg: #FAFAF5; 
            --bg-accent: #FFFFFF;
            --text: #1A3015;
            --text-muted: #6B7280;
            --white: #ffffff;
            --border: #E5E7EB;
            --error: #EF4444;
            --card-shadow: 0 10px 40px rgba(0,0,0,0.03);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Quicksand', sans-serif; background-color: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; -webkit-font-smoothing: antialiased; padding-top: 110px; }
        
        #preloader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg); z-index: 9999; display: flex; align-items: center; justify-content: center; transition: opacity 0.5s; }
        .loader-logo { font-size: 3rem; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { transform: scale(0.9); opacity: 0.5; } 100% { transform: scale(1.1); opacity: 1; } }

        .top-bar { background: var(--primary); color: white; padding: 0.5rem 5%; display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; font-weight: 700; z-index: 2001; position: fixed; top: 0; width: 100%; height: 35px; transition: opacity 0.3s, transform 0.3s; }
        .top-info { display: flex; gap: 1.5rem; }
        .top-social { display: flex; gap: 1rem; }
        .top-social a { color: white; text-decoration: none; opacity: 0.8; transition: opacity 0.2s; }
        .top-social a:hover { opacity: 1; }

        header { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); padding: 0 5%; display: flex; justify-content: space-between; align-items: center; z-index: 2000; position: fixed; top: 35px; width: 100%; height: 75px; transition: var(--transition); }
        .logo { text-decoration: none; display: flex; align-items: center; gap: 0.8rem; }
        .logo img { height: 40px; width: auto; }
        .logo-text { font-size: 1.4rem; font-weight: 800; color: var(--primary); letter-spacing: -1px; }

        .catalog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 2rem; margin-top: 2rem; }
        .product-card { background: var(--bg-accent); border-radius: 20px; overflow: hidden; border: 1px solid var(--border); transition: var(--transition); display: flex; flex-direction: column; position: relative; }
        .product-card:hover { transform: translateY(-5px); box-shadow: var(--card-shadow); }
        .product-image { height: 200px; background: #f3f4f6; position: relative; overflow: hidden; }
        .product-image img { width: 100%; height: 100%; object-fit: cover; }
        .product-info { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; }
        .product-name { font-size: 1rem; font-weight: 800; text-decoration: none; margin-bottom: 0.5rem; color: var(--text); }
        .product-price { font-size: 1.1rem; font-weight: 800; color: var(--primary); }

        .header-search { position: relative; flex: 1; max-width: 400px; margin: 0 2rem; }
        .header-search input { width: 100%; padding: 0.6rem 1.2rem; border-radius: 50px; border: 1px solid var(--border); background: #f9fafb; font-family: inherit; font-size: 0.85rem; outline: none; transition: var(--transition); }
        #search-results { position: absolute; top: 100%; left: 0; width: 100%; background: var(--white); border-radius: 15px; margin-top: 0.5rem; box-shadow: var(--card-shadow); display: none; overflow: hidden; border: 1px solid var(--border); }

        nav { display: flex; gap: 1.5rem; align-items: center; }
        nav a { text-decoration: none; color: var(--text); font-weight: 700; font-size: 0.85rem; transition: var(--transition); }
        nav a:hover { color: var(--primary); }
        .nav-icons { display: flex; gap: 1rem; align-items: center; }
        .icon-btn { position: relative; font-size: 1.3rem; color: var(--text); text-decoration: none; transition: var(--transition); background: none; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; }
        .icon-btn:hover { color: var(--primary); transform: translateY(-2px); background: rgba(0,0,0,0.02); }
        
        /* Account Icon Coloring */
        .icon-btn.login-state { color: <?php echo $isLoggedIn ? 'var(--primary)' : 'var(--accent)'; ?>; }
        .icon-btn.login-state:hover { color: <?php echo $isLoggedIn ? 'var(--primary-light)' : '#d97706'; ?>; }

        .icon-badge { position: absolute; top: 0; right: 0; background: var(--accent); color: var(--white); font-size: 0.65rem; padding: 2px 6px; border-radius: 50px; font-weight: 800; border: 2px solid var(--white); }

        .mobile-bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; background: var(--white); display: none; justify-content: space-around; padding: 0.8rem; border-top: 1px solid var(--border); z-index: 2000; box-shadow: 0 -5px 20px rgba(0,0,0,0.05); }
        .mobile-bottom-nav a { text-decoration: none; color: var(--text-muted); display: flex; flex-direction: column; align-items: center; gap: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
        .mobile-bottom-nav a.active { color: var(--primary); }
        .mobile-bottom-nav span { font-size: 1.4rem; }

        @media (max-width: 992px) { .header-search { display: none; } }
        @media (max-width: 768px) {
            .top-bar { display: none; }
            header { top: 0; height: 70px; }
            body { padding-top: 70px; padding-bottom: 80px; }
            nav { display: none; }
            .mobile-bottom-nav { display: flex; }
            .mobile-only { display: block !important; }
        }

        #toast-container { position: fixed; bottom: 90px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast { background: var(--white); color: var(--text); padding: 1rem 1.5rem; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 1rem; transform: translateX(120%); transition: transform 0.4s; border-left: 5px solid var(--primary); font-weight: 600; }
        .toast.show { transform: translateX(0); }
        
        #cart-popup-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.05); z-index: 10000; display: none; align-items: flex-start; justify-content: flex-end; padding: 90px 5% 20px; opacity: 0; transition: var(--transition); pointer-events: none; }
        #cart-popup { background: var(--white); width: 350px; padding: 2rem; border-radius: 24px; box-shadow: 0 20px 80px rgba(0,0,0,0.1); transform: translateX(50px); transition: var(--transition); border: 1px solid var(--border); pointer-events: auto; }
        #cart-popup-overlay.show { display: flex; opacity: 1; }
        #cart-popup-overlay.show #cart-popup { transform: translateX(0); }
        .btn-mini-cart { display: block; width: 100%; padding: 1rem; background: var(--primary); color: white; border-radius: 14px; text-decoration: none; font-weight: 800; text-align: center; margin-top: 1rem; transition: var(--transition); }

        /* Global Form Field Overhaul */
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 800; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.6rem; }
        
        input[type="text"], input[type="email"], input[type="password"], input[type="tel"], input[type="number"], select, textarea {
            width: 100%; padding: 1rem 1.2rem; border-radius: 15px; border: 2px solid var(--border); background: var(--white); font-family: inherit; font-size: 0.95rem; font-weight: 600; color: var(--text); outline: none; transition: var(--transition);
        }
        
        input:focus, select:focus, textarea:focus { border-color: var(--primary); background: var(--bg); box-shadow: 0 0 0 4px rgba(45, 90, 39, 0.05); }
        
        input::placeholder, textarea::placeholder { opacity: 0.4; font-weight: 500; }
        
        select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%232D5A27'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1.2rem center; background-size: 1.2rem; padding-right: 3rem; }

        input[type="checkbox"], input[type="radio"] { width: 20px; height: 20px; accent-color: var(--primary); cursor: pointer; }
    </style>
</head>
<body>
    <div id="preloader">
        <div class="loader-logo">
            <?php if (!empty($s['preloader_image'])): ?>
                <img src="<?php echo SITE_URL; ?>/assets/img/site/<?php echo $s['preloader_image']; ?>">
            <?php else: ?>
                <?php echo $s['preloader_logo'] ?? '🌿'; ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($s['festival_text'])): ?>
            <div class="loader-text"><?php echo htmlspecialchars($s['festival_text']); ?></div>
        <?php endif; ?>
    </div>

    <!-- Top Bar -->
    <div class="top-bar" id="top-bar">
        <div class="top-info">
            <span>📍 <?php echo htmlspecialchars($s['store_address'] ?? 'Farm to Table'); ?></span>
            <span>📞 <?php echo htmlspecialchars($s['contact_phone'] ?? ''); ?></span>
        </div>
        <div class="top-social">
            <?php if (!empty($s['facebook_url'])): ?><a href="<?php echo $s['facebook_url']; ?>"><span>📘</span> FB</a><?php endif; ?>
            <?php if (!empty($s['twitter_url'])): ?><a href="<?php echo $s['twitter_url']; ?>"><span>🐦</span> TW</a><?php endif; ?>
            <?php if (!empty($s['instagram_url'])): ?><a href="<?php echo $s['instagram_url']; ?>"><span>📸</span> IG</a><?php endif; ?>
        </div>
    </div>

    <!-- Header -->
    <header id="main-header">
        <a href="<?php echo SITE_URL; ?>" class="logo">
            <?php if ($s['site_logo'] ?? ''): ?>
                <img src="<?php echo SITE_URL; ?>/assets/img/site/<?php echo $s['site_logo']; ?>" alt="Sobjiwali">
            <?php else: ?>
                <span style="font-size: 1.8rem;">🥕</span>
            <?php endif; ?>
            <span class="logo-text">SOBJIWALI</span>
        </a>

        <div class="header-search">
            <input type="text" placeholder="Search vegetables..." onkeyup="handleGlobalSearch(this.value)">
            <div id="search-results"></div>
        </div>

        <nav>
            <a href="<?php echo SITE_URL; ?>/catalog">Shop</a>
            <?php 
            $headerPages = $db->query("SELECT title, slug FROM static_pages WHERE location IN ('header', 'both')")->fetchAll();
            foreach ($headerPages as $hp):
            ?>
                <a href="<?php echo SITE_URL; ?>/<?php echo $hp['slug']; ?>"><?php echo htmlspecialchars($hp['title']); ?></a>
            <?php endforeach; ?>
            <div class="nav-icons">
                <?php if (AuthManager::hasRole(['admin', 'editor', 'warehouse', 'reports'])): ?>
                    <a href="<?php echo SITE_URL; ?>/admin_stealth_zone/dashboard.php" class="icon-btn" title="Admin Dashboard" style="color: var(--primary);">⚙️</a>
                <?php endif; ?>

                <a href="<?php echo SITE_URL; ?>/cart" class="icon-btn" title="Basket">
                    🛒 <span id="cart-badge" class="icon-badge">0</span>
                </a>
                
                <?php if ($isLoggedIn): ?>
                    <a href="<?php echo SITE_URL; ?>/account" class="icon-btn login-state" title="My Account">👤</a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/login" class="icon-btn login-state" title="Login">👤</a>
                <?php endif; ?>

                <button class="icon-btn mobile-only" onclick="toggleMenu()" style="display:none;">☰</button>
            </div>
        </nav>
    </header>

    <!-- Mobile Menu Drawer -->
    <div id="mobile-drawer" style="position:fixed; top:0; left:-100%; width:85%; height:100%; background:var(--white); z-index:3000; transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 20px 0 60px rgba(0,0,0,0.1); padding: 2.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3rem;">
            <span class="logo-text" style="font-size:1.2rem;">MENU</span>
            <button onclick="toggleMenu()" style="background:none; border:none; font-size:2rem; cursor:pointer; color:var(--text);">×</button>
        </div>
        <nav style="display:flex; flex-direction:column; gap:1.8rem;">
            <a href="<?php echo SITE_URL; ?>/catalog" style="font-size:1.1rem; text-decoration:none; color:var(--text); font-weight:700;">Shop Produce</a>
            <a href="<?php echo SITE_URL; ?>/about" style="font-size:1.1rem; text-decoration:none; color:var(--text); font-weight:700;">Our Story</a>
            <a href="<?php echo SITE_URL; ?>/wholesale" style="font-size:1.1rem; text-decoration:none; color:var(--text); font-weight:700;">Wholesale</a>
            <a href="<?php echo SITE_URL; ?>/contact" style="font-size:1.1rem; text-decoration:none; color:var(--text); font-weight:700;">Contact</a>
            <hr style="border:none; border-top:1px solid var(--border);">
            <?php if ($isLoggedIn): ?>
                <a href="<?php echo SITE_URL; ?>/account" style="font-size:1.1rem; text-decoration:none; color:var(--text); font-weight:700;">👤 My Account</a>
                <a href="<?php echo SITE_URL; ?>/logout" style="font-size:1.1rem; text-decoration:none; color:var(--error); font-weight:700;">🚪 Sign Out</a>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/login" style="font-size:1.1rem; text-decoration:none; color:var(--text); font-weight:700;">🔑 Login / Register</a>
            <?php endif; ?>
        </nav>
    </div>
    <div id="drawer-overlay" onclick="toggleMenu()" style="position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:2999; display:none; opacity:0; transition:opacity 0.4s;"></div>

    <!-- Mobile Bottom Nav -->
    <div class="mobile-bottom-nav">
        <a href="<?php echo SITE_URL; ?>" class="active"><span>🏠</span>Home</a>
        <a href="<?php echo SITE_URL; ?>/catalog"><span>🥗</span>Shop</a>
        <a href="<?php echo SITE_URL; ?>/cart"><span>🛒</span>Cart</a>
        <?php if ($isLoggedIn): ?>
            <a href="<?php echo SITE_URL; ?>/account"><span>👤</span>Account</a>
        <?php else: ?>
            <a href="<?php echo SITE_URL; ?>/login"><span>👤</span>Login</a>
        <?php endif; ?>
    </div>

    <!-- Cart Popup -->
    <div id="cart-popup-overlay" onclick="if(event.target === this) Cart.hidePopup()">
        <div id="cart-popup">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h3 style="font-weight:800; font-size:1.1rem; color:var(--primary);">BASKET UPDATED</h3>
                <button onclick="Cart.hidePopup()" style="background:none; border:none; color:var(--text); font-size:1.2rem; cursor:pointer;">×</button>
            </div>
            <div id="popup-items-list" style="margin-bottom: 1.5rem;"></div>
            <a href="<?php echo SITE_URL; ?>/cart" class="btn-mini-cart">View Basket & Checkout</a>
        </div>
    </div>

    <div id="toast-container"></div>

    <script>
        // Header Scroll Fade Effect
        window.addEventListener('scroll', () => {
            const topBar = document.getElementById('top-bar');
            const header = document.getElementById('main-header');
            if (window.scrollY > 50) {
                topBar.style.opacity = '0';
                topBar.style.transform = 'translateY(-100%)';
                header.style.top = '0';
            } else {
                topBar.style.opacity = '1';
                topBar.style.transform = 'translateY(0)';
                header.style.top = '35px';
            }
        });

        function toggleMenu() {
            const drawer = document.getElementById('mobile-drawer');
            const overlay = document.getElementById('drawer-overlay');
            const isOpen = drawer.style.left === '0px';
            if (isOpen) {
                drawer.style.left = '-100%'; overlay.style.opacity = '0';
                setTimeout(() => overlay.style.display = 'none', 400);
            } else {
                overlay.style.display = 'block';
                setTimeout(() => { drawer.style.left = '0'; overlay.style.opacity = '1'; }, 10);
            }
        }

        const Toast = {
            show(message, type = 'success') {
                const container = document.getElementById('toast-container');
                const toast = document.createElement('div');
                toast.className = 'toast';
                if(type === 'error') toast.style.borderLeftColor = 'var(--error)';
                toast.innerHTML = `<div>${type === 'success' ? '✓' : '!'}</div> <div>${message}</div>`;
                container.appendChild(toast);
                setTimeout(() => toast.classList.add('show'), 10);
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 400);
                }, 4000);
            }
        };

        const Cart = {
            get() { return JSON.parse(localStorage.getItem('sobji_cart') || '[]'); },
            save(items) { localStorage.setItem('sobji_cart', JSON.stringify(items)); this.updateBadge(); },
            add(variationId, quantity = 1) {
                let items = this.get();
                let existing = items.find(i => i.variation_id === variationId);
                if (existing) existing.quantity += parseInt(quantity);
                else items.push({ variation_id: variationId, quantity: parseInt(quantity) });
                this.save(items);
                this.showPopup();
            },
            updateBadge() {
                const count = this.get().reduce((sum, item) => sum + item.quantity, 0);
                document.getElementById('cart-badge').textContent = count;
            },
            async showPopup() {
                const overlay = document.getElementById('cart-popup-overlay');
                const list = document.getElementById('popup-items-list');
                const items = this.get();
                overlay.classList.add('show');
                try {
                    const res = await fetch(`<?php echo SITE_URL; ?>/api_cart.php`, { method: 'POST', body: JSON.stringify(items) });
                    const data = await res.json();
                    list.innerHTML = data.items.slice(0, 3).map(i => `
                        <div style="display:flex; gap:1rem; align-items:center; margin-bottom:1rem;">
                            <div style="width:35px; height:35px; background:#f3f4f6; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:1.1rem;">📦</div>
                            <div style="flex:1;">
                                <div style="font-weight:700; font-size:0.8rem;">${i.name}</div>
                                <div style="font-size:0.7rem; opacity:0.6;">Qty: ${i.quantity}</div>
                            </div>
                        </div>
                    `).join('');
                } catch(e) {}
                setTimeout(() => this.hidePopup(), 6000);
            },
            hidePopup() { document.getElementById('cart-popup-overlay').classList.remove('show'); }
        };

        async function handleGlobalSearch(query) {
            const resultsBox = document.getElementById('search-results');
            if (query.length < 2) { resultsBox.style.display = 'none'; return; }
            try {
                const response = await fetch(`<?php echo SITE_URL; ?>/api_search.php?q=${query}`);
                const products = await response.json();
                if (products.length > 0) {
                    resultsBox.innerHTML = products.slice(0, 5).map(p => `
                        <a href="<?php echo SITE_URL; ?>/product/${p.slug}" style="display:flex; align-items:center; gap:1rem; padding:0.8rem; text-decoration:none; color:var(--text); border-bottom:1px solid #f3f4f6;">
                            <div style="font-weight:700; font-size:0.85rem;">${p.name}</div>
                        </a>
                    `).join('');
                    resultsBox.style.display = 'block';
                } else { resultsBox.style.display = 'none'; }
            } catch (e) {}
        }

        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const pre = document.getElementById('preloader');
                if(pre) { pre.style.opacity = '0'; setTimeout(() => pre.remove(), 500); }
            }, 800);
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('active'); });
            }, { threshold: 0.1 });
            document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
            Cart.updateBadge();
        });
    </script>
    <main>
