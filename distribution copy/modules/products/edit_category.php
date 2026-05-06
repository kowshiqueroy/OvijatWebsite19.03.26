<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);

$id = $_GET['id'] ?? 0;
$category = fetch_one("SELECT * FROM categories WHERE id = ?", [$id]);

if (!$category) {
    redirect('modules/products/categories.php', 'Category not found.', 'danger');
}

if (isset($_POST['update_category'])) {
    $name = sanitize($_POST['name']);
    db_query("UPDATE categories SET name = ? WHERE id = ?", [$name, $id]);
    log_activity($_SESSION['user_id'], "Updated category: $name");
    redirect('modules/products/categories.php', 'Category updated successfully.');
}
?>

<div class="row justify-content-center">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Edit Category</strong></div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo $category['name']; ?>" required>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="categories.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
