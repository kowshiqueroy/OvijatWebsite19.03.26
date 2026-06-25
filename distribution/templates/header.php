<?php
require_once __DIR__ . '/../includes/functions.php';
$company = get_company_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : APP_NAME; ?></title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <!-- App styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body>

<div id="wrapper">

    <!-- ── Sidebar ─────────────────────────────────────────── -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- ── Sidebar backdrop (mobile) ──────────────────────── -->
    <div id="sidebar-backdrop"></div>

    <!-- ── Main content area ───────────────────────────────── -->
    <div id="page-content-wrapper">

        <!-- Top Navbar -->
        <nav class="top-navbar">
            <button id="menu-toggle" title="Toggle sidebar" aria-label="Toggle sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Brand shown on mobile only -->
            <span class="top-navbar-brand"><?php echo htmlspecialchars($company['name'] ?? APP_NAME); ?></span>

            <div class="top-navbar-spacer"></div>

            <!-- User badge -->
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="top-navbar-user">
                <div class="user-badge">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="user-info d-none d-sm-block">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></div>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-sm btn-outline-danger d-none d-sm-flex" title="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
            <?php endif; ?>
        </nav>

        <!-- Flash messages -->
        <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="flash-wrapper">
            <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'success'; ?> alert-dismissible fade show" role="alert">
                <?php
                    $icon_map = ['success'=>'circle-check','danger'=>'circle-exclamation','warning'=>'triangle-exclamation','info'=>'circle-info'];
                    $ftype = $_SESSION['flash_type'] ?? 'success';
                    echo '<i class="fa-solid fa-' . ($icon_map[$ftype] ?? 'circle-info') . ' me-2"></i>';
                    echo htmlspecialchars($_SESSION['flash_message']);
                    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page content -->
        <div class="main-content">
