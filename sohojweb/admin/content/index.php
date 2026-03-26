<?php
require_once __DIR__ . '/../../includes/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$canEdit   = hasPermission('editor');
$canDelete = hasPermission('editor');

$section = $_GET['section'] ?? 'features';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json');

    // CSRF Check
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        jsonResponse(['success' => false, 'message' => 'CSRF Token Mismatch'], 403);
    }

    // Permission check
    $postAction = $_POST['action'] ?? '';
    if (strpos($postAction, 'delete_') === 0) {
        if (!hasPermission('editor')) {
            jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
        }
    } else {
        if (!hasPermission('editor')) {
            jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
        }
    }
    
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save_testimonial') {
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
            $data = [
                'client_name' => sanitize($_POST['client_name']),
                'client_designation' => sanitize($_POST['client_designation'] ?? ''),
                'client_company' => sanitize($_POST['client_company'] ?? ''),
                'rating' => (int)$_POST['rating'],
                'testimonial_text' => sanitize($_POST['testimonial_text']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            if ($id) {
                db()->update('site_testimonials', $data, 'id = :id', ['id' => $id]);
                jsonResponse(['success' => true, 'message' => 'Testimonial updated']);
            } else {
                db()->insert('site_testimonials', $data);
                jsonResponse(['success' => true, 'message' => 'Testimonial added']);
            }
        }
        
        if ($action === 'delete_testimonial') {
            db()->delete('site_testimonials', 'id = :id', ['id' => (int)$_POST['id']]);
            jsonResponse(['success' => true]);
        }

        if ($action === 'save_team') {
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
            $data = [
                'member_name' => sanitize($_POST['member_name']),
                'designation' => sanitize($_POST['designation']),
                'bio' => sanitize($_POST['bio'] ?? ''),
                'member_email' => sanitize($_POST['member_email'] ?? ''),
                'member_facebook' => sanitize($_POST['member_facebook'] ?? ''),
                'member_linkedin' => sanitize($_POST['member_linkedin'] ?? ''),
                'member_image' => sanitize($_POST['member_image'] ?? ''),
                'member_order' => (int)$_POST['member_order'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            if ($id) {
                db()->update('site_team', $data, 'id = :id', ['id' => $id]);
                jsonResponse(['success' => true, 'message' => 'Team member updated']);
            } else {
                db()->insert('site_team', $data);
                jsonResponse(['success' => true, 'message' => 'Team member added']);
            }
        }
        
        if ($action === 'delete_team') {
            db()->delete('site_team', 'id = :id', ['id' => (int)$_POST['id']]);
            jsonResponse(['success' => true]);
        }

        if ($action === 'save_features') {
            $features = $_POST['features'] ?? [];
            foreach ($features as $key => $value) {
                $key = sanitize($key);
                $value = sanitize($value);
                db()->query("INSERT INTO site_features (feature_key, feature_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE feature_value = ?", [$key, $value, $value]);
            }
            jsonResponse(['success' => true, 'message' => 'Features saved successfully']);
        }
        
        if ($action === 'save_service') {
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
            $data = [
                'service_title' => sanitize($_POST['service_title']),
                'service_icon' => sanitize($_POST['service_icon']),
                'service_description' => sanitize($_POST['service_description']),
                'service_order' => (int)$_POST['service_order'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            if ($id) {
                db()->update('site_services', $data, 'id = :id', ['id' => $id]);
                jsonResponse(['success' => true, 'message' => 'Service updated']);
            } else {
                db()->insert('site_services', $data);
                jsonResponse(['success' => true, 'message' => 'Service added']);
            }
        }
        
        if ($action === 'delete_service') {
            db()->delete('site_services', 'id = :id', ['id' => (int)$_POST['id']]);
            jsonResponse(['success' => true]);
        }
        
        if ($action === 'save_stat') {
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
            $data = [
                'stat_label' => sanitize($_POST['stat_label']),
                'stat_value' => sanitize($_POST['stat_value']),
                'stat_icon' => sanitize($_POST['stat_icon']),
                'stat_order' => (int)$_POST['stat_order'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            if ($id) {
                db()->update('site_stats', $data, 'id = :id', ['id' => $id]);
                jsonResponse(['success' => true, 'message' => 'Stat updated']);
            } else {
                db()->insert('site_stats', $data);
                jsonResponse(['success' => true, 'message' => 'Stat added']);
            }
        }
        
        if ($action === 'delete_stat') {
            db()->delete('site_stats', 'id = :id', ['id' => (int)$_POST['id']]);
            jsonResponse(['success' => true]);
        }
        
        if ($action === 'save_why_choose') {
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
            $data = [
                'title' => sanitize($_POST['title']),
                'description' => sanitize($_POST['description']),
                'icon' => sanitize($_POST['icon']),
                'item_order' => (int)$_POST['item_order'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            if ($id) {
                db()->update('site_why_choose', $data, 'id = :id', ['id' => $id]);
                jsonResponse(['success' => true, 'message' => 'Item updated']);
            } else {
                db()->insert('site_why_choose', $data);
                jsonResponse(['success' => true, 'message' => 'Item added']);
            }
        }
        
        if ($action === 'delete_why_choose') {
            db()->delete('site_why_choose', 'id = :id', ['id' => (int)$_POST['id']]);
            jsonResponse(['success' => true]);
        }
        
        if ($action === 'save_contact') {
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
            $data = [
                'info_type' => sanitize($_POST['info_type']),
                'info_value' => sanitize($_POST['info_value']),
                'info_icon' => sanitize($_POST['info_icon']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            if ($id) {
                db()->update('site_contact_info', $data, 'id = :id', ['id' => $id]);
                jsonResponse(['success' => true, 'message' => 'Contact info updated']);
            } else {
                db()->insert('site_contact_info', $data);
                jsonResponse(['success' => true, 'message' => 'Contact info added']);
            }
        }
        
        if ($action === 'delete_contact') {
            db()->delete('site_contact_info', 'id = :id', ['id' => (int)$_POST['id']]);
            jsonResponse(['success' => true]);
        }
        
        if ($action === 'save_social') {
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
            $data = [
                'platform' => sanitize($_POST['platform']),
                'profile_url' => sanitize($_POST['profile_url']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            if ($id) {
                db()->update('site_social_links', $data, 'id = :id', ['id' => $id]);
                jsonResponse(['success' => true, 'message' => 'Social link updated']);
            } else {
                db()->insert('site_social_links', $data);
                jsonResponse(['success' => true, 'message' => 'Social link added']);
            }
        }
        
        if ($action === 'delete_social') {
            db()->delete('site_social_links', 'id = :id', ['id' => (int)$_POST['id']]);
            jsonResponse(['success' => true]);
        }
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

$features = db()->select("SELECT * FROM site_features");
$featuresMap = [];
foreach ($features as $f) {
    $featuresMap[$f['feature_key']] = $f['feature_value'];
}

$services = getServices();
$stats = getStats();
$whyChoose = getWhyChoose();
$contactInfo = getContactInfo();
$socialLinks = getSocialLinks();

$pageTitle = 'Site Content | SohojWeb Admin';
include __DIR__ . '/../header.php';
?>

<div class="flex flex-col md:flex-row gap-6">
    <div class="w-full md:w-64 shrink-0">
        <div class="bg-white rounded-xl shadow-sm border p-2 sticky top-24">
            <a href="?section=features" class="block px-4 py-2 rounded-lg mb-1 <?= $section === 'features' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas fa-home w-6"></i> Hero & CTA
            </a>
            <a href="?section=services" class="block px-4 py-2 rounded-lg mb-1 <?= $section === 'services' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas fa-cubes w-6"></i> Services
            </a>
            <a href="?section=stats" class="block px-4 py-2 rounded-lg mb-1 <?= $section === 'stats' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas fa-chart-bar w-6"></i> Stats
            </a>
            <a href="?section=why_choose" class="block px-4 py-2 rounded-lg mb-1 <?= $section === 'why_choose' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas fa-check-circle w-6"></i> Why Choose
            </a>
            <a href="?section=testimonials" class="block px-4 py-2 rounded-lg mb-1 <?= $section === 'testimonials' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas fa-quote-left w-6"></i> Testimonials
            </a>
            <a href="?section=team" class="block px-4 py-2 rounded-lg mb-1 <?= $section === 'team' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas fa-users w-6"></i> Team
            </a>
            <a href="?section=contact" class="block px-4 py-2 rounded-lg mb-1 <?= $section === 'contact' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas fa-address-book w-6"></i> Contact Info
            </a>
            <a href="?section=social" class="block px-4 py-2 rounded-lg <?= $section === 'social' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas fa-share-alt w-6"></i> Social Links
            </a>
        </div>
    </div>
    
    <div class="flex-1">
        <?php if ($section === 'features'): ?>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6 border-b pb-4">Hero & CTA Section Content</h2>
            <form id="featuresForm">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="save_features">
                <div class="space-y-6">
                    <div>
                        <h3 class="text-sm font-semibold text-blue-600 uppercase tracking-wider mb-4">Hero Section</h3>
                        <div class="grid gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Hero Title</label>
                                <input type="text" name="features[hero_title]" value="<?= escape($featuresMap['hero_title'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Hero Subtitle</label>
                                <textarea name="features[hero_subtitle]" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"><?= escape($featuresMap['hero_subtitle'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-blue-600 uppercase tracking-wider mb-4">About Section</h3>
                        <div class="grid gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">About Title</label>
                                <input type="text" name="features[about_title]" value="<?= escape($featuresMap['about_title'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Mission Statement</label>
                                <textarea name="features[about_mission]" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"><?= escape($featuresMap['about_mission'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Vision Statement</label>
                                <textarea name="features[about_vision]" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"><?= escape($featuresMap['about_vision'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-blue-600 uppercase tracking-wider mb-4">Call To Action</h3>
                        <div class="grid gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">CTA Title</label>
                                <input type="text" name="features[cta_title]" value="<?= escape($featuresMap['cta_title'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">CTA Subtitle</label>
                                <textarea name="features[cta_subtitle]" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"><?= escape($featuresMap['cta_subtitle'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 pt-4 border-t flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition-all">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
        
        <?php elseif ($section === 'services'): ?>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Services</h2>
                <?php if ($canEdit): ?>
                <button onclick="openServiceModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition-all">
                    <i class="fas fa-plus mr-2"></i> Add Service
                </button>
                <?php endif; ?>
            </div>
            <div class="space-y-4">
                <?php foreach ($services as $service): ?>
                <div class="flex items-start md:items-center gap-4 p-4 border rounded-lg hover:shadow-md transition-shadow bg-gray-50">
                    <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center shadow-sm shrink-0">
                        <i class="<?= escape($service['service_icon']) ?> text-blue-600 text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-bold text-gray-900 truncate"><?= escape($service['service_title']) ?></h3>
                            <span class="px-2 py-0.5 text-xs rounded-full <?= $service['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' ?>">
                                <?= $service['is_active'] ? 'Active' : 'Hidden' ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 line-clamp-1"><?= escape($service['service_description']) ?></p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <?php if ($canEdit): ?>
                        <button onclick="editService(<?= $service['id'] ?>, '<?= addslashes($service['service_title']) ?>', '<?= addslashes($service['service_icon']) ?>', '<?= addslashes($service['service_description']) ?>', <?= $service['service_order'] ?>, <?= $service['is_active'] ?>)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                        <button onclick="deleteService(<?= $service['id'] ?>)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Service Modal -->
        <div id="serviceModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg transform transition-all scale-100">
                <div class="border-b px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold" id="serviceModalTitle">Add Service</h2>
                    <button onclick="closeServiceModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
                </div>
                <form id="serviceForm" class="p-6">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="save_service">
                    <input type="hidden" name="id" id="service_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Title *</label>
                        <input type="text" name="service_title" id="service_title" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Icon (Font Awesome)</label>
                        <div class="flex gap-2">
                            <input type="text" name="service_icon" id="service_icon" placeholder="fas fa-code" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <a href="https://fontawesome.com/icons" target="_blank" class="px-3 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200" title="Find Icons"><i class="fas fa-search"></i></a>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Description</label>
                        <textarea name="service_description" id="service_description" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Order Priority</label>
                            <input type="number" name="service_order" id="service_order" value="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex items-center mt-6">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="is_active" id="service_active" checked class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeServiceModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30">Save Service</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($section === 'stats'): ?>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Statistics</h2>
                <button onclick="openStatModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition-all">
                    <i class="fas fa-plus mr-2"></i> Add Stat
                </button>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($stats as $stat): ?>
                <div class="p-5 border rounded-xl hover:shadow-lg transition-all bg-white group">
                    <div class="flex justify-between items-start mb-3">
                        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <i class="<?= escape($stat['stat_icon']) ?> text-xl"></i>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full <?= $stat['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                            <?= $stat['is_active'] ? 'Active' : 'Hidden' ?>
                        </span>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-900 mb-1"><?= escape($stat['stat_value']) ?></h3>
                    <p class="text-gray-500 font-medium"><?= escape($stat['stat_label']) ?></p>
                    
                    <div class="flex gap-2 mt-4 pt-3 border-t">
                        <button onclick="editStat(<?= $stat['id'] ?>, '<?= addslashes($stat['stat_label']) ?>', '<?= addslashes($stat['stat_value']) ?>', '<?= addslashes($stat['stat_icon']) ?>', <?= $stat['stat_order'] ?>, <?= $stat['is_active'] ?>)" class="flex-1 py-1.5 text-sm text-blue-600 bg-blue-50 rounded hover:bg-blue-100">Edit</button>
                        <button onclick="deleteStat(<?= $stat['id'] ?>)" class="flex-1 py-1.5 text-sm text-red-600 bg-red-50 rounded hover:bg-red-100">Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div id="statModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="border-b px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold" id="statModalTitle">Add Stat</h2>
                    <button onclick="closeStatModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
                </div>
                <form id="statForm" class="p-6">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="save_stat">
                    <input type="hidden" name="id" id="stat_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Label *</label>
                        <input type="text" name="stat_label" id="stat_label" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Value *</label>
                        <input type="text" name="stat_value" id="stat_value" required placeholder="e.g. 100+" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Icon</label>
                        <input type="text" name="stat_icon" id="stat_icon" placeholder="fas fa-users" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Order</label>
                            <input type="number" name="stat_order" id="stat_order" value="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex items-center mt-6">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="is_active" id="stat_active" checked class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeStatModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30">Save</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($section === 'why_choose'): ?>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Why Choose Us Items</h2>
                <button onclick="openWhyChooseModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition-all">
                    <i class="fas fa-plus mr-2"></i> Add Item
                </button>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($whyChoose as $item): ?>
                <div class="p-5 border rounded-xl hover:shadow-lg transition-all bg-white h-full flex flex-col">
                    <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center mb-4 text-purple-600">
                        <i class="<?= escape($item['icon']) ?> text-xl"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2 text-gray-900"><?= escape($item['title']) ?></h3>
                    <p class="text-sm text-gray-600 flex-1"><?= escape($item['description']) ?></p>
                    <div class="flex gap-2 mt-4 pt-3 border-t">
                        <button onclick="editWhyChoose(<?= $item['id'] ?>, '<?= addslashes($item['title']) ?>', '<?= addslashes($item['description']) ?>', '<?= addslashes($item['icon']) ?>', <?= $item['item_order'] ?>, <?= $item['is_active'] ?>)" class="text-blue-600 text-sm hover:underline">Edit</button>
                        <button onclick="deleteWhyChoose(<?= $item['id'] ?>)" class="text-red-600 text-sm hover:underline ml-auto">Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Why Choose Modal -->
        <div id="whyChooseModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="border-b px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold" id="whyChooseModalTitle">Add Item</h2>
                    <button onclick="closeWhyChooseModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
                </div>
                <form id="whyChooseForm" class="p-6">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="save_why_choose">
                    <input type="hidden" name="id" id="why_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Title *</label>
                        <input type="text" name="title" id="why_title" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Description</label>
                        <textarea name="description" id="why_description" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Icon</label>
                        <input type="text" name="icon" id="why_icon" placeholder="fas fa-rocket" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Order</label>
                            <input type="number" name="item_order" id="why_order" value="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex items-center mt-6">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="is_active" id="why_active" checked class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeWhyChooseModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30">Save</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($section === 'testimonials'): ?>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Testimonials</h2>
                <button onclick="openTestimonialModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition-all">
                    <i class="fas fa-plus mr-2"></i> Add Testimonial
                </button>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <?php $testimonials = getTestimonials(); foreach ($testimonials as $idx => $t): $colors = ['blue', 'green', 'purple', 'orange']; $color = $colors[$idx % count($colors)]; ?>
                <div class="p-5 border rounded-xl hover:shadow-lg transition-all bg-white flex flex-col">
                    <div class="flex items-center gap-1 text-yellow-400 mb-2">
                        <?php for($i=0;$i<$t['rating'];$i++): ?><i class="fas fa-star text-xs"></i><?php endfor; ?>
                    </div>
                    <p class="text-gray-600 italic mb-4 flex-1">"<?= escape($t['testimonial_text']) ?>"</p>
                    <div class="flex items-center justify-between mt-4 pt-3 border-t">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-<?= $color ?>-50 rounded-full flex items-center justify-center text-<?= $color ?>-600 font-bold border border-<?= $color ?>-100">
                                <?= strtoupper(substr($t['client_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-900 text-sm"><?= escape($t['client_name']) ?></h4>
                                <p class="text-[10px] text-gray-500 uppercase"><?= escape($t['client_designation']) ?></p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="editTestimonial(<?= htmlspecialchars(json_encode($t)) ?>)" class="text-blue-600 text-sm hover:underline">Edit</button>
                            <button onclick="deleteTestimonial(<?= $t['id'] ?>)" class="text-red-600 text-sm hover:underline">Delete</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Testimonial Modal -->
        <div id="testimonialModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="border-b px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold" id="testimonialModalTitle">Add Testimonial</h2>
                    <button onclick="closeTestimonialModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
                </div>
                <form id="testimonialForm" class="p-6">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="save_testimonial">
                    <input type="hidden" name="id" id="testimonial_id">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Client Name *</label>
                            <input type="text" name="client_name" id="client_name" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Designation</label>
                            <input type="text" name="client_designation" id="client_designation" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Company</label>
                        <input type="text" name="client_company" id="client_company" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Rating (1-5)</label>
                        <select name="rating" id="rating" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="5">5 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="2">2 Stars</option>
                            <option value="1">1 Star</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Testimonial *</label>
                        <textarea name="testimonial_text" id="testimonial_text" rows="4" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="flex items-center mb-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" id="testimonial_active" checked class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-gray-700">Active</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeTestimonialModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <?php elseif ($section === 'team'): ?>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Team Members</h2>
                <button onclick="openTeamModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition-all">
                    <i class="fas fa-plus mr-2"></i> Add Member
                </button>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php $team = getTeam(); foreach ($team as $m): $colors = ['blue', 'indigo', 'purple', 'violet']; $color = $colors[$m['member_order'] % count($colors)]; ?>
                <div class="p-5 border rounded-xl hover:shadow-lg transition-all bg-white text-center group">
                    <div class="w-16 h-16 bg-<?= $color ?>-50 rounded-full mx-auto mb-4 flex items-center justify-center text-<?= $color ?>-600 font-bold text-xl border-2 border-<?= $color ?>-100 group-hover:bg-<?= $color ?>-100 transition-colors">
                        <?= strtoupper(substr($m['member_name'], 0, 1)) . strtoupper(substr(explode(' ', $m['member_name'])[1] ?? '', 0, 1)) ?>
                    </div>
                    <h3 class="font-bold text-lg text-gray-900"><?= escape($m['member_name']) ?></h3>
                    <p class="text-sm text-blue-600 mb-2"><?= escape($m['designation']) ?></p>
                    <p class="text-xs text-gray-500 line-clamp-2 mb-4"><?= escape($m['bio']) ?></p>
                    <div class="flex gap-2 justify-center pt-3 border-t">
                        <button onclick="editTeam(<?= htmlspecialchars(json_encode($m)) ?>)" class="text-blue-600 text-xs hover:underline">Edit</button>
                        <button onclick="deleteTeam(<?= $m['id'] ?>)" class="text-red-600 text-xs hover:underline">Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Team Modal -->
        <div id="teamModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="border-b px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold" id="teamModalTitle">Add Team Member</h2>
                    <button onclick="closeTeamModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
                </div>
                <form id="teamForm" class="p-6">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="save_team">
                    <input type="hidden" name="id" id="team_id">
                    <input type="hidden" name="member_image" id="member_image_url">
                    
                    <div class="flex flex-col items-center mb-6">
                        <div id="memberImagePreview" class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center text-gray-400 border-2 border-dashed border-gray-300 overflow-hidden mb-2">
                            <i class="fas fa-user text-3xl"></i>
                        </div>
                        <input type="file" id="memberPhotoInput" class="hidden" accept="image/*">
                        <button type="button" onclick="document.getElementById('memberPhotoInput').click()" class="text-sm text-blue-600 hover:underline">
                            <i class="fas fa-camera mr-1"></i> Upload Photo
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Name *</label>
                            <input type="text" name="member_name" id="member_name" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Designation *</label>
                            <input type="text" name="designation" id="designation" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Email</label>
                            <input type="email" name="member_email" id="member_email" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Facebook URL</label>
                            <input type="url" name="member_facebook" id="member_facebook" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">LinkedIn URL</label>
                        <input type="url" name="member_linkedin" id="member_linkedin" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Bio</label>
                        <textarea name="bio" id="bio" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Order</label>
                            <input type="number" name="member_order" id="member_order" value="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex items-center mt-6">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="is_active" id="team_active" checked class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeTeamModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30">Save Member</button>
                    </div>
                </form>
            </div>
        </div>

        <?php elseif ($section === 'contact'): ?>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Contact Information</h2>
                <button onclick="openContactModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition-all">
                    <i class="fas fa-plus mr-2"></i> Add Info
                </button>
            </div>
            <div class="space-y-3">
                <?php foreach ($contactInfo as $info): ?>
                <div class="flex items-center gap-4 p-4 border rounded-lg bg-white hover:shadow-md transition-shadow">
                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="<?= escape($info['info_icon']) ?> text-gray-600"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-900"><?= ucfirst(escape($info['info_type'])) ?></p>
                        <p class="text-sm text-gray-600"><?= escape($info['info_value']) ?></p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="editContact(<?= $info['id'] ?>, '<?= addslashes($info['info_type']) ?>', '<?= addslashes($info['info_value']) ?>', '<?= addslashes($info['info_icon']) ?>', <?= $info['is_active'] ?>)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteContact(<?= $info['id'] ?>)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Contact Modal -->
        <div id="contactModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="border-b px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold" id="contactModalTitle">Add Contact Info</h2>
                    <button onclick="closeContactModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
                </div>
                <form id="contactForm" class="p-6">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="save_contact">
                    <input type="hidden" name="id" id="contact_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Type *</label>
                        <select name="info_type" id="info_type" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="address">Address</option>
                            <option value="email">Email</option>
                            <option value="phone">Phone</option>
                            <option value="whatsapp">WhatsApp</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Value *</label>
                        <input type="text" name="info_value" id="info_value" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Icon</label>
                        <input type="text" name="info_icon" id="info_icon" placeholder="fas fa-map-marker-alt" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" id="contact_active" checked class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-gray-700">Active</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeContactModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30">Save</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($section === 'social'): ?>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Social Media Links</h2>
                <button onclick="openSocialModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition-all">
                    <i class="fas fa-plus mr-2"></i> Add Link
                </button>
            </div>
            <div class="space-y-3">
                <?php foreach ($socialLinks as $social): ?>
                <div class="flex items-center gap-4 p-4 border rounded-lg bg-white hover:shadow-md transition-shadow">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600">
                        <i class="fab fa-<?= escape($social['platform']) ?>"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium capitalize text-gray-900"><?= escape($social['platform']) ?></p>
                        <p class="text-sm text-gray-500"><?= escape($social['profile_url']) ?></p>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full <?= $social['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                        <?= $social['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                    <div class="flex gap-2">
                        <button onclick="editSocial(<?= $social['id'] ?>, '<?= addslashes($social['platform']) ?>', '<?= addslashes($social['profile_url']) ?>', <?= $social['is_active'] ?>)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteSocial(<?= $social['id'] ?>)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Social Modal -->
        <div id="socialModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="border-b px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold" id="socialModalTitle">Add Social Link</h2>
                    <button onclick="closeSocialModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
                </div>
                <form id="socialForm" class="p-6">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="save_social">
                    <input type="hidden" name="id" id="social_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Platform *</label>
                        <select name="platform" id="social_platform" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="facebook">Facebook</option>
                            <option value="twitter">Twitter</option>
                            <option value="linkedin">LinkedIn</option>
                            <option value="instagram">Instagram</option>
                            <option value="youtube">YouTube</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Profile URL *</label>
                        <input type="url" name="profile_url" id="social_url" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" id="social_active" checked class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-gray-700">Active</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeSocialModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30">Save</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;

// Features Form
document.getElementById('featuresForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';

    fetch('index.php', { method: 'POST', body: new FormData(this) })
    .then(res => res.json())
    .then(data => {
        if(data.success) { 
            showToast('success', data.message); 
        } else {
            showToast('error', data.message);
        }
    })
    .catch(err => showToast('error', 'An error occurred'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
});

// Generic Modal & Form Handler
function handleFormSubmit(formId, modalId) {
    document.getElementById(formId).addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';

        fetch('index.php', { method: 'POST', body: new FormData(this) })
        .then(res => res.json())
        .then(data => {
            if(data.success) { 
                showToast('success', data.message || 'Saved successfully');
                document.getElementById(modalId).classList.add('hidden');
                document.getElementById(modalId).classList.remove('flex');
                setTimeout(() => location.reload(), 500);
            } else { 
                showToast('error', data.message); 
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        })
        .catch(err => {
            showToast('error', 'An error occurred');
            btn.disabled = false;
            btn.innerHTML = originalContent;
        });
    });
}

// Services
if(document.getElementById('serviceForm')) handleFormSubmit('serviceForm', 'serviceModal');
function openServiceModal() {
    document.getElementById('serviceModalTitle').innerText = 'Add Service';
    document.getElementById('serviceForm').reset();
    document.getElementById('service_id').value = '';
    document.getElementById('serviceModal').classList.remove('hidden');
    document.getElementById('serviceModal').classList.add('flex');
}
function closeServiceModal() {
    document.getElementById('serviceModal').classList.add('hidden');
    document.getElementById('serviceModal').classList.remove('flex');
}
function editService(id, title, icon, desc, order, active) {
    document.getElementById('serviceModalTitle').innerText = 'Edit Service';
    document.getElementById('service_id').value = id;
    document.getElementById('service_title').value = title;
    document.getElementById('service_icon').value = icon;
    document.getElementById('service_description').value = desc;
    document.getElementById('service_order').value = order;
    document.getElementById('service_active').checked = active;
    document.getElementById('serviceModal').classList.remove('hidden');
    document.getElementById('serviceModal').classList.add('flex');
}
function deleteService(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if(confirm('Delete this service?')) {
        let formData = new FormData();
        formData.append('action', 'delete_service');
        formData.append('id', id);
        fetch('index.php', {method: 'POST', body: formData}).then(() => location.reload()); 
    }
}

// Stats
if(document.getElementById('statForm')) handleFormSubmit('statForm', 'statModal');
function openStatModal() {
    document.getElementById('statModalTitle').innerText = 'Add Stat';
    document.getElementById('statForm').reset();
    document.getElementById('stat_id').value = '';
    document.getElementById('statModal').classList.remove('hidden');
    document.getElementById('statModal').classList.add('flex');
}
function closeStatModal() {
    document.getElementById('statModal').classList.add('hidden');
    document.getElementById('statModal').classList.remove('flex');
}
function editStat(id, label, value, icon, order, active) {
    document.getElementById('statModalTitle').innerText = 'Edit Stat';
    document.getElementById('stat_id').value = id;
    document.getElementById('stat_label').value = label;
    document.getElementById('stat_value').value = value;
    document.getElementById('stat_icon').value = icon;
    document.getElementById('stat_order').value = order;
    document.getElementById('stat_active').checked = active;
    document.getElementById('statModal').classList.remove('hidden');
    document.getElementById('statModal').classList.add('flex');
}
function deleteStat(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if(confirm('Delete?')) {
        let formData = new FormData();
        formData.append('action', 'delete_stat');
        formData.append('id', id);
        fetch('index.php', {method: 'POST', body: formData}).then(() => location.reload()); 
    }
}

// Why Choose
if(document.getElementById('whyChooseForm')) handleFormSubmit('whyChooseForm', 'whyChooseModal');
function openWhyChooseModal() {
    document.getElementById('whyChooseModalTitle').innerText = 'Add Item';
    document.getElementById('whyChooseForm').reset();
    document.getElementById('why_id').value = '';
    document.getElementById('whyChooseModal').classList.remove('hidden');
    document.getElementById('whyChooseModal').classList.add('flex');
}
function closeWhyChooseModal() {
    document.getElementById('whyChooseModal').classList.add('hidden');
    document.getElementById('whyChooseModal').classList.remove('flex');
}
function editWhyChoose(id, title, desc, icon, order, active) {
    document.getElementById('whyChooseModalTitle').innerText = 'Edit Item';
    document.getElementById('why_id').value = id;
    document.getElementById('why_title').value = title;
    document.getElementById('why_description').value = desc;
    document.getElementById('why_icon').value = icon;
    document.getElementById('why_order').value = order;
    document.getElementById('why_active').checked = active;
    document.getElementById('whyChooseModal').classList.remove('hidden');
    document.getElementById('whyChooseModal').classList.add('flex');
}
function deleteWhyChoose(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if(confirm('Delete?')) {
        let formData = new FormData();
        formData.append('csrf_token', '<?= csrf_token() ?>');
        formData.append('action', 'delete_why_choose');
        formData.append('id', id);
        fetch('index.php', {method: 'POST', body: formData}).then(() => location.reload()); 
    }
}

// Testimonials
if(document.getElementById('testimonialForm')) handleFormSubmit('testimonialForm', 'testimonialModal');
function openTestimonialModal() {
    document.getElementById('testimonialModalTitle').innerText = 'Add Testimonial';
    document.getElementById('testimonialForm').reset();
    document.getElementById('testimonial_id').value = '';
    document.getElementById('testimonialModal').classList.remove('hidden');
    document.getElementById('testimonialModal').classList.add('flex');
}
function closeTestimonialModal() {
    document.getElementById('testimonialModal').classList.add('hidden');
    document.getElementById('testimonialModal').classList.remove('flex');
}
function editTestimonial(t) {
    document.getElementById('testimonialModalTitle').innerText = 'Edit Testimonial';
    document.getElementById('testimonial_id').value = t.id;
    document.getElementById('client_name').value = t.client_name;
    document.getElementById('client_designation').value = t.client_designation;
    document.getElementById('client_company').value = t.client_company;
    document.getElementById('rating').value = t.rating;
    document.getElementById('testimonial_text').value = t.testimonial_text;
    document.getElementById('testimonial_active').checked = t.is_active;
    document.getElementById('testimonialModal').classList.remove('hidden');
    document.getElementById('testimonialModal').classList.add('flex');
}
function deleteTestimonial(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if(confirm('Delete?')) {
        let formData = new FormData();
        formData.append('csrf_token', '<?= csrf_token() ?>');
        formData.append('action', 'delete_testimonial');
        formData.append('id', id);
        fetch('index.php', {method: 'POST', body: formData}).then(() => location.reload());
    }
}

// Team
if(document.getElementById('teamForm')) {
    handleFormSubmit('teamForm', 'teamModal');
    
    document.getElementById('memberPhotoInput').addEventListener('change', function() {
        const file = this.files[0];
        if(!file) return;
        
        const formData = new FormData();
        formData.append('file', file);
        
        fetch('../ajax/upload-team-photo.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                document.getElementById('member_image_url').value = data.url;
                document.getElementById('memberImagePreview').innerHTML = `<img src="${data.url}" class="w-full h-full object-cover">`;
                showToast('success', 'Photo uploaded');
            } else {
                showToast('error', data.message);
            }
        });
    });
}

function openTeamModal() {
    document.getElementById('teamModalTitle').innerText = 'Add Team Member';
    document.getElementById('teamForm').reset();
    document.getElementById('team_id').value = '';
    document.getElementById('member_image_url').value = '';
    document.getElementById('memberImagePreview').innerHTML = '<i class="fas fa-user text-3xl"></i>';
    document.getElementById('teamModal').classList.remove('hidden');
    document.getElementById('teamModal').classList.add('flex');
}
function closeTeamModal() {
    document.getElementById('teamModal').classList.add('hidden');
    document.getElementById('teamModal').classList.remove('flex');
}
function editTeam(m) {
    document.getElementById('teamModalTitle').innerText = 'Edit Member';
    document.getElementById('team_id').value = m.id;
    document.getElementById('member_name').value = m.member_name;
    document.getElementById('designation').value = m.designation;
    document.getElementById('bio').value = m.bio;
    document.getElementById('member_email').value = m.member_email || '';
    document.getElementById('member_facebook').value = m.member_facebook || '';
    document.getElementById('member_linkedin').value = m.member_linkedin || '';
    document.getElementById('member_image_url').value = m.member_image || '';
    
    if(m.member_image) {
        document.getElementById('memberImagePreview').innerHTML = `<img src="${m.member_image}" class="w-full h-full object-cover">`;
    } else {
        document.getElementById('memberImagePreview').innerHTML = '<i class="fas fa-user text-3xl"></i>';
    }
    
    document.getElementById('member_order').value = m.member_order;
    document.getElementById('team_active').checked = parseInt(m.is_active);
    document.getElementById('teamModal').classList.remove('hidden');
    document.getElementById('teamModal').classList.add('flex');
}
function deleteTeam(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if(confirm('Delete?')) {
        let formData = new FormData();
        formData.append('csrf_token', '<?= csrf_token() ?>');
        formData.append('action', 'delete_team');
        formData.append('id', id);
        fetch('index.php', {method: 'POST', body: formData}).then(() => location.reload());
    }
}

// Contact
if(document.getElementById('contactForm')) handleFormSubmit('contactForm', 'contactModal');
function openContactModal() {
    document.getElementById('contactModalTitle').innerText = 'Add Contact Info';
    document.getElementById('contactForm').reset();
    document.getElementById('contact_id').value = '';
    document.getElementById('contactModal').classList.remove('hidden');
    document.getElementById('contactModal').classList.add('flex');
}
function closeContactModal() {
    document.getElementById('contactModal').classList.add('hidden');
    document.getElementById('contactModal').classList.remove('flex');
}
function editContact(id, type, value, icon, active) {
    document.getElementById('contactModalTitle').innerText = 'Edit Contact Info';
    document.getElementById('contact_id').value = id;
    document.getElementById('info_type').value = type;
    document.getElementById('info_value').value = value;
    document.getElementById('info_icon').value = icon;
    document.getElementById('contact_active').checked = active;
    document.getElementById('contactModal').classList.remove('hidden');
    document.getElementById('contactModal').classList.add('flex');
}
function deleteContact(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if(confirm('Delete?')) {
        let formData = new FormData();
        formData.append('action', 'delete_contact');
        formData.append('id', id);
        fetch('index.php', {method: 'POST', body: formData}).then(() => location.reload()); 
    }
}

// Social
if(document.getElementById('socialForm')) handleFormSubmit('socialForm', 'socialModal');
function openSocialModal() {
    document.getElementById('socialModalTitle').innerText = 'Add Social Link';
    document.getElementById('socialForm').reset();
    document.getElementById('social_id').value = '';
    document.getElementById('socialModal').classList.remove('hidden');
    document.getElementById('socialModal').classList.add('flex');
}
function closeSocialModal() {
    document.getElementById('socialModal').classList.add('hidden');
    document.getElementById('socialModal').classList.remove('flex');
}
function editSocial(id, platform, url, active) {
    document.getElementById('socialModalTitle').innerText = 'Edit Social Link';
    document.getElementById('social_id').value = id;
    document.getElementById('social_platform').value = platform;
    document.getElementById('social_url').value = url;
    document.getElementById('social_active').checked = active;
    document.getElementById('socialModal').classList.remove('hidden');
    document.getElementById('socialModal').classList.add('flex');
}
function deleteSocial(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if(confirm('Delete?')) {
        let formData = new FormData();
        formData.append('action', 'delete_social');
        formData.append('id', id);
        fetch('index.php', {method: 'POST', body: formData}).then(() => location.reload()); 
    }
}
</script>
<?php include __DIR__ . '/../footer.php'; ?>
