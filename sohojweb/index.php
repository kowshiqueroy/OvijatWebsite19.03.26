<?php
require_once __DIR__ . '/includes/config/database.php';

$page = $_GET['page'] ?? 'home';
$page = str_replace(['..', '/', '\\'], '', $page);

$allowedPages = ['home', 'about', 'services', 'portfolio', 'contact', 'estimator', 'invoice', 'careers', 'track', 'team'];

if (!in_array($page, $allowedPages)) {
    require __DIR__ . '/public/404/index.php';
    exit;
}

$pageFile = __DIR__ . '/public/' . $page . '/index.php';

if (file_exists($pageFile)) {
    require $pageFile;
} else {
    require __DIR__ . '/public/404/index.php';
}
