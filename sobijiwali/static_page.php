<?php
/**
 * Dynamic Static Page Handler
 */
require_once 'includes/Database.php';

$db = Database::getInstance();
$slug = $_GET['slug'] ?? '';

$page = $db->query("SELECT * FROM static_pages WHERE slug = ?", [$slug])->fetch();

if (!$page) {
    header("HTTP/1.0 404 Not Found");
    echo "404 - Page Not Found";
    exit;
}

$pageTitle = $page['title'];
include 'templates/header.php';
?>

<div class="section-container" style="max-width: 900px; margin: 0 auto; padding-top: 4rem; padding-bottom: 8rem;">
    <h1 style="font-size: 3rem; font-weight: 800; color: var(--primary); margin-bottom: 3rem; text-align: center; letter-spacing: -1px;"><?php echo htmlspecialchars($page['title']); ?></h1>
    
    <div style="background: white; padding: 4rem; border-radius: 30px; box-shadow: var(--card-shadow); border: 1px solid var(--border); line-height: 1.8; font-size: 1.1rem; color: var(--text); opacity: 0.9;">
        <?php echo $page['content']; // Allowing HTML from Admin ?>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
