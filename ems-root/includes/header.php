<?php
// includes/header.php
// Usage: require_once ROOT . '/includes/header.php';
// Set $page_title and optional $breadcrumbs before including.
if (!defined('EMS_ROOT')) define('EMS_ROOT', dirname(__DIR__));
require_once EMS_ROOT . '/config/constants.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/rbac.php';

$school_name = setting('school_name', 'EMS');
$page_title  = $page_title ?? 'Dashboard';
$breadcrumbs = $breadcrumbs ?? [];
$school_logo = setting('school_logo');
$current_session_id = (int)setting('current_session_id', 0);
$current_session_name = '';
if ($current_session_id) {
    try {
        $_sess_stmt = db()->prepare('SELECT session_name FROM academic_sessions WHERE id=:id');
        $_sess_stmt->execute([':id' => $current_session_id]);
        $current_session_name = $_sess_stmt->fetchColumn() ?: '';
        unset($_sess_stmt);
    } catch (Exception $e) {}
}

$menu        = filter_menu(get_nav_menu());
$full_name   = $_SESSION['full_name'] ?? 'User';
$user_avatar = $_SESSION['avatar'] ?? '';
$initials    = strtoupper(substr($full_name, 0, 1) . (strpos($full_name, ' ') !== false ? substr(strrchr($full_name, ' '), 1, 1) : ''));

// Detect if current file is in a sub-folder to prefix asset path
$script_file = $_SERVER['SCRIPT_FILENAME'] ?? '';
if ($script_file) {
    $depth  = substr_count(str_replace('\\', '/', dirname($script_file)), '/');
    $root_d = substr_count(str_replace('\\', '/', EMS_ROOT), '/');
    $rel    = str_repeat('../', max(0, $depth - $root_d));
} else {
    $rel    = '';
}
$ASSET  = $rel . 'assets';
$MOD    = $rel;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token"   content="<?= e(csrf_token()) ?>">
<meta name="school-name"  content="<?= e($school_name) ?>">
<meta name="currency"     content="<?= e(setting('currency_symbol','৳')) ?>">
<title><?= e($page_title) ?> — <?= e($school_name) ?></title>

<!-- Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- Google Fonts (Bangla-compatible) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<!-- EMS Custom CSS -->
<link rel="stylesheet" href="<?= $ASSET ?>/css/style.css">
<?php if (!empty($extra_css)) foreach ((array)$extra_css as $css): ?>
<link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; ?>
</head>
<body>
<div id="wrapper">

<!-- ── Sidebar ────────────────────────────────────────────── -->
<nav id="sidebar">
  <div class="sidebar-brand d-flex align-items-center gap-2">
    <div class="brand-logo">
      <?php if ($school_logo && file_exists(EMS_ROOT . '/uploads/logos/' . $school_logo)): ?>
        <img src="<?= $ASSET ?>/../uploads/logos/<?= e($school_logo) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">
      <?php else: ?>
        <i class="bi bi-mortarboard-fill text-white"></i>
      <?php endif; ?>
    </div>
    <div style="min-width:0;">
      <h6 class="text-truncate" style="max-width:170px;"><?= e($school_name) ?></h6>
      <small>Management System</small>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($menu as $item): ?>
      <?php
        $hasChildren = !empty($item['children']);
        $collapseId  = 'nav-' . preg_replace('/\W/', '', strtolower($item['label']));
        $isActive    = !$hasChildren && !empty($item['url']) && is_active_url($item['url']);
        $childActive = $hasChildren && array_reduce($item['children'], fn($c, $ch) => $c || (!empty($ch['url']) && is_active_url($ch['url'])), false);
      ?>
      <?php if ($hasChildren): ?>
        <a class="nav-link <?= $childActive ? 'active' : '' ?>"
           data-bs-toggle="collapse"
           data-bs-target="#<?= $collapseId ?>"
           aria-expanded="<?= $childActive ? 'true' : 'false' ?>">
          <i class="bi bi-<?= e($item['icon']) ?> nav-icon"></i>
          <span><?= e($item['label']) ?></span>
          <i class="bi bi-chevron-right nav-arrow"></i>
        </a>
        <div id="<?= $collapseId ?>" class="collapse <?= $childActive ? 'show' : '' ?> sidebar-submenu">
          <?php foreach ($item['children'] as $child): ?>
            <a class="nav-link <?= (!empty($child['url']) && is_active_url($child['url'])) ? 'active' : '' ?>"
               href="<?= $MOD . e($child['url']) ?>">
              <?= e($child['label']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <a class="nav-link <?= $isActive ? 'active' : '' ?>"
           href="<?= !empty($item['url']) ? $MOD . e($item['url']) : '#' ?>">
          <i class="bi bi-<?= e($item['icon']) ?> nav-icon"></i>
          <span><?= e($item['label']) ?></span>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <div style="padding:.75rem 1.25rem;border-top:1px solid rgba(255,255,255,.07);">
    <a href="<?= $MOD ?>logout.php" class="nav-link text-danger d-flex align-items-center gap-2" style="padding:.5rem 0;">
      <i class="bi bi-box-arrow-left nav-icon"></i> Logout
    </a>
  </div>
</nav>

<!-- Mobile overlay -->
<div id="sidebar-overlay" class="sidebar-overlay"></div>

<!-- ── Main content ────────────────────────────────────────── -->
<div id="main-content">

  <!-- Topbar -->
  <header id="topbar">
    <button class="topbar-toggle sidebar-toggle border-0 bg-transparent">
      <i class="bi bi-list"></i>
    </button>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="d-none d-md-block">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= $MOD ?>dashboard.php"><i class="bi bi-house-fill"></i></a></li>
        <?php foreach ($breadcrumbs as $label => $url): ?>
          <?php if ($url): ?>
            <li class="breadcrumb-item"><a href="<?= $MOD . e($url) ?>"><?= e($label) ?></a></li>
          <?php else: ?>
            <li class="breadcrumb-item active"><?= e($label) ?></li>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty($breadcrumbs)): ?>
          <li class="breadcrumb-item active"><?= e($page_title) ?></li>
        <?php endif; ?>
      </ol>
    </nav>

    <div class="topbar-right">
      <?php if ($current_session_name): ?>
        <span class="session-badge d-none d-sm-inline">
          <i class="bi bi-calendar3 me-1"></i><?= e($current_session_name) ?>
        </span>
      <?php endif; ?>

      <!-- Notifications placeholder -->
      <button class="btn btn-sm btn-light position-relative" title="Notifications">
        <i class="bi bi-bell"></i>
      </button>

      <!-- User menu -->
      <div class="dropdown">
        <button class="topbar-user-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <div class="topbar-avatar">
            <?php if ($user_avatar && file_exists(EMS_ROOT . '/uploads/avatars/' . $user_avatar)): ?>
              <img src="<?= $ASSET ?>/../uploads/avatars/<?= e($user_avatar) ?>" alt="">
            <?php else: ?>
              <?= e($initials) ?>
            <?php endif; ?>
          </div>
          <span class="d-none d-md-inline"><?= e($full_name) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:200px;border-radius:12px;">
          <li><div class="dropdown-header">
            <div class="fw-600"><?= e($full_name) ?></div>
            <small class="text-muted"><?= e(implode(', ', array_map('ucwords', str_replace('_',' ', $_SESSION['roles'] ?? [])))) ?></small>
          </div></li>
          <li><hr class="dropdown-divider my-1"></li>
          <li><a class="dropdown-item" href="<?= $MOD ?>modules/users/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
          <li><a class="dropdown-item" href="<?= $MOD ?>modules/users/change_password.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
          <li><hr class="dropdown-divider my-1"></li>
          <li><a class="dropdown-item text-danger" href="<?= $MOD ?>logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </header>

  <!-- Page content starts here -->
  <main class="page-content">
<?php
// render any flash messages
if (function_exists('render_flash')) render_flash();
?>
