<?php
/**
 * modules/products/categories.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Manager']);

$branch_id = $_SESSION['branch_id'];

// Fetch categories for current branch
$stmt = $pdo->prepare("SELECT * FROM categories WHERE branch_id = ? AND is_deleted = 0 ORDER BY name ASC");
$stmt->execute([$branch_id]);
$categories = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Manage Categories</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-plus me-1"></i> Add Category
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="80">ID</th>
                        <th>Category Name</th>
                        <th>Created At</th>
                        <th width="150" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="categoryTableBody">
                    <?php foreach ($categories as $cat): ?>
                    <tr id="category-row-<?php echo $cat['id']; ?>">
                        <td><?php echo $cat['id']; ?></td>
                        <td class="cat-name"><?php echo $cat['name']; ?></td>
                        <td><?php echo formatDate($cat['created_at']); ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-info me-1 edit-cat" data-id="<?php echo $cat['id']; ?>" data-name="<?php echo $cat['name']; ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-cat" data-id="<?php echo $cat['id']; ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="addCategoryForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="editCategoryForm">
            <input type="hidden" name="id" id="edit-cat-id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" id="edit-cat-name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Add Category
    $('#addCategoryForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/products.php?action=add_category', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                location.reload();
            } else {
                alert(res.message);
            }
        }, 'json');
    });

    // Edit Category (Fill Data)
    $('.edit-cat').on('click', function() {
        $('#edit-cat-id').val($(this).data('id'));
        $('#edit-cat-name').val($(this).data('name'));
        $('#editCategoryModal').modal('show');
    });

    // Update Category
    $('#editCategoryForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/products.php?action=edit_category', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                location.reload();
            } else {
                alert(res.message);
            }
        }, 'json');
    });

    // Delete Category
    $('.delete-cat').on('click', function() {
        if (confirm('Are you sure you want to delete this category?')) {
            const id = $(this).data('id');
            $.post('../../actions/products.php?action=delete_category', {id: id}, function(res) {
                if (res.status === 'success') {
                    $('#category-row-' + id).fadeOut();
                } else {
                    alert(res.message);
                }
            }, 'json');
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
