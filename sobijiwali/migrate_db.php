<?php
/**
 * Comprehensive DB Migration & Sync Script
 * Run this in your browser to fix all "Unknown Column" errors.
 */
require_once 'config/config.php';
require_once 'includes/Database.php';

try {
    $db = Database::getInstance();
    echo "<h1>Sobjiwali Comprehensive Migration</h1>";

    // 1. Update Users Table (Enum Role)
    echo "Syncing 'users' table roles...<br>";
    try {
        $db->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'retail', 'wholesale', 'pending_wholesale', 'editor', 'warehouse', 'reports', 'support') NOT NULL DEFAULT 'retail'");
        echo "Done: Updated roles enum.<br>";
    } catch (Exception $e) {
        echo "Error/Skipped: " . $e->getMessage() . "<br>";
    }

    // 2. Create Notifications Table
    echo "<br>Syncing 'notifications' table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `notifications` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `type` VARCHAR(50) NOT NULL, -- 'order_status', 'new_message', 'wallet'
      `target_id` INT, -- order_id, message_id etc
      `title` VARCHAR(255) NOT NULL,
      `message` TEXT,
      `is_read` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Done: notifications table verified.<br>";

    // 3. Update order_messages with is_read
    echo "<br>Updating 'order_messages' table...<br>";
    try {
        $db->query("ALTER TABLE `order_messages` ADD COLUMN IF NOT EXISTS `is_read` TINYINT(1) NOT NULL DEFAULT 0");
        echo "Done: added is_read to order_messages.<br>";
    } catch (Exception $e) { echo "Skipped: " . $e->getMessage() . "<br>"; }

    // 4. Create chat_threads and chat_messages for general B2B support
    echo "<br>Syncing Chat System tables...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `chat_threads` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `customer_id` INT NOT NULL,
      `subject` VARCHAR(255),
      `priority` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
      `status` ENUM('open', 'closed') DEFAULT 'open',
      `last_admin_id` INT, -- Tracks who replied last
      `internal_notes` TEXT, -- Staff only
      `last_message_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT `fk_chat_cust` FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add missing columns if table exists
    $cols = [
        'priority' => "ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium' AFTER subject",
        'last_admin_id' => "INT AFTER status",
        'internal_notes' => "TEXT AFTER last_admin_id"
    ];
    foreach($cols as $col => $def) {
        try { $db->query("ALTER TABLE chat_threads ADD COLUMN $col $def"); } catch(Exception $e) {}
    }

    $db->query("CREATE TABLE IF NOT EXISTS `chat_messages` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `thread_id` INT NOT NULL,
      `sender_id` INT NOT NULL,
      `sender_type` ENUM('admin', 'customer') NOT NULL,
      `message` TEXT NOT NULL,
      `is_read` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT `fk_chat_thread` FOREIGN KEY (`thread_id`) REFERENCES `chat_threads`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Done: Chat tables verified.<br>";

    // Add last_login to users
    try { 
        $db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL"); 
        $db->query("UPDATE users SET last_login_at = NOW() WHERE last_login_at IS NULL");
    } catch(Exception $e) {}

    // 2. Update Orders Table
    echo "<br>Syncing 'orders' table columns...<br>";
    $orderQueries = [
        "ALTER TABLE orders MODIFY COLUMN user_id INT NULL",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS guest_email VARCHAR(255) AFTER user_id",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS guest_name VARCHAR(255) AFTER guest_email",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS guest_phone VARCHAR(50) AFTER guest_name",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_address TEXT AFTER guest_phone",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_state VARCHAR(50) AFTER shipping_address",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS billing_name VARCHAR(255) AFTER shipping_state",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS billing_email VARCHAR(255) AFTER billing_name",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS billing_phone VARCHAR(50) AFTER billing_email",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS billing_address TEXT AFTER billing_phone",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS billing_state VARCHAR(50) AFTER billing_address",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS order_note TEXT AFTER billing_address",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER total_amount",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER shipping_fee",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_method ENUM('stripe', 'wallet', 'mixed', 'bank_transfer') NOT NULL AFTER status",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_details TEXT AFTER stripe_payment_intent_id",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS is_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_details",
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS order_hash VARCHAR(64) AFTER is_paid",
        "ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'authorized', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'",
        "ALTER TABLE product_variations ADD COLUMN IF NOT EXISTS original_price DECIMAL(10, 2) AFTER sku",
        "ALTER TABLE product_variations ADD COLUMN IF NOT EXISTS wholesale_price DECIMAL(10, 2) AFTER price_override",
        "ALTER TABLE product_variations ADD COLUMN IF NOT EXISTS retail_min_qty INT NOT NULL DEFAULT 1 AFTER wholesale_price",
        "ALTER TABLE product_variations ADD COLUMN IF NOT EXISTS wholesale_min_qty INT NOT NULL DEFAULT 1 AFTER retail_min_qty",
        "ALTER TABLE product_variations ADD COLUMN IF NOT EXISTS qty_in_box INT NOT NULL DEFAULT 1 AFTER wholesale_min_qty",
        "ALTER TABLE product_variations ADD COLUMN IF NOT EXISTS box_weight DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER qty_in_box"
    ];

    foreach ($orderQueries as $q) {
        try {
            $db->query($q);
            echo "Executed: " . htmlspecialchars($q) . "<br>";
        } catch (Exception $e) {
            echo "Skipped (Column likely exists): " . $e->getMessage() . "<br>";
        }
    }

    // 3. Ensure user_addresses Table
    echo "<br>Syncing 'user_addresses' table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `user_addresses` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `address_type` ENUM('shipping', 'billing') NOT NULL DEFAULT 'shipping',
      `full_name` VARCHAR(255) NOT NULL,
      `email` VARCHAR(255),
      `phone` VARCHAR(50),
      `address_line1` TEXT NOT NULL,
      `state` VARCHAR(10),
      `notes` TEXT,
      `is_default` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT `fk_address_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add state column if it doesn't exist (for existing tables)
    try {
        $db->query("ALTER TABLE user_addresses ADD COLUMN IF NOT EXISTS `state` VARCHAR(50) AFTER `address_line1` ");
    } catch (Exception $e) { /* Likely exists */ }

    echo "Done: user_addresses table verified.<br>";

    // 4. Create shipping_rates Table
    echo "<br>Syncing 'shipping_rates' table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `shipping_rates` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `min_weight` DECIMAL(10, 2) NOT NULL,
      `max_weight` DECIMAL(10, 2) NOT NULL,
      `rate` DECIMAL(10, 2) NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Done: shipping_rates table verified.<br>";

    // 5. Create state_taxes Table
    echo "<br>Syncing 'state_taxes' table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `state_taxes` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `state_code` VARCHAR(10) UNIQUE NOT NULL,
      `state_name` VARCHAR(100) NOT NULL,
      `tax_rate` DECIMAL(5, 4) NOT NULL, -- e.g. 0.0500 for 5%
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Done: state_taxes table verified.<br>";

    // 6. Create system_logs Table
    echo "<br>Syncing 'system_logs' table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `system_logs` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `admin_id` INT,
      `action` VARCHAR(255) NOT NULL,
      `target_type` VARCHAR(50),
      `target_id` INT,
      `details` TEXT,
      `ip_address` VARCHAR(45),
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Done: system_logs table verified.<br>";

    // 5. Create order_messages Table
    echo "<br>Syncing 'order_messages' table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `order_messages` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `order_id` INT NOT NULL,
      `sender_id` INT, -- NULL for Guest or System
      `sender_type` ENUM('admin', 'customer') NOT NULL,
      `message` TEXT NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT `fk_msg_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Done: order_messages table verified.<br>";

    // 6. Create static_pages Table
    echo "<br>Syncing 'static_pages' table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `static_pages` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `slug` VARCHAR(50) UNIQUE NOT NULL,
      `title` VARCHAR(255) NOT NULL,
      `content` TEXT,
      `location` ENUM('header', 'footer', 'both', 'none') DEFAULT 'footer',
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Robust check for 'location' column
    $checkLocation = $db->query("SHOW COLUMNS FROM `static_pages` LIKE 'location'")->fetch();
    if (!$checkLocation) {
        try {
            $db->query("ALTER TABLE `static_pages` ADD COLUMN `location` ENUM('header', 'footer', 'both', 'none') DEFAULT 'footer' AFTER `content` ");
            echo "Successfully added 'location' column.<br>";
        } catch (Exception $e) {
            echo "Notice: Could not add location column manually: " . $e->getMessage() . "<br>";
        }
    }
    
    // Ensure 'about' exists as a default
    try {
        $db->query("INSERT IGNORE INTO static_pages (slug, title, content, location) VALUES ('about', 'Our Story', '<h1>Welcome to Sobjiwali</h1><p>Fresh organic produce delivered to your door.</p>', 'both')");
    } catch (Exception $e) {
        echo "Notice: Could not insert default page: " . $e->getMessage() . "<br>";
    }
    
    echo "Done: static_pages table verified.<br>";

    // 7. Create site_settings Table
    echo "<br>Syncing 'site_settings' table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `site_settings` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `setting_key` VARCHAR(50) UNIQUE NOT NULL,
      `setting_value` TEXT,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 10. Create canned_responses Table
    echo "<br>Syncing 'canned_responses' table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `canned_responses` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `title` VARCHAR(100) NOT NULL,
      `message` TEXT NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Seed basic replies
    $replies = [
        ['Order Update', "Hello! We are currently processing your order and it will be dispatched shortly. You will receive a notification as soon as it's on the way."],
        ['Delivery Help', "Our delivery team is out in your area today. You can expect your harvest within the next 2-4 hours. Thank you for your patience!"],
        ['Wholesale Inquiry', "Thank you for your interest in our wholesale program! A dedicated account manager will reach out to you within 24 hours with our full catalog and bulk pricing."],
        ['Refund/Return', "We are sorry to hear about the issue with your produce. Please send us a photo of the items, and we will credit your internal wallet immediately."]
    ];
    foreach($replies as $r) {
        $db->query("INSERT IGNORE INTO canned_responses (title, message) VALUES (?, ?)", [$r[0], $r[1]]);
    }
    echo "Done: canned_responses verified.<br>";

    // Initialize Default Settings (Updated with greeting)
    $defaults = [
        'site_logo' => '',
        'favicon' => '',
        'preloader_logo' => '🌿',
        'preloader_image' => '',
        'festival_text' => '',
        'auto_greeting' => 'Hello! Welcome to Sobjiwali Fresh Support. How can we help you today?',
        'contact_phone' => '+1 234 567 890',
        'contact_email' => 'hello@sobjiwali.com',
        'store_address' => '123 Fresh Lane, Green Valley',
        'stripe_publishable_key' => 'pk_test_placeholder',
        'stripe_secret_key' => 'sk_test_placeholder',
        'facebook_url' => '',
        'twitter_url' => '',
        'instagram_url' => ''
    ];
    foreach($defaults as $k => $v) {
        $db->query("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (?, ?)", [$k, $v]);
    }
    echo "Done: site_settings verified and defaults initialized.<br>";

    // Ensure all banners are active
    echo "Activating all hero banners...<br>";
    $db->query("UPDATE hero_slides SET is_active = 1");
    echo "Done: Banners activated.<br>";

    // 8. Create hero_slides Table
    echo "<br>Syncing 'hero_slides' table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `hero_slides` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `image_path` VARCHAR(255) NOT NULL,
      `title` VARCHAR(255),
      `subtitle` TEXT,
      `sort_order` INT DEFAULT 0,
      `is_active` TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Done: hero_slides table verified.<br>";

    // 9. Create payment_gateways Table
    echo "<br>Syncing 'payment_gateways' table...<br>";
    $db->query("CREATE TABLE IF NOT EXISTS `payment_gateways` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `gateway_name` VARCHAR(100) NOT NULL,
      `gateway_type` ENUM('stripe', 'manual') NOT NULL DEFAULT 'manual',
      `icon_emoji` VARCHAR(10) DEFAULT '🏦',
      `details_html` TEXT,
      `is_active` TINYINT(1) DEFAULT 1,
      `sort_order` INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Seed default Stripe if not exists
    $db->query("INSERT IGNORE INTO payment_gateways (gateway_name, gateway_type, icon_emoji, is_active) VALUES ('Secure Card Payment', 'stripe', '💳', 1)");
    
    echo "Done: payment_gateways verified.<br>";

    echo "<br><h2 style='color:green'>COMPREHENSIVE MIGRATION SUCCESSFUL!</h2>";
    echo "<p>Please try your checkout again now.</p>";

} catch (Exception $e) {
    die("<h2 style='color:red'>Migration Failed:</h2> " . $e->getMessage());
}
