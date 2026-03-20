<?php
// ============================================================
// setup.php — Run ONCE to create tables & seed data.
// DELETE THIS FILE from the server after running!
// ============================================================
require_once 'config.php';
$pdo = getPDO();

$errors = [];
$success = [];

$tables = [

// Settings
"CREATE TABLE IF NOT EXISTS settings (
    `key`   VARCHAR(100) PRIMARY KEY,
    `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// Loading screen taglines
"CREATE TABLE IF NOT EXISTS taglines (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tagline    VARCHAR(255) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// Admin users
"CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('superadmin','editor') NOT NULL DEFAULT 'editor',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// Product categories
"CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL UNIQUE,
    description TEXT,
    sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// Products
"CREATE TABLE IF NOT EXISTS products (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id   INT UNSIGNED NOT NULL,
    name          VARCHAR(200) NOT NULL,
    slug          VARCHAR(220) NOT NULL UNIQUE,
    short_desc    VARCHAR(300),
    description   TEXT,
    image         VARCHAR(255),
    is_new        TINYINT(1) NOT NULL DEFAULT 0,
    is_featured   TINYINT(1) NOT NULL DEFAULT 0,
    sort_order    INT UNSIGNED NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// Team members
"CREATE TABLE IF NOT EXISTS team_members (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    position   VARCHAR(150) NOT NULL,
    bio        TEXT,
    photo      VARCHAR(255),
    type       ENUM('chairman','md','management') NOT NULL DEFAULT 'management',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// Global presence map countries
"CREATE TABLE IF NOT EXISTS map_countries (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    country    VARCHAR(100) NOT NULL,
    region     VARCHAR(100) NOT NULL,
    pos_x      DECIMAL(5,2) NOT NULL COMMENT 'Percentage X on map image',
    pos_y      DECIMAL(5,2) NOT NULL COMMENT 'Percentage Y on map image',
    is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// Draw entries (reuses contact_submissions with subject='Draw Entry')
// No extra table needed - entries stored in contact_submissions

// Contact submissions
"CREATE TABLE IF NOT EXISTS contact_submissions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    phone      VARCHAR(30),
    subject    VARCHAR(200),
    message    TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// Multiple contact info entries
"CREATE TABLE IF NOT EXISTS contact_info (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label       VARCHAR(100) NOT NULL COMMENT 'e.g. Head Office, Sales, Support',
    address     TEXT,
    phone       VARCHAR(100),
    email       VARCHAR(150),
    whatsapp    VARCHAR(50),
    show_header TINYINT(1) NOT NULL DEFAULT 1,
    show_footer TINYINT(1) NOT NULL DEFAULT 1,
    show_contact_page TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// Stats strip
"CREATE TABLE IF NOT EXISTS stats (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label      VARCHAR(100) NOT NULL,
    value      INT UNSIGNED NOT NULL,
    suffix     VARCHAR(20) DEFAULT '',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($tables as $sql) {
    try {
        $pdo->exec($sql);
        $success[] = 'Table created/verified.';
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}

// --- Seed Data ---

// Default settings
$defaultSettings = [
    'site_name'       => 'Ovijat Food & Beverage Industries Ltd.',
    'site_tagline'    => 'Nourishing Bangladesh, Reaching the World',
    'contact_email'   => 'info@ovijatfood.com',
    'contact_phone'   => '09647000025',
    'contact_address' => 'Dhaka, Bangladesh',
    'facebook_url'    => 'https://facebook.com/ovijatfood',
    'linkedin_url'    => 'https://linkedin.com/company/ovijatfood',
    'youtube_url'     => '',
    'hero_slides'     => '3',
    'site_logo'       => '',
    'hero_slide_1'    => '',
    'hero_slide_2'    => '',
    'hero_slide_3'    => '',
    'site_logo_2'     => '',
    'about_image'     => '',
    'draw_title'      => 'Lucky Draw',
    'draw_description'=> 'Purchase any Ovijat product and enter the product code to participate!',
    'draw_prize'      => 'Amazing Prizes',
    'draw_end_date'   => '2026-12-31',
    'about_short'     => 'Ovijat Food & Beverage Industries Ltd. has been delivering premium quality food products across Bangladesh and beyond since 2015. Rooted in tradition, driven by innovation.',
    'chairman_message'=> 'Our commitment to quality is unwavering. Every product that leaves our facility carries our legacy of excellence and our promise to consumers across the globe.',
    'md_message'      => 'Innovation and integrity are the twin pillars of Ovijat. We continuously invest in research and technology to bring world-class food products to every table.',
    'chairman_name'   => 'Md Mostafizur Rahman',
    'md_name'         => 'Md Shamsul Haque',
    'chairman_title'  => 'Chairman',
    'md_title'        => 'Managing Director',
];

$stmtSetting = $pdo->prepare(
    "INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)"
);
foreach ($defaultSettings as $k => $v) {
    $stmtSetting->execute([$k, $v]);
}
$success[] = 'Settings seeded.';

// Taglines
$taglines = [
    'Nourishing Bangladesh, Reaching the World',
    'Pure. Natural. Premium.',
    'Taste the Tradition of Excellence',
    'From Our Fields to Your Table',
    'Quality You Can Trust',
];
$stmtTag = $pdo->prepare("INSERT IGNORE INTO taglines (tagline, sort_order) VALUES (?, ?)");
foreach ($taglines as $i => $t) {
    $stmtTag->execute([$t, $i + 1]);
}
$success[] = 'Taglines seeded.';

// Stats
$stats = [
    ['label' => 'Products', 'value' => 120, 'suffix' => '+', 'sort_order' => 1],
    ['label' => 'Countries Reached', 'value' => 28, 'suffix' => '+', 'sort_order' => 2],
    ['label' => 'Years of Excellence', 'value' => 10, 'suffix' => '', 'sort_order' => 3],
    ['label' => 'Happy Clients', 'value' => 5000, 'suffix' => '+', 'sort_order' => 4],
];
$stmtStat = $pdo->prepare(
    "INSERT IGNORE INTO stats (label, value, suffix, sort_order) VALUES (?, ?, ?, ?)"
);
foreach ($stats as $s) {
    $stmtStat->execute([$s['label'], $s['value'], $s['suffix'], $s['sort_order']]);
}
$success[] = 'Stats seeded.';

// Categories
$cats = [
    ['Beverages',       'beverages',       'Premium fruit drinks, juices & carbonated beverages.',  1],
    ['Snacks',          'snacks',          'Crispy, flavorful snacks for every occasion.',          2],
  // rice, Oil, Bakery
    ['Rice',            'rice',            'High-quality rice varieties sourced from the best fields.', 3],
    ['Edible Oil',     'edible-oil',     'Pure, healthy cooking oils for delicious meals.',          4],
    ['Bakery', 'bakery-products', 'Freshly baked breads, cakes & pastries made with love.',   5],
];
$stmtCat = $pdo->prepare(
    "INSERT IGNORE INTO categories (name, slug, description, sort_order) VALUES (?, ?, ?, ?)"
);
foreach ($cats as $c) {
    $stmtCat->execute($c);
}
$success[] = 'Categories seeded.';

// Map countries
$countries = [
    ['Bangladesh',    'South Asia',    68.5, 42.0],
    //USA, UK, Germany, Canada, Japan, Australia
    ['USA',           'Americas',      18.0, 33.0],
    ['UK',            'Europe',        47.0, 22.0],
    ['Germany',       'Europe',        50.0, 21.0],
    ['Canada',        'Americas',      20.0, 25.0],
    ['Japan',         'East Asia',     83.0, 30.0],
    ['Australia',     'Oceania',       82.0, 65.0],
    ['India',         'South Asia',    63.0, 38.0],
    ['United Kingdom','Europe',        47.0, 22.0],
    ['United States', 'Americas',      18.0, 33.0],
    ['UAE',           'Middle East',   58.0, 37.0],
    ['Saudi Arabia',  'Middle East',   57.0, 40.0],
    ['Malaysia',      'Southeast Asia',76.0, 48.0],
    ['Singapore',     'Southeast Asia',77.0, 51.0],
    ['Germany',       'Europe',        50.0, 21.0],
 
];
$stmtMap = $pdo->prepare(
    "INSERT IGNORE INTO map_countries (country, region, pos_x, pos_y) VALUES (?, ?, ?, ?)"
);
foreach ($countries as $m) {
    $stmtMap->execute($m);
}
$success[] = 'Map countries seeded.';

// Admin user (password: Admin@Ovijat2025)
$hash = password_hash('Admin@Ovijat2025', PASSWORD_BCRYPT);
$stmtAdmin = $pdo->prepare(
    "INSERT IGNORE INTO admin_users (name, email, password_hash, role)
     VALUES ('Super Admin', 'admin@ovijatfood.com', ?, 'superadmin')"
);
$stmtAdmin->execute([$hash]);
$success[] = 'Admin user created (admin@ovijatfood.com / Admin@Ovijat2025).';

// Upload directory .htaccess protection
$uploadDirs = [
    __DIR__ . '/uploads/products/',
    __DIR__ . '/uploads/team/',
    __DIR__ . '/uploads/logo/',
    __DIR__ . '/uploads/hero/',
    __DIR__ . '/uploads/about/',
];
$htaccess = "Options -Indexes\n<FilesMatch \"\\.php$\">\n  Require all denied\n</FilesMatch>\n";
foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($dir . '.htaccess', $htaccess);
}
$success[] = 'Upload directories secured.';

// Seed default contact_info
$pdo->exec("INSERT IGNORE INTO contact_info (id,label,address,phone,email,sort_order)
 VALUES (1,'Head Office','Dhaka, Bangladesh','+880 1234-567890','info@ovijatfood.com',1)");
$success[] = 'Default contact info seeded.';

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ovijat — Setup</title>
<style>
  body{font-family:monospace;background:#1a3d1c;color:#f7f5f0;padding:2rem}
  h1{color:#C9A84C}
  .ok{color:#6fcf97}.err{color:#C0150F}
  .warn{background:#C9A84C;color:#1a3d1c;padding:1rem;margin-top:2rem;border-radius:4px;font-weight:bold}
</style>
</head>
<body>
<h1>Ovijat Setup</h1>
<?php foreach ($success as $m): ?>
  <p class="ok">✔ <?= htmlspecialchars($m) ?></p>
<?php endforeach; ?>
<?php foreach ($errors as $m): ?>
  <p class="err">✘ <?= htmlspecialchars($m) ?></p>
<?php endforeach; ?>
<div class="warn">⚠ DELETE this file (setup.php) from your server immediately!</div>
</body>
</html>
