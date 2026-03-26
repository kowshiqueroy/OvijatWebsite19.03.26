<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in (basic check for all admin pages included via header)
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$logoUrl = getSetting('company_logo', '') ?: getSetting('company_logo_large', '');
$companyName = getSetting('company_name', 'SohojWeb');

$adminPath = ADMIN_URL;
$dashboardLink = $adminPath . '/dashboard/index.php';
$generatorsBase = $adminPath . '/generators/';
$settingsLink = $adminPath . '/settings/index.php';
$contentLink = $adminPath . '/content/index.php';
$tasksLink = $adminPath . '/tasks/index.php';
$projectsLink = $adminPath . '/projects/index.php';
$leadsLink = $adminPath . '/crm/leads.php';
$usersLink  = $adminPath . '/users/index.php';
$auditLink  = $adminPath . '/audit/index.php';
$logoutLink = $adminPath . '/logout.php';
$publicLink = BASE_URL . '/';

function isActive($page, $currentPage, $currentDir = '') {
    if (!empty($currentDir) && strpos($currentPage, $currentDir) !== false && $page === $currentDir) return true;
    return strpos($currentPage, $page) !== false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'SohojWeb Admin' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {'50': '#eff6ff', '100': '#dbeafe', '200': '#bfdbfe', '300': '#93c5fd', '400': '#60a5fa', '500': '#3b82f6', '600': '#2563eb', '700': '#1d4ed8', '800': '#1e40af', '900': '#1e3a8a'},
                        slate: {'50': '#f8fafc', '100': '#f1f5f9', '200': '#e2e8f0', '300': '#cbd5e1', '400': '#94a3b8', '500': '#64748b', '600': '#475569', '700': '#334155', '800': '#1e293b', '900': '#0f172a'}
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link { transition: all 0.2s; }
        .sidebar-link:hover { background: rgba(59, 130, 246, 0.1); }
        .sidebar-link.active { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .sidebar-link.active:hover { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        
        /* Mobile Sidebar Transitions */
        #sidebar { transition: transform 0.3s ease-in-out; }
        .sidebar-open { transform: translateX(0); }
        .sidebar-closed { transform: translateX(-100%); }
        
        @media (min-width: 1024px) {
            .sidebar-closed { transform: translateX(0); }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">
    <div class="flex min-h-screen relative overflow-hidden">
        <!-- Mobile Sidebar Overlay -->
        <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden glass-effect"></div>

        <!-- Sidebar -->
        <aside id="sidebar" class="w-64 bg-white border-r border-slate-200 fixed h-full z-50 sidebar-closed lg:translate-x-0 overflow-y-auto">
            <!-- Logo -->
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100 sticky top-0 bg-white z-10">
                <a href="<?= $dashboardLink ?>" class="flex items-center gap-3">
                    <?php if ($logoUrl): ?>
                        <img src="<?= escape($logoUrl) ?>" class="h-8">
                    <?php else: ?>
                        <div class="w-9 h-9 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center shadow-lg shadow-blue-500/30">
                            <span class="text-white font-bold text-lg">S</span>
                        </div>
                    <?php endif; ?>
                    <span class="font-bold text-slate-800 text-lg tracking-tight"><?= $companyName ?></span>
                </a>
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Navigation -->
            <nav class="p-4 space-y-1">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 px-3 mt-2">Main</div>
                <a href="<?= $dashboardLink ?>" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('dashboard', $currentPage, 'dashboard') ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-th-large w-5 text-center"></i>
                    <span>Dashboard</span>
                </a>
                
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-6 mb-3 px-3">Documents</div>
                <a href="<?= $generatorsBase ?>invoices.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('invoices', $currentPage) ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-file-invoice w-5 text-center"></i>
                    <span>Invoices</span>
                </a>
                <a href="<?= $generatorsBase ?>quotations.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('quotations', $currentPage) ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-file-alt w-5 text-center"></i>
                    <span>Quotations</span>
                </a>
                
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-6 mb-3 px-3">HR & Careers</div>
                <a href="<?= $generatorsBase ?>job-circular.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('job-circular', $currentPage) ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-bullhorn w-5 text-center"></i>
                    <span>Job Circulars</span>
                </a>
                <a href="<?= $adminPath ?>/crm/job_appli.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('job_appli', $currentPage, 'crm') ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-user-graduate w-5 text-center"></i>
                    <span>Job Applications</span>
                </a>
                <a href="<?= $generatorsBase ?>job-offer.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('job-offer', $currentPage) ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-user-plus w-5 text-center"></i>
                    <span>Job Offers</span>
                </a>
                <a href="<?= $generatorsBase ?>applications.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('applications', $currentPage) ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-paper-plane w-5 text-center"></i>
                    <span>Outgoing Letters</span>
                </a>
                
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-6 mb-3 px-3">Management</div>
                <a href="<?= $projectsLink ?>" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('projects', $currentPage) ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-layer-group w-5 text-center"></i>
                    <span>Project Tracker</span>
                </a>
                <a href="<?= $projectsLink ?>#tasks" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('tasks', $currentPage, 'tasks') ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-calendar-check w-5 text-center"></i>
                    <span>Daily Tasks</span>
                </a>
                <a href="<?= $leadsLink ?>" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('leads', $currentPage) ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-user-tag w-5 text-center"></i>
                    <span>Leads</span>
                </a>
                
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-6 mb-3 px-3">System</div>
                <a href="<?= $contentLink ?>" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('content', $currentPage) ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-palette w-5 text-center"></i>
                    <span>Site Content</span>
                </a>
                <?php if (hasPermission('super_admin')): ?>
                <a href="<?= $usersLink ?>" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('users', $currentDir) ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-users-cog w-5 text-center"></i>
                    <span>Users</span>
                </a>
                <a href="<?= $auditLink ?>" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('audit', $currentDir) ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-shield-alt w-5 text-center"></i>
                    <span>Audit Log</span>
                </a>
                <?php endif; ?>
                <a href="<?= $settingsLink ?>" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 font-medium <?= isActive('settings', $currentPage) ? 'active shadow-lg shadow-blue-500/30' : '' ?>">
                    <i class="fas fa-cog w-5 text-center"></i>
                    <span>Settings</span>
                </a>
                
                <div class="pt-4 mt-4 border-t border-slate-100 pb-20 lg:pb-4">
                    <a href="<?= $publicLink ?>" target="_blank" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:text-blue-600 font-medium">
                        <i class="fas fa-external-link-alt w-5 text-center"></i>
                        <span>View Website</span>
                    </a>
                    <a href="<?= $logoutLink ?>" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:text-red-600 font-medium">
                        <i class="fas fa-sign-out-alt w-5 text-center"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 lg:ml-64 flex flex-col min-h-screen transition-all duration-300">
            <!-- Top Bar -->
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8 sticky top-0 z-30 shadow-sm">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="lg:hidden p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-xl font-bold text-slate-800 hidden sm:block"><?= str_replace(['| SohojWeb Admin', '| SohojWeb'], '', $pageTitle ?? 'Dashboard') ?></h1>
                </div>
                
                <div class="flex items-center gap-3 lg:gap-6">
                    <div class="hidden md:flex flex-col items-end">
                        <span class="text-sm font-semibold text-slate-700"><?= $_SESSION['user_name'] ?? 'Admin' ?></span>
                        <span class="text-xs text-slate-500"><?= date('F j, Y') ?></span>
                    </div>
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold shadow-md shadow-blue-500/20 ring-2 ring-white">
                        <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-4 lg:p-8 overflow-x-hidden">
                <div class="max-w-7xl mx-auto">
