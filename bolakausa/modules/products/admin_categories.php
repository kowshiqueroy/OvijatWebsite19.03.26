<?php
/**
 * Admin Category Management
 */
restrict_to(['admin', 'manager']);

$error = '';
$success = '';

// Handle Category Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        if ($stmt->execute([$name, $description])) {
            $success = "Category added successfully.";
            log_action($pdo, $_SESSION['user_id'], 'Created Category', null, $name);
        }
    } else {
        $error = "Category name is required.";
    }
}

// Fetch Categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-tags" style="color: var(--primary);"></i>
    Manage Categories
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

<div class="card" style="margin-bottom: 2rem;">
    <h3 style="font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem;">Add New Category</h3>
    <form method="POST" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
            <label>Category Name *</label>
            <input type="text" name="name" placeholder="e.g. Produce, Dairy" required>
        </div>
        <div class="form-group" style="margin: 0; flex: 2; min-width: 300px;">
            <label>Description</label>
            <input type="text" name="description" placeholder="Brief details about the category">
        </div>
        <button type="submit" name="add_category" class="btn btn-green" style="padding: 1.15rem 2rem;"><i class="fas fa-plus"></i> Add Category</button>
    </form>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th style="width: 10%;">ID</th>
                <th style="width: 30%;">Name</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr>
                <td style="font-weight: 800; color: var(--text-muted);">#<?php echo $cat['id']; ?></td>
                <td><strong style="color: var(--secondary);"><?php echo e($cat['name']); ?></strong></td>
                <td style="color: var(--text-muted);"><?php echo e($cat['description']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
