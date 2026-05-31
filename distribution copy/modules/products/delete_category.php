<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER]);

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Check if category has products
    $products = fetch_all("SELECT id FROM products WHERE category_id = ? AND isDelete = 0", [$id]);
    
    if (count($products) > 0) {
        redirect('modules/products/categories.php', 'Cannot delete category with active products.', 'danger');
    }

    db_query("UPDATE categories SET isDelete = 1 WHERE id = ?", [$id]);
    log_activity($_SESSION['user_id'], "Soft deleted category ID: $id");
    redirect('modules/products/categories.php', 'Category deleted successfully.');
}

redirect('modules/products/categories.php');
?>
