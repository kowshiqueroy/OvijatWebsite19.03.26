<?php
/**
 * Admin Category Management
 */
$pageTitle = 'Manage Categories';
require_once 'layout_header.php';

$db = Database::getInstance();
$logger = new Logger();
$message = '';

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "Invalid security token.";
    } else {
        $name = $_POST['name'];
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

        if ($id) {
            $db->query("UPDATE categories SET name = ?, slug = ? WHERE id = ?", [$name, $slug, $id]);
            $logger->log('update_category', 'category', $id, "Updated category: $name");
            $message = "Category updated.";
        } else {
            $db->query("INSERT INTO categories (name, slug) VALUES (?, ?)", [$name, $slug]);
            $newId = $db->lastInsertId();
            $logger->log('create_category', 'category', $newId, "Created category: $name");
            $message = "Category added.";
        }
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<h1>Category Management</h1>

<div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem; margin-top: 2rem; align-items: start;">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                <tr>
                    <td>#<?php echo $c['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                    <td><small><?php echo $c['slug']; ?></small></td>
                    <td>
                        <button onclick="editCat(<?php echo $c['id']; ?>, '<?php echo addslashes($c['name']); ?>')" class="btn btn-outline">Edit</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 id="form-title" style="margin-bottom: 1.5rem;">Add New Category</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
            <input type="hidden" name="id" id="cat-id">
            <div class="form-group">
                <label>Category Name</label>
                <input type="text" name="name" id="cat-name" required placeholder="e.g. Root Vegetables">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Save Category</button>
            <button type="button" onclick="resetForm()" class="btn btn-outline" style="width: 100%; margin-top: 0.5rem;">Cancel</button>
        </form>
    </div>
</div>

<script>
    function editCat(id, name) {
        document.getElementById('form-title').innerText = 'Edit Category';
        document.getElementById('cat-id').value = id;
        document.getElementById('cat-name').value = name;
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
    function resetForm() {
        document.getElementById('form-title').innerText = 'Add New Category';
        document.getElementById('cat-id').value = '';
        document.getElementById('cat-name').value = '';
    }
</script>

<?php require_once 'layout_footer.php'; ?>
