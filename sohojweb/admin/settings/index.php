<?php
require_once __DIR__ . '/../../includes/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$canEdit = hasPermission('super_admin');

$action = $_GET['action'] ?? 'general';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_settings') {
    header('Content-Type: application/json');
    if (!hasPermission('super_admin')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        foreach ($_POST['settings'] as $key => $value) {
            db()->query("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$key, $value, $value]);
        }
        logAudit('settings_updated', 'site_settings', null, null, array_keys($_POST['settings']));
        jsonResponse(['success' => true, 'message' => 'Settings saved']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

$settings = db()->select("SELECT * FROM site_settings");
$settingsMap = [];
foreach ($settings as $s) {
    $settingsMap[$s['setting_key']] = $s['setting_value'];
}

$pageTitle = 'Settings | SohojWeb Admin';
include __DIR__ . '/../header.php';
?>

<main class="p-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Settings</h1>
    
    <div class="flex gap-6">
        <!-- Sidebar -->
        <div class="w-48 shrink-0">
            <div class="bg-white rounded-xl shadow-sm border p-2">
                <a href="?action=general" class="block px-4 py-2 rounded-lg <?= $action === 'general' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">General</a>
                <a href="?action=logos" class="block px-4 py-2 rounded-lg <?= $action === 'logos' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">Logos</a>
                <a href="?action=seo" class="block px-4 py-2 rounded-lg <?= $action === 'seo' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">SEO</a>
                <a href="?action=backup" class="block px-4 py-2 rounded-lg <?= $action === 'backup' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">Backup</a>
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-1">
            <?php if ($action === 'general'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h2 class="text-lg font-semibold mb-6">General Settings</h2>
                <form id="settingsForm">
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                            <input type="text" name="settings[company_name]" value="<?= escape($settingsMap['company_name'] ?? 'SOHOJWEB') ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Company Email</label>
                            <input type="email" name="settings[company_email]" value="<?= escape($settingsMap['company_email'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Company Phone</label>
                            <input type="text" name="settings[company_phone]" value="<?= escape($settingsMap['company_phone'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Currency Symbol</label>
                            <input type="text" name="settings[currency_symbol]" value="<?= escape($settingsMap['currency_symbol'] ?? '৳') ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Prefix</label>
                            <input type="text" name="settings[invoice_prefix]" value="<?= escape($settingsMap['invoice_prefix'] ?? 'INV') ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quotation Prefix</label>
                            <input type="text" name="settings[quotation_prefix]" value="<?= escape($settingsMap['quotation_prefix'] ?? 'QUO') ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Default Tax Rate (%)</label>
                            <input type="number" name="settings[tax_rate]" value="<?= $settingsMap['tax_rate'] ?? 0 ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Company Address</label>
                        <textarea name="settings[company_address]" rows="3" class="w-full px-3 py-2 border rounded-lg"><?= escape($settingsMap['company_address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default Invoice Terms</label>
                        <textarea name="settings[invoice_terms]" rows="2" class="w-full px-3 py-2 border rounded-lg"><?= escape($settingsMap['invoice_terms'] ?? 'Payment due within 30 days.') ?></textarea>
                    </div>
                    
                    <?php if ($canEdit): ?>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Settings</button>
                    <?php else: ?>
                    <div class="flex items-center gap-3 px-4 py-2 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-700 text-sm">
                        <i class="fas fa-lock"></i> Read-only — super admin access required to save settings.
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <?php elseif ($action === 'logos'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h2 class="text-lg font-semibold mb-6">Logo & Favicon Settings</h2>
                
                <div class="grid md:grid-cols-3 gap-6">
                    <!-- Favicon -->
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center">
                        <h3 class="font-medium mb-3">Favicon</h3>
                        <div class="w-16 h-16 mx-auto mb-3 bg-gray-100 rounded-lg flex items-center justify-center">
                            <?php if (!empty($settingsMap['company_favicon'])): ?>
                            <img src="<?= escape($settingsMap['company_favicon']) ?>" class="w-12 h-12 object-contain">
                            <?php else: ?>
                            <i class="fas fa-image text-gray-400 text-2xl"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="faviconInput" accept="image/*" class="hidden">
                        <button onclick="document.getElementById('faviconInput').click()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">Upload</button>
                        <p class="text-xs text-gray-500 mt-2">16x16 or 32x32 PNG</p>
                    </div>
                    
                    <!-- Logo -->
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center">
                        <h3 class="font-medium mb-3">Standard Logo</h3>
                        <div class="w-32 h-16 mx-auto mb-3 bg-gray-100 rounded-lg flex items-center justify-center">
                            <?php if (!empty($settingsMap['company_logo'])): ?>
                            <img src="<?= escape($settingsMap['company_logo']) ?>" class="max-h-14 max-w-28 object-contain">
                            <?php else: ?>
                            <i class="fas fa-image text-gray-400 text-2xl"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="logoInput" accept="image/*" class="hidden">
                        <button onclick="document.getElementById('logoInput').click()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">Upload</button>
                        <p class="text-xs text-gray-500 mt-2">Recommended: 200x50 PNG</p>
                    </div>
                    
                    <!-- Large Logo -->
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center">
                        <h3 class="font-medium mb-3">Large Logo</h3>
                        <div class="w-40 h-20 mx-auto mb-3 bg-gray-100 rounded-lg flex items-center justify-center">
                            <?php if (!empty($settingsMap['company_logo_large'])): ?>
                            <img src="<?= escape($settingsMap['company_logo_large']) ?>" class="max-h-18 max-w-36 object-contain">
                            <?php else: ?>
                            <i class="fas fa-image text-gray-400 text-2xl"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="logoLargeInput" accept="image/*" class="hidden">
                        <button onclick="document.getElementById('logoLargeInput').click()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">Upload</button>
                        <p class="text-xs text-gray-500 mt-2">For hero sections</p>
                    </div>
                </div>
                
                <div id="uploadStatus" class="mt-4 text-center text-sm"></div>
            </div>
            
            <?php elseif ($action === 'seo'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h2 class="text-lg font-semibold mb-6">SEO Settings</h2>
                <form id="seoForm">
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Home Page Title</label>
                        <input type="text" name="settings[seo_home_title]" value="<?= escape($settingsMap['seo_home_title'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg" placeholder="SohojWeb - Building Smart Ecosystems">
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Home Page Meta Description</label>
                        <textarea name="settings[seo_home_description]" rows="3" class="w-full px-3 py-2 border rounded-lg" placeholder="We develop highly modular, responsive management software..."></textarea>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Keywords</label>
                        <input type="text" name="settings[seo_keywords]" value="<?= escape($settingsMap['seo_keywords'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg" placeholder="ERP, POS, School Management, Hospital Management">
                    </div>
                    <?php if ($canEdit): ?>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save SEO Settings</button>
                    <?php else: ?>
                    <div class="flex items-center gap-3 px-4 py-2 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-700 text-sm">
                        <i class="fas fa-lock"></i> Read-only — super admin access required.
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php elseif ($action === 'backup'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h2 class="text-lg font-semibold mb-6">Database Backup</h2>
                <p class="text-gray-600 mb-6">Create a backup of your database. The backup will be downloaded as a .sql file.</p>
                <a href="backup.php" class="inline-flex items-center px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-download mr-2"></i> Download Backup
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;

document.getElementById('settingsForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Saving...';

    fetch('index.php?action=save_settings', {method: 'POST', body: new FormData(this)})
    .then(res => res.json())
    .then(data => { 
        if(data.success) alert('Settings saved!'); else alert(data.message); 
        btn.disabled = false;
        btn.innerText = originalText;
    })
    .catch(err => {
        alert('Error: ' + err.message);
        btn.disabled = false;
        btn.innerText = originalText;
    });
});
document.getElementById('seoForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Saving...';

    fetch('index.php?action=save_settings', {method: 'POST', body: new FormData(this)})
    .then(res => res.json())
    .then(data => { 
        if(data.success) alert('SEO Settings saved!'); else alert(data.message); 
        btn.disabled = false;
        btn.innerText = originalText;
    })
    .catch(err => {
        alert('Error: ' + err.message);
        btn.disabled = false;
        btn.innerText = originalText;
    });
});

// Logo upload handlers
function uploadLogo(type, fileInput) {
    const file = fileInput.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', type);
    
    fetch('../ajax/upload-logo.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('uploadStatus').innerHTML = '<span class="text-green-600">Logo uploaded successfully! Refreshing...</span>';
            setTimeout(() => location.reload(), 1500);
        } else {
            document.getElementById('uploadStatus').innerHTML = '<span class="text-red-600">' + data.message + '</span>';
        }
    })
    .catch(err => {
        document.getElementById('uploadStatus').innerHTML = '<span class="text-red-600">Upload failed</span>';
    });
}

document.getElementById('faviconInput')?.addEventListener('change', function() { uploadLogo('favicon', this); });
document.getElementById('logoInput')?.addEventListener('change', function() { uploadLogo('logo', this); });
document.getElementById('logoLargeInput')?.addEventListener('change', function() { uploadLogo('logo_large', this); });
</script>
<?php include __DIR__ . '/../footer.php'; ?>
