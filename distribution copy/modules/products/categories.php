<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);

if (isset($_POST['add_category'])) {
    check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);
    $name = sanitize($_POST['name']);
    db_query("INSERT INTO categories (name) VALUES (?)", [$name]);
    log_activity($_SESSION['user_id'], "Added category: $name");
    redirect('modules/products/categories.php', 'Category added.');
}

$categories = fetch_all("SELECT * FROM categories WHERE isDelete = 0");
?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Add Category</strong></div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary w-100">Save Category</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Category List</strong></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?php echo $cat['id']; ?></td>
                            <td><?php echo $cat['name']; ?></td>
                            <td>
                                <a href="edit_category.php?id=<?php echo $cat['id']; ?>" class="text-primary me-2"><i class="fas fa-edit"></i></a>
                                <a href="delete_category.php?id=<?php echo $cat['id']; ?>" class="text-danger" onclick="return confirm('Soft delete this category?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
