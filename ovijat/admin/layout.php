<?php
// admin/layout.php

function adminOpen(string $pageTitle, string $activePage): void {
    $name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
    $base = BASE_URL;
    $role = $_SESSION['admin_role'] ?? 'editor';

    $nav = [
        'dashboard'  => ['Dashboard',       'grid',    '/admin/dashboard.php',       'main'],
        'products'   => ['Products',         'package', '/admin/crud/products.php',   'content'],
        'categories' => ['Categories',       'tag',     '/admin/crud/categories.php', ''],
        'team'       => ['Team Members',     'users',   '/admin/crud/team.php',       ''],
        'map'        => ['Map Countries',    'map-pin', '/admin/crud/map.php',        ''],
        'stats'      => ['Stats Counters',   'bar-2',   '/admin/crud/stats.php',      ''],
        'contacts'   => ['Messages',         'mail',    '/admin/crud/contacts.php',   'system'],
        'contact_info'=> ['Contact Info',      'phone',   '/admin/crud/contact_info.php',''],
        'taglines'   => ['Taglines',         'type',    '/admin/crud/taglines.php',   ''],
        'settings'   => ['Settings',         'settings','/admin/crud/settings.php',  ''],
        'users'      => ['Admin Users',      'shield',  '/admin/crud/users.php',      ''],
    ];

    $icons = [
        'grid'    => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'package' => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        'tag'     => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
        'users'   => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'map-pin' => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
        'bar-2'   => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'mail'    => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>',
        'type'    => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>',
        'settings'=> '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        'shield'  => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'logout'  => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
    ];

    // Unread message count badge
    $unread = getPDO()->query("SELECT COUNT(*) FROM contact_submissions WHERE is_read=0")->fetchColumn();

    echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
    echo "  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n";
    echo "  <title>{$pageTitle} — Ovijat Admin</title>\n";
    echo "  <link rel=\"stylesheet\" href=\"{$base}/assets/css/style.css\">\n";
    echo "  <meta name=\"robots\" content=\"noindex,nofollow\">\n</head>\n<body>\n";
    echo "<div class=\"admin-body\">\n";

    // Sidebar
    echo "<aside class=\"admin-sidebar\" role=\"navigation\" aria-label=\"Admin navigation\">\n";
    echo "  <div class=\"admin-sidebar-logo\">\n";
    echo "    <span style=\"color:var(--clr-gold);\">OFB</span> <span style=\"font-size:.85rem;font-weight:600;\">Admin</span>\n";
    echo "    <div style=\"font-size:.6rem;font-weight:500;letter-spacing:.1em;text-transform:uppercase;color:rgba(201,168,76,.5);margin-top:.2rem;\">Ovijat Food &amp; Beverage</div>\n";
    echo "  </div>\n";

    $currentSection = '';
    foreach ($nav as $key => $item) {
        [$label, $icon, $url, $section] = $item;

        if ($section === 'main') {
            echo "<p class=\"admin-nav-label\">Main</p>\n";
        } elseif ($section === 'content') {
            echo "<p class=\"admin-nav-label\">Content</p>\n";
        } elseif ($section === 'system') {
            echo "<p class=\"admin-nav-label\">Enquiries</p>\n";
        } elseif ($section === 'config') {
            echo "<p class=\"admin-nav-label\">Config</p>\n";
        }

        // Hide users link from non-superadmin
        if ($key === 'users' && $role !== 'superadmin') continue;

        $activeClass = $activePage === $key ? 'active' : '';
        $iconHtml    = $icons[$icon] ?? '';
        $badge       = ($key === 'contacts' && $unread > 0)
            ? " <span style=\"background:var(--clr-crimson);color:#fff;font-size:.6rem;padding:.1rem .4rem;border-radius:99px;font-family:var(--ff-ui);font-weight:700;\">{$unread}</span>"
            : '';

        echo "<a href=\"{$base}{$url}\" class=\"admin-nav-link {$activeClass}\">{$iconHtml} {$label}{$badge}</a>\n";
    }

    echo "  <div style=\"margin-top:auto;padding:1.5rem 0 0;border-top:1px solid rgba(201,168,76,.1);\">\n";
    echo "    <a href=\"{$base}/admin/logout.php\" class=\"admin-nav-link\" style=\"color:rgba(192,21,15,.8);\">\n";
    echo "      {$icons['logout']} Logout\n    </a>\n  </div>\n</aside>\n";

    // Main area
    echo "<div class=\"admin-main\">\n";
    echo "  <header class=\"admin-topbar\">\n";
    echo "    <h1 class=\"admin-page-title\">{$pageTitle}</h1>\n";
    echo "    <div style=\"display:flex;align-items:center;gap:1rem;\">\n";
    echo "      <a href=\"{$base}/\" target=\"_blank\" style=\"font-family:var(--ff-ui);font-size:.78rem;color:var(--clr-muted);display:flex;align-items:center;gap:.3rem;\">View Site <svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='currentColor' stroke-width='2' viewBox='0 0 24 24'><path d='M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6'/><polyline points='15 3 21 3 21 9'/><line x1='10' y1='14' x2='21' y2='3'/></svg></a>\n";
    echo "      <span style=\"font-family:var(--ff-ui);font-size:.82rem;color:var(--clr-muted);\">👤 {$name}</span>\n";
    echo "    </div>\n  </header>\n";
    echo "  <div class=\"admin-content\">\n";
}

function adminClose(): void {
    echo "  </div>\n</div>\n</div>\n</body>\n</html>\n";
}
