<?php
/**
 * Admin Product Management (List & Add) - Premium Glassmorphic Redesign
 */
restrict_to(['admin', 'manager']);

$error = '';
$success = '';

// Handle Product Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $cat_id = $_POST['category_id'] ?: null;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $base_price = $_POST['base_price'] ?? 0;
    $stock_qty = $_POST['stock_qty'] ?? 0;
    $min_qty = $_POST['min_order_qty'] ?? 1;
    $max_qty = $_POST['max_order_qty'] ?? 9999;
    $weight = $_POST['weight'] ?? 0;

    if ($name && $base_price) {
        $stmt = $pdo->prepare("INSERT INTO products (category_id, name, description, base_price, stock_qty, min_order_qty, max_order_qty, weight) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$cat_id, $name, $description, $base_price, $stock_qty, $min_qty, $max_qty, $weight])) {
            $product_id = $pdo->lastInsertId();
            
            // Handle Price Tiers
            if (!empty($_POST['tier_qty']) && !empty($_POST['tier_price'])) {
                $tstmt = $pdo->prepare("INSERT INTO product_price_tiers (product_id, min_qty, unit_price) VALUES (?, ?, ?)");
                $tstmt->execute([$product_id, $_POST['tier_qty'], $_POST['tier_price']]);
            }

            // Handle Simple Variant (Color/Size)
            if (!empty($_POST['variant_type']) && !empty($_POST['variant_value'])) {
                $vstmt = $pdo->prepare("INSERT INTO product_variants (product_id, variant_type, variant_value, stock_qty) VALUES (?, ?, ?, ?)");
                $vstmt->execute([$product_id, $_POST['variant_type'], $_POST['variant_value'], $stock_qty]);
            }

            // Handle Photo Upload (Basic implementation)
            if (!empty($_FILES['product_image']['name'])) {
                $target_dir = "public/images/products/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                
                $filename = time() . "_" . basename($_FILES["product_image"]["name"]);
                $target_file = $target_dir . $filename;
                
                if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                    $istmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, 1)");
                    $istmt->execute([$product_id, "/" . basename(__DIR__, 2) . "/$target_file"]); // hacky path formatting for simplicity
                }
            }

            $success = "Product '$name' added successfully.";
            log_action($pdo, $_SESSION['user_id'], 'Created Product', null, "Product ID: $product_id");
        }
    } else {
        $error = "Product Name and Base Price are strictly required.";
    }
}

// Fetch Data
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$products = $pdo->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC")->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-boxes" style="color: var(--primary);"></i>
    Advanced Product Catalog
</div>

<?php if ($error): ?>
    <div style="background: rgba(244, 63, 94, 0.1); color: var(--accent); padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.2);">
        <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div style="background: rgba(16, 185, 129, 0.1); color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2);">
        <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
    </div>
<?php endif; ?>

<!-- TAB NAVIGATION -->
<div style="display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid var(--glass-border); padding-bottom: 1rem;">
    <button onclick="switchTab('tab-list')" id="btn-list" class="btn btn-blue" style="background: var(--primary);"><i class="fas fa-list"></i> Catalog List</button>
    <button onclick="switchTab('tab-add')" id="btn-add" class="btn btn-blue" style="background: rgba(15,23,42,0.1); color: var(--secondary);"><i class="fas fa-plus-circle"></i> Add New Product</button>
</div>

<!-- TAB CONTENT: Catalog List -->
<div id="tab-list" class="tab-content" style="display: block;">
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
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td style="font-weight: 800; color: var(--text-muted);">#<?php echo $p['id']; ?></td>
                    <td style="font-weight: 700; color: var(--secondary);"><?php echo e($p['name']); ?></td>
                    <td><span style="background: rgba(15,23,42,0.1); padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 700;"><?php echo e($p['cat_name'] ?? 'Uncategorized'); ?></span></td>
                    <td style="color: var(--primary); font-weight: 800;">$<?php echo number_format($p['base_price'], 2); ?></td>
                    <td style="font-size: 0.85rem; color: var(--text-muted);">
                        <i class="fas fa-weight-hanging"></i> <?php echo $p['weight']; ?>kg<br>
                        <i class="fas fa-box"></i> MOQ: <?php echo $p['min_order_qty']; ?> (Max: <?php echo $p['max_order_qty']; ?>)
                    </td>
                    <td>
                        <?php if ($p['stock_qty'] <= 5): ?>
                            <span style="color: var(--accent); font-weight: 800;"><i class="fas fa-exclamation-circle"></i> <?php echo $p['stock_qty']; ?></span>
                        <?php else: ?>
                            <span style="color: #166534; font-weight: 700;"><?php echo $p['stock_qty']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?php echo $p['is_active'] ? 'rgba(16, 185, 129, 0.1); color: var(--primary)' : 'rgba(244, 63, 94, 0.1); color: var(--accent)'; ?>;">
                            <?php echo $p['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                        </span>
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
        <h3 style="font-weight: 800; margin-bottom: 1.5rem; color: var(--secondary); border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem;">Create New Wholesale Item</h3>
        
        <form method="POST" enctype="multipart/form-data">
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
                    <input type="text" name="name" placeholder="e.g. Organic Almonds 50lb Sack" required>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Base Price ($) *</label>
                    <input type="number" step="0.01" name="base_price" placeholder="100.00" required>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Unit Weight (kg) [For Shipping Math]</label>
                    <input type="number" step="0.01" name="weight" placeholder="22.5">
                </div>
            </div>

            <div class="form-group">
                <label>Product Description</label>
                <textarea name="description" rows="2" placeholder="Brief details about sourcing, packaging, etc."></textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; padding: 1.5rem; background: rgba(15, 23, 42, 0.03); border-radius: 12px; border: 1px dashed var(--glass-border);">
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-cubes"></i> Initial Stock Qty</label>
                    <input type="number" name="stock_qty" value="0">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-arrow-down"></i> Min Order Qty (MOQ)</label>
                    <input type="number" name="min_order_qty" value="1">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-arrow-up"></i> Max Order Qty</label>
                    <input type="number" name="max_order_qty" value="9999">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-camera"></i> Main Photo</label>
                    <input type="file" name="product_image" accept="image/*" style="padding: 0.5rem;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <div style="padding: 1.5rem; border: 1px solid var(--glass-border); border-radius: 12px;">
                    <h4 style="margin-bottom: 1rem; color: var(--primary);">Variant Tracking</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem;">
                        <div style="min-width: 0;">
                            <label style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">Type (e.g. Color, Size)</label>
                            <input type="text" name="variant_type" placeholder="Packaging Type">
                        </div>
                        <div style="min-width: 0;">
                            <label style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">Value</label>
                            <input type="text" name="variant_value" placeholder="Burlap Sack">
                        </div>
                    </div>
                </div>

                <div style="padding: 1.5rem; border: 1px solid var(--glass-border); border-radius: 12px;">
                    <h4 style="margin-bottom: 1rem; color: var(--primary);">Bulk Tier Pricing</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem;">
                        <div style="min-width: 0;">
                            <label style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">Min Qty Trigger</label>
                            <input type="number" name="tier_qty" placeholder="e.g. 50">
                        </div>
                        <div style="min-width: 0;">
                            <label style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">New Unit Price ($)</label>
                            <input type="number" step="0.01" name="tier_price" placeholder="90.00">
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="submit" name="add_product" class="btn btn-green" style="margin-top: 2rem; width: 100%; justify-content: center; padding: 1.25rem; font-size: 1.1rem;">
                <i class="fas fa-save"></i> Publish Product to Catalog
            </button>
        </form>
    </div>
</div>

<script>
function switchTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    
    // Reset all buttons to inactive style
    document.querySelectorAll('[id^="btn-"]').forEach(btn => {
        btn.style.background = 'rgba(15,23,42,0.1)';
        btn.style.color = 'var(--secondary)';
    });

    // Show selected tab
    document.getElementById(tabId).style.display = 'block';
    
    // Highlight active button
    const activeBtn = document.getElementById('btn-' + tabId.replace('tab-', ''));
    activeBtn.style.background = 'var(--primary)';
    activeBtn.style.color = 'white';
}

// Auto-switch if URL hash is present
if (window.location.hash) {
    const hash = window.location.hash.substring(1);
    if (document.getElementById(hash)) {
        switchTab(hash);
    }
}
</script>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
