<?php
/**
 * Shared layout header + sidebar.
 * Include at top of every page AFTER requireLogin().
 * Usage:
 *   $pageTitle = 'Dashboard';
 *   $activePage = 'dashboard';
 *   require_once 'includes/layout.php';
 */
$agent        = currentAgent();
$notifCount   = unreadNotifCount();
$taskCount    = pendingTaskCount();
$companyName  = getSetting('company_name', APP_NAME);

// Fetch recent 5 notifications for bell dropdown
$notifs = $conn->query(
    "SELECT n.*, a.full_name as from_name
     FROM notifications n
     LEFT JOIN agents a ON a.id = n.from_agent
     WHERE n.agent_id = " . agentId() . "
     ORDER BY n.created_at DESC LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e($companyName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- ── Mobile top bar ───────────────────────────────────────────────────────── -->
<div class="mobile-topbar d-lg-none">
    <button class="btn-icon" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <span class="fw-bold"><?= e($companyName) ?></span>
    <div class="d-flex gap-2 align-items-center">
        <?php if ($taskCount): ?>
        <a href="<?= APP_URL ?>/todos.php" class="btn-icon position-relative">
            <i class="fas fa-list-check"></i>
            <span class="badge-dot"><?= $taskCount ?></span>
        </a>
        <?php endif; ?>
        <button class="btn-icon position-relative" onclick="toggleNotif()">
            <i class="fas fa-bell"></i>
            <?php if ($notifCount): ?><span class="badge-dot"><?= $notifCount ?></span><?php endif; ?>
        </button>
    </div>
</div>

<!-- ── Sidebar ───────────────────────────────────────────────────────────────── -->
<div class="app-wrap">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-head">
        <div class="sidebar-logo">
            <i class="fas fa-headset"></i>
            <span><?= e($companyName) ?></span>
        </div>
        <button class="sidebar-close d-lg-none" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
    </div>

    <div class="agent-card">
        <div class="agent-avatar"><i class="fas fa-user"></i></div>
        <div>
            <div class="agent-name"><?= e($agent['full_name']) ?></div>
            <div class="agent-dept"><?= e($agent['dept'] ?: $agent['username']) ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php
        $nav = [
            ['dashboard',     'fa-chart-pie',        'Dashboard',  'dashboard.php'],
            ['calls',         'fa-phone-volume',     'Call Logs',  'calls.php'],
            ['marks',         'fa-tag',              'Work Queue', 'marks.php'],
            ['contacts',      'fa-address-book',     'Contacts',   'contacts.php'],
            ['todos',         'fa-list-check',       'Tasks',      'todos.php'],
            ['fetch',         'fa-cloud-arrow-down', 'Fetch PBX',  'fetch.php'],
            ['reports',       'fa-chart-bar',        'Reports',    'reports.php'],
            ['faqs',          'fa-circle-question',  'Knowledge',  'faqs.php'],
            ['agents',        'fa-users',            'Agents',     'agents.php'],
            ['settings',      'fa-gear',             'Settings',   'settings.php'],
        ];
        foreach ($nav as [$key, $icon, $label, $href]):
            $active = ($activePage ?? '') === $key ? 'active' : '';
            $badge  = '';
            if ($key === 'todos' && $taskCount)   $badge = "<span class='nav-badge'>$taskCount</span>";
        ?>
        <a href="<?= APP_URL ?>/<?= $href ?>" class="nav-item <?= $active ?>">
            <i class="fas <?= $icon ?>"></i>
            <span><?= $label ?></span>
            <?= $badge ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
        <a href="<?= APP_URL ?>/logout.php" class="nav-item text-danger">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </a>
    </div>
</aside>

<!-- ── Sidebar overlay (mobile) ─────────────────────────────────────────────── -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- ── Main content ─────────────────────────────────────────────────────────── -->
<main class="main-content" id="mainContent">

    <!-- Top header -->
    <div class="page-header d-none d-lg-flex">
        <div class="page-header-left">
            <h1 class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
            <?php if (!empty($pageSubtitle)): ?>
            <span class="page-subtitle"><?= e($pageSubtitle) ?></span>
            <?php endif; ?>
        </div>
        <div class="page-header-right">
            <!-- Global search -->
            <div class="global-search" id="globalSearchWrap">
                <i class="fas fa-search"></i>
                <input type="text" id="globalSearch" placeholder="Search calls, contacts, notes…" autocomplete="off">
                <div class="search-results" id="searchResults"></div>
            </div>

            <!-- Task count pill -->
            <?php if ($taskCount): ?>
            <a href="<?= APP_URL ?>/todos.php" class="header-pill pill-task">
                <i class="fas fa-list-check"></i> <?= $taskCount ?> task<?= $taskCount > 1 ? 's' : '' ?>
            </a>
            <?php endif; ?>

            <!-- Notifications bell -->
            <div class="notif-wrap" id="notifWrap">
                <button class="btn-icon position-relative" onclick="toggleNotif(event)">
                    <i class="fas fa-bell"></i>
                    <?php if ($notifCount): ?><span class="badge-dot"><?= $notifCount ?></span><?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-head">
                        Notifications
                        <?php if ($notifCount): ?>
                        <button class="btn-link small" onclick="markAllRead()">Mark all read</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($notifs && $notifs->num_rows): ?>
                        <?php while ($n = $notifs->fetch_assoc()): ?>
                        <a href="<?= e($n['link'] ?? '#') ?>" class="notif-item <?= $n['is_read'] ? 'read' : 'unread' ?>">
                            <div class="notif-icon type-<?= e($n['type']) ?>">
                                <i class="fas <?= $n['type'] === 'task_assigned' ? 'fa-list-check' : ($n['type'] === 'note_reply' ? 'fa-comment' : 'fa-bell') ?>"></i>
                            </div>
                            <div>
                                <div class="notif-title"><?= e($n['title']) ?></div>
                                <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
                            </div>
                        </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="notif-empty"><i class="fas fa-check-circle"></i> All caught up</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Agent menu -->
            <div class="agent-menu-wrap" id="agentMenuWrap">
                <button class="agent-menu-btn" onclick="toggleAgentMenu(event)">
                    <div class="agent-avatar-sm"><i class="fas fa-user"></i></div>
                    <span class="d-none d-xl-inline"><?= e($agent['full_name']) ?></span>
                    <i class="fas fa-chevron-down small"></i>
                </button>
                <div class="agent-dropdown" id="agentDropdown">
                    <div class="agent-drop-head">
                        <strong><?= e($agent['full_name']) ?></strong>
                        <span><?= e($agent['username']) ?></span>
                    </div>
                    <a href="<?= APP_URL ?>/agents.php?view=<?= $agent['id'] ?>"><i class="fas fa-user me-2"></i>My Profile</a>
                    <a href="<?= APP_URL ?>/settings.php"><i class="fas fa-gear me-2"></i>Settings</a>
                    <hr class="my-1">
                    <a href="<?= APP_URL ?>/logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Page body starts here — closed in footer.php -->
