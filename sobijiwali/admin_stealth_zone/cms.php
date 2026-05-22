<?php
/**
 * Admin Static CMS Module
 */
$pageTitle = 'Content Management';
require_once 'layout_header.php';

$db = Database::getInstance();
$logger = new Logger();
$message = '';
$error = '';

// Handle Page Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['page_content'])) {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token invalid.";
    } else {
        $slug = $_POST['page_slug'];
        $title = $_POST['page_title'];
        $content = $_POST['page_content'];
        $location = $_POST['page_location'] ?? 'footer';
        
        $exists = $db->query("SELECT id FROM static_pages WHERE slug = ?", [$slug])->fetch();
        if ($exists) {
            $db->query("UPDATE static_pages SET title = ?, content = ?, location = ? WHERE slug = ?", [$title, $content, $location, $slug]);
        } else {
            $db->query("INSERT INTO static_pages (slug, title, content, location) VALUES (?, ?, ?, ?)", [$slug, $title, $content, $location]);
        }
        
        $logger->log('update_cms_page', 'cms', null, "Updated page: $slug");
        $message = "Page '$title' saved successfully.";
    }
}

// Fetch Pages
$pages = $db->query("SELECT * FROM static_pages ORDER BY title ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Content Management (CMS)</h1>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 450px; gap: 2rem; align-items: start;">
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Page Title</th>
                    <th>Slug</th>
                    <th>Last Updated</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $p): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($p['title']); ?></strong></td>
                    <td><code>/<?php echo $p['slug']; ?></code></td>
                    <td><small><?php echo date('M d, Y', strtotime($p['updated_at'])); ?></small></td>
                    <td>
                        <button onclick='editPage(<?php echo json_encode($p); ?>)' class="btn btn-outline">Edit</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pages)): ?>
                    <tr><td colspan="4" style="text-align:center; padding:2rem; opacity:0.5;">No static pages created yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 id="form-title" style="margin-bottom: 1.5rem;">Create / Edit Page</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label>Page Title</label>
                <input type="text" name="page_title" id="page_title" required placeholder="e.g. Privacy Policy">
            </div>

            <div class="form-group">
                <label>URL Slug</label>
                <input type="text" name="page_slug" id="page_slug" required placeholder="e.g. privacy-policy">
            </div>

            <div class="form-group">
                <label>Display Location</label>
                <select name="page_location" id="page_location">
                    <option value="footer">Footer Only</option>
                    <option value="header">Header Only</option>
                    <option value="both">Both Header & Footer</option>
                    <option value="none">Hidden from Navigation</option>
                </select>
            </div>

            <div class="form-group">
                <label>Content (HTML Allowed)</label>
                <textarea name="page_content" id="page_content" style="height: 400px;" required placeholder="Write your content here..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">Save Page Content</button>
            <button type="button" onclick="resetForm()" class="btn btn-outline" style="width: 100%; margin-top: 0.5rem;">Reset Form</button>
        </form>
    </div>

</div>

<script>
    function editPage(page) {
        document.getElementById('form-title').innerText = 'Edit: ' + page.title;
        document.getElementById('page_title').value = page.title;
        document.getElementById('page_slug').value = page.slug;
        document.getElementById('page_location').value = page.location;
        document.getElementById('page_content').value = page.content;
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
    function resetForm() {
        document.getElementById('form-title').innerText = 'Create / Edit Page';
        document.getElementById('page_title').value = '';
        document.getElementById('page_slug').value = '';
        document.getElementById('page_content').value = '';
    }
</script>

<?php require_once 'layout_footer.php'; ?>
