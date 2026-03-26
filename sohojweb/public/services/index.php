<?php 
require_once __DIR__ . '/../../includes/config/database.php';

$logo = getSetting('company_logo', '');
$logoLarge = getSetting('company_logo_large', '');
$headerLogo = $logo ?: $logoLarge;
$favicon = getSetting('company_favicon', '');

$ctaTitle = getFeature('cta_title', 'Need a Custom Solution?');
$ctaSubtitle = getFeature('cta_subtitle', 'Contact us today for a free consultation and quote.');

$services = getServices();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services | SohojWeb</title>
    <meta name="description" content="Professional software development and IT consulting services.">
    <?php if($favicon): ?><link rel="icon" type="image/*" href="<?= escape($favicon) ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { primary: {'50': '#eff6ff', '500': '#3b82f6', '600': '#2563eb'}}, fontFamily: { sans: ['Inter', 'sans-serif'] } } } }</script>
    <style>body { font-family: 'Inter', sans-serif; }.gradient-text { background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #06b6d4 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }.card-hover:hover { transform: translateY(-5px); }</style>
</head>
<body class="font-sans text-gray-800">
    <?php include __DIR__ . '/../header.php'; ?>

    <section class="pt-28 pb-16 bg-gradient-to-br from-blue-50 to-white">
        <div class="max-w-7xl mx-auto px-4 text-center" data-aos="fade-up">
            <h1 class="text-5xl font-bold text-gray-900 mb-4">Our <span class="gradient-text">Services</span></h1>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">Professional software development and IT consulting services tailored to your business needs.</p>
        </div>
    </section>

    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($services as $service): $colors = ['blue', 'green', 'orange', 'red', 'purple', 'pink', 'teal', 'indigo']; $color = $colors[$service['service_order'] % count($colors)]; ?>
                <div class="bg-white p-8 rounded-3xl shadow-lg border hover:shadow-2xl transition-all duration-300 card-hover" data-aos="fade-up" data-aos-delay="<?= $service['service_order'] * 100 ?>">
                    <div class="w-16 h-16 bg-gradient-to-br from-<?= $color ?>-500 to-<?= $color ?>-600 rounded-2xl flex items-center justify-center mb-4">
                        <i class="<?= escape($service['service_icon']) ?> text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2"><?= escape($service['service_title']) ?></h3>
                    <p class="text-gray-600"><?= escape($service['service_description']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-16 bg-gradient-to-r from-blue-600 via-purple-600 to-cyan-600">
        <div class="max-w-4xl mx-auto px-4 text-center" data-aos="zoom-in">
            <h2 class="text-3xl font-bold text-white mb-4"><?= escape($ctaTitle) ?></h2>
            <p class="text-blue-100 mb-8 text-lg"><?= escape($ctaSubtitle) ?></p>
            <a href="/?page=contact" class="inline-block px-8 py-4 bg-white text-blue-600 rounded-2xl font-bold hover:shadow-2xl transition-all transform hover:scale-105">Get In Touch</a>
        </div>
    </section>

    <?php include __DIR__ . '/../footer.php'; ?>
    <script>AOS.init({ duration: 800, once: true });</script>
</body>
</html>
