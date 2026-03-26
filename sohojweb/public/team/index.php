<?php 
require_once __DIR__ . '/../../includes/config/database.php';

$logo = getSetting('company_logo', '');
$logoLarge = getSetting('company_logo_large', '');
$favicon = getSetting('company_favicon', '');
$headerLogo = $logo ?: $logoLarge;

$team = getTeam();
$pageTitle = 'Our Team | SohojWeb';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <?php if($favicon): ?><link rel="icon" type="image/*" href="<?= escape($favicon) ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: {'50': '#eff6ff', '500': '#3b82f6', '600': '#2563eb'} },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-text { background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #06b6d4 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="font-sans text-gray-800 bg-slate-50">
    <?php include __DIR__ . '/../header.php'; ?>

    <section class="pt-32 pb-16 bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 text-center" data-aos="fade-up">
            <h1 class="text-5xl font-bold text-gray-900 mb-4">Meet Our <span class="gradient-text">Experts</span></h1>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">The passionate individuals dedicated to building your smart business ecosystems.</p>
        </div>
    </section>

    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4">
            <?php if (empty($team)): ?>
                <div class="text-center py-20 bg-white rounded-3xl border border-dashed border-gray-300">
                    <i class="fas fa-users text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-900">No team members found</h3>
                    <p class="text-gray-500">Our team is currently being updated. Please check back soon.</p>
                </div>
            <?php else: ?>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-10">
                    <?php foreach ($team as $member): $colors = ['blue', 'indigo', 'purple', 'violet']; $color = $colors[$member['member_order'] % count($colors)]; ?>
                    <div class="group" data-aos="fade-up" data-aos-delay="<?= $member['member_order'] * 100 ?>">
                        <div class="relative mb-6">
                            <div class="aspect-square rounded-[2.5rem] overflow-hidden bg-<?= $color ?>-50 flex items-center justify-center border-4 border-white shadow-xl transition-all duration-500 group-hover:rounded-3xl">
                                <?php if ($member['member_image']): ?>
                                    <img src="<?= escape($member['member_image']) ?>" alt="<?= escape($member['member_name']) ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                                <?php else: ?>
                                    <div class="text-<?= $color ?>-600 font-bold text-5xl">
                                        <?= strtoupper(substr($member['member_name'], 0, 1)) . strtoupper(substr(explode(' ', $member['member_name'])[1] ?? '', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Social Overlay -->
                                <div class="absolute inset-0 bg-<?= $color ?>-600/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center gap-3 backdrop-blur-[2px]">
                                    <?php if ($member['member_email']): ?>
                                        <a href="mailto:<?= escape($member['member_email']) ?>" class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-<?= $color ?>-600 hover:bg-<?= $color ?>-600 hover:text-white transition-all shadow-lg"><i class="fas fa-envelope"></i></a>
                                    <?php endif; ?>
                                    <?php if ($member['member_facebook']): ?>
                                        <a href="<?= escape($member['member_facebook']) ?>" target="_blank" class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-<?= $color ?>-600 hover:bg-<?= $color ?>-600 hover:text-white transition-all shadow-lg"><i class="fab fa-facebook-f"></i></a>
                                    <?php endif; ?>
                                    <?php if ($member['member_linkedin']): ?>
                                        <a href="<?= escape($member['member_linkedin']) ?>" target="_blank" class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-<?= $color ?>-600 hover:bg-<?= $color ?>-600 hover:text-white transition-all shadow-lg"><i class="fab fa-linkedin-in"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <h3 class="text-2xl font-bold text-gray-900 group-hover:text-<?= $color ?>-600 transition-colors"><?= escape($member['member_name']) ?></h3>
                            <p class="text-<?= $color ?>-600 font-semibold mb-3"><?= escape($member['designation']) ?></p>
                            <p class="text-gray-500 text-sm leading-relaxed line-clamp-3 px-2"><?= escape($member['bio']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Join Us CTA -->
    <section class="py-20">
        <div class="max-w-5xl mx-auto px-4">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-[3rem] p-12 text-center text-white relative overflow-hidden shadow-2xl">
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2 blur-3xl"></div>
                <div class="relative z-10" data-aos="zoom-in">
                    <h2 class="text-4xl font-bold mb-4">Want to Join Our Team?</h2>
                    <p class="text-blue-100 text-xl mb-8">We're always looking for talented and passionate people to join us.</p>
                    <a href="<?= BASE_URL ?>/?page=careers" class="px-10 py-4 bg-white text-blue-600 rounded-2xl font-bold text-lg hover:shadow-xl transition-all transform hover:scale-105 inline-block">
                        View Openings
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../footer.php'; ?>
    <script>AOS.init({ duration: 800, once: true });</script>
</body>
</html>
