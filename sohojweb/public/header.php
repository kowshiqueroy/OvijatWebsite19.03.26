<?php
require_once __DIR__ . '/../includes/config/database.php';

$logo = getSetting('company_logo', '');
$logoLarge = getSetting('company_logo_large', '');
$favicon = getSetting('company_favicon', '');

$headerLogo = $logoLarge ?: $logo;
$baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/sohojweb';
$currentPage = $_GET['page'] ?? 'home';

$hasTeam    = !empty(getTeam());
$hasCareers = !empty(getRunningJobCirculars());
?>
<header class="fixed w-full bg-white/80 backdrop-blur-md shadow-lg z-50 transition-all duration-300" id="header">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <!-- Logo -->
        <a href="<?= $baseUrl ?>/" class="flex items-center gap-3 group shrink-0">
            <?php if ($headerLogo): ?>
            <img src="<?= escape($headerLogo) ?>" alt="Logo" class="h-14 transition-transform group-hover:scale-110">
            <?php else: ?>
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                <span class="text-white font-bold text-xl">S</span>
            </div>
            <span class="font-bold text-xl gradient-text">SOHOJWEB</span>
            <?php endif; ?>
        </a>

        <!-- Desktop nav -->
        <nav class="hidden md:flex items-center gap-8">
            <a href="<?= $baseUrl ?>/?page=home"     class="text-gray-600 hover:text-blue-600 font-medium transition-all <?= $currentPage === 'home'     ? 'text-blue-600' : '' ?>">Home</a>
            <a href="<?= $baseUrl ?>/?page=about"    class="text-gray-600 hover:text-blue-600 font-medium transition-all <?= $currentPage === 'about'    ? 'text-blue-600' : '' ?>">About</a>
            <a href="<?= $baseUrl ?>/?page=services" class="text-gray-600 hover:text-blue-600 font-medium transition-all <?= $currentPage === 'services' ? 'text-blue-600' : '' ?>">Services</a>
            <?php if ($hasTeam): ?>
            <a href="<?= $baseUrl ?>/?page=team"     class="text-gray-600 hover:text-blue-600 font-medium transition-all <?= $currentPage === 'team'     ? 'text-blue-600' : '' ?>">Team</a>
            <?php endif; ?>
            <?php if ($hasCareers): ?>
            <a href="<?= $baseUrl ?>/?page=careers"  class="text-gray-600 hover:text-blue-600 font-medium transition-all <?= $currentPage === 'careers'  ? 'text-blue-600' : '' ?>">Careers</a>
            <?php endif; ?>
            <a href="<?= $baseUrl ?>/?page=contact"  class="text-gray-600 hover:text-blue-600 font-medium transition-all <?= $currentPage === 'contact'  ? 'text-blue-600' : '' ?>">Contact</a>
            <a href="<?= $baseUrl ?>/?page=track"    class="text-gray-600 hover:text-blue-600 font-medium transition-all <?= $currentPage === 'track'    ? 'text-blue-600' : '' ?>" title="Track Status"><i class="fas fa-search"></i></a>
        </nav>

        <!-- Right side -->
        <div class="flex items-center gap-3">
            <!-- Get Quote: hidden on mobile -->
            <a href="<?= $baseUrl ?>/?page=estimator" class="hidden md:flex px-5 py-2.5 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-medium hover:shadow-lg hover:shadow-blue-500/30 transition-all transform hover:scale-105 items-center gap-2">
                <i class="fas fa-calculator"></i> Get Quote
            </a>
            <!-- Hamburger: visible on mobile only -->
            <button id="menuToggle" onclick="toggleMobileMenu()" class="md:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors" aria-label="Menu">
                <i id="menuIcon" class="fas fa-bars text-2xl"></i>
            </button>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobileMenu" class="md:hidden hidden border-t border-gray-100 bg-white/95 backdrop-blur-md">
        <nav class="flex flex-col px-4 py-4 gap-1">
            <a href="<?= $baseUrl ?>/?page=home"     class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all <?= $currentPage === 'home'     ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50' ?>"><i class="fas fa-home w-5 text-center text-gray-400"></i>Home</a>
            <a href="<?= $baseUrl ?>/?page=about"    class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all <?= $currentPage === 'about'    ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50' ?>"><i class="fas fa-info-circle w-5 text-center text-gray-400"></i>About</a>
            <a href="<?= $baseUrl ?>/?page=services" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all <?= $currentPage === 'services' ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50' ?>"><i class="fas fa-th-large w-5 text-center text-gray-400"></i>Services</a>
            <?php if ($hasTeam): ?>
            <a href="<?= $baseUrl ?>/?page=team"     class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all <?= $currentPage === 'team'     ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50' ?>"><i class="fas fa-users w-5 text-center text-gray-400"></i>Team</a>
            <?php endif; ?>
            <?php if ($hasCareers): ?>
            <a href="<?= $baseUrl ?>/?page=careers"  class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all <?= $currentPage === 'careers'  ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50' ?>"><i class="fas fa-briefcase w-5 text-center text-gray-400"></i>Careers</a>
            <?php endif; ?>
            <a href="<?= $baseUrl ?>/?page=contact"  class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all <?= $currentPage === 'contact'  ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50' ?>"><i class="fas fa-envelope w-5 text-center text-gray-400"></i>Contact</a>
            <a href="<?= $baseUrl ?>/?page=track"    class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all <?= $currentPage === 'track'    ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50' ?>"><i class="fas fa-search w-5 text-center text-gray-400"></i>Track Status</a>
            <a href="<?= $baseUrl ?>/?page=estimator" class="mt-2 flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold">
                <i class="fas fa-calculator"></i> Get Quote
            </a>
        </nav>
    </div>
</header>

<style>
.gradient-text {
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #06b6d4 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
</style>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('menuIcon');
    const isHidden = menu.classList.toggle('hidden');
    icon.className = isHidden ? 'fas fa-bars text-2xl' : 'fas fa-times text-2xl';
}
// Close menu when a link is clicked
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#mobileMenu a').forEach(function(link) {
        link.addEventListener('click', function() {
            document.getElementById('mobileMenu').classList.add('hidden');
            document.getElementById('menuIcon').className = 'fas fa-bars text-2xl';
        });
    });
});
</script>
