<?php
// ============================================================
// includes/header.php — Common page header + nav
// ============================================================
$flash   = getFlash();
$user    = currentUser();
$curPage = $_GET['page'] ?? 'dashboard';

function navLink(string $page, string $icon, string $label, string $curPage): string {
    $active = ($curPage === $page) ? ' active' : '';
    return sprintf(
        '<a href="index.php?page=%s" class="nav-link%s"><span class="icon">%s</span>%s</a>',
        $page, $active, $icon, $label
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= APP_NAME ?></title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Libre+Barcode+39&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>

<!-- ── Header ──────────────────────────────────────────────── -->
<header class="app-header no-print">
  <div style="display:flex;align-items:center;gap:10px">
    <button class="nav-toggle" id="navToggle" aria-label="Menu">☰</button>
    <div class="logo">🛒 <?= APP_NAME ?></div>
  </div>
  <div class="header-actions">
    <span class="user-badge">
      
      <a href="index.php?page=pos" class="role-pill" style="color:white">P O S</a>
    </span>
    <a href="index.php?action=logout" class="btn btn-danger btn-sm">Logout</a>
  </div>
</header>

<!-- ── Overlay ─────────────────────────────────────────────── -->
<div class="nav-overlay" id="navOverlay"></div>

<!-- ── Side Navigation ─────────────────────────────────────── -->
<nav class="side-nav" id="sideNav">

  <div class="nav-section">
    <div class="nav-section-label">Main</div>
    <?= navLink('dashboard', '📊', 'Dashboard', $curPage) ?>
    <?= navLink('pos',       '🛒', 'Point of Sale', $curPage) ?>
  </div>

  <div class="nav-section">
    <div class="nav-section-label">Inventory</div>
    <?= navLink('products',   '📦', 'Products', $curPage) ?>
    <?= navLink('product_entries', '📜', 'Product History', $curPage) ?>
    
  <?php if (isAdmin()): ?>
    <?= navLink('categories', '🏷️', 'Categories', $curPage) ?>
    <?= navLink('brands',     '🏷️', 'Brands', $curPage) ?>
    <?= navLink('customers',  '👤', 'Customers', $curPage) ?>
 <?php endif ?>
        <?= navLink('inventory_report',  '📊', 'Inventory Report', $curPage) ?>
  </div>

  <div class="nav-section">
    <div class="nav-section-label">Finance</div>
    <?= navLink('sales',    '🧾', 'Sales', $curPage) ?>
    <?= navLink('finance',  '💰', 'Finance', $curPage) ?>
     <?php if (isAdmin()): ?>
    <?= navLink('reports',  '📈', 'Reports', $curPage) ?>
    <?php endif ?>

  </div>

  <?php if (isAdmin()): ?>
  <div class="nav-section">
    <div class="nav-section-label">Admin</div>
    <?= navLink('users',    '👥', 'Users',       $curPage) ?>
    <?= navLink('logs',     '📋', 'Action Logs', $curPage) ?>
    <?= navLink('settings', '⚙️', 'Settings',    $curPage) ?>
 
   
  </div>
  <?php endif ?>

  <div class="nav-section">
    <div class="nav-section-label">Tools</div>
    <?= navLink('barcodes', '🏷️', 'Print Labels', $curPage) ?>
      <?php if (isAdmin()): ?>
         <?= navLink('sms', '📱', 'SMS', $curPage) ?>
    <a href="index.php?page=backup" class="nav-link"><span class="icon">⚙️</span>Backup</a>
    <?php endif ?>
  </div>
    

   <div class="nav-section" style="display:flex;justify-content:center;align-items:center">
    <div class="nav-section-label">   <?= e($user['full_name']) ?></div>
<span class="user-badge" style="display:flex;justify-content:center;align-items:center">
   
      <span class="role-pill <?= e($user['role']) ?>"><?= e($user['role']) ?></span>
    </span>
  </div>

</nav>

<!-- ── Main ─────────────────────────────────────────────────── -->
<main class="app-main">

<?php if ($flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif ?>
