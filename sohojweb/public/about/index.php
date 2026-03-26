<?php 
require_once __DIR__ . '/../../includes/config/database.php';

$logo = getSetting('company_logo', '');
$logoLarge = getSetting('company_logo_large', '');
$headerLogo = $logo ?: $logoLarge;
$favicon = getSetting('company_favicon', '');

$aboutTitle = getFeature('about_title', 'About SohojWeb');
$aboutSubtitle = getFeature('about_subtitle', 'We are a leading software development company specializing in custom web and mobile applications.');
$aboutMission = getFeature('about_mission', 'To empower businesses with innovative, efficient, and user-friendly software solutions that drive growth and streamline operations.');
$aboutVision = getFeature('about_vision', 'To become the most trusted technology partner for businesses seeking digital transformation across Bangladesh and beyond.');

$stats = getStats();
$whyChoose = getWhyChoose();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($aboutTitle) ?> | SohojWeb</title>
    <meta name="description" content="<?= escape($aboutSubtitle) ?>">
    <?php if($favicon): ?><link rel="icon" type="image/*" href="<?= escape($favicon) ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 
                        primary: {'50': '#eff6ff', '500': '#3b82f6', '600': '#2563eb'},
                        tech: {'50': '#f0f9ff', '500': '#0ea5e9'}
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
        .card-hover:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="font-sans text-gray-800">
    <?php include __DIR__ . '/../header.php'; ?>

    <section class="pt-28 pb-16 bg-gradient-to-br from-blue-50 to-white">
        <div class="max-w-7xl mx-auto px-4 text-center" data-aos="fade-up">
            <h1 class="text-5xl font-bold text-gray-900 mb-4"><?= escape($aboutTitle) ?></h1>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto"><?= escape($aboutSubtitle) ?></p>
        </div>
    </section>

    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div data-aos="fade-right">
                    <h2 class="text-3xl font-bold mb-6">Our Mission</h2>
                    <p class="text-gray-600 text-lg mb-8"><?= escape($aboutMission) ?></p>
                    <h2 class="text-3xl font-bold mb-6">Our Vision</h2>
                    <p class="text-gray-600 text-lg"><?= escape($aboutVision) ?></p>
                </div>
                <div class="grid grid-cols-2 gap-4" data-aos="fade-left">
                    <?php foreach ($stats as $stat): $colors = ['blue', 'green', 'orange', 'purple']; $color = $colors[$stat['stat_order'] % count($colors)]; ?>
                    <div class="bg-gradient-to-br from-<?= $color ?>-500 to-<?= $color ?>-600 text-white p-8 rounded-3xl text-center">
                        <p class="text-4xl font-bold"><?= escape($stat['stat_value']) ?></p>
                        <p class="text-<?= $color ?>-100"><?= escape($stat['stat_label']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-3xl font-bold mb-12 text-center" data-aos="fade-up">Why Choose Us</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <?php foreach ($whyChoose as $item): $colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink']; $color = $colors[$item['item_order'] % count($colors)]; ?>
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

    <?php include __DIR__ . '/../footer.php'; ?>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });
    </script>
</body>
</html>
