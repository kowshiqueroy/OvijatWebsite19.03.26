<?php
/**
 * Admin Product & Category Management - Unified Modern View
 */
restrict_to(['admin', 'manager', 'editor']);

$error = '';
$success = '';

// Handle Category Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['cat_name'] ?? '');
    $description = trim($_POST['cat_desc'] ?? '');

    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        if ($stmt->execute([$name, $description])) {
            $success = "Category '$name' added successfully.";
            log_action($pdo, $_SESSION['user_id'], 'Created Category', null, $name);
        }
    } else {
        $error = "Category name is required.";
    }
}

// Handle Category Editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $cat_id = (int)$_POST['category_id'];
    $name = trim($_POST['cat_name'] ?? '');
    $description = trim($_POST['cat_desc'] ?? '');

    if ($cat_id && $name) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $cat_id])) {
            $success = "Category '$name' updated successfully.";
            log_action($pdo, $_SESSION['user_id'], 'Updated Category', null, "ID: $cat_id, Name: $name");
        }
    } else {
        $error = "Category name is required.";
    }
}

// Handle Product Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $cat_id = $_POST['category_id'] ?: null;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $base_price = (float)($_POST['base_price'] ?? 0);
    $stock_qty = 0; // Hardcoded to 0; stock is only managed via Advanced Inbound LOTs
    $min_qty = (int)($_POST['min_order_qty'] ?? 1);
    $max_qty = (int)($_POST['max_order_qty'] ?? 9999);
    $weight = (float)($_POST['weight'] ?? 0);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    if ($name && $base_price >= 0) {
        $stmt = $pdo->prepare("INSERT INTO products (category_id, name, description, base_price, stock_qty, min_order_qty, max_order_qty, weight, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$cat_id, $name, $description, $base_price, $stock_qty, $min_qty, $max_qty, $weight, $is_featured])) {
            $product_id = $pdo->lastInsertId();
            
            // Handle Price Tiers
            if (!empty($_POST['tier_qty']) && !empty($_POST['tier_price'])) {
                $tstmt = $pdo->prepare("INSERT INTO product_price_tiers (product_id, min_qty, unit_price) VALUES (?, ?, ?)");
                foreach ($_POST['tier_qty'] as $idx => $t_qty) {
                    $t_price = (float)($_POST['tier_price'][$idx] ?? 0);
                    if ($t_qty > 0 && $t_price > 0) {
                        $tstmt->execute([$product_id, (int)$t_qty, $t_price]);
                    }
                }
            }

            // Handle Multiple Variants
            if (!empty($_POST['var_type'])) {
                $vstmt = $pdo->prepare("INSERT INTO product_variants (product_id, variant_type, variant_value, price_modifier, stock_qty) VALUES (?, ?, ?, ?, ?)");
                foreach ($_POST['var_type'] as $idx => $v_type) {
                    $v_val = trim($_POST['var_value'][$idx] ?? '');
                    $v_price = (float)($_POST['var_price'][$idx] ?? 0);
                    $v_stock = (int)($_POST['var_stock'][$idx] ?? 0);
                    if ($v_type && $v_val) {
                        $vstmt->execute([$product_id, $v_type, $v_val, $v_price, $v_stock]);
                    }
                }
            }

            // Handle Multiple Photos Upload (Base64 Canvas Resized, Max 4)
            if (!empty($_POST['resized_images'])) {
                $upload_dir = "public/uploads/products/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                foreach ($_POST['resized_images'] as $index => $base64_data) {
                    if ($index >= 4) break; // Hard limit to max 4 images
                    
                    if (preg_match('/^data:image\/(\w+);base64,/', $base64_data, $type)) {
                        $data = substr($base64_data, strpos($base64_data, ',') + 1);
                        $data = base64_decode($data);
                        
                        if ($data !== false) {
                            $filename = time() . "_" . $index . "_" . uniqid() . ".jpg";
                            $filepath = $upload_dir . $filename;
                            
                            if (file_put_contents($filepath, $data)) {
                                $is_main = ($index === 0) ? 1 : 0;
                                $istmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, ?)");
                                $istmt->execute([$product_id, "/bolakausa/" . $filepath, $is_main]);
                            }
                        }
                    }
                }
            }

            $success = "Product '$name' added successfully.";
            log_action($pdo, $_SESSION['user_id'], 'Created Product', null, "Product ID: $product_id");
        }
    } else {
        $error = "Product Name and Base Price are strictly required.";
    }
}

// Handle Product Editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $product_id = (int)$_POST['product_id'];
    $cat_id = $_POST['category_id'] ?: null;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $base_price = (float)($_POST['base_price'] ?? 0);
    $min_qty = (int)($_POST['min_order_qty'] ?? 1);
    $max_qty = (int)($_POST['max_order_qty'] ?? 9999);
    $weight = (float)($_POST['weight'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    if ($product_id && $name && $base_price >= 0) {
        $stmt = $pdo->prepare("UPDATE products SET category_id = ?, name = ?, description = ?, base_price = ?, min_order_qty = ?, max_order_qty = ?, weight = ?, is_active = ?, is_featured = ? WHERE id = ?");
        if ($stmt->execute([$cat_id, $name, $description, $base_price, $min_qty, $max_qty, $weight, $is_active, $is_featured, $product_id])) {
            
            // Re-sync Price Tiers (Soft-delete old ones)
            $pdo->prepare("UPDATE product_price_tiers SET is_deleted = 1 WHERE product_id = ?")->execute([$product_id]);
            if (!empty($_POST['tier_qty'])) {
                $tstmt = $pdo->prepare("INSERT INTO product_price_tiers (product_id, min_qty, unit_price) VALUES (?, ?, ?)");
                foreach ($_POST['tier_qty'] as $idx => $t_qty) {
                    $t_price = (float)($_POST['tier_price'][$idx] ?? 0);
                    if ($t_qty > 0 && $t_price > 0) {
                        $tstmt->execute([$product_id, (int)$t_qty, $t_price]);
                    }
                }
            }

            // Re-sync Variants (Soft-delete old ones)
            $pdo->prepare("UPDATE product_variants SET is_deleted = 1 WHERE product_id = ?")->execute([$product_id]);
            if (!empty($_POST['var_type'])) {
                $vstmt = $pdo->prepare("INSERT INTO product_variants (product_id, variant_type, variant_value, price_modifier, stock_qty) VALUES (?, ?, ?, ?, ?)");
                foreach ($_POST['var_type'] as $idx => $v_type) {
                    $v_val = trim($_POST['var_value'][$idx] ?? '');
                    $v_price = (float)($_POST['var_price'][$idx] ?? 0);
                    $v_stock = (int)($_POST['var_stock'][$idx] ?? 0);
                    if ($v_type && $v_val) {
                        $vstmt->execute([$product_id, $v_type, $v_val, $v_price, $v_stock]);
                    }
                }
            }

            // Handle New Photos Upload (Base64 Canvas Resized)
            if (!empty($_POST['resized_images'])) {
                $upload_dir = "public/uploads/products/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                // Get current images count to enforce hard limit of 4
                $img_count = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_deleted = 0");
                $img_count->execute([$product_id]);
                $current_count = (int)$img_count->fetchColumn();

                foreach ($_POST['resized_images'] as $index => $base64_data) {
                    if (($current_count + $index) >= 4) break; 
                    
                    if (preg_match('/^data:image\/(\w+);base64,/', $base64_data, $type)) {
                        $data = substr($base64_data, strpos($base64_data, ',') + 1);
                        $data = base64_decode($data);
                        
                        if ($data !== false) {
                            $filename = time() . "_" . $index . "_" . uniqid() . ".jpg";
                            $filepath = $upload_dir . $filename;
                            
                            if (file_put_contents($filepath, $data)) {
                                // Set as main if it is the first photo and no main exists
                                $main_check = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_main = 1 AND is_deleted = 0");
                                $main_check->execute([$product_id]);
                                $is_main = ($main_check->fetchColumn() == 0 && $index === 0) ? 1 : 0;

                                $istmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, ?)");
                                $istmt->execute([$product_id, "/bolakausa/" . $filepath, $is_main]);
                            }
                        }
                    }
                }
            }

            $success = "Product '$name' updated successfully.";
            log_action($pdo, $_SESSION['user_id'], 'Updated Product', "Product ID: $product_id", $name);
        }
    } else {
        $error = "Product Name and Base Price are required.";
    }
}

// Handle Image Deletion
if (isset($_GET['delete_image']) && isset($_GET['product_id'])) {
    $img_id = (int)$_GET['delete_image'];
    $pid = (int)$_GET['product_id'];

    // Fetch details to check if is_main
    $stmt = $pdo->prepare("SELECT is_main FROM product_images WHERE id = ? AND product_id = ? AND is_deleted = 0");
    $stmt->execute([$img_id, $pid]);
    $img = $stmt->fetch();

    if ($img) {
        $pdo->prepare("UPDATE product_images SET is_deleted = 1 WHERE id = ?")->execute([$img_id]);
        
        // If deleted image was the main one, promote another image to main
        if ($img['is_main']) {
            $stmt = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ? AND is_deleted = 0 LIMIT 1");
            $stmt->execute([$pid]);
            $next = $stmt->fetch();
            if ($next) {
                $pdo->prepare("UPDATE product_images SET is_main = 1 WHERE id = ?")->execute([$next['id']]);
            }
        }
        $success = "Image deleted successfully.";
    }
}

// Handle Toggle Featured Status
if (isset($_GET['toggle_featured'])) {
    $id = (int)$_GET['toggle_featured'];
    $stmt = $pdo->prepare("SELECT is_featured, name FROM products WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$id]);
    $prod = $stmt->fetch();
    if ($prod) {
        $new_val = $prod['is_featured'] ? 0 : 1;
        $pdo->prepare("UPDATE products SET is_featured = ? WHERE id = ?")->execute([$new_val, $id]);
        log_action($pdo, $_SESSION['user_id'], 'Toggled Featured Product', "Product ID: $id", $new_val ? 'Set Featured' : 'Unset Featured');
        $success = "Product '" . $prod['name'] . "' " . ($new_val ? "marked as featured." : "removed from featured.");
    }
}

// Handle Single Product Fetch for Editing Modal/View
$edit_prod = null;
$edit_prod_tiers = [];
$edit_prod_variants = [];
$edit_prod_images = [];
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_prod = $stmt->fetch();

    if ($edit_prod) {
        $edit_prod_tiers = $pdo->prepare("SELECT * FROM product_price_tiers WHERE product_id = ? AND is_deleted = 0 ORDER BY min_qty ASC");
        $edit_prod_tiers->execute([$edit_id]);
        $edit_prod_tiers = $edit_prod_tiers->fetchAll();

        $edit_prod_variants = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? AND is_deleted = 0");
        $edit_prod_variants->execute([$edit_id]);
        $edit_prod_variants = $edit_prod_variants->fetchAll();

        $edit_prod_images = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? AND is_deleted = 0");
        $edit_prod_images->execute([$edit_id]);
        $edit_prod_images = $edit_prod_images->fetchAll();
    }
}

// Fetch Data
$categories = $pdo->query("SELECT * FROM categories WHERE is_deleted = 0 ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_deleted = 0 ORDER BY p.id DESC")->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-boxes" style="color: var(--primary);"></i>
    Advanced Catalog Console
</div>

<?php if ($error): ?>
    <div style="background: rgba(244, 63, 94, 0.08); color: #991b1b; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.15);">
        <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div style="background: rgba(16, 185, 129, 0.08); color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.15);">
        <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
    </div>
<?php endif; ?>

<!-- TAB NAVIGATION -->
<div style="display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid var(--border-light); padding-bottom: 1rem;">
    <button onclick="switchTab('tab-list')" id="btn-list" class="btn btn-blue" style="background: var(--primary);"><i class="fas fa-list"></i> Catalog List</button>
    <button onclick="switchTab('tab-add')" id="btn-add" class="btn btn-blue" style="background: rgba(15,23,42,0.1); color: var(--secondary);"><i class="fas fa-plus-circle"></i> Add Product</button>
    <button onclick="switchTab('tab-categories')" id="btn-categories" class="btn btn-blue" style="background: rgba(15,23,42,0.1); color: var(--secondary);"><i class="fas fa-tags"></i> Categories</button>
    <?php if ($edit_prod): ?>
        <button onclick="switchTab('tab-edit')" id="btn-edit" class="btn btn-blue" style="background: var(--accent); color: white;"><i class="fas fa-edit"></i> Edit Product (#<?php echo $edit_prod['id']; ?>)</button>
    <?php endif; ?>
</div>

<!-- TAB CONTENT: Catalog List -->
<div id="tab-list" class="tab-content" style="display: block;">
    <style>
        .featured-star-toggle:hover {
            transform: scale(1.2);
        }
    </style>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Base Price</th>
                    <th>Weight/MOQ/Max</th>
                    <th>Stock</th>
                    <th style="text-align: center; width: 80px;">Featured</th>
                    <th>Status</th>
                    <th style="text-align: right; width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td style="font-weight: 800; color: var(--text-muted);">#<?php echo $p['id']; ?></td>
                    <td style="font-weight: 700; color: var(--secondary);"><?php echo e($p['name']); ?></td>
                    <td><span style="background: rgba(15,23,42,0.05); padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 700;"><?php echo e($p['cat_name'] ?? 'Uncategorized'); ?></span></td>
                    <td style="color: var(--primary-dark); font-weight: 800;">$<?php echo number_format($p['base_price'], 2); ?></td>
                    <td style="font-size: 0.85rem; color: var(--text-muted);">
                        <i class="fas fa-weight-hanging"></i> <?php echo $p['weight']; ?>kg<br>
                        <i class="fas fa-box"></i> MOQ: <?php echo $p['min_order_qty']; ?> (Max: <?php echo $p['max_order_qty']; ?>)
                    </td>
                    <td>
                        <?php if ($p['stock_qty'] <= 5): ?>
                            <span style="color: var(--rose); font-weight: 800;"><i class="fas fa-exclamation-circle"></i> <?php echo $p['stock_qty']; ?></span>
                        <?php else: ?>
                            <span style="color: #166534; font-weight: 700;"><?php echo $p['stock_qty']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?php echo $p['is_active'] ? 'rgba(16, 185, 129, 0.08); color: var(--primary)' : 'rgba(244, 63, 94, 0.08); color: var(--rose)'; ?>;">
                            <?php echo $p['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                        </span>
                    </td>
                    <td style="text-align: center; vertical-align: middle;">
                        <a href="/bolakausa/admin/products?toggle_featured=<?php echo $p['id']; ?>" 
                           style="text-decoration: none; font-size: 1.15rem; transition: transform 0.2s ease; display: inline-block;" 
                           class="featured-star-toggle"
                           title="<?php echo $p['is_featured'] ? 'Featured (Click to unset)' : 'Not Featured (Click to set)'; ?>">
                            <?php if ($p['is_featured']): ?>
                                <i class="fas fa-star" style="color: #eab308; filter: drop-shadow(0 0 2px rgba(234, 179, 8, 0.4));"></i>
                            <?php else: ?>
                                <i class="far fa-star" style="color: #cbd5e1;"></i>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td style="text-align: right;">
                        <a href="/bolakausa/admin/products?edit_id=<?php echo $p['id']; ?>#tab-edit" class="btn btn-blue" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px;"><i class="fas fa-pencil-alt"></i> Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- TAB CONTENT: Add Product -->
<div id="tab-add" class="tab-content" style="display: none;">
    <div class="card" style="margin-bottom: 3rem;">
        <h3 style="font-weight: 800; margin-bottom: 1.5rem; color: var(--secondary); border-bottom: 1px solid var(--border-light); padding-bottom: 1rem;">Create New Wholesale Item</h3>
        
        <form method="POST" id="product-form" enctype="multipart/form-data">
            <input type="hidden" name="add_product" value="1">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group" style="margin: 0;">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">No Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Product Name *</label>
                    <input type="text" name="name" placeholder="e.g. Organic Jasmine Rice (50 lb)" required>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Base Price ($) *</label>
                    <input type="number" step="0.01" name="base_price" placeholder="45.00" required>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Unit Weight (kg)</label>
                    <input type="number" step="0.01" name="weight" placeholder="22.68">
                </div>
            </div>
            
            <div style="display: flex; gap: 1.5rem; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap;">
                <div class="form-group" style="margin: 0; flex-direction: row; align-items: center; gap: 0.5rem; display: flex;">
                    <input type="checkbox" name="is_featured" id="is_featured" value="1" style="width: auto;">
                    <label for="is_featured" style="margin: 0; cursor: pointer;"><strong>Featured Product</strong></label>
                </div>
            </div>

            <div class="form-group">
                <label>Product Description</label>
                <textarea name="description" rows="2" placeholder="Brief details about sourcing, packaging, etc."></textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; padding: 1.5rem; background: rgba(15, 23, 42, 0.02); border-radius: 12px; border: 1px dashed var(--border-light);">
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-arrow-down"></i> Min Order Qty (MOQ)</label>
                    <input type="number" name="min_order_qty" value="1">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-arrow-up"></i> Max Order Qty</label>
                    <input type="number" name="max_order_qty" value="9999">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-camera"></i> Photos (Max 4, Auto-resized)</label>
                    <input type="file" id="product_images_input" accept="image/*" multiple style="padding: 0.4rem;">
                    <small style="color: var(--text-muted); font-size: 0.72rem; display: block; margin-top: 0.25rem;">
                        Max 4 images. Width/height auto-converted to 800px max on-device.
                    </small>
                    <div id="image-previews-container" style="display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap;"></div>
                </div>
            </div>

            <!-- Advanced Repeatable Variants -->
            <div class="card" style="border: 1px solid var(--border-light); margin-bottom: 2rem; padding: 1.5rem;">
                <h4 style="margin-bottom: 1rem; color: var(--accent); display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="fas fa-tags"></i> Advanced Variant Tracking</span>
                    <button type="button" class="btn btn-blue" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;" onclick="addVariantRow('var-container')">+ Add Variant Option</button>
                </h4>
                <div id="var-container" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <!-- Rows will be injected here by JS -->
                </div>
            </div>

            <!-- Advanced Repeatable Price Tiers -->
            <div class="card" style="border: 1px solid var(--border-light); margin-bottom: 2rem; padding: 1.5rem;">
                <h4 style="margin-bottom: 1rem; color: var(--primary-dark); display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="fas fa-money-bill-wave"></i> Bulk Tier Pricing Matrix</span>
                    <button type="button" class="btn btn-blue" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;" onclick="addTierRow('tier-container')">+ Add Tier</button>
                </h4>
                <div id="tier-container" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <!-- Rows will be injected here by JS -->
                </div>
            </div>

            <button type="submit" class="btn btn-green" style="width: 100%; justify-content: center; padding: 1rem; font-size: 1.05rem; border-radius: 10px;">
                <i class="fas fa-save"></i> Publish Product to Catalog
            </button>
        </form>
    </div>
</div>

<!-- TAB CONTENT: Edit Product -->
<?php if ($edit_prod): ?>
<div id="tab-edit" class="tab-content" style="display: none;">
    <div class="card" style="margin-bottom: 3rem;">
        <h3 style="font-weight: 800; margin-bottom: 1.5rem; color: var(--secondary); border-bottom: 1px solid var(--border-light); padding-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
            <span>Edit Wholesale Product: <?php echo e($edit_prod['name']); ?></span>
            <a href="/bolakausa/admin/products" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">Cancel</a>
        </h3>
        
        <form method="POST" id="edit-product-form" enctype="multipart/form-data">
            <input type="hidden" name="edit_product" value="1">
            <input type="hidden" name="product_id" value="<?php echo $edit_prod['id']; ?>">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group" style="margin: 0;">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">No Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($edit_prod['category_id'] == $cat['id']) ? 'selected' : ''; ?>><?php echo e($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Product Name *</label>
                    <input type="text" name="name" value="<?php echo e($edit_prod['name']); ?>" required>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Base Price ($) *</label>
                    <input type="number" step="0.01" name="base_price" value="<?php echo $edit_prod['base_price']; ?>" required>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Unit Weight (kg)</label>
                    <input type="number" step="0.01" name="weight" value="<?php echo $edit_prod['weight']; ?>">
                </div>
                <div class="form-group" style="margin: 0; flex-direction: row; align-items: center; gap: 0.5rem; display: flex;">
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1" <?php echo $edit_prod['is_active'] ? 'checked' : ''; ?> style="width: auto;">
                    <label for="edit_is_active" style="margin: 0; cursor: pointer;"><strong>Active in Catalog</strong></label>
                </div>
                <div class="form-group" style="margin: 0; flex-direction: row; align-items: center; gap: 0.5rem; display: flex;">
                    <input type="checkbox" name="is_featured" id="edit_is_featured" value="1" <?php echo $edit_prod['is_featured'] ? 'checked' : ''; ?> style="width: auto;">
                    <label for="edit_is_featured" style="margin: 0; cursor: pointer;"><strong>Featured Product</strong></label>
                </div>
            </div>

            <div class="form-group">
                <label>Product Description</label>
                <textarea name="description" rows="2"><?php echo e($edit_prod['description']); ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; padding: 1.5rem; background: rgba(15, 23, 42, 0.02); border-radius: 12px; border: 1px dashed var(--border-light);">
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-arrow-down"></i> MOQ</label>
                    <input type="number" name="min_order_qty" value="<?php echo $edit_prod['min_order_qty']; ?>">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-arrow-up"></i> Max Order Qty</label>
                    <input type="number" name="max_order_qty" value="<?php echo $edit_prod['max_order_qty']; ?>">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-camera"></i> Upload New Photos</label>
                    <input type="file" id="edit_product_images_input" accept="image/*" multiple style="padding: 0.4rem;">
                    <small style="color: var(--text-muted); font-size: 0.72rem; display: block; margin-top: 0.25rem;">
                        Max 4 total. Auto-resized on-device.
                    </small>
                    <div id="edit-image-previews-container" style="display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap;"></div>
                </div>
            </div>

            <!-- Existing Images List -->
            <?php if ($edit_prod_images): ?>
            <div class="form-group" style="margin-bottom: 2rem;">
                <label>Existing Product Images</label>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <?php foreach ($edit_prod_images as $img): ?>
                        <div style="position: relative; display: inline-block;">
                            <img src="<?php echo e($img['image_path']); ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-light);">
                            <?php if ($img['is_main']): ?>
                                <span style="position: absolute; bottom: 2px; left: 2px; background: var(--primary); color: white; font-size: 8px; font-weight: 800; padding: 1px 4px; border-radius: 3px;">MAIN</span>
                            <?php endif; ?>
                            <a href="/bolakausa/admin/products?product_id=<?php echo $edit_prod['id']; ?>&delete_image=<?php echo $img['id']; ?>#tab-edit" onclick="return confirm('Delete this image?')" style="position: absolute; top: -5px; right: -5px; background: var(--rose); color: white; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; text-decoration: none;"><i class="fas fa-times"></i></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Edit Variants Rows -->
            <div class="card" style="border: 1px solid var(--border-light); margin-bottom: 2rem; padding: 1.5rem;">
                <h4 style="margin-bottom: 1rem; color: var(--accent); display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="fas fa-tags"></i> Advanced Variant Tracking</span>
                    <button type="button" class="btn btn-blue" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;" onclick="addVariantRow('edit-var-container')">+ Add Variant Option</button>
                </h4>
                <div id="edit-var-container" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($edit_prod_variants as $idx => $v): ?>
                        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;" class="var-row">
                            <div class="form-group" style="margin: 0; flex: 1; min-width: 120px;">
                                <input type="text" name="var_type[]" value="<?php echo e($v['variant_type']); ?>" placeholder="Type (e.g. Size, Packaging)" required>
                            </div>
                            <div class="form-group" style="margin: 0; flex: 1; min-width: 120px;">
                                <input type="text" name="var_value[]" value="<?php echo e($v['variant_value']); ?>" placeholder="Value (e.g. 50lb, Burlap)" required>
                            </div>
                            <div class="form-group" style="margin: 0; width: 100px;">
                                <input type="number" step="0.01" name="var_price[]" value="<?php echo $v['price_modifier']; ?>" placeholder="Price mod ($)">
                            </div>
                            <div class="form-group" style="margin: 0; width: 100px;">
                                <input type="number" name="var_stock[]" value="<?php echo $v['stock_qty']; ?>" placeholder="Stock">
                            </div>
                            <button type="button" class="btn btn-red" style="padding: 0.65rem 0.75rem; font-size: 0.85rem;" onclick="this.parentElement.remove()"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Edit Pricing Tiers Rows -->
            <div class="card" style="border: 1px solid var(--border-light); margin-bottom: 2rem; padding: 1.5rem;">
                <h4 style="margin-bottom: 1rem; color: var(--primary-dark); display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="fas fa-money-bill-wave"></i> Bulk Tier Pricing Matrix</span>
                    <button type="button" class="btn btn-blue" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;" onclick="addTierRow('edit-tier-container')">+ Add Tier</button>
                </h4>
                <div id="edit-tier-container" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($edit_prod_tiers as $idx => $t): ?>
                        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;" class="tier-row">
                            <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
                                <input type="number" name="tier_qty[]" value="<?php echo $t['min_qty']; ?>" placeholder="Min Qty (e.g. 50)" required>
                            </div>
                            <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
                                <input type="number" step="0.01" name="tier_price[]" value="<?php echo $t['unit_price']; ?>" placeholder="Unit Price ($)" required>
                            </div>
                            <button type="button" class="btn btn-red" style="padding: 0.65rem 0.75rem; font-size: 0.85rem;" onclick="this.parentElement.remove()"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-green" style="width: 100%; justify-content: center; padding: 1rem; font-size: 1.05rem; border-radius: 10px;">
                <i class="fas fa-save"></i> Save Product Changes
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- TAB CONTENT: Categories -->
<div id="tab-categories" class="tab-content" style="display: none;">
    <div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 2.5rem; align-items: flex-start; flex-wrap: wrap;">
        <!-- Left: Category List -->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">ID</th>
                        <th style="width: 35%;">Category Name</th>
                        <th>Description</th>
                        <th style="text-align: right; width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td style="font-weight: 800; color: var(--text-muted);">#<?php echo $cat['id']; ?></td>
                        <td><strong style="color: var(--secondary);"><?php echo e($cat['name']); ?></strong></td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo e($cat['description']); ?></td>
                        <td style="text-align: right;">
                            <button onclick="triggerEditCategory(<?php echo $cat['id']; ?>, '<?php echo e($cat['name']); ?>', '<?php echo e($cat['description']); ?>')" class="btn btn-blue" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 6px;">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Right: Forms -->
        <div>
            <!-- Add Form -->
            <div class="card" id="category-add-card" style="padding: 2rem; margin-bottom: 2rem;">
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.25rem;">Add New Category</h3>
                <form method="POST">
                    <input type="hidden" name="add_category" value="1">
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="cat_name" placeholder="e.g. Dairy, Frozen Foods" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label>Description</label>
                        <textarea name="cat_desc" rows="2" placeholder="Brief explanation of items in this category"></textarea>
                    </div>
                    <button type="submit" class="btn btn-green" style="width: 100%; border-radius: 8px;">
                        <i class="fas fa-plus"></i> Create Category
                    </button>
                </form>
            </div>

            <!-- Edit Form (Hidden by default) -->
            <div class="card" id="category-edit-card" style="padding: 2rem; display: none; border-color: var(--accent);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--secondary); margin: 0;">Edit Category</h3>
                    <button onclick="closeEditCategory()" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 4px;"><i class="fas fa-times"></i></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="edit_category" value="1">
                    <input type="hidden" name="category_id" id="edit-cat-id">
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="cat_name" id="edit-cat-name" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label>Description</label>
                        <textarea name="cat_desc" id="edit-cat-desc" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-blue" style="width: 100%; border-radius: 8px;">
                        <i class="fas fa-save"></i> Save Category Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    
    document.querySelectorAll('[id^="btn-"]').forEach(btn => {
        btn.style.background = 'rgba(15,23,42,0.05)';
        btn.style.color = 'var(--secondary)';
    });

    const target = document.getElementById(tabId);
    if (target) target.style.display = 'block';
    
    const activeBtn = document.getElementById('btn-' + tabId.replace('tab-', ''));
    if (activeBtn) {
        activeBtn.style.background = 'var(--primary)';
        activeBtn.style.color = 'white';
    }
}

function triggerEditCategory(id, name, desc) {
    document.getElementById('category-add-card').style.display = 'none';
    
    const editCard = document.getElementById('category-edit-card');
    editCard.style.display = 'block';
    
    document.getElementById('edit-cat-id').value = id;
    document.getElementById('edit-cat-name').value = name;
    document.getElementById('edit-cat-desc').value = desc;
    
    editCard.scrollIntoView({ behavior: 'smooth' });
}

function closeEditCategory() {
    document.getElementById('category-edit-card').style.display = 'none';
    document.getElementById('category-add-card').style.display = 'block';
}

// Javascript functions to add repeatable rows for Variants and Price Tiers
function addVariantRow(containerId) {
    const container = document.getElementById(containerId);
    const row = document.createElement('div');
    row.style.display = 'flex';
    row.style.gap = '1rem';
    row.style.alignItems = 'center';
    row.style.flexWrap = 'wrap';
    row.className = 'var-row';
    
    row.innerHTML = `
        <div class="form-group" style="margin: 0; flex: 1; min-width: 120px;">
            <input type="text" name="var_type[]" placeholder="Type (e.g. Size, Packaging)" required>
        </div>
        <div class="form-group" style="margin: 0; flex: 1; min-width: 120px;">
            <input type="text" name="var_value[]" placeholder="Value (e.g. 50lb, Burlap)" required>
        </div>
        <div class="form-group" style="margin: 0; width: 100px;">
            <input type="number" step="0.01" name="var_price[]" placeholder="Price mod ($)" value="0.00">
        </div>
        <div class="form-group" style="margin: 0; width: 100px;">
            <input type="number" name="var_stock[]" placeholder="Stock" value="0">
        </div>
        <button type="button" class="btn btn-red" style="padding: 0.65rem 0.75rem; font-size: 0.85rem;" onclick="this.parentElement.remove()"><i class="fas fa-trash-alt"></i></button>
    `;
    container.appendChild(row);
}

function addTierRow(containerId) {
    const container = document.getElementById(containerId);
    const row = document.createElement('div');
    row.style.display = 'flex';
    row.style.gap = '1rem';
    row.style.alignItems = 'center';
    row.style.flexWrap = 'wrap';
    row.className = 'tier-row';
    
    row.innerHTML = `
        <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
            <input type="number" name="tier_qty[]" placeholder="Min Qty (e.g. 50)" required>
        </div>
        <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
            <input type="number" step="0.01" name="tier_price[]" placeholder="Unit Price ($)" required>
        </div>
        <button type="button" class="btn btn-red" style="padding: 0.65rem 0.75rem; font-size: 0.85rem;" onclick="this.parentElement.remove()"><i class="fas fa-trash-alt"></i></button>
    `;
    container.appendChild(row);
}

// Canvas Resizer logic
function setupResizer(inputElementId, formElementId, previewContainerId) {
    const fileInput = document.getElementById(inputElementId);
    const previewsContainer = document.getElementById(previewContainerId);
    const form = document.getElementById(formElementId);
    
    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            previewsContainer.innerHTML = '';
            
            // Clean up any previously created hidden fields for this form
            const oldHiddenFields = form.querySelectorAll('input[name="resized_images[]"]');
            oldHiddenFields.forEach(el => el.remove());
            
            let files = Array.from(e.target.files);
            if (files.length > 4) {
                alert("You can upload a maximum of 4 images.");
                files = files.slice(0, 4);
            }
            
            files.forEach((file, index) => {
                if (!file.type.startsWith('image/')) return;
                
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        let width = img.width;
                        let height = img.height;
                        const max_size = 800;
                        
                        if (width > height) {
                            if (width > max_size) {
                                height *= max_size / width;
                                width = max_size;
                            }
                        } else {
                            if (height > max_size) {
                                width *= max_size / height;
                                height = max_size;
                            }
                        }
                        
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        
                        const base64Data = canvas.toDataURL('image/jpeg', 0.85);
                        
                        const previewWrapper = document.createElement('div');
                        previewWrapper.style.position = 'relative';
                        previewWrapper.style.display = 'inline-block';
                        
                        const previewImg = document.createElement('img');
                        previewImg.src = base64Data;
                        previewImg.style.width = '70px';
                        previewImg.style.height = '70px';
                        previewImg.style.objectFit = 'cover';
                        previewImg.style.borderRadius = '8px';
                        previewImg.style.border = '1px solid var(--border-light)';
                        
                        const previewBadge = document.createElement('span');
                        previewBadge.innerText = index === 0 ? "NEW MAIN" : index + 1;
                        previewBadge.style.position = 'absolute';
                        previewBadge.style.bottom = '2px';
                        previewBadge.style.left = '2px';
                        previewBadge.style.background = 'rgba(15,23,42,0.7)';
                        previewBadge.style.color = 'white';
                        previewBadge.style.fontSize = '8px';
                        previewBadge.style.fontWeight = '800';
                        previewBadge.style.padding = '1px 4px';
                        previewBadge.style.borderRadius = '3px';
                        
                        previewWrapper.appendChild(previewImg);
                        previewWrapper.appendChild(previewBadge);
                        previewsContainer.appendChild(previewWrapper);
                        
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'resized_images[]';
                        hiddenInput.value = base64Data;
                        form.appendChild(hiddenInput);
                    };
                    img.src = event.target.result;
                };
                reader.readAsDataURL(file);
            });
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Initial fields injections
    setupResizer('product_images_input', 'product-form', 'image-previews-container');
    addVariantRow('var-container');
    addTierRow('tier-container');

    <?php if ($edit_prod): ?>
        setupResizer('edit_product_images_input', 'edit-product-form', 'edit-image-previews-container');
        switchTab('tab-edit');
    <?php endif; ?>

    // Auto-switch if URL hash is present
    if (window.location.hash) {
        const hash = window.location.hash.substring(1);
        if (document.getElementById(hash)) {
            switchTab(hash);
        }
    }
});
</script>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
