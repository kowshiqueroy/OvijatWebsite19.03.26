<?php 
require_once __DIR__ . '/../../includes/config/database.php';
$logo = getSetting('company_logo', '');
$logoLarge = getSetting('company_logo_large', '');
$headerLogo = $logo ?: $logoLarge;
$favicon = getSetting('company_favicon', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investment Estimator | SohojWeb</title>
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

    <section class="pt-28 pb-20 bg-gradient-to-br from-blue-50 to-white min-h-screen">
        <div class="max-w-3xl mx-auto px-4">
            <div class="text-center mb-8" data-aos="fade-up">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Investment <span class="gradient-text">Estimator</span></h1>
                <p class="text-xl text-gray-600">Get a quick estimate for your software project</p>
            </div>

            <div class="bg-white rounded-3xl shadow-xl p-8" data-aos="fade-up" data-aos-delay="200">
                <form id="estimatorForm">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Select Primary Software Module *</label>
                        <div class="grid md:grid-cols-2 gap-3">
                            <label class="flex items-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition">
                                <input type="radio" name="module" value="Education System" class="w-4 h-4 text-blue-600" required>
                                <span class="ml-3 font-medium">Education System</span>
                            </label>
                            <label class="flex items-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition">
                                <input type="radio" name="module" value="Offline Shop / POS System" class="w-4 h-4 text-blue-600">
                                <span class="ml-3 font-medium">Shop / POS System</span>
                            </label>
                            <label class="flex items-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition">
                                <input type="radio" name="module" value="Company HR/ERP Portal" class="w-4 h-4 text-blue-600">
                                <span class="ml-3 font-medium">HR/ERP Portal</span>
                            </label>
                            <label class="flex items-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition">
                                <input type="radio" name="module" value="Hospital Management System" class="w-4 h-4 text-blue-600">
                                <span class="ml-3 font-medium">Hospital Management</span>
                            </label>
                            <label class="flex items-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition">
                                <input type="radio" name="module" value="Enterprise Business ERP" class="w-4 h-4 text-blue-600">
                                <span class="ml-3 font-medium">Business ERP</span>
                            </label>
                            <label class="flex items-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition">
                                <input type="radio" name="module" value="E-Commerce Platform" class="w-4 h-4 text-blue-600">
                                <span class="ml-3 font-medium">E-Commerce Platform</span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Complexity Scale (Branches/Departments)</label>
                        <select name="complexity" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                            <option value="1">1 - Single Branch/Department</option>
                            <option value="2">2 - Few Branches (2-3)</option>
                            <option value="3">3 - Multiple Branches (4-10)</option>
                            <option value="4">4 - Large Scale (10+)</option>
                            <option value="5">5 - Enterprise (Multi-location)</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Technical Integrations</label>
                        <div class="grid md:grid-cols-2 gap-3">
                            <label class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" name="integrations[]" value="Offline Sync Engine" class="w-4 h-4 text-blue-600 rounded">
                                <span class="ml-2 text-sm">Offline Sync Engine</span>
                            </label>
                            <label class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" name="integrations[]" value="Hardware Interfacing" class="w-4 h-4 text-blue-600 rounded">
                                <span class="ml-2 text-sm">Hardware Interfacing</span>
                            </label>
                            <label class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" name="integrations[]" value="Payment Gateway" class="w-4 h-4 text-blue-600 rounded">
                                <span class="ml-2 text-sm">Payment Gateway</span>
                            </label>
                            <label class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" name="integrations[]" value="SMS/Email Notifications" class="w-4 h-4 text-blue-600 rounded">
                                <span class="ml-2 text-sm">SMS/Email Notifications</span>
                            </label>
                            <label class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" name="integrations[]" value="API Integration" class="w-4 h-4 text-blue-600 rounded">
                                <span class="ml-2 text-sm">Third-party API</span>
                            </label>
                            <label class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" name="integrations[]" value="Mobile App" class="w-4 h-4 text-blue-600 rounded">
                                <span class="ml-2 text-sm">Mobile App (iOS/Android)</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Your Name *</label>
                            <input type="text" name="client_name" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Company Name</label>
                            <input type="text" name="company_name" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email *</label>
                            <input type="email" name="client_email" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Phone *</label>
                            <input type="tel" name="client_phone" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Additional Message</label>
                        <textarea name="message" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <button type="submit" class="w-full py-4 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-bold text-lg hover:shadow-lg hover:shadow-blue-500/30 transition-all transform hover:scale-[1.02]">
                        <i class="fas fa-calculator mr-2"></i> Get Estimate
                    </button>
                </form>

                <div id="result" class="hidden mt-8 p-6 bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-2xl text-center">
                    <div class="text-4xl font-bold text-green-600 mb-2">৳ <span id="estimatedAmount">70,000</span></div>
                    <p class="text-green-700 text-sm">Note: Prices are estimates. Final cost is determined after thorough requirement analysis.</p>
                </div>
            </div>

            <div class="mt-8 text-center" data-aos="fade-up">
                <a href="<?= $baseUrl ?>/?page=contact" class="text-blue-600 hover:underline">Need immediate assistance? Contact us directly</a>
            </div>
        </div>
    </section>

    <script>
    const AJAX_URL = '<?= BASE_URL ?>/admin/ajax/save-lead.php';
        document.getElementById('estimatorForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Calculating...';

            const formData = new FormData(this);
            formData.append('lead_source', 'investment_estimator');

            fetch(AJAX_URL, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('result').classList.remove('hidden');
                    document.getElementById('estimatedAmount').textContent = data.estimate;
                    window.scrollTo({ top: document.getElementById('result').offsetTop, behavior: 'smooth' });
                    Swal.fire('Success!', 'We\'ve received your request. Our team will contact you soon!', 'success');
                } else {
                    Swal.fire('Error', data.message || 'Something went wrong', 'error');
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
