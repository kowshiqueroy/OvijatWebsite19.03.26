<?php
/**
 * Entry Point for sobjiwali.com
 * Handles routing and core initialization.
 */

require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/AuthManager.php';

// Check if database is initialized
try {
    $db = Database::getInstance();
    $db->query("SELECT 1 FROM site_settings LIMIT 1");
} catch (Exception $e) {
    // Database or Table missing? Redirect to migration
    if (strpos($_SERVER['REQUEST_URI'], 'migrate_db.php') === false) {
        header("Location: migrate_db.php");
        exit;
    }
}

// Simple Router
$url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : '';

// Map URLs to files
$routes = [
    '' => 'home.php',
    'login' => 'login.php',
    'register' => 'register.php',
    'logout' => 'logout.php',
    'catalog' => 'catalog.php',
    'account' => 'account.php',
    'cart' => 'cart.php',
    'checkout' => 'checkout.php',
    'track' => 'track.php'
];

try {
    if (array_key_exists($url, $routes)) {
        include $routes[$url];
        exit;
    }

    // Dynamic Routing for Product Details
    if (preg_match('/^product\/([a-z0-9-]+)$/', $url, $matches)) {
        $_GET['slug'] = $matches[1];
        include 'product_detail.php';
        exit;
    }

    // Dynamic Routing for Static Pages (Catch-all)
    $stmt = $db->query("SELECT slug FROM static_pages WHERE slug = ?", [$url]);
    if ($stmt->fetch()) {
        $_GET['slug'] = $url;
        include 'static_page.php';
        exit;
    }
} catch (Exception $e) {
    // If we're stuck in a preloader, try to break out or show error
    echo "<script>document.getElementById('preloader')?.remove();</script>";
    echo "<div style='padding:2rem; font-family:sans-serif;'>";
    echo "<h2 style='color:#e53e3e;'>System Maintenance</h2>";
    echo "<p>We're having trouble reaching our farm data. Please try again in a moment.</p>";
    echo "<p><small>Error: " . htmlspecialchars($e->getMessage()) . "</small></p>";
    echo "<a href='migrate_db.php' style='color:#2D5A27; font-weight:700;'>Run System Repair (Migration)</a>";
    echo "</div>";
    exit;
}

// Fallback: 404
header("HTTP/1.0 404 Not Found");
echo "404 - Page Not Found";
