<?php 
require_once __DIR__ . '/../../includes/config/database.php';

$logo = getSetting('company_logo', '');
$logoLarge = getSetting('company_logo_large', '');
$favicon = getSetting('company_favicon', '');

$headerLogo = $logo ?: $logoLarge;
$faviconUrl = $favicon ?: '';

$heroTitle = getFeature('hero_title', 'Transform Your Business with Intelligent Software');
$heroSubtitle = getFeature('hero_subtitle', 'We develop highly modular, colorful, and responsive management software. Specializing in offline-capable applications tailored precisely for your organizational structure.');
$ctaTitle = getFeature('cta_title', 'Ready to Transform Your Business?');
$ctaSubtitle = getFeature('cta_subtitle', 'Get a free estimate for your project. Our team will analyze your requirements and provide a customized quote.');

$baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/sohojweb';

$services = getServices();
$stats = getStats();
$whyChoose = getWhyChoose();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= getSetting('seo_home_title', 'SohojWeb - Building Smart Ecosystems') ?></title>
    <meta name="description" content="<?= getSetting('seo_home_description', 'We develop highly modular, colorful, and responsive management software.') ?>">
    <?php if($faviconUrl): ?>
    <link rel="icon" type="image/*" href="<?= escape($faviconUrl) ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 
                        primary: {'50': '#eff6ff', '100': '#dbeafe', '500': '#3b82f6', '600': '#2563eb', '700': '#1d4ed8'},
                        tech: {'50': '#f0f9ff', '100': '#e0f2fe', '200': '#bae6fd', '300': '#7dd3fc', '400': '#38bdf8', '500': '#0ea5e9'}
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-text {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #06b6d4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-gradient {
            background: linear-gradient(135deg, #eff6ff 0%, #e0f2fe 50%, #f0f9ff 100%);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(59, 130, 246, 0.15);
        }
        .glow {
            animation: glow 2s ease-in-out infinite alternate;
        }
        @keyframes glow {
            from { box-shadow: 0 0 10px rgba(59, 130, 246, 0.3); }
            to { box-shadow: 0 0 25px rgba(59, 130, 246, 0.6); }
        }
        .float {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        .tech-grid {
            background-image: linear-gradient(rgba(59, 130, 246, 0.05) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(59, 130, 246, 0.05) 1px, transparent 1px);
            background-size: 50px 50px;
        }
    </style>
</head>
<body class="font-sans text-gray-800 overflow-x-hidden">
    
    <?php include __DIR__ . '/../header.php'; ?>

    <!-- Hero Section -->
    <section class="pt-28 pb-20 hero-gradient tech-grid min-h-screen flex items-center relative overflow-hidden">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute top-20 left-10 w-72 h-72 bg-blue-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse-slow"></div>
            <div class="absolute top-40 right-10 w-72 h-72 bg-purple-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse-slow" style="animation-delay: 1s;"></div>
            <div class="absolute bottom-20 left-1/3 w-72 h-72 bg-cyan-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse-slow" style="animation-delay: 2s;"></div>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div data-aos="fade-right" data-aos-duration="1000">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 rounded-full text-blue-600 text-sm font-medium mb-6">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        Building Smart Ecosystems
                    </div>
                    <h1 class="text-5xl lg:text-6xl font-bold text-gray-900 mb-6 leading-tight">
                        <?= nl2br(escape($heroTitle)) ?>
                    </h1>
                    <p class="text-xl text-gray-600 mb-8 leading-relaxed">
                        <?= nl2br(escape($heroSubtitle)) ?>
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a href="<?= $baseUrl ?>/?page=estimator" class="px-8 py-4 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-2xl font-bold text-lg hover:shadow-2xl hover:shadow-blue-500/30 transition-all transform hover:scale-105 flex items-center gap-3">
                            <i class="fas fa-rocket"></i> Get Free Estimate
                        </a>
                        <a href="<?= $baseUrl ?>/?page=portfolio" class="px-8 py-4 border-2 border-gray-200 rounded-2xl font-semibold text-gray-700 hover:border-blue-500 hover:text-blue-600 transition-all flex items-center gap-3">
                            <i class="fas fa-eye"></i> View Projects
                        </a>
                    </div>
                    
                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-6 mt-12 pt-8 border-t border-gray-200">
                        <?php foreach ($stats as $stat): ?>
                        <div data-aos="fade-up" data-aos-delay="<?= $stat['stat_order'] * 100 ?>">
                            <p class="text-3xl font-bold gradient-text"><?= escape($stat['stat_value']) ?></p>
                            <p class="text-gray-500"><?= escape($stat['stat_label']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="relative" data-aos="fade-left" data-aos-duration="1000">
                    <div class="relative h-[500px]">
                        <?php 
                        $moduleIcons = [
                            'Education System' => ['fas fa-graduation-cap', 'from-blue-500 to-cyan-500'],
                            'Company HR/ERP' => ['fas fa-users', 'from-green-500 to-emerald-500'],
                            'Shop / POS' => ['fas fa-shopping-cart', 'from-orange-500 to-red-500'],
                            'Hospital System' => ['fas fa-hospital', 'from-purple-500 to-pink-500']
                        ];
                        $moduleNames = ['Education System' => 'Education System', 'HR / ERP' => 'Company HR/ERP', 'POS' => 'Shop / POS', 'Hospital' => 'Hospital System'];
                        $positions = [
                            ['top-0 right-0', '0s'],
                            ['top-40 left-0', '0.5s'],
                            ['bottom-20 right-10', '1s'],
                            ['bottom-0 left-20', '1.5s']
                        ];
                        $i = 0;
                        foreach ($moduleNames as $name => $type):
                            $iconData = $moduleIcons[$type] ?? ['fas fa-cube', 'from-blue-500 to-purple-500'];
                        ?>
                        <div class="absolute <?= $positions[$i][0] ?> bg-white p-6 rounded-2xl shadow-2xl float" style="animation-delay: <?= $positions[$i][1] ?>;">
                            <div class="w-14 h-14 bg-gradient-to-br <?= $iconData[1] ?> rounded-xl flex items-center justify-center mb-3">
                                <i class="<?= $iconData[0] ?> text-2xl text-white"></i>
                            </div>
                            <h3 class="font-bold text-gray-800"><?= $name ?></h3>
                        </div>
                        <?php $i++; endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modules Section -->
    <section class="py-24 bg-white relative">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16" data-aos="fade-up">
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-purple-50 rounded-full text-purple-600 text-sm font-medium mb-4">
                    <i class="fas fa-cube"></i> Our Modules
                </div>
                <h2 class="text-4xl font-bold text-gray-900 mb-4">
                    Software <span class="gradient-text">Solutions</span>
                </h2>
                <p class="text-gray-600 max-w-2xl mx-auto text-lg">Explore our comprehensive suite of management software solutions designed for various industries.</p>
            </div>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($services as $service): $colors = ['blue', 'green', 'orange', 'red', 'purple', 'pink']; $color = $colors[$service['service_order'] % count($colors)]; ?>
                <div class="bg-gradient-to-br from-<?= $color ?>-50 to-white p-8 rounded-3xl border border-<?= $color ?>-100 card-hover transition-all duration-300" data-aos="fade-up" data-aos-delay="<?= $service['service_order'] * 100 ?>">
                    <div class="w-16 h-16 bg-gradient-to-br from-<?= $color ?>-500 to-<?= $color ?>-600 rounded-2xl flex items-center justify-center mb-5 shadow-lg shadow-<?= $color ?>-500/30">
                        <i class="<?= escape($service['service_icon']) ?> text-3xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3"><?= escape($service['service_title']) ?></h3>
                    <p class="text-gray-600 mb-4"><?= escape($service['service_description']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Why Choose Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-3xl font-bold mb-12 text-center" data-aos="fade-up">Why Choose Us</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <?php foreach ($whyChoose as $item): $colors = ['blue', 'green', 'purple']; $color = $colors[$item['item_order'] % count($colors)]; ?>
                <div class="bg-white p-8 rounded-3xl shadow-lg card-hover transition-all duration-300" data-aos="fade-up" data-aos-delay="<?= $item['item_order'] * 100 ?>">
                    <div class="w-16 h-16 bg-<?= $color ?>-100 rounded-2xl flex items-center justify-center mb-4">
                        <i class="<?= escape($item['icon']) ?> text-<?= $color ?>-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2"><?= escape($item['title']) ?></h3>
                    <p class="text-gray-600"><?= escape($item['description']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <?php $testimonials = getTestimonials(); if($testimonials): ?>
    <section class="py-24 bg-slate-50 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
        <div class="max-w-7xl mx-auto px-4 relative z-10">
            <div class="text-center mb-16" data-aos="fade-up">
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-50 rounded-full text-yellow-600 text-sm font-medium mb-4">
                    <i class="fas fa-star"></i> Testimonials
                </div>
                <h2 class="text-4xl font-bold text-gray-900 mb-4">What Our <span class="gradient-text">Clients</span> Say</h2>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($testimonials as $idx => $t): $colors = ['blue', 'green', 'purple', 'orange']; $color = $colors[$idx % count($colors)]; ?>
                <div class="bg-white p-8 rounded-3xl shadow-lg border border-gray-100 relative group hover:border-<?= $color ?>-200 transition-all duration-300" data-aos="zoom-in-up">
                    <div class="absolute -top-4 -left-4 w-12 h-12 bg-<?= $color ?>-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-<?= $color ?>-500/30">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    <div class="flex items-center gap-1 text-yellow-400 mb-4 pt-2">
                        <?php for($i=0;$i<$t['rating'];$i++): ?><i class="fas fa-star text-sm"></i><?php endfor; ?>
                    </div>
                    <p class="text-gray-600 italic mb-6 leading-relaxed">"<?= escape($t['testimonial_text']) ?>"</p>
                    <div class="flex items-center gap-4 border-t pt-6">
                        <div class="w-12 h-12 bg-<?= $color ?>-50 rounded-full flex items-center justify-center text-<?= $color ?>-600 font-bold border-2 border-<?= $color ?>-100">
                            <?= strtoupper(substr($t['client_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900"><?= escape($t['client_name']) ?></h4>
                            <p class="text-xs text-gray-500 font-medium"><?= escape($t['client_designation']) ?><?= $t['client_company'] ? ', ' . escape($t['client_company']) : '' ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-blue-600 via-purple-600 to-cyan-600 relative overflow-hidden">
        <div class="absolute inset-0 tech-grid opacity-20"></div>
        <div class="max-w-4xl mx-auto px-4 text-center relative z-10" data-aos="zoom-in">
            <h2 class="text-4xl font-bold text-white mb-4"><?= escape($ctaTitle) ?></h2>
            <p class="text-blue-100 mb-8 text-xl"><?= escape($ctaSubtitle) ?></p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="<?= $baseUrl ?>/?page=estimator" class="px-10 py-5 bg-white text-blue-600 rounded-2xl font-bold text-lg hover:shadow-2xl hover:shadow-white/30 transition-all transform hover:scale-105 flex items-center gap-3">
                    <i class="fas fa-calculator"></i> Get Free Estimate
                </a>
                <a href="<?= $baseUrl ?>/?page=contact" class="px-10 py-5 border-2 border-white text-white rounded-2xl font-bold text-lg hover:bg-white/10 transition-all flex items-center gap-3">
                    <i class="fas fa-phone"></i> Contact Us
                </a>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../footer.php'; ?>

    <script>
        AOS.init({ duration: 800, once: true, offset: 100 });
        
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('shadow-lg');
                header.classList.add('bg-white/95');
            } else {
                header.classList.remove('shadow-lg');
                header.classList.remove('bg-white/95');
            }
        });
    </script>
</body>
</html>
