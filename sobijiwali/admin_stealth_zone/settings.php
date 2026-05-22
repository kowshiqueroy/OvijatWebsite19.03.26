<?php
/**
 * Admin Site Settings Manager
 */
$pageTitle = 'Site Settings';
require_once 'layout_header.php';

$db = Database::getInstance();
$logger = new Logger();
$optimizer = new ImageOptimizer();
$message = '';
$error = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token invalid.";
    } else {
        if ($_POST['action'] === 'update_info') {
            $keys = ['contact_phone', 'contact_email', 'store_address', 'facebook_url', 'twitter_url', 'instagram_url', 'preloader_logo', 'stripe_publishable_key', 'stripe_secret_key'];
            foreach ($keys as $k) {
                if (isset($_POST[$k])) {
                    $db->query("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?", [$_POST[$k], $k]);
                }
            }
            $logger->log('update_site_info', 'settings', null, "Updated store contact and API info");
            $message = "Settings updated successfully.";
        } elseif ($_POST['action'] === 'upload_assets') {
            $destDir = __DIR__ . '/../assets/img/site/';
            if (!is_dir($destDir)) mkdir($destDir, 0777, true);

            $assets = ['site_logo', 'favicon'];
            foreach ($assets as $a) {
                if (!empty($_FILES[$a]['name'])) {
                    $result = $optimizer->processUpload($_FILES[$a], $destDir);
                    if ($result) {
                        $filename = basename($result);
                        $db->query("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?", [$filename, $a]);
                    }
                }
            }
            $message = "Assets uploaded successfully.";
        } elseif ($_POST['action'] === 'add_banner') {
            if (!empty($_FILES['banner_image']['name'])) {
                $destDir = __DIR__ . '/../assets/img/banners/';
                if (!is_dir($destDir)) mkdir($destDir, 0777, true);
                $result = $optimizer->processUpload($_FILES['banner_image'], $destDir);
                if ($result) {
                    $db->query("INSERT INTO hero_slides (image_path, title, subtitle) VALUES (?, ?, ?)", 
                               [basename($result), $_POST['title'], $_POST['subtitle']]);
                    $message = "Hero banner added.";
                }
            }
        } elseif ($_POST['action'] === 'delete_banner') {
            $id = (int)$_POST['banner_id'];
            $slide = $db->query("SELECT image_path FROM hero_slides WHERE id = ?", [$id])->fetch();
            if ($slide) {
                @unlink(__DIR__ . '/../assets/img/banners/' . $slide['image_path']);
                $db->query("DELETE FROM hero_slides WHERE id = ?", [$id]);
                $message = "Banner deleted.";
            }
        } elseif ($_POST['action'] === 'save_gateway') {
            $id = isset($_POST['gateway_id']) ? (int)$_POST['gateway_id'] : null;
            $name = $_POST['name'];
            $icon = $_POST['icon'];
            $details = $_POST['details'];
            $active = isset($_POST['is_active']) ? 1 : 0;

            if ($id) {
                $db->query("UPDATE payment_gateways SET gateway_name = ?, icon_emoji = ?, details_html = ?, is_active = ? WHERE id = ?", 
                           [$name, $icon, $details, $active, $id]);
                $message = "Gateway updated.";
            } else {
                $db->query("INSERT INTO payment_gateways (gateway_name, icon_emoji, details_html, is_active) VALUES (?, ?, ?, ?)", 
                           [$name, $icon, $details, $active]);
                $message = "Payment gateway added.";
            }
        } elseif ($_POST['action'] === 'toggle_gateway') {
            $id = (int)$_POST['gateway_id'];
            $db->query("UPDATE payment_gateways SET is_active = NOT is_active WHERE id = ?", [$id]);
            $message = "Gateway status toggled.";
        }
    }
}

// Fetch current settings
$rawSettings = $db->query("SELECT * FROM site_settings")->fetchAll();
$settings = [];
foreach ($rawSettings as $rs) {
    $settings[$rs['setting_key']] = $rs['setting_value'];
}

$banners = $db->query("SELECT * FROM hero_slides ORDER BY sort_order ASC")->fetchAll();
$gateways = $db->query("SELECT * FROM payment_gateways ORDER BY sort_order ASC")->fetchAll();
?>

<h1>Site Appearance & Identity</h1>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem; align-items: start;">
    
    <!-- Identity & Contact -->
    <div>
        <div class="card">
            <h3>Visual Identity</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-top: 1.5rem;">
                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="upload_assets">
                
                <div class="form-group">
                    <label>Site Logo (Transparent PNG/WebP)</label>
                    <input type="file" name="site_logo" accept="image/*">
                    <?php if (isset($settings['site_logo']) && $settings['site_logo']): ?>
                        <div style="margin-top: 0.5rem; background: #eee; padding: 10px; border-radius: 8px; display: inline-block;">
                            <img src="../assets/img/site/<?php echo $settings['site_logo']; ?>" style="height: 30px;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Favicon (32x32)</label>
                    <input type="file" name="favicon" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Preloader Emoji/Icon</label>
                    <input type="text" name="preloader_logo" value="<?php echo htmlspecialchars($settings['preloader_logo'] ?? '🌿'); ?>">
                </div>

                <div class="form-group">
                    <label>Preloader Image (Overrides Emoji)</label>
                    <input type="file" name="preloader_image" accept="image/*">
                    <?php if (isset($settings['preloader_image']) && $settings['preloader_image']): ?>
                        <div style="margin-top: 0.5rem;">
                            <img src="../assets/img/site/<?php echo $settings['preloader_image']; ?>" style="height: 50px;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Festival Wish / Loading Text</label>
                    <input type="text" name="festival_text" value="<?php echo htmlspecialchars($settings['festival_text'] ?? ''); ?>" placeholder="e.g. Happy Harvesting! or Loading Freshness...">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Upload & Update Assets</button>
            </form>
        </div>

        <div class="card">
            <h3>API Credentials (Stripe)</h3>
            <form method="POST" style="margin-top: 1.5rem;">
                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_info">
                
                <div class="form-group">
                    <label>Stripe Publishable Key</label>
                    <input type="text" name="stripe_publishable_key" value="<?php echo htmlspecialchars($settings['stripe_publishable_key'] ?? ''); ?>" placeholder="pk_test_...">
                </div>

                <div class="form-group">
                    <label>Stripe Secret Key</label>
                    <input type="password" name="stripe_secret_key" value="<?php echo htmlspecialchars($settings['stripe_secret_key'] ?? ''); ?>" placeholder="sk_test_...">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Save API Keys</button>
            </form>
        </div>

        <div class="card">
            <h3>Contact & Socials</h3>
            <form method="POST" style="margin-top: 1.5rem;">
                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_info">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group"><label>Phone</label><input type="text" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>"></div>
                </div>
                <div class="form-group"><label>Physical Address</label><input type="text" name="store_address" value="<?php echo htmlspecialchars($settings['store_address'] ?? ''); ?>"></div>
                
                <div class="nav-sep"></div>
                
                <div class="form-group"><label>Facebook URL</label><input type="text" name="facebook_url" value="<?php echo htmlspecialchars($settings['facebook_url'] ?? ''); ?>"></div>
                <div class="form-group"><label>Twitter URL</label><input type="text" name="twitter_url" value="<?php echo htmlspecialchars($settings['twitter_url'] ?? ''); ?>"></div>
                <div class="form-group"><label>Instagram URL</label><input type="text" name="instagram_url" value="<?php echo htmlspecialchars($settings['instagram_url'] ?? ''); ?>"></div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Contact Details</button>
            </form>
        </div>
    </div>

    <!-- Banners & Gateways -->
    <div>
        <div class="card">
            <h3>Hero Banners</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-top: 1.5rem; background: var(--bg); padding: 1.5rem; border-radius: 12px; border: 2px dashed var(--border);">
                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_banner">
                
                <div class="form-group"><label>Banner Image</label><input type="file" name="banner_image" required></div>
                <div class="form-group"><label>Headline</label><input type="text" name="title" placeholder="Nature's Purest Harvest"></div>
                <div class="form-group"><label>Sub-headline</label><input type="text" name="subtitle" placeholder="Direct from local artisans..."></div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Add New Slide</button>
            </form>

            <div style="margin-top: 2rem;">
                <?php foreach ($banners as $b): ?>
                    <div style="display: flex; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid var(--border); border-radius: 12px; margin-bottom: 1rem;">
                        <img src="../assets/img/banners/<?php echo $b['image_path']; ?>" style="width: 100px; height: 60px; object-fit: cover; border-radius: 8px;">
                        <div style="flex: 1;">
                            <div style="font-weight: 800; font-size: 0.8rem;"><?php echo htmlspecialchars($b['title']); ?></div>
                            <div style="font-size: 0.7rem; opacity: 0.6;"><?php echo htmlspecialchars($b['subtitle']); ?></div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="delete_banner">
                            <input type="hidden" name="banner_id" value="<?php echo $b['id']; ?>">
                            <button type="submit" class="btn btn-danger" style="padding: 5px;">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h3 id="gateway-form-title">Payment Gateways</h3>
            <form method="POST" id="gateway-form" style="margin-top: 1.5rem; background: var(--bg); padding: 1.5rem; border-radius: 12px; border: 2px dashed var(--border);">
                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="save_gateway">
                <input type="hidden" name="gateway_id" id="gateway-id">
                
                <div style="display: grid; grid-template-columns: 1fr 60px; gap: 1rem;">
                    <div class="form-group"><label>Gateway Name</label><input type="text" name="name" id="gateway-name" placeholder="Manual Bank Transfer" required></div>
                    <div class="form-group"><label>Icon</label><input type="text" name="icon" id="gateway-icon" value="🏦"></div>
                </div>
                <div class="form-group">
                    <label>Details (HTML allowed)</label>
                    <textarea name="details" id="gateway-details" placeholder="Bank: X, A/C: Y..." style="height: 100px;"></textarea>
                </div>
                <label style="display:flex; align-items:center; gap:0.5rem; font-weight:700; font-size:0.8rem; margin-bottom:1rem;"><input type="checkbox" name="is_active" id="gateway-active" checked style="width:auto;"> Active</label>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Save Gateway</button>
                    <button type="button" onclick="resetGatewayForm()" class="btn btn-outline">Cancel</button>
                </div>
            </form>

            <div style="margin-top: 2rem;">
                <?php foreach ($gateways as $g): ?>
                    <div style="display: flex; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid var(--border); border-radius: 12px; margin-bottom: 1rem; opacity: <?php echo $g['is_active'] ? '1' : '0.5'; ?>;">
                        <div style="font-size: 1.5rem;"><?php echo $g['icon_emoji']; ?></div>
                        <div style="flex: 1;">
                            <div style="font-weight: 800; font-size: 0.85rem;"><?php echo htmlspecialchars($g['gateway_name']); ?></div>
                            <div style="font-size: 0.7rem; opacity: 0.6;"><?php echo $g['gateway_type']; ?></div>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <button onclick='editGateway(<?php echo json_encode($g); ?>)' class="btn btn-outline" style="padding: 5px;">Edit</button>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="toggle_gateway">
                                <input type="hidden" name="gateway_id" value="<?php echo $g['id']; ?>">
                                <button type="submit" class="btn btn-outline" style="padding: 5px;"><?php echo $g['is_active'] ? 'Off' : 'On'; ?></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<script>
    function editGateway(g) {
        document.getElementById('gateway-form-title').innerText = 'Edit Gateway: ' + g.gateway_name;
        document.getElementById('gateway-id').value = g.id;
        document.getElementById('gateway-name').value = g.gateway_name;
        document.getElementById('gateway-icon').value = g.icon_emoji;
        document.getElementById('gateway-details').value = g.details_html;
        document.getElementById('gateway-active').checked = g.is_active == 1;
        document.getElementById('gateway-form').scrollIntoView({behavior: 'smooth'});
    }
    function resetGatewayForm() {
        document.getElementById('gateway-form-title').innerText = 'Payment Gateways';
        document.getElementById('gateway-id').value = '';
        document.getElementById('gateway-name').value = '';
        document.getElementById('gateway-icon').value = '🏦';
        document.getElementById('gateway-details').value = '';
        document.getElementById('gateway-active').checked = true;
    }
</script>

<?php require_once 'layout_footer.php'; ?>
