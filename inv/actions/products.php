<?php
/**
 * actions/products.php - Add product actions
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? '';
$branch_id = $_SESSION['branch_id'];

if ($action === 'add_category') {
    requireRole(['Admin', 'Manager']);
    $name = sanitize($_POST['name']);
    
    if (empty($name)) jsonResponse('error', 'Category name is required.');
    
    $stmt = $pdo->prepare("INSERT INTO categories (branch_id, name) VALUES (?, ?)");
    if ($stmt->execute([$branch_id, $name])) {
        auditLog($pdo, 'Add Category', "Added category: $name");
        jsonResponse('success', 'Category added successfully.');
    } else {
        jsonResponse('error', 'Failed to add category.');
    }
}

if ($action === 'edit_category') {
    requireRole(['Admin', 'Manager']);
    $id = (int)$_POST['id'];
    $name = sanitize($_POST['name']);
    
    if (empty($name)) jsonResponse('error', 'Category name is required.');
    
    $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ? AND branch_id = ?");
    if ($stmt->execute([$name, $id, $branch_id])) {
        auditLog($pdo, 'Edit Category', "Updated category ID: $id to $name");
        jsonResponse('success', 'Category updated successfully.');
    } else {
        jsonResponse('error', 'Failed to update category.');
    }
}

if ($action === 'delete_category') {
    requireRole(['Admin', 'Manager']);
    $id = (int)$_POST['id'];
    
    $stmt = $pdo->prepare("UPDATE categories SET is_deleted = 1 WHERE id = ? AND branch_id = ?");
    if ($stmt->execute([$id, $branch_id])) {
        auditLog($pdo, 'Delete Category', "Soft deleted category ID: $id");
        jsonResponse('success', 'Category deleted successfully.');
    } else {
        jsonResponse('error', 'Failed to delete category.');
    }
}

if ($action === 'add_product') {
    requireRole(['Admin', 'Manager']);
    $name = sanitize($_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $unit_name = sanitize($_POST['unit_name']);
    $conversion_ratio = (int)$_POST['conversion_ratio'];
    $min_sale_price = (float)$_POST['min_sale_price'];
    $prices = $_POST['prices'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO products (branch_id, name, category_id, unit_name, conversion_ratio, min_sale_price) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$branch_id, $name, $category_id, $unit_name, $conversion_ratio, $min_sale_price]);
        $product_id = $pdo->lastInsertId();

        $stmtPrice = $pdo->prepare("INSERT INTO product_prices (product_id, customer_type, pack_price, piece_price) VALUES (?, ?, ?, ?)");
        if (!empty($prices)) {
            foreach ($prices as $type => $p) {
                if (!empty($p['pack']) || !empty($p['piece'])) {
                    $stmtPrice->execute([$product_id, $type, $p['pack'] ?? 0, $p['piece'] ?? 0]);
                }
            }
        }

        $pdo->commit();
        auditLog($pdo, 'Add Product', "Added product: $name");
        jsonResponse('success', 'Product added successfully.');
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse('error', 'Failed to add product: ' . $e->getMessage());
    }
}

if ($action === 'edit_product') {
    requireRole(['Admin', 'Manager']);
    $id = (int)$_POST['id'];
    $name = sanitize($_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $unit_name = sanitize($_POST['unit_name']);
    $conversion_ratio = (int)$_POST['conversion_ratio'];
    $min_sale_price = (float)$_POST['min_sale_price'];
    $prices = $_POST['prices'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE products SET name = ?, category_id = ?, unit_name = ?, conversion_ratio = ?, min_sale_price = ? WHERE id = ? AND branch_id = ?");
        $stmt->execute([$name, $category_id, $unit_name, $conversion_ratio, $min_sale_price, $id, $branch_id]);

        // Update prices
        $pdo->prepare("DELETE FROM product_prices WHERE product_id = ?")->execute([$id]);
        $stmtPrice = $pdo->prepare("INSERT INTO product_prices (product_id, customer_type, pack_price, piece_price) VALUES (?, ?, ?, ?)");
        if (!empty($prices)) {
            foreach ($prices as $type => $p) {
                if (!empty($p['pack']) || !empty($p['piece'])) {
                    $stmtPrice->execute([$id, $type, $p['pack'] ?? 0, $p['piece'] ?? 0]);
                }
            }
        }

        $pdo->commit();
        auditLog($pdo, 'Edit Product', "Updated product ID: $id");
        jsonResponse('success', 'Product updated successfully.');
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse('error', 'Failed to update product: ' . $e->getMessage());
    }
}

if ($action === 'delete_product') {
    requireRole(['Admin', 'Manager']);
    $id = (int)$_POST['id'];
    
    $stmt = $pdo->prepare("UPDATE products SET is_deleted = 1 WHERE id = ? AND branch_id = ?");
    if ($stmt->execute([$id, $branch_id])) {
        auditLog($pdo, 'Delete Product', "Soft deleted product ID: $id");
        jsonResponse('success', 'Product deleted successfully.');
    } else {
        jsonResponse('error', 'Failed to delete product.');
    }
}
?>