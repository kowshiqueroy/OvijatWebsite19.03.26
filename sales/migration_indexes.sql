-- SQL Migration Script: Add missing indexes for performance scaling
-- Run this on your live database to speed up reports and listings.

USE u312077073_app; -- Update this to your live database name if different

-- 1. ORDERS TABLE
ALTER TABLE orders ADD INDEX idx_company_id (company_id);
ALTER TABLE orders ADD INDEX idx_route_id (route_id);
ALTER TABLE orders ADD INDEX idx_shop_id (shop_id);
ALTER TABLE orders ADD INDEX idx_created_by (created_by);
ALTER TABLE orders ADD INDEX idx_order_date (order_date);
ALTER TABLE orders ADD INDEX idx_order_status (order_status);
ALTER TABLE orders ADD INDEX idx_status (status);

-- 2. SHOPS TABLE
ALTER TABLE shops ADD INDEX idx_company_id (company_id);
ALTER TABLE shops ADD INDEX idx_route_id (route_id);
ALTER TABLE shops ADD INDEX idx_user_id (user_id);
ALTER TABLE shops ADD INDEX idx_status (status);

-- 3. ITEMS TABLE
ALTER TABLE items ADD INDEX idx_company_id (company_id);
ALTER TABLE items ADD INDEX idx_status (status);

-- 4. USERS TABLE
ALTER TABLE users ADD INDEX idx_company_id (company_id);
ALTER TABLE users ADD INDEX idx_status (status);
ALTER TABLE users ADD INDEX idx_role (role);

-- 5. CASH COLLECTIONS TABLE
ALTER TABLE cash_collections ADD INDEX idx_company_id (company_id);
ALTER TABLE cash_collections ADD INDEX idx_route_id (route_id);
ALTER TABLE cash_collections ADD INDEX idx_shop_id (shop_id);
ALTER TABLE cash_collections ADD INDEX idx_collected_by (collected_by);
ALTER TABLE cash_collections ADD INDEX idx_status (status);

-- 6. ORDER ITEMS TABLE (Performance for JOINs)
ALTER TABLE order_items ADD INDEX idx_order_id (order_id);
ALTER TABLE order_items ADD INDEX idx_item_id (item_id);
