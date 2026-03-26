<?php 
require_once __DIR__ . '/../../includes/config/database.php';

$logo = getSetting('company_logo', '');
$logoLarge = getSetting('company_logo_large', '');
$headerLogo = $logo ?: $logoLarge;
$favicon = getSetting('company_favicon', '');

$projects = db()->select("SELECT * FROM projects WHERE show_in_portfolio = 1 ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio | SohojWeb</title>
    <meta name="description" content="Explore our completed projects and success stories.">
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
            <h1 class="text-5xl font-bold text-gray-900 mb-4">Our <span class="gradient-text">Projects</span></h1>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">Explore our completed projects and success stories across various industries.</p>
        </div>
    </section>

    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4">
            <?php if (empty($projects)): ?>
            <div class="text-center py-12">
                <i class="fas fa-folder-open text-5xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No projects to display yet.</p>
            </div>
            <?php else: ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($projects as $project): 
                    $colors = ['blue', 'green', 'orange', 'red', 'purple', 'pink', 'teal', 'indigo'];
                    $color = $colors[$project['id'] % count($colors)];
                    $icons = [
                        'Education System' => 'fas fa-graduation-cap',
                        'Hospital Management' => 'fas fa-hospital',
                        'HR' => 'fas fa-users',
                        'ERP' => 'fas fa-building',
                        'POS' => 'fas fa-shopping-cart',
                        'E-Commerce' => 'fas fa-store'
                    ];
                    $icon = 'fas fa-folder';
                    foreach ($icons as $key => $ic) {
                        if (stripos($project['project_name'], $key) !== false || stripos($project['description'] ?? '', $key) !== false) {
                            $icon = $ic;
                            break;
                        }
                    }
                ?>
                <div class="bg-white rounded-3xl shadow-lg border overflow-hidden card-hover transition-all duration-300" data-aos="fade-up" data-aos-delay="<?= ($project['id'] % 3 + 1) * 100 ?>">
                    <div class="h-48 bg-gradient-to-r from-<?= $color ?>-500 to-<?= $color ?>-700 flex items-center justify-center">
                        <i class="<?= $icon ?> text-5xl text-white"></i>
                    </div>
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs text-gray-500"><?= escape($project['project_code']) ?></span>
                            <span class="px-2 py-1 text-xs rounded-full <?= $project['status'] === 'completed' ? 'bg-green-100 text-green-700' : ($project['status'] === 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700') ?>">
                                <?= ucwords(str_replace('_', ' ', $project['status'])) ?>
                            </span>
                        </div>
                        <h3 class="text-xl font-bold mb-2"><?= escape($project['project_name']) ?></h3>
                        <p class="text-gray-600 text-sm mb-3"><?= escape($project['client_name'] ?: 'Internal Project') ?></p>
                        <?php if ($project['description']): ?>
                        <p class="text-gray-500 text-sm mb-3"><?= escape(substr($project['description'], 0, 80)) ?><?= strlen($project['description']) > 80 ? '...' : '' ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="py-16 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 text-center" data-aos="fade-up">
            <h2 class="text-2xl font-bold mb-4">Have a Project in Mind?</h2>
            <p class="text-gray-600 mb-6">Let's discuss your requirements and build something amazing together.</p>
            <a href="<?= $baseUrl ?>/?page=contact" class="inline-block px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-medium hover:shadow-lg transition-all">Start a Project</a>
        </div>
    </section>

    <?php include __DIR__ . '/../footer.php'; ?>
    <script>AOS.init({ duration: 800, once: true });</script>
</body>
</html>
