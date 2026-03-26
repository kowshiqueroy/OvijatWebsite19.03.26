<?php 
require_once __DIR__ . '/../../includes/config/database.php';

$logo = getSetting('company_logo', '');
$logoLarge = getSetting('company_logo_large', '');
$headerLogo = $logo ?: $logoLarge;
$favicon = getSetting('company_favicon', '');

$contactInfo = getContactInfo();
$socialLinks = getSocialLinks();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact | SohojWeb</title>
    <meta name="description" content="Contact SohojWeb for your software development needs.">
    <?php if($favicon): ?><link rel="icon" type="image/*" href="<?= escape($favicon) ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>tailwind.config = { theme: { extend: { colors: { primary: {'50': '#eff6ff', '500': '#3b82f6', '600': '#2563eb'}}, fontFamily: { sans: ['Inter', 'sans-serif'] } } } }</script>
    <style>body { font-family: 'Inter', sans-serif; }.gradient-text { background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #06b6d4 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }</style>
</head>
<body class="font-sans text-gray-800">
    <?php include __DIR__ . '/../header.php'; ?>

    <section class="pt-28 pb-16 bg-gradient-to-br from-blue-50 to-white">
        <div class="max-w-7xl mx-auto px-4 text-center" data-aos="fade-up">
            <h1 class="text-5xl font-bold text-gray-900 mb-4">Get In <span class="gradient-text">Touch</span></h1>
            <p class="text-xl text-gray-600">We'd love to hear from you. Contact us for any inquiries.</p>
        </div>
    </section>

    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid md:grid-cols-2 gap-12">
                <div data-aos="fade-right">
                    <h2 class="text-2xl font-bold mb-6">Send us a Message</h2>
                    <form id="contactForm" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Your Name *</label>
                            <input type="text" name="client_name" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                <input type="email" name="client_email" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="tel" name="client_phone" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
                            <textarea name="message" rows="5" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <button type="submit" class="w-full py-4 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-bold hover:shadow-lg transition-all transform hover:scale-[1.02]">
                            <i class="fas fa-paper-plane mr-2"></i> Send Message
                        </button>
                    </form>
                </div>
                
                <div data-aos="fade-left">
                    <h2 class="text-2xl font-bold mb-6">Contact Information</h2>
                    <div class="bg-white rounded-3xl shadow-lg border p-8">
                        <?php foreach ($contactInfo as $info): ?>
                        <?php 
                        $color = match($info['info_type']) {
                            'address' => 'blue',
                            'email' => 'green',
                            'phone' => 'purple',
                            'whatsapp' => 'green',
                            default => 'blue'
                        };
                        $isWhatsapp = $info['info_type'] === 'whatsapp';
                        ?>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-14 h-14 bg-<?= $color ?>-100 rounded-2xl flex items-center justify-center">
                                <i class="<?= escape($info['info_icon']) ?> text-<?= $color ?>-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="font-medium"><?= ucfirst(escape($info['info_type'])) ?></p>
                                <?php if ($isWhatsapp): ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $info['info_value']) ?>" target="_blank" class="text-<?= $color ?>-600 text-sm hover:underline"><?= escape($info['info_value']) ?></a>
                                <?php else: ?>
                                <p class="text-gray-600 text-sm"><?= escape($info['info_value']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-6">
                        <h3 class="font-semibold mb-3">Follow Us</h3>
                        <div class="flex gap-4">
                            <?php foreach ($socialLinks as $social): ?>
                            <a href="<?= escape($social['profile_url']) ?>" target="_blank" class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl flex items-center justify-center hover:shadow-lg transition">
                                <i class="fab fa-<?= escape($social['platform']) ?>"></i>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
    const AJAX_URL = '<?= BASE_URL ?>/admin/ajax/save-lead.php';
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';

            const formData = new FormData(this);
            formData.append('lead_source', 'contact_form');

            fetch(AJAX_URL, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', 'Message sent successfully! We\'ll contact you soon.', 'success');
                    this.reset();
                } else {
                    Swal.fire('Error', data.message || 'Failed to send message', 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'An error occurred', 'error'))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            });
        });
    </script>

    <?php include __DIR__ . '/../footer.php'; ?>
    <script>AOS.init({ duration: 800, once: true });</script>
</body>
</html>
