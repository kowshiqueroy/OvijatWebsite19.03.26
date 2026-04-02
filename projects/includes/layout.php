<?php
function userInitials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) $initials .= strtoupper($w[0]);
    }
    return !empty($initials) ? $initials : '?';
}

function avatarColor(string $name): string {
    $initials = userInitials($name);
    static $colors = ['#4F6BED','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#06B6D4','#F97316'];
    $hash = 0;
    for ($i = 0; $i < strlen($initials); $i++) $hash += ord($initials[$i]);
    return $colors[$hash % 8];
}

function layoutStart(string $pageTitle, string $activeNav = ''): void {
    $user = currentUser();
    $appName = APP_NAME;
    $baseUrl = BASE_URL;
    $flash = getFlash();
    $unreadCount = 0;
    $myProjects = [];
    if ($user) {
        $unreadCount = (int)(dbFetch(
            "SELECT COUNT(*) as c FROM updates u WHERE u.user_id != ? AND u.id NOT IN (SELECT update_id FROM update_reads WHERE user_id=?)",
            [$user['id'], $user['id']]
        )['c'] ?? 0);
        $myProjects = $user['role'] === 'admin'
            ? dbFetchAll("SELECT id, name FROM projects WHERE status NOT IN ('archived','completed') ORDER BY name")
            : dbFetchAll("SELECT p.id, p.name FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? AND p.status NOT IN ('archived','completed') ORDER BY p.name", [$user['id']]);
    }
    $initials  = $user ? userInitials($user['full_name']) : '?';
    $avatarBg  = $user ? avatarColor($user['full_name']) : '#4F6BED';
    ?>
<!DOCTYPE html>
<html lang="en" id="htmlRoot">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> — <?= e($appName) ?></title>
<link rel="stylesheet" href="<?= $baseUrl ?>/assets/style.css">
<!-- Apply saved theme + sidebar state before paint to avoid flash -->
<script>
(function(){
  var t = localStorage.getItem('swTheme');
  if (t) document.documentElement.setAttribute('data-theme', t);
})();
</script>
<script>
window.CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;
window.BASE_URL   = <?= json_encode($baseUrl) ?>;
window.MY_PROJECTS = <?= json_encode(array_map(fn($p)=>['id'=>(int)$p['id'],'name'=>$p['name']], $myProjects)) ?>;
</script>
</head>
<body>

<!-- HEADER -->
<header class="app-header">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <a href="<?= $baseUrl ?>/index.php" class="header-brand"><?= e($appName) ?></a>
    <form action="<?= $baseUrl ?>/search.php" method="GET" class="header-search">
        <input name="q" type="search" class="form-control" placeholder="Search…"
               value="<?= isset($_GET['q']) ? e($_GET['q']) : '' ?>" autocomplete="off">
        <button type="submit" data-tooltip="Search">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
    </form>
    <div class="header-actions">
        <button class="theme-toggle" id="themeToggle" data-tooltip="Toggle theme" aria-label="Toggle dark mode">
            <svg class="icon-moon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <svg class="icon-sun"  width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>
        <a href="<?= $baseUrl ?>/modules/updates/index.php" class="header-notif" data-tooltip="Updates">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <?php if ($unreadCount > 0): ?><span class="notif-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span><?php endif ?>
        </a>
        <a href="<?= $baseUrl ?>/profile/index.php" class="header-avatar" style="background:<?= $avatarBg ?>" data-tooltip="<?= e($user['full_name'] ?? '') ?>"><?= $initials ?></a>
    </div>
</header>

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-profile">
        <div class="sidebar-avatar" style="background:<?= $avatarBg ?>"><?= $initials ?></div>
        <div>
            <div class="sidebar-name"><?= e($user['full_name'] ?? '') ?></div>
            <div class="sidebar-role"><?= e(ucfirst($user['role'] ?? '')) ?></div>
        </div>
    </div>
    <ul class="sidebar-nav">
        <li><a href="<?= $baseUrl ?>/index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>" data-tooltip-collapsed="Dashboard">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span class="nav-label">Dashboard</span></a></li>
        <li><a href="<?= $baseUrl ?>/modules/projects/index.php" class="<?= $activeNav === 'projects' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <span class="nav-label">Projects</span></a></li>
        <li><a href="<?= $baseUrl ?>/modules/tasks/index.php" class="<?= $activeNav === 'tasks' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <span class="nav-label">Tasks</span></a></li>
        <li><a href="<?= $baseUrl ?>/calendar.php" class="<?= $activeNav === 'calendar' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
            <span class="nav-label">Calendar</span></a></li>
        <li><a href="<?= $baseUrl ?>/modules/meetings/index.php" class="<?= $activeNav === 'meetings' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span class="nav-label">Meetings</span></a></li>
        <li><a href="<?= $baseUrl ?>/reports.php" class="<?= $activeNav === 'reports' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span class="nav-label">Reports</span></a></li>
        <li><a href="<?= $baseUrl ?>/modules/updates/index.php" class="<?= $activeNav === 'updates' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="nav-label">Updates<?php if ($unreadCount > 0): ?> <span class="nav-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span><?php endif ?></span></a></li>
        <li><a href="<?= $baseUrl ?>/worksheet/index.php" class="<?= $activeNav === 'worksheet' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <span class="nav-label">Worksheet</span></a></li>
        <li><a href="<?= $baseUrl ?>/modules/myday/index.php" class="<?= $activeNav === 'myday' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <span class="nav-label">My Day</span></a></li>
        <?php if ($user['role'] === 'admin'): ?>
        <li><a href="<?= $baseUrl ?>/modules/users/index.php" class="<?= $activeNav === 'users' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span class="nav-label">Users</span></a></li>
        <li><a href="<?= $baseUrl ?>/modules/users/performance.php" class="<?= $activeNav === 'performance' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span class="nav-label">Performance</span></a></li>
        <?php endif ?>
    </ul>
    <div class="sidebar-footer">
        <a href="<?= $baseUrl ?>/profile/index.php" class="<?= $activeNav === 'profile' ? 'active' : '' ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="sidebar-footer-label">Profile</span></a>
        <a href="<?= $baseUrl ?>/logout.php">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="sidebar-footer-label">Logout</span></a>
        <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Collapse sidebar">
            <span class="sidebar-footer-label" style="font-size:.8rem">Collapse</span>
            <svg class="collapse-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
    </div>
</nav>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- MAIN -->
<main class="app-main">
<?php if ($flash): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.showToast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
});
</script>
<?php endif ?>
<?php
}

function layoutEnd(): void {
    $baseUrl = BASE_URL;
    $user = currentUser();
    $unreadCount = 0;
    if ($user) {
        $unreadCount = (int)(dbFetch(
            "SELECT COUNT(*) as c FROM updates u WHERE u.user_id != ? AND u.id NOT IN (SELECT update_id FROM update_reads WHERE user_id=?)",
            [$user['id'], $user['id']]
        )['c'] ?? 0);
    }
    ?>
</main>

<!-- MOBILE BOTTOM NAV -->
<nav class="bottom-nav" id="bottomNav">
    <a href="<?= $baseUrl ?>/index.php" class="bottom-nav-item">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <span>Home</span>
    </a>
    <a href="<?= $baseUrl ?>/modules/projects/index.php" class="bottom-nav-item">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        <span>Projects</span>
    </a>
    <a href="<?= $baseUrl ?>/modules/tasks/index.php" class="bottom-nav-item">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span>Tasks</span>
    </a>
    <a href="<?= $baseUrl ?>/modules/meetings/index.php" class="bottom-nav-item">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Meetings</span>
    </a>
    <button class="bottom-nav-item" id="moreBtn">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
        <span>More</span>
    </button>
</nav>

<!-- MORE SHEET -->
<div class="more-overlay" id="moreOverlay"></div>
<div class="more-sheet" id="moreSheet">
    <div class="more-handle"></div>
    <a href="<?= $baseUrl ?>/search.php">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Search
    </a>
    <a href="<?= $baseUrl ?>/calendar.php">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
        Calendar
    </a>
    <a href="<?= $baseUrl ?>/reports.php">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        Reports
    </a>
    <a href="<?= $baseUrl ?>/modules/updates/index.php">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Updates<?php if ($unreadCount > 0): ?> <span class="nav-badge"><?= $unreadCount ?></span><?php endif ?>
    </a>
    <a href="<?= $baseUrl ?>/worksheet/index.php">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        Worksheet
    </a>
    <?php if ($user && $user['role'] === 'admin'): ?>
    <a href="<?= $baseUrl ?>/modules/users/index.php">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        Users
    </a>
    <?php endif ?>
    <a href="<?= $baseUrl ?>/profile/index.php">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profile
    </a>
    <a href="<?= $baseUrl ?>/logout.php">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Logout
    </a>
</div>

<!-- Quick Create Modal (Ctrl+K) -->
<div class="modal-overlay" id="quickCreateModal" style="z-index:2000">
    <div class="modal" style="max-width:460px;width:90%">
        <div class="modal-header">
            <span class="modal-title">Quick Add Task</span>
            <button class="modal-close" onclick="closeModal('quickCreateModal')">✕</button>
        </div>
        <div class="modal-body" style="padding:20px">
            <div class="form-group" style="margin-bottom:12px">
                <input id="qcTitle" class="form-control" placeholder="Task title…" autocomplete="off">
            </div>
            <div style="display:flex;gap:10px;margin-bottom:12px">
                <select id="qcProject" class="form-control" style="flex:1">
                    <option value="">— Project —</option>
                </select>
                <select id="qcPriority" class="form-control" style="width:120px">
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                </select>
            </div>
            <input id="qcDue" type="date" class="form-control" style="margin-bottom:16px">
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button class="btn btn-secondary" onclick="closeModal('quickCreateModal')">Cancel</button>
                <button class="btn btn-primary" id="qcSubmitBtn" onclick="quickCreateTask()">Create Task</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $baseUrl ?>/assets/app.js"></script>
</body>
</html>
<?php
}
