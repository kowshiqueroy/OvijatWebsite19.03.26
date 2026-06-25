<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit; }
if ($_SESSION['role'] != 3)       { header("Location: ../" . $_SESSION['role']); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403); die('<div style="padding:40px;font-family:sans-serif;color:#dc2626">Invalid or expired request token. <a href="javascript:history.back()">Go back</a>.</div>');
}

/* Company name */
$company_name = APP_NAME;
$stmt = $conn->prepare("SELECT name FROM companies WHERE id = ?");
$stmt->bind_param("i", $_SESSION['company_id']);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
if ($r) $company_name = htmlspecialchars($r['name']);
$stmt->close();

$role_label = 'Sales Rep';
$current    = basename($_SERVER['PHP_SELF']);
$pageTitle  = $pageTitle ?? APP_NAME;

$menuItems = [
    ['icon'=>'fa-house',              'label'=>'Dashboard',   'link'=>'index.php'],
    ['section'=>'Sales'],
    ['icon'=>'fa-clipboard-list',     'label'=>'Orders',      'link'=>'orders.php'],
    ['icon'=>'fa-truck',              'label'=>'Truck Loads', 'link'=>'truck_loads.php'],
    ['icon'=>'fa-money-bill',         'label'=>'Cash',        'link'=>'cash.php'],
    ['section'=>'Catalogue'],
    ['icon'=>'fa-box',                'label'=>'Items',       'link'=>'items.php'],
    ['icon'=>'fa-store',              'label'=>'Shops',       'link'=>'shops.php'],
    ['icon'=>'fa-map',                'label'=>'Routes',      'link'=>'routes.php'],
    ['section'=>'Survey'],
    ['icon'=>'fa-clipboard-check',    'label'=>'Surveys',     'link'=>'survey.php'],
    ['section'=>'My Performance'],
    ['icon'=>'fa-bullseye',           'label'=>'My Target',   'link'=>'my_target.php'],
    ['icon'=>'fa-chart-bar',          'label'=>'Net Sales',   'link'=>'sales_summary.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?> &mdash; <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fa-solid fa-building sidebar-brand-icon"></i>
        <span class="sidebar-brand-name"><?= APP_NAME ?></span>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menuItems as $item): ?>
            <?php if (isset($item['section'])): ?>
                <div class="sidebar-section-label"><?= htmlspecialchars($item['section']) ?></div>
            <?php else: ?>
                <a href="<?= htmlspecialchars($item['link']) ?>"
                   class="nav-item <?= $current === $item['link'] ? 'active' : '' ?>">
                    <i class="fa-solid <?= $item['icon'] ?>"></i>
                    <span class="nav-item-label"><?= htmlspecialchars($item['label']) ?></span>
                    <?php if (!empty($item['badge'])): ?>
                        <span class="nav-badge"><?= intval($item['badge']) ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="../logout.php" class="nav-item">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span class="nav-item-label">Logout</span>
        </a>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<header class="topbar">
    <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
    <div class="topbar-right">
        <span class="topbar-chip company"><i class="fa-regular fa-building"></i> <?= $company_name ?></span>
        <span class="topbar-chip role"><i class="fa-solid fa-user-tag"></i> <?= $role_label ?></span>
        <span class="topbar-chip user"><i class="fa-regular fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
    </div>
</header>

<main class="main-content">
<div class="container">
