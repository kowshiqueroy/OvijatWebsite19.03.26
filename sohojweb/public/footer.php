<?php 
require_once __DIR__ . '/../includes/config/database.php';
$logo = getSetting('company_logo', '');
$logoLarge = getSetting('company_logo_large', '');
$footerLogo = $logo ?: $logoLarge;
$companyName = getSetting('company_name', 'SohojWeb');
$baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/sohojweb';
$contactInfo = getContactInfo();
$socialLinks = getSocialLinks();
$services = getServices();
?>
<footer class="bg-gray-900 text-white py-16">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid md:grid-cols-4 gap-8 mb-12">
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <?php if ($footerLogo): ?>
                    <img src="<?= escape($footerLogo) ?>" alt="Logo" class="h-12">
                    <?php else: ?>
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-xl">S</span>
                    </div>
                    <span class="font-bold text-xl">SOHOJWEB</span>
                    <?php endif; ?>
                </div>
                <p class="text-gray-400">Building smart ecosystems with modular, responsive management software.</p>
            </div>
            <div>
                <h4 class="font-bold mb-4">Quick Links</h4>
                <ul class="space-y-2 text-gray-400">
                    <li><a href="<?= $baseUrl ?>/?page=about" class="hover:text-white transition">About Us</a></li>
                    <li><a href="<?= $baseUrl ?>/?page=services" class="hover:text-white transition">Services</a></li>
                    <li><a href="<?= $baseUrl ?>/?page=portfolio" class="hover:text-white transition">Portfolio</a></li>
                    <li><a href="<?= $baseUrl ?>/?page=contact" class="hover:text-white transition">Contact</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold mb-4">Services</h4>
                <ul class="space-y-2 text-gray-400">
                    <?php foreach (array_slice($services, 0, 4) as $service): ?>
                    <li><?= escape($service['service_title']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h4 class="font-bold mb-4">Contact</h4>
                <ul class="space-y-3 text-gray-400">
                    <?php foreach ($contactInfo as $info): ?>
                    <?php if ($info['info_type'] === 'whatsapp'): ?>
                    <li><a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $info['info_value']) ?>" target="_blank" class="flex items-center gap-3 text-green-400 hover:text-green-300"><i class="<?= escape($info['info_icon']) ?>"></i> WhatsApp</a></li>
                    <?php else: ?>
                    <li class="flex items-center gap-3"><i class="<?= escape($info['info_icon']) ?> text-blue-400"></i> <?= escape($info['info_value']) ?></li>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-800 pt-8 flex justify-between items-center flex-wrap gap-4">
            <p class="text-gray-400">&copy; 2026 <?= escape($companyName) ?>. All rights reserved.</p>
            <div class="flex gap-4">
                <?php foreach ($socialLinks as $social): ?>
                <a href="<?= escape($social['profile_url']) ?>" target="_blank" class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-blue-600 transition">
                    <i class="fab fa-<?= escape($social['platform']) ?>"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</footer>
