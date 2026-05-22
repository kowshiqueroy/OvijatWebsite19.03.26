<?php
/**
 * Admin Product Management
 */
$pageTitle = 'Manage Products';
require_once 'layout_header.php';

$db = Database::getInstance();
$pm = new ProductManager();
$message = '';

// Handle Bulk Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['product_ids'])) {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) die("CSRF invalid.");
    
    $action = $_POST['bulk_action'];
    $ids = array_map('intval', $_POST['product_ids']);
    $idList = implode(',', $ids);

    switch ($action) {
        case 'activate':
            $db->query("UPDATE products SET is_active = 1 WHERE id IN ($idList)");
            $message = "Selected products activated.";
            break;
        case 'deactivate':
            $db->query("UPDATE products SET is_active = 0 WHERE id IN ($idList)");
            $message = "Selected products hidden.";
            break;
        case 'set_category':
            $catId = (int)$_POST['bulk_category_id'];
            $db->query("UPDATE products SET category_id = ? WHERE id IN ($idList)", [$catId]);
            $message = "Selected products category updated.";
            break;
    }
}

// Handle Single Deletion
if (isset($_GET['delete_id'])) {
    if (!AuthManager::verifyCSRFToken($_GET['csrf_token'] ?? '')) die("CSRF invalid.");
    $id = (int)$_GET['delete_id'];
    $db->query("UPDATE products SET is_active = 0 WHERE id = ?", [$id]);
    $message = "Product #$id deactivated.";
}

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$stockFilter = $_GET['stock'] ?? 'all';
$searchQuery = $_GET['q'] ?? '';

$sql = "SELECT p.*, c.name as category_name, 
        (SELECT SUM(quantity_remaining) FROM inventory_batches b 
         JOIN product_variations v ON b.product_variation_id = v.id 
         WHERE v.product_id = p.id) as total_stock
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE 1=1";

$params = [];

if ($statusFilter === 'active') $sql .= " AND p.is_active = 1";
elseif ($statusFilter === 'hidden') $sql .= " AND p.is_active = 0";

if ($stockFilter === 'low') {
    $sql .= " HAVING total_stock < 10 OR total_stock IS NULL";
}

if ($searchQuery) {
    $sql .= " AND (p.name LIKE ? OR p.slug LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$sql .= " ORDER BY p.created_at DESC";
$products = $db->query($sql, $params)->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Product Inventory</h1>
    <div style="display: flex; gap: 10px;">
        <a href="tax_shipping.php" class="btn btn-outline">⚖️ Shipping & Tax</a>
        <a href="categories.php" class="btn btn-outline">📂 Categories</a>
        <a href="product_edit.php" class="btn btn-primary">+ Add New Product</a>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>

<!-- Advanced Filters -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
    <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
        <div style="flex: 1; min-width: 250px;">
            <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search product name or SKU..." style="width: 100%;">
        </div>
        
        <select name="status" style="width: auto;">
            <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>All Status</option>
            <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Active Only</option>
            <option value="hidden" <?php echo $statusFilter == 'hidden' ? 'selected' : ''; ?>>Hidden Only</option>
        </select>

        <select name="stock" style="width: auto;">
            <option value="all" <?php echo $stockFilter == 'all' ? 'selected' : ''; ?>>All Stock Levels</option>
            <option value="low" <?php echo $stockFilter == 'low' ? 'selected' : ''; ?>>Low Stock (< 10)</option>
        </select>

        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($searchQuery || $statusFilter != 'all' || $stockFilter != 'all'): ?>
            <a href="products.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>
    </form>
</div>

<form method="POST" id="bulk-form">
    <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
    
    <div style="display: flex; gap: 1rem; align-items: center; background: var(--white); padding: 1rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1rem;">
        <span style="font-weight: 800; font-size: 0.7rem; color: #718096; text-transform: uppercase;">Bulk Actions:</span>
        <select name="bulk_action" id="bulk-action" style="width: auto;">
            <option value="">-- Select Action --</option>
            <option value="activate">Mark as Active</option>
            <option value="deactivate">Mark as Hidden</option>
            <option value="set_category">Move to Category...</option>
        </select>
        
        <select name="bulk_category_id" id="bulk-category" style="width: auto; display: none;">
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-outline" style="background: var(--sidebar); color: #fff;">Apply</button>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" onclick="toggleAll(this)"></th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Base Price</th>
                    <th>Total Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="8" style="text-align:center; padding: 2rem; opacity: 0.5;">No products found matching your criteria.</td></tr>
                <?php endif; ?>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><input type="checkbox" name="product_ids[]" value="<?php echo $p['id']; ?>"></td>
                    <td>#<?php echo $p['id']; ?></td>
                    <td>
                        <div style="font-weight: 800; color: var(--text);"><?php echo htmlspecialchars($p['name']); ?></div>
                        <div style="font-size: 0.7rem; opacity: 0.5;">slug/<?php echo $p['slug']; ?></div>
                    </td>
                    <td><span class="badge" style="background:#edf2f7; color:#4a5568;"><?php echo htmlspecialchars($p['category_name'] ?? 'Uncategorized'); ?></span></td>
                    <td><strong>$<?php echo number_format($p['base_price'], 2); ?></strong></td>
                    <td>
                        <?php 
                            $stock = (int)$p['total_stock']; 
                            $stockColor = $stock < 5 ? 'var(--error)' : ($stock < 20 ? 'var(--accent)' : 'var(--primary)');
                        ?>
                        <span style="font-weight: 800; color: <?php echo $stockColor; ?>;">
                            <?php echo $stock; ?> units
                        </span>
                    </td>
                    <td>
                        <span class="badge" style="background: <?php echo $p['is_active'] ? '#c6f6d5; color: #22543d;' : '#fed7d7; color: #9b2c2c;'; ?>">
                            <?php echo $p['is_active'] ? 'Active' : 'Hidden'; ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <a href="product_edit.php?id=<?php echo $p['id']; ?>" class="btn btn-outline" title="Edit Details">✏️</a>
                            <a href="?delete_id=<?php echo $p['id']; ?>&csrf_token=<?php echo AuthManager::generateCSRFToken(); ?>" class="btn btn-danger" onclick="return confirm('Deactivate this product?')">🚫</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>

<script>
    function toggleAll(source) {
        checkboxes = document.getElementsByName('product_ids[]');
        for(var i=0, n=checkboxes.length;i<n;i++) {
            checkboxes[i].checked = source.checked;
        }
    }

    document.getElementById('bulk-action').addEventListener('change', function() {
        document.getElementById('bulk-category').style.display = (this.value === 'set_category') ? 'inline-block' : 'none';
    });
</script>

<?php require_once 'layout_footer.php'; ?>