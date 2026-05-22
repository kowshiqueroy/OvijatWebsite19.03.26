<?php
/**
 * Add/Edit Product Form
 */
$pageTitle = 'Edit Product';
require_once 'layout_header.php';

$db = Database::getInstance();
$pm = new ProductManager();
$optimizer = new ImageOptimizer();
$logger = new Logger();
$error = '';
$success = '';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Handle All POST Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please refresh.";
    } else {
        if (isset($_POST['form_type'])) {
            if ($_POST['form_type'] === 'save_variation') {
                $varId = isset($_POST['variation_id']) ? (int)$_POST['variation_id'] : null;
                $modifier = $_POST['name_modifier'];
                $retail = !empty($_POST['price_override']) ? (float)$_POST['price_override'] : null;
                $wholesale = !empty($_POST['wholesale_price']) ? (float)$_POST['wholesale_price'] : null;
                $orig = !empty($_POST['original_price']) ? (float)$_POST['original_price'] : null;
                $rmin = (int)$_POST['retail_min_qty'];
                $wmin = (int)$_POST['wholesale_min_qty'];
                $qbox = (int)$_POST['qty_in_box'];
                $weight = (float)$_POST['box_weight'];

                if ($varId) {
                    // Update
                    $db->query("UPDATE product_variations SET name_modifier = ?, price_override = ?, wholesale_price = ?, original_price = ?, retail_min_qty = ?, wholesale_min_qty = ?, qty_in_box = ?, box_weight = ? WHERE id = ?", 
                               [$modifier, $retail, $wholesale, $orig, $rmin, $wmin, $qbox, $weight, $varId]);
                    $logger->log('update_variation', 'variation', $varId, "Updated variation: $modifier");
                    $success = "Variation updated.";
                } else {
                    // Add New
                    $catSql = "SELECT slug FROM categories WHERE id = (SELECT category_id FROM products WHERE id = ?)";
                    $cat = $db->query($catSql, [$productId])->fetch();
                    $prefix = $cat ? substr(strtoupper($cat['slug']), 0, 3) : 'GEN';
                    $prodSql = "SELECT name FROM products WHERE id = ?";
                    $prodName = $db->query($prodSql, [$productId])->fetch()['name'];
                    $sku = $pm->generateSKU($prefix, $prodName, $modifier);
                    
                    $db->query("INSERT INTO product_variations (product_id, sku, name_modifier, price_override, wholesale_price, original_price, retail_min_qty, wholesale_min_qty, qty_in_box, box_weight) VALUES (?,?,?,?,?,?,?,?,?,?)", 
                               [$productId, $sku, $modifier, $retail, $wholesale, $orig, $rmin, $wmin, $qbox, $weight]);
                    
                    $logger->log('add_variation', 'product', $productId, "Added variation: $modifier");
                    $success = "Variation added.";
                }
            } elseif ($_POST['form_type'] === 'delete_variation') {
                $varId = (int)$_POST['variation_id'];
                $db->query("DELETE FROM product_variations WHERE id = ?", [$varId]);
                $logger->log('delete_variation', 'product', $productId, "Deleted variation #$varId");
                $success = "Variation deleted.";
            } elseif ($_POST['form_type'] === 'add_image') {
                if (!empty($_FILES['product_image']['name'])) {
                    $destDir = __DIR__ . '/../assets/img/products/';
                    $result = $optimizer->processUpload($_FILES['product_image'], $destDir);
                    if ($result) {
                        $filePath = basename($result);
                        $pm->addImage($productId, $filePath, isset($_POST['is_primary']) ? 1 : 0);
                        $logger->log('add_image', 'product', $productId, "Uploaded image: $filePath");
                        $success = "Image uploaded.";
                    }
                }
            } elseif ($_POST['form_type'] === 'delete_image') {
                $imgId = (int)$_POST['image_id'];
                $img = $db->query("SELECT file_path FROM product_images WHERE id = ?", [$imgId])->fetch();
                if ($img) {
                    @unlink(__DIR__ . '/../assets/img/products/' . $img['file_path']);
                    $db->query("DELETE FROM product_images WHERE id = ?", [$imgId]);
                    $success = "Image deleted.";
                }
            } elseif ($_POST['form_type'] === 'add_stock') {
                $varId = (int)$_POST['variation_id'];
                $pm->addInventoryBatch($varId, ['quantity' => (int)$_POST['quantity'], 'cost_price' => (float)$_POST['cost_price']]);
                $logger->log('add_stock', 'variation', $varId, "Added stock batch");
                $success = "Stock added.";
            }
        } else {
            // Main Product Details Save
            $data = [
                'category_id' => $_POST['category_id'] ?: null, 
                'name' => $_POST['name'], 
                'description' => $_POST['description'], 
                'base_price' => (float)$_POST['base_price'], 
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            if ($productId) {
                $db->query("UPDATE products SET category_id = ?, name = ?, description = ?, base_price = ?, is_active = ? WHERE id = ?", 
                           [$data['category_id'], $data['name'], $data['description'], $data['base_price'], $data['is_active'], $productId]);
                $logger->log('update_product', 'product', $productId, "Updated product details");
                $success = "Product updated.";
            } else {
                $productId = $pm->createProduct($data);
                $logger->log('create_product', 'product', $productId, "Created product: {$_POST['name']}");
                header("Location: product_edit.php?id=$productId&success=1");
                exit;
            }
        }
    }
}

if (isset($_GET['success'])) $success = "Product created.";

$product = $productId ? $pm->getProduct($productId) : null;
$images = $productId ? $pm->getImages($productId) : [];
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1><?php echo $productId ? 'Edit Product' : 'Add New Product'; ?></h1>
    <a href="products.php" class="btn btn-outline">&larr; Back to Inventory</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem; align-items: start;">
    <div>
        <div class="card">
            <h3>Primary Information</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">None</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($product['category_id']) && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="5"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                </div>
                <div style="display: flex; gap: 2rem; align-items: center; margin-top: 1rem;">
                    <div class="form-group" style="width: 200px; margin-bottom: 0;">
                        <label>Base Price ($) *</label>
                        <input type="number" step="0.01" name="base_price" value="<?php echo $product['base_price'] ?? '0.00'; ?>" required>
                    </div>
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; cursor: pointer;">
                        <input type="checkbox" name="is_active" <?php echo (!isset($product) || $product['is_active']) ? 'checked' : ''; ?> style="width: auto;">
                        Visible in Catalog
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 2rem; padding: 1rem 2rem;">Save Product Details</button>
            </form>
        </div>

        <?php if ($productId): ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem;">
                    <h3>Variations & Stock</h3>
                    <button class="btn btn-outline" onclick="openVarModal()">+ Add Variation</button>
                </div>
                <div class="table-responsive">
                    <table style="font-size: 0.75rem;">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Modifier</th>
                                <th>Retail/Wholesale</th>
                                <th>Min Qty (R/W)</th>
                                <th>Pkg/Weight</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($product['variations'] as $v): ?>
                            <tr>
                                <td><code><?php echo $v['sku']; ?></code></td>
                                <td><strong><?php echo htmlspecialchars($v['name_modifier'] ?: 'Default'); ?></strong></td>
                                <td>
                                    <div>R: <?php echo $v['price_override'] ? '$'.number_format($v['price_override'], 2) : '<small>Base</small>'; ?></div>
                                    <div style="color:var(--accent);">W: <?php echo $v['wholesale_price'] ? '$'.number_format($v['wholesale_price'], 2) : 'N/A'; ?></div>
                                </td>
                                <td><?php echo $v['retail_min_qty']; ?> / <?php echo $v['wholesale_min_qty']; ?></td>
                                <td><?php echo $v['qty_in_box']; ?> / <?php echo number_format($v['box_weight'], 2); ?>kg</td>
                                <td>
                                    <?php $sCol = $v['total_stock'] < 10 ? 'var(--error)' : 'var(--primary)'; ?>
                                    <span class="badge" style="background:var(--bg); color:<?php echo $sCol; ?>; font-weight:800;"><?php echo (int)$v['total_stock']; ?></span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button class="btn btn-outline" style="padding: 4px 6px;" onclick='editVar(<?php echo json_encode($v); ?>)'>✏️</button>
                                        <button class="btn btn-outline" style="padding: 4px 6px;" onclick="openStockModal(<?php echo $v['id']; ?>, '<?php echo addslashes($v['name_modifier']); ?>')">📦</button>
                                        <form method="POST" onsubmit="return confirm('Delete this variation?')" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                                            <input type="hidden" name="form_type" value="delete_variation">
                                            <input type="hidden" name="variation_id" value="<?php echo $v['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 4px 6px;">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <aside>
        <?php if ($productId): ?>
            <div class="card">
                <h3>Images</h3>
                <form method="POST" enctype="multipart/form-data" style="margin-top:1.5rem; padding:1.2rem; background:var(--bg); border-radius:12px; border:2px dashed var(--border);">
                    <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                    <input type="hidden" name="form_type" value="add_image">
                    <input type="file" name="product_image" accept="image/*" required style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.7rem; margin-bottom:1rem;"><input type="checkbox" name="is_primary" value="1" style="width:auto;"> Set as Primary</label>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Upload WebP</button>
                </form>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 2rem;">
                    <?php foreach ($images as $img): ?>
                        <div style="position:relative; border-radius:10px; overflow:hidden; border:2px solid <?php echo $img['is_primary'] ? 'var(--primary)' : '#eee'; ?>;">
                            <img src="../assets/img/products/<?php echo $img['file_path']; ?>" style="width:100%; height:100px; object-fit:cover;">
                            <form method="POST" style="position:absolute; top:5px; right:5px;">
                                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                                <input type="hidden" name="form_type" value="delete_image">
                                <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                                <button type="submit" style="background:rgba(255,255,255,0.9); border:none; border-radius:4px; padding:2px 5px; cursor:pointer; font-size:10px;">❌</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div style="padding:2rem; text-align:center; opacity:0.5; font-weight:800; border:2px dashed var(--border); border-radius:24px;">
                Save primary details first to manage variations and images.
            </div>
        <?php endif; ?>
    </aside>
</div>

<!-- Modals -->
<div id="varModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width:500px; max-height: 90vh; overflow-y: auto;">
        <h3 id="varTitle">Add Variation</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
            <input type="hidden" name="form_type" value="save_variation">
            <input type="hidden" name="variation_id" id="varId">
            
            <div class="form-group"><label>Modifier (e.g. 500g)</label><input type="text" name="name_modifier" id="varModifier" required></div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group"><label>Retail Price ($)</label><input type="number" step="0.01" name="price_override" id="varRetail"></div>
                <div class="form-group"><label>Wholesale Price ($)</label><input type="number" step="0.01" name="wholesale_price" id="varWholesale" required></div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group"><label>Min Qty (Retail)</label><input type="number" name="retail_min_qty" id="varRMin" value="1" required></div>
                <div class="form-group"><label>Min Qty (Wholesale)</label><input type="number" name="wholesale_min_qty" id="varWMin" value="10" required></div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group"><label>Qty in a Box</label><input type="number" name="qty_in_box" id="varQBox" value="1" required></div>
                <div class="form-group"><label>Weight per Box (kg)</label><input type="number" step="0.01" name="box_weight" id="varWeight" value="0.00" required></div>
            </div>

            <div class="form-group"><label>Original Price / MSRP ($)</label><input type="number" step="0.01" name="original_price" id="varOrig"></div>

            <button type="submit" class="btn btn-primary" style="width:100%;">Save Variation</button>
            <button type="button" onclick="this.closest('#varModal').style.display='none'" class="btn btn-outline" style="width:100%; margin-top:0.5rem;">Cancel</button>
        </form>
    </div>
</div>

<div id="stockModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width:400px;">
        <h3 id="stockTitle">Add Stock</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
            <input type="hidden" name="form_type" value="add_stock">
            <input type="hidden" name="variation_id" id="stockVarId">
            <div class="form-group"><label>Quantity</label><input type="number" name="quantity" required></div>
            <div class="form-group"><label>Cost Price ($)</label><input type="number" step="0.01" name="cost_price" required></div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Receive Batch</button>
            <button type="button" onclick="this.closest('#stockModal').style.display='none'" class="btn btn-outline" style="width:100%; margin-top:0.5rem;">Cancel</button>
        </form>
    </div>
</div>

<script>
    function openVarModal() {
        document.getElementById('varTitle').innerText = 'Add Variation';
        document.getElementById('varId').value = '';
        document.getElementById('varModifier').value = '';
        document.getElementById('varRetail').value = '';
        document.getElementById('varWholesale').value = '';
        document.getElementById('varRMin').value = '1';
        document.getElementById('varWMin').value = '10';
        document.getElementById('varQBox').value = '1';
        document.getElementById('varWeight').value = '0.00';
        document.getElementById('varOrig').value = '';
        document.getElementById('varModal').style.display = 'flex';
    }

    function editVar(v) {
        document.getElementById('varTitle').innerText = 'Edit Variation: ' + v.sku;
        document.getElementById('varId').value = v.id;
        document.getElementById('varModifier').value = v.name_modifier;
        document.getElementById('varRetail').value = v.price_override;
        document.getElementById('varWholesale').value = v.wholesale_price;
        document.getElementById('varRMin').value = v.retail_min_qty;
        document.getElementById('varWMin').value = v.wholesale_min_qty;
        document.getElementById('varQBox').value = v.qty_in_box;
        document.getElementById('varWeight').value = v.box_weight;
        document.getElementById('varOrig').value = v.original_price;
        document.getElementById('varModal').style.display = 'flex';
    }

    function openStockModal(id, name) {
        document.getElementById('stockVarId').value = id;
        document.getElementById('stockTitle').innerText = 'Add Stock: ' + (name || 'Default');
        document.getElementById('stockModal').style.display = 'flex';
    }
</script>

<?php require_once 'layout_footer.php'; ?>