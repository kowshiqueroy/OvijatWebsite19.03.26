-- Merged & Updated B2B Wholesale E-Commerce Database Schema
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 1. Users Table (Enhanced with Status & Extended Roles)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'manager', 'warehouse', 'viewer', 'wholesale_user', 'editor', 'executive') NOT NULL DEFAULT 'wholesale_user',
  `status` ENUM('pending', 'active', 'suspended') DEFAULT 'pending',
  `full_name` VARCHAR(100),
  `phone` VARCHAR(20),
  `location_id` INT,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. User Addresses (Multiple locations per wholesale user)
CREATE TABLE IF NOT EXISTS `user_addresses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `address_line` TEXT NOT NULL,
  `city` VARCHAR(100),
  `location_id` INT DEFAULT NULL,
  `is_default` TINYINT(1) DEFAULT 0,
  `is_deleted` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Locations (Delivery Matrix)
CREATE TABLE IF NOT EXISTS `locations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `tax_percent` DECIMAL(5, 2) DEFAULT 0.00,
  `base_delivery_charge` DECIMAL(10, 2) DEFAULT 0.00,
  `per_unit_weight_charge` DECIMAL(10, 2) DEFAULT 0.00,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Products (Enhanced with is_featured flag)
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `base_price` DECIMAL(10, 2) NOT NULL,
  `stock_qty` INT DEFAULT 0,
  `min_order_qty` INT DEFAULT 1,
  `max_order_qty` INT DEFAULT 9999,
  `weight` DECIMAL(10, 2) DEFAULT 0.00,
  `is_featured` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Product Images (Multiple Photos Support)
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_main` TINYINT(1) DEFAULT 0,
  `is_deleted` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Product Variants (Color/Size/Weight specific stock)
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `variant_type` VARCHAR(50),
  `variant_value` VARCHAR(100),
  `price_modifier` DECIMAL(10, 2) DEFAULT 0.00,
  `stock_qty` INT DEFAULT 0,
  `is_deleted` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Product Price Tiers (Bulk Discounts)
CREATE TABLE IF NOT EXISTS `product_price_tiers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `min_qty` INT NOT NULL,
  `unit_price` DECIMAL(10, 2) NOT NULL,
  `is_deleted` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Discounts (With rules and target wholesalers)
CREATE TABLE IF NOT EXISTS `discounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100),
  `discount_type` ENUM('global', 'product_specific') NOT NULL,
  `product_id` INT DEFAULT NULL,
  `percent` DECIMAL(5, 2) DEFAULT 0.00,
  `amount` DECIMAL(10, 2) DEFAULT 0.00,
  `rules` TEXT DEFAULT NULL,
  `target_wholesalers` VARCHAR(255) DEFAULT 'all',
  `start_date` DATETIME,
  `end_date` DATETIME,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_deleted` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Coupons (With start_date, rules, used_count and target wholesalers)
CREATE TABLE IF NOT EXISTS `coupons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `type` ENUM('fixed', 'percentage') NOT NULL,
  `value` DECIMAL(10, 2) NOT NULL,
  `min_spend` DECIMAL(10, 2) DEFAULT 0.00,
  `max_discount` DECIMAL(10, 2) DEFAULT NULL,
  `usage_limit` INT DEFAULT 1,
  `used_count` INT DEFAULT 0,
  `start_date` DATETIME DEFAULT NULL,
  `end_date` DATETIME DEFAULT NULL,
  `expiry_date` DATETIME DEFAULT NULL,
  `rules` TEXT DEFAULT NULL,
  `target_wholesalers` VARCHAR(255) DEFAULT 'all',
  `is_active` TINYINT(1) DEFAULT 1,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Orders (Enhanced with v2 status list, rejection, and refunds)
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `status` ENUM('Pending Payment', 'Payment Verified', 'Confirmed', 'Processing', 'Hold', 'Stock Out', 'Ready to Ship', 'Shipped', 'Out for Delivery', 'Delivered', 'Cancelled', 'Rejected', 'Pending Customer Approval') DEFAULT 'Pending Payment',
  `total_amount` DECIMAL(10, 2) NOT NULL,
  `tax_amount` DECIMAL(10, 2) DEFAULT 0.00,
  `shipping_amount` DECIMAL(10, 2) DEFAULT 0.00,
  `discount_amount` DECIMAL(10, 2) DEFAULT 0.00,
  `coupon_id` INT DEFAULT NULL,
  `payment_method` ENUM('COD', 'Bank Transfer', 'Pay Later', 'Stripe', 'Wallet') NOT NULL,
  `payment_details` TEXT,
  `delivery_address` TEXT,
  `rejection_charge` DECIMAL(10, 2) DEFAULT 0.00,
  `refund_approved` TINYINT(1) DEFAULT 0,
  `pending_change_details` TEXT DEFAULT NULL,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`coupon_id`) REFERENCES `coupons`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Order Items
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `variant_id` INT DEFAULT NULL,
  `qty` INT NOT NULL,
  `price_at_purchase` DECIMAL(10, 2) NOT NULL,
  `is_deleted` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Inventory Lots (With condition photo/selfie)
CREATE TABLE IF NOT EXISTS `inventory_lots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `lot_number` VARCHAR(100) NOT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `shelf_location` VARCHAR(100) DEFAULT NULL,
  `qty_received` INT NOT NULL,
  `qty_remaining` INT NOT NULL,
  `status` ENUM('active', 'expired', 'damaged', 'returned') NOT NULL DEFAULT 'active',
  `batch_photo` VARCHAR(255) DEFAULT NULL,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Order Item Picks (Split LOT picking tracker)
CREATE TABLE IF NOT EXISTS `order_item_picks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_item_id` INT NOT NULL,
  `lot_id` INT NOT NULL,
  `qty` INT NOT NULL,
  `is_deleted` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`order_item_id`) REFERENCES `order_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lot_id`) REFERENCES `inventory_lots`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Order Status History (Audit Trail for Status Changes)
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `changed_by` INT NOT NULL,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Wallet Transactions (Credits/Debits)
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` ENUM('credit', 'debit') NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `description` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. Wallet Top-up Requests (Admin Approval Flow)
CREATE TABLE IF NOT EXISTS `wallet_topups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `payment_method` VARCHAR(50),
  `transaction_id` VARCHAR(100),
  `proof_image` VARCHAR(255),
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `admin_notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME DEFAULT NULL,
  `order_id` INT DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. System Settings (Global Config)
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT,
  `description` VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. System Logs
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `action_type` VARCHAR(100) NOT NULL,
  `old_value` TEXT,
  `new_value` TEXT,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. Chats (With Attachment Links)
CREATE TABLE IF NOT EXISTS `chats` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `admin_id` INT DEFAULT NULL,
  `message` TEXT NOT NULL,
  `sender_role` ENUM('wholesale_user', 'admin', 'manager') NOT NULL,
  `attachment_type` VARCHAR(50) DEFAULT NULL,
  `attachment_id` INT DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 22. Promotions Table
CREATE TABLE IF NOT EXISTS `promotions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `target_wholesalers` VARCHAR(255) DEFAULT 'all',
  `start_date` DATETIME DEFAULT NULL,
  `end_date` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Default Settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES 
('min_order_value', '100.00', 'Minimum cart total required for checkout'),
('company_name', 'Bolakausa Wholesale', 'Official company name for invoices'),
('tax_on_shipping', '0', '1 = Calculate tax on shipping charge too, 0 = Only on products');

COMMIT;
