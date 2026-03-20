<?php
// includes/header.php
if (!defined('BASE_URL')) require_once dirname(__DIR__) . '/config.php';

$pdo = getPDO();

// Settings
$stmtS = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN
    ('site_name','facebook_url','linkedin_url','youtube_url','site_logo','site_logo_2',
     'contact_email','contact_phone')");
$settings = array_column($stmtS->fetchAll(), 'value', 'key');
$siteName = $settings['site_name']   ?? 'Ovijat Food & Beverage';
$logo1    = $settings['site_logo']   ?? '';
$logo2    = $settings['site_logo_2'] ?? '';
$fb       = $settings['facebook_url']  ?? '';
$li       = $settings['linkedin_url']  ?? '';
$yt       = $settings['youtube_url']   ?? '';

// Top-bar contact: use contact_info show_header=1, fallback to settings
$headerContacts = $pdo->query(
    "SELECT phone, email, whatsapp FROM contact_info
     WHERE is_active=1 AND show_header=1 ORDER BY sort_order LIMIT 3"
)->fetchAll();

// Build flat phone/email lists for top bar
$topPhones = $topEmails = [];
if ($headerContacts) {
    foreach ($headerContacts as $hc) {
        if ($hc['phone']    && !in_array($hc['phone'],    $topPhones)) $topPhones[] = $hc['phone'];
        if ($hc['email']    && !in_array($hc['email'],    $topEmails)) $topEmails[] = $hc['email'];
    }
} else {
    // Fallback
    if ($settings['contact_phone'] ?? '') $topPhones[] = $settings['contact_phone'];
    if ($settings['contact_email'] ?? '') $topEmails[] = $settings['contact_email'];
}

// Categories for dropdown
$cats = $pdo->query(
    "SELECT name, slug FROM categories WHERE is_active=1 ORDER BY sort_order"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($siteName) ?> — Premium food and beverage products from Bangladesh to the world.">
  <title><?= e($pageTitle ?? $siteName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/uploads/logo/ovijatlogo.ico">
</head>
<body>

<!-- ===================== LOADING SCREEN ===================== -->
<div id="loading-screen" role="status" aria-label="Loading">
  <?php if ($logo1): ?>
  <div class="loading-logo">
    <img src="<?= BASE_URL ?>/uploads/logo/<?= e($logo1) ?>" alt="<?= e($siteName) ?>">
  </div>
  <?php endif; ?>
  <div class="loading-brand">
    OVIJAT <span style="color:var(--clr-gold);">GROUP</span>
    <small>Food &amp; Beverage Industries Ltd.</small>
  </div>
  <div class="loading-tagline" id="typed-target"></div>
  <div class="loading-bar"><div class="loading-bar-inner"></div></div>
</div>

<!-- ===================== TOP BAR ===================== -->
<div id="top-bar" role="complementary" aria-label="Contact information">
  <div class="container">
    <div class="top-bar-inner">

      <!-- Phone + Email -->
      <div class="top-bar-info">
        <?php foreach ($topPhones as $ph): ?>
        <a href="tel:<?= e(preg_replace('/\s+/', '', $ph)) ?>">
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-15.74-15.74A2 2 0 0 1 5.09 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L9.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <?= e($ph) ?>
        </a>
        <?php endforeach; ?>
        <?php foreach ($topEmails as $em): ?>
        <a href="mailto:<?= e($em) ?>">
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>
          <?= e($em) ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Socials -->
      <div class="top-bar-social">
        <?php if ($fb): ?>
          <a href="<?= e($fb) ?>" target="_blank" rel="noopener" aria-label="Facebook">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
          </a>
        <?php endif; ?>
        <?php if ($li): ?>
          <a href="<?= e($li) ?>" target="_blank" rel="noopener" aria-label="LinkedIn">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7H10V9h4v2a6 6 0 0 1 6-3zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
          </a>
        <?php endif; ?>
        <?php if ($yt): ?>
          <a href="<?= e($yt) ?>" target="_blank" rel="noopener" aria-label="YouTube">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.95C5.12 20 12 20 12 20s6.88 0 8.59-.47a2.78 2.78 0 0 0 1.96-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58zM9.75 15.02V8.98L15.5 12l-5.75 3.02z"/></svg>
          </a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<!-- ===================== NAVBAR ===================== -->
<nav id="main-nav" role="navigation" aria-label="Main navigation">
  <div class="container">
    <div class="nav-inner">

      <!-- Logo -->
      <a href="<?= BASE_URL ?>/" class="nav-logo" aria-label="<?= e($siteName) ?> Homepage">

        <?php if ($logo1 || $logo2): ?>
          <div class="logo-img-wrap" id="logo-img-wrap">
            <?php if ($logo1): ?>
              <img src="<?= BASE_URL ?>/uploads/logo/<?= e($logo1) ?>"
                   alt="<?= e($siteName) ?>" class="logo-active" id="logo-img-1">
            <?php endif; ?>
            <?php if ($logo2): ?>
              <img src="<?= BASE_URL ?>/uploads/logo/<?= e($logo2) ?>"
                   alt="<?= e($siteName) ?> alternate" id="logo-img-2">
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="logo-mark" aria-hidden="true"><span>OFB</span></div>
        <?php endif; ?>

        <!-- Brand text — always visible -->
        <div class="logo-text-wrap">
          <span class="logo-brand-name">OVIJAT</span>
          <div class="logo-subtitle" id="logo-subtitle">
            <span class="sub-active" id="sub-a">Food &amp; Beverage Industries Ltd.</span>
            <span class="sub-enter"  id="sub-b">Group</span>
          </div>
        </div>

      </a>

      <!-- Desktop Nav -->
      <ul class="nav-links" role="menubar">
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/" class="<?= ($currentPage??'')==='home'?'active':'' ?>">Home</a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/about.php" class="<?= ($currentPage??'')==='about'?'active':'' ?>">About</a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/products.php"
             class="<?= ($currentPage??'')==='products'?'active':'' ?>"
             aria-haspopup="true">
            Products <span class="nav-arrow" aria-hidden="true"></span>
          </a>
          <?php if ($cats): ?>
          <div class="nav-dropdown" role="menu">
            <a href="<?= BASE_URL ?>/products.php" role="menuitem">All Products</a>
            <?php foreach ($cats as $cat): ?>
              <a href="<?= BASE_URL ?>/products.php?cat=<?= e($cat['slug']) ?>" role="menuitem">
                <?= e($cat['name']) ?>
              </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/global.php" class="<?= ($currentPage??'')==='global'?'active':'' ?>">Global Presence</a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/contact.php" class="<?= ($currentPage??'')==='contact'?'active':'' ?>">Contact</a>
        </li>
      </ul>

      <!-- Hamburger -->
      <button class="hamburger" id="hamburger-btn"
              aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-menu">
        <span></span><span></span><span></span>
      </button>

    </div>
  </div>
</nav>

<!-- ===================== MOBILE MENU ===================== -->
<div id="mobile-menu" role="dialog" aria-label="Mobile navigation" aria-hidden="true">
  <div class="mobile-menu-header">
    <a href="<?= BASE_URL ?>/" class="mobile-menu-brand">
      <?php if ($logo1): ?>
        <img src="<?= BASE_URL ?>/uploads/logo/<?= e($logo1) ?>" alt="<?= e($siteName) ?>" class="mobile-menu-logo">
      <?php else: ?>
        <div class="mobile-menu-logo-placeholder">OFB</div>
      <?php endif; ?>
      <div class="mobile-menu-brand-text">
        <span class="mobile-brand-name">OVIJAT</span>
        <small class="mobile-brand-tagline">Food &amp; Beverage Industries Ltd.</small>
      </div>
    </a>
    <button class="mobile-menu-close" onclick="closeMobileMenu()" aria-label="Close menu">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>

  <div class="mobile-menu-content">
    <nav class="mobile-nav-links">
      <a href="<?= BASE_URL ?>/" class="mobile-nav-link <?= ($currentPage??'')==='home'?'active':'' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Home
      </a>
      <a href="<?= BASE_URL ?>/about.php" class="mobile-nav-link <?= ($currentPage??'')==='about'?'active':'' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        About
      </a>
      <div class="mobile-nav-item has-submenu <?= ($currentPage??'')==='products'?'active':'' ?>">
        <a href="<?= BASE_URL ?>/products.php" class="mobile-nav-link <?= ($currentPage??'')==='products'?'active':'' ?>">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
          Products
        </a>
        <button class="mobile-submenu-toggle" aria-expanded="false" aria-label="Toggle categories">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
      </div>
      <div class="mobile-submenu">
        <a href="<?= BASE_URL ?>/products.php" class="mobile-submenu-link">All Products</a>
        <?php if ($cats): foreach ($cats as $cat): ?>
          <a href="<?= BASE_URL ?>/products.php?cat=<?= e($cat['slug']) ?>" class="mobile-submenu-link">
            <?= e($cat['name']) ?>
          </a>
        <?php endforeach; endif; ?>
      </div>
      <a href="<?= BASE_URL ?>/global.php" class="mobile-nav-link <?= ($currentPage??'')==='global'?'active':'' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
        Global Presence
      </a>
      <a href="<?= BASE_URL ?>/contact.php" class="mobile-nav-link <?= ($currentPage??'')==='contact'?'active':'' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        Contact
      </a>
    </nav>
  </div>

  <div class="mobile-menu-footer">
    <div class="mobile-social-links">
      <?php if ($fb): ?>
        <a href="<?= e($fb) ?>" target="_blank" rel="noopener" class="mobile-social-link" aria-label="Facebook">
          <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
        </a>
      <?php endif; ?>
      <?php if ($li): ?>
        <a href="<?= e($li) ?>" target="_blank" rel="noopener" class="mobile-social-link" aria-label="LinkedIn">
          <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4V9h4v2a6 6 0 0 1 6-3z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
        </a>
      <?php endif; ?>
      <?php if ($yt): ?>
        <a href="<?= e($yt) ?>" target="_blank" rel="noopener" class="mobile-social-link" aria-label="YouTube">
          <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.95C5.12 20 12 20 12 20s6.88 0 8.59-.47a2.78 2.78 0 0 0 1.96-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="var(--clr-dark)"/></svg>
        </a>
      <?php endif; ?>
    </div>
    <p class="mobile-copyright">&copy; <?= date('Y') ?> Ovijat Group. All rights reserved.</p>
  </div>
</div>
